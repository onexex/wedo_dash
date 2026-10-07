/* ==========================================================================
   Messages page (messages.php) — talks to query/Query-messages.php.
   Message text is always rendered with textContent / text nodes, never as HTML.

   A conversation has a key: an EmpID (1-to-1) or 'grp:<id>' (group).
   Live behaviour, all on plain polling (2.5 s with a chat open, 4 s otherwise):
     - list: All / Unread tabs, instant filtering, "Start a new chat" people results,
       groups with their last sender, "Bea is typing…"
     - thread: grouped bubbles (sender names in groups), day chips, event lines
       ("Ramon added Carlo", call notes), "New messages" line, jump-to-latest
     - optimistic sending with retry; "Sent" / "Seen" (1-to-1) / "Seen by Bea, Ben" (groups)
     - online dot, "Active 5m ago", typing both ways
     - groups: create, info (members, add / remove / rename for admins, leave),
       video call the group, "Group call in progress · Join"
     - emoji picker, clickable links, unread count in the tab title
     - keys: Ctrl/Cmd+K search, ↑/↓ in the list, Enter send, Shift+Enter new line, Esc
   ========================================================================== */
(function () {
  'use strict';

  var app = document.getElementById('msgApp');
  if (!app) { return; }

  var API = 'query/Query-messages.php';
  var POLL_LIVE = 2500, POLL_IDLE = 4000;
  var GROUP_GAP_MIN = 5;          // same sender within 5 minutes = one bubble group
  var TYPING_EVERY_MS = 3000;     // how often to re-announce "typing" while typing
  var token = app.dataset.token;
  var maxLen = parseInt(app.dataset.max, 10) || 500;
  var baseTitle = document.title;

  function $(id) { return document.getElementById(id); }
  var el = {
    list: $('msgList'), search: $('msgSearch'), unreadN: $('msgUnreadN'), newGroup: $('msgNewGroup'),
    online: $('msgOnline'), onlineRow: $('msgOnlineRow'), onlineN: $('msgOnlineN'),
    tabs: Array.prototype.slice.call(app.querySelectorAll('.msg-tab')),
    placeholder: $('msgPlaceholder'), head: $('msgHead'), headAv: $('msgHeadAv'),
    headName: $('msgHeadName'), headRole: $('msgHeadRole'), back: $('msgBack'),
    call: $('msgCallBtn'), info: $('msgInfoBtn'), clear: $('msgClearBtn'),
    callBar: $('msgCallBar'), callBarText: $('msgCallBarText'), callJoin: $('msgCallJoin'),
    scroll: $('msgScroll'), jump: $('msgJump'), form: $('msgCompose'), text: $('msgText'),
    send: $('msgSend'), count: $('msgCount'), emoji: $('msgEmoji'), emojiBtn: $('msgEmojiBtn'),
    gif: $('msgGif'), gifBtn: $('msgGifBtn'), fileBtn: $('msgFileBtn'), fileInput: $('msgFileInput'), main: $('msgMain'),
    error: $('msgError'), readonly: $('msgReadonly'), modal: $('msgModal'), modalCard: $('msgModalCard')
  };

  var state = {
    threads: [], threadsKey: '', onlineKey: '', groupsOn: false, filter: 'all', term: '', people: null,
    active: null,                 // conversation key
    kind: null,                   // 'dm' | 'group'
    person: null, presence: null, // 1-to-1
    group: null, readers: [], typingNames: [], activeCall: null,   // group
    canSend: false, lastId: 0, seen: {}, today: '', seenUpTo: 0,
    lastDay: '', group_: null,    // newest bubble run: {sender, mine, at, day, row, meta}
    pending: [], typingEl: null, statusEl: null, newBelow: 0,
    polling: false, typingSentAt: 0, typingOn: false,
    gifsOn: false,                // the GIF sticker library (assets/gifs) is there
    filesOn: false,               // I hold the `msgfile` right: may send pictures + documents
    delOn: false,                 // I hold the `msgdel` right: may delete my own messages
    rxOn: false, rxBusy: {},      // reactions set up on the server; message ids with a react request in flight
    mentionKey: '', mentionRe: null
  };

  // ------------------------------------------------------------------ helpers

  function h(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }

  function avatar(p, cls, online) {
    var a = h('div', 'msg-av' + (cls ? ' ' + cls : '') + (online ? ' is-online' : ''));
    fillAvatar(a, p);
    return a;
  }
  function fillAvatar(a, p) {
    a.textContent = '';
    a.classList.toggle('msg-av--group', !!(p && p.type === 'group'));
    if (p && p.type === 'group') {
      a.appendChild(h('i', 'fa-solid fa-user-group'));
    } else if (p && p.photo) {
      var img = document.createElement('img');
      img.src = p.photo; img.alt = '';
      img.onerror = function () { a.textContent = (p && p.initials) || '?'; };
      a.appendChild(img);
    } else {
      a.textContent = (p && p.initials) || '?';
    }
  }

  function api(params, body) {
    var url = API + '?' + new URLSearchParams(params).toString();
    var opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (body) {
      var b = new URLSearchParams();
      Object.keys(body).forEach(function (k) {
        if (Array.isArray(body[k])) { body[k].forEach(function (v) { b.append(k + '[]', v); }); }
        else { b.append(k, body[k]); }
      });
      b.append('token', token);
      opts.method = 'POST';
      opts.body = b;
    }
    return fetch(url, opts).then(readJson);
  }
  function readJson(r) {
    return r.json().catch(function () {
      return { status: 'error', msg: r.status === 413 ? 'That file is too large (10 MB at most).' : 'Unexpected server response (HTTP ' + r.status + ').' };
    }).then(function (j) {
      if (r.status === 401) { window.location.href = 'login'; }
      if (!r.ok || j.status !== 'ok') { var e = new Error(j.msg || 'Something went wrong.'); e.http = r.status; throw e; }
      return j;
    });
  }
  function post(action, data) { return api({ action: action }, Object.assign({ action: action }, data || {})); }

  // dates come from the server as "YYYY-MM-DD HH:MM:SS" (Manila time); format without timezone shifts
  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  var MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  function parts(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(s || '');
    return m ? { y: +m[1], mo: +m[2], d: +m[3], hh: +(m[4] || 0), mm: +(m[5] || 0), day: m[1] + '-' + m[2] + '-' + m[3] } : null;
  }
  function stamp(p) { return Date.UTC(p.y, p.mo - 1, p.d, p.hh, p.mm); }
  function clock(p) {
    var hr = p.hh % 12 || 12;
    return hr + ':' + (p.mm < 10 ? '0' : '') + p.mm + (p.hh < 12 ? ' AM' : ' PM');
  }
  function dayDiff(a, b) {
    var pa = parts(a), pb = parts(b);
    return Math.round((Date.UTC(pb.y, pb.mo - 1, pb.d) - Date.UTC(pa.y, pa.mo - 1, pa.d)) / 864e5);
  }
  function listTime(s) {
    var p = parts(s), t = parts(state.today);
    if (!p) { return ''; }
    if (t && p.day === t.day) { return clock(p); }
    if (t && dayDiff(p.day, t.day) === 1) { return 'Yesterday'; }
    if (t && dayDiff(p.day, t.day) < 7) { return DAYS[new Date(Date.UTC(p.y, p.mo - 1, p.d)).getUTCDay()].slice(0, 3); }
    return MONTHS[p.mo - 1] + ' ' + p.d + (t && p.y === t.y ? '' : ', ' + p.y);
  }
  function dayLabel(day) {
    var p = parts(day), t = parts(state.today);
    if (t && day === t.day) { return 'Today'; }
    if (t && dayDiff(day, t.day) === 1) { return 'Yesterday'; }
    var dow = DAYS[new Date(Date.UTC(p.y, p.mo - 1, p.d)).getUTCDay()];
    return dow + ', ' + MONTHS_LONG[p.mo - 1] + ' ' + p.d + (t && p.y === t.y ? '' : ', ' + p.y);
  }
  function fullTime(s) {
    var p = parts(s);
    return p ? dayLabel(p.day) + ' at ' + clock(p) : '';
  }

  /** "Active now" / "Active 5m ago" / "Active 3h ago" / "Active 2d ago" / '' */
  function activeLabel(pr) {
    if (!pr) { return ''; }
    if (pr.online) { return 'Active now'; }
    if (pr.ago === null || pr.ago === undefined) { return ''; }
    var m = Math.floor(pr.ago / 60);
    if (m < 60) { return 'Active ' + Math.max(1, m) + 'm ago'; }
    if (m < 1440) { return 'Active ' + Math.floor(m / 60) + 'h ago'; }
    if (m < 10080) { return 'Active ' + Math.floor(m / 1440) + 'd ago'; }
    return '';
  }

  /** "Bea", "Bea and Ben", "Bea, Ben and Cora", "Bea, Ben and 3 others" */
  function namesText(names) {
    var n = names.length;
    if (n <= 1) { return names[0] || ''; }
    if (n === 2) { return names[0] + ' and ' + names[1]; }
    if (n === 3) { return names[0] + ', ' + names[1] + ' and ' + names[2]; }
    return names[0] + ', ' + names[1] + ' and ' + (n - 2) + ' others';
  }

  /** Text with http(s) links turned into safe anchors. */
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
  function isEmojiOnly(t) {
    return !!EMOJI_ONLY && t.length <= 16 && EMOJI_ONLY.test(t) && /[^\s\d#*]/.test(t);
  }

  /** top-bar envelope badge (assets/js/wedo-call.js owns it) + this page's tab title */
  function setTopbarBadge(n) {
    if (window.WDInbox) { window.WDInbox.set(n); }
    document.title = n > 0 ? '(' + n + ') ' + baseTitle : baseTitle;
  }

  function empty(icon, title, text) {
    var box = h('div', 'msg-empty');
    box.appendChild(h('i', icon));
    if (title) { box.appendChild(h('b', null, title)); }
    if (text) { box.appendChild(h('span', null, text)); }
    return box;
  }

  function skeletonList(n) {
    var f = document.createDocumentFragment();
    for (var i = 0; i < n; i++) {
      var r = h('div', 'msg-skel');
      r.appendChild(h('div', 'a msg-shimmer'));
      var l = h('div', 'l'); l.appendChild(h('span', 'msg-shimmer')); l.appendChild(h('span', 'msg-shimmer'));
      r.appendChild(l);
      f.appendChild(r);
    }
    return f;
  }
  function skeletonThread() {
    var f = document.createDocumentFragment();
    ['', 'r', '', 'r', 'r', ''].forEach(function (side) { f.appendChild(h('div', 'msg-skelb msg-shimmer ' + side)); });
    return f;
  }

  /** name with the search term highlighted */
  function highlighted(name, term, cls) {
    var span = h('span', cls || 'msg-item__name');
    if (!term) { span.textContent = name; return span; }
    var lower = name.toLowerCase(), t = term.toLowerCase(), i = 0, j;
    while ((j = lower.indexOf(t, i)) !== -1) {
      if (j > i) { span.appendChild(document.createTextNode(name.slice(i, j))); }
      span.appendChild(h('mark', null, name.slice(j, j + t.length)));
      i = j + t.length;
    }
    if (i < name.length) { span.appendChild(document.createTextNode(name.slice(i))); }
    return span;
  }

  function threadByKey(key) { return state.threads.filter(function (t) { return t.key === key; })[0] || null; }

  // ------------------------------------------------------------------ list

  function threadItem(t, term) {
    var item = h('button', 'msg-item' + (t.key === state.active ? ' is-active' : '') + (t.unread ? ' is-unread' : ''));
    item.type = 'button';
    item.dataset.key = t.key;
    item.appendChild(avatar(t, '', t.online));
    var main = h('div', 'msg-item__main');
    var top = h('div', 'msg-item__top');
    top.appendChild(highlighted(t.name, term));
    top.appendChild(h('span', 'msg-item__time', listTime(t.at)));
    var bottom = h('div', 'msg-item__bottom');
    if (t.typing) {
      bottom.appendChild(h('span', 'msg-item__preview is-typing', t.type === 'group' ? t.typing + ' is typing…' : 'typing…'));
    } else {
      var who = t.lastMine ? 'You: ' : (t.type === 'group' && t.lastSender ? t.lastSender + ': ' : '');
      bottom.appendChild(h('span', 'msg-item__preview', who + (t.last || 'No messages yet')));
    }
    if (t.mentions) {
      var at = h('span', 'msg-item__at', '@');
      at.title = 'You were mentioned';
      bottom.appendChild(at);
    }
    if (t.unread) { bottom.appendChild(h('span', 'msg-item__badge', t.unread > 99 ? '99+' : String(t.unread))); }
    main.appendChild(top);
    main.appendChild(bottom);
    item.appendChild(main);
    return item;
  }

  function personItem(p, term) {
    var item = h('button', 'msg-item');
    item.type = 'button';
    item.dataset.key = p.id;
    item.appendChild(avatar(p));
    var main = h('div', 'msg-item__main');
    main.appendChild(highlighted(p.name, term));
    main.appendChild(h('div', 'msg-item__preview', p.position || p.id));
    item.appendChild(main);
    return item;
  }

  function renderList() {
    var focusedKey = el.list.contains(document.activeElement) && document.activeElement.dataset ? document.activeElement.dataset.key : null;
    var term = state.term;
    var frag = document.createDocumentFragment();

    var unreadCount = state.threads.filter(function (t) { return t.unread; }).length;
    el.unreadN.textContent = unreadCount ? String(unreadCount) : '';

    if (term) {
      var tl = term.toLowerCase();
      var chats = state.threads.filter(function (t) { return t.name.toLowerCase().indexOf(tl) !== -1 || String(t.key).toLowerCase().indexOf(tl) !== -1; });
      if (chats.length) {
        frag.appendChild(h('div', 'msg-label', 'Chats'));
        chats.forEach(function (t) { frag.appendChild(threadItem(t, term)); });
      }
      if (term.length >= 2) {
        var have = {};
        state.threads.forEach(function (t) { have[t.key] = true; });
        if (state.people === null) {
          frag.appendChild(h('div', 'msg-label', 'Start a new chat'));
          frag.appendChild(skeletonList(2));
        } else {
          var people = state.people.filter(function (p) { return !have[p.id]; });
          if (people.length) {
            frag.appendChild(h('div', 'msg-label', 'Start a new chat'));
            people.forEach(function (p) { frag.appendChild(personItem(p, term)); });
          } else if (state.searchError) {
            frag.appendChild(empty('fa-solid fa-triangle-exclamation', 'Search failed', state.searchError));
          } else if (!chats.length) {
            frag.appendChild(empty('fa-solid fa-user-slash', 'No one found', 'Try a first name, last name or employee ID.'));
          }
        }
      } else if (!chats.length) {
        frag.appendChild(empty('fa-solid fa-magnifying-glass', null, 'Keep typing to search people.'));
      }
    } else {
      var list = state.filter === 'unread' ? state.threads.filter(function (t) { return t.unread; }) : state.threads;
      if (!list.length) {
        frag.appendChild(state.filter === 'unread'
          ? empty('fa-regular fa-circle-check', 'You’re all caught up', 'No unread conversations.')
          : empty('fa-regular fa-comments', 'No conversations yet', 'Search for a colleague above, or start a group.'));
      }
      list.forEach(function (t) { frag.appendChild(threadItem(t)); });
    }

    el.list.textContent = '';
    el.list.appendChild(frag);
    if (focusedKey) {
      var again = el.list.querySelector('.msg-item[data-key="' + CSS.escape(focusedKey) + '"]');
      if (again) { again.focus(); }
    }
  }

  /** "Online now" strip: everyone I can message who's on WeDo right now; click a face to chat */
  function renderOnline(people) {
    if (people === null || people === undefined) { el.online.hidden = true; return; }   // presence not set up
    var key = JSON.stringify(people);
    if (key === state.onlineKey) { return; }
    state.onlineKey = key;
    el.online.hidden = false;
    el.onlineN.textContent = String(people.length);
    el.onlineRow.textContent = '';
    if (!people.length) {
      el.onlineRow.appendChild(h('div', 'msg-online__none', 'No one else is online right now.'));
      return;
    }
    people.forEach(function (p) {
      var b = h('button', 'msg-face');
      b.type = 'button';
      b.setAttribute('role', 'listitem');
      b.title = p.name + (p.position ? ' — ' + p.position : '') + ' · online now';
      b.setAttribute('aria-label', 'Chat with ' + p.name + ', online now');
      b.appendChild(avatar(p, '', true));
      b.appendChild(h('span', 'msg-face__name', p.first));
      b.addEventListener('click', function () { openThread(p.id); });
      el.onlineRow.appendChild(b);
    });
  }

  function loadThreads() {
    return api({ action: 'threads' }).then(function (j) {
      state.today = j.today;
      renderOnline(j.online);
      state.groupsOn = !!j.groups;
      el.newGroup.hidden = !state.groupsOn;
      state.gifsOn = !!j.gifs;
      el.gifBtn.hidden = !state.gifsOn;
      state.filesOn = !!j.files;
      el.fileBtn.hidden = !state.filesOn;
      state.delOn = !!j.canDelete;
      app.classList.toggle('msg-del-on', state.delOn);
      var key = JSON.stringify(j.threads);
      if (key !== state.threadsKey) {           // nothing changed = no re-render (no flicker, focus kept)
        state.threadsKey = key;
        state.threads = j.threads;
        renderList();
      }
      setTopbarBadge(j.unread);
    });
  }

  // ------------------------------------------------------------------ search + tabs

  var searchTimer = null, searchSeq = 0;
  el.search.addEventListener('input', function () {
    clearTimeout(searchTimer);
    state.term = el.search.value.trim();
    state.people = null;
    renderList();
    if (state.term.length < 2) { return; }
    var term = state.term;
    searchTimer = setTimeout(function () {
      var seq = ++searchSeq;
      api({ action: 'search', term: term }).then(function (j) {
        if (seq !== searchSeq || state.term !== term) { return; }
        state.people = j.people;
        state.searchError = '';
        renderList();
      }).catch(function (e) {
        if (seq !== searchSeq) { return; }
        state.people = [];
        state.searchError = e.message;   // shown instead of a fake "No one found"
        renderList();
      });
    }, 220);
  });
  el.search.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && el.search.value) {
      e.preventDefault(); e.stopPropagation();
      el.search.value = ''; state.term = ''; state.people = null; renderList();
    } else if (e.key === 'ArrowDown') {
      var first = el.list.querySelector('.msg-item');
      if (first) { e.preventDefault(); first.focus(); }
    } else if (e.key === 'Enter') {
      var only = el.list.querySelector('.msg-item');
      if (only) { e.preventDefault(); only.click(); }
    }
  });

  el.tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      state.filter = tab.dataset.filter;
      el.tabs.forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('is-on', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      renderList();
    });
  });

  el.list.addEventListener('click', function (e) {
    var item = e.target.closest('.msg-item');
    if (!item) { return; }
    if (state.term) { el.search.value = ''; state.term = ''; state.people = null; }
    openThread(item.dataset.key);
  });
  el.list.addEventListener('keydown', function (e) {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
    var items = Array.prototype.slice.call(el.list.querySelectorAll('.msg-item'));
    var i = items.indexOf(document.activeElement);
    if (i === -1) { return; }
    e.preventDefault();
    if (e.key === 'ArrowUp' && i === 0) { el.search.focus(); return; }
    var next = items[Math.max(0, Math.min(items.length - 1, i + (e.key === 'ArrowDown' ? 1 : -1)))];
    if (next) { next.focus(); }
  });

  // ------------------------------------------------------------------ thread rendering

  function nearBottom() { return el.scroll.scrollHeight - el.scroll.scrollTop - el.scroll.clientHeight < 90; }
  function toBottom(instant) {
    if (instant) { el.scroll.classList.add('is-instant'); }
    el.scroll.scrollTop = el.scroll.scrollHeight;
    if (instant) { el.scroll.classList.remove('is-instant'); }
  }

  function minutesBetween(a, b) {
    var pa = parts(a), pb = parts(b);
    return pa && pb ? Math.abs(stamp(pb) - stamp(pa)) / 60000 : 999;
  }

  /** put a node at the end of the conversation, keeping the typing bubble last */
  function place(node) {
    if (state.typingEl && state.typingEl.parentNode === el.scroll) { el.scroll.insertBefore(node, state.typingEl); }
    else { el.scroll.appendChild(node); }
  }

  // ------------------------------------------------------------------ GIF stickers (assets/gifs)

  var GIF_FILE = /^[a-z0-9][a-z0-9_-]{0,60}\.gif$/;
  /** a GIF message's card {f, w, h, t}, or null if it doesn't name a library file */
  function gifData(text) {
    try { var g = JSON.parse(text); } catch (e) { return null; }
    return g && typeof g.f === 'string' && GIF_FILE.test(g.f) ? g : null;
  }
  function gifImg(g) {
    var img = document.createElement('img');
    img.src = 'assets/gifs/' + g.f; img.alt = g.t || 'GIF'; img.loading = 'lazy'; img.decoding = 'async';
    if (g.w && g.h) { img.width = g.w; img.height = g.h; }   // reserves the space: no jump when it loads
    return img;
  }

  // ------------------------------------------------------------------ pictures + documents (includes/msg-files.php)

  var FILE_KEY = /^\d{4}\/\d{2}\/[a-f0-9]{32}\.([a-z]{3,4})$/;
  var FILE_MAX = 10 * 1024 * 1024;
  var FILE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
  /** a picture/document message's card {k, n, s, w, h, ext}, or null */
  function fileData(text) {
    try { var c = JSON.parse(text); } catch (e) { return null; }
    var m = c && typeof c.k === 'string' ? FILE_KEY.exec(c.k) : null;
    if (!m) { return null; }
    c.ext = m[1];
    return c;
  }
  function fileUrl(id, download) { return 'query/msg-file.php?id=' + id + (download ? '&dl=1' : ''); }
  function fileSize(b) {
    return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : b >= 1024 ? Math.round(b / 1024) + ' KB' : (b || 0) + ' B';
  }
  function fileIcon(ext) {
    return { pdf: 'fa-file-pdf', doc: 'fa-file-word', docx: 'fa-file-word', xls: 'fa-file-excel', xlsx: 'fa-file-excel',
             csv: 'fa-file-csv', ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint' }[ext] || 'fa-file-lines';
  }
  /** the inside of a picture / document bubble; m.local = a blob URL while my own upload is on its way */
  function fileBody(m, card) {
    var a = h('a');
    a.target = '_blank'; a.rel = 'noopener';
    if (m.kind === 'image') {
      a.className = 'msg-img';
      a.dataset.mf = 'view';
      a.setAttribute('aria-label', 'Open picture: ' + (card.n || 'photo'));
      var img = document.createElement('img');
      img.alt = card.n || 'Photo'; img.loading = 'lazy'; img.decoding = 'async';
      img.src = m.local || fileUrl(m.id);
      if (card.w && card.h) { img.width = card.w; img.height = card.h; }   // reserves the space: no jump when it loads
      a.appendChild(img);
    } else {
      a.className = 'msg-file';
      a.dataset.mf = 'dl';
      a.title = 'Download ' + (card.n || 'file');
      a.appendChild(h('i', 'fa-solid ' + fileIcon(card.ext) + ' msg-file__ico'));
      var t = h('span', 'msg-file__txt');
      t.appendChild(h('span', 'msg-file__name', card.n || 'Document'));
      t.appendChild(h('span', 'msg-file__size', (card.ext || '').toUpperCase() + ' · ' + fileSize(card.s)));
      a.appendChild(t);
      a.appendChild(h('i', 'fa-solid fa-download msg-file__dl'));
    }
    if (m.id) { a.href = fileUrl(m.id, a.dataset.mf === 'dl'); }
    return a;
  }
  /** once my upload has its message id, point the links at the server copy */
  function linkFile(row, id) {
    Array.prototype.forEach.call(row.querySelectorAll('[data-mf]'), function (a) { a.href = fileUrl(id, a.dataset.mf === 'dl'); });
  }

  // ------------------------------------------------------------------ mentions (groups)

  function escapeRe(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  /** /@(Full Name|…|everyone)/ for the open group; null in 1-to-1 chats */
  function mentionRe() {
    if (state.kind !== 'group' || !state.group || !state.group.members) { return null; }
    var names = state.group.members.map(function (m) { return m.name; }).filter(Boolean);
    var key = names.join('|');
    if (state.mentionKey !== key) {
      state.mentionKey = key;
      var alts = names.slice().sort(function (a, b) { return b.length - a.length; }).map(escapeRe);
      alts.push('everyone');
      try { state.mentionRe = new RegExp('@(?:' + alts.join('|') + ')(?![\\p{L}\\p{N}])', 'giu'); }
      catch (e) { state.mentionRe = null; }
    }
    return state.mentionRe;
  }

  /** wrap each "@Name" in a bubble's text (outside links) in a highlight */
  function markMentions(node) {
    var re = mentionRe();
    if (!re) { return; }
    var walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT), texts = [];
    while (walker.nextNode()) {
      if (!(walker.currentNode.parentNode.closest && walker.currentNode.parentNode.closest('a'))) { texts.push(walker.currentNode); }
    }
    texts.forEach(function (t) {
      var s = t.nodeValue, last = 0, m, frag = null;
      re.lastIndex = 0;
      while ((m = re.exec(s)) !== null) {
        frag = frag || document.createDocumentFragment();
        if (m.index > last) { frag.appendChild(document.createTextNode(s.slice(last, m.index))); }
        frag.appendChild(h('span', 'msg-mention', m[0]));
        last = m.index + m[0].length;
      }
      if (!frag) { return; }
      if (last < s.length) { frag.appendChild(document.createTextNode(s.slice(last))); }
      t.parentNode.replaceChild(frag, t);
    });
  }

  // ------------------------------------------------------------------ reactions

  var RX = ['👍', '❤️', '😂', '😮', '😢', '🙏', '🖕'];

  /** the reaction pill under a bubble: up to 3 emoji + total; hover lists who */
  function renderReactions(row, list) {
    var key = list && list.length ? JSON.stringify(list) : '';
    if ((row.dataset.rx || '') === key) { return; }
    row.dataset.rx = key;
    var bubble = row.querySelector('.msg-bubble');
    if (!bubble) { return; }
    var old = bubble.querySelector('.msg-rx');
    if (old) { old.remove(); }
    row.classList.toggle('has-rx', !!key);
    if (!key) { return; }
    var pill = h('button', 'msg-rx' + (list.some(function (g) { return g.mine; }) ? ' is-mine' : ''));
    pill.type = 'button';
    var total = 0, who = [];
    list.forEach(function (g, i) {
      if (i < 3) { pill.appendChild(h('span', 'msg-rx__e', g.emoji)); }
      total += g.count;
      who.push(g.emoji + ' ' + g.names.join(', '));
    });
    if (total > 1) { pill.appendChild(h('span', 'msg-rx__n', String(total))); }
    pill.title = who.join('\n');
    pill.setAttribute('aria-label', 'Reactions: ' + who.join('; ') + '. Change mine');
    bubble.appendChild(pill);
  }

  /** reactions from a thread response ({msid: [...]}, or null when not set up on the server) */
  function applyReactions(map) {
    state.rxOn = map !== null && map !== undefined;
    app.classList.toggle('msg-rx-on', state.rxOn);
    if (!state.rxOn) { return; }
    Array.prototype.forEach.call(el.scroll.querySelectorAll('.msg-row[data-id]'), function (row) {
      if (state.rxBusy[row.dataset.id]) { return; }   // my own change is on its way; don't flicker back
      renderReactions(row, map[row.dataset.id] || []);
    });
  }

  /** my change applied locally, so the pill updates before the server answers */
  function rxLocal(list, emoji) {
    var out = JSON.parse(JSON.stringify(list || [])), had = null;
    out.forEach(function (g) {
      if (g.mine) { had = g.emoji; g.count--; g.mine = false; g.names = g.names.filter(function (n) { return n !== 'You'; }); }
    });
    out = out.filter(function (g) { return g.count > 0; });
    if (had !== emoji) {
      var g = out.filter(function (x) { return x.emoji === emoji; })[0];
      if (!g) { g = { emoji: emoji, count: 0, mine: false, names: [] }; out.push(g); }
      g.count++; g.mine = true; g.names.unshift('You');
    }
    return out;
  }

  function react(row, emoji) {
    var id = row && row.dataset.id;
    if (!id) { return; }
    var before = row.dataset.rx ? JSON.parse(row.dataset.rx) : [];
    state.rxBusy[id] = (state.rxBusy[id] || 0) + 1;
    renderReactions(row, rxLocal(before, emoji));
    post('react', { id: id, emoji: emoji }).then(function (j) {
      if (row.isConnected) { renderReactions(row, j.reactions); }
    }).catch(function (e) {
      if (row.isConnected) { renderReactions(row, before); }
      showError(e.message);
    }).then(function () {
      if (--state.rxBusy[id] <= 0) { delete state.rxBusy[id]; }
    });
  }

  var rxBar = h('div', 'msg-rxbar'), rxRow = null;
  rxBar.hidden = true;
  rxBar.setAttribute('role', 'menu');
  RX.forEach(function (em) {
    var b = h('button', null, em);
    b.type = 'button'; b.dataset.emoji = em; b.setAttribute('role', 'menuitem'); b.setAttribute('aria-label', 'React ' + em);
    rxBar.appendChild(b);
  });
  function openRxBar(row) {
    if (!row || !row.dataset.id || !state.rxOn) { return; }
    rxRow = row;
    var mine = (row.dataset.rx ? JSON.parse(row.dataset.rx) : []).filter(function (g) { return g.mine; }).map(function (g) { return g.emoji; })[0];
    Array.prototype.forEach.call(rxBar.children, function (b) { b.classList.toggle('is-on', b.dataset.emoji === mine); });
    row.appendChild(rxBar);
    rxBar.hidden = false;
    rxBar.classList.toggle('is-below', row.getBoundingClientRect().top - el.scroll.getBoundingClientRect().top < 56);
    row.classList.add('rx-open');
  }
  function closeRxBar() {
    if (rxRow) { rxRow.classList.remove('rx-open'); }
    rxRow = null;
    rxBar.hidden = true;
    if (rxBar.parentNode) { rxBar.parentNode.removeChild(rxBar); }
  }

  el.scroll.addEventListener('click', function (e) {
    var del = e.target.closest('.msg-del-btn');
    if (del) { e.stopPropagation(); closeRxBar(); deleteMessage(del.closest('.msg-row')); return; }
    var pick = e.target.closest('.msg-rxbar button');
    if (pick) { e.stopPropagation(); var r = rxRow; closeRxBar(); react(r, pick.dataset.emoji); return; }
    var opener = e.target.closest('.msg-react-btn, .msg-rx');
    if (opener) {
      e.stopPropagation();
      var row = opener.closest('.msg-row');
      if (rxRow === row) { closeRxBar(); } else { closeRxBar(); openRxBar(row); }
      return;
    }
    // touch screens have no hover: tapping a bubble shows its react button
    var bubble = e.target.closest('.msg-bubble');
    if (bubble && !e.target.closest('a') && window.matchMedia && window.matchMedia('(hover: none)').matches) {
      var tapped = bubble.closest('.msg-row');
      Array.prototype.forEach.call(el.scroll.querySelectorAll('.msg-row.show-rx'), function (x) { if (x !== tapped) { x.classList.remove('show-rx'); } });
      tapped.classList.toggle('show-rx');
    }
  });
  document.addEventListener('click', function (e) { if (rxRow && !rxBar.contains(e.target)) { closeRxBar(); } });

  // ------------------------------------------------------------------ delete my own message (msgdel access right)

  /** turn a bubble into the "deleted" note (text, picture, file, reactions and buttons go) */
  function fillDeleted(row, bubble) {
    row.classList.add('is-deleted');
    bubble.className = 'msg-bubble is-deleted';
    bubble.textContent = '';
    bubble.appendChild(h('i', 'fa-solid fa-ban'));
    bubble.appendChild(document.createTextNode(row.classList.contains('msg-row--mine') ? ' You deleted this message' : ' This message was deleted'));
    Array.prototype.forEach.call(row.querySelectorAll('.msg-react-btn, .msg-del-btn'), function (b) { b.remove(); });
    row.dataset.rx = '';
    row.classList.remove('has-rx', 'is-mention');
  }
  function markDeleted(row) {
    if (!row || row.classList.contains('is-deleted')) { return; }
    var bubble = row.querySelector('.msg-bubble');
    if (bubble) { fillDeleted(row, bubble); }
  }
  /** ids deleted in this conversation (from each thread response): update bubbles already on screen */
  function applyDeleted(ids) {
    (ids || []).forEach(function (id) { markDeleted(el.scroll.querySelector('.msg-row[data-id="' + id + '"]')); });
  }
  function deleteMessage(row) {
    var id = row && +row.dataset.id;
    if (!id || !state.delOn || row.classList.contains('is-deleted')) { return; }
    if (!confirm('Delete this message for everyone? This can’t be undone.')) { return; }
    row.classList.add('is-pending');
    post('delete', { id: id }).then(function () {
      row.classList.remove('is-pending');
      markDeleted(row);
      loadThreads();
    }).catch(function (e) {
      row.classList.remove('is-pending');
      showError(e.message);
    });
  }

  /** who sent a message, as a display card */
  function senderOf(m) {
    if (m.sender && m.sender.name) { return m.sender; }
    return m.mine ? { id: 'me' } : state.person;
  }

  /** one message; bubbles are grouped when same sender, same day, within 5 minutes */
  function addBubble(m, opts) {
    var p = parts(m.at) || parts(state.today + ' 00:00');
    if (p.day !== state.lastDay) {
      var chip = h('div', 'msg-day'); chip.appendChild(h('span', null, dayLabel(p.day)));
      place(chip);
      state.lastDay = p.day;
      state.group_ = null;
    }
    if (opts.dividerBefore) {
      place(h('div', 'msg-new', 'New messages'));
      state.group_ = null;
    }
    if (m.kind === 'event') {
      var ev = h('div', 'msg-event');
      var span = h('span', null, m.text);
      span.title = fullTime(m.at);
      ev.appendChild(span);
      if (m.id) { ev.dataset.id = m.id; }
      place(ev);
      state.group_ = null;
      return ev;
    }

    var who = senderOf(m), sid = m.mine ? 'me' : (who && who.id) || '?';
    var g = state.group_;
    var joins = g && g.sender === sid && g.day === p.day && minutesBetween(g.at, m.at) <= GROUP_GAP_MIN;
    if (joins) {
      g.row.classList.remove('is-last');
      if (g.meta && g.meta.parentNode) { g.meta.remove(); }
    } else if (state.kind === 'group' && !m.mine) {
      place(h('div', 'msg-sender', who.name));
    }

    var row = h('div', 'msg-row' + (m.mine ? ' msg-row--mine' : '') + (joins ? '' : ' is-first') + ' is-last' + (opts.animate ? ' is-new' : ''));
    if (!m.mine) { row.appendChild(avatar(who, 'msg-av--sm')); }
    if (m.kind === 'deleted') {
      var gone = h('div', 'msg-bubble');
      row.appendChild(gone);
      fillDeleted(row, gone);
      gone.title = fullTime(m.at);
      if (m.id) { row.dataset.id = m.id; }
      place(row);
      var dmeta = h('div', 'msg-meta ' + (m.mine ? 'msg-meta--mine' : 'msg-meta--theirs'), clock(p));
      place(dmeta);
      state.group_ = { sender: sid, at: m.at, day: p.day, row: row, meta: dmeta };
      return row;
    }
    var gif = m.kind === 'gif' ? gifData(m.text) : null;
    var card = m.kind === 'image' || m.kind === 'file' ? fileData(m.text) : null;
    var bubble = h('div', 'msg-bubble' + (gif ? ' is-gif' : card ? (m.kind === 'image' ? ' is-img' : ' is-file') : isEmojiOnly(m.text) ? ' is-emoji' : ''));
    if (gif) { bubble.appendChild(gifImg(gif)); }
    else if (m.kind === 'gif') { bubble.appendChild(document.createTextNode('GIF')); }
    else if (card) { bubble.appendChild(fileBody(m, card)); }
    else if (m.kind === 'image' || m.kind === 'file') { bubble.appendChild(document.createTextNode(m.kind === 'image' ? 'Photo' : 'Document')); }
    else { bubble.appendChild(linkify(m.text)); markMentions(bubble); }
    bubble.title = fullTime(m.at);
    if (m.mentionsMe && !m.mine) { row.classList.add('is-mention'); }
    var rbtn = h('button', 'msg-react-btn');
    rbtn.type = 'button'; rbtn.title = 'React'; rbtn.setAttribute('aria-label', 'React to this message');
    rbtn.appendChild(h('i', 'fa-regular fa-face-smile'));
    if (m.mine) {                                                      // the buttons sit on the inner side
      var dbtn = h('button', 'msg-del-btn');
      dbtn.type = 'button'; dbtn.title = 'Delete'; dbtn.setAttribute('aria-label', 'Delete this message');
      dbtn.appendChild(h('i', 'fa-regular fa-trash-can'));
      row.appendChild(dbtn); row.appendChild(rbtn); row.appendChild(bubble);
    }
    else { row.appendChild(bubble); row.appendChild(rbtn); }
    if (m.id) { row.dataset.id = m.id; }
    place(row);

    var meta = h('div', 'msg-meta ' + (m.mine ? 'msg-meta--mine' : 'msg-meta--theirs'), clock(p));
    place(meta);
    state.group_ = { sender: sid, at: m.at, day: p.day, row: row, meta: meta };
    return row;
  }

  function appendMessages(msgs, opts) {
    opts = opts || {};
    var hello = el.scroll.querySelector('.msg-empty');
    var added = 0, theirs = 0;
    msgs.forEach(function (m) {
      if (state.seen[m.id]) { return; }
      // a message of mine that is still "sending" here: adopt it instead of drawing it twice
      if (m.mine && m.kind !== 'event') {
        var mkey = m.kind === 'gif' ? 'gif:' + ((gifData(m.text) || {}).f || '')
                 : m.kind === 'image' || m.kind === 'file' ? 'file:' + ((fileData(m.text) || {}).s || '') : m.text;
        for (var i = 0; i < state.pending.length; i++) {
          if ((state.pending[i].key || state.pending[i].text) === mkey) {
            var pend = state.pending.splice(i, 1)[0];
            pend.row.dataset.id = m.id;
            if (pend.file) { linkFile(pend.row, m.id); }
            pend.row.classList.remove('is-pending');
            state.seen[m.id] = true;
            if (m.id > state.lastId) { state.lastId = m.id; }
            return;
          }
        }
      }
      if (hello) { hello.remove(); hello = null; }
      state.seen[m.id] = true;
      addBubble(m, { animate: opts.animate, dividerBefore: opts.firstUnread && m.id === opts.firstUnread });
      if (m.id > state.lastId) { state.lastId = m.id; }
      added++;
      if (!m.mine) { theirs++; }
    });
    if (added) { updateStatus(); }
    return theirs;
  }

  /** "Sending…" / "Sent" / "Seen" / "Seen by Bea, Ben" / "Not sent · Retry" under my newest message */
  function updateStatus() {
    if (state.statusEl) { state.statusEl.remove(); state.statusEl = null; }
    var mine = el.scroll.querySelectorAll('.msg-row--mine');
    if (!mine.length) { return; }
    var row = mine[mine.length - 1];
    var id = +row.dataset.id || 0;
    var st = h('div', 'msg-status');
    var line = function (icon, text, cls) { if (cls) { st.classList.add(cls); } st.appendChild(h('i', icon)); st.appendChild(document.createTextNode(text)); };
    if (row.classList.contains('is-failed')) {
      line('fa-solid fa-circle-exclamation', 'Not sent · ', 'is-failed');
      var retry = h('button', null, 'Retry');
      retry.type = 'button';
      retry.addEventListener('click', function () { retrySend(row); });
      st.appendChild(retry);
    } else if (row.classList.contains('is-pending') || !id) {
      line('fa-regular fa-clock', 'Sending…');
    } else if (state.kind === 'group') {
      var seenBy = state.readers.filter(function (r) { return r.lastRead >= id; }).map(function (r) { return r.name; });
      if (!seenBy.length) { line('fa-solid fa-check', 'Sent'); }
      else if (seenBy.length === state.readers.length && seenBy.length > 1) { line('fa-solid fa-check-double', 'Seen by everyone', 'is-seen'); }
      else { line('fa-solid fa-check-double', 'Seen by ' + (seenBy.length > 3 ? seenBy.length + ' people' : namesText(seenBy)), 'is-seen'); }
    } else if (id <= state.seenUpTo) {
      line('fa-solid fa-check-double', 'Seen', 'is-seen');
    } else {
      line('fa-solid fa-check', 'Sent');
    }
    var after = row.nextSibling && row.nextSibling.classList && row.nextSibling.classList.contains('msg-meta') ? row.nextSibling : row;
    after.parentNode.insertBefore(st, after.nextSibling);
    state.statusEl = st;
  }

  /** typing bubble at the bottom: 1-to-1 = their avatar; group = "Bea is typing" */
  function setTyping(names) {
    var key = names.join('|');
    if (state.typingEl && state.typingEl.dataset.key === key) { return; }
    if (state.typingEl) { state.typingEl.remove(); state.typingEl = null; }
    if (!names.length) { return; }
    var stick = nearBottom();
    var t = h('div', 'msg-typing-wrap');
    t.dataset.key = key;
    if (state.kind === 'group') { t.appendChild(h('div', 'msg-typing__who', namesText(names) + (names.length > 1 ? ' are' : ' is') + ' typing')); }
    var row = h('div', 'msg-typing');
    var who = state.kind === 'group' ? { initials: names[0].slice(0, 1).toUpperCase() } : state.person;
    row.appendChild(avatar(who, 'msg-av--sm'));
    var b = h('div', 'msg-bubble');
    b.appendChild(h('i')); b.appendChild(h('i')); b.appendChild(h('i'));
    row.appendChild(b);
    t.appendChild(row);
    el.scroll.appendChild(t);
    state.typingEl = t;
    if (stick) { toBottom(); }
  }

  function renderHead() {
    el.headRole.textContent = '';
    if (state.kind === 'group' && state.group) {
      var g = state.group;
      fillAvatar(el.headAv, { type: 'group' });
      el.headAv.classList.remove('is-online');
      el.headName.textContent = g.name;
      if (state.typingNames.length) {
        el.headRole.appendChild(h('span', 'on', namesText(state.typingNames) + (state.typingNames.length > 1 ? ' are' : ' is') + ' typing…'));
      } else {
        var firsts = g.members.map(function (m) { return m.first; });
        el.headRole.appendChild(h('span', null, g.members.length + ' members · ' + namesText(firsts)));
      }
      el.call.hidden = !callsOn() || !!state.activeCall;
      el.call.title = 'Video call the group (up to 4 people)';
      el.info.hidden = false;
      renderCallBar();
      return;
    }
    var p = state.person, pr = state.presence;
    el.info.hidden = true;
    el.callBar.hidden = true;
    if (!p) { return; }
    fillAvatar(el.headAv, p);
    el.headAv.classList.toggle('is-online', !!(pr && pr.online));
    el.headName.textContent = p.name;
    el.headRole.appendChild(h('span', null, p.position || p.id));
    el.call.hidden = !state.canSend || !callsOn();
    el.call.title = 'Video call';
    var label = pr && pr.typing ? 'typing…' : activeLabel(pr);
    if (label) {
      el.headRole.appendChild(h('span', null, '·'));
      el.headRole.appendChild(h('span', pr.online || pr.typing ? 'on' : null, label));
    }
  }

  function renderCallBar() {
    var c = state.activeCall;
    el.callBar.hidden = !c;
    if (!c) { return; }
    el.callBarText.textContent = 'Group call in progress' + (c.joined.length ? ' · ' + namesText(c.joined.map(function (n) { return n.split(' ')[0]; })) : '') +
      ' (' + c.joined.length + '/' + c.max + ')';
    var full = c.joined.length >= c.max;
    el.callJoin.disabled = c.imIn || full || !callsOn();
    el.callJoin.textContent = c.imIn ? 'You’re in it' : full ? 'Full' : 'Join';
  }

  // ------------------------------------------------------------------ open / close

  function showThreadUI(on) {
    el.placeholder.hidden = on;
    el.head.hidden = !on;
    el.scroll.hidden = !on;
    app.classList.toggle('has-thread', on);
    if (!on) { el.jump.hidden = true; el.callBar.hidden = true; }
  }

  function resetThread() {
    state.lastId = 0; state.seen = {}; state.lastDay = ''; state.group_ = null;
    state.pending = []; state.typingEl = null; state.statusEl = null; state.newBelow = 0;
    state.seenUpTo = 0; state.presence = null; state.readers = []; state.typingNames = []; state.activeCall = null;
    state.rxBusy = {};
    closeRxBar();
    if (el.gif) { el.gif.hidden = true; el.gifBtn.classList.remove('is-on'); }
    closeMention();
    el.jump.hidden = true;
    el.callBar.hidden = true;
    closeEmoji();
  }

  /** take a thread response (first load or poll) into state */
  function absorb(j) {
    state.kind = j.kind;
    state.canSend = !!j.canSend;
    if (j.kind === 'group') {
      state.group = j.group;
      state.person = null;
      state.readers = j.readers || [];
      state.typingNames = j.typing || [];
      state.activeCall = j.activeCall;
    } else {
      state.person = j.person;
      state.group = null;
      state.presence = j.presence;
      state.seenUpTo = j.seenUpTo || state.seenUpTo;
      state.typingNames = j.presence && j.presence.typing ? [j.person.name] : [];
    }
  }

  var openSeq = 0;
  function openThread(key) {
    if (!key) { return; }
    if (state.active && state.active !== key) { stopTyping(); }
    var seq = ++openSeq;
    state.active = key;
    resetThread();
    hideError();

    // instant header from the list while the conversation loads
    var known = threadByKey(key);
    state.kind = known ? known.type : (String(key).indexOf('grp:') === 0 ? 'group' : 'dm');
    el.call.hidden = true; el.info.hidden = true; el.clear.hidden = true;
    if (known) {
      fillAvatar(el.headAv, known);
      el.headName.textContent = known.name;
      el.headRole.textContent = known.type === 'group' ? known.members + ' members' : '';
    } else { el.headName.textContent = ''; el.headRole.textContent = ''; el.headAv.textContent = ''; }

    el.scroll.textContent = '';
    el.scroll.appendChild(skeletonThread());
    showThreadUI(true);
    renderList();
    try { history.replaceState(null, '', 'messages?with=' + encodeURIComponent(key)); } catch (e) { /* ignore */ }

    api({ action: 'thread', with: key }).then(function (j) {
      if (seq !== openSeq) { return; }
      state.today = j.today;
      absorb(j);
      renderHead();
      el.scroll.textContent = '';
      if (!j.messages.length) {
        var hi = empty('fa-regular fa-hand', 'Say hello', 'This is the start of your conversation with ' + (j.person ? j.person.name : j.group.name) + '.');
        hi.querySelector('i').className = 'msg-hello'; hi.querySelector('i').textContent = '👋';
        el.scroll.appendChild(hi);
      }
      el.clear.hidden = !j.messages.length;   // nothing to delete in a brand-new conversation
      appendMessages(j.messages, { firstUnread: j.firstUnread });
      applyDeleted(j.deleted);
      applyReactions(j.reactions);
      setTyping(state.kind === 'group' ? state.typingNames : (state.typingNames.length ? ['x'] : []));
      var divider = el.scroll.querySelector('.msg-new');
      if (divider) {
        el.scroll.classList.add('is-instant');
        el.scroll.scrollTop = Math.max(0, divider.offsetTop - 60);
        el.scroll.classList.remove('is-instant');
      } else {
        toBottom(true);
      }
      el.form.hidden = !state.canSend;
      el.readonly.hidden = state.canSend;
      updateComposer();
      if (state.canSend && window.innerWidth > 760) { el.text.focus(); }
      loadThreads();   // opening read them: refresh counts + badge
    }).catch(function (e) {
      if (seq !== openSeq) { return; }
      el.scroll.textContent = '';
      el.scroll.appendChild(empty('fa-solid fa-triangle-exclamation', 'Couldn’t open this conversation', e.message));
      el.form.hidden = true;
      el.readonly.hidden = true;
    });
  }

  function closeThread() {
    stopTyping();
    state.active = null; state.person = null; state.group = null; state.kind = null;
    el.clear.hidden = true;
    resetThread();
    showThreadUI(false);
    el.form.hidden = true;
    el.readonly.hidden = true;
    hideError();
    renderList();
    try { history.replaceState(null, '', 'messages'); } catch (e) { /* ignore */ }
  }
  el.back.addEventListener('click', closeThread);

  // Delete conversation — for me only (includes/msg-clear.php): it leaves my list, the others keep their copy,
  // and a new message brings it back with just what came after.
  el.clear.addEventListener('click', function () {
    var key = state.active;
    if (!key) { return; }
    var isGroup = state.kind === 'group' && state.group;
    var who = isGroup ? state.group.name : (state.person ? state.person.name : 'The other person');
    if (!confirm('Delete this conversation? It will be removed from your Messages.\n\n' +
                 (isGroup ? 'Everyone else in ' + who + ' keeps their copy.' : who + ' keeps their copy.'))) { return; }
    el.clear.disabled = true;
    post('clear', { with: key }).then(function () {
      el.clear.disabled = false;
      closeThread();
      loadThreads();
    }).catch(function (e) {
      el.clear.disabled = false;
      showError(e.message);
    });
  });

  // ------------------------------------------------------------------ calls (assets/js/wedo-call.js, on every page)

  el.call.addEventListener('click', function () {
    if (!window.WeDoCall || !state.active) { return; }
    if (state.kind === 'group' && state.group) { window.WeDoCall.startGroup(state.active, state.group); }
    else if (state.person) { window.WeDoCall.start(state.active, state.person); }
  });
  el.callJoin.addEventListener('click', function () {
    if (window.WeDoCall && state.activeCall) { window.WeDoCall.join(state.activeCall.id, state.group); }
  });
  /** calls are offered only once this server has confirmed they're set up */
  function callsOn() { return !!(window.WeDoCall && window.WeDoCall.enabled && window.WeDoCall.enabled()); }
  document.addEventListener('wdcall:ready', function () { if (state.active) { renderHead(); } });
  document.addEventListener('wdcall:status', function () { if (state.active) { renderHead(); } });
  document.addEventListener('wdcall:ended', function () { if (state.active) { poll(); } });

  // ------------------------------------------------------------------ jump to latest

  el.scroll.addEventListener('scroll', function () {
    if (nearBottom()) {
      state.newBelow = 0;
      el.jump.hidden = true;
    } else if (el.scroll.scrollHeight - el.scroll.scrollTop - el.scroll.clientHeight > 300) {
      showJump();
    }
  });
  function showJump() {
    el.jump.hidden = false;
    el.jump.classList.toggle('has-new', state.newBelow > 0);
    el.jump.querySelector('span').textContent = state.newBelow > 0
      ? state.newBelow + ' new message' + (state.newBelow > 1 ? 's' : '') : 'Latest';
  }
  el.jump.addEventListener('click', function () { toBottom(); });

  // ------------------------------------------------------------------ compose + send

  function showError(msg) { el.error.textContent = msg; el.error.hidden = false; }
  function hideError() { el.error.hidden = true; el.error.textContent = ''; }

  function updateComposer() {
    el.text.style.height = 'auto';
    el.text.style.height = Math.min(el.text.scrollHeight, 140) + 'px';
    var n = el.text.value.length;
    el.count.textContent = n > maxLen - 100 ? n + '/' + maxLen : '';
    el.count.classList.toggle('is-over', n > maxLen);
    el.send.disabled = !el.text.value.trim() || n > maxLen;
  }

  function announceTyping() {
    if (!state.active || !state.canSend) { return; }
    var has = !!el.text.value.trim();
    var now = Date.now();
    if (has && (!state.typingOn || now - state.typingSentAt > TYPING_EVERY_MS)) {
      state.typingOn = true; state.typingSentAt = now;
      post('typing', { with: state.active }).catch(function () {});
    } else if (!has && state.typingOn) {
      stopTyping();
    }
  }
  function stopTyping() {
    if (!state.typingOn) { return; }
    state.typingOn = false;
    post('typing', { with: '' }).catch(function () {});
  }

  // "@" in a group: pick a member (or everyone) to mention
  var mention = { pop: h('div', 'msg-mention-pop'), items: [], at: 0, start: -1 };
  mention.pop.hidden = true;
  mention.pop.setAttribute('role', 'listbox');
  mention.pop.setAttribute('aria-label', 'Mention someone');
  el.form.appendChild(mention.pop);

  function closeMention() { mention.pop.hidden = true; mention.items = []; mention.start = -1; }
  function mentionCandidates(q) {
    if (state.kind !== 'group' || !state.group || !state.group.members) { return []; }
    var others = {};
    state.readers.forEach(function (r) { others[r.id] = true; });   // readers = everyone but me
    q = q.toLowerCase();
    var out = [];
    if ('everyone'.indexOf(q) === 0) { out.push({ everyone: true, name: 'everyone' }); }
    state.group.members.forEach(function (m) {
      if (!others[m.id]) { return; }
      var n = m.name.toLowerCase();
      var hit = !q || n.indexOf(q) === 0 || n.split(/\s+/).some(function (w) { return w.indexOf(q) === 0; });
      if (hit) { out.push(m); }
    });
    return out.slice(0, 7);
  }
  function updateMention() {
    var caret = el.text.selectionStart, before = el.text.value.slice(0, caret);
    var m = /(?:^|\s)@([^\s@]{0,24}(?: [^\s@]{0,24})?)$/.exec(before);
    var list = m ? mentionCandidates(m[1]) : [];
    if (!list.length) { closeMention(); return; }
    mention.start = caret - m[1].length - 1;
    mention.items = list;
    mention.at = Math.min(mention.at, list.length - 1);
    mention.pop.textContent = '';
    list.forEach(function (p, i) {
      var b = h('button', i === mention.at ? 'is-on' : null);
      b.type = 'button'; b.setAttribute('role', 'option');
      if (p.everyone) {
        var ic = h('span', 'msg-av msg-av--sm msg-mention-pop__all'); ic.appendChild(h('i', 'fa-solid fa-users'));
        b.appendChild(ic);
        b.appendChild(h('span', null, '@everyone'));
        b.appendChild(h('small', null, 'Notify the whole group'));
      } else {
        b.appendChild(avatar(p, 'msg-av--sm'));
        b.appendChild(h('span', null, p.name));
        if (p.position) { b.appendChild(h('small', null, p.position)); }
      }
      b.addEventListener('mousedown', function (e) { e.preventDefault(); pickMention(i); });   // keep focus in the box
      mention.pop.appendChild(b);
    });
    mention.pop.hidden = false;
  }
  function pickMention(i) {
    var p = mention.items[i];
    if (!p || mention.start < 0) { return; }
    var t = el.text, caret = t.selectionStart;
    var ins = '@' + (p.everyone ? 'everyone' : p.name) + ' ';
    t.value = t.value.slice(0, mention.start) + ins + t.value.slice(caret);
    t.selectionStart = t.selectionEnd = mention.start + ins.length;
    closeMention();
    t.focus();
    updateComposer();
    announceTyping();
  }
  /** arrow keys / Enter / Tab / Escape while the suggestions are open; true = handled */
  function mentionKey(e) {
    if (mention.pop.hidden || !mention.items.length) { return false; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      mention.at = (mention.at + (e.key === 'ArrowDown' ? 1 : -1) + mention.items.length) % mention.items.length;
      Array.prototype.forEach.call(mention.pop.children, function (b, i) { b.classList.toggle('is-on', i === mention.at); });
    } else if ((e.key === 'Enter' && !e.shiftKey) || e.key === 'Tab') {
      pickMention(mention.at);
    } else if (e.key === 'Escape') {
      closeMention();
    } else { return false; }
    e.preventDefault();
    e.stopPropagation();
    return true;
  }

  el.text.addEventListener('input', function () { updateComposer(); announceTyping(); updateMention(); });
  el.text.addEventListener('click', updateMention);
  el.text.addEventListener('blur', function () { setTimeout(closeMention, 120); });
  el.text.addEventListener('keydown', function (e) {
    if (mentionKey(e)) { return; }
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      if (el.form.requestSubmit) { el.form.requestSubmit(); } else { el.form.dispatchEvent(new Event('submit', { cancelable: true })); }
    }
  });

  var tempSeq = 0;
  function sendText(text) {
    var to = state.active;
    var now = new Date();
    var at = state.today + ' ' + (now.getHours() < 10 ? '0' : '') + now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes() + ':00';
    var hello = el.scroll.querySelector('.msg-empty');
    if (hello) { hello.remove(); }
    var row = addBubble({ id: 0, mine: true, text: text, at: at }, { animate: true });
    row.classList.add('is-pending');
    row.dataset.temp = 't' + (++tempSeq);
    var entry = { text: text, row: row };
    state.pending.push(entry);
    updateStatus();
    toBottom();

    post('send', { with: to, text: text }).then(function (j) {
      if (state.active !== to) { return; }
      var i = state.pending.indexOf(entry);
      if (i !== -1) {                       // not already adopted by a poll
        state.pending.splice(i, 1);
        row.dataset.id = j.message.id;
        row.classList.remove('is-pending');
        state.seen[j.message.id] = true;
        if (j.message.id > state.lastId) { state.lastId = j.message.id; }
      }
      state.typingOn = false;               // the server cleared it
      updateStatus();
      loadThreads();
    }).catch(function (err) {
      if (state.active !== to) { return; }
      var i = state.pending.indexOf(entry);
      if (i !== -1) { state.pending.splice(i, 1); }
      row.classList.remove('is-pending');
      row.classList.add('is-failed');
      row.dataset.failedText = text;
      showError(err.message);
      updateStatus();
    });
  }

  function retrySend(row) {
    var text = row.dataset.failedText || '';
    var failedGif = row.dataset.failedGif ? JSON.parse(row.dataset.failedGif) : null;
    var meta = row.nextSibling && row.nextSibling.classList && row.nextSibling.classList.contains('msg-meta') ? row.nextSibling : null;
    if (state.group_ && state.group_.row === row) { state.group_ = null; }
    if (meta) { meta.remove(); }
    row.remove();
    hideError();
    if (row._file) { sendFile(row._file); }
    else if (failedGif) { sendGif(failedGif); }
    else if (text) { sendText(text); }
  }

  /** send a GIF picked from the panel: shows at once, confirmed by the server */
  function sendGif(card) {
    var to = state.active;
    var now = new Date();
    var at = state.today + ' ' + (now.getHours() < 10 ? '0' : '') + now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes() + ':00';
    var hello = el.scroll.querySelector('.msg-empty');
    if (hello) { hello.remove(); }
    var text = JSON.stringify({ f: card.file, w: card.w, h: card.h, t: card.title });
    var row = addBubble({ id: 0, mine: true, kind: 'gif', text: text, at: at }, { animate: true });
    row.classList.add('is-pending');
    var entry = { key: 'gif:' + card.file, text: text, row: row };
    state.pending.push(entry);
    updateStatus();
    toBottom();

    post('send_gif', { with: to, gif: card.file }).then(function (j) {
      if (state.active !== to) { return; }
      var i = state.pending.indexOf(entry);
      if (i !== -1) {
        state.pending.splice(i, 1);
        row.dataset.id = j.message.id;
        row.classList.remove('is-pending');
        state.seen[j.message.id] = true;
        if (j.message.id > state.lastId) { state.lastId = j.message.id; }
      }
      updateStatus();
      loadThreads();
    }).catch(function (err) {
      if (state.active !== to) { return; }
      var i = state.pending.indexOf(entry);
      if (i !== -1) { state.pending.splice(i, 1); }
      row.classList.remove('is-pending');
      row.classList.add('is-failed');
      row.dataset.failedGif = JSON.stringify(card);
      showError(err.message);
      updateStatus();
    });
  }

  /** send a picture or document: shows at once (pictures from the local copy), confirmed by the server */
  function sendFile(file) {
    var to = state.active;
    var ext = (/\.([^.]+)$/.exec(file.name || '') || [])[1];
    ext = ext ? ext.toLowerCase() : '';
    if (FILE_EXT.indexOf(ext) === -1) {
      showError('“' + file.name + '” can’t be sent. Pictures (JPG, PNG, GIF, WebP) and documents (PDF, Word, Excel, PowerPoint, TXT, CSV) only.');
      return;
    }
    if (file.size > FILE_MAX) { showError('“' + file.name + '” is too large (10 MB at most).'); return; }
    if (!file.size) { showError('“' + file.name + '” is empty.'); return; }

    var isImg = ['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1;
    var local = isImg && window.URL && URL.createObjectURL ? URL.createObjectURL(file) : '';
    var now = new Date();
    var at = state.today + ' ' + (now.getHours() < 10 ? '0' : '') + now.getHours() + ':' + (now.getMinutes() < 10 ? '0' : '') + now.getMinutes() + ':00';
    var hello = el.scroll.querySelector('.msg-empty');
    if (hello) { hello.remove(); }
    var text = JSON.stringify({ k: '0000/00/' + new Array(33).join('0') + '.' + ext, n: file.name, s: file.size });
    var row = addBubble({ id: 0, mine: true, kind: isImg ? 'image' : 'file', text: text, at: at, local: local }, { animate: true });
    row.classList.add('is-pending');
    var entry = { key: 'file:' + file.size, text: text, row: row, file: true };
    state.pending.push(entry);
    updateStatus();
    toBottom();

    var fd = new FormData();
    fd.append('action', 'send_file'); fd.append('with', to); fd.append('token', token); fd.append('file', file, file.name);
    fetch(API + '?action=send_file', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(readJson).then(function (j) {
        if (state.active !== to) { return; }
        var i = state.pending.indexOf(entry);
        if (i !== -1) {
          state.pending.splice(i, 1);
          row.dataset.id = j.message.id;
          row.classList.remove('is-pending');
          linkFile(row, j.message.id);
          state.seen[j.message.id] = true;
          if (j.message.id > state.lastId) { state.lastId = j.message.id; }
        }
        updateStatus();
        loadThreads();
      }).catch(function (err) {
        if (state.active !== to) { return; }
        var i = state.pending.indexOf(entry);
        if (i !== -1) { state.pending.splice(i, 1); }
        row.classList.remove('is-pending');
        row.classList.add('is-failed');
        row._file = file;
        showError(err.message);
        updateStatus();
      });
  }
  function sendFiles(list) {
    if (!state.filesOn || !state.active || !state.canSend) { return; }
    hideError();
    Array.prototype.slice.call(list || [], 0, 10).forEach(sendFile);   // at most 10 at a time
  }

  el.fileBtn.addEventListener('click', function () { closeEmoji(); el.fileInput.click(); });
  el.fileInput.addEventListener('change', function () { sendFiles(el.fileInput.files); el.fileInput.value = ''; });
  // paste a screenshot straight into the message box
  el.text.addEventListener('paste', function (e) {
    var files = e.clipboardData && e.clipboardData.files;
    if (!state.filesOn || !files || !files.length) { return; }
    e.preventDefault();
    sendFiles(files);
  });
  // drop files onto the conversation
  function dragHasFiles(e) {
    return state.filesOn && state.active && state.canSend && e.dataTransfer &&
      Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1;
  }
  var dragDepth = 0;
  el.main.addEventListener('dragenter', function (e) { if (dragHasFiles(e)) { dragDepth++; el.main.classList.add('is-drop'); } });
  el.main.addEventListener('dragleave', function () { if (dragDepth && --dragDepth === 0) { el.main.classList.remove('is-drop'); } });
  el.main.addEventListener('dragover', function (e) { if (dragHasFiles(e)) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
  el.main.addEventListener('drop', function (e) {
    dragDepth = 0; el.main.classList.remove('is-drop');
    if (!dragHasFiles(e)) { return; }
    e.preventDefault();
    sendFiles(e.dataTransfer.files);
  });

  el.form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = el.text.value.trim();
    if (!text || text.length > maxLen || !state.active || el.send.disabled) { return; }
    hideError();
    closeEmoji();
    el.text.value = '';
    updateComposer();
    el.text.focus();
    sendText(text);
  });

  // ------------------------------------------------------------------ emoji

  var EMOJI = ['😀', '😂', '😊', '🙂', '😉', '😍', '🥰', '😎', '🤔', '😅', '😮', '😢', '😭', '😡', '🙄', '😴',
               '👍', '👎', '👏', '🙏', '💪', '🙌', '👌', '👋', '✌️', '🤝', '❤️', '💯', '🔥', '🎉', '🎂', '⭐',
               '✅', '❌', '⚠️', '📌', '📎', '📅', '⏰', '☕'];
  EMOJI.forEach(function (em) {
    var b = h('button', null, em);
    b.type = 'button';
    b.setAttribute('aria-label', em);
    el.emoji.appendChild(b);
  });
  function closeEmoji() { el.emoji.hidden = true; el.emojiBtn.classList.remove('is-on'); }
  el.emojiBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    if (!el.gif.hidden) { closeGif(); }
    el.emoji.hidden = !el.emoji.hidden;
    el.emojiBtn.classList.toggle('is-on', !el.emoji.hidden);
  });
  el.emoji.addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (!b) { return; }
    e.stopPropagation();
    var t = el.text, s = t.selectionStart || t.value.length, en = t.selectionEnd || t.value.length;
    t.value = t.value.slice(0, s) + b.textContent + t.value.slice(en);
    t.selectionStart = t.selectionEnd = s + b.textContent.length;
    t.focus();
    updateComposer();
    announceTyping();
  });
  document.addEventListener('click', function (e) {
    if (!el.emoji.hidden && !el.emoji.contains(e.target) && e.target !== el.emojiBtn) { closeEmoji(); }
  });

  // ------------------------------------------------------------------ GIF panel (the sticker library, searchable)

  var gp = { input: h('input', 'wd-input'), grid: h('div', 'msg-gif__grid'), list: null, loading: false };
  gp.input.type = 'search'; gp.input.placeholder = 'Search GIFs'; gp.input.autocomplete = 'off';
  gp.input.setAttribute('aria-label', 'Search GIFs');
  (function () {
    var top = h('div', 'msg-gif__search'); top.appendChild(gp.input);
    el.gif.appendChild(top);
    el.gif.appendChild(gp.grid);
  })();

  function gifNote(text) { gp.grid.appendChild(h('div', 'msg-gif__note', text)); }
  function renderGifs() {
    gp.grid.textContent = '';
    var q = gp.input.value.trim().toLowerCase();
    var shown = gp.list.filter(function (g) { return !q || (g.title + ' ' + g.tags).toLowerCase().indexOf(q) !== -1; });
    if (!shown.length) { gifNote('No GIFs for “' + gp.input.value.trim() + '”.'); return; }
    shown.forEach(function (g) {
      var b = h('button');
      b.type = 'button'; b.title = g.title; b.setAttribute('aria-label', 'Send GIF: ' + g.title);
      b.appendChild(gifImg({ f: g.file, w: g.w, h: g.h, t: g.title }));
      b.addEventListener('click', function () { closeGif(); sendGif(g); });
      gp.grid.appendChild(b);
    });
  }
  function loadGifs() {
    if (gp.list || gp.loading) { return; }
    gp.loading = true;
    gifNote('Loading…');
    api({ action: 'gifs' }).then(function (j) {
      gp.list = j.gifs;
      renderGifs();
    }).catch(function (e) {
      gp.grid.textContent = '';
      gifNote(e.message);
    }).then(function () { gp.loading = false; });
  }
  function openGif() {
    closeEmoji();
    el.gif.hidden = false;
    el.gifBtn.classList.add('is-on');
    loadGifs();
    if (window.innerWidth > 760) { setTimeout(function () { gp.input.focus(); }, 30); }
  }
  function closeGif() { el.gif.hidden = true; el.gifBtn.classList.remove('is-on'); }
  el.gifBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    if (el.gif.hidden) { openGif(); } else { closeGif(); }
  });
  gp.input.addEventListener('input', function () { if (gp.list) { renderGifs(); } });
  gp.input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.stopPropagation(); closeGif(); el.text.focus(); } });
  document.addEventListener('click', function (e) {
    if (!el.gif.hidden && !el.gif.contains(e.target) && e.target !== el.gifBtn) { closeGif(); }
  });

  // ------------------------------------------------------------------ modal (new group / group info)

  function openModal(build) {
    el.modalCard.textContent = '';
    build(el.modalCard);
    el.modal.hidden = false;
    var first = el.modalCard.querySelector('input') || el.modalCard.querySelector('button.msg-modal__x');
    if (first) { setTimeout(function () { first.focus(); }, 30); }
  }
  function closeModal() { el.modal.hidden = true; el.modalCard.textContent = ''; }
  el.modal.addEventListener('mousedown', function (e) { if (e.target === el.modal) { closeModal(); } });

  function modalHead(card, title) {
    var head = h('div', 'msg-modal__head');
    head.appendChild(h('h3', null, title));
    var x = h('button', 'msg-modal__x');
    x.type = 'button'; x.setAttribute('aria-label', 'Close');
    x.appendChild(h('i', 'fa-solid fa-xmark'));
    x.addEventListener('click', closeModal);
    head.appendChild(x);
    card.appendChild(head);
  }

  /** people picker: search box + results with ticks + chips of who's picked */
  function picker(container, opts) {
    var picked = {};          // id -> person
    var exclude = opts.exclude || {};
    var input = h('input', 'wd-input');
    input.type = 'search'; input.placeholder = 'Search people by name or employee ID'; input.autocomplete = 'off';
    var chips = h('div', 'msg-chips');
    var list = h('div', 'msg-pick');
    container.appendChild(input);
    container.appendChild(chips);
    container.appendChild(list);
    var results = [], seq = 0, timer = null, loading = false, failed = '';

    function renderChips() {
      chips.textContent = '';
      Object.keys(picked).forEach(function (id) {
        var c = h('span', 'msg-chip', picked[id].name);
        var x = h('button', null, '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + picked[id].name);
        x.addEventListener('click', function () { delete picked[id]; renderChips(); renderList_(); opts.onChange(); });
        c.appendChild(x);
        chips.appendChild(c);
      });
    }
    function renderList_() {
      list.textContent = '';
      if (input.value.trim().length < 2) {
        list.appendChild(h('div', 'msg-member__sub', 'Type at least 2 letters to find people.'));
        return;
      }
      if (failed) { list.appendChild(h('div', 'msg-modal__err', 'Search failed: ' + failed)); return; }
      if (loading && !results.length) { list.appendChild(h('div', 'msg-member__sub', 'Searching…')); return; }
      var shown = results.filter(function (p) { return !exclude[p.id]; });
      if (!shown.length) {
        list.appendChild(h('div', 'msg-member__sub', results.length ? 'Everyone found is already in the group.' : 'No one found. Try a first name, last name or employee ID.'));
        return;
      }
      shown.forEach(function (p) {
        var item = h('button', 'msg-item' + (picked[p.id] ? ' is-on' : ''));
        item.type = 'button';
        item.appendChild(avatar(p));
        var main = h('div', 'msg-item__main');
        main.appendChild(h('div', 'msg-item__name', p.name));
        main.appendChild(h('div', 'msg-item__preview', p.position || p.id));
        item.appendChild(main);
        var tick = h('span', 'msg-pick__tick'); tick.appendChild(h('i', 'fa-solid fa-check'));
        item.appendChild(tick);
        item.addEventListener('click', function () {
          if (picked[p.id]) { delete picked[p.id]; } else { picked[p.id] = p; }
          renderChips(); renderList_(); opts.onChange();
        });
        list.appendChild(item);
      });
    }
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var term = input.value.trim();
      failed = '';
      if (term.length < 2) { results = []; loading = false; renderList_(); return; }
      loading = true; results = []; renderList_();
      timer = setTimeout(function () {
        var s = ++seq;
        api({ action: 'search', term: term }).then(function (j) {
          if (s === seq) { loading = false; results = j.people || []; renderList_(); }
        }).catch(function (e) {
          if (s === seq) { loading = false; results = []; failed = e.message; renderList_(); }   // show why, don't pretend "no one"
        });
      }, 220);
    });
    renderList_();
    return { ids: function () { return Object.keys(picked); }, focus: function () { input.focus(); } };
  }

  el.newGroup.addEventListener('click', function () {
    openModal(function (card) {
      modalHead(card, 'New group');
      var body = h('div', 'msg-modal__body');
      var lab = h('label', null, 'Group name');
      var name = h('input', 'wd-input');
      name.maxLength = 60; name.placeholder = 'e.g. Payroll Team';
      lab.appendChild(name);
      body.appendChild(lab);
      body.appendChild(h('div', 'msg-section', 'Members (pick at least 2)'));
      var create, pick = picker(body, { onChange: function () { check(); } });
      var err = h('div', 'msg-modal__err');
      body.appendChild(err);
      card.appendChild(body);
      var foot = h('div', 'msg-modal__foot');
      var cancel = h('button', 'wd-btn wd-btn--ghost', 'Cancel'); cancel.type = 'button';
      cancel.addEventListener('click', closeModal);
      create = h('button', 'wd-btn wd-btn--primary', 'Create group'); create.type = 'button';
      foot.appendChild(cancel); foot.appendChild(create);
      card.appendChild(foot);
      function check() { create.disabled = !name.value.trim() || pick.ids().length < 2; }
      name.addEventListener('input', check);
      check();
      create.addEventListener('click', function () {
        create.disabled = true; err.textContent = '';
        post('group_create', { name: name.value, members: pick.ids() }).then(function (j) {
          closeModal();
          state.threadsKey = '';
          loadThreads().then(function () { openThread(j.key); });
        }).catch(function (e) { err.textContent = e.message; check(); });
      });
    });
  });

  function openGroupInfo() {
    if (state.kind !== 'group' || !state.group) { return; }
    var g = state.group, admin = g.myRole === 'admin';
    openModal(function (card) {
      modalHead(card, 'Group info');
      var body = h('div', 'msg-modal__body');
      var err = h('div', 'msg-modal__err');

      // name
      body.appendChild(h('div', 'msg-section', 'Name'));
      if (admin) {
        var row = h('div', 'msg-rename');
        var name = h('input', 'wd-input'); name.value = g.name; name.maxLength = 60;
        var save = h('button', 'wd-btn wd-btn--ghost wd-btn--sm', 'Rename'); save.type = 'button';
        save.addEventListener('click', function () {
          save.disabled = true; err.textContent = '';
          post('group_rename', { id: g.id, name: name.value }).then(function () { refreshAfterChange(true); })
            .catch(function (e) { err.textContent = e.message; save.disabled = false; });
        });
        row.appendChild(name); row.appendChild(save);
        body.appendChild(row);
      } else {
        body.appendChild(h('div', 'msg-member__name', g.name));
      }

      // members
      body.appendChild(h('div', 'msg-section', g.members.length + ' members'));
      var me = null;
      g.members.forEach(function (m) {
        var r = h('div', 'msg-member');
        r.appendChild(avatar(m));
        var main = h('div', 'msg-member__main');
        var isMe = state.readers.every(function (x) { return x.id !== m.id; });
        if (isMe) { me = m; }
        main.appendChild(h('div', 'msg-member__name', m.name + (isMe ? ' (you)' : '')));
        main.appendChild(h('div', 'msg-member__sub', m.position || m.id));
        r.appendChild(main);
        if (m.role === 'admin') { r.appendChild(h('span', 'msg-role', 'Admin')); }
        if (admin && !isMe) {
          var rm = h('button', 'msg-linkbtn msg-linkbtn--danger', 'Remove'); rm.type = 'button';
          rm.addEventListener('click', function () {
            if (!confirm('Remove ' + m.name + ' from “' + g.name + '”?')) { return; }
            post('group_remove', { id: g.id, member: m.id }).then(function () { refreshAfterChange(true); })
              .catch(function (e) { err.textContent = e.message; });
          });
          r.appendChild(rm);
        }
        body.appendChild(r);
      });

      // add people (admins)
      if (admin) {
        body.appendChild(h('div', 'msg-section', 'Add people'));
        var have = {}; g.members.forEach(function (m) { have[m.id] = true; });
        var add = h('button', 'wd-btn wd-btn--primary wd-btn--sm', 'Add to group'); add.type = 'button'; add.disabled = true;
        var pick = picker(body, { exclude: have, onChange: function () { add.disabled = !pick.ids().length; } });
        body.appendChild(add);
        add.addEventListener('click', function () {
          add.disabled = true; err.textContent = '';
          post('group_add', { id: g.id, members: pick.ids() }).then(function () { refreshAfterChange(true); })
            .catch(function (e) { err.textContent = e.message; add.disabled = false; });
        });
      }
      body.appendChild(err);
      card.appendChild(body);

      var foot = h('div', 'msg-modal__foot');
      var leave = h('button', 'msg-linkbtn msg-linkbtn--danger', 'Leave group'); leave.type = 'button';
      leave.addEventListener('click', function () {
        if (!confirm('Leave “' + g.name + '”? You won’t get its messages any more.')) { return; }
        post('group_leave', { id: g.id }).then(function () {
          closeModal();
          state.threadsKey = '';
          closeThread();
          loadThreads();
        }).catch(function (e) { err.textContent = e.message; });
      });
      foot.appendChild(leave);
      foot.appendChild(h('span', 'grow'));
      var done = h('button', 'wd-btn wd-btn--ghost', 'Done'); done.type = 'button';
      done.addEventListener('click', closeModal);
      foot.appendChild(done);
      card.appendChild(foot);
    });
  }
  el.info.addEventListener('click', openGroupInfo);

  /** after a membership change: pull the thread now and reopen the info panel with fresh data */
  function refreshAfterChange(reopen) {
    poll().then(function () { if (reopen && !el.modal.hidden) { openGroupInfo(); } });
  }

  // ------------------------------------------------------------------ keyboard

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
      e.preventDefault();
      if (app.classList.contains('has-thread') && window.innerWidth <= 760) { closeThread(); }
      el.search.focus(); el.search.select();
    } else if (e.key === 'Escape') {
      if (rxRow) { closeRxBar(); }
      else if (!el.gif.hidden) { closeGif(); }
      else if (!el.modal.hidden) { closeModal(); }
      else if (!el.emoji.hidden) { closeEmoji(); el.text.focus(); }
      else if (state.active && window.innerWidth <= 760) { closeThread(); }
    }
  });

  // ------------------------------------------------------------------ polling

  function poll() {
    if (document.hidden || state.polling) { return Promise.resolve(); }
    state.polling = true;
    var reqs = [loadThreads()];
    var active = state.active, seq = openSeq;
    if (active && (state.person || state.group) && !el.scroll.querySelector('.msg-skelb')) {
      reqs.push(api({ action: 'thread', with: active, after: state.lastId }).then(function (j) {
        if (state.active !== active || seq !== openSeq) { return; }
        var stick = nearBottom();
        var couldSend = state.canSend;
        absorb(j);
        if (couldSend !== state.canSend) {
          el.form.hidden = !state.canSend;
          el.readonly.hidden = state.canSend;
        }
        var theirs = appendMessages(j.messages, { animate: true });
        applyDeleted(j.deleted);
        applyReactions(j.reactions);
        setTyping(theirs ? [] : (state.kind === 'group' ? state.typingNames : (state.typingNames.length ? ['x'] : [])));
        renderHead();
        updateStatus();
        if (stick) { toBottom(); }
        else if (theirs) { state.newBelow += theirs; showJump(); }
      }).catch(function (e) {
        if (e.http === 404 && state.active === active && state.kind === 'group') {   // removed from the group
          closeThread();
          state.threadsKey = '';
          alert('You’re no longer in this group.');
        }
      }));
    }
    return Promise.all(reqs).catch(function () { /* transient; next tick retries */ })
      .then(function () { state.polling = false; });
  }
  (function loop() {
    setTimeout(function () { poll().then(loop, loop); }, state.active && !document.hidden ? POLL_LIVE : POLL_IDLE);
  })();
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stopTyping(); } else { poll(); }
  });
  window.addEventListener('pagehide', stopTyping);

  // ------------------------------------------------------------------ start

  updateComposer();
  el.list.appendChild(skeletonList(6));
  loadThreads().catch(function (e) {
    el.list.textContent = '';
    el.list.appendChild(empty('fa-solid fa-triangle-exclamation', 'Couldn’t load conversations', e.message));
  });
  if (app.dataset.with) { openThread(app.dataset.with); }
})();
