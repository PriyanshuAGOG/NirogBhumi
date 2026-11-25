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

  /* ---------------------------------------------------------------- 3D books */

  // Colour each book's spine from its cover, like a printed book.
  document.querySelectorAll('.nba-b3').forEach(function (book) {
    var img = book.querySelector('.nba-b3-front img');
    if (!img) { return; }
    var tint = function () {
      try {
        var c = document.createElement('canvas');
        c.width = 8; c.height = 24;
        var ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0, Math.max(1, img.naturalWidth * 0.08), img.naturalHeight, 0, 0, 8, 24);
        var d = ctx.getImageData(0, 0, 8, 24).data, r = 0, g = 0, b = 0, n = d.length / 4;
        for (var i = 0; i < d.length; i += 4) { r += d[i]; g += d[i + 1]; b += d[i + 2]; }
        r = Math.round(r / n * 0.82); g = Math.round(g / n * 0.82); b = Math.round(b / n * 0.82);
        book.style.setProperty('--b3-spine', 'rgb(' + r + ',' + g + ',' + b + ')');
        var light = (r * 299 + g * 587 + b * 114) / 1000 > 150;
        var label = book.querySelector('.nba-b3-spine span');
        if (label && light) { label.style.color = 'rgba(48,69,53,.85)'; }
      } catch (e) { /* keep the default spine colour */ }
    };
    if (img.complete && img.naturalWidth) { tint(); } else { img.addEventListener('load', tint); }
  });

  // The large book on a booklet page can be turned by dragging it.
  var heroBook = document.querySelector('.nba-b3.is-hero');
  if (heroBook) {
    var obj = heroBook.querySelector('.nba-b3-obj');
    var startX = 0, startY = 0, baseY = 22, baseX = 4, rotY = 22, rotX = 4, dragging = false;
    var set = function () { obj.style.transform = 'rotateY(' + rotY + 'deg) rotateX(' + rotX + 'deg)'; };
    heroBook.addEventListener('pointerdown', function (e) {
      dragging = true; startX = e.clientX; startY = e.clientY; baseY = rotY; baseX = rotX;
      heroBook.classList.add('is-dragging');
      heroBook.setPointerCapture(e.pointerId);
    });
    heroBook.addEventListener('pointermove', function (e) {
      if (!dragging) { return; }
      rotY = Math.max(-70, Math.min(160, baseY + (e.clientX - startX) * 0.5));
      rotX = Math.max(-18, Math.min(18, baseX - (e.clientY - startY) * 0.2));
      set();
    });
    var release = function () {
      if (!dragging) { return; }
      dragging = false;
      heroBook.classList.remove('is-dragging');
      rotY = 22; rotX = 4; set();
    };
    heroBook.addEventListener('pointerup', release);
    heroBook.addEventListener('pointercancel', release);
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


  /*
   * Booklet flipbook: PDF.js draws the pages, page-flip turns them (two-page
   * spread on wide screens, one page on phones). Pages are drawn only when
   * they are about to be seen, and every page seen is reported for progress.
   */
  var readerEl = page.querySelector('.nba-flipbook');
  if (readerEl) { tracker = initFlipbook(readerEl); }

  function loadClassic(src, globalName) {
    return new Promise(function (resolve, reject) {
      if (window[globalName]) { resolve(window[globalName]); return; }
      var s2 = document.createElement('script');
      s2.src = src;
      s2.onload = function () { resolve(window[globalName]); };
      s2.onerror = reject;
      document.head.appendChild(s2);
    });
  }

  function initFlipbook(root) {
    var conf = JSON.parse(root.dataset.config || '{}');
    var stage = root.querySelector('.nba-fb-stage');
    var msg = root.querySelector('.nba-fb-msg');
    var count = root.querySelector('.nba-fb-count');
    var index = root.querySelector('.nba-fb-index');
    var btnPrev = root.querySelector('[data-act=prev]');
    var btnNext = root.querySelector('[data-act=next]');
    var status = page.querySelector('.nba-reader-status');
    var pdf = null, flip = null, total = 0, ratio = 1.414;
    var drawn = {}, seen = {}, queue = [], sending = false;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    (conf.read || '').split('').forEach(function (c, i) { if (c === '1') { seen[i + 1] = true; } });

    function draw(n) {
      if (n < 1 || n > total || drawn[n] || !pdf) { return; }
      drawn[n] = true;
      var holder = stage.querySelector('[data-page="' + n + '"]');
      pdf.getPage(n).then(function (pg) {
        var unit = pg.getViewport({ scale: 1 });
        var bounds = flip && flip.getBoundsRect ? flip.getBoundsRect() : null;
        var cssWidth = Math.max((bounds && bounds.pageWidth) || holder.clientWidth || 0, 300);
        var vp = pg.getViewport({ scale: (cssWidth / unit.width) * dpr });
        var canvas = document.createElement('canvas');
        canvas.width = Math.floor(vp.width);
        canvas.height = Math.floor(vp.height);
        canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); });
        holder.innerHTML = '';
        holder.appendChild(canvas);
        if (cfg.watermark) {
          var mark = document.createElement('div');
          mark.className = 'nba-watermark';
          mark.textContent = cfg.watermark;
          mark.style.left = (8 + (n * 37) % 46) + '%';
          mark.style.top = (14 + (n * 53) % 66) + '%';
          holder.appendChild(mark);
        }
        return pg.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
      }).catch(function () { drawn[n] = false; });
    }

    function visiblePages() {
      var i = flip.getCurrentPageIndex() + 1;
      var spread = flip.getOrientation() === 'landscape';
      // In a spread the cover sits alone; after it, pages come in pairs.
      if (!spread || i === 1 || i === total) { return [i]; }
      var left = i % 2 === 0 ? i : i - 1;
      return [left, Math.min(total, left + 1)];
    }

    var sent = {};
    function report(n) {
      seen[n] = true;
      if (sent[n]) { return; } // Each page is reported once per visit.
      sent[n] = true;
      queue.push(n);
      pump();
    }
    function pump() {
      if (sending || !queue.length) { return; }
      sending = true;
      var n = queue.shift();
      api('lessons/' + conf.lesson + '/pages', { page: n, total: total }).then(function (r) {
        if (r.ok && r.data && r.data.completed) { conf.completed = true; markCompleted(); }
      }).catch(function () {}).then(function () { sending = false; pump(); });
    }

    function update() {
      var vis = visiblePages();
      vis.forEach(function (n) { report(n); });
      var first = vis[0], last = vis[vis.length - 1];
      for (var k = first - 2; k <= last + 3; k++) { draw(k); }
      count.textContent = vis.length > 1
        ? t('pages').replace('%1$d', first).replace('%2$d', last).replace('%3$d', total)
        : t('page').replace('%1$d', first).replace('%2$d', total);
      btnPrev.disabled = first <= 1;
      btnNext.disabled = last >= total;
      Array.prototype.forEach.call(index.children, function (b) {
        var n = parseInt(b.dataset.page, 10);
        b.classList.toggle('is-current', vis.indexOf(n) !== -1);
        b.classList.toggle('is-read', !!seen[n]);
      });
    }

    function build() {
      var pages = [];
      for (var i = 1; i <= total; i++) {
        var d = document.createElement('div');
        d.className = 'nba-fb-page';
        d.dataset.page = String(i);
        d.dataset.density = (i === 1 || i === total) ? 'hard' : 'soft';
        stage.appendChild(d);
        pages.push(d);
        var b = document.createElement('button');
        b.type = 'button';
        b.dataset.page = String(i);
        b.textContent = String(i);
        index.appendChild(b);
      }
      // Fit the book to the window: a page is never taller than the screen.
      var maxH = Math.max(360, window.innerHeight - (document.fullscreenElement ? 120 : 230));
      var pageW = Math.floor(maxH / ratio);
      stage.style.maxWidth = (pageW * 2) + 'px';
      flip = new window.St.PageFlip(stage, {
        width: pageW,
        height: Math.round(pageW * ratio),
        size: 'stretch',
        minWidth: 220,
        maxWidth: pageW,
        minHeight: Math.round(220 * ratio),
        maxHeight: maxH,
        showCover: true,
        usePortrait: true,
        mobileScrollSupport: true,
        maxShadowOpacity: 0.35,
        flippingTime: 650,
        drawShadow: true,
        autoSize: true,
      });
      flip.loadFromHTML(pages);
      flip.on('flip', update);
      flip.on('changeOrientation', function () { drawn = {}; update(); });
      msg.hidden = true;
      if (conf.resume > 1) { flip.turnToPage(Math.min(total, conf.resume) - 1); }
      // Draw once the book has its final size.
      requestAnimationFrame(function () { drawn = {}; update(); });
    }

    root.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('[data-act],[data-page]') : null;
      if (!el || !flip) { return; }
      if (el.dataset.page && el.parentNode === index) { flip.flip(parseInt(el.dataset.page, 10) - 1); return; }
      var act = el.dataset.act;
      if (act === 'prev') { flip.flipPrev(); }
      if (act === 'next') { flip.flipNext(); }
      if (act === 'index') { index.hidden = !index.hidden; el.setAttribute('aria-expanded', index.hidden ? 'false' : 'true'); }
      if (act === 'full') {
        if (document.fullscreenElement) { document.exitFullscreen(); }
        else if (root.requestFullscreen) { root.requestFullscreen().catch(function () {}); }
      }
    });
    var redraw = function () { if (flip) { flip.update(); drawn = {}; update(); } };
    document.addEventListener('fullscreenchange', function () { setTimeout(redraw, 200); });
    var resizeTimer = null;
    window.addEventListener('resize', function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(redraw, 300); });
    document.addEventListener('keydown', function (e) {
      if (!flip || /input|textarea|select/i.test((document.activeElement || {}).tagName || '')) { return; }
      if (e.key === 'ArrowRight') { flip.flipNext(); }
      if (e.key === 'ArrowLeft') { flip.flipPrev(); }
    });

    Promise.all([import(cfg.pdfjs), loadClassic(cfg.pageflip, 'St')]).then(function (libs) {
      var lib = libs[0];
      lib.GlobalWorkerOptions.workerSrc = cfg.pdfWorker;
      return lib.getDocument({ url: conf.url, withCredentials: true, isEvalSupported: false }).promise;
    }).then(function (doc) {
      pdf = doc;
      total = doc.numPages;
      return doc.getPage(1).then(function (first) {
        var v = first.getViewport({ scale: 1 });
        ratio = v.height / v.width;
      });
    }).then(build).catch(function () {
      msg.hidden = false;
      msg.textContent = t('bookletError');
    });

    return {
      flush: function () {
        return new Promise(function (resolve) {
          var wait = function () { if (!sending && !queue.length) { resolve(); } else { setTimeout(wait, 150); } };
          wait();
        });
      },
    };
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
