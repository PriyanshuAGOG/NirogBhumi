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

  /* ---------------------------------------------------------------- mobile menu */

  var sheet = document.getElementById('nba-sheet');
  var openBtn = document.querySelector('.nba-head .nba-menu-btn');
  if (sheet && openBtn) {
    var setOpen = function (open) {
      sheet.hidden = !open;
      openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.documentElement.style.overflow = open ? 'hidden' : '';
      if (open) { var first = sheet.querySelector('nav a'); if (first) { first.focus(); } } else { openBtn.focus(); }
    };
    openBtn.addEventListener('click', function () { setOpen(true); });
    sheet.querySelector('.nba-menu-close').addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !sheet.hidden) { setOpen(false); } });
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
          else {
            completeBtn.disabled = false;
            var msg = (r.data && r.data.message) || (readerEl ? t('notRead') : t('notWatched'));
            if (readerEl) { var rs = page.querySelector('.nba-reader-status'); if (rs) { rs.textContent = msg; rs.classList.add('is-error'); } } else { setStatus(msg, true); }
          }
        }).catch(function () { completeBtn.disabled = false; setStatus(t('error'), true); });
      };
      // Send any unsent watch time first, so the server has the latest picture.
      if (tracker) { tracker.flush().then(finish, finish); } else { finish(); }
    });
  }

  var tracker = null;


  /* Booklet reader: renders the PDF on screen with PDF.js (bundled locally). */
  var readerEl = page.querySelector('.nba-reader');
  if (readerEl) { tracker = initReader(readerEl); }

  function initReader(root) {
    var conf = JSON.parse(root.dataset.config || '{}');
    var pagesEl = root.querySelector('.nba-reader-pages');
    var label = root.querySelector('.nba-reader-page');
    var status = page.querySelector('.nba-reader-status');
    var pdf = null, total = 0, current = 1, zoom = 1, ratio = 1.414;
    var sheets = [], rendered = {}, reportTimer = null, lastReported = 0, pending = Promise.resolve();
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    function setLabel() { label.textContent = t('page').replace('%1$d', current).replace('%2$d', total); }
    function baseWidth() { return Math.min(pagesEl.clientWidth - 26, 920); }

    function render(i) {
      if (rendered[i] || !pdf) { return; }
      rendered[i] = true;
      var sheetEl = sheets[i - 1];
      pdf.getPage(i).then(function (pg) {
        var unit = pg.getViewport({ scale: 1 });
        var cssWidth = baseWidth() * zoom;
        var vp = pg.getViewport({ scale: (cssWidth / unit.width) * dpr });
        var canvas = document.createElement('canvas');
        canvas.width = Math.floor(vp.width);
        canvas.height = Math.floor(vp.height);
        canvas.style.width = cssWidth + 'px';
        canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); });
        sheetEl.innerHTML = '';
        sheetEl.style.width = cssWidth + 'px';
        sheetEl.style.height = '';
        sheetEl.appendChild(canvas);
        if (cfg.watermark) {
          var mark = document.createElement('div');
          mark.className = 'nba-watermark';
          mark.textContent = cfg.watermark;
          mark.style.left = (8 + (i * 37) % 50) + '%';
          mark.style.top = (12 + (i * 53) % 70) + '%';
          sheetEl.appendChild(mark);
        }
        var no = document.createElement('span');
        no.className = 'nba-page-no';
        no.textContent = i + ' / ' + total;
        sheetEl.appendChild(no);
        return pg.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
      }).catch(function () { rendered[i] = false; });
    }

    function sizePlaceholders() {
      var w = baseWidth() * zoom;
      sheets.forEach(function (sheetEl, idx) {
        if (!rendered[idx + 1]) { sheetEl.style.width = w + 'px'; sheetEl.style.height = (w * ratio) + 'px'; }
      });
    }

    function report(i) {
      clearTimeout(reportTimer);
      reportTimer = setTimeout(function () {
        if (i === lastReported) { return; }
        lastReported = i;
        pending = api('lessons/' + conf.lesson + '/pages', { page: i, total: total }).then(function (r) {
          if (r.ok && r.data && r.data.completed) { markCompleted(); }
        }).catch(function () {});
      }, 700);
    }

    function goTo(i) {
      i = Math.max(1, Math.min(total, i));
      var target = sheets[i - 1];
      if (target) { pagesEl.scrollTo({ top: target.offsetTop - pagesEl.offsetTop - 12, behavior: 'smooth' }); }
    }

    function rerender() {
      rendered = {};
      sheets.forEach(function (s2) { s2.innerHTML = ''; });
      sizePlaceholders();
      render(current);
      render(Math.min(total, current + 1));
    }

    root.addEventListener('click', function (e) {
      var act = e.target.getAttribute && e.target.getAttribute('data-act');
      if (!act || !pdf) { return; }
      if (act === 'prev') { goTo(current - 1); }
      if (act === 'next') { goTo(current + 1); }
      if (act === 'in') { zoom = Math.min(2.5, zoom + 0.25); rerender(); }
      if (act === 'out') { zoom = Math.max(0.5, zoom - 0.25); rerender(); }
      if (act === 'fit') { zoom = 1; rerender(); }
    });

    import(cfg.pdfjs).then(function (lib) {
      lib.GlobalWorkerOptions.workerSrc = cfg.pdfWorker;
      return lib.getDocument({ url: conf.url, withCredentials: true, isEvalSupported: false }).promise;
    }).then(function (doc) {
      pdf = doc;
      total = doc.numPages;
      return doc.getPage(1).then(function (first) {
        var v = first.getViewport({ scale: 1 });
        ratio = v.height / v.width;
      });
    }).then(function () {
      pagesEl.innerHTML = '';
      for (var i = 1; i <= total; i++) {
        var sheetEl = document.createElement('div');
        sheetEl.className = 'nba-page-sheet';
        sheetEl.dataset.page = String(i);
        pagesEl.appendChild(sheetEl);
        sheets.push(sheetEl);
      }
      sizePlaceholders();
      var seen = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          var n = parseInt(en.target.dataset.page, 10);
          if (en.isIntersecting) {
            render(n);
            render(Math.min(total, n + 1));
            if (en.intersectionRatio >= 0.5) { current = n; setLabel(); report(n); }
          }
        });
      }, { root: pagesEl, threshold: [0, 0.5] });
      sheets.forEach(function (s2) { seen.observe(s2); });
      setLabel();
      if (conf.resume > 1) { setTimeout(function () { goTo(conf.resume); }, 150); }
      if (total <= 1) { report(1); }
    }).catch(function () {
      pagesEl.innerHTML = '<p class="nba-reader-msg">' + t('bookletError') + '</p>';
    });

    return { flush: function () { clearTimeout(reportTimer); if (current !== lastReported && total) { lastReported = current; return api('lessons/' + conf.lesson + '/pages', { page: current, total: total }); } return pending; } };
  }

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
