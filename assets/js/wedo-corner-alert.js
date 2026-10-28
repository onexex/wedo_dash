/* ==========================================================================
   wedo-corner-alert.js — pop-up for a new Corner announcement or poll, on every
   signed-in page. Rides on the 3-second check-in in assets/js/wedo-call.js
   (query/Query-calls.php?action=incoming&ca=AID → includes/corner-lib.php
   wd_corner_alert): an in-page card, plus a desktop notification while WeDo is
   in the background (when the browser allows notifications).
   The last post this browser was told about is kept per user in localStorage,
   so a post pops up once, not on every page.
   Self-contained (injects its own styles) so legacy pages work too.
   ========================================================================== */
(function () {
  'use strict';
  var me = (window.WD_CALL && window.WD_CALL.me) || '';
  var KEY = 'wd.corner.after.' + me;
  var card = null, hideTimer = null;

  var mem = -1;   // when storage is blocked: remember for this page only
  function read() {
    try { var v = localStorage.getItem(KEY); return v === null ? mem : parseInt(v, 10) || 0; } catch (e) { return mem; }
  }
  function write(v) { mem = v; try { localStorage.setItem(KEY, String(v)); } catch (e) { /* private mode */ } }

  var ICONS = { poll: 'fa-square-poll-horizontal', bday: 'fa-cake-candles', news: 'fa-bullhorn' };

  function css() {
    if (document.getElementById('wdca-css')) { return; }
    var s = document.createElement('style');
    s.id = 'wdca-css';
    s.textContent =
      '.wdca{position:fixed;top:76px;right:20px;z-index:1200;width:340px;max-width:calc(100vw - 32px);display:flex;gap:12px;' +
      'padding:14px 14px 14px 16px;background:#fff;border:1px solid #e7eaf0;border-left:4px solid #f93627;border-radius:14px;' +
      'box-shadow:0 18px 44px rgba(16,24,40,.22);font-family:Biryani,system-ui,-apple-system,"Segoe UI",sans-serif;color:#16203a;' +
      'transform:translateX(24px);opacity:0;transition:transform .25s cubic-bezier(.2,.8,.2,1),opacity .2s}' +
      '.wdca.is-in{transform:none;opacity:1}' +
      '.wdca__icon{width:38px;height:38px;flex:0 0 38px;border-radius:11px;background:rgba(249,54,39,.1);color:#f93627;display:flex;' +
      'align-items:center;justify-content:center;font-size:16px}' +
      '.wdca__main{flex:1 1 auto;min-width:0}' +
      '.wdca__title{display:block;font-weight:700;font-size:13px;line-height:1.35;margin:1px 0 3px}' +
      '.wdca__text{display:block;font-size:13px;line-height:1.45;color:#586173;word-break:break-word}' +
      '.wdca__go{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:7px 13px;border-radius:9px;background:#f93627;' +
      'color:#fff !important;font-size:12.5px;font-weight:700;text-decoration:none !important}' +
      '.wdca__go:hover{background:#e22d1f}' +
      '.wdca__x{align-self:flex-start;width:28px;height:28px;flex:0 0 28px;border:0;border-radius:50%;background:none;color:#909aab;' +
      'cursor:pointer;font-size:15px;line-height:1}' +
      '.wdca__x:hover{background:#f0f2f5;color:#16203a}' +
      '@media (max-width:620px){.wdca{top:auto;bottom:16px;right:16px;left:16px;width:auto}}' +
      '@media (prefers-reduced-motion:reduce){.wdca{transition:none}}';
    document.head.appendChild(s);
  }

  function hide() {
    clearTimeout(hideTimer);
    if (!card) { return; }
    var c = card; card = null;
    c.classList.remove('is-in');
    setTimeout(function () { if (c.parentNode) { c.parentNode.removeChild(c); } }, 250);
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined) { n.textContent = text; }
    return n;
  }

  function showCard(x) {
    css();
    hide();
    var c = el('div', 'wdca');
    c.setAttribute('role', 'status');
    c.setAttribute('aria-live', 'polite');
    var icon = el('span', 'wdca__icon');
    icon.appendChild(el('i', 'fa-solid ' + (ICONS[x.kind] || ICONS.news)));
    var main = el('div', 'wdca__main');
    main.appendChild(el('b', 'wdca__title', x.title));
    if (x.text) { main.appendChild(el('span', 'wdca__text', x.text)); }
    var go = el('a', 'wdca__go', x.kind === 'poll' ? 'Vote now' : 'Open the Corner');
    go.href = x.url;
    go.addEventListener('click', function (e) {
      // already on the Corner: a same-page link would only change the #hash, so
      // scroll to a poll that is on the page, otherwise reload to show the new post
      if (!/\/corner(\.php)?$/.test(location.pathname)) { return; }
      e.preventDefault();
      hide();
      var id = x.url.split('#')[1], target = id && document.getElementById(id);
      if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
      if (id) { location.hash = id; }
      location.reload();
    });
    main.appendChild(go);
    var close = el('button', 'wdca__x', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss');
    close.addEventListener('click', hide);
    c.appendChild(icon); c.appendChild(main); c.appendChild(close);
    c.addEventListener('mouseenter', function () { clearTimeout(hideTimer); });
    c.addEventListener('mouseleave', function () { hideTimer = setTimeout(hide, 6000); });
    document.body.appendChild(c);
    card = c;
    void c.offsetWidth;
    c.classList.add('is-in');
    hideTimer = setTimeout(hide, 15000);
  }

  function desktop(x) {
    if (!document.hidden || !('Notification' in window) || Notification.permission !== 'granted') { return; }
    try {
      var n = new Notification(x.title, { body: x.text || '', tag: 'wedo-corner' });
      n.onclick = function () { window.focus(); n.close(); location.href = x.url; };
    } catch (e) { /* some browsers only allow notifications from a service worker */ }
  }

  window.WDCornerAlert = {
    after: function () { return read(); },
    update: function (j) {
      if (typeof j.corner_top === 'number') { if (read() < 0) { write(j.corner_top); } return; }
      var x = j.corner;
      if (!x || !x.aid) { return; }
      if (x.aid <= read()) { return; }   // another tab already showed it
      write(x.aid);
      showCard(x);
      desktop(x);
    }
  };
})();
