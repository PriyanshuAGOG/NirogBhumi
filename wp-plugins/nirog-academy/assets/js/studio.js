/*
 * Nirog Bhumi Courses — Course Studio.
 *
 * A small single-page app inside WordPress admin. Uses WordPress's bundled
 * React (wp.element) with htm for templates, and SortableJS for drag and drop.
 * Every change is saved automatically through /wp-json/nirog-academy/v1/studio/.
 */
(function () {
  'use strict';

  var el = window.wp.element;
  var h = el.createElement;
  var useState = el.useState, useEffect = el.useEffect, useRef = el.useRef, useMemo = el.useMemo, Fragment = el.Fragment;
  var html = window.htm.bind(h);
  var CFG = window.NBAStudio || {};

  var TYPES = {
    video: { label: 'Recorded lesson', short: 'Video' },
    audio: { label: 'Audio practice', short: 'Audio' },
    booklet: { label: 'Booklet', short: 'Booklet' },
    reading: { label: 'Reading', short: 'Reading' },
  };
  var ELEMENTS = [
    ['earth', 'Earth', '#82ad5c'], ['water', 'Water', '#77d0d4'], ['fire', 'Fire', '#d7b84e'], ['air', 'Air', '#87a8e8'], ['space', 'Space', '#9e82c9'],
  ];

  /* ------------------------------------------------------------------ api */

  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': CFG.nonce } };
    if (opts.form) {
      init.body = opts.form;
    } else if (opts.body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.body);
    }
    return fetch(CFG.rest + path, init).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (!res.ok) { throw new Error((data && data.message) || ('Request failed (' + res.status + ')')); }
        return data;
      });
    });
  }

  /* ------------------------------------------------------------------ small parts */

  function pad(n) { return n < 10 ? '0' + n : String(n); }
  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
  function minutes(sec) { if (!sec) { return '—'; } var m = Math.round(sec / 60); return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60) + ' min'; }

  /*
   * Booklet cover: draw the PDF's first page in the browser and upload it as
   * an image, with the page count. Covers are what participants see on the
   * shelf, so they always match the real booklet.
   */
  function makeCover(itemId, pdfUrl) {
    return import(CFG.pdfjs).then(function (lib) {
      lib.GlobalWorkerOptions.workerSrc = CFG.pdfWorker;
      return lib.getDocument({ url: pdfUrl, withCredentials: true, isEvalSupported: false }).promise;
    }).then(function (doc) {
      return doc.getPage(1).then(function (pg) {
        var unit = pg.getViewport({ scale: 1 });
        var vp = pg.getViewport({ scale: 900 / unit.width });
        var canvas = document.createElement('canvas');
        canvas.width = Math.floor(vp.width);
        canvas.height = Math.floor(vp.height);
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        return pg.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
          return new Promise(function (resolve) { canvas.toBlob(function (b) { resolve({ blob: b, pages: doc.numPages }); }, 'image/jpeg', 0.86); });
        });
      });
    }).then(function (res) {
      var form = new FormData();
      form.append('file', res.blob, 'cover.jpg');
      form.append('pages', String(res.pages));
      return api('items/' + itemId + '/cover', { method: 'POST', form: form });
    });
  }

  function TypeTag(props) {
    return html`<span className=${'st-tag st-tag-' + props.type}>${(TYPES[props.type] || { short: props.type }).short}</span>`;
  }

  function Field(props) {
    return html`<label className=${'st-field' + (props.wide ? ' is-wide' : '')}>
      <span className="st-field-label">${props.label}</span>
      ${props.children}
      ${props.help ? html`<span className="st-field-help">${props.help}</span>` : null}
    </label>`;
  }

  function Toggle(props) {
    return html`<button type="button" role="switch" aria-checked=${props.on ? 'true' : 'false'}
      className=${'st-toggle' + (props.on ? ' is-on' : '')} onClick=${function () { props.onChange(!props.on); }}>
      <span className="st-toggle-track"><span className="st-toggle-dot"></span></span>
      <span>${props.on ? props.onLabel : props.offLabel}</span>
    </button>`;
  }

  function Check(props) {
    return html`<label className="st-check"><input type="checkbox" checked=${!!props.checked} onChange=${function (e) { props.onChange(e.target.checked); }} /> <span>${props.label}</span></label>`;
  }

  function SaveState(props) {
    var map = { idle: '', saving: 'Saving…', saved: 'All changes saved', error: 'Not saved — ' + (props.error || 'try again') };
    return html`<span className=${'st-save st-save-' + props.state} role="status">${map[props.state]}</span>`;
  }

  /*
   * Drag-and-drop list. SortableJS moves DOM nodes; React must own the DOM,
   * so the move is undone immediately and reported to the parent, which
   * updates state.
   */
  function SortableList(props) {
    var ref = useRef(null);
    var cb = useRef(props.onMove);
    cb.current = props.onMove;
    useEffect(function () {
      if (!ref.current || !window.Sortable) { return undefined; }
      var s = window.Sortable.create(ref.current, {
        group: props.group,
        handle: props.handle || '.st-handle',
        draggable: props.draggable || '.st-draggable',
        animation: 150,
        ghostClass: 'st-ghost',
        onEnd: function (evt) {
          // Undo Sortable's DOM change; React re-renders from state.
          if (evt.from !== evt.to) {
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
          } else if (evt.oldIndex !== evt.newIndex) {
            var ref2 = evt.from.children[evt.oldIndex > evt.newIndex ? evt.oldIndex + 1 : evt.oldIndex];
            evt.from.insertBefore(evt.item, ref2 || null);
          }
          if (evt.from === evt.to && evt.oldDraggableIndex === evt.newDraggableIndex) { return; }
          cb.current(evt.from.dataset.list, evt.to.dataset.list, evt.oldDraggableIndex, evt.newDraggableIndex);
        },
      });
      return function () { s.destroy(); };
    }, []);
    return html`<div ref=${ref} className=${props.className} data-list=${props.listKey}>${props.children}</div>`;
  }

  /* ------------------------------------------------------------------ app */

  function parseHash() {
    var m = (window.location.hash || '').match(/^#\/(programme|library)(?:\/(\d+))?(?:\/(\w+))?/);
    if (!m) { return { screen: 'home' }; }
    return { screen: m[1], id: m[2] ? parseInt(m[2], 10) : 0, tab: m[3] || '' };
  }

  function App() {
    var routeState = useState(parseHash());
    var route = routeState[0], setRoute = routeState[1];
    useEffect(function () {
      var on = function () { setRoute(parseHash()); window.scrollTo(0, 0); };
      window.addEventListener('hashchange', on);
      return function () { window.removeEventListener('hashchange', on); };
    }, []);
    var go = function (hash) { window.location.hash = hash; };

    return html`<div className="st-app">
      <header className="st-top">
        <a className="st-brand" href="#/">Nirog Bhumi <span>Course Studio</span></a>
        <nav className="st-top-nav">
          <a href="#/" className=${route.screen === 'home' || route.screen === 'programme' ? 'is-active' : ''}>Programmes</a>
          <a href="#/library" className=${route.screen === 'library' ? 'is-active' : ''}>Video library</a>
          <a href=${CFG.links.enrollments}>Participants</a>
          <a href=${CFG.links.overview}>Overview</a>
          <a href=${CFG.links.help}>Guides</a>
        </nav>
      </header>
      ${route.screen === 'programme' && route.id
        ? h(ProgrammeEditor, { key: route.id, id: route.id, tab: route.tab || 'weeks', go: go })
        : route.screen === 'library' ? h(Library, null) : h(Home, { go: go })}
    </div>`;
  }

  /* ------------------------------------------------------------------ home */

  function Home(props) {
    var s = useState(null), list = s[0], setList = s[1];
    var e = useState(''), error = e[0], setError = e[1];
    var c = useState(false), creating = c[0], setCreating = c[1];
    var t = useState(''), title = t[0], setTitle = t[1];

    useEffect(function () { api('programmes').then(setList).catch(function (err) { setError(err.message); }); }, []);

    var create = function (ev) {
      ev.preventDefault();
      api('programmes', { method: 'POST', body: { title: title } }).then(function (p) { props.go('#/programme/' + p.id + '/weeks'); }).catch(function (err) { setError(err.message); });
    };

    var hour = new Date().getHours();
    var greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

    return html`<main className="st-page">
      <section className="st-hero">
        <p className="st-eyebrow">${greet}, ${CFG.firstName}</p>
        <h1>Your programmes</h1>
        <p className="st-lead">Weeks, recorded lessons, audio practices, booklets, bonus content and live sessions — all in one place. Changes save as you work.</p>
        ${creating
          ? html`<form className="st-inline-form" onSubmit=${create}>
              <input autoFocus value=${title} onChange=${function (ev) { setTitle(ev.target.value); }} placeholder="Programme name, e.g. 99 Day Diabetes Programme" />
              <button className="st-btn" type="submit">Create</button>
              <button className="st-btn st-btn-quiet" type="button" onClick=${function () { setCreating(false); }}>Cancel</button>
            </form>`
          : html`<button className="st-btn" onClick=${function () { setCreating(true); }}>New programme</button>`}
      </section>
      <${Glance} />
      ${error ? html`<p className="st-error">${error}</p>` : null}
      ${list === null ? html`<p className="st-muted">Loading…</p>` : null}
      ${list && !list.length ? html`<p className="st-empty">No programmes yet. Create your first one above.</p>` : null}
      <ol className="st-programmes">
        ${(list || []).map(function (p, i) {
          var c2 = p.counts;
          var bits = [plural(c2.weeks, 'week', 'weeks'), plural(c2.video + c2.audio, 'lesson', 'lessons'), plural(c2.booklet, 'booklet', 'booklets'), plural(c2.live, 'live session', 'live sessions'), plural(c2.bonus, 'bonus item', 'bonus items')];
          return html`<li key=${p.id}>
            <a className="st-programme-row" href=${'#/programme/' + p.id + '/weeks'}>
              <span className="st-index">${pad(i + 1)}</span>
              <span className=${'st-swatch st-el-' + p.element}></span>
              <span className="st-programme-main">
                <span className="st-programme-title">${p.title}</span>
                <span className="st-programme-meta">${bits.join(' · ')}</span>
              </span>
              <span className="st-programme-side">
                <span className=${'st-status st-status-' + p.status}>${p.status === 'publish' ? 'Published' : 'Draft'}</span>
                <span className="st-muted">${plural(p.participants, 'participant', 'participants')}</span>
              </span>
              <span className="st-arrow" aria-hidden="true">→</span>
            </a>
          </li>`;
        })}
      </ol>
      <${DemoPanel} onChange=${function () { api('programmes').then(setList); }} />
    </main>`;
  }

  /* ------------------------------------------------------------------ at a glance */

  function Glance() {
    var g = useState(null), data = g[0], setData = g[1];
    useEffect(function () { api('glance').then(setData).catch(function () {}); }, []);
    if (!data) { return null; }
    var progLink = function (s) { return '#/programme/' + s.programme + '/live'; };
    return html`<section className="st-glance" aria-label="At a glance">
      <a className="st-glance-card" href=${data.links.enrollments}><strong>${data.active}</strong><span>active participants</span></a>
      <a className=${'st-glance-card' + (data.waiting ? ' is-attn' : '')} href=${data.links.enrollments + '&status=pending'}><strong>${data.waiting}</strong><span>waiting for a batch</span></a>
      <a className=${'st-glance-card' + (data.devices ? ' is-attn' : '')} href=${data.links.devices}><strong>${data.devices}</strong><span>device requests</span></a>
      <a className=${'st-glance-card' + (data.open ? '' : ' is-attn')} href=${data.links.settings}><strong>${data.open ? 'Open' : 'Closed'}</strong><span>${data.open ? 'participants can log in' : 'only the team can see the courses'}</span></a>
      <div className="st-glance-live">
        <div className="st-split-head"><h2 className="st-h2">Live in the next 7 days</h2><a className="st-link" href=${data.links.liveDesk}>Live desk →</a></div>
        ${data.live.length ? html`<ul>${data.live.map(function (s) {
          return html`<li key=${s.id}><a href=${progLink(s)}>
            <span className="st-glance-when">${s.phase === 'live' ? 'Live now' : s.phase === 'open' ? 'Join open' : s.when}</span>
            <span className="st-glance-title">${s.title}</span>
            <span className=${s.has_link ? 'st-ok' : 'st-warn'}>${s.draft ? 'Draft' : s.has_link ? 'Link ready' : 'No link yet'}</span>
          </a></li>`;
        })}</ul>` : html`<p className="st-muted">No live sessions this week.</p>`}
      </div>
    </section>`;
  }

  /* ------------------------------------------------------------------ demo programme */

  function DemoPanel(props) {
    var d = useState(null), demo = d[0], setDemo = d[1];
    var b = useState(false), busy = b[0], setBusy = b[1];
    var e = useState(''), error = e[0], setError = e[1];
    useEffect(function () { api('demo').then(setDemo).catch(function () {}); }, []);
    if (!demo || (!demo.exists && !demo.canManage)) { return null; }

    var run = function (method) {
      if (method === 'DELETE' && !window.confirm('Remove the demo programme, its files and the demo participant? Your own programmes are not touched.')) { return; }
      setBusy(true); setError('');
      api('demo', { method: method }).then(function (r) { setDemo(r); setBusy(false); props.onChange(); })
        .catch(function (err) { setError(err.message); setBusy(false); });
    };

    if (!demo.exists) {
      return html`<section className="st-demo">
        <div>
          <p className="st-eyebrow">Try everything first</p>
          <h2 className="st-h2">Load the demo programme</h2>
          <p className="st-muted">A complete, published programme to click through: four weeks of videos, audio practices, booklets and readings, bonus content, live sessions of every kind and a demo participant whose access started nine days ago. Remove it with one button when you are done. Your website, theme and shop are not touched.</p>
          ${error ? html`<p className="st-error">${error}</p>` : null}
        </div>
        <button className="st-btn" disabled=${busy} onClick=${function () { run('POST'); }}>${busy ? 'Loading… (about 10 seconds)' : 'Load demo programme'}</button>
      </section>`;
    }

    return html`<section className="st-demo is-loaded">
      <div>
        <p className="st-eyebrow">Demo programme is loaded</p>
        <h2 className="st-h2">See it as a participant</h2>
        <ol className="st-demo-steps">
          <li>Open a private (incognito) window, or another browser or your phone.</li>
          <li>Go to <a className="st-demo-url" href=${demo.loginUrl} target="_blank" rel="noopener">${demo.loginUrl}</a></li>
          <li>Log in with username <code>${demo.login}</code> and password <code>${demo.password}</code></li>
        </ol>
        <p className="st-muted">Weeks 1–2 are open and weeks 3–4 open on later days, as a real participant would see. The demo participant gets no emails, may use several devices, and can log in even while Nirog Bhumi Courses is closed. You are enrolled too, so <a href=${demo.academyUrl} target="_blank" rel="noopener">opening Nirog Bhumi Courses</a> in this browser also works.</p>
        ${error ? html`<p className="st-error">${error}</p>` : null}
      </div>
      <div className="st-demo-actions">
        <a className="st-btn" href=${'#/programme/' + demo.programme + '/weeks'}>Open in the Studio</a>
        <a className="st-btn st-btn-quiet" href=${demo.programmeUrl} target="_blank" rel="noopener">View as a participant</a>
        ${demo.canManage ? html`<button className="st-btn st-btn-quiet st-danger" disabled=${busy} onClick=${function () { run('DELETE'); }}>${busy ? 'Removing…' : 'Remove demo'}</button>` : null}
      </div>
    </section>`;
  }

  /* ------------------------------------------------------------------ programme editor */

  function ProgrammeEditor(props) {
    var ps = useState(null), prog = ps[0], setProg = ps[1];
    var ms = useState([]), media = ms[0], setMedia = ms[1];
    var ss = useState(null), sel = ss[0], setSel = ss[1];
    var st = useState('idle'), save = st[0], setSave = st[1];
    var es = useState(''), saveError = es[0], setSaveError = es[1];
    var tab = props.tab;

    var load = function () { return api('programmes/' + props.id).then(setProg); };
    useEffect(function () {
      load().catch(function (err) { setSaveError(err.message); setSave('error'); });
      api('media').then(setMedia).catch(function () {});
    }, []);

    var track = function (promise) {
      setSave('saving');
      return promise.then(function (r) { setSave('saved'); return r; }).catch(function (err) { setSaveError(err.message); setSave('error'); throw err; });
    };

    if (!prog) {
      return html`<main className="st-page"><p className="st-muted">${save === 'error' ? saveError : 'Loading…'}</p></main>`;
    }

    var allItems = [].concat.apply([], prog.weeks.map(function (w) { return w.items; })).concat(prog.unsorted, prog.bonus);
    var findItem = function (id) { return allItems.filter(function (i) { return i.id === id; })[0]; };

    var replaceItem = function (item) {
      setProg(function (p) {
        var swap = function (list) { return list.map(function (i) { return i.id === item.id ? item : i; }); };
        return Object.assign({}, p, { weeks: p.weeks.map(function (w) { return Object.assign({}, w, { items: swap(w.items) }); }), unsorted: swap(p.unsorted), bonus: swap(p.bonus) });
      });
    };

    var patchProgramme = function (patch) {
      setProg(function (p) { return Object.assign({}, p, patch); });
      return track(api('programmes/' + prog.id, { method: 'POST', body: patch })).then(function (fresh) { setProg(function (p) { return Object.assign({}, fresh, { weeks: p.weeks, bonus: p.bonus, unsorted: p.unsorted, live: p.live }); }); });
    };

    var saveOrder = function (weeks, bonus) {
      return track(api('programmes/' + prog.id + '/order', { method: 'POST', body: { weeks: weeks.map(function (w) { return { id: w.id, items: w.items.map(function (i) { return i.id; }) }; }), bonus: bonus.map(function (i) { return i.id; }) } }));
    };

    var addItem = function (type, week, placement) {
      return track(api('programmes/' + prog.id + '/items', { method: 'POST', body: { type: type, week: week || 0, placement: placement || 'curriculum' } })).then(function (item) {
        return load().then(function () { setSel({ kind: 'item', id: item.id }); });
      });
    };

    var addLive = function (week) {
      return track(api('programmes/' + prog.id + '/live', { method: 'POST', body: { week: week || 0, status: 'draft' } })).then(function (live) {
        return load().then(function () { setSel({ kind: 'live', id: live.id }); });
      });
    };

    var selectedItem = sel && sel.kind === 'item' && tab !== 'settings' ? findItem(sel.id) : null;
    var selectedLive = sel && sel.kind === 'live' && tab !== 'settings' ? prog.live.filter(function (l) { return l.id === sel.id; })[0] : null;
    var tabs = [['weeks', 'Weeks'], ['bonus', 'Bonus'], ['live', 'Live sessions'], ['settings', 'Settings']];

    return html`<main className=${'st-page st-editor' + (selectedItem || selectedLive ? ' has-inspector' : '')}>
      <div className="st-editor-main">
        <header className="st-editor-head">
          <a className="st-back" href="#/">← All programmes</a>
          <div className="st-editor-title-row">
            <span className=${'st-swatch st-swatch-lg st-el-' + prog.element}></span>
            <input className="st-title-input" value=${prog.title} aria-label="Programme name"
              onChange=${function (e) { setProg(Object.assign({}, prog, { title: e.target.value })); }}
              onBlur=${function (e) { patchProgramme({ title: e.target.value }); }} />
          </div>
          <div className="st-editor-actions">
            ${h(Toggle, { on: prog.status === 'publish', onLabel: 'Published', offLabel: 'Draft — only staff can see it', onChange: function (on) { patchProgramme({ status: on ? 'publish' : 'draft' }); } })}
            ${prog.view_url ? html`<a className="st-btn st-btn-quiet" href=${prog.view_url} target="_blank" rel="noopener">View as participant ↗</a>` : null}
            ${h(SaveState, { state: save, error: saveError })}
          </div>
          <nav className="st-tabs" role="tablist">
            ${tabs.map(function (t) {
              return html`<a key=${t[0]} role="tab" aria-selected=${tab === t[0] ? 'true' : 'false'} className=${tab === t[0] ? 'is-active' : ''} href=${'#/programme/' + prog.id + '/' + t[0]}>${t[1]}${t[0] === 'bonus' && prog.bonus.length ? html` <span className="st-count">${prog.bonus.length}</span>` : null}${t[0] === 'live' && prog.live.length ? html` <span className="st-count">${prog.live.length}</span>` : null}</a>`;
            })}
          </nav>
        </header>

        ${tab === 'weeks' ? h(WeeksTab, { prog: prog, setProg: setProg, sel: sel, setSel: setSel, saveOrder: saveOrder, addItem: addItem, addLive: addLive, track: track, load: load }) : null}
        ${tab === 'bonus' ? h(BonusTab, { prog: prog, setProg: setProg, sel: sel, setSel: setSel, saveOrder: saveOrder, addItem: addItem }) : null}
        ${tab === 'live' ? h(LiveTab, { prog: prog, sel: sel, setSel: setSel, addLive: addLive }) : null}
        ${tab === 'settings' ? h(SettingsTab, { prog: prog, setProg: setProg, patch: patchProgramme, go: props.go, track: track }) : null}
      </div>

      ${selectedItem ? h(ItemInspector, { key: 'i' + selectedItem.id, item: selectedItem, prog: prog, media: media, onMedia: setMedia, track: track, onSaved: replaceItem, onClose: function () { setSel(null); }, onDeleted: function () { setSel(null); load(); } }) : null}
      ${selectedLive ? h(LiveInspector, { key: 'l' + selectedLive.id, live: selectedLive, prog: prog, track: track, onSaved: function (l) { setProg(function (p) { return Object.assign({}, p, { live: p.live.map(function (x) { return x.id === l.id ? l : x; }) }); }); }, onClose: function () { setSel(null); }, onDeleted: function () { setSel(null); load(); } }) : null}
    </main>`;
  }

  function ItemRow(props) {
    var it = props.item;
    var meta = [];
    if (it.placement !== 'bonus' || it.day) { meta.push(it.day === 0 ? 'Opens at start' : 'Day ' + it.day); }
    if (it.duration) { meta.push(it.duration + ' min'); }
    if (it.type === 'booklet') { meta.push(it.file ? (it.file.pages ? it.file.pages + ' pages' : 'PDF ready') : 'No PDF yet'); }
    if ((it.type === 'video' || it.type === 'audio') && !it.media) { meta.push('No video chosen'); }
    var missing = (it.type === 'booklet' && !it.file) || ((it.type === 'video' || it.type === 'audio') && !it.media);
    return html`<div className=${'st-row st-draggable' + (props.selected ? ' is-selected' : '') + (it.status !== 'publish' ? ' is-draft' : '')} data-id=${it.id}>
      <span className="st-handle" title="Drag to move" aria-hidden="true">⠿</span>
      <button type="button" className="st-row-main" onClick=${props.onSelect}>
        <${TypeTag} type=${it.type} />
        <span className="st-row-title">${it.title}</span>
        <span className=${'st-row-meta' + (missing ? ' is-warn' : '')}>${meta.join(' · ')}</span>
        <span className="st-row-status">${it.status === 'publish' ? '' : 'Draft'}</span>
      </button>
    </div>`;
  }

  function AddBar(props) {
    return html`<div className="st-addbar">
      <span className="st-addbar-label">Add</span>
      ${Object.keys(TYPES).map(function (type) {
        return html`<button key=${type} type="button" onClick=${function () { props.onAdd(type); }}>${TYPES[type].label}</button>`;
      })}
      ${props.onLive ? html`<button type="button" onClick=${props.onLive}>Live session</button>` : null}
    </div>`;
  }

  /* ------------------------------------------------------------------ weeks */

  function WeeksTab(props) {
    var prog = props.prog;

    var moveItem = function (fromKey, toKey, oldIndex, newIndex) {
      var weeks = prog.weeks.map(function (w) { return Object.assign({}, w, { items: w.items.slice() }); });
      var unsorted = prog.unsorted.slice();
      var listFor = function (key) { return key === 'unsorted' ? unsorted : weeks.filter(function (w) { return String(w.id) === String(key); })[0].items; };
      var from = listFor(fromKey), to = listFor(toKey);
      var item = from.splice(oldIndex, 1)[0];
      if (!item) { return; }
      to.splice(newIndex, 0, item);
      var next = Object.assign({}, prog, { weeks: weeks, unsorted: unsorted });
      props.setProg(next);
      // Unsorted items only join the order when dropped into a week.
      props.saveOrder(weeks.concat(unsorted.length ? [{ id: 0, items: unsorted }] : []), prog.bonus);
    };

    var moveWeek = function (fromKey, toKey, oldIndex, newIndex) {
      var weeks = prog.weeks.slice();
      var w = weeks.splice(oldIndex, 1)[0];
      weeks.splice(newIndex, 0, w);
      props.setProg(Object.assign({}, prog, { weeks: weeks }));
      props.saveOrder(weeks, prog.bonus);
    };

    var renameWeek = function (week, title) {
      props.track(api('weeks/' + week.id, { method: 'POST', body: { title: title } }));
    };
    var deleteWeek = function (week) {
      if (!window.confirm('Delete "' + week.title + '"? Its items are kept under "Not in a week".')) { return; }
      props.track(api('weeks/' + week.id, { method: 'DELETE' })).then(props.setProg);
    };
    var addWeek = function () {
      props.track(api('programmes/' + prog.id + '/weeks', { method: 'POST', body: { title: 'Week ' + (prog.weeks.length + 1) } })).then(props.setProg);
    };

    var dripNote = { none: 'Everything is available from the start.', enrollment: 'Self-paced: "Day 3" means three days after each person joins.', cohort: 'Batch: "Day 3" means three days after the batch starts.' }[prog.drip];

    return html`<section className="st-weeks">
      <p className="st-note">${dripNote} Drag items to reorder them or move them between weeks.</p>
      ${h(SortableList, { className: 'st-week-list', group: 'weeks', handle: '.st-week-handle', draggable: '.st-week', listKey: 'weeks', onMove: moveWeek },
        prog.weeks.map(function (week, i) {
          var live = prog.live.filter(function (l) { return l.week === week.id; });
          return html`<article className="st-week" key=${week.id}>
            <header className="st-week-head">
              <span className="st-week-handle" title="Drag to reorder weeks" aria-hidden="true">⠿</span>
              <span className="st-week-no">${pad(i + 1)}</span>
              <input className="st-week-title" defaultValue=${week.title} aria-label="Week name"
                onBlur=${function (e) { if (e.target.value !== week.title) { renameWeek(week, e.target.value); } }} />
              <span className="st-muted">${plural(week.items.length, 'item', 'items')}</span>
              <button type="button" className="st-link st-danger" onClick=${function () { deleteWeek(week); }}>Delete week</button>
            </header>
            ${h(SortableList, { className: 'st-rows', group: 'items', listKey: String(week.id), onMove: moveItem },
              week.items.map(function (it) { return h(ItemRow, { key: it.id, item: it, selected: props.sel && props.sel.kind === 'item' && props.sel.id === it.id, onSelect: function () { props.setSel({ kind: 'item', id: it.id }); } }); }))}
            ${week.items.length === 0 ? html`<p className="st-empty-week">Drop items here, or add one below.</p>` : null}
            ${live.map(function (l) {
              return html`<button type="button" key=${'l' + l.id} className=${'st-row st-row-live' + (props.sel && props.sel.kind === 'live' && props.sel.id === l.id ? ' is-selected' : '') + (l.status !== 'publish' ? ' is-draft' : '')} onClick=${function () { props.setSel({ kind: 'live', id: l.id }); }}>
                <span className="st-tag st-tag-live">Live</span>
                <span className="st-row-title">${l.title}</span>
                <span className="st-row-meta">${l.starts_label}</span>
                <span className="st-row-status">${l.status === 'publish' ? '' : 'Draft'}</span>
              </button>`;
            })}
            ${h(AddBar, { onAdd: function (type) { props.addItem(type, week.id); }, onLive: function () { props.addLive(week.id); } })}
          </article>`;
        }))}
      ${prog.unsorted.length ? html`<article className="st-week st-week-unsorted">
        <header className="st-week-head"><span className="st-week-no">—</span><strong>Not in a week</strong><span className="st-muted">Drag these into a week</span></header>
        ${h(SortableList, { className: 'st-rows', group: 'items', listKey: 'unsorted', onMove: moveItem },
          prog.unsorted.map(function (it) { return h(ItemRow, { key: it.id, item: it, selected: props.sel && props.sel.id === it.id, onSelect: function () { props.setSel({ kind: 'item', id: it.id }); } }); }))}
      </article>` : null}
      <button type="button" className="st-add-week" onClick=${addWeek}>+ Add week</button>
    </section>`;
  }

  /* ------------------------------------------------------------------ bonus */

  function BonusTab(props) {
    var prog = props.prog;
    var move = function (fromKey, toKey, oldIndex, newIndex) {
      var bonus = prog.bonus.slice();
      var it = bonus.splice(oldIndex, 1)[0];
      bonus.splice(newIndex, 0, it);
      props.setProg(Object.assign({}, prog, { bonus: bonus }));
      props.saveOrder(prog.weeks, bonus);
    };
    return html`<section className="st-weeks">
      <p className="st-note">Bonus content sits in its own library for participants: extra talks, recipes, Q&A recordings, extra booklets. Give each item a category to group it. Use "Opens on day" to release bonus items later.</p>
      <article className="st-week">
        ${h(SortableList, { className: 'st-rows', group: 'bonus', listKey: 'bonus', onMove: move },
          prog.bonus.map(function (it) {
            return h(ItemRow, { key: it.id, item: Object.assign({}, it, { title: it.title + (it.category ? '  ·  ' + it.category : '') }), selected: props.sel && props.sel.id === it.id, onSelect: function () { props.setSel({ kind: 'item', id: it.id }); } });
          }))}
        ${prog.bonus.length === 0 ? html`<p className="st-empty-week">No bonus content yet.</p>` : null}
        ${h(AddBar, { onAdd: function (type) { props.addItem(type, 0, 'bonus'); } })}
      </article>
    </section>`;
  }

  /* ------------------------------------------------------------------ live */

  function LiveTab(props) {
    var prog = props.prog;
    var weekName = function (id) { var w = prog.weeks.filter(function (x) { return x.id === id; })[0]; return w ? w.title : ''; };
    var upcoming = prog.live.filter(function (l) { return !l.past; });
    var past = prog.live.filter(function (l) { return l.past; });
    var row = function (l) {
      return html`<button type="button" key=${l.id} className=${'st-live-row' + (props.sel && props.sel.id === l.id ? ' is-selected' : '') + (l.status !== 'publish' ? ' is-draft' : '')} onClick=${function () { props.setSel({ kind: 'live', id: l.id }); }}>
        <span className="st-live-when">${l.starts_label}</span>
        <span className="st-row-title">${l.title}</span>
        <span className="st-row-meta">${[l.kind === 'stream' ? 'YouTube Live' : 'Meeting', l.host_name ? 'Host: ' + l.host_name : 'No host', weekName(l.week), l.join_url ? '' : 'No link yet', l.past && l.attended ? l.attended + ' attended' : '', l.recording ? 'Recording linked' : ''].filter(Boolean).join(' · ')}</span>
        <span className="st-row-status">${l.cancelled ? 'Cancelled' : l.status === 'publish' ? '' : 'Draft'}</span>
      </button>`;
    };
    return html`<section className="st-weeks">
      <p className="st-note">Each session has its own page for participants. Participants join from there: a meeting link opens Zoom or Google Meet, a YouTube Live stream plays right on the page. Reminders go out the day before and shortly before the start. Hosts see their sessions, links and team notes on <a href=${CFG.links.liveDesk}>Courses → Live desk</a>.</p>
      <div className="st-split-head"><h2 className="st-h2">Upcoming</h2><button className="st-btn" onClick=${function () { props.addLive(0); }}>Schedule a session</button></div>
      <div className="st-live-list">${upcoming.length ? upcoming.map(row) : html`<p className="st-empty-week">Nothing scheduled.</p>`}</div>
      ${past.length ? html`<h2 className="st-h2">Past</h2><div className="st-live-list">${past.map(row)}</div>` : null}
    </section>`;
  }

  /* ------------------------------------------------------------------ inspectors */

  function useAutosave(initial, save) {
    var ds = useState(initial), draft = ds[0], setDraft = ds[1];
    var pending = useRef({});
    var timer = useRef(null);
    var saveRef = useRef(save);
    saveRef.current = save;
    var flush = function () {
      clearTimeout(timer.current);
      var patch = pending.current;
      pending.current = {};
      if (Object.keys(patch).length) { saveRef.current(patch); }
    };
    useEffect(function () { return flush; }, []);
    var set = function (key, value, immediate) {
      setDraft(function (d) { var n = Object.assign({}, d); n[key] = value; return n; });
      pending.current[key] = value;
      clearTimeout(timer.current);
      timer.current = setTimeout(flush, immediate ? 0 : 700);
    };
    return [draft, set, setDraft];
  }

  function ItemInspector(props) {
    var it = props.item;
    var auto = useAutosave(it, function (patch) {
      props.track(api('items/' + it.id, { method: 'POST', body: patch })).then(props.onSaved).catch(function () {});
    });
    var d = auto[0], set = auto[1], setDraft = auto[2];
    var up = useState(''), uploading = up[0], setUploading = up[1];
    var ue = useState(''), uploadError = ue[0], setUploadError = ue[1];
    var bookletInput = useRef(null), fileInput = useRef(null), coverInput = useRef(null);

    var upload = function (kind, file) {
      if (!file) { return; }
      if (file.size > CFG.maxUpload) { setUploadError('This file is larger than the server allows (' + CFG.maxUploadLabel + ').'); return; }
      var form = new FormData();
      form.append('file', file);
      setUploading(kind); setUploadError('');
      var endpoint = kind === 'booklet' ? 'booklet' : kind === 'cover' ? 'cover' : 'files';
      props.track(api('items/' + it.id + '/' + endpoint, { method: 'POST', form: form }))
        .then(function (fresh) {
          // After a new PDF, make its cover from the first page.
          return kind === 'booklet' && fresh.file_url ? makeCover(it.id, fresh.file_url).catch(function () { return fresh; }) : fresh;
        })
        .then(function (fresh) { setDraft(function (x) { return Object.assign({}, x, { file: fresh.file, cover: fresh.cover, file_url: fresh.file_url, resources: fresh.resources }); }); props.onSaved(fresh); })
        .catch(function (err) { setUploadError(err.message); })
        .then(function () { setUploading(''); });
    };
    var removeFile = function (fid) {
      props.track(api('items/' + it.id + '/files/' + fid, { method: 'DELETE' })).then(function (fresh) { setDraft(function (x) { return Object.assign({}, x, { resources: fresh.resources }); }); props.onSaved(fresh); });
    };
    var remove = function () {
      if (!window.confirm('Delete "' + d.title + '"? Participants will no longer see it. (It goes to the WordPress trash.)')) { return; }
      props.track(api('items/' + it.id, { method: 'DELETE' })).then(props.onDeleted);
    };

    var ys = useState(''), ytUrl = ys[0], setYtUrl = ys[1];
    var yb = useState(false), ytBusy = yb[0], setYtBusy = yb[1];
    var ye = useState(''), ytError = ye[0], setYtError = ye[1];
    var chosen = props.media.filter(function (m) { return m.id === d.media; })[0];
    var addYouTube = function (ev) {
      ev.preventDefault();
      setYtBusy(true); setYtError('');
      api('media', { method: 'POST', body: { provider: 'youtube', url: ytUrl, title: '' } }).then(function (list) {
        var newest = list.reduce(function (a, m) { return !a || m.id > a.id ? m : a; }, null);
        if (props.onMedia) { props.onMedia(list); }
        if (newest) { set('media', newest.id, true); if (/^New (recorded lesson|audio practice)$/.test(d.title)) { set('title', newest.title, true); } }
        setYtUrl('');
      }).catch(function (e) { setYtError(e.message); }).then(function () { setYtBusy(false); });
    };
    var mediaOptions = props.media.filter(function (m) { return d.type === 'audio' || m.provider !== 'file' || true; });
    var mediaSelect = function (key) {
      return html`<select value=${d[key] || 0} onChange=${function (e) { set(key, parseInt(e.target.value, 10), true); }}>
        <option value="0">— Choose from the video library —</option>
        ${mediaOptions.map(function (m) { return html`<option key=${m.id} value=${m.id}>${m.title}${m.ok ? '' : ' (not ready)'} · ${minutes(m.duration)}</option>`; })}
      </select>`;
    };

    return html`<aside className="st-inspector" aria-label="Edit item">
      <header className="st-inspector-head">
        <${TypeTag} type=${d.type} />
        <span className="st-muted">${d.placement === 'bonus' ? 'Bonus' : 'Lesson'} · #${it.id}</span>
        <button type="button" className="st-close" onClick=${props.onClose} aria-label="Close">×</button>
      </header>
      <textarea className="st-inspector-title" rows="2" value=${d.title} onChange=${function (e) { set('title', e.target.value); }} aria-label="Title"></textarea>
      ${h(Toggle, { on: d.status === 'publish', onLabel: 'Visible to participants', offLabel: 'Draft — hidden from participants', onChange: function (on) { set('status', on ? 'publish' : 'draft', true); } })}
      ${it.view_url ? html`<a className="st-link" href=${it.view_url} target="_blank" rel="noopener">Open as participant ↗</a>` : null}

      <div className="st-section">
        <h3>Type and timing</h3>
        <div className="st-grid-2">
          ${h(Field, { label: 'Type' }, html`<select value=${d.type} onChange=${function (e) { set('type', e.target.value, true); }}>
            ${Object.keys(TYPES).map(function (t) { return html`<option key=${t} value=${t}>${TYPES[t].label}</option>`; })}
          </select>`)}
          ${h(Field, { label: 'Opens on day', help: d.day === 0 ? 'Available from the start' : 'Day ' + d.day + ' after the start' }, html`<input type="number" min="0" max="3650" value=${d.day} onChange=${function (e) { set('day', parseInt(e.target.value || '0', 10)); }} />`)}
          ${d.type !== 'booklet' ? h(Field, { label: 'Length (minutes)' }, html`<input type="number" min="0" value=${d.duration} onChange=${function (e) { set('duration', parseInt(e.target.value || '0', 10)); }} />`) : null}
          ${d.placement === 'bonus' ? h(Field, { label: 'Bonus category', help: 'e.g. Recipes, Q&A, Pranayama' }, html`<input value=${d.category} onChange=${function (e) { set('category', e.target.value); }} />`) : null}
        </div>
      </div>

      <div className="st-section">
        <h3>${d.type === 'booklet' ? 'Booklet' : d.type === 'reading' ? 'Text' : 'Video or audio'}</h3>
        ${(d.type === 'video' || d.type === 'audio') ? html`<${Fragment}>
          ${chosen && chosen.thumb ? html`<img className="st-yt-thumb" src=${chosen.thumb} alt="" />` : null}
          ${h(Field, { label: 'Video', help: chosen && chosen.provider === 'youtube' ? 'YouTube video. Viewing is tracked; YouTube videos are not protected — anyone with the link can watch.' : null }, mediaSelect('media'))}
          <form className="st-yt-add" onSubmit=${addYouTube}>
            <input type="url" value=${ytUrl} placeholder="Or paste a YouTube link" onChange=${function (e) { setYtUrl(e.target.value); }} aria-label="YouTube link" />
            <button className="st-btn st-btn-quiet" type="submit" disabled=${!ytUrl || ytBusy}>${ytBusy ? 'Adding…' : 'Use'}</button>
          </form>
          ${ytError ? html`<p className="st-error">${ytError}</p>` : null}
          ${h(Field, { label: 'Hindi version (optional)' }, mediaSelect('media_hi'))}
          <a className="st-link" href="#/library">Video library →</a>
        </${Fragment}>` : null}
        ${d.type === 'booklet' ? html`<${Fragment}>
          <div className=${'st-drop' + (d.file ? ' has-file' : '')}
            onDragOver=${function (e) { e.preventDefault(); }}
            onDrop=${function (e) { e.preventDefault(); upload('booklet', e.dataTransfer.files[0]); }}>
            ${d.file ? html`<div className="st-booklet-file">
                ${d.cover ? html`<img className="st-cover-thumb" src=${d.cover} alt="Cover" />` : null}
                <div><strong>${d.file.name}</strong><br/><span className="st-muted">${d.file.size}${d.file.pages ? ' · ' + d.file.pages + ' pages' : ''}</span>
                ${uploading === 'booklet' ? html`<br/><span className="st-muted">Making the cover from page 1…</span>` : null}</div>
              </div>` : html`<div><strong>Drop the booklet PDF here</strong><br/><span className="st-muted">Up to ${CFG.maxUploadLabel}. Stored privately; participants read it on their course page as a flipbook. The first page becomes the cover.</span></div>`}
            <button type="button" className="st-btn st-btn-quiet" disabled=${uploading === 'booklet'} onClick=${function () { bookletInput.current.click(); }}>${uploading === 'booklet' ? 'Uploading…' : d.file ? 'Replace PDF' : 'Choose PDF'}</button>
            <input type="file" accept="application/pdf" hidden ref=${bookletInput} onChange=${function (e) { upload('booklet', e.target.files[0]); e.target.value = ''; }} />
            ${d.file ? html`<button type="button" className="st-link" disabled=${uploading === 'cover'} onClick=${function () { coverInput.current.click(); }}>${uploading === 'cover' ? 'Uploading…' : 'Use a different cover image'}</button>` : null}
            <input type="file" accept="image/jpeg,image/png,image/webp" hidden ref=${coverInput} onChange=${function (e) { upload('cover', e.target.files[0]); e.target.value = ''; }} />
          </div>
          ${h(Check, { checked: d.allow_download, label: 'Also let participants download the PDF', onChange: function (v) { set('allow_download', v, true); } })}
        </${Fragment}>` : null}
        ${d.type === 'reading' ? h(Field, { label: 'Text', help: 'Leave a blank line between paragraphs.', wide: true }, html`<textarea rows="10" value=${d.body} onChange=${function (e) { set('body', e.target.value); }}></textarea>`) : null}
        ${uploadError ? html`<p className="st-error">${uploadError}</p>` : null}
      </div>

      <div className="st-section">
        <h3>What participants read</h3>
        ${h(Field, { label: 'Short summary' }, html`<textarea rows="3" value=${d.summary} onChange=${function (e) { set('summary', e.target.value); }}></textarea>`)}
        ${h(Field, { label: 'Safety note', help: 'Shown in a highlighted box above the lesson. Must be approved by your clinical team.' }, html`<textarea className="st-safety" rows="3" value=${d.safety} onChange=${function (e) { set('safety', e.target.value); }}></textarea>`)}
        ${d.type !== 'reading' && d.type !== 'booklet' ? h(Field, { label: 'Lesson notes (optional)', help: 'Shown below the video.' }, html`<textarea rows="4" value=${d.body} onChange=${function (e) { set('body', e.target.value); }}></textarea>`) : null}
      </div>

      <div className="st-section">
        <h3>Downloads</h3>
        <ul className="st-files">
          ${(d.resources || []).map(function (f) { return html`<li key=${f.id}><span>${f.name}</span><span className="st-muted">${f.size}</span><button type="button" className="st-link st-danger" onClick=${function () { removeFile(f.id); }}>Remove</button></li>`; })}
        </ul>
        <button type="button" className="st-btn st-btn-quiet" disabled=${uploading === 'file'} onClick=${function () { fileInput.current.click(); }}>${uploading === 'file' ? 'Uploading…' : 'Add a worksheet or chart'}</button>
        <input type="file" hidden ref=${fileInput} accept=".pdf,.mp3,.m4a,.jpg,.jpeg,.png,.docx,.xlsx" onChange=${function (e) { upload('file', e.target.files[0]); e.target.value = ''; }} />
      </div>

      <div className="st-section">
        <h3>Options</h3>
        ${h(Check, { checked: d.required, label: 'Counts towards progress', onChange: function (v) { set('required', v, true); } })}
        ${h(Check, { checked: d.preview, label: 'Free preview — any logged-in visitor can open it', onChange: function (v) { set('preview', v, true); } })}
      </div>

      <div className="st-section">
        <h3>Hindi</h3>
        ${h(Field, { label: 'Title in Hindi' }, html`<input value=${d.title_hi} onChange=${function (e) { set('title_hi', e.target.value); }} />`)}
        ${h(Field, { label: 'Summary in Hindi' }, html`<textarea rows="2" value=${d.summary_hi} onChange=${function (e) { set('summary_hi', e.target.value); }}></textarea>`)}
        ${h(Field, { label: 'Safety note in Hindi' }, html`<textarea rows="2" value=${d.safety_hi} onChange=${function (e) { set('safety_hi', e.target.value); }}></textarea>`)}
      </div>

      <div className="st-section st-section-end">
        <button type="button" className="st-link st-danger" onClick=${remove}>Delete this item</button>
      </div>
    </aside>`;
  }

  function LiveInspector(props) {
    var l = props.live, prog = props.prog;
    var autoRef = useRef(l);
    var auto = useAutosave(l, function (patch) {
      var body = Object.assign({}, autoRef.current, patch);
      props.track(api('live/' + l.id, { method: 'POST', body: body })).then(props.onSaved).catch(function () {});
    });
    var d = auto[0], set = auto[1], setDraft = auto[2];
    autoRef.current = d;
    var as = useState(null), people = as[0], setPeople = as[1];
    var ms = useState(''), note = ms[0], setNote = ms[1];
    var lessons = [].concat.apply([], prog.weeks.map(function (w) { return w.items; })).concat(prog.bonus);

    useEffect(function () { api('live/' + l.id + '/attendance').then(setPeople).catch(function () { setPeople([]); }); }, []);

    var remove = function () {
      if (!window.confirm('Delete this live session? Use "Cancel session" instead if participants should be told.')) { return; }
      props.track(api('live/' + l.id, { method: 'DELETE' })).then(props.onDeleted);
    };
    var remind = function () {
      if (!window.confirm('Send a reminder to everyone in this session now (inbox, and email for those who want reminders)?')) { return; }
      props.track(api('live/' + l.id + '/remind', { method: 'POST' })).then(function (r) { setNote('Reminder sent to ' + plural(r.sent, 'person', 'people') + '.'); }).catch(function (e) { setNote(e.message); });
    };
    var cancel = function (on) {
      if (on && !window.confirm('Cancel this session? Everyone in it is told straight away, on their course page and by email.')) { return; }
      props.track(api('live/' + l.id + '/cancel', { method: 'POST', body: { cancelled: on } })).then(function (r) {
        setDraft(function (x) { return Object.assign({}, x, { cancelled: r.live.cancelled, phase: r.live.phase }); });
        props.onSaved(r.live);
        setNote(on ? 'Cancelled. ' + plural(r.notified, 'person was', 'people were') + ' told.' : 'Session restored.');
      });
    };
    var mt = useState(false), meetBusy = mt[0], setMeetBusy = mt[1];
    var mn = useState(''), meetNote = mn[0], setMeetNote = mn[1];
    var createMeet = function () {
      setMeetBusy(true); setMeetNote('');
      // Save what is on screen first, so the calendar event gets the right time.
      props.track(api('live/' + l.id, { method: 'POST', body: autoRef.current }))
        .then(function () { return props.track(api('live/' + l.id + '/meet', { method: 'POST' })); })
        .then(function (fresh) { setDraft(function (x) { return Object.assign({}, x, { join_url: fresh.join_url, kind: fresh.kind, meet_auto: fresh.meet_auto }); }); props.onSaved(fresh); setMeetNote(''); })
        .catch(function (e) { setMeetNote(e.message); })
        .then(function () { setMeetBusy(false); });
    };
    var kinds = [
      ['meeting', 'Google Meet or meeting link', 'Google Meet (or Zoom/Teams). Participants press Join and the meeting opens. Two-way — good for Q&A and small groups.'],
      ['stream', 'YouTube Live', 'An unlisted YouTube live stream that plays inside the session page. One-way — good for a guided class with many people.'],
    ];
    var phaseLabel = { upcoming: 'Scheduled', open: 'Join is open', live: 'Live now', ended: 'Finished', cancelled: 'Cancelled' }[d.phase] || '';

    return html`<aside className="st-inspector" aria-label="Edit live session">
      <header className="st-inspector-head">
        <span className="st-tag st-tag-live">Live</span>
        <span className="st-muted">${phaseLabel} · #${l.id}</span>
        <button type="button" className="st-close" onClick=${props.onClose} aria-label="Close">×</button>
      </header>
      <textarea className="st-inspector-title" rows="2" value=${d.title} onChange=${function (e) { set('title', e.target.value); }} aria-label="Title"></textarea>
      ${h(Toggle, { on: d.status === 'publish', onLabel: 'Visible to participants', offLabel: 'Draft — hidden, no reminders', onChange: function (on) { set('status', on ? 'publish' : 'draft', true); } })}
      ${l.view_url ? html`<a className="st-link" href=${l.view_url} target="_blank" rel="noopener">Open the session page ↗</a>` : null}
      ${d.cancelled ? html`<p className="st-error">This session is cancelled. Participants see "Cancelled".</p>` : null}

      <div className="st-section">
        <h3>When</h3>
        <div className="st-grid-2">
          ${h(Field, { label: 'Date and time (India time)' }, html`<input type="datetime-local" value=${d.starts_at} onChange=${function (e) { set('starts_at', e.target.value); }} />`)}
          ${h(Field, { label: 'Length (minutes)' }, html`<input type="number" min="5" value=${d.duration} onChange=${function (e) { set('duration', parseInt(e.target.value || '60', 10)); }} />`)}
        </div>
      </div>

      <div className="st-section">
        <h3>How people join</h3>
        <div className="st-choice-cards st-choice-2" role="radiogroup">
          ${kinds.map(function (k) {
            return html`<button type="button" key=${k[0]} role="radio" aria-checked=${d.kind === k[0] ? 'true' : 'false'} className=${'st-choice' + (d.kind === k[0] ? ' is-on' : '')} onClick=${function () { set('kind', k[0], true); }}><strong>${k[1]}</strong><span>${k[2]}</span></button>`;
          })}
        </div>
        ${d.kind === 'meeting' ? html`<div className="st-meet">
          ${CFG.google && CFG.google.connected
            ? (d.meet_auto && d.join_url
              ? html`<p className="st-ok">Google Meet link ready, in ${CFG.google.email || 'the connected'} calendar. Changing the title or time here updates the calendar event.</p>`
              : html`<button type="button" className="st-btn" disabled=${meetBusy} onClick=${createMeet}>${meetBusy ? 'Creating…' : (d.join_url ? 'Replace with a new Google Meet link' : 'Create Google Meet link')}</button>`)
            : html`<p className="st-muted">To create Google Meet links with one click, <a href=${CFG.google ? CFG.google.settingsUrl : '#'}>connect your Google account</a> (once). Or paste any meeting link below.</p>`}
          ${meetNote ? html`<p className="st-error">${meetNote}</p>` : null}
        </div>` : null}
        ${h(Field, { label: d.kind === 'stream' ? 'YouTube live link' : 'Meeting link', help: d.kind === 'stream' ? 'In YouTube Studio, create the stream as Unlisted, allow embedding, and paste its link here.' : 'Participants never see this link before the session; they join through their course page, which records attendance.' }, html`<input type="url" value=${d.join_url} placeholder="https://meet.google.com/…" onChange=${function (e) { set('join_url', e.target.value); }} />`)}
        ${h(Field, { label: 'What participants should know (optional)', help: 'For example: have a mat and a cushion ready.' }, html`<textarea rows="3" value=${d.description} onChange=${function (e) { set('description', e.target.value); }}></textarea>`)}
      </div>

      <div className="st-section">
        <h3>Team</h3>
        ${h(Field, { label: 'Host', help: 'Shown to participants ("With …"). Hosts see this session on Courses → Live desk.' }, html`<select value=${d.host} onChange=${function (e) { set('host', parseInt(e.target.value, 10), true); }}>
          <option value="0">— No host chosen —</option>
          ${(prog.team || []).map(function (u) { return html`<option key=${u.id} value=${u.id}>${u.name}</option>`; })}
        </select>`)}
        ${h(Field, { label: 'Notes for the team (private)', help: 'Only staff see this: meeting ID, passcode, host key, who runs the slides, what to check before going live.' }, html`<textarea className="st-team-notes" rows="4" value=${d.notes} onChange=${function (e) { set('notes', e.target.value); }}></textarea>`)}
      </div>

      <div className="st-section">
        <h3>Who and where it shows</h3>
        <div className="st-grid-2">
          ${h(Field, { label: 'Show in week' }, html`<select value=${d.week} onChange=${function (e) { set('week', parseInt(e.target.value, 10), true); }}>
            <option value="0">— Only on the Live page —</option>
            ${prog.weeks.map(function (w) { return html`<option key=${w.id} value=${w.id}>${w.title}</option>`; })}
          </select>`)}
          ${h(Field, { label: 'Batch' }, html`<select value=${d.cohort} onChange=${function (e) { set('cohort', parseInt(e.target.value, 10), true); }}>
            <option value="0">Everyone in the programme</option>
            ${prog.cohorts.map(function (c) { return html`<option key=${c.id} value=${c.id}>${c.name} (${c.starts})</option>`; })}
          </select>`)}
        </div>
      </div>

      <div className="st-section">
        <h3>On the day</h3>
        <p className="st-muted">Reminders go out automatically the day before and about 15–30 minutes before the start.</p>
        <div className="st-row-actions">
          <button type="button" className="st-btn st-btn-quiet" disabled=${d.status !== 'publish' || d.cancelled} onClick=${remind}>Send a reminder now</button>
          ${d.cancelled
            ? html`<button type="button" className="st-btn st-btn-quiet" onClick=${function () { cancel(false); }}>Restore session</button>`
            : html`<button type="button" className="st-link st-danger" onClick=${function () { cancel(true); }}>Cancel session…</button>`}
        </div>
        ${note ? html`<p className="st-muted">${note}</p>` : null}
      </div>

      <div className="st-section">
        <h3>Attendance ${people ? html`<span className="st-count">${people.length}</span>` : null}</h3>
        ${people === null ? html`<p className="st-muted">Loading…</p>` : null}
        ${people && !people.length ? html`<p className="st-muted">Nobody has joined yet. People are counted when they press Join (or open the stream).</p>` : null}
        ${people && people.length ? html`<ul className="st-files">${people.map(function (p) { return html`<li key=${p.user_id}><span>${p.name}</span><span className="st-muted">${p.joined}</span><span className="st-muted">${p.joins > 1 ? p.joins + '×' : ''}</span></li>`; })}</ul>` : null}
      </div>

      <div className="st-section">
        <h3>After the session</h3>
        ${h(Field, { label: 'Recording', help: 'Add the recording as a recorded lesson (often in Bonus), then choose it here. Participants then see "Watch the recording".' }, html`<select value=${d.recording} onChange=${function (e) { set('recording', parseInt(e.target.value, 10), true); }}>
          <option value="0">— None yet —</option>
          ${lessons.map(function (x) { return html`<option key=${x.id} value=${x.id}>${x.title}</option>`; })}
        </select>`)}
      </div>
      <div className="st-section st-section-end"><button type="button" className="st-link st-danger" onClick=${remove}>Delete this session</button></div>
    </aside>`;
  }

  /* ------------------------------------------------------------------ settings */

  function SettingsTab(props) {
    var prog = props.prog;
    var auto = useAutosave(prog, function (patch) { props.patch(patch); });
    var d = auto[0], set = auto[1];
    var pickCover = function () {
      var frame = window.wp.media({ title: 'Choose a cover image', library: { type: 'image' }, multiple: false });
      frame.on('select', function () { var a = frame.state().get('selection').first().toJSON(); set('cover_id', a.id, true); set('cover', (a.sizes && a.sizes.medium_large ? a.sizes.medium_large.url : a.url), false); });
      frame.open();
    };
    var drip = [
      ['enrollment', 'Self-paced', 'Lessons open day by day from each person\'s own start date. Good for programmes people can buy any day.'],
      ['cohort', 'Batch', 'Lessons open day by day from the batch start date, so everyone moves together. Good with live sessions.'],
      ['none', 'All at once', 'Everything is available immediately. Good for short courses and libraries.'],
    ];
    var del = function () {
      if (!window.confirm('Move this programme to the trash?')) { return; }
      props.track(api('programmes/' + prog.id, { method: 'DELETE' })).then(function () { props.go('#/'); });
    };
    return html`<section className="st-settings">
      <div className="st-settings-group">
        <h2 className="st-h2">About the programme</h2>
        ${h(Field, { label: 'Short description', help: 'One or two sentences shown on the participant dashboard.' }, html`<textarea rows="3" value=${d.summary} onChange=${function (e) { set('summary', e.target.value); }}></textarea>`)}
        ${h(Field, { label: 'Colour', help: 'One of the five elements, used as the programme\'s accent.' }, html`<div className="st-swatches">
          ${ELEMENTS.map(function (e) { return html`<button type="button" key=${e[0]} className=${'st-swatch-btn' + (d.element === e[0] ? ' is-on' : '')} onClick=${function () { set('element', e[0], true); }}><span style=${{ background: e[2] }}></span>${e[1]}</button>`; })}
        </div>`)}
        ${h(Field, { label: 'Cover image' }, html`<div className="st-cover">
          ${d.cover ? html`<img src=${d.cover} alt="" />` : html`<span className="st-muted">No cover yet</span>`}
          <button type="button" className="st-btn st-btn-quiet" onClick=${pickCover}>${d.cover ? 'Change' : 'Choose image'}</button>
          ${d.cover ? html`<button type="button" className="st-link st-danger" onClick=${function () { set('cover_id', 0, true); set('cover', ''); }}>Remove</button>` : null}
        </div>`)}
      </div>

      <div className="st-settings-group">
        <h2 className="st-h2">Access and lesson release</h2>
        ${h(Field, { label: 'Access length (days)', help: '365 = one year from each person\'s start date.' }, html`<input type="number" min="1" max="3650" value=${d.access_days} onChange=${function (e) { set('access_days', parseInt(e.target.value || '365', 10)); }} />`)}
        <div className="st-choice-cards" role="radiogroup" aria-label="Lesson release">
          ${drip.map(function (o) {
            return html`<button type="button" key=${o[0]} role="radio" aria-checked=${d.drip === o[0] ? 'true' : 'false'} className=${'st-choice' + (d.drip === o[0] ? ' is-on' : '')} onClick=${function () { set('drip', o[0], true); }}>
              <strong>${o[1]}</strong><span>${o[2]}</span></button>`;
          })}
        </div>
        ${d.drip === 'cohort' ? html`<div className="st-callout">
          ${h(Check, { checked: d.cohort_assignment !== 'manual', label: 'Put new buyers in the next open batch automatically', onChange: function (v) { set('cohort_assignment', v ? 'auto' : 'manual', true); } })}
          <p className="st-muted">${prog.cohorts.length ? prog.cohorts.length + ' batch(es) set up. ' : 'No batches yet. '}<a href=${CFG.links.batches}>Manage batches →</a></p>
        </div>` : null}
        ${h(Check, { checked: d.sequential, label: 'Participants must finish each lesson before the next one opens', onChange: function (v) { set('sequential', v, true); } })}
      </div>

      <div className="st-settings-group">
        <h2 className="st-h2">Health and safety note</h2>
        <p className="st-muted">Participants read and accept this once, before their first lesson. Please have it written or approved by your clinical team. Changing the English text asks everyone to accept the new version.</p>
        ${h(Field, { label: 'English', wide: true }, html`<textarea rows="8" value=${d.ack} onChange=${function (e) { set('ack', e.target.value); }}></textarea>`)}
        ${h(Field, { label: 'Hindi', wide: true }, html`<textarea rows="8" value=${d.ack_hi} onChange=${function (e) { set('ack_hi', e.target.value); }}></textarea>`)}
      </div>

      <div className="st-settings-group">
        <h2 className="st-h2">Hindi</h2>
        ${h(Field, { label: 'Programme name in Hindi' }, html`<input value=${d.title_hi} onChange=${function (e) { set('title_hi', e.target.value); }} />`)}
        ${h(Field, { label: 'Short description in Hindi' }, html`<textarea rows="3" value=${d.summary_hi} onChange=${function (e) { set('summary_hi', e.target.value); }}></textarea>`)}
      </div>

      <div className="st-settings-group">
        <h2 className="st-h2">Selling</h2>
        ${prog.products.length
          ? html`<p>Sold through: ${prog.products.map(function (p, i) { return html`<${Fragment} key=${p.id}>${i ? ', ' : ''}<a href=${p.edit}>${p.name}</a></${Fragment}>`; })}</p>`
          : html`<p className="st-muted">Not linked to a product yet. Open the WooCommerce product, tick this programme on its "Course" tab, and mark it Virtual.</p>`}
        <p><a href=${CFG.links.products}>WooCommerce products →</a> · <a href=${CFG.links.enrollments + '&programme_id=' + prog.id}>Participants in this programme →</a></p>
      </div>

      <div className="st-settings-group st-danger-zone">
        <h2 className="st-h2">Remove</h2>
        <p className="st-muted">Programmes with participants cannot be deleted — switch them to Draft instead.</p>
        <button type="button" className="st-link st-danger" onClick=${del}>Move programme to trash</button>
      </div>
    </section>`;
  }

  /* ------------------------------------------------------------------ library */

  function Library() {
    var ms = useState(null), media = ms[0], setMedia = ms[1];
    var rs = useState(null), results = rs[0], setResults = rs[1];
    var bs = useState(false), busy = bs[0], setBusy = bs[1];
    var es = useState(''), error = es[0], setError = es[1];
    var fs = useState({ provider: 'youtube', title: '', url: '', duration: '' }), form = fs[0], setForm = fs[1];

    useEffect(function () { api('media').then(setMedia).catch(function (e) { setError(e.message); }); }, []);

    var importNow = function () {
      setBusy(true); setError('');
      api('media/import', { method: 'POST' }).then(function (r) { setMedia(r.media); setResults(r.results); }).catch(function (e) { setError(e.message); }).then(function () { setBusy(false); });
    };
    var addLink = function (ev) {
      ev.preventDefault(); setError('');
      api('media', { method: 'POST', body: form }).then(function (list) { setMedia(list); setForm({ provider: form.provider, title: '', url: '', duration: '' }); }).catch(function (e) { setError(e.message); });
    };
    var rename = function (m, title) {
      if (title === m.title) { return; }
      api('media/' + m.id, { method: 'POST', body: { title: title } }).then(setMedia);
    };
    var sourceLabel = { hls: 'Protected', file: 'Private file', youtube: 'YouTube', external: 'External' };

    return html`<main className="st-page">
      <section className="st-hero st-hero-small">
        <p className="st-eyebrow">All programmes</p>
        <h1>Video library</h1>
        <p className="st-lead">Videos here can be used in any lesson, in any programme.</p>
      </section>
      ${error ? html`<p className="st-error">${error}</p>` : null}

      <div className="st-library">
        <section>
          <div className="st-split-head"><h2 className="st-h2">Videos and audio</h2>
            ${CFG.canImport ? html`<button className="st-btn" disabled=${busy} onClick=${importNow}>${busy ? 'Looking…' : 'Find newly uploaded videos'}</button>` : null}</div>
          ${results ? html`<ul className="st-results">${results.length ? results.map(function (r) { return html`<li key=${r.folder}><code>${r.folder}</code> — ${r.status}${r.message ? ': ' + r.message : ''}</li>`; }) : html`<li>No folders found in the media folder.</li>`}</ul>` : null}
          <div className="st-table">
            <div className="st-table-head"><span>Title</span><span>Source</span><span>Length</span><span>Status</span><span>Used in</span></div>
            ${media === null ? html`<p className="st-muted">Loading…</p>` : null}
            ${media && !media.length ? html`<p className="st-empty-week">No videos yet. Follow the steps on the right to add your first one.</p>` : null}
            ${(media || []).map(function (m) {
              return html`<div className="st-table-row" key=${m.id}>
                <input className="st-plain-input" defaultValue=${m.title} onBlur=${function (e) { rename(m, e.target.value); }} aria-label="Video title" />
                <span>${sourceLabel[m.provider] || m.provider}</span>
                <span>${minutes(m.duration)}</span>
                <span className=${m.ok ? 'st-ok' : 'st-warn'}>${m.status}</span>
                <span className="st-muted">${m.used_in.length ? m.used_in.slice(0, 2).join(', ') + (m.used_in.length > 2 ? ' +' + (m.used_in.length - 2) : '') : 'Not used yet'}</span>
              </div>`;
            })}
          </div>
        </section>

        <aside className="st-howto">
          <h2 className="st-h2">Add a protected video</h2>
          <ol>
            <li>On your computer, run <code>encode-lesson</code> on the MP4 (it is in the plugin's <code>tools</code> folder). It creates an encrypted folder.</li>
            <li>Upload that folder to <code>${CFG.mediaFolder}</code> with Hostinger File Manager.</li>
            <li>Press <strong>Find newly uploaded videos</strong>.</li>
            <li>Choose the video in any lesson.</li>
          </ol>
          <a className="st-link" href=${CFG.links.videoHelp}>Step-by-step guide →</a>

          <h2 className="st-h2">Or link a video</h2>
          <form className="st-stack" onSubmit=${addLink}>
            <div className="st-seg">
              ${['youtube', 'external'].map(function (p) { return html`<button type="button" key=${p} className=${form.provider === p ? 'is-on' : ''} onClick=${function () { setForm(Object.assign({}, form, { provider: p })); }}>${p === 'youtube' ? 'YouTube' : 'Video service link'}</button>`; })}
            </div>
            <p className="st-muted">${form.provider === 'youtube' ? 'Public or Unlisted YouTube videos play inside the lesson and viewing is tracked. They are not protected: anyone with the link can watch. For paid lessons, prefer protected videos.' : 'An HLS (.m3u8) or MP4 link from a video service such as Bunny Stream.'}</p>
            ${h(Field, { label: 'Link' }, html`<input value=${form.url} onChange=${function (e) { setForm(Object.assign({}, form, { url: e.target.value })); }} required placeholder=${form.provider === 'youtube' ? 'https://youtu.be/…' : 'https://'} />`)}
            ${h(Field, { label: 'Title', help: form.provider === 'youtube' ? 'Leave empty to use the title from YouTube.' : null }, html`<input value=${form.title} onChange=${function (e) { setForm(Object.assign({}, form, { title: e.target.value })); }} required=${form.provider !== 'youtube'} />`)}
            ${form.provider !== 'youtube' ? h(Field, { label: 'Length (minutes)' }, html`<input type="number" min="0" value=${form.duration} onChange=${function (e) { setForm(Object.assign({}, form, { duration: e.target.value })); }} />`) : null}
            <button className="st-btn" type="submit">Add to library</button>
          </form>
        </aside>
      </div>
    </main>`;
  }

  /* ------------------------------------------------------------------ mount */

  var root = document.getElementById('nba-studio');
  if (root) {
    if (el.createRoot) { el.createRoot(root).render(h(App)); } else { el.render(h(App), root); }
  }
})();
