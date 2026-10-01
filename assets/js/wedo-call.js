/* ==========================================================================
   wedo-call.js — video calls on every signed-in page: 1-to-1 and group calls
   up to 4 people. Config comes from includes/call-widget.php (window.WD_CALL);
   the server side is query/Query-calls.php.

   Audio/video go browser-to-browser over WebRTC. In a group call every pair of
   people has its own connection (a mesh): for each pair, the person whose
   EmpID sorts first sends the offer, so two people never offer to each other
   at once. The server only relays these set-up messages and the call's state.

     ringer   polls `incoming` every 3 s: "Bea is calling" / "Ops Team · Bea is
              calling", Accept / Decline, sound, tab title, desktop notification
     window   video tiles for everyone in the call, mic / camera / minimize /
              hang up; minimized it floats bottom-left so the page stays usable
     API      window.WeDoCall.start(empId, person)     1-to-1
              window.WeDoCall.startGroup(key, group)    whole group ('grp:<id>')
              window.WeDoCall.join(callId, group)       join a group call late

   Text from the server is always set with textContent.
   ========================================================================== */
(function () {
  'use strict';

  var CFG = window.WD_CALL;
  if (!CFG || !window.fetch || window.WeDoCall) { return; }

  var RING_POLL_MS = 3000, SETUP_POLL_MS = 900, LIVE_POLL_MS = 1500;
  var canRTC = !!(window.RTCPeerConnection && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  var secure = window.isSecureContext !== false;
  var ME = String(CFG.me);

  /* ------------------------------------------------------------------ icons (inline: legacy pages load an old icon font) */
  var ICON = {
    hang:  '<svg viewBox="0 0 24 24" width="1.1em" height="1.1em" fill="currentColor"><path d="M12 9c-1.6 0-3.1.3-4.6.7v3.1c0 .4-.2.7-.6.9-1 .5-1.9 1.1-2.7 1.8-.2.2-.5.3-.7.3-.3 0-.5-.1-.7-.3L.3 13.1A1 1 0 0 1 0 12.4c0-.3.1-.5.3-.7C3.4 8.8 7.5 7 12 7s8.6 1.8 11.7 4.7c.2.2.3.4.3.7 0 .3-.1.5-.3.7l-2.4 2.4c-.2.2-.4.3-.7.3-.3 0-.5-.1-.7-.3a11 11 0 0 0-2.7-1.8.9.9 0 0 1-.6-.9V9.7C15.1 9.3 13.6 9 12 9z"/></svg>',
    video: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z"/></svg>',
    videoOff: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M21 6.5l-4 4V7a1 1 0 0 0-1-1H9.8L21 17.2V6.5zM3.3 2 2 3.3 4.7 6H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12c.2 0 .4-.1.5-.2l3.2 3.2 1.3-1.3L3.3 2z"/></svg>',
    mic: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5.3-3a5.3 5.3 0 0 1-10.6 0H5a7 7 0 0 0 6 6.9V21h2v-3.1a7 7 0 0 0 6-6.9h-1.7z"/></svg>',
    micOff: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M19 11h-1.7c0 .7-.2 1.4-.4 2l1.2 1.2c.6-.9.9-2 .9-3.2zm-4 .2V5a3 3 0 0 0-6 0v.2l6 6zM4.3 3 3 4.3l6 6V11a3 3 0 0 0 4.4 2.7l1.7 1.7a5.3 5.3 0 0 1-8.4-4.4H5a7 7 0 0 0 6 6.9V21h2v-3.1c1-.1 2-.5 2.8-1L19.7 21 21 19.7 4.3 3z"/></svg>',
    mini: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M19 13H5v-2h14v2z"/></svg>',
    full: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>',
    group: '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor"><path d="M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 2c-2.3 0-7 1.2-7 3.5V19h14v-2.5C15 14.2 10.3 13 8 13zm8 0c-.3 0-.6 0-1 .1 1.2.8 2 2 2 3.4V19h6v-2.5c0-2.3-4.7-3.5-7-3.5z"/></svg>'
  };

  function h(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined && text !== null) { n.textContent = text; }
    return n;
  }
  function iconBtn(cls, icon, label, act) {
    var b = h('button', cls);
    b.type = 'button';
    b.innerHTML = ICON[icon];            // trusted, static markup
    b.setAttribute('aria-label', label);
    b.title = label;
    if (act) { b.dataset.act = act; }
    return b;
  }
  function avatar(p, cls) {
    var a = h('div', 'wdc-av' + (cls ? ' ' + cls : ''));
    if (p && p.photo) {
      var img = document.createElement('img');
      img.src = p.photo; img.alt = '';
      img.onerror = function () { a.textContent = (p && p.initials) || '?'; };
      a.appendChild(img);
    } else if (p && p.isGroup) {
      a.innerHTML = ICON.group;
      a.classList.add('wdc-av--group');
    } else {
      a.textContent = (p && p.initials) || '?';
    }
    return a;
  }

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
          if (!r.ok || j.status !== 'ok') { var e = new Error(j.msg || 'Something went wrong.'); e.code = j.code; e.http = r.status; throw e; }
          return j;
        });
    });
  }

  /** who/what the call is "with", for titles: the group, or the other person */
  function callTitle(call) {
    if (call.group) { return { name: call.group.name, isGroup: true }; }
    var other = (call.members || []).filter(function (m) { return m.id !== ME; })[0] || call.starter;
    return other;
  }

  /* ------------------------------------------------------------------ sound + attention */
  var audioCtx = null, ringLoop = null;
  function tone(freqs, ms) {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') { audioCtx.resume(); }
      var t = audioCtx.currentTime, g = audioCtx.createGain();
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(0.18, t + 0.03);
      g.gain.exponentialRampToValueAtTime(0.0001, t + ms / 1000);
      g.connect(audioCtx.destination);
      freqs.forEach(function (f) {
        var o = audioCtx.createOscillator();
        o.type = 'sine'; o.frequency.value = f;
        o.connect(g); o.start(t); o.stop(t + ms / 1000);
      });
    } catch (e) { /* audio blocked until the user interacts with the page — the card still shows */ }
  }
  function startRingtone(kind) {
    stopRingtone();
    var play = kind === 'incoming'
      ? function () { tone([523, 659], 380); setTimeout(function () { tone([587, 784], 380); }, 450); }
      : function () { tone([440, 480], 900); };                 // soft ringback for the caller
    play();
    ringLoop = setInterval(play, kind === 'incoming' ? 2200 : 3000);
  }
  function stopRingtone() { if (ringLoop) { clearInterval(ringLoop); ringLoop = null; } }

  var baseTitle = document.title, titleLoop = null;
  function flashTitle(text) {
    stopTitle();
    baseTitle = document.title;
    var on = false;
    titleLoop = setInterval(function () { on = !on; document.title = on ? text : baseTitle; }, 1000);
  }
  function stopTitle() { if (titleLoop) { clearInterval(titleLoop); titleLoop = null; document.title = baseTitle; } }

  var note = null;
  function notify(call, title) {
    try {
      if (!document.hidden || !window.Notification || Notification.permission !== 'granted') { return; }
      note = new Notification(title, { body: 'WeDo video call — click to answer', tag: 'wedo-call-' + call.id, requireInteraction: true });
      note.onclick = function () { window.focus(); note.close(); };
    } catch (e) { /* ignore */ }
  }
  function askNotifyPermission() {
    try { if (window.Notification && Notification.permission === 'default') { Notification.requestPermission(); } } catch (e) { /* ignore */ }
  }

  /* ------------------------------------------------------------------ incoming-call card */
  var ringCard = null, ringingCall = null, dismissed = {};

  function showRing(call) {
    if (ringingCall && ringingCall.id === call.id) { return; }
    hideRing();
    ringingCall = call;
    var who = callTitle(call);
    var title = call.group ? call.group.name : call.starter.name;
    var line = call.group ? call.starter.name.split(' ')[0] + ' is calling the group…' : 'Incoming video call…';

    ringCard = h('div', 'wdc-ring');
    ringCard.setAttribute('role', 'alertdialog');
    ringCard.setAttribute('aria-label', title + ' — incoming call');
    ringCard.appendChild(avatar(call.group ? who : call.starter, 'wdc-av--pulse'));
    var info = h('div', 'wdc-ring__info');
    info.appendChild(h('div', 'wdc-ring__name', title));
    var sub = h('div', 'wdc-ring__sub');
    sub.innerHTML = ICON.video;
    sub.appendChild(document.createTextNode(' ' + line));
    info.appendChild(sub);
    ringCard.appendChild(info);
    var btns = h('div', 'wdc-ring__btns');
    var no = iconBtn('wdc-round wdc-round--bad', 'hang', 'Decline');
    var yes = iconBtn('wdc-round wdc-round--ok', 'video', call.group ? 'Join' : 'Accept');
    no.addEventListener('click', function () { declineRing(call); });
    yes.addEventListener('click', function () { acceptRing(call); });
    btns.appendChild(no); btns.appendChild(yes);
    ringCard.appendChild(btns);
    document.body.appendChild(ringCard);
    startRingtone('incoming');
    flashTitle('📞 ' + (call.group ? call.group.name + ' call…' : call.starter.name + ' is calling…'));
    notify(call, call.group ? call.starter.name + ' is calling ' + call.group.name : call.starter.name + ' is calling you');
  }
  function hideRing() {
    if (ringCard) { ringCard.remove(); ringCard = null; }
    ringingCall = null;
    if (!active) { stopRingtone(); }
    stopTitle();
    if (note) { try { note.close(); } catch (e) { /* ignore */ } note = null; }
  }
  function declineRing(call) {
    dismissed[call.id] = true;
    hideRing();
    api({ action: 'leave', id: call.id }, true).catch(function () { /* it rings out on its own */ });
  }
  function acceptRing(call) {
    dismissed[call.id] = true;
    hideRing();
    askNotifyPermission();
    beginCall(call, null, false);
  }

  function pollIncoming() {
    if (active) { return Promise.resolve(); }
    return api({ action: 'incoming' }).then(function (j) {
      setEnabled(!j.disabled);
      // calls not set up on this server: keep checking in slowly — it still keeps me "online" for Messages
      if (j.disabled) { ringerDelay = 20000; return; }
      ringerDelay = RING_POLL_MS;
      if (j.call && !dismissed[j.call.id] && !active) { showRing(j.call); }
      else if (ringingCall && (!j.call || j.call.id !== ringingCall.id)) { hideRing(); }  // answered elsewhere / gave up
    }).catch(function (e) { if (e.http === 401) { stopRingerLoop(); } });
  }
  var ringerTimer = null, ringerOn = true, ringerDelay = RING_POLL_MS;

  /** whether this server has calls set up (null until the first check-in answers) */
  var enabled = null;
  function setEnabled(on) {
    if (enabled === on) { return; }
    enabled = on;
    document.dispatchEvent(new CustomEvent('wdcall:status', { detail: { enabled: on } }));
  }
  function ringerLoop() {
    if (!ringerOn) { return; }
    ringerTimer = setTimeout(function () { pollIncoming().then(ringerLoop, ringerLoop); }, ringerDelay);
  }
  function stopRingerLoop() { ringerOn = false; clearTimeout(ringerTimer); }

  /* ------------------------------------------------------------------ call window */
  var active = null;        // {id, call, everConnected}
  var win = null, ui = {};
  var peers = {};           // EmpID -> {pc, ready, pendingIce, queue, tile, video, connected}
  var localStream = null, iceServers = [], lastSig = 0;
  var callTimer = null, clock = null, connectedAt = 0, closing = false;

  function buildWindow(call) {
    var who = callTitle(call);
    win = h('div', 'wdc');
    win.setAttribute('role', 'dialog');
    win.setAttribute('aria-label', 'Video call — ' + who.name);
    var stage = h('div', 'wdc__stage');
    ui.grid = h('div', 'wdc__grid');
    ui.peer = h('div', 'wdc__peer');
    ui.peer.appendChild(avatar(who, 'wdc-av--xl'));
    ui.peer.appendChild(h('div', 'wdc__name', who.name));
    ui.status = h('div', 'wdc__status', '');
    ui.peer.appendChild(ui.status);
    ui.top = h('div', 'wdc__top');
    ui.pill = h('div', 'wdc__pill');
    ui.pill.hidden = true;
    ui.pill.appendChild(h('span', 'wdc__dot'));
    ui.clock = h('span', null, '0:00');
    ui.pill.appendChild(ui.clock);
    ui.count = h('div', 'wdc__pill');
    ui.count.hidden = true;                 // shown once we know who's in the call
    ui.top.appendChild(ui.pill);
    ui.top.appendChild(ui.count);
    ui.local = document.createElement('video');
    ui.local.className = 'wdc__local'; ui.local.autoplay = true; ui.local.playsInline = true; ui.local.muted = true;
    var bar = h('div', 'wdc__bar');
    ui.mic = iconBtn('wdc-ctl', 'mic', 'Mute microphone', 'mic');
    ui.cam = iconBtn('wdc-ctl', 'video', 'Turn camera off', 'cam');
    ui.min = iconBtn('wdc-ctl', 'mini', 'Minimize', 'min');
    ui.end = iconBtn('wdc-ctl wdc-ctl--end', 'hang', 'Leave call', 'end');
    [ui.mic, ui.cam, ui.min, ui.end].forEach(function (b) { bar.appendChild(b); });
    stage.appendChild(ui.grid); stage.appendChild(ui.peer); stage.appendChild(ui.top); stage.appendChild(ui.local); stage.appendChild(bar);
    win.appendChild(stage);
    document.body.appendChild(win);

    ui.mic.addEventListener('click', function (e) { e.stopPropagation(); toggleTrack('audio', ui.mic, 'mic', 'micOff', 'Mute microphone', 'Unmute microphone'); });
    ui.cam.addEventListener('click', function (e) { e.stopPropagation(); toggleTrack('video', ui.cam, 'video', 'videoOff', 'Turn camera off', 'Turn camera on'); });
    ui.min.addEventListener('click', function (e) { e.stopPropagation(); setMini(!win.classList.contains('is-mini')); });
    ui.end.addEventListener('click', function (e) { e.stopPropagation(); hangUp(); });
    stage.addEventListener('click', function () { if (win.classList.contains('is-mini')) { setMini(false); } });
  }
  function setMini(on) {
    if (!win) { return; }
    win.classList.toggle('is-mini', on);
    ui.min.innerHTML = ICON[on ? 'full' : 'mini'];
    ui.min.title = on ? 'Expand' : 'Minimize';
    ui.min.setAttribute('aria-label', ui.min.title);
  }
  function setStatus(text, bad) {
    ui.status.textContent = text;
    ui.status.classList.toggle('is-bad', !!bad);
  }
  function toggleTrack(kind, btn, onIcon, offIcon, onLabel, offLabel) {
    if (!localStream) { return; }
    var tracks = kind === 'audio' ? localStream.getAudioTracks() : localStream.getVideoTracks();
    if (!tracks.length) { return; }
    var enable = !tracks[0].enabled;
    tracks.forEach(function (t) { t.enabled = enable; });
    btn.classList.toggle('is-off', !enable);
    btn.innerHTML = ICON[enable ? onIcon : offIcon];
    btn.title = enable ? onLabel : offLabel;
    btn.setAttribute('aria-label', btn.title);
    if (kind === 'video') { ui.local.classList.toggle('is-off', !enable); }
  }
  function startClock(agoSecs) {
    if (clock) { return; }
    connectedAt = Date.now() - (agoSecs ? agoSecs * 1000 : 0);
    ui.pill.hidden = false;
    var tick = function () {
      var s = Math.floor((Date.now() - connectedAt) / 1000), m = Math.floor(s / 60);
      ui.clock.textContent = (m >= 60 ? Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2) : m) + ':' + ('0' + (s % 60)).slice(-2);
    };
    tick();
    clock = setInterval(tick, 1000);
  }

  /** camera + mic; falls back to mic only, then to receive-only, so a missing camera never blocks a call */
  function getMedia() {
    // the browser's permission prompt can sit unnoticed: say what we're waiting for
    var hint = setTimeout(function () { if (ui.status && active) { setStatus('Allow camera & microphone in your browser’s prompt…'); } }, 1500);
    return navigator.mediaDevices.getUserMedia({ audio: true, video: { width: { ideal: 1280 }, height: { ideal: 720 } } })
      .catch(function () { return navigator.mediaDevices.getUserMedia({ audio: true, video: false }); })
      .catch(function () { return null; })
      .then(function (s) {
        clearTimeout(hint);
        localStream = s;
        if (!ui.local) { return s; }
        if (s) {
          ui.local.srcObject = s;
          if (!s.getVideoTracks().length) { ui.local.hidden = true; ui.cam.hidden = true; }
        } else {
          ui.local.hidden = true; ui.cam.hidden = true; ui.mic.hidden = true;
        }
        return s;
      });
  }

  /* ------------------------------------------------------------------ peers (one connection per other person) */

  /** a tile per person in the call (their avatar until video arrives); the big "Calling…" card only while alone */
  function layoutTiles() {
    var n = Object.keys(peers).length;
    ui.grid.dataset.n = String(n);
    ui.peer.hidden = n > 0;
    ui.grid.hidden = n === 0;
  }

  /** lower each video send rate as the call grows (everyone uploads to everyone in a mesh) */
  function tuneBitrate() {
    var n = Object.keys(peers).length;
    var max = n <= 1 ? 1500000 : n === 2 ? 800000 : 500000;
    Object.keys(peers).forEach(function (id) {
      var pc = peers[id].pc;
      pc.getSenders().forEach(function (s) {
        if (!s.track || s.track.kind !== 'video' || !s.getParameters) { return; }
        var p = s.getParameters();
        if (!p.encodings || !p.encodings.length) { p.encodings = [{}]; }
        p.encodings[0].maxBitrate = max;
        s.setParameters(p).catch(function () { /* not supported everywhere */ });
      });
    });
  }

  function sendSignal(to, kind, data) {
    if (!active) { return Promise.resolve(); }
    return api({ action: 'signal', id: active.id, to: to, kind: kind, payload: JSON.stringify(data && data.toJSON ? data.toJSON() : data) }, true)
      .catch(function () { /* the other side retries on its own schedule */ });
  }

  function createPeer(member) {
    var id = member.id;
    var p = { pc: new RTCPeerConnection({ iceServers: iceServers }), ready: false, pendingIce: [], queue: Promise.resolve(), connected: false };
    p.tile = h('div', 'wdc-tile');
    p.video = document.createElement('video');
    p.video.autoplay = true; p.video.playsInline = true;
    p.tile.appendChild(avatar(member, 'wdc-av--tile'));
    p.tile.appendChild(p.video);
    p.tile.appendChild(h('div', 'wdc-tile__name', member.name));
    ui.grid.appendChild(p.tile);

    if (localStream) { localStream.getTracks().forEach(function (t) { p.pc.addTrack(t, localStream); }); }
    else { p.pc.addTransceiver('audio', { direction: 'recvonly' }); p.pc.addTransceiver('video', { direction: 'recvonly' }); }
    p.pc.ontrack = function (e) {
      if (p.video.srcObject !== e.streams[0]) { p.video.srcObject = e.streams[0]; }
      if (e.track.kind === 'video') { p.tile.classList.add('has-video'); }
    };
    p.pc.onicecandidate = function (e) { if (e.candidate) { sendSignal(id, 'ice', e.candidate); } };
    p.pc.onconnectionstatechange = function () {
      if (closing || !peers[id]) { return; }
      var s = p.pc.connectionState;
      if (s === 'connected') {
        p.connected = true;
        p.tile.classList.remove('is-reconnecting');
        stopRingtone();
        if (!active.everConnected) { active.everConnected = true; startClock(active.call.connectedAgo); }
        setStatus('');
        tuneBitrate();
      } else if (s === 'disconnected') {
        p.tile.classList.add('is-reconnecting');
      } else if (s === 'failed') {
        p.tile.classList.add('is-reconnecting');
        if (Object.keys(peers).length === 1) {
          setStatus('Couldn’t connect — your network may be blocking calls.', true);
          ui.peer.hidden = false; ui.grid.hidden = true;
        }
      }
      layoutTiles();
    };
    peers[id] = p;
    layoutTiles();
    tuneBitrate();
    return p;
  }

  function closePeer(id) {
    var p = peers[id];
    if (!p) { return; }
    try { p.pc.close(); } catch (e) { /* ignore */ }
    p.tile.remove();
    delete peers[id];
    layoutTiles();
  }

  function flushIce(p) { return Promise.all(p.pendingIce.splice(0).map(function (c) { return p.pc.addIceCandidate(c).catch(function () {}); })); }

  /** signals per person, strictly in order */
  function handleSignal(s) {
    var p = peers[s.from];
    if (!p) { return; }   // they'll show as joined on this same poll; created before signals are handled
    p.queue = p.queue.then(function () {
      if (!peers[s.from] || closing) { return; }
      if (s.kind === 'offer') {
        return p.pc.setRemoteDescription(s.data).then(function () { p.ready = true; return flushIce(p); })
          .then(function () { return p.pc.createAnswer(); })
          .then(function (a) { return p.pc.setLocalDescription(a); })
          .then(function () { return sendSignal(s.from, 'answer', p.pc.localDescription); });
      }
      if (s.kind === 'answer') {
        return p.pc.setRemoteDescription(s.data).then(function () { p.ready = true; return flushIce(p); });
      }
      if (s.kind === 'ice') {
        if (p.ready) { return p.pc.addIceCandidate(s.data).catch(function () {}); }
        p.pendingIce.push(s.data);
      }
    }).catch(function () { p.tile.classList.add('is-reconnecting'); });
  }

  /** make the set of connections match who is in the call */
  function syncPeers(call) {
    var joined = call.members.filter(function (m) { return m.state === 'joined' && m.id !== ME; });
    var ids = joined.map(function (m) { return m.id; });
    Object.keys(peers).forEach(function (id) { if (ids.indexOf(id) === -1) { closePeer(id); } });
    joined.forEach(function (m) {
      if (peers[m.id]) { return; }
      var p = createPeer(m);
      if (ME < m.id) {          // the one whose ID sorts first offers
        p.queue = p.queue.then(function () { return p.pc.createOffer(); })
          .then(function (o) { return p.pc.setLocalDescription(o); })
          .then(function () { return sendSignal(m.id, 'offer', p.pc.localDescription); })
          .catch(function () { p.tile.classList.add('is-reconnecting'); });
      }
    });
    if (call.group) {
      ui.count.textContent = (joined.length + 1) + ' / ' + call.max + ' in call';
      ui.count.hidden = false;
    }
    if (!joined.length) {
      var ringing = call.members.some(function (m) { return m.state === 'invited'; });
      setStatus(active.everConnected ? (ringing ? 'Waiting for others to join…' : 'Everyone else left') : (call.group ? 'Calling the group…' : 'Calling…'));
    } else if (!active.everConnected) {
      setStatus('Connecting…');
    }
  }

  var END_TEXT = { declined: 'Call declined', missed: 'No answer', cancelled: 'Call cancelled', ended: 'Call ended' };

  function pollCall() {
    if (!active || closing) { return; }
    var id = active.id;
    api({ action: 'state', id: id, after: lastSig }).then(function (j) {
      if (!active || active.id !== id || closing) { return; }
      active.call = j.call;
      if (j.call.status === 'ended') {
        var t = END_TEXT[j.call.reason] || 'Call ended';
        if (j.call.group && j.call.reason === 'missed') { t = 'No one answered'; }
        finish(t);
        return;
      }
      if (j.call.status === 'active') { stopRingtone(); }
      syncPeers(j.call);
      j.signals.forEach(function (s) { lastSig = Math.max(lastSig, s.id); handleSignal(s); });
      callTimer = setTimeout(pollCall, active.everConnected ? LIVE_POLL_MS : SETUP_POLL_MS);
    }).catch(function (e) {
      if (!active) { return; }
      if (e.http === 404) { finish('Call ended'); return; }
      callTimer = setTimeout(pollCall, LIVE_POLL_MS);   // network blip: keep trying
    });
  }

  /** open the window for a call; $joinFirst = false when I'm already in it (I started it) */
  function beginCall(call, servers, alreadyIn) {
    if (active) { return; }
    closing = false; lastSig = 0; peers = {};
    active = { id: call.id, call: call, everConnected: false };
    buildWindow(call);
    setStatus(alreadyIn ? (call.group ? 'Calling the group…' : 'Calling…') : 'Joining…');
    if (servers) { iceServers = servers; }
    getMedia().then(function () {
      if (!active || closing) { return; }
      if (alreadyIn) { pollCall(); return; }
      return api({ action: 'join', id: call.id }, true).then(function (j) {
        iceServers = j.iceServers;
        active.call = j.call;
        pollCall();
      });
    }).catch(function (e) {
      finish(e.code === 'full' ? 'This call is full (4 people max).' : (e.message || 'Couldn’t join the call.'), true);
    });
  }

  function finish(text, bad) {
    if (!active || closing) { return; }
    closing = true;
    clearTimeout(callTimer);
    stopRingtone();
    if (clock) { clearInterval(clock); clock = null; }
    Object.keys(peers).forEach(closePeer);
    if (localStream) { localStream.getTracks().forEach(function (t) { t.stop(); }); localStream = null; }
    ui.grid.hidden = true; ui.peer.hidden = false; ui.local.hidden = true;
    setStatus(text, bad);
    setTimeout(function () {
      if (win) { win.remove(); win = null; }
      active = null; closing = false; ui = {};
      document.dispatchEvent(new CustomEvent('wdcall:ended'));
    }, bad ? 2600 : 1500);
  }

  function hangUp() {
    if (!active) { return; }
    api({ action: 'leave', id: active.id }, true).catch(function () { /* the others notice within seconds anyway */ });
    finish(active.everConnected ? 'You left the call' : 'Call cancelled');
  }

  /* leaving the page mid-call: leave cleanly so nobody is left waiting */
  window.addEventListener('pagehide', function () {
    if (!active || !navigator.sendBeacon) { return; }
    var fd = new FormData();
    fd.append('action', 'leave'); fd.append('id', active.id); fd.append('token', CFG.token);
    navigator.sendBeacon(CFG.api + '?action=leave', fd);
  });
  window.addEventListener('beforeunload', function (e) {
    if (active && !closing) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ------------------------------------------------------------------ public */
  function preflight() {
    if (!canRTC) { alert('Video calls need a newer browser (Chrome, Edge, Firefox or Safari).'); return false; }
    if (!secure) { alert('Video calls only work on a secure (https://) address.'); return false; }
    if (active) { setMini(false); return false; }
    askNotifyPermission();
    return true;
  }

  function startWith(withKey, label) {
    if (!preflight()) { return; }
    api({ action: 'start', with: withKey }, true).then(function (j) {
      beginCall(j.call, j.iceServers, true);
      startRingtone('outgoing');
    }).catch(function (e) {
      if (e.code === 'disabled') { setEnabled(false); }
      if (e.code === 'busy') { alert(withKey.indexOf('grp:') === 0 ? e.message : label + ' is on another call right now. Try again in a bit.'); }
      else { alert(e.message); }
    });
  }

  window.WeDoCall = {
    available: canRTC && secure,
    enabled: function () { return enabled === true; },   // calls set up on this server
    inCall: function () { return !!active; },
    start: function (empId, person) { startWith(empId, (person && person.name) || empId); },
    startGroup: function (key, group) { startWith(key, (group && group.name) || 'The group'); },
    join: function (callId, group) {
      if (!preflight()) { return; }
      beginCall({ id: callId, members: [], group: { name: (group && group.name) || 'Group call' }, starter: { name: '' }, max: 4 }, null, false);
    }
  };
  document.dispatchEvent(new CustomEvent('wdcall:ready'));

  // first check right away, then every few seconds
  pollIncoming().then(ringerLoop, ringerLoop);
})();
