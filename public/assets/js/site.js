// Behaviour shared by every page: header search, cinema picker, phone menu, toasts and image fallbacks.
(() => {
  const layer = document.getElementById('layer');
  const toasts = layer && layer.querySelector('.toasts');

  function toast(message, { bad = false, ms = 3400 } = {}) {
    if (!toasts) return;
    const el = document.createElement('div');
    el.className = bad ? 'toast bad' : 'toast';
    el.textContent = message;
    toasts.replaceChildren(el);
    setTimeout(() => el.remove(), ms);
  }

  function csrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Opens a dialog over the page. Focus stays inside it and goes back where it was on close.
  // A dialog that isn't dismissable, like a payment in progress, ignores Escape and clicks outside.
  function openDialog(html, { dismissable = true } = {}) {
    const veil = document.createElement('div');
    veil.className = 'veil';
    veil.innerHTML = html;
    const opener = document.activeElement;
    const close = () => {
      veil.remove();
      if (opener && typeof opener.focus === 'function') opener.focus();
    };
    if (dismissable) veil.addEventListener('cmx:close', close);
    veil.addEventListener('click', (e) => {
      if ((dismissable && e.target === veil) || e.target.closest('[data-close]')) close();
    });
    veil.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab') return;
      const items = [...veil.querySelectorAll('a[href], button, input, iframe, select, textarea')].filter((el) => !el.disabled);
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    layer.appendChild(veil);
    return { veil, close };
  }

  // Shared with the page scripts.
  window.cmx = { toast, csrf, esc, openDialog };

  // Trailers open in a player over the page. Without JavaScript the links go to YouTube.
  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-trailer]');
    if (!link || e.metaKey || e.ctrlKey || e.shiftKey) return;
    const key = link.dataset.trailer;
    if (!/^[\w-]{6,20}$/.test(key)) return;
    e.preventDefault();
    const title = esc(link.dataset.title || 'Trailer');
    const { veil } = openDialog(`
      <div class="tm" role="dialog" aria-modal="true" aria-label="${title} trailer">
        <div class="frame"><iframe src="https://www.youtube-nocookie.com/embed/${key}?autoplay=1&rel=0" title="${title} trailer"
          allow="autoplay; encrypted-media; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
        <div class="cap"><span>${title}</span><span class="cap-links"><a href="https://www.youtube.com/watch?v=${key}" target="_blank" rel="noopener">Open on YouTube</a><button type="button" data-close>Close</button></span></div>
      </div>`);
    veil.querySelector('[data-close]').focus();
  });

  // Remind me asks for a phone number in a sheet. Without JavaScript the link goes to the film page.
  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-remind]');
    if (!link || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault();
    const { veil, close } = openDialog(`
      <form class="sheet remind" role="dialog" aria-modal="true" aria-labelledby="remind-h" method="post" action="/remind.php" novalidate>
        <h2 id="remind-h">Get a reminder</h2>
        <p>We’ll send you a WhatsApp message when ${esc(link.dataset.title)} opens for booking.</p>
        <input type="hidden" name="film" value="${esc(link.dataset.remind)}">
        <label class="phone"><span>+256</span><input name="phone" inputmode="tel" autocomplete="tel-national" placeholder="772 123 456" aria-label="Phone number" required></label>
        <p class="err" role="alert"></p>
        <div class="sheet-btns"><button class="btn btn-sm btn-y" type="submit">Remind me</button><button class="btn btn-sm btn-o" type="button" data-close>Cancel</button></div>
      </form>`);
    const form = veil.querySelector('form');
    const error = form.querySelector('.err');
    form.querySelector('input[name="phone"]').focus();
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      error.textContent = '';
      try {
        const res = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: new FormData(form) });
        const data = await res.json();
        if (res.ok && data.ok) {
          close();
          toast(data.message, { ms: 6000 });
        } else {
          error.textContent = data.message || data.error || 'That didn’t work. Try again.';
        }
      } catch {
        error.textContent = 'We couldn’t reach the server. Check your connection and try again.';
      }
    });
  });

  // Flash messages from the server stay a little longer than ordinary toasts.
  if (toasts) toasts.querySelectorAll('.toast').forEach((el) => setTimeout(() => el.remove(), 5000));

  // Download ticket opens the print dialog; the print stylesheet keeps just the ticket.
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-print]')) window.print();
  });

  // Buttons that only explain something, like the style guide's toast demo.
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-toast]');
    if (el) toast(el.dataset.toast);
  });

  // A poster that fails to load leaves the title stand-in showing.
  document.addEventListener('error', (e) => {
    const img = e.target;
    if (img instanceof HTMLImageElement && img.closest('.pic')) img.remove();
  }, true);

  // Pickers save or update as soon as they change. Listening on the document covers forms
  // that are swapped in later, like the film page's booking panel.
  document.addEventListener('change', (e) => {
    const select = e.target.closest('form[data-autosubmit] select');
    if (select) select.form.requestSubmit();
  });

  // Search: the icon opens the field, then searches once something is typed.
  const search = document.querySelector('.search');
  if (search) {
    const input = search.querySelector('input');
    const button = search.querySelector('button');
    const setOpen = (open) => {
      search.classList.toggle('open', open);
      button.setAttribute('aria-expanded', String(open));
      if (open) input.focus();
    };
    button.addEventListener('click', (e) => {
      if (!search.classList.contains('open')) {
        e.preventDefault();
        setOpen(true);
      } else if (!input.value.trim()) {
        e.preventDefault();
        setOpen(false);
      }
    });
    search.addEventListener('focusout', (e) => {
      if (search.contains(e.relatedTarget)) return;
      search.querySelector('.results').hidden = true;
      if (!input.value.trim()) setOpen(false);
    });
    input.addEventListener('focus', () => {
      const box = search.querySelector('.results');
      if (box.childElementCount) box.hidden = false;
    });

    // Results drop down as you type.
    const results = search.querySelector('.results');
    let timer = null;
    let shown = '';
    const hideResults = () => { results.hidden = true; results.replaceChildren(); shown = ''; };
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        const q = input.value.trim();
        if (q === shown) return;
        if (!q) { hideResults(); return; }
        try {
          const res = await fetch(`/search.php?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
          const { films } = await res.json();
          if (input.value.trim() !== q) return;
          shown = q;
          results.innerHTML = films.length
            ? films.map((f) => `<a href="${esc(f.url)}"><span class="pic">${f.poster ? `<img src="${esc(f.poster)}" alt="">` : ''}</span><span>${esc(f.title)}<small>${esc(f.status)}</small></span></a>`).join('')
            : '<p>No films match that. Try a title or an actor.</p>';
          results.hidden = false;
        } catch {
          hideResults();
        }
      }, 150);
    });
    // Arrow keys move between the input and the results.
    search.addEventListener('keydown', (e) => {
      if ((e.key !== 'ArrowDown' && e.key !== 'ArrowUp') || results.hidden) return;
      const links = [...results.querySelectorAll('a')];
      if (!links.length) return;
      e.preventDefault();
      const at = links.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') links[(at + 1) % links.length].focus();
      else if (at <= 0) input.focus();
      else links[at - 1].focus();
    });
    window.cmx.closeSearch = () => { hideResults(); setOpen(false); };
  }

  // The phone menu closes when you tap outside it.
  const menu = document.querySelector('details.menu');
  document.addEventListener('click', (e) => {
    if (menu && menu.open && !menu.contains(e.target)) menu.open = false;
  });
  if (menu) {
    menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => { menu.open = false; }));
  }

  // Escape closes whatever is open, newest first.
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const veil = layer && layer.querySelector('.veil:last-of-type');
    if (veil) {
      veil.dispatchEvent(new CustomEvent('cmx:close'));
      return;
    }
    if (menu && menu.open) {
      menu.open = false;
      menu.querySelector('summary').focus();
      return;
    }
    if (search && search.classList.contains('open')) {
      search.querySelector('input').value = '';
      window.cmx.closeSearch();
      search.querySelector('button').focus();
    }
  });
})();
