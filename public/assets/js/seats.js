// Seat map: the live total and seat chips, the eight-seat limit, arrow keys around the hall,
// seats greyed out as other people take them, and Checkout holding the seats without a reload
// when someone else got there first.
(() => {
  const form = document.querySelector('form.seat-form');
  if (!form) return;
  const { toast, csrf } = window.cmx;
  const max = Number(form.dataset.max) || 8;
  const inputs = [...form.querySelectorAll('.seat input')];
  const chips = form.querySelector('.chips');
  const total = form.querySelector('.total');
  const cta = form.querySelector('.cta');
  const status = document.getElementById('seat-status');
  const money = (n) => `UGX ${n.toLocaleString('en-US')}`;
  const list = (items) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}` : items[0]);
  const picked = () => inputs.filter((i) => i.checked);

  function update(announce) {
    const seats = picked();
    const sum = seats.reduce((a, i) => a + Number(i.dataset.price), 0);
    chips.replaceChildren(...(seats.length
      ? seats.map((i) => Object.assign(document.createElement('span'), { textContent: i.dataset.label }))
      : [Object.assign(document.createElement('em'), { textContent: chips.dataset.empty })]));
    total.textContent = money(sum);
    cta.disabled = seats.length === 0;
    if (announce && status) {
      status.textContent = seats.length ? `${seats.length} seat${seats.length > 1 ? 's' : ''} picked, ${money(sum)}` : 'No seats picked';
    }
  }

  form.addEventListener('change', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || !input.closest('.seat')) return;
    if (input.checked && picked().length > max) {
      input.checked = false;
      toast(`You can book up to ${max} seats at a time.`);
    }
    update(true);
  });

  // The hall as rows of columns, so the arrow keys can move around it like a grid.
  const grid = [];
  let row = -1;
  let col = 0;
  let inRow = false;
  for (const cell of form.querySelector('.seatgrid').children) {
    if (cell.classList.contains('rl')) {
      if (!inRow) {
        row += 1;
        col = 0;
        grid[row] = [];
      }
      inRow = !inRow;
    } else if (inRow) {
      col += 1;
      const input = cell.querySelector('input');
      if (input) {
        grid[row][col] = input;
        input.dataset.r = String(row);
        input.dataset.c = String(col);
      }
    }
  }
  const usable = (input) => input && !input.disabled;

  function neighbour(from, dr, dc) {
    let r = Number(from.dataset.r);
    let c = Number(from.dataset.c);
    if (dc) {
      for (c += dc; c >= 1 && c < grid[r].length; c += dc) if (usable(grid[r][c])) return grid[r][c];
      return null;
    }
    for (r += dr; r >= 0 && r < grid.length; r += dr) {
      let best = null;
      let distance = Infinity;
      grid[r].forEach((input, at) => {
        if (usable(input) && Math.abs(at - c) < distance) {
          best = input;
          distance = Math.abs(at - c);
        }
      });
      if (best) return best;
    }
    return null;
  }

  // One tab stop for the whole hall; the arrow keys move between seats.
  function rove(to) {
    inputs.forEach((i) => { i.tabIndex = -1; });
    if (to) to.tabIndex = 0;
  }
  const resetStop = () => rove(picked()[0] || inputs.find(usable));
  resetStop();

  form.querySelector('.seatgrid').addEventListener('focusin', (e) => {
    if (e.target.matches('.seat input')) rove(e.target);
  });
  form.querySelector('.seatgrid').addEventListener('keydown', (e) => {
    const step = { ArrowLeft: [0, -1], ArrowRight: [0, 1], ArrowUp: [-1, 0], ArrowDown: [1, 0] }[e.key];
    if (!step || !e.target.matches('.seat input')) return;
    e.preventDefault();
    const to = neighbour(e.target, ...step);
    if (to) {
      rove(to);
      to.focus();
      to.closest('.seat').scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
  });

  // Seats that are now taken get greyed out. A seat this visitor had ticked is marked as just taken.
  function applyTaken(ids, { lost = false } = {}) {
    const taken = new Set(ids.map(String));
    const gone = [];
    inputs.forEach((input) => {
      const seat = input.closest('.seat');
      if (taken.has(input.value)) {
        if (input.disabled) return;
        const wasPicked = input.checked;
        input.checked = false;
        input.disabled = true;
        if (wasPicked || lost) {
          seat.classList.add('lost');
          input.setAttribute('aria-label', `${input.dataset.name}, just taken`);
          if (wasPicked) gone.push(input.dataset.label);
        } else {
          input.setAttribute('aria-label', `${input.dataset.name}, reserved`);
        }
      } else if (input.disabled && !seat.classList.contains('lost')) {
        // A hold ran out, so the seat is free again.
        input.disabled = false;
        input.setAttribute('aria-label', `${input.dataset.name}, ${money(Number(input.dataset.price))}`);
      }
    });
    if (!usable(inputs.find((i) => i.tabIndex === 0))) resetStop();
    update(false);
    return gone;
  }

  async function refresh() {
    if (document.hidden) return;
    try {
      const res = await fetch(location.pathname + location.search, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const { taken } = await res.json();
      const gone = applyTaken(taken);
      if (gone.length) toast(`${list(gone)} ${gone.length > 1 ? 'were' : 'was'} just taken by someone else.`, { bad: true, ms: 6000 });
    } catch {
      // Try again next time.
    }
  }
  setInterval(refresh, 30000);
  document.addEventListener('visibilitychange', refresh);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!picked().length) {
      toast('Pick at least one seat.');
      return;
    }
    cta.disabled = true;
    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() },
        body: new FormData(form),
      });
      const data = await res.json();
      if (data.ok) {
        location.href = data.redirect;
        return;
      }
      if (Array.isArray(data.taken)) applyTaken(data.taken, { lost: true });
      toast(data.message || data.error || 'That didn’t work. Try again.', { bad: true, ms: 6000 });
    } catch {
      toast('We couldn’t reach the server. Check your connection and try again.', { bad: true });
    }
    update(false);
  });

  // On a phone the hall is wider than the screen, so start in the middle.
  const wrap = form.querySelector('.map-wrap');
  wrap.scrollLeft = (wrap.scrollWidth - wrap.clientWidth) / 2;
  update(false);
})();
