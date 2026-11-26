// Movie page: picking a cinema, day, language, format or time updates the booking panel in place.
// Without JavaScript the same form reloads the page.
(() => {
  const status = document.getElementById('book-status');

  // Puts focus back on the same control in the new panel, or the nearest one that's left.
  function refocus(panel, name, value) {
    if (!name) return;
    const control = panel.querySelector(value !== null ? `button[name="${name}"][value="${CSS.escape(value)}"]:not(:disabled)` : `select[name="${name}"]`)
      || panel.querySelector(`[name="${name}"][aria-pressed="true"]`)
      || panel.querySelector(`button[name="${name}"]:not(:disabled)`)
      || panel.querySelector('select');
    if (control) control.focus();
  }

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('form.book[method="get"]')) return;
    e.preventDefault();

    const submitter = e.submitter && e.submitter.name ? e.submitter : null;
    const params = new URLSearchParams(new FormData(form));
    if (submitter) params.set(submitter.name, submitter.value);
    // The cinema only goes in the request when it changed, because the server remembers it and redirects.
    if (params.get('cinema') === form.dataset.cinema) params.delete('cinema');
    const url = `${form.action}?${params}`;
    const focusName = submitter ? submitter.name : (document.activeElement && document.activeElement.name) || '';
    const focusValue = submitter ? submitter.value : null;

    form.classList.add('busy');
    form.setAttribute('aria-busy', 'true');
    try {
      const res = await fetch(url, { headers: { Accept: 'text/html' } });
      if (!res.ok) throw new Error(String(res.status));
      const page = new DOMParser().parseFromString(await res.text(), 'text/html');
      const fresh = page.querySelector('form.book');
      if (!fresh) throw new Error('No booking panel');
      form.replaceWith(fresh);

      const address = new URL(fresh.dataset.url || res.url, location.href);
      history.replaceState(null, '', address);
      // The header's cinema picker follows, and comes back here after a change.
      const picker = document.getElementById('cinema-pick');
      if (picker && fresh.dataset.cinema) picker.value = fresh.dataset.cinema;
      const back = document.querySelector('.loc input[name="return"]');
      if (back) back.value = address.pathname + address.search;

      if (status) status.textContent = fresh.dataset.status || '';
      refocus(fresh, focusName, focusValue);
    } catch {
      location.href = url;
    }
  });
})();
