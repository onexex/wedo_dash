/* ==========================================================================
   Floating Corner bubble — see includes/corner-bubble.php for the markup.
   No jQuery: it also runs on the legacy (includes/header.php) pages.

   - click / Enter / Space: open or close the panel (ctrl/cmd/middle-click on
     the bubble still opens the Corner page natively, it's a real link)
   - drag: move it; on release it snaps to the nearest left/right edge and the
     spot is remembered. Dropping it on the X hides it for this tab's session.
   - Announcements tab: lazy-loaded feed; viewing it marks new posts as seen,
     which clears the bubble badge and the sidebar Corner badge.
   - Calendar tab: the same calendar as the Corner page (calendar-ajax.php).
   - The unseen count is re-checked every few minutes while the tab is visible.
   ========================================================================== */
(function () {
  'use strict';

  var root = document.getElementById('wdcb');
  if (!root || root.getAttribute('data-ready')) { return; }
  root.setAttribute('data-ready', '1');

  var API = root.getAttribute('data-api');
  var CAL = root.getAttribute('data-cal');
  var bubble   = root.querySelector('.wdcb-bubble');
  var badge    = root.querySelector('.wdcb-badge');
  var panel    = root.querySelector('.wdcb-panel');
  var dismiss  = root.querySelector('.wdcb-dismiss');
  var hint     = root.querySelector('.wdcb-hint');
  var toastEl  = root.querySelector('.wdcb-toast');
  var tabs     = panel.querySelectorAll('[role="tab"]');
  var tabCount = panel.querySelector('.wdcb-tabcount');
  var paneAnn  = document.getElementById('wdcbAnn');
  var paneCal  = document.getElementById('wdcbCal');
  var calBox   = paneCal.querySelector('.wdcb-calbox');
  var html     = document.documentElement;
  var name     = root.getAttribute('data-name') || 'Corner';

  var mqPhone  = window.matchMedia('(max-width: 600px)');
  var mqCalm   = window.matchMedia('(prefers-reduced-motion: reduce)');

  var unseen   = parseInt(root.getAttribute('data-unseen'), 10) || 0;
  var isOpen   = false;
  var current  = 'ann';
  var cur      = { x: 0, y: 0 };   // bubble top-left, px in the viewport

  /* ---------- storage (can throw in private mode / blocked storage) ---------- */
  function sGet(k, session) { try { return (session ? sessionStorage : localStorage).getItem(k); } catch (e) { return null; } }
  function sSet(k, v, session) { try { (session ? sessionStorage : localStorage).setItem(k, v); } catch (e) {} }
  function sDel(k, session) { try { (session ? sessionStorage : localStorage).removeItem(k); } catch (e) {} }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

  /* ======================================================================
     Position + drag
     ====================================================================== */
  function vw() { return document.documentElement.clientWidth || window.innerWidth; }
  function vh() { return window.innerHeight; }
  function size() { return bubble.offsetWidth || 56; }
  function gap() { return mqPhone.matches ? 14 : 20; }
  // keep the bubble below the top bar
  function topLimit() {
    var tb = document.querySelector('.wd-topbar');
    return (tb ? Math.max(0, tb.getBoundingClientRect().bottom) : 0) + 8;
  }

  // saved as { side, bottom } (distance from the viewport bottom) so it keeps
  // its place relative to the bottom edge when the window is resized
  var pos = { side: 'right', bottom: null };
  (function loadPos() {
    try {
      var p = JSON.parse(sGet('wdcb-pos') || 'null');
      if (p && (p.side === 'left' || p.side === 'right') && typeof p.bottom === 'number') { pos = p; }
    } catch (e) {}
  })();

  function setXY(x, y, animate) {
    cur.x = x; cur.y = y;
    bubble.classList.toggle('is-snapping', !!animate && !mqCalm.matches);
    bubble.style.transform = 'translate3d(' + Math.round(x) + 'px,' + Math.round(y) + 'px,0)';
  }

  function place(animate) {
    var s = size(), g = gap();
    var bottom = pos.bottom == null ? g : pos.bottom;
    bottom = clamp(bottom, g, Math.max(g, vh() - s - topLimit()));
    var x = pos.side === 'left' ? g : vw() - g - s;
    setXY(x, vh() - bottom - s, animate);
    root.setAttribute('data-side', pos.side);
  }

  var drag = null, suppressClick = false;

  function dismissHit(cx, cy) {
    var r = dismiss.getBoundingClientRect();
    var dx = cx - (r.left + r.width / 2), dy = cy - (r.top + r.height / 2);
    return Math.sqrt(dx * dx + dy * dy) < 64;
  }

  bubble.addEventListener('pointerdown', function (e) {
    if (e.button !== 0 || root.classList.contains('is-dismissed')) { return; }
    drag = { id: e.pointerId, sx: e.clientX, sy: e.clientY, ox: cur.x, oy: cur.y, moved: false, hot: false };
    // capture now, so a quick flick that leaves the bubble in one move still drags
    try { bubble.setPointerCapture(e.pointerId); } catch (err) {}
  });

  bubble.addEventListener('pointermove', function (e) {
    if (!drag || e.pointerId !== drag.id) { return; }
    var dx = e.clientX - drag.sx, dy = e.clientY - drag.sy;
    if (!drag.moved) {
      if (Math.abs(dx) + Math.abs(dy) < 6) { return; }  // still a click
      drag.moved = true;
      closePanel(false);
      hideHint();
      root.classList.add('is-dragging');
      html.classList.add('wdcb-dragging');
    }
    e.preventDefault();
    var s = size();
    var x = clamp(drag.ox + dx, 4, vw() - s - 4);
    var y = clamp(drag.oy + dy, 4, vh() - s - 4);
    drag.hot = dismissHit(x + s / 2, y + s / 2);
    dismiss.classList.toggle('is-hot', drag.hot);
    if (drag.hot) {                                     // magnet onto the X
      var r = dismiss.getBoundingClientRect();
      x = r.left + r.width / 2 - s / 2; y = r.top + r.height / 2 - s / 2;
    }
    setXY(x, y, drag.hot);
  });

  function endDrag(e, cancelled) {
    if (!drag || e.pointerId !== drag.id) { return; }
    var d = drag; drag = null;
    if (!d.moved) { return; }
    suppressClick = true;
    setTimeout(function () { suppressClick = false; }, 0);
    root.classList.remove('is-dragging');
    html.classList.remove('wdcb-dragging');
    dismiss.classList.remove('is-hot');
    if (d.hot && !cancelled) { hideBubble(); return; }
    var s = size();
    pos.side = (cur.x + s / 2) < vw() / 2 ? 'left' : 'right';
    pos.bottom = vh() - (cur.y + s);
    sSet('wdcb-pos', JSON.stringify(pos));
    place(true);
  }
  bubble.addEventListener('pointerup', function (e) { endDrag(e, false); });
  bubble.addEventListener('pointercancel', function (e) { endDrag(e, true); });
  bubble.addEventListener('dragstart', function (e) { e.preventDefault(); });

  bubble.addEventListener('click', function (e) {
    // modified / middle click: let the link open the Corner page normally
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button > 0) { return; }
    e.preventDefault();
    if (suppressClick) { return; }
    togglePanel();
  });
  bubble.addEventListener('keydown', function (e) {
    if (e.key === ' ' || e.key === 'Spacebar') { e.preventDefault(); togglePanel(); }
  });

  /* ---------- hide for this session (drop on the X) ---------- */
  function hideBubble() {
    closePanel(false);
    root.classList.add('is-dismissed');
    html.classList.remove('wdcb-on');
    sSet('wdcb-hidden', '1', true);
    // put it back in its last docked spot in case they undo
    place(false);
    toast('Corner bubble hidden for now. The Corner is still in the menu.', 'Undo', function () {
      sDel('wdcb-hidden', true);
      root.classList.remove('is-dismissed');
      html.classList.add('wdcb-on');
      place(false);
    });
  }

  var toastTimer = null;
  function toast(msg, actionLabel, action) {
    toastEl.querySelector('.wdcb-toast__msg').textContent = msg;
    var btn = toastEl.querySelector('.wdcb-toast__btn');
    btn.hidden = !action;
    btn.textContent = actionLabel || '';
    btn.onclick = function () { hideToast(); if (action) { action(); } };
    toastEl.classList.add('is-shown');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 8000);
  }
  function hideToast() { clearTimeout(toastTimer); toastEl.classList.remove('is-shown'); }
  // don't let it vanish while someone is reaching for Undo
  function holdToast() { clearTimeout(toastTimer); }
  function releaseToast() {
    clearTimeout(toastTimer);
    if (toastEl.classList.contains('is-shown')) { toastTimer = setTimeout(hideToast, 4000); }
  }
  toastEl.addEventListener('mouseenter', holdToast);
  toastEl.addEventListener('focusin', holdToast);
  toastEl.addEventListener('mouseleave', releaseToast);
  toastEl.addEventListener('focusout', releaseToast);

  /* ======================================================================
     Panel
     ====================================================================== */
  function positionPanel() {
    if (mqPhone.matches) {            // bottom sheet: CSS owns the geometry
      panel.style.left = panel.style.top = panel.style.width = panel.style.height = panel.style.transformOrigin = '';
      return;
    }
    var s = size(), W = vw(), H = vh(), margin = 12;
    var w = Math.min(380, W - s - gap() - margin * 2);
    var h = Math.min(600, H - margin * 2);
    var left = pos.side === 'left' ? cur.x + s + margin : cur.x - margin - w;
    left = clamp(left, margin, W - w - margin);
    var top = clamp(cur.y + s - h, margin, H - h - margin);   // bottom-aligned with the bubble
    panel.style.width = w + 'px';
    panel.style.height = h + 'px';
    panel.style.left = left + 'px';
    panel.style.top = top + 'px';
    panel.style.transformOrigin = (pos.side === 'left' ? '0 ' : '100% ') + clamp(cur.y + s / 2 - top, 0, h) + 'px';
  }

  function modalMode() {
    // phones: the sheet is modal (backdrop + scroll lock + focus trap)
    if (isOpen && mqPhone.matches) {
      panel.setAttribute('aria-modal', 'true');
      html.classList.add('wdcb-lock');
    } else {
      panel.removeAttribute('aria-modal');
      html.classList.remove('wdcb-lock');
    }
  }

  function openPanel() {
    if (isOpen) { return; }
    isOpen = true;
    hideHint();
    positionPanel();
    root.classList.add('is-open');
    bubble.setAttribute('aria-expanded', 'true');
    modalMode();
    // new posts win; otherwise reopen the tab they used last
    selectTab(unseen > 0 ? 'ann' : (sGet('wdcb-tab') || 'ann'), false);
    setTimeout(function () {
      var t = panel.querySelector('[role="tab"][aria-selected="true"]');
      if (t && isOpen) { t.focus({ preventScroll: true }); }
    }, 60);
  }

  function closePanel(restoreFocus) {
    if (!isOpen) { return; }
    isOpen = false;
    root.classList.remove('is-open');
    bubble.setAttribute('aria-expanded', 'false');
    modalMode();
    if (restoreFocus) { try { bubble.focus({ preventScroll: true }); } catch (e) {} }
  }

  function togglePanel() { if (isOpen) { closePanel(true); } else { openPanel(); } }

  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-wdcb-close]')) { closePanel(true); }
  });

  // click anywhere else closes it (desktop popover behaviour)
  document.addEventListener('pointerdown', function (e) {
    if (!isOpen) { return; }
    if (panel.contains(e.target) || bubble.contains(e.target)) { return; }
    if (e.target.closest && e.target.closest('.modal, .wdcb-toast')) { return; }
    closePanel(false);
  }, true);

  document.addEventListener('keydown', function (e) {
    if (!isOpen) { return; }
    if (e.key === 'Escape' || e.key === 'Esc') {
      if (document.body.classList.contains('modal-open')) { return; }  // a Bootstrap modal owns Esc
      e.preventDefault();
      closePanel(true);
      return;
    }
    // focus trap for the phone bottom sheet
    if (e.key === 'Tab' && mqPhone.matches) {
      var f = Array.prototype.filter.call(
        panel.querySelectorAll('a[href],button:not([disabled]),select,[tabindex]:not([tabindex="-1"])'),
        function (el) { return el.offsetParent !== null; });
      if (!f.length) { return; }
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      else if (!panel.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
    }
  });

  /* ---------- tabs ---------- */
  function selectTab(tab, focus) {
    current = tab === 'cal' ? 'cal' : 'ann';
    Array.prototype.forEach.call(tabs, function (t) {
      var on = t.getAttribute('data-tab') === current;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      if (on && focus) { t.focus(); }
    });
    paneAnn.hidden = current !== 'ann';
    paneCal.hidden = current !== 'cal';
    sSet('wdcb-tab', current);
    if (current === 'ann') { loadFeed(false); fitPosts(); } else { loadCal(false); }
  }
  Array.prototype.forEach.call(tabs, function (t, i) {
    t.addEventListener('click', function () { selectTab(t.getAttribute('data-tab'), false); });
    t.addEventListener('keydown', function (e) {
      var n = null;
      if (e.key === 'ArrowRight') { n = (i + 1) % tabs.length; }
      if (e.key === 'ArrowLeft')  { n = (i - 1 + tabs.length) % tabs.length; }
      if (e.key === 'Home') { n = 0; }
      if (e.key === 'End')  { n = tabs.length - 1; }
      if (n !== null) { e.preventDefault(); selectTab(tabs[n].getAttribute('data-tab'), true); }
    });
  });

  /* ---------- shared states ---------- */
  function errorHtml(kind, what) {
    if (kind === 'session') {
      return '<div class="wdcb-error"><div class="wdcb-empty__icon" aria-hidden="true">!</div>' +
        '<p>Your session has ended</p><span>Reload the page to sign in again.</span><br>' +
        '<button type="button" class="wdcb-btn" data-wdcb-reload>Reload</button></div>';
    }
    return '<div class="wdcb-error"><div class="wdcb-empty__icon" aria-hidden="true">!</div>' +
      '<p>Couldn’t load ' + esc(what) + '</p><span>Check your connection and try again.</span><br>' +
      '<button type="button" class="wdcb-btn" data-wdcb-retry>Try again</button></div>';
  }
  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-wdcb-reload]')) { location.reload(); return; }
    if (e.target.closest('[data-wdcb-retry]')) {
      if (current === 'ann') { loadFeed(true); } else { loadCal(true); }
    }
  });
  function fetchErr(res) { var e = new Error('http'); e.kind = res.status === 401 ? 'session' : 'http'; return e; }

  /* ======================================================================
     Announcements
     ====================================================================== */
  var feed = { loaded: false, loading: false, at: 0 };
  var SKELETON = (function () {
    var one = '<div class="wdcb-skel"><div class="wdcb-skel__a"></div><div class="wdcb-skel__l">' +
      '<div class="wdcb-skel__line" style="width:45%"></div><div class="wdcb-skel__line"></div><div class="wdcb-skel__line" style="width:75%"></div></div></div>';
    return '<div aria-busy="true" aria-label="Loading announcements">' + one + one + one + '</div>';
  })();

  function loadFeed(force) {
    if (feed.loading) { return; }
    var fresh = feed.loaded && (Date.now() - feed.at < 60000);
    if (fresh && !force) { markSeen(); return; }
    if (!feed.loaded) { paneAnn.innerHTML = SKELETON; }
    feed.loading = true;
    fetch(API + '?action=feed', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) { throw fetchErr(r); } return r.text(); })
      .then(function (body) {
        var top = paneAnn.scrollTop;
        paneAnn.innerHTML = body;
        paneAnn.scrollTop = feed.loaded ? top : 0;
        feed.loaded = true; feed.at = Date.now();
        fitPosts();
        markSeen();
      })
      .catch(function (err) {
        // keep showing what we had if a background refresh fails
        if (!feed.loaded) { paneAnn.innerHTML = errorHtml(err && err.kind, 'announcements'); }
      })
      .then(function () { feed.loading = false; });
  }

  // only offer "See more" on posts the 5-line clamp actually cuts
  function fitPosts() {
    if (paneAnn.hidden) { return; }
    Array.prototype.forEach.call(paneAnn.querySelectorAll('.wdcb-post'), function (p) {
      if (p.classList.contains('is-expanded')) { return; }
      var b = p.querySelector('.wdcb-post__body'), more = p.querySelector('.wdcb-post__more');
      if (b && more) { more.hidden = b.scrollHeight <= b.clientHeight + 2; }
    });
  }
  paneAnn.addEventListener('click', function (e) {
    var more = e.target.closest('.wdcb-post__more');
    if (!more) { return; }
    var p = more.closest('.wdcb-post');
    var open = p.classList.toggle('is-expanded');
    more.textContent = open ? 'See less' : 'See more';
    more.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  var seenBusy = false;
  function markSeen() {
    if (!isOpen || current !== 'ann' || unseen <= 0 || seenBusy) { return; }
    seenBusy = true;
    fetch(API + '?action=seen', { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (r.ok) { setUnseen(0); } })
      .catch(function () {})
      .then(function () { seenBusy = false; });
  }

  function setUnseen(n) {
    unseen = Math.max(0, n | 0);
    var txt = unseen > 99 ? '99+' : String(unseen);
    badge.textContent = txt;
    root.classList.toggle('has-unseen', unseen > 0);
    root.setAttribute('data-unseen', unseen);
    var label = name + (unseen > 0 ? ' — ' + unseen + ' new announcement' + (unseen > 1 ? 's' : '') : '');
    bubble.setAttribute('aria-label', label);
    bubble.title = label;
    tabCount.textContent = txt;
    tabCount.hidden = unseen <= 0;
    // keep the sidebar's Corner badge in step
    var nav = document.querySelector('.wd-sidebar a.wd-nav[href="corner"]');
    if (nav) {
      var nb = nav.querySelector('.wd-nav__badge');
      if (unseen > 0) {
        if (!nb) { nb = document.createElement('span'); nb.className = 'wd-nav__badge'; nav.appendChild(nb); }
        nb.textContent = txt;
      } else if (nb) { nb.parentNode.removeChild(nb); }
      nav.title = label;
    }
  }

  /* ---------- periodic badge refresh ---------- */
  var lastPoll = Date.now();
  function poll() {
    if (document.hidden || Date.now() - lastPoll < 30000) { return; }
    lastPoll = Date.now();
    fetch(API + '?action=count', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || typeof d.unseen !== 'number') { return; }
        var grew = d.unseen > unseen;
        setUnseen(d.unseen);
        if (grew) {
          feed.at = 0;  // stale: refetch on next view
          if (isOpen && current === 'ann') { loadFeed(true); }
          else if (!mqCalm.matches) {
            bubble.classList.remove('is-bump'); void bubble.offsetWidth; bubble.classList.add('is-bump');
          }
        }
      })
      .catch(function () {});
  }
  setInterval(poll, 180000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); } });

  /* ======================================================================
     Calendar (same markup + behaviour as corner.php)
     ====================================================================== */
  var cal = { loaded: false, ctrl: null };

  function loadCal(force, month, year) {
    if (cal.loaded && !force) { return; }
    var outer = calBox.querySelector('.wd-cal');
    if (!cal.loaded) {
      calBox.innerHTML = '<div aria-busy="true" aria-label="Loading calendar"><div class="wdcb-skel__line" style="height:36px;margin-bottom:12px"></div><div class="wdcb-skel__block"></div></div>';
    } else if (outer) { outer.classList.add('is-loading'); }
    if (cal.ctrl) { cal.ctrl.abort(); }
    var ctrl = window.AbortController ? new AbortController() : null;
    cal.ctrl = ctrl;
    var body = new URLSearchParams();
    if (month && year) { body.set('month', month); body.set('year', year); }
    fetch(CAL, { method: 'POST', body: body, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined,
                 headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (!r.ok) { throw fetchErr(r); } return r.text(); })
      .then(function (h) {
        calBox.innerHTML = h;
        cal.loaded = true;
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') { return; }
        if (!cal.loaded) { calBox.innerHTML = errorHtml(err && err.kind, 'the calendar'); }
        else if (outer) { outer.classList.remove('is-loading'); }
      })
      .then(function () { if (cal.ctrl === ctrl) { cal.ctrl = null; } });
  }

  function showDay(cell) {
    if (!cell) { return; }
    var day = cell.getAttribute('data-day');
    Array.prototype.forEach.call(calBox.querySelectorAll('.clckday.is-active, .wd-cal__hitem.is-active'), function (el) { el.classList.remove('is-active'); });
    cell.classList.add('is-active');
    Array.prototype.forEach.call(calBox.querySelectorAll('.wd-cal__hitem[data-day="' + day + '"]'), function (el) { el.classList.add('is-active'); });
    // day detail (holiday + birthdays) is pre-rendered by class.calendar.php
    var tpl = calBox.querySelector('.wd-cal__tpl[data-day="' + day + '"]');
    var detail = calBox.querySelector('.wd-cal__detail');
    if (tpl && detail) { detail.innerHTML = tpl.innerHTML; }
  }

  calBox.addEventListener('click', function (e) {
    var t = e.target;
    var nav = t.closest('.prev, .next, .today');
    if (nav && !nav.disabled) { loadCal(true, nav.getAttribute('data-month'), nav.getAttribute('data-year')); return; }
    var cell = t.closest('.clckday');
    if (cell) { showDay(cell); return; }
    var item = t.closest('.wd-cal__hitem');
    if (item) { showDay(calBox.querySelector('.clckday[data-day="' + item.getAttribute('data-day') + '"]')); }
  });
  calBox.addEventListener('change', function (e) {
    if (!e.target.matches('select')) { return; }
    var m = calBox.querySelector('.wd-cal__select--month'), y = calBox.querySelector('.wd-cal__select--year');
    if (m && y) { loadCal(true, m.value, y.value); }
  });
  calBox.addEventListener('keydown', function (e) {
    var cell = e.target.closest && e.target.closest('.clckday');
    if (cell && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); showDay(cell); return; }
    if (e.target.matches && e.target.matches('select')) { return; }
    var btn = e.key === 'ArrowLeft' ? calBox.querySelector('.prev') : e.key === 'ArrowRight' ? calBox.querySelector('.next') : null;
    if (btn) { e.preventDefault(); btn.click(); }
  });

  /* ======================================================================
     First-run tip
     ====================================================================== */
  var hintTimer = null;
  function showHint() {
    if (sGet('wdcb-hint') || isOpen || root.classList.contains('is-dismissed')) { return; }
    sSet('wdcb-hint', '1');
    hint.classList.add('is-shown');
    var s = size(), w = hint.offsetWidth, h = hint.offsetHeight, W = vw();
    var left = pos.side === 'left' ? cur.x + s + 12 : cur.x - 12 - w;
    if (mqPhone.matches || left < 8 || left + w > W - 8) {   // no room beside it: sit above
      left = clamp(pos.side === 'left' ? cur.x : cur.x + s - w, 8, W - w - 8);
      hint.style.top = Math.max(8, cur.y - h - 12) + 'px';
    } else {
      hint.style.top = clamp(cur.y + s / 2 - h / 2, 8, vh() - h - 8) + 'px';
    }
    hint.style.left = left + 'px';
    hintTimer = setTimeout(function () { hideHint(); }, 9000);
  }
  function hideHint() { clearTimeout(hintTimer); hint.classList.remove('is-shown'); }
  hint.querySelector('.wdcb-hint__x').addEventListener('click', function () { hideHint(); });

  /* ======================================================================
     Boot
     ====================================================================== */
  if (sGet('wdcb-hidden', true)) { root.classList.add('is-dismissed'); }
  else { html.classList.add('wdcb-on'); }
  root.classList.add('is-ready');
  place(false);

  var resizeRaf = 0;
  function onResize() {
    cancelAnimationFrame(resizeRaf);
    resizeRaf = requestAnimationFrame(function () {
      place(false);
      if (isOpen) { positionPanel(); modalMode(); fitPosts(); }
      if (hint.classList.contains('is-shown')) { hideHint(); }
    });
  }
  window.addEventListener('resize', onResize);
  window.addEventListener('orientationchange', onResize);
  // the Bootstrap sidebar toggle can change the top bar; fonts can shift sizes
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { place(false); }); }

  setTimeout(showHint, 1500);
})();
