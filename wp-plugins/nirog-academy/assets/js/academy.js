/*
 * Nirog Bhumi Courses — participant area behaviour.
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
    var release = function (e) {
      if (!dragging) { return; }
      dragging = false;
      if (e && (Math.abs(e.clientX - startX) > 6 || Math.abs(e.clientY - startY) > 6)) { heroBook.classList.add('is-dragged'); }
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
   * Booklet reader. The booklet page shows the book; tapping it (or "Start
   * reading") opens a full-screen reader on top of the page:
   *  - Scroll view (default on phones): pages one under another, sharp at
   *    any width, drawn only when near the screen; zoom buttons.
   *  - Book view (default on larger screens): real page turns, two-page
   *    spreads when there is room.
   * The phone's back button closes it. Every page seen is reported once,
   * and the reader reopens on the page where the participant stopped.
   */
  var readerEl = page.querySelector('.nba-reader');
  if (readerEl) { tracker = initReader(readerEl); }

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

  function initReader(root) {
    var conf = JSON.parse(root.dataset.config || '{}');
    var body = root.querySelector('.nba-rd-body');
    var bookHost = root.querySelector('.nba-rd-book');
    var scrollHost = root.querySelector('.nba-rd-scroll');
    var msg = root.querySelector('.nba-rd-msg');
    var count = root.querySelector('.nba-rd-count');
    var bar = root.querySelector('.nba-rd-progress span');
    var index = root.querySelector('.nba-rd-index');
    var btnPrev = root.querySelector('[data-act=prev]');
    var btnNext = root.querySelector('[data-act=next]');
    var endTpl = root.querySelector('.nba-rd-end-tpl');
    var dpr = Math.min(window.devicePixelRatio || 1, 2.5);
    var pdf = null, total = 0, ratio = 1.414, loading = null;
    var mode = '', flip = null, current = Math.max(1, conf.resume || 1);
    var seen = {}, sent = {}, queue = [], sending = false, isOpen = false;
    var zoom = 1, observer = null, lastFocus = null;
    (conf.read || '').split('').forEach(function (c, i) { if (c === '1') { seen[i + 1] = true; } });

    function store(key, value) { try { if (value === undefined) { return localStorage.getItem(key); } localStorage.setItem(key, value); } catch (e) { return null; } return null; }
    function preferredMode() {
      var saved = store('nba-reader-mode');
      if (saved === 'book' || saved === 'scroll') { return saved; }
      return window.innerWidth < 720 ? 'scroll' : 'book';
    }

    /* ---------- progress */
    function report(n) {
      seen[n] = true;
      if (sent[n]) { return; }
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
    function setCurrent(first, last) {
      current = first;
      last = last || first;
      for (var p = first; p <= last; p++) { report(p); }
      count.textContent = last > first
        ? t('pages').replace('%1$d', first).replace('%2$d', last).replace('%3$d', total)
        : t('page').replace('%1$d', first).replace('%2$d', total);
      bar.style.width = (total ? Math.round(last * 100 / total) : 0) + '%';
      btnPrev.disabled = first <= 1;
      btnNext.disabled = last >= total;
      Array.prototype.forEach.call(index.children, function (b) {
        var n = parseInt(b.dataset.page, 10);
        b.classList.toggle('is-current', n >= first && n <= last);
        b.classList.toggle('is-read', !!seen[n]);
      });
    }

    /* ---------- drawing */
    function renderInto(holder, n, cssWidth) {
      return pdf.getPage(n).then(function (pg) {
        var unit = pg.getViewport({ scale: 1 });
        var vp = pg.getViewport({ scale: (cssWidth / unit.width) * dpr });
        var canvas = document.createElement('canvas');
        canvas.width = Math.floor(vp.width);
        canvas.height = Math.floor(vp.height);
        canvas.addEventListener('contextmenu', function (e) { e.preventDefault(); });
        return pg.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise.then(function () {
          holder.querySelectorAll('canvas').forEach(function (c) { c.remove(); });
          holder.insertBefore(canvas, holder.firstChild);
          if (cfg.watermark && !holder.querySelector('.nba-watermark')) {
            var mark = document.createElement('div');
            mark.className = 'nba-watermark';
            mark.textContent = cfg.watermark;
            mark.style.left = (8 + (n * 37) % 46) + '%';
            mark.style.top = (14 + (n * 53) % 66) + '%';
            holder.appendChild(mark);
          }
        });
      });
    }

    /* ---------- scroll view */
    function buildScroll() {
      scrollHost.innerHTML = '';
      var list = document.createElement('div');
      list.className = 'nba-rd-pages';
      list.style.setProperty('--nba-zoom', String(zoom));
      for (var i = 1; i <= total; i++) {
        var d = document.createElement('div');
        d.className = 'nba-rd-page';
        d.dataset.page = String(i);
        d.style.aspectRatio = '1 / ' + ratio;
        d.innerHTML = '<span class="nba-rd-pageno">' + i + '</span>';
        list.appendChild(d);
      }
      if (endTpl) { list.appendChild(endTpl.content.cloneNode(true)); }
      scrollHost.appendChild(list);
      var drawn = {};
      var ratios = {};
      observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          var n = parseInt(en.target.dataset.page, 10);
          ratios[n] = en.intersectionRatio;
          if (en.isIntersecting && !drawn[n]) {
            drawn[n] = true;
            renderInto(en.target, n, en.target.clientWidth).catch(function () { drawn[n] = false; });
          }
        });
        // The page taking most of the screen is the current one.
        var best = current, bestRatio = -1;
        Object.keys(ratios).forEach(function (k) { if (ratios[k] > bestRatio) { bestRatio = ratios[k]; best = parseInt(k, 10); } });
        if (bestRatio > 0) { setCurrent(best); }
      }, { root: scrollHost, rootMargin: '120% 0px', threshold: [0, 0.25, 0.5, 0.75, 1] });
      Array.prototype.forEach.call(list.querySelectorAll('.nba-rd-page'), function (el) { observer.observe(el); });
      goScroll(current, false);
    }
    function goScroll(n, smooth) {
      var el = scrollHost.querySelector('.nba-rd-page[data-page="' + n + '"]');
      if (el) { scrollHost.scrollTo({ top: el.offsetTop - 12, behavior: smooth ? 'smooth' : 'auto' }); }
      setCurrent(n);
    }

    /* ---------- book view */
    function buildBook() {
      bookHost.innerHTML = '';
      var stage = document.createElement('div');
      stage.className = 'nba-rd-stage';
      bookHost.appendChild(stage);
      var availW = bookHost.clientWidth - 24, availH = bookHost.clientHeight - 24;
      var spread = availW >= 700 && availW > availH * 0.95;
      var pageW = Math.floor(Math.min(availH / ratio, availW / (spread ? 2 : 1)));
      var pageH = Math.round(pageW * ratio);
      stage.style.width = (pageW * (spread ? 2 : 1)) + 'px';
      stage.style.height = pageH + 'px';
      var pages = [];
      for (var i = 1; i <= total; i++) {
        var d = document.createElement('div');
        d.className = 'nba-rd-leaf';
        d.dataset.page = String(i);
        d.dataset.density = (i === 1 || i === total) ? 'hard' : 'soft';
        stage.appendChild(d);
        pages.push(d);
      }
      var drawn = {};
      flip = new window.St.PageFlip(stage, {
        width: pageW, height: pageH, size: 'fixed',
        showCover: true, usePortrait: !spread, mobileScrollSupport: false,
        maxShadowOpacity: 0.4, flippingTime: 650, drawShadow: true, autoSize: false,
      });
      flip.loadFromHTML(pages);
      var visible = function () {
        var i2 = flip.getCurrentPageIndex() + 1;
        if (!spread || i2 === 1 || i2 === total) { return [i2, i2]; }
        var left = i2 % 2 === 0 ? i2 : i2 - 1;
        return [left, Math.min(total, left + 1)];
      };
      var refresh = function () {
        var v = visible();
        for (var k = v[0] - 2; k <= v[1] + 3; k++) {
          if (k >= 1 && k <= total && !drawn[k]) {
            drawn[k] = true;
            (function (n) { renderInto(stage.querySelector('.nba-rd-leaf[data-page="' + n + '"]'), n, pageW).catch(function () { drawn[n] = false; }); })(k);
          }
        }
        setCurrent(v[0], v[1]);
      };
      flip.on('flip', refresh);
      if (current > 1) { flip.turnToPage(Math.min(total, current) - 1); }
      refresh();
    }
    function teardownBook() {
      if (flip) { try { flip.destroy(); } catch (e) { /* already gone */ } flip = null; }
      bookHost.innerHTML = '';
    }

    function setMode(m, remember) {
      if (remember) { store('nba-reader-mode', m); }
      mode = m;
      root.dataset.mode = m;
      Array.prototype.forEach.call(root.querySelectorAll('[data-mode]'), function (b) { b.setAttribute('aria-pressed', b.dataset.mode === m ? 'true' : 'false'); });
      if (!pdf) { return; }
      if (observer) { observer.disconnect(); observer = null; }
      teardownBook();
      scrollHost.innerHTML = '';
      if (m === 'book') {
        loadClassic(cfg.pageflip, 'St').then(buildBook).catch(function () { setMode('scroll'); });
      } else {
        buildScroll();
      }
    }

    function load() {
      if (loading) { return loading; }
      msg.hidden = false;
      loading = import(cfg.pdfjs).then(function (lib) {
        lib.GlobalWorkerOptions.workerSrc = cfg.pdfWorker;
        return lib.getDocument({ url: conf.url, withCredentials: true, isEvalSupported: false }).promise;
      }).then(function (doc) {
        pdf = doc;
        total = doc.numPages;
        for (var i = 1; i <= total; i++) {
          var b = document.createElement('button');
          b.type = 'button';
          b.dataset.page = String(i);
          b.textContent = String(i);
          index.appendChild(b);
        }
        return doc.getPage(1).then(function (first) {
          var v = first.getViewport({ scale: 1 });
          ratio = v.height / v.width;
          msg.hidden = true;
        });
      }).catch(function () {
        loading = null;
        msg.hidden = false;
        msg.textContent = t('bookletError');
        throw new Error('booklet');
      });
      return loading;
    }

    /* ---------- open / close */
    function open(push) {
      if (isOpen) { return; }
      isOpen = true;
      lastFocus = document.activeElement;
      root.hidden = false;
      document.documentElement.classList.add('nba-reading');
      if (push && location.hash !== '#read') { history.pushState({ nbaReader: 1 }, '', '#read'); }
      root.querySelector('.nba-rd-close').focus();
      load().then(function () { setMode(mode || preferredMode()); }).catch(function () {});
    }
    function close(fromHistory) {
      if (!isOpen) { return; }
      isOpen = false;
      root.hidden = true;
      document.documentElement.classList.remove('nba-reading');
      if (observer) { observer.disconnect(); observer = null; }
      teardownBook();
      scrollHost.innerHTML = '';
      index.hidden = true;
      if (!fromHistory && location.hash === '#read') {
        if (history.state && history.state.nbaReader) { history.back(); } else { history.replaceState(null, '', location.pathname + location.search); }
      }
      if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    window.addEventListener('popstate', function () { if (location.hash === '#read') { open(false); } else { close(true); } });

    document.addEventListener('click', function (e) {
      var opener = e.target.closest ? e.target.closest('[data-open-reader]') : null;
      if (opener) {
        e.preventDefault();
        if (opener.querySelector('.nba-b3.is-dragged')) { opener.querySelector('.nba-b3').classList.remove('is-dragged'); return; }
        open(true);
      }
    });

    root.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('[data-act],[data-mode],[data-page]') : null;
      if (!el) { return; }
      if (el.dataset.mode) { setMode(el.dataset.mode, true); return; }
      if (el.dataset.page && el.parentNode === index) {
        var n = parseInt(el.dataset.page, 10);
        index.hidden = true;
        if (mode === 'book' && flip) { flip.flip(n - 1); } else { goScroll(n, true); }
        return;
      }
      var act = el.dataset.act;
      if (act === 'close') { close(false); }
      if (act === 'index') { index.hidden = !index.hidden; el.setAttribute('aria-expanded', index.hidden ? 'false' : 'true'); }
      if (act === 'prev') { if (flip) { flip.flipPrev(); } else { goScroll(Math.max(1, current - 1), true); } }
      if (act === 'next') { if (flip) { flip.flipNext(); } else { goScroll(Math.min(total, current + 1), true); } }
    });

    document.addEventListener('keydown', function (e) {
      if (!isOpen) { return; }
      if (e.key === 'Escape') { if (!index.hidden) { index.hidden = true; } else { close(false); } return; }
      if (e.key === 'ArrowRight') { e.preventDefault(); btnNext.click(); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); btnPrev.click(); }
    });

    // Zoom (scroll view): +/- keep the page under the reader's eye.
    root.addEventListener('click', function (e) {
      var z = e.target.closest ? e.target.closest('[data-zoom]') : null;
      if (!z || mode !== 'scroll') { return; }
      var keep = current;
      zoom = Math.max(1, Math.min(2.5, zoom + (z.dataset.zoom === 'in' ? 0.5 : -0.5)));
      root.classList.toggle('is-zoomed', zoom > 1);
      setMode('scroll');
      goScroll(keep, false);
    });

    var resizeTimer = null, lastW = window.innerWidth, lastH = window.innerHeight;
    window.addEventListener('resize', function () {
      if (!isOpen || !pdf) { return; }
      // Ignore the small height changes of a phone's address bar.
      if (Math.abs(window.innerWidth - lastW) < 2 && Math.abs(window.innerHeight - lastH) < 120) { return; }
      lastW = window.innerWidth; lastH = window.innerHeight;
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { setMode(mode); }, 250);
    });

    if (location.hash === '#read') { open(false); }

    return {
      flush: function () {
        return new Promise(function (resolve) {
          var wait = function () { if (!sending && !queue.length) { resolve(); } else { setTimeout(wait, 150); } };
          wait();
        });
      },
    };
  }

  /*
   * YouTube lessons: YouTube's player script reports play, pause and time, so
   * these lessons are tracked like protected videos. A plain embed is already
   * on the page; it is swapped for the scripted player once that loads, so
   * the video still plays if the script is blocked.
   */
  if (player && player.kind === 'youtube' && player.yt) {
    var ytFrame = playerEl.querySelector('.nba-yt');
    loadClassic('https://www.youtube.com/iframe_api', '__nbaNoGlobal').catch(function () {});
    var whenYT = new Promise(function (resolve) {
      if (window.YT && window.YT.Player) { resolve(window.YT); return; }
      var prev = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = function () { if (prev) { prev(); } resolve(window.YT); };
    });
    whenYT.then(function (YT) {
      var mount = ytFrame.querySelector('.nba-yt-mount');
      var target = document.createElement('div');
      mount.innerHTML = '';
      mount.appendChild(target);
      var listeners = {}, ticker = null, yp = null;
      var adapter = {
        paused: true,
        seeking: false,
        addEventListener: function (type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
        pause: function () { if (yp && yp.pauseVideo) { yp.pauseVideo(); } },
      };
      Object.defineProperty(adapter, 'currentTime', { get: function () { return yp && yp.getCurrentTime ? yp.getCurrentTime() || 0 : 0; } });
      Object.defineProperty(adapter, 'duration', { get: function () { var d = yp && yp.getDuration ? yp.getDuration() : 0; return d > 0 ? d : NaN; } });
      var emit = function (type) { (listeners[type] || []).forEach(function (fn) { fn(); }); };
      yp = new YT.Player(target, {
        host: 'https://www.youtube-nocookie.com',
        videoId: player.yt,
        playerVars: { rel: 0, modestbranding: 1, playsinline: 1, start: player.resume > 5 ? Math.floor(player.resume) : 0, origin: location.origin },
        events: {
          onStateChange: function (e) {
            if (e.data === YT.PlayerState.PLAYING) {
              adapter.paused = false;
              emit('play');
              clearInterval(ticker);
              ticker = setInterval(function () { emit('timeupdate'); }, 1000);
            } else if (e.data === YT.PlayerState.PAUSED || e.data === YT.PlayerState.ENDED) {
              adapter.paused = true;
              clearInterval(ticker);
              emit(e.data === YT.PlayerState.ENDED ? 'ended' : 'pause');
            }
          },
          onError: function () { setStatus(t('error'), true); },
        },
      });
      control = {
        seek: function (d) { if (yp && yp.seekTo) { yp.seekTo(Math.max(0, adapter.currentTime + d), true); } },
        rate: function () { return yp && yp.getPlaybackRate ? yp.getPlaybackRate() : 1; },
        setRate: function (r) { if (yp && yp.setPlaybackRate) { yp.setPlaybackRate(r); } },
      };
      var frame = mount.querySelector('iframe');
      if (frame) { frame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen'); frame.setAttribute('title', document.title); }
      if (player.trackable) {
        tracker = createTracker(adapter, lessonId, function (result) {
          if (result && result.completed) { markCompleted(); }
        });
      }
    });
  }

  /*
   * Big, simple playback buttons under the player (−10 s, speed, +10 s), for
   * both our own player and YouTube's. Full screen turns the phone sideways.
   */
  var control = null;
  var speeds = [1, 1.25, 1.5, 0.75];
  if (playerEl) {
    playerEl.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-seek],[data-speed]') : null;
      if (!b || !control) { return; }
      if (b.hasAttribute('data-seek')) { control.seek(parseInt(b.dataset.seek, 10)); }
      else {
        var next = speeds[(speeds.indexOf(control.rate()) + 1) % speeds.length];
        control.setRate(next);
        b.textContent = String(next).replace(/^1$/, '1') + '×';
      }
    });
  }
  var lockLandscape = function () {
    var fs = document.fullscreenElement || document.webkitFullscreenElement;
    try {
      if (fs && screen.orientation && screen.orientation.lock) { screen.orientation.lock('landscape').catch(function () {}); }
      else if (!fs && screen.orientation && screen.orientation.unlock) { screen.orientation.unlock(); }
    } catch (err) { /* not supported */ }
  };
  document.addEventListener('fullscreenchange', lockLandscape);
  document.addEventListener('webkitfullscreenchange', lockLandscape);

  if (media && player && player.src) {
    media.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    control = {
      seek: function (d) { var to = Math.max(0, (media.currentTime || 0) + d); if (isFinite(media.duration)) { to = Math.min(media.duration - 0.5, to); } media.currentTime = to; },
      rate: function () { return media.playbackRate || 1; },
      setRate: function (r) { media.playbackRate = r; },
    };

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
