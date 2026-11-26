// Checkout: snack steppers and the live total, the hold timer, and paying. Mobile money shows the
// "Check your phone" sheet and asks the server how it's going until the payment settles.
(() => {
  const form = document.querySelector('form.pay-form');
  if (!form) return;
  const { toast, csrf, esc, openDialog } = window.cmx;
  const money = (n) => `UGX ${n.toLocaleString('en-US')}`;
  const status = document.getElementById('pay-status');
  const button = form.querySelector('.cta');
  const seatsTotal = Number(form.dataset.seatsTotal);
  const seatCount = Number(form.dataset.seatCount);
  const glassesEach = Number(form.dataset.glasses);
  const steppers = [...form.querySelectorAll('.step input')];
  const ownGlasses = form.querySelector('input[name="own_glasses"]');
  let paying = false;

  // Snacks and glasses: the summary and total follow along.
  function recalc() {
    let total = seatsTotal;
    steppers.forEach((input) => {
      const quantity = Math.max(0, Math.min(Number(input.max), parseInt(input.value, 10) || 0));
      const price = Number(input.dataset.price);
      const line = form.querySelector(`[data-line="snack-${input.dataset.id}"]`);
      line.hidden = quantity === 0;
      line.querySelector('.q').textContent = String(quantity);
      line.querySelector('.amt').textContent = money(quantity * price);
      const [minus, plus] = input.parentElement.querySelectorAll('button');
      minus.disabled = quantity === 0;
      plus.disabled = quantity >= Number(input.max);
      total += quantity * price;
    });
    if (ownGlasses) {
      form.querySelector('[data-line="glasses"]').hidden = ownGlasses.checked;
      if (!ownGlasses.checked) total += seatCount * glassesEach;
    }
    form.querySelectorAll('.total').forEach((el) => { el.textContent = money(total); });
    return total;
  }

  form.addEventListener('click', (e) => {
    const step = e.target.closest('.step button');
    if (!step) return;
    const input = step.parentElement.querySelector('input');
    input.value = String(Math.max(0, Math.min(Number(input.max), (parseInt(input.value, 10) || 0) + Number(step.dataset.step))));
    const total = recalc();
    if (status) status.textContent = `${document.getElementById(input.getAttribute('aria-labelledby')).textContent}: ${input.value}. Total ${money(total)}.`;
    if (step.disabled) input.focus(); // the button just switched off, so keep focus nearby
  });
  form.addEventListener('input', (e) => {
    if (e.target.matches('.step input, input[name="own_glasses"]')) recalc();
  });
  form.addEventListener('change', (e) => {
    if (e.target.matches('.step input')) {
      e.target.value = String(Math.max(0, Math.min(Number(e.target.max), parseInt(e.target.value, 10) || 0)));
      recalc();
    }
    if (e.target.name === 'method') {
      const hint = document.getElementById('pay-hint');
      hint.textContent = hint.dataset[e.target.value] || '';
    }
  });

  // The hold timer counts down using the server's clock, then sends them back to pick seats again.
  const hold = document.querySelector('.s-head .hold');
  if (hold) {
    const left = hold.querySelector('b');
    const skew = Number(hold.dataset.now) * 1000 - Date.now();
    const expires = Number(hold.dataset.expires) * 1000;
    let warned = false;
    const tick = () => {
      if (paying) return;
      const seconds = Math.max(0, Math.round((expires - (Date.now() + skew)) / 1000));
      left.textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
      hold.classList.toggle('low', seconds <= 60);
      if (seconds <= 60 && !warned) {
        warned = true;
        if (status) status.textContent = 'One minute left to pay before your seats are released.';
      }
      if (seconds === 0) {
        clearInterval(timer);
        location.href = '/checkout.php'; // the server explains and sends them back to the seat map
      }
    };
    const timer = setInterval(tick, 1000);
    tick();
  }

  const done = () => {
    paying = false;
    button.disabled = false;
  };

  async function post(url, body) {
    const res = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() }, body });
    return { res, data: await res.json() };
  }

  // Mobile money: the sheet stays until the payment settles or they cancel.
  function waitForPhone(payment) {
    const wallet = payment.method === 'airtel' ? 'Airtel Money' : 'MoMo';
    const { veil, close } = openDialog(`
      <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="pay-h2" aria-describedby="pay-p">
        <div class="spin"></div>
        <h2 id="pay-h2">Check your phone</h2>
        <p id="pay-p">We sent a prompt to ${esc(payment.phone)}. Enter your ${wallet} PIN to pay ${esc(money(payment.total))}.</p>
        <button class="btn btn-sm btn-o" type="button" data-cancel>Cancel payment</button>
      </div>`, { dismissable: false });
    let stopped = false;

    veil.querySelector('[data-cancel]').addEventListener('click', async () => {
      stopped = true;
      const body = new FormData();
      body.append('ref', payment.ref);
      try {
        const { data } = await post(payment.cancel, body);
        toast(data.message || 'Payment cancelled.');
      } catch {
        toast('We couldn’t reach the server, but the payment won’t go ahead without your PIN.', { bad: true });
      }
      close();
      done();
    });
    veil.querySelector('[data-cancel]').focus();

    const poll = async () => {
      if (stopped) return;
      try {
        const res = await fetch(payment.poll, { headers: { Accept: 'application/json' } });
        const answer = await res.json();
        if (stopped) return;
        if (answer.status === 'pending') {
          setTimeout(poll, 1500);
          return;
        }
        stopped = true;
        if (answer.status === 'paid') {
          location.href = answer.next;
          return;
        }
        close();
        toast(answer.message || 'The payment didn’t go through.', { bad: true, ms: 7000 });
        if (answer.next && answer.next !== '/checkout.php') setTimeout(() => { location.href = answer.next; }, 2500);
        else done();
      } catch {
        setTimeout(poll, 3000); // a dropped connection isn't a failed payment; keep asking
      }
    };
    setTimeout(poll, 1500);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (paying) return;
    const digits = form.querySelector('input[name="phone"]').value.replace(/\D/g, '');
    if (!/^(7\d{8}|07\d{8}|2567\d{8})$/.test(digits)) {
      toast('Enter the 9 digits of your number after +256, like 772 123 456.', { bad: true });
      form.querySelector('input[name="phone"]').focus();
      return;
    }
    paying = true;
    button.disabled = true;
    let reply;
    try {
      reply = await post(form.action, new FormData(form));
    } catch {
      toast('We couldn’t reach the server. Check your connection and try again.', { bad: true });
      done();
      return;
    }
    const { res, data } = reply;
    if (!res.ok || !data.ok) {
      toast(data.message || 'That didn’t work. Try again.', { bad: true, ms: 6000 });
      if (res.status === 410 && data.redirect) setTimeout(() => { location.href = data.redirect; }, 2500);
      else done();
      return;
    }
    if (data.card) {
      openDialog(`
        <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="pay-h2">
          <div class="spin"></div>
          <h2 id="pay-h2">Opening the card page</h2>
          <p>You’ll pay ${esc(money(data.total))} there and come straight back.</p>
        </div>`, { dismissable: false });
      setTimeout(() => { location.href = data.redirect; }, 900);
      return;
    }
    waitForPhone(data);
  });

  recalc();
})();
