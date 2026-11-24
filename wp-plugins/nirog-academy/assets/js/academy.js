/*
 * Nirog Bhumi Academy — participant area behaviour.
 * Plain JavaScript, no framework, no external requests.
 */
(function () {
  'use strict';

  var cfg = window.NBA || { i18n: {} };
  var t = function (key) { return (cfg.i18n && cfg.i18n[key]) || key; };

  function api(path, body, opts) {
    opts = opts || {};
    return fetch(cfg.rest + path, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: !!opts.keepalive,
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify(body || {})
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        return { ok: res.ok, status: res.status, data: data };
      });
    });
  }

  function randomId() {
    var a = new Uint8Array(12);
    (window.crypto || window.msCrypto).getRandomValues(a);
    return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      if (window.Hls) { resolve(); return; }
      var s = document.createElement('script');
      s.src = src;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  /* ---------------------------------------------------------------- password + help */

  document.querySelectorAll('.nba-show-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('aria-controls'));
      if (!input) { return; }
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? btn.dataset.hide : btn.dataset.show;
    });
  });

  var search = document.querySelector('.nba-help-search');
  if (search) {
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      document.querySelectorAll('.nba-help-item').forEach(function (item) {
        item.hidden = q !== '' && item.dataset.search.indexOf(q) === -1;
      });
    });
  }

  /* ---------------------------------------------------------------- lesson page */

  var page = document.querySelector('.nba-lesson-page');
  if (!page) { return; }
  var lessonId = parseInt(page.dataset.lesson, 10);
  var badge = page.querySelector('.nba-complete-badge');
  var completeBtn = page.querySelector('.nba-complete');

  function markCompleted() {
    if (badge) { badge.hidden = false; }
    if (completeBtn) { completeBtn.remove(); completeBtn = null; }
  }

  /* Notes: autosave after typing stops. */
  var note = page.querySelector('.nba-note');
  var noteStatus = page.querySelector('.nba-note-status');
  if (note) {
    var timer = null;
    note.addEventListener('input', function () {
      clearTimeout(timer);
      noteStatus.textContent = t('saving');
      timer = setTimeout(function () {
        api('lessons/' + lessonId + '/note', { body: note.value }).then(function (r) {
          noteStatus.textContent = r.ok ? t('saved') : t('error');
        }).catch(function () { noteStatus.textContent = t('error'); });
      }, 900);
    });
  }

  /* Player. */
  var playerEl = page.querySelector('.nba-player');
  var player = playerEl ? JSON.parse(playerEl.dataset.config || '{}') : null;
  var media = playerEl ? playerEl.querySelector('.nba-media') : null;
  var statusEl = playerEl ? playerEl.querySelector('.nba-player-status') : null;

  function setStatus(msg, isError) {
    if (!statusEl) { return; }
    statusEl.textContent = msg || '';
    statusEl.classList.toggle('is-error', !!isError);
  }

  if (completeBtn) {
    completeBtn.addEventListener('click', function () {
      completeBtn.disabled = true;
      var finish = function () {
        api('lessons/' + lessonId + '/complete').then(function (r) {
          if (r.ok) { markCompleted(); setStatus(t('completed')); }
          else { completeBtn.disabled = false; setStatus((r.data && r.data.message) || t('notWatched'), true); }
        }).catch(function () { completeBtn.disabled = false; setStatus(t('error'), true); });
      };
      // Send any unsent watch time first, so the server has the latest picture.
      if (tracker) { tracker.flush().then(finish, finish); } else { finish(); }
    });
  }

  var tracker = null;

  if (media && player && player.src) {
    media.addEventListener('contextmenu', function (e) { e.preventDefault(); });

    var attach = function () {
      if (player.kind === 'hls') {
        var nativeHls = media.canPlayType('application/vnd.apple.mpegurl');
        return loadScript(cfg.hls).then(function () {
          if (window.Hls && window.Hls.isSupported()) {
            var hls = new window.Hls({ maxBufferLength: 30, capLevelToPlayerSize: true, startLevel: -1 });
            hls.on(window.Hls.Events.ERROR, function (event, data) {
              if (!data.fatal) { return; }
              var code = data.response && data.response.code;
              if (code === 410 || code === 403) { setStatus(t('expired'), true); hls.destroy(); return; }
              if (data.type === window.Hls.ErrorTypes.NETWORK_ERROR) { hls.startLoad(); return; }
              if (data.type === window.Hls.ErrorTypes.MEDIA_ERROR) { hls.recoverMediaError(); return; }
              setStatus(t('error'), true);
            });
            hls.loadSource(player.src);
            hls.attachMedia(media);
          } else if (nativeHls) {
            media.src = player.src;
          } else {
            setStatus(t('error'), true);
          }
        }).catch(function () {
          if (nativeHls) { media.src = player.src; } else { setStatus(t('error'), true); }
        });
      }
      media.src = player.src;
      return Promise.resolve();
    };

    attach().then(function () {
      if (player.resume > 5) {
        var resumeOnce = function () {
          if (media.duration && player.resume < media.duration - 10) { media.currentTime = player.resume; }
          media.removeEventListener('loadedmetadata', resumeOnce);
        };
        media.addEventListener('loadedmetadata', resumeOnce);
      }
    });

    if (player.trackable) {
      tracker = createTracker(media, lessonId, function (result) {
        if (result && result.completed) { markCompleted(); }
      });
    }

    /* Moving watermark. */
    var mark = playerEl.querySelector('.nba-watermark');
    if (mark) {
      var move = function () {
        mark.style.left = (6 + Math.random() * 58) + '%';
        mark.style.top = (8 + Math.random() * 72) + '%';
      };
      move();
      setInterval(move, 8000);
    }
  }

  /*
   * Watch tracking: collects the ranges actually played (not skipped) and
   * reports them every 15 seconds, on pause, at the end, and when the page
   * is hidden. The server decides when a lesson counts as watched.
   */
  function createTracker(media, lessonId, onResult) {
    var stream = randomId();
    var claimed = false;
    var ranges = [];
    var current = null;
    var lastTime = 0;
    var stopped = false;

    media.addEventListener('play', function () {
      if (stopped) { media.pause(); return; }
      if (!claimed) { claimed = true; api('stream/claim', { stream: stream }); }
      lastTime = media.currentTime;
      current = [media.currentTime, media.currentTime];
    });

    media.addEventListener('timeupdate', function () {
      if (media.paused || media.seeking || !current) { return; }
      var now = media.currentTime;
      var step = now - lastTime;
      if (step > 0 && step < 3) {
        current[1] = now;
      } else {
        closeRange();
        current = [now, now];
      }
      lastTime = now;
    });

    media.addEventListener('seeking', function () { closeRange(); });
    media.addEventListener('seeked', function () {
      lastTime = media.currentTime;
      if (!media.paused) { current = [media.currentTime, media.currentTime]; }
    });
    media.addEventListener('pause', function () { closeRange(); flush(); });
    media.addEventListener('ended', function () { closeRange(); flush(); });
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') { closeRange(); flush(true); if (!media.paused) { current = [media.currentTime, media.currentTime]; } }
    });
    setInterval(function () {
      if (!media.paused) { closeRange(); current = [media.currentTime, media.currentTime]; flush(); }
    }, 15000);

    function closeRange() {
      if (current && current[1] - current[0] >= 0.5) { ranges.push([current[0], current[1]]); }
      current = null;
    }

    function flush(keepalive) {
      if (stopped) { return Promise.resolve(); }
      var payload = {
        position: media.currentTime || 0,
        duration: isFinite(media.duration) ? media.duration : 0,
        ranges: ranges.splice(0, ranges.length),
        stream: stream
      };
      if (!payload.ranges.length && !keepalive && payload.position === 0) { return Promise.resolve(); }
      return api('lessons/' + lessonId + '/progress', payload, { keepalive: !!keepalive }).then(function (r) {
        if (r.status === 409) {
          stopped = true;
          media.pause();
          setStatus(t('stopped'), true);
          return;
        }
        if (r.status === 403) { setStatus(t('expired'), true); return; }
        if (!r.ok) { ranges = payload.ranges.concat(ranges); return; }
        onResult(r.data);
      }).catch(function () {
        ranges = payload.ranges.concat(ranges); // Try again on the next report.
      });
    }

    return { flush: function () { closeRange(); return flush(); } };
  }
})();
