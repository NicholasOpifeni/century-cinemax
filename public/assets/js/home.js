// Home page: hero carousel and the day pills on Now showing.
(() => {
  const hero = document.querySelector('.hero');
  if (hero) {
    const slides = [...hero.querySelectorAll('.hero-slide')];
    const backdrops = [...hero.querySelectorAll(':scope > .pic')];
    const thumbs = [...hero.querySelectorAll('.carousel a')];
    let current = 0;

    const show = (next) => {
      next = (next + slides.length) % slides.length;
      if (next === current || !slides[next]) return;
      const before = backdrops[current];
      const after = backdrops[next];

      slides[current].hidden = true;
      slides[next].hidden = false;
      after.hidden = false;
      requestAnimationFrame(() => {
        after.classList.add('on');
        before.classList.remove('on');
      });
      setTimeout(() => { if (!before.classList.contains('on')) before.hidden = true; }, 650);

      thumbs.forEach((t, i) => t.setAttribute('aria-current', String(i === next)));
      const rail = thumbs[next].parentElement;
      rail.scrollTo({ left: thumbs[next].offsetLeft - rail.clientWidth / 2 + thumbs[next].clientWidth / 2, behavior: 'smooth' });
      current = next;
      // preview.js listens for this and switches to the new film's trailer.
      hero.dispatchEvent(new CustomEvent('cmx:slide', { detail: { index: next, preview: slides[next].dataset.previewKey || '' } }));
    };

    hero.querySelectorAll('[data-step]').forEach((button) => {
      button.addEventListener('click', () => show(current + Number(button.dataset.step)));
    });
    // A poster switches the hero; pressing the poster that's already showing opens its film page.
    thumbs.forEach((thumb, i) => {
      thumb.addEventListener('click', (e) => {
        if (i !== current) {
          e.preventDefault();
          show(i);
        }
      });
    });
    hero.addEventListener('click', (e) => {
      const more = e.target.closest('.see');
      if (more) {
        const syn = more.closest('.syn');
        syn.classList.add('open');
        syn.querySelector('.syn-full').setAttribute('tabindex', '-1');
        syn.querySelector('.syn-full').focus();
      }
    });
  }

  // Picking a day swaps the Now showing grid in place instead of reloading the page.
  document.addEventListener('click', async (e) => {
    const link = e.target.closest('#now .days a');
    if (!link || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault();
    try {
      const res = await fetch(link.href, { headers: { Accept: 'text/html' } });
      if (!res.ok) throw new Error(res.status);
      const fresh = new DOMParser().parseFromString(await res.text(), 'text/html').getElementById('now');
      if (!fresh) throw new Error('no section');
      document.getElementById('now').replaceWith(fresh);
      history.replaceState(null, '', link.href);
      const picked = fresh.querySelector('.days a[aria-current="true"]');
      if (picked) picked.focus();
    } catch {
      location.href = link.href;
    }
  });
})();
