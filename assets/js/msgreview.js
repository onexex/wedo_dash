/* ==========================================================================
   msgreview.js  —  Management > Message Review (msgreview.php).
   Filters + conversation list on the left, the full read-only transcript on
   the right. Everything users wrote is rendered as text (textContent), never
   as HTML. Data: query/msgreview-action.php; files: query/msgreview-file.php.
   ========================================================================== */
(function () {
  'use strict';

  var BOOT = window.MR_BOOT || {};
  var API = 'query/msgreview-action.php';
  var FILE = 'query/msgreview-file.php';
  var $ = function (id) { return document.getElementById(id); };

  var state = { emp: '', empName: '', q: '', from: '', to: '', type: '', page: 0, more: false,
                key: '', hl: '', oldest: 0, older: false, loading: false };

  /* ------------------------------------------------------------ helpers */
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }
  function icon(cls) { var i = el('i', cls); i.setAttribute('aria-hidden', 'true'); return i; }

  function get(params) {
    var qs = Object.keys(params).filter(function (k) { return params[k] !== '' && params[k] !== null && params[k] !== undefined; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return fetch(API + '?' + qs, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, error: 'Unexpected server response (' + r.status + ').' }; });
      });
  }

  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  function parse(s) {   // 'Y-m-d H:i:s' (Manila wall clock) -> parts, no timezone shifting
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/.exec(s || '');
    return m ? { y: +m[1], mo: +m[2], d: +m[3], h: +(m[4] || 0), mi: +(m[5] || 0) } : null;
  }
  function fmtDay(s) { var p = parse(s); return p ? MONTHS[p.mo - 1] + ' ' + p.d + ', ' + p.y : (s || ''); }
  function fmtTime(s) {
    var p = parse(s); if (!p) { return ''; }
    var h = p.h % 12 || 12;
    return h + ':' + (p.mi < 10 ? '0' : '') + p.mi + ' ' + (p.h < 12 ? 'AM' : 'PM');
  }
  function fmtWhen(s) { return fmtDay(s) + ' ' + fmtTime(s); }
  function fmtSize(b) {
    b = +b || 0;
    if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' MB'; }
    if (b >= 1024) { return Math.round(b / 1024) + ' KB'; }
    return b + ' B';
  }
  function card(text) { try { var c = JSON.parse(text); return c && typeof c === 'object' ? c : null; } catch (e) { return null; } }

  function avatar(p, small) {
    var a = el('span', 'mr-av' + (small ? ' mr-av--sm' : ''));
    if (p && p.photo) {
      var img = el('img'); img.src = p.photo; img.alt = ''; img.loading = 'lazy';
      a.appendChild(img);
    } else {
      a.textContent = (p && p.initials) || '?';
    }
    return a;
  }

  /** Text with every case-insensitive occurrence of state.hl wrapped in <mark>. */
  function highlighted(text) {
    var frag = document.createDocumentFragment();
    var needle = state.hl.toLowerCase();
    if (!needle) { frag.appendChild(document.createTextNode(text)); return frag; }
    var lower = text.toLowerCase(), at = 0, i;
    while ((i = lower.indexOf(needle, at)) !== -1) {
      if (i > at) { frag.appendChild(document.createTextNode(text.slice(at, i))); }
      frag.appendChild(el('mark', '', text.slice(i, i + needle.length)));
      at = i + needle.length;
    }
    if (at < text.length) { frag.appendChild(document.createTextNode(text.slice(at))); }
    return frag;
  }

  /* ------------------------------------------------------------ person filter */
  var personBox = $('mrPerson'), suggest = $('mrSuggest'), personTimer = null;

  function pickPerson(p) {
    state.emp = p ? p.id : '';
    state.empName = p ? p.name : '';
    $('mrPickedName').textContent = p ? p.name + ' (' + p.id + ')' : '';
    $('mrPicked').hidden = !p;
    personBox.hidden = !!p;
    personBox.value = '';
    suggest.hidden = true;
  }

  personBox.addEventListener('input', function () {
    clearTimeout(personTimer);
    var q = personBox.value.trim();
    if (q.length < 2) { suggest.hidden = true; return; }
    personTimer = setTimeout(function () {
      get({ action: 'people', q: q }).then(function (res) {
        suggest.textContent = '';
        var people = (res && res.people) || [];
        if (!people.length) { suggest.appendChild(el('div', 'mr-suggest__none', 'No one matches.')); }
        people.forEach(function (p) {
          var b = el('button', 'mr-suggest__item');
          b.type = 'button';
          b.appendChild(el('b', '', p.name));
          b.appendChild(el('span', '', p.id + (p.position ? ' · ' + p.position : '') + (p.active ? '' : ' · not active')));
          b.addEventListener('click', function () { pickPerson(p); runSearch(); });
          suggest.appendChild(b);
        });
        suggest.hidden = false;
      });
    }, 250);
  });
  personBox.addEventListener('keydown', function (e) { if (e.key === 'Escape') { suggest.hidden = true; } });
  document.addEventListener('click', function (e) { if (!e.target.closest || !e.target.closest('.mr-person')) { suggest.hidden = true; } });
  $('mrPickedClear').addEventListener('click', function () { pickPerson(null); runSearch(); });

  /* ------------------------------------------------------------ type + search */
  Array.prototype.forEach.call(document.querySelectorAll('.mr-seg button'), function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('.mr-seg button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
      state.type = b.getAttribute('data-type');
      runSearch();
    });
  });
  $('mrFilters').addEventListener('submit', function (e) { e.preventDefault(); runSearch(); });
  $('mrReset').addEventListener('click', function () {
    pickPerson(null);
    $('mrQ').value = ''; $('mrFrom').value = ''; $('mrTo').value = '';
    document.querySelectorAll('.mr-seg button').forEach(function (x) { x.classList.toggle('is-on', x.getAttribute('data-type') === ''); });
    state.type = '';
    runSearch();
  });

  function runSearch() {
    state.q = $('mrQ').value.trim();
    state.from = $('mrFrom').value;
    state.to = $('mrTo').value;
    state.page = 0;
    $('mrReset').hidden = !(state.emp || state.q || state.from || state.to || state.type);
    loadList(false);
  }

  /* ------------------------------------------------------------ conversation list */
  var list = $('mrList');

  function loadList(append) {
    if (!append) { list.textContent = ''; $('mrCount').textContent = 'Searching…'; }
    get({ action: 'list', emp: state.emp, q: state.q, from: state.from, to: state.to, type: state.type, page: state.page })
      .then(function (res) {
        var old = list.querySelector('.mr-more'); if (old) { old.remove(); }
        if (!res || !res.ok) { $('mrCount').textContent = (res && res.error) || 'Couldn’t load conversations.'; return; }
        res.items.forEach(function (c) { list.appendChild(row(c)); });
        state.more = res.more;
        var n = list.querySelectorAll('.mr-conv').length;
        $('mrCount').textContent = n === 0 ? 'No conversations match.'
          : n + (res.more ? '+' : '') + ' conversation' + (n === 1 ? '' : 's');
        if (res.more) {
          var more = el('button', 'wd-btn wd-btn--ghost wd-btn--sm mr-more', 'Load more');
          more.type = 'button';
          more.addEventListener('click', function () { state.page++; more.disabled = true; loadList(true); });
          list.appendChild(more);
        }
        markActive();
      });
  }

  function row(c) {
    var b = el('button', 'mr-conv');
    b.type = 'button';
    b.setAttribute('data-key', c.key);
    if (c.type === 'group') {
      var g = el('span', 'mr-av mr-av--grp'); g.appendChild(icon('fa-solid fa-users')); b.appendChild(g);
    } else {
      var pair = el('span', 'mr-pair');
      pair.appendChild(avatar(c.people[0], true));
      pair.appendChild(avatar(c.people[1], true));
      b.appendChild(pair);
    }
    var body = el('span', 'mr-conv__body');
    body.appendChild(el('span', 'mr-conv__title', c.title));
    var meta = el('span', 'mr-conv__meta',
      (c.type === 'group' ? 'Group · ' + c.people.length + ' people · ' : '') +
      c.count + ' message' + (c.count === 1 ? '' : 's') + ' · last ' + fmtDay(c.last));
    body.appendChild(meta);
    var tags = el('span', 'mr-conv__tags');
    if (c.hits) { tags.appendChild(el('span', 'mr-tag mr-tag--hit', c.hits + ' match' + (c.hits === 1 ? '' : 'es'))); }
    if (c.deleted) { tags.appendChild(el('span', 'mr-tag mr-tag--del', c.deleted + ' deleted')); }
    if (tags.childNodes.length) { body.appendChild(tags); }
    b.appendChild(body);
    b.addEventListener('click', function () { openThread(c.key); });
    return b;
  }

  function markActive() {
    list.querySelectorAll('.mr-conv').forEach(function (x) { x.classList.toggle('is-on', x.getAttribute('data-key') === state.key); });
  }

  /* ------------------------------------------------------------ transcript */
  var msgsBox = $('mrMsgs');

  function show(which) {   // 'empty' | 'thread'
    $('mrEmpty').hidden = which !== 'empty';
    $('mrThread').hidden = which !== 'thread';
    $('mrApp').classList.toggle('is-reading', which !== 'empty');
  }

  function openThread(key) {
    state.key = key;
    state.hl = state.q;
    markActive();
    show('thread');
    $('mrTitle').textContent = 'Loading…';
    $('mrType').textContent = '';
    $('mrPeople').textContent = '';
    msgsBox.textContent = '';
    get({ action: 'thread', key: key }).then(function (res) {
      if (state.key !== key) { return; }
      if (!res || !res.ok) { $('mrTitle').textContent = (res && res.error) || 'Couldn’t load this conversation.'; return; }
      var c = res.conversation;
      $('mrType').textContent = c.type === 'group' ? 'Group chat' : '1-to-1 conversation';
      $('mrTitle').textContent = c.title;
      c.people.forEach(function (p) {
        var chip = el('span', 'mr-chip' + (p.left ? ' is-left' : ''));
        chip.appendChild(avatar(p, true));
        chip.appendChild(el('span', '', p.name));
        if (p.role === 'admin') { chip.appendChild(el('em', '', 'admin')); }
        if (p.left) { chip.appendChild(el('em', '', 'left')); }
        chip.title = p.id + (p.position ? ' · ' + p.position : '');
        $('mrPeople').appendChild(chip);
      });
      renderMessages(res.messages, res.older, false);
      var first = msgsBox.querySelector('mark');
      if (first) { first.scrollIntoView({ block: 'center' }); } else { msgsBox.scrollTop = msgsBox.scrollHeight; }
    });
  }

  function renderMessages(msgs, older, prepend) {
    var frag = document.createDocumentFragment();
    if (older) {
      var b = el('button', 'wd-btn wd-btn--ghost wd-btn--sm mr-older mr-noprint', 'Load older messages');
      b.type = 'button';
      b.addEventListener('click', loadOlder);
      frag.appendChild(b);
    }
    if (!msgs.length && !prepend) { frag.appendChild(el('p', 'mr-none', 'No messages in this conversation.')); }
    var lastDay = null;
    msgs.forEach(function (m) {
      var day = (m.at || '').slice(0, 10);
      if (day !== lastDay) { frag.appendChild(el('div', 'mr-day', fmtDay(m.at))); lastDay = day; }
      frag.appendChild(message(m));
    });
    if (msgs.length) { state.oldest = msgs[0].id; }
    state.older = older;

    if (prepend) {
      var oldBtn = msgsBox.querySelector('.mr-older'); if (oldBtn) { oldBtn.remove(); }
      var h = msgsBox.scrollHeight;
      var joint = msgsBox.querySelector('.mr-day');   // the old first day line: drop it when the older page ends on that day
      if (joint && lastDay !== null && joint.textContent === fmtDay(lastDay)) { joint.remove(); }
      msgsBox.insertBefore(frag, msgsBox.firstChild);
      msgsBox.scrollTop += msgsBox.scrollHeight - h;
    } else {
      msgsBox.appendChild(frag);
    }
  }

  function loadOlder() {
    if (state.loading || !state.older) { return; }
    state.loading = true;
    var key = state.key;
    get({ action: 'thread', key: key, before: state.oldest }).then(function (res) {
      state.loading = false;
      if (state.key !== key || !res || !res.ok) { return; }
      renderMessages(res.messages, res.older, true);
    });
  }

  /** The body of a message of kind k with text t (live message, or a deleted one's original). */
  function content(id, k, t, fileThere) {
    var box = el('div', 'mr-body');
    if (k === 'gif') {
      var g = card(t);
      if (g && g.f) {
        var gi = el('img', 'mr-gif'); gi.src = 'assets/gifs/' + encodeURIComponent(g.f); gi.alt = g.t || 'GIF'; gi.loading = 'lazy';
        box.appendChild(gi);
        box.appendChild(el('span', 'mr-sub', 'GIF' + (g.t ? ': ' + g.t : '')));
      } else { box.appendChild(el('span', 'mr-sub', 'GIF')); }
      return box;
    }
    if (k === 'image' || k === 'file') {
      var c = card(t) || {};
      var name = c.n || (k === 'image' ? 'Photo' : 'Document');
      if (!fileThere) {
        box.appendChild(el('span', 'mr-sub', (k === 'image' ? '📷 ' : '📎 ') + name + ' — file no longer on the server'));
        return box;
      }
      var href = FILE + '?id=' + id;
      if (k === 'image') {
        var a = el('a', 'mr-photo'); a.href = href; a.target = '_blank'; a.rel = 'noopener';
        a.title = 'Open full size';
        var img = el('img'); img.src = href + '&t=1'; img.alt = name; img.loading = 'lazy';
        a.appendChild(img);
        box.appendChild(a);
        box.appendChild(el('span', 'mr-sub', name + ' · ' + fmtSize(c.s)));
      } else {
        var d = el('a', 'mr-doc'); d.href = href; d.target = '_blank'; d.rel = 'noopener';
        d.appendChild(icon('fa-solid fa-file-lines'));
        d.appendChild(el('span', '', name));
        d.appendChild(el('small', '', fmtSize(c.s)));
        box.appendChild(d);
      }
      return box;
    }
    var p = el('p', 'mr-text');
    p.appendChild(highlighted(t));
    box.appendChild(p);
    return box;
  }

  function message(m) {
    if (m.kind === 'event') {
      return el('div', 'mr-event', m.text + ' · ' + fmtTime(m.at));
    }
    var row = el('article', 'mr-msg' + (m.kind === 'deleted' ? ' is-deleted' : ''));
    row.id = 'm' + m.id;
    row.appendChild(avatar(m.sender));
    var main = el('div', 'mr-msg__main');
    var head = el('div', 'mr-msg__head');
    head.appendChild(el('b', '', m.sender.name));
    var time = el('time', '', fmtWhen(m.at)); time.setAttribute('datetime', m.at);
    head.appendChild(time);
    head.appendChild(el('span', 'mr-msg__id', '#' + m.id));
    if (m.read === false) { head.appendChild(el('span', 'mr-tag', 'unread')); }
    main.appendChild(head);

    if (m.kind === 'deleted') {
      var note = el('div', 'mr-delnote');
      note.appendChild(icon('fa-solid fa-trash-can'));
      if (m.original) {
        note.appendChild(el('span', '', 'Deleted by the sender on ' + fmtWhen(m.original.at) + ' — the others see “This message was deleted”. Original:'));
        main.appendChild(note);
        main.appendChild(content(m.id, m.original.kind, m.original.text, m.original.file));
      } else {
        note.appendChild(el('span', '', 'Deleted by the sender before deleted messages were kept — the original can’t be recovered.'));
        main.appendChild(note);
      }
    } else {
      main.appendChild(content(m.id, m.kind, m.text, m.file));
    }

    if (m.reactions && m.reactions.length) {
      var rx = el('div', 'mr-rx');
      m.reactions.forEach(function (r) { rx.appendChild(el('span', '', r.emoji + ' ' + r.name)); });
      main.appendChild(rx);
    }
    row.appendChild(main);
    return row;
  }

  $('mrBack').addEventListener('click', function () { state.key = ''; markActive(); show('empty'); });

  /* ------------------------------------------------------------ print */
  $('mrPrint').addEventListener('click', function () {
    if (!state.key) { return; }
    $('mrPrintNote').textContent = 'Printed ' + new Date().toLocaleString() + ' from WeDo Message Review. Confidential.' +
      (state.older ? ' Older messages were not loaded and are not included.' : '');
    window.print();
  });

  /* ------------------------------------------------------------ start */
  loadList(false);
  if (BOOT.open) { openThread(BOOT.open); }
})();
