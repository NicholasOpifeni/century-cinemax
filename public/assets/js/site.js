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

  // Shared with the page scripts.
  window.cmx = { toast, csrf };

  // Flash messages from the server stay a little longer than ordinary toasts.
  if (toasts) toasts.querySelectorAll('.toast').forEach((el) => setTimeout(() => el.remove(), 5000));

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

  // The cinema picker saves as soon as it changes.
  document.querySelectorAll('form[data-autosubmit] select').forEach((select) => {
    select.addEventListener('change', () => select.form.requestSubmit());
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
      if (!search.contains(e.relatedTarget) && !input.value.trim()) setOpen(false);
    });
    window.cmx.closeSearch = () => setOpen(false);
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
