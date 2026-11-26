// Muted ten-second trailer previews over a still, on the home hero and the movie page poster.
// Laptop screens only: nothing loads with reduced motion, on smaller screens, with Data Saver on,
// or when a film has no trailer. The pause and sound buttons appear once a preview has played.
(() => {
  const stages = [...document.querySelectorAll('[data-preview]')];
  if (!stages.length) return;

  const START = 30; // seconds into the trailer the visible clip starts, past rating cards and logos
  const WARM = 5000; // YouTube shows its title and buttons for the first seconds of play, so those play hidden
  const CLIP = 10000; // how long each preview shows
  const DELAY = 1000; // time on the still before a preview starts loading
  const GAP = 12000; // time on the still between previews
  const FADE = 800; // the CSS fade

  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const wide = matchMedia('(min-width: 1001px)');
  const allowed = () => !reduce.matches && wide.matches && !(navigator.connection && navigator.connection.saveData);
  const validKey = (key) => (/^[\w-]{6,20}$/.test(key || '') ? key : '');

  // The YouTube IFrame API loads once, the first time a preview is due.
  let api = null;
  const loadApi = () => {
    api ??= new Promise((resolve, reject) => {
      if (window.YT && window.YT.Player) {
        resolve(window.YT);
        return;
      }
      const before = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = () => {
        if (typeof before === 'function') before();
        resolve(window.YT);
      };
      const script = document.createElement('script');
      script.src = 'https://www.youtube.com/iframe_api';
      script.addEventListener('error', () => reject(new Error('The YouTube player did not load')));
      document.head.appendChild(script);
    });
    return api;
  };

  function preview(stage) {
    const box = stage.parentElement;
    const controls = box.querySelector('.pv-ctl');
    const pauseButton = controls && controls.querySelector('[data-pv="pause"]');
    const soundButton = controls && controls.querySelector('[data-pv="sound"]');

    let key = validKey(stage.dataset.key);
    let player = null;
    let ready = false;
    // idle: on the still. loading: asked YouTube to play. warming: playing, still hidden. showing: faded in.
    let phase = 'idle';
    let timer = 0;
    let played = false; // a preview has shown, so the buttons appear
    let paused = false; // the visitor pressed pause
    let sound = false;
    let inView = true;
    let covered = false; // a dialog is open over the page
    let broken = false; // the player can't load at all
    const bad = new Set(); // trailers YouTube won't play here
    const starts = {}; // start second for trailers too short to start at 30

    const canRun = () => key !== '' && !bad.has(key) && !broken && allowed() && !paused && inView && !covered && !document.hidden;
    const later = (fn, ms) => {
      clearTimeout(timer);
      timer = setTimeout(fn, ms);
    };
    const loadFrom = () => Math.max(0, (starts[key] ?? START) - WARM / 1000);

    function updateControls() {
      if (controls) controls.hidden = !(allowed() && key !== '' && !bad.has(key) && !broken && (played || paused));
    }

    function schedule(ms = DELAY) {
      clearTimeout(timer);
      if (canRun()) later(play, ms);
    }

    // Back to the still. The player is stopped rather than paused, because a paused
    // YouTube player shows its own buttons when it plays again.
    function fadeOut() {
      phase = 'idle';
      clearTimeout(timer);
      stage.classList.remove('on');
      setTimeout(() => {
        if (player && ready && phase === 'idle') player.stopVideo();
      }, FADE);
    }

    async function play() {
      if (!canRun() || phase !== 'idle') return;
      phase = 'loading';
      try {
        const YT = await loadApi();
        if (phase !== 'loading' || !canRun()) return;
        if (!player) make(YT);
        else if (ready) start();
      } catch {
        broken = true;
        fadeOut();
        updateControls();
      }
    }

    function start() {
      player.loadVideoById({ videoId: key, startSeconds: loadFrom() });
      if (sound) player.unMute();
      else player.mute();
    }

    function playing() {
      if (phase !== 'loading') {
        // It started after the preview was called off, for example while scrolling away.
        if (phase === 'idle') player.stopVideo();
        return;
      }
      phase = 'warming';
      // A short trailer starts a third of the way in instead.
      const length = player.getDuration();
      if (length && length < START + 15 && starts[key] === undefined) {
        starts[key] = Math.max(WARM / 1000, Math.floor(length / 3));
        player.seekTo(loadFrom(), true);
      }
      later(reveal, WARM);
    }

    function reveal() {
      if (phase !== 'warming') return;
      if (!canRun()) {
        fadeOut();
        return;
      }
      phase = 'showing';
      stage.classList.add('on');
      played = true;
      updateControls();
      later(end, CLIP);
    }

    function end() {
      fadeOut();
      if (canRun()) later(play, GAP + FADE);
    }

    function make(YT) {
      const holder = document.createElement('div');
      stage.appendChild(holder);
      const madeFor = key;
      player = new YT.Player(holder, {
        host: 'https://www.youtube-nocookie.com',
        videoId: key,
        width: '100%',
        height: '100%',
        playerVars: { autoplay: 1, mute: 1, controls: 0, disablekb: 1, fs: 0, iv_load_policy: 3, playsinline: 1, rel: 0, start: Math.floor(loadFrom()), origin: location.origin },
        events: {
          onReady: () => {
            ready = true;
            const frame = player.getIframe();
            frame.setAttribute('tabindex', '-1');
            frame.setAttribute('title', 'Trailer preview');
            // It starts by itself. If the preview was called off while it loaded, stop it,
            // and if the hero moved on to another film meanwhile, load that one instead.
            if (phase !== 'loading' || !canRun()) player.stopVideo();
            else if (madeFor !== key) start();
          },
          onStateChange: (e) => {
            if (e.data === YT.PlayerState.PLAYING) playing();
            else if (e.data === YT.PlayerState.ENDED && phase !== 'idle') end();
          },
          onError: () => {
            bad.add(key);
            fadeOut();
            updateControls();
          },
        },
      });
    }

    // Something changed: screen size, motion setting, tab, scroll position or a dialog.
    function refresh() {
      if (!canRun()) {
        if (phase !== 'idle' || stage.classList.contains('on')) fadeOut();
        else clearTimeout(timer);
      } else if (phase === 'idle') {
        schedule();
      }
      updateControls();
    }

    if (pauseButton) {
      pauseButton.addEventListener('click', () => {
        paused = !paused;
        pauseButton.setAttribute('aria-pressed', String(paused));
        if (paused) fadeOut();
        else play();
        updateControls();
      });
    }
    if (soundButton) {
      soundButton.addEventListener('click', () => {
        sound = !sound;
        soundButton.setAttribute('aria-pressed', String(sound));
        if (player && ready) {
          if (sound) {
            player.unMute();
            player.setVolume(70);
          } else {
            player.mute();
          }
        }
      });
    }

    if ('IntersectionObserver' in window) {
      new IntersectionObserver(([entry]) => {
        inView = entry.isIntersecting;
        refresh();
      }, { threshold: 0.35 }).observe(box);
    }
    document.addEventListener('visibilitychange', refresh);
    reduce.addEventListener('change', refresh);
    wide.addEventListener('change', refresh);
    const layer = document.getElementById('layer');
    if (layer) {
      new MutationObserver(() => {
        covered = Boolean(layer.querySelector('.veil'));
        refresh();
      }).observe(layer, { childList: true });
    }

    // The home hero tells the preview which film it's on.
    box.addEventListener('cmx:slide', (e) => {
      const next = validKey(e.detail && e.detail.preview);
      if (next === key) return;
      fadeOut();
      key = next;
      updateControls();
      schedule();
    });

    updateControls();
    schedule();
  }

  stages.forEach(preview);
})();
