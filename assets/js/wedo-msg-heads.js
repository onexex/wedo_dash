/* ==========================================================================
   Message chat heads — see includes/msg-heads.php. No jQuery: it also runs
   on the legacy (includes/header.php) pages.

   - One round photo per conversation with something unread (3 at most, then
     "+N"), stacked above the Corner bubble on the same side and following it
     when it is dragged. Nothing shows while everything is read.
   - Data rides the ringer's check-in (assets/js/wedo-call.js calls
     WDHeads.sig() / WDHeads.update()); the server only sends the heads again
     when its signature changes.
   - A new message pops its head, shows a preview next to it for a few seconds
     and plays a soft ping (mute switch in the chat window, remembered).
     "Already announced" is kept per conversation in localStorage, so a
     message is announced once, not again on every page or in every tab.
   - Click a head: mini chat window over the page (same API as Messages:
     query/Query-messages.php). Opening it marks the conversation read.
   - Drag a head onto the X (or its small x): hidden until that conversation
     gets something new. It is NOT marked read.
   - WeDo minimized / in another tab: a desktop pop-up (browser notification)
     once the person has allowed it — the chat window offers "Turn on".
   Message text is always rendered as text nodes, never as HTML.
   ========================================================================== */
(function () {
  'use strict';

  var CFG = window.WD_HEADS;
  if (!CFG || window.WDHeads || document.getElementById('msgApp')) { return; }

  var HEAD = 52, STEP = HEAD + 10, SHOWN = 3, PREVIEW_MS = 6000, CHAT_POLL_MS = 3000, MAX_RENDER = 80;
  var html = document.documentElement;
  var mqPhone = window.matchMedia('(max-width: 600px)');
  var mqCalm  = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ---------- storage (can throw in private mode / blocked storage) ---------- */
  function sGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function sSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function jGet(k) { try { var o = JSON.parse(sGet(k) || '{}'); return o && typeof o === 'object' ? o : {}; } catch (e) { return {}; } }

  var hideMap = jGet('wdmh-hide');   // key -> newest message id when it was dismissed
  var annMap  = jGet('wdmh-ann');    // key -> newest message id already announced
  var muted   = sGet('wdmh-mute') === '1';

  var state = { sig: String(CFG.sig || '0'), total: CFG.total || 0, heads: CFG.heads || [] };
  var openKey = null, openHead = null;

  /* ---------- tiny DOM helpers ---------- */
  function h(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }
  var ICON = {
    x:     '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    open:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>',
    bell:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
    mute:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.7 3A6 6 0 0 1 18 8a21.3 21.3 0 0 0 .6 5"/><path d="M17 17H3s3-2 3-9a4.67 4.67 0 0 1 .3-1.7"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/><path d="m2 2 20 20"/></svg>',
    send:  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>'
  };
  function iconBtn(cls, icon, label) {
    var b = h('button', cls);
    b.type = 'button';
    b.innerHTML = ICON[icon];
    b.title = label;
    b.setAttribute('aria-label', label);
    return b;
  }
  function avatar(p, cls) {
    var a = h('span', 'wdmh-av' + (cls ? ' ' + cls : '') + (p && p.group ? ' wdmh-av--group' : ''));
    if (p && p.photo) {
      var img = document.createElement('img');
      img.src = p.photo; img.alt = ''; img.draggable = false;
      img.onerror = function () { a.textContent = (p && p.initials) || '?'; };
      a.appendChild(img);
    } else {
      a.textContent = (p && p.initials) || '?';
    }
    return a;
  }
  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
  function vw() { return document.documentElement.clientWidth || window.innerWidth; }
  function vh() { return window.innerHeight; }
  function gap() { return mqPhone.matches ? 14 : 20; }
  function topLimit() {
    var tb = document.querySelector('.wd-topbar');
    return (tb ? Math.max(0, tb.getBoundingClientRect().bottom) : 0) + 8;
  }

  /* ---------- skeleton ---------- */
  var root    = h('div', 'wdmh');
  var stack   = h('div', 'wdmh-stack');
  var more    = h('a', 'wdmh-more');
  var preview = h('div', 'wdmh-preview');
  var target  = h('div', 'wdmh-dismiss');
  more.href = CFG.page;
  preview.setAttribute('role', 'status');
  preview.setAttribute('aria-live', 'polite');
  target.setAttribute('aria-hidden', 'true');
  target.innerHTML = ICON.x + '<span class="wdmh-dismiss__label">Drop to hide</span>';
  stack.setAttribute('role', 'group');
  stack.setAttribute('aria-label', 'New messages');
  stack.appendChild(more);
  root.appendChild(stack);
  root.appendChild(preview);
  root.appendChild(target);
  document.body.appendChild(root);

  /* ======================================================================
     Which heads, and where
     ====================================================================== */
  function isHidden(x) { return x.key !== openKey && (hideMap[x.key] || 0) >= x.last; }
  function visibleHeads() {
    // the open chat keeps its place in the stack even though it has just been read
    var list = state.heads.filter(function (x) { return (x.count > 0 || x.key === openKey) && !isHidden(x); });
    if (openHead && !list.some(function (x) { return x.key === openKey; })) { list.unshift(openHead); }
    return list;
  }

  /** where the stack starts: just above the Corner bubble, or where it would be */
  function anchor() {
    var cb = document.getElementById('wdcb'), bub = document.getElementById('wdcbBubble');
    if (cb && bub && !cb.classList.contains('is-dismissed') && bub.offsetWidth) {
      var r = bub.getBoundingClientRect();
      return { side: cb.getAttribute('data-side') || 'right', cx: r.left + r.width / 2, bottom: r.top - 10 };
    }
    var side = 'right';
    try { var p = JSON.parse(sGet('wdcb-pos') || 'null'); if (p && p.side === 'left') { side = 'left'; } } catch (e) {}
    var g = gap();
    return { side: side, cx: side === 'left' ? g + HEAD / 2 : vw() - g - HEAD / 2, bottom: vh() - g };
  }

  var els = {};            // key -> head element
  var shownKeys = [];

  function place(el, x, y, animate) {
    el.classList.toggle('is-moving', !!animate && !mqCalm.matches);
    el.style.transform = 'translate3d(' + Math.round(x) + 'px,' + Math.round(y) + 'px,0)';
  }

  function layout(animate) {
    var a = anchor();
    root.setAttribute('data-side', a.side);
    // as many as fit between the anchor and the top bar (the Corner bubble may be dragged up high)
    var fit = Math.max(1, Math.floor((a.bottom - topLimit() + 10) / STEP));
    var n = Math.min(shownKeys.length, fit);
    shownKeys.forEach(function (k, i) {
      var el = els[k];
      if (!el || el === drag.el) { return; }
      el.hidden = i >= n;
      place(el, a.cx - HEAD / 2, a.bottom - HEAD - i * STEP, animate);
    });
    // "+N" sits in the next slot, centred
    place(more, a.cx - 18, a.bottom - HEAD - n * STEP + (HEAD - 36) / 2, animate);
    placePreview(a);
    placeChat(a);
  }

  /* ======================================================================
     Heads
     ====================================================================== */
  function headLabel(x) {
    return x.name + (x.count > 0 ? ' — ' + x.count + ' new message' + (x.count > 1 ? 's' : '') : '') + '. Open chat';
  }

  function buildHead(x) {
    var el = h('button', 'wdmh-head');
    el.type = 'button';
    el.setAttribute('data-key', x.key);
    el.appendChild(avatar(x));
    el.appendChild(h('span', 'wdmh-badge'));
    var close = h('span', 'wdmh-head__x');
    close.innerHTML = ICON.x;
    close.title = 'Hide until there’s something new';
    el.appendChild(close);
    el.addEventListener('pointerdown', onDown);
    el.addEventListener('click', function (e) {
      if (suppressClick) { e.preventDefault(); return; }
      if (e.target.closest('.wdmh-head__x')) { dismissHead(x.key); return; }
      var cur = headByKey(x.key);
      if (cur) { toggleChat(cur); }
    });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); dismissHead(x.key); }
    });
    el.addEventListener('dragstart', function (e) { e.preventDefault(); });
    return el;
  }

  function headByKey(k) {
    if (openHead && openHead.key === k) {
      var live = state.heads.filter(function (x) { return x.key === k; })[0];
      return live && live.count > 0 && live.last > openHead.last ? live : openHead;
    }
    return state.heads.filter(function (x) { return x.key === k; })[0] || null;
  }

  function render(popKeys) {
    var list = visibleHeads();
    var shown = list.slice(0, SHOWN);
    var extra = (list.length - shown.length) + Math.max(0, state.total - state.heads.length);
    var keep = {};
    shown.forEach(function (x, i) {
      keep[x.key] = true;
      var el = els[x.key];
      if (!el) {
        el = els[x.key] = buildHead(x);
        stack.insertBefore(el, more);
        el.classList.add('is-pop');
        var a = anchor();   // appear in place, not flying in from the corner of the screen
        place(el, a.cx - HEAD / 2, a.bottom - HEAD - i * STEP, false);
      }
      var badge = el.querySelector('.wdmh-badge');
      badge.textContent = x.count > 99 ? '99+' : String(x.count);
      badge.hidden = !(x.count > 0) || x.key === openKey;
      el.classList.toggle('is-open', x.key === openKey);
      el.setAttribute('aria-label', headLabel(x));
      el.setAttribute('aria-expanded', x.key === openKey ? 'true' : 'false');
      el.title = x.name;
      if (popKeys && popKeys.indexOf(x.key) >= 0) {
        el.classList.remove('is-pop'); void el.offsetWidth; el.classList.add('is-pop');
      }
    });
    Object.keys(els).forEach(function (k) {
      if (keep[k]) { return; }
      var el = els[k];
      delete els[k];
      el.classList.add('is-leaving');
      setTimeout(function () { el.remove(); }, 220);
      if (previewKey === k) { hidePreview(); }
    });
    shownKeys = shown.map(function (x) { return x.key; });
    more.hidden = extra <= 0;
    more.textContent = '+' + extra;
    more.title = extra + ' more conversation' + (extra > 1 ? 's' : '') + ' with new messages';
    more.setAttribute('aria-label', more.title + ' — open Messages');
    root.classList.toggle('is-on', shown.length > 0 || openKey !== null);
    layout(true);
  }

  /* ======================================================================
     New messages: announce once (preview + ping)
     ====================================================================== */
  function saveMaps() {
    // only conversations that are still unread need remembering
    var live = {};
    state.heads.forEach(function (x) { live[x.key] = true; });
    [hideMap, annMap].forEach(function (m) { Object.keys(m).forEach(function (k) { if (!live[k]) { delete m[k]; } }); });
    sSet('wdmh-hide', JSON.stringify(hideMap));
    sSet('wdmh-ann', JSON.stringify(annMap));
  }

  function announce() {
    annMap = jGet('wdmh-ann');          // another tab may have announced already
    var fresh = [];
    state.heads.forEach(function (x) {
      if ((annMap[x.key] || 0) >= x.last) { return; }
      annMap[x.key] = x.last;
      if (x.key !== openKey && !isHidden(x)) { fresh.push(x); }
    });
    saveMaps();
    return fresh;
  }

  function update(j) {
    state.sig = String(j.hs);
    state.total = typeof j.unread === 'number' ? j.unread : state.total;
    if (!j.heads) { return; }
    state.heads = j.heads;
    var fresh = announce();
    render(fresh.map(function (x) { return x.key; }));
    if (fresh.length) {
      showPreview(fresh[0]);
      ping();
      desktopAlert(fresh[0]);
    }
  }

  /* ---------- desktop pop-up while WeDo is minimized / in another tab ---------- */
  function canAlert() { return 'Notification' in window && window.isSecureContext !== false; }
  function desktopAlert(x) {
    if (!document.hidden || !canAlert() || Notification.permission !== 'granted') { return; }
    try {
      var n = new Notification(x.name, {
        body: (x.group && x.from ? x.from + ': ' : '') + (x.preview || 'New message'),
        icon: x.photo || undefined,
        tag: 'wedo-msg-' + x.key            // one pop-up per conversation, replaced by the next
      });
      n.onclick = function () {
        window.focus();
        n.close();
        var cur = headByKey(x.key);
        if (cur) { openChat(cur); }
      };
    } catch (e) { /* some browsers only allow notifications from a service worker */ }
  }

  /* ---------- preview bubble ---------- */
  var previewKey = null, previewTimer = null;
  function showPreview(x) {
    if (!els[x.key] || (openKey && mqPhone.matches)) { return; }
    previewKey = x.key;
    preview.innerHTML = '';
    var body = h('button', 'wdmh-preview__body');
    body.type = 'button';
    body.appendChild(h('b', 'wdmh-preview__name', x.name));
    var line = h('span', 'wdmh-preview__text');
    if (x.group && x.from) { line.appendChild(h('span', 'wdmh-preview__from', x.from + ': ')); }
    line.appendChild(document.createTextNode(x.preview || 'New message'));
    body.appendChild(line);
    body.addEventListener('click', function () { hidePreview(); var cur = headByKey(x.key); if (cur) { openChat(cur); } });
    var close = iconBtn('wdmh-preview__x', 'x', 'Dismiss');
    close.addEventListener('click', hidePreview);
    preview.appendChild(body);
    preview.appendChild(close);
    preview.classList.add('is-shown');
    layout(false);
    clearTimeout(previewTimer);
    previewTimer = setTimeout(hidePreview, PREVIEW_MS);
  }
  function hidePreview() { clearTimeout(previewTimer); previewKey = null; preview.classList.remove('is-shown'); }
  preview.addEventListener('mouseenter', function () { clearTimeout(previewTimer); });
  preview.addEventListener('mouseleave', function () { if (previewKey) { previewTimer = setTimeout(hidePreview, 2500); } });

  function placePreview(a) {
    if (!previewKey) { return; }
    var i = shownKeys.indexOf(previewKey);
    if (i < 0) { hidePreview(); return; }
    var w = Math.min(280, vw() - HEAD - gap() * 2 - 16);
    var y = a.bottom - HEAD - i * STEP + HEAD / 2;
    preview.style.width = w + 'px';
    preview.style.top = Math.round(y) + 'px';
    preview.style.left = Math.round(a.side === 'left' ? a.cx + HEAD / 2 + 12 : a.cx - HEAD / 2 - 12 - w) + 'px';
  }

  /* ---------- soft ping (Web Audio, nothing to download) ---------- */
  var actx = null;
  function audio() {
    if (!actx) {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) { return null; }
      try { actx = new AC(); } catch (e) { return null; }
    }
    if (actx.state === 'suspended') { try { actx.resume(); } catch (e) {} }
    return actx;
  }
  // browsers only allow sound after the person has interacted with the page
  function unlock() { audio(); document.removeEventListener('pointerdown', unlock, true); document.removeEventListener('keydown', unlock, true); }
  document.addEventListener('pointerdown', unlock, true);
  document.addEventListener('keydown', unlock, true);

  function ping() {
    if (muted || document.querySelector('.wdc-ring, .wdc')) { return; }   // never over a ringing / live call
    var ac = audio();
    if (!ac || ac.state !== 'running') { return; }
    [[660, 0], [990, 0.13]].forEach(function (n) {
      var t = ac.currentTime + n[1];
      var o = ac.createOscillator(), g = ac.createGain();
      o.type = 'sine'; o.frequency.value = n[0];
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(0.12, t + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
      o.connect(g); g.connect(ac.destination);
      o.start(t); o.stop(t + 0.25);
    });
  }

  /* ======================================================================
     Drag a head onto the X to hide it
     ====================================================================== */
  var drag = { el: null }, suppressClick = false;

  function onDown(e) {
    if (e.button !== 0 || e.target.closest('.wdmh-head__x')) { return; }
    var el = e.currentTarget, r = el.getBoundingClientRect();
    drag = { el: null, cand: el, id: e.pointerId, sx: e.clientX, sy: e.clientY, ox: r.left, oy: r.top, hot: false };
    try { el.setPointerCapture(e.pointerId); } catch (err) {}
    el.addEventListener('pointermove', onMove);
    el.addEventListener('pointerup', onUp);
    el.addEventListener('pointercancel', onUp);
  }
  function hitTarget(cx, cy) {
    var r = target.getBoundingClientRect();
    var dx = cx - (r.left + r.width / 2), dy = cy - (r.top + r.height / 2);
    return Math.sqrt(dx * dx + dy * dy) < 64;
  }
  function onMove(e) {
    if (e.pointerId !== drag.id) { return; }
    var dx = e.clientX - drag.sx, dy = e.clientY - drag.sy;
    if (!drag.el) {
      if (Math.abs(dx) + Math.abs(dy) < 6) { return; }   // still a click
      drag.el = drag.cand;
      hidePreview();
      root.classList.add('is-dragging');
      html.classList.add('wdmh-dragging');
    }
    e.preventDefault();
    var x = clamp(drag.ox + dx, 4, vw() - HEAD - 4), y = clamp(drag.oy + dy, 4, vh() - HEAD - 4);
    drag.hot = hitTarget(x + HEAD / 2, y + HEAD / 2);
    target.classList.toggle('is-hot', drag.hot);
    if (drag.hot) {
      var r = target.getBoundingClientRect();
      x = r.left + r.width / 2 - HEAD / 2; y = r.top + r.height / 2 - HEAD / 2;
    }
    place(drag.el, x, y, drag.hot);
  }
  function onUp(e) {
    if (e.pointerId !== drag.id) { return; }
    var el = drag.cand, moved = drag.el, hot = drag.hot && e.type === 'pointerup';
    el.removeEventListener('pointermove', onMove);
    el.removeEventListener('pointerup', onUp);
    el.removeEventListener('pointercancel', onUp);
    drag = { el: null };
    if (!moved) { return; }
    suppressClick = true;
    setTimeout(function () { suppressClick = false; }, 0);
    root.classList.remove('is-dragging');
    html.classList.remove('wdmh-dragging');
    target.classList.remove('is-hot');
    if (hot) { dismissHead(el.getAttribute('data-key')); } else { layout(true); }
  }

  function dismissHead(key) {
    var x = headByKey(key);
    if (!x) { return; }
    hideMap[key] = x.last;
    annMap[key] = Math.max(annMap[key] || 0, x.last);
    saveMaps();
    if (openKey === key) { closeChat(); }
    if (previewKey === key) { hidePreview(); }
    render();
  }

  /* ======================================================================
     Mini chat window
     ====================================================================== */
  var chat = {
    el: h('section', 'wdmh-chat'), head: h('header', 'wdmh-chat__head'), list: h('div', 'wdmh-msgs'),
    form: h('form', 'wdmh-compose'), input: h('textarea', 'wdmh-input'), note: h('p', 'wdmh-note'), err: h('p', 'wdmh-err'),
    key: null, data: null, lastId: 0, ids: {}, seenUpTo: 0, timer: null, busy: false, prev: null
  };
  (function buildChat() {
    chat.el.setAttribute('role', 'dialog');
    chat.el.hidden = true;
    chat.list.setAttribute('role', 'log');
    chat.list.setAttribute('aria-live', 'polite');
    chat.list.tabIndex = 0;
    chat.input.rows = 1;
    chat.input.placeholder = 'Aa';
    chat.input.setAttribute('aria-label', 'Message');
    chat.input.maxLength = 2000;
    var send = iconBtn('wdmh-send', 'send', 'Send');
    send.type = 'submit';
    chat.form.appendChild(chat.input);
    chat.form.appendChild(send);
    chat.err.hidden = true;
    chat.note.hidden = true;
    chat.alerts = h('div', 'wdmh-alerts');
    chat.alerts.hidden = true;
    chat.alerts.appendChild(h('span', null, 'Get a pop-up for new messages when WeDo is minimized.'));
    var on = h('button', 'wdmh-alerts__on', 'Turn on');
    on.type = 'button';
    on.addEventListener('click', function () {
      chat.alerts.hidden = true;
      try {
        var p = Notification.requestPermission(function () {});   // old Safari: callback form
        if (p && p.then) { p.then(function () {}, function () {}); }
      } catch (e) {}
    });
    var no = iconBtn('wdmh-alerts__x', 'x', 'Not now');
    no.addEventListener('click', function () { chat.alerts.hidden = true; sSet('wdmh-noalert', '1'); });
    chat.alerts.appendChild(on);
    chat.alerts.appendChild(no);
    chat.el.appendChild(chat.head);
    chat.el.appendChild(chat.alerts);
    chat.el.appendChild(chat.list);
    chat.el.appendChild(chat.err);
    chat.el.appendChild(chat.note);
    chat.el.appendChild(chat.form);
    root.appendChild(chat.el);
  })();

  function api(params, post) {
    var url = CFG.api + '?' + new URLSearchParams(post ? { action: params.action } : params).toString();
    var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (post) {
      var body = new URLSearchParams(params);
      body.append('token', CFG.token);
      opts.method = 'POST';
      opts.body = body;
    }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return { status: 'error', msg: 'Unexpected server response (HTTP ' + r.status + ').' }; })
        .then(function (j) {
          if (!r.ok || j.status !== 'ok') { var e = new Error(j.msg || 'Something went wrong.'); e.http = r.status; throw e; }
          return j;
        });
    });
  }

  function chatHeader(x, sub) {
    var hd = chat.head;
    hd.innerHTML = '';
    hd.appendChild(avatar(x, 'wdmh-av--sm'));
    var t = h('div', 'wdmh-chat__title');
    t.appendChild(h('b', null, x.name));
    if (sub) { t.appendChild(h('span', null, sub)); }
    hd.appendChild(t);
    var bell = iconBtn('wdmh-ibtn', muted ? 'mute' : 'bell', muted ? 'Sound off — turn on' : 'Sound on — mute new-message sound');
    bell.setAttribute('aria-pressed', muted ? 'true' : 'false');
    bell.addEventListener('click', function () {
      muted = !muted;
      sSet('wdmh-mute', muted ? '1' : '0');
      if (!muted) { audio(); }
      chatHeader(x, sub);
    });
    hd.appendChild(bell);
    var full = h('a', 'wdmh-ibtn');
    full.href = CFG.page + '?with=' + encodeURIComponent(x.key);
    full.innerHTML = ICON.open;
    full.title = 'Open in Messages';
    full.setAttribute('aria-label', 'Open in Messages');
    hd.appendChild(full);
    var close = iconBtn('wdmh-ibtn', 'x', 'Close chat');
    close.addEventListener('click', function () { closeChat(true); });
    hd.appendChild(close);
    chat.el.setAttribute('aria-label', 'Chat with ' + x.name);
  }

  function toggleChat(x) { if (openKey === x.key) { closeChat(true); } else { openChat(x); } }

  function openChat(x) {
    if (openKey) { closeChat(false); }
    hidePreview();
    openKey = x.key;
    openHead = Object.assign({}, x);
    chat.key = x.key; chat.lastId = 0; chat.ids = {}; chat.seenUpTo = 0; chat.prev = null; chat.data = null;
    chat.list.innerHTML = '';
    chat.list.appendChild(h('p', 'wdmh-loading', 'Loading…'));
    chat.err.hidden = true;
    chat.note.hidden = true;
    chat.form.hidden = false;
    chatHeader(x, x.group ? 'Group chat' : '');
    chat.alerts.hidden = !(canAlert() && Notification.permission === 'default' && sGet('wdmh-noalert') !== '1');
    chat.el.hidden = false;
    root.classList.add('has-chat');
    html.classList.toggle('wdmh-lock', mqPhone.matches);
    render();
    setTimeout(function () { if (openKey === x.key) { chat.el.classList.add('is-shown'); } }, 0);
    loadChat(x.key, true);
  }

  function closeChat(restoreFocus) {
    if (!openKey) { return; }
    var key = openKey;
    clearTimeout(chat.timer);
    chat.el.classList.remove('is-shown');
    chat.el.hidden = true;
    root.classList.remove('has-chat');
    html.classList.remove('wdmh-lock');
    // what was open has just been read: drop it until the next check-in says otherwise
    state.heads.forEach(function (x) { if (x.key === key && x.last <= chat.lastId) { x.count = 0; } });
    openKey = null; openHead = null; chat.key = null;
    render();
    if (restoreFocus && els[key]) { try { els[key].focus({ preventScroll: true }); } catch (e) {} }
  }

  function loadChat(key, first) {
    clearTimeout(chat.timer);
    if (chat.key !== key) { return; }
    if (!first && document.hidden) { chat.timer = setTimeout(function () { loadChat(key, false); }, CHAT_POLL_MS); return; }
    api({ action: 'thread', with: key, after: chat.lastId }).then(function (j) {
      if (chat.key !== key) { return; }
      if (first) { firstLoad(key, j); } else { appendMessages(j.messages || []); }
      if (j.kind === 'dm') { chat.seenUpTo = j.seenUpTo || 0; markSeen(); }
      chat.timer = setTimeout(function () { loadChat(key, false); }, CHAT_POLL_MS);
    }).catch(function (e) {
      if (chat.key !== key) { return; }
      if (first) { chat.list.innerHTML = ''; chat.list.appendChild(h('p', 'wdmh-loading', e.message)); chat.form.hidden = true; return; }
      if (e.http === 401) { return; }
      chat.timer = setTimeout(function () { loadChat(key, false); }, CHAT_POLL_MS * 2);
    });
  }

  function firstLoad(key, j) {
    chat.data = j;
    chat.today = j.today;
    var x = openHead;
    if (j.kind === 'group') {
      chatHeader(x, j.group.members.length + ' members');
    } else if (j.person) {
      chatHeader(x, j.presence && j.presence.online ? 'Active now' : (j.person.position || ''));
    }
    chat.list.innerHTML = '';
    var msgs = j.messages || [];
    if (msgs.length > MAX_RENDER) {
      var older = h('a', 'wdmh-older', 'Earlier messages are in Messages');
      older.href = CFG.page + '?with=' + encodeURIComponent(key);
      chat.list.appendChild(older);
      msgs.forEach(function (m) { chat.ids[m.id] = true; chat.lastId = Math.max(chat.lastId, m.id); });
      msgs = msgs.slice(-MAX_RENDER);
    }
    if (!msgs.length) { chat.list.appendChild(h('p', 'wdmh-loading', 'No messages yet — say hi!')); }
    appendMessages(msgs, true);
    chat.list.scrollTop = chat.list.scrollHeight;
    if (j.canSend === false) {
      chat.form.hidden = true;
      chat.note.textContent = 'You can’t reply in this conversation.';
      chat.note.hidden = false;
    } else if (!mqPhone.matches) {
      try { chat.input.focus({ preventScroll: true }); } catch (e) {}
    }
    // read now: clear its badge here and on the envelope straight away
    var live = state.heads.filter(function (y) { return y.key === key; })[0];
    if (live && live.count > 0 && window.WDInbox) { window.WDInbox.set(Math.max(0, window.WDInbox.count() - 1)); }
    if (live) { live.count = 0; }
    render();
  }

  /* ---------- one message ---------- */
  var GIF_FILE = /^[a-z0-9][a-z0-9_-]{0,60}\.gif$/;
  function gifData(text) {
    try { var g = JSON.parse(text); } catch (e) { return null; }
    return g && typeof g.f === 'string' && GIF_FILE.test(g.f) ? g : null;
  }
  var URL_RE = /(https?:\/\/[^\s<>"']+|www\.[^\s<>"']+)/gi;
  function linkify(text) {
    var frag = document.createDocumentFragment(), last = 0, m;
    URL_RE.lastIndex = 0;
    while ((m = URL_RE.exec(text)) !== null) {
      var url = m[0].replace(/[.,!?;:)\]]+$/, '');
      if (m.index > last) { frag.appendChild(document.createTextNode(text.slice(last, m.index))); }
      var a = document.createElement('a');
      a.href = /^www\./i.test(url) ? 'https://' + url : url;
      a.target = '_blank'; a.rel = 'noopener noreferrer';
      a.textContent = url;
      frag.appendChild(a);
      last = m.index + url.length;
      URL_RE.lastIndex = last;
    }
    if (last < text.length) { frag.appendChild(document.createTextNode(text.slice(last))); }
    return frag;
  }
  var EMOJI_ONLY = null;
  try { EMOJI_ONLY = new RegExp('^(?:\\p{Extended_Pictographic}|\\p{Emoji_Component}|\\u200d|\\ufe0f|\\s)+$', 'u'); } catch (e) { /* old browser */ }
  function isEmojiOnly(t) { return !!EMOJI_ONLY && t.length <= 16 && EMOJI_ONLY.test(t) && /[^\s\d#*]/.test(t); }

  function parseAt(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(s || '');
    return m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) : null;
  }
  function timeLabel(d) {
    var hh = d.getHours(), mm = d.getMinutes();
    var t = ((hh % 12) || 12) + ':' + (mm < 10 ? '0' : '') + mm + ' ' + (hh < 12 ? 'AM' : 'PM');
    var ymd = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
    if (ymd === chat.today) { return t; }
    return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][d.getMonth()] + ' ' + d.getDate() + ', ' + t;
  }

  function messageEl(m, isGroup) {
    var d = parseAt(m.at);
    var frag = document.createDocumentFragment();
    // time stamp when the conversation picks up again after a while
    if (d && (!chat.prev || !chat.prev.d || d - chat.prev.d > 30 * 60 * 1000)) {
      frag.appendChild(h('div', 'wdmh-time', timeLabel(d)));
      chat.prev = null;
    }
    if (m.kind === 'event') {
      frag.appendChild(h('div', 'wdmh-event', m.text));
      chat.prev = { d: d, sender: null };
      return frag;
    }
    var sid = m.mine ? 'me' : (m.sender ? m.sender.id : 'them');
    var row = h('div', 'wdmh-row' + (m.mine ? ' is-mine' : ''));
    row.setAttribute('data-id', m.id || '');
    if (isGroup && !m.mine && (!chat.prev || chat.prev.sender !== sid) && m.sender) {
      frag.appendChild(h('div', 'wdmh-sender', m.sender.name.split(' ')[0]));
    }
    var b = h('div', 'wdmh-bubble');
    var gif = m.kind === 'gif' ? gifData(m.text) : null;
    if (gif) {
      b.classList.add('is-gif');
      var img = document.createElement('img');
      img.src = 'assets/gifs/' + gif.f; img.alt = gif.t || 'GIF'; img.loading = 'lazy';
      if (gif.w && gif.h) { img.width = gif.w; img.height = gif.h; }
      b.appendChild(img);
    } else if (m.kind === 'gif') {
      b.textContent = 'GIF';
    } else {
      if (isEmojiOnly(m.text)) { b.classList.add('is-emoji'); }
      b.appendChild(linkify(m.text));
    }
    if (d) { b.title = timeLabel(d); }
    row.appendChild(b);
    frag.appendChild(row);
    chat.prev = { d: d, sender: sid };
    return frag;
  }

  function appendMessages(msgs, initial) {
    var isGroup = chat.data && chat.data.kind === 'group';
    var nearBottom = chat.list.scrollHeight - chat.list.scrollTop - chat.list.clientHeight < 80;
    var added = false;
    msgs.forEach(function (m) {
      chat.lastId = Math.max(chat.lastId, m.id);
      if (chat.ids[m.id]) { return; }
      chat.ids[m.id] = true;
      var empty = chat.list.querySelector('.wdmh-loading');
      if (empty) { empty.remove(); }
      chat.list.appendChild(messageEl(m, isGroup));
      added = true;
    });
    if (added && (initial || nearBottom)) { chat.list.scrollTop = chat.list.scrollHeight; }
    markSeen();
  }

  function markSeen() {
    var old = chat.list.querySelector('.wdmh-seen');
    if (old) { old.remove(); }
    if (!chat.data || chat.data.kind !== 'dm' || !chat.seenUpTo) { return; }
    var rows = chat.list.querySelectorAll('.wdmh-row');
    var last = rows[rows.length - 1];
    if (!last || !last.classList.contains('is-mine')) { return; }
    var id = +last.getAttribute('data-id');
    if (id && id <= chat.seenUpTo) { last.parentNode.insertBefore(h('div', 'wdmh-seen', 'Seen'), last.nextSibling); }
  }

  /* ---------- sending ---------- */
  function autosize() {
    chat.input.style.height = 'auto';
    chat.input.style.height = Math.min(chat.input.scrollHeight, 96) + 'px';
  }
  chat.input.addEventListener('input', autosize);
  chat.input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); sendNow(); }
  });
  chat.form.addEventListener('submit', function (e) { e.preventDefault(); sendNow(); });

  function sendNow() {
    var text = chat.input.value.replace(/\s+$/, '');
    if (!text.trim() || !chat.key) { return; }
    var key = chat.key;
    chat.input.value = '';
    autosize();
    chat.err.hidden = true;
    var empty = chat.list.querySelector('.wdmh-loading');
    if (empty) { empty.remove(); }
    var temp = messageEl({ id: 0, mine: true, text: text, at: '', kind: 'text' }, false);
    var row = temp.querySelector('.wdmh-row');
    row.classList.add('is-pending');
    chat.list.appendChild(temp);
    chat.list.scrollTop = chat.list.scrollHeight;
    api({ action: 'send', with: key, text: text }, true).then(function (j) {
      if (chat.key !== key) { return; }
      var m = j.message || {};
      if (m.id && chat.ids[m.id]) { row.remove(); markSeen(); return; }   // the check-in got here first
      row.classList.remove('is-pending');
      row.setAttribute('data-id', m.id || '');
      if (m.id) { chat.ids[m.id] = true; chat.lastId = Math.max(chat.lastId, m.id); }
      markSeen();
    }).catch(function (e) {
      if (chat.key !== key) { return; }
      row.remove();
      if (!chat.input.value) { chat.input.value = text; autosize(); }
      chat.err.textContent = 'Not sent: ' + e.message;
      chat.err.hidden = false;
    });
  }

  function placeChat(a) {
    if (!openKey || mqPhone.matches) {
      chat.el.style.left = chat.el.style.top = chat.el.style.width = chat.el.style.height = '';
      return;
    }
    var W = vw(), H = vh(), m = 12, top0 = topLimit();
    var w = Math.min(340, W - HEAD - gap() * 2 - m * 2);
    var hgt = Math.min(480, H - top0 - m);
    var bottom = clamp(a.bottom + 10 + HEAD, top0 + hgt, H - m);   // bottom-aligned with the stack's base
    var left = a.side === 'left' ? a.cx + HEAD / 2 + m : a.cx - HEAD / 2 - m - w;
    chat.el.style.width = w + 'px';
    chat.el.style.height = hgt + 'px';
    chat.el.style.left = Math.round(clamp(left, m, W - w - m)) + 'px';
    chat.el.style.top = Math.round(bottom - hgt) + 'px';
  }

  // Esc closes the chat; opening the Corner panel closes it too (they'd overlap)
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Escape' || e.key === 'Esc') && openKey && chat.el.contains(document.activeElement)) {
      if (document.body.classList.contains('modal-open')) { return; }
      e.preventDefault();
      closeChat(true);
    }
  });
  document.addEventListener('pointerdown', function (e) {
    if (openKey && e.target.closest && e.target.closest('#wdcb')) { closeChat(false); }
  }, true);

  /* ---------- keep in place ---------- */
  document.addEventListener('wdcb:moved', function (e) { layout(!!(e.detail && e.detail.animate)); });
  var raf = 0;
  window.addEventListener('resize', function () {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(function () { html.classList.toggle('wdmh-lock', !!openKey && mqPhone.matches); layout(false); });
  });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && openKey) { loadChat(openKey, false); }
  });

  window.WDHeads = { sig: function () { return state.sig; }, update: update };

  /* ---------- first paint ---------- */
  root.classList.add('is-ready');
  var fresh = announce();
  render(fresh.map(function (x) { return x.key; }));
  if (fresh.length) { setTimeout(function () { showPreview(fresh[0]); ping(); }, 400); }
})();
