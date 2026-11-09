/* ==========================================================================
   subscriptions.js  —  screen logic for subscriptions.php. The overview
   answers first (headline, tiles, needs attention, PhilGEPS, 12-month cost),
   then the register by category and the Reminders tab. One side panel shows
   an item or a form (content swapped, nothing stacks). Data comes from
   query/subscriptions-action.php; every value goes through esc().
   ========================================================================== */
(function () {
  'use strict';

  var L = window.LCY, esc = L.esc, BOOT = L.boot;
  var look = null;          // lookups: categories, cycles, people, companies, ...
  var data = null;          // the last overview
  var tab = 'all';
  var company = '';         // '' = every company (super users), else a CompanyID
  var pickedMonth = null;   // timeline month shown under the bars
  var openId = 0;           // item open in the panel

  var ICON = { domain: 'fa-globe', hosting: 'fa-server', security: 'fa-shield-halved', philgeps: 'fa-landmark' };

  function $id(id) { return document.getElementById(id); }
  function each(box, sel, fn) { Array.prototype.forEach.call(box.querySelectorAll(sel), fn); }
  function empty(msg) { return '<div class="lcy-empty">' + esc(msg) + '</div>'; }
  function money(n, dec) {
    if (n === null || n === undefined || n === '') { return ''; }
    return '₱' + Number(n).toLocaleString(undefined, { minimumFractionDigits: dec === 0 ? 0 : 2, maximumFractionDigits: dec === 0 ? 0 : 2 });
  }
  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
  function pill(tone, text) { return '<span class="wd-pill wd-pill--' + tone + '">' + esc(text) + '</span>'; }

  /** "in 5 days", "today", "3 days ago" */
  function rel(days) {
    if (days === null || days === undefined) { return 'no date'; }
    if (days === 0) { return 'today'; }
    if (days === 1) { return 'tomorrow'; }
    if (days === -1) { return 'yesterday'; }
    return days > 0 ? 'in ' + days + ' days' : (-days) + ' days ago';
  }
  /** The days-left pill used everywhere. */
  function duePill(tone, days) {
    switch (tone) {
      case 'expired':   return pill('danger', days === -1 ? 'Expired yesterday' : 'Expired ' + (-days) + 'd ago');
      case 'urgent':    return pill('warn', days === 0 ? 'Expires today' : plural(days, 'day') + ' left');
      case 'soon':      return pill('info', plural(days, 'day') + ' left');
      case 'ok':        return pill('ok', plural(days, 'day') + ' left');
      case 'cancelled': return pill('info', 'Cancelled');
      default:          return pill('info', 'No expiry date');
    }
  }
  function catIcon(c) { return '<i class="fa-solid ' + (ICON[c] || 'fa-circle') + '" aria-hidden="true"></i>'; }
  function personName(id) {
    var p = (look.people || []).filter(function (x) { return x.id === id; })[0];
    return p ? p.name : '';
  }

  /* ------------------------------------------------------------ loading */
  function loadLookups() {
    return L.post('lookups', {}).then(function (res) {
      if (res.status !== 'ok') { $id('subAnswer').innerHTML = empty(res.msg); return false; }
      look = res;
      var sel = $id('subCompany');
      if (look.allCompanies && look.companies.length > 1) {
        sel.innerHTML = '<option value="all">All companies</option>' + look.companies.map(function (c) {
          return '<option value="' + esc(c.id) + '">' + esc(c.name) + '</option>';
        }).join('');
        company = look.myCompany || 'all';
        try { company = localStorage.getItem('wd-sub-company') || company; } catch (e) { /* private window */ }
        if (!look.companies.some(function (c) { return c.id === company; })) { company = 'all'; }
        sel.value = company;
        sel.classList.remove('lcy-hidden');
      } else {
        company = look.myCompany;
      }
      return true;
    });
  }

  function load() {
    return L.post('overview', { company: company }).then(function (res) {
      if (res.status !== 'ok') { $id('subAnswer').innerHTML = empty(res.msg); return; }
      data = res;
      drawAnswer();
      drawStats();
      drawAttention();
      drawPhilgeps();
      drawTimeline();
      drawList();
    });
  }

  /* ------------------------------------------------------------ the answer */
  function drawAnswer() {
    var s = data.stats, a = data.attention, now = s.expired + s.due7, head, tone;
    if (!data.items.length) {
      head = 'Nothing is tracked yet.';
      tone = 'info';
    } else if (now > 0) {
      head = plural(now, 'renewal needs', 'renewals need') + ' action this week' + (s.expired ? ', ' + s.expired + ' already expired' : '') + '.';
      tone = s.expired ? 'danger' : 'warn';
    } else if (a.length) {
      head = plural(a.length, 'renewal is', 'renewals are') + ' coming up. Nothing is urgent this week.';
      tone = 'info';
    } else {
      head = 'Everything is current. Nothing needs renewing in the next 30 days.';
      tone = 'ok';
    }
    var bits = [];
    if (s.due30Cost > 0) { bits.push(money(s.due30Cost, 0) + ' is due in the next 30 days'); }
    if (data.next) { bits.push('next up: ' + data.next.label + ' on ' + L.fmtDate(data.next.expiresOn)); }
    if (data.philgeps.length) {
      var p = data.philgeps[0];
      bits.push(p.canBid ? 'PhilGEPS: eligible to bid' + (p.until ? ' until ' + L.fmtDate(p.until) : '') : 'PhilGEPS: not eligible to bid right now');
    }
    if (s.noDate) { bits.push(plural(s.noDate, 'item has', 'items have') + ' no expiry date, so no reminders'); }
    var sub = bits.length ? bits.join(' · ') : '';
    $id('subAnswer').className = 'sub-answer sub-answer--' + tone;
    $id('subAnswer').innerHTML = '<p class="sub-answer__date">' + esc(new Date(BOOT.today + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }))
      + '</p><h2 class="sub-answer__head">' + esc(head) + '</h2>'
      + (sub ? '<p class="sub-answer__sub">' + esc(sub.charAt(0).toUpperCase() + sub.slice(1)) + '.</p>' : '')
      + (!data.items.length ? '<p class="sub-answer__sub">Add your domains, hosting, security services and PhilGEPS registration. Reminders start on their own.</p>' : '');
  }

  function drawStats() {
    var s = data.stats;
    function tile(label, value, icon, tone, filter, title) {
      return '<button type="button" class="wd-stat sub-stat sub-stat--' + tone + '" data-filter="' + filter + '" title="' + esc(title) + '">'
        + '<div class="wd-stat__label"><i class="fa-solid ' + icon + '"></i> ' + esc(label) + '</div><div class="wd-stat__value">' + esc(value) + '</div></button>';
    }
    $id('subStats').innerHTML = tile('Expired', s.expired, 'fa-circle-xmark', s.expired ? 'danger' : 'calm', 'expired', 'Past their expiry date')
      + tile('Due in 7 days', s.due7, 'fa-hourglass-half', s.due7 ? 'warn' : 'calm', 'due7', 'Expire within a week')
      + tile('Due in 30 days', s.due30, 'fa-calendar-day', 'calm', 'due30', 'Expire within 30 days, including this week')
      + tile('Auto-renew off', s.autoOff, 'fa-hand', 'calm', 'autooff', 'Someone has to renew these by hand');
    each($id('subStats'), '[data-filter]', function (b) {
      b.addEventListener('click', function () { setTab('all'); filterBy = b.getAttribute('data-filter'); drawList(); $id('subPaneList').scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    });
  }

  function drawAttention() {
    var list = data.attention;
    $id('subAttnNote').textContent = list.length ? 'Reminders go out daily for these' : '';
    if (!list.length) {
      $id('subAttention').innerHTML = '<div class="sub-allclear"><i class="fa-solid fa-circle-check"></i><div><b>All clear</b><br><span class="lcy-muted">'
        + (data.next ? 'Next renewal: ' + esc(data.next.label) + ' on ' + esc(L.fmtDate(data.next.expiresOn)) + '.' : 'Nothing is inside its reminder window.') + '</span></div></div>';
      return;
    }
    var items = {};
    data.items.forEach(function (i) { items[i.id] = i; });
    $id('subAttention').innerHTML = '<ul class="sub-rows">' + list.map(function (e) {
      var i = items[e.itemId] || {};
      var meta = [e.categoryLabel];
      if (!e.reqId && i.provider) { meta.push(i.provider); }
      if (!e.reqId) { meta.push(e.autoRenew ? 'auto-renews' : 'auto-renew off'); }
      if (e.owner) { meta.push('owner ' + e.owner); } else { meta.push('no owner'); }
      return '<li class="sub-row sub-row--' + e.tone + '" data-open="' + e.itemId + '">'
        + '<span class="sub-row__icon">' + catIcon(e.category) + '</span>'
        + '<div class="sub-row__main"><b>' + esc(e.label) + '</b><span class="lcy-muted">' + esc(meta.join(' · ')) + '</span>'
        + (e.impact ? '<span class="sub-row__impact"><i class="fa-solid fa-triangle-exclamation"></i> If it lapses: ' + esc(e.impact) + '</span>' : '') + '</div>'
        + '<div class="sub-row__end">' + duePill(e.tone, e.days)
        + '<span class="sub-row__date">' + esc(L.fmtDate(e.expiresOn)) + '</span>'
        + '<span class="sub-row__btns">'
        + (!e.reqId && i.renewUrl ? '<a class="wd-btn wd-btn--ghost wd-btn--sm" href="' + esc(i.renewUrl) + '" target="_blank" rel="noopener" data-stop title="Open the renewal page"><i class="fa-solid fa-arrow-up-right-from-square"></i><span class="sub-hide-sm"> Renew</span></a>' : '')
        + '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-renew="' + e.itemId + '" data-req="' + (e.reqId || '') + '" data-stop><i class="fa-solid fa-check"></i> Mark renewed</button>'
        + '</span></div></li>';
    }).join('') + '</ul>';
    wireRows($id('subAttention'));
  }

  function wireRows(box) {
    each(box, '[data-open]', function (r) {
      r.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-stop]')) { return; }
        openItem(+r.getAttribute('data-open'));
      });
    });
    each(box, '[data-renew]', function (b) {
      b.addEventListener('click', function () { renewForm(+b.getAttribute('data-renew'), +b.getAttribute('data-req') || 0); });
    });
  }

  function drawPhilgeps() {
    var list = data.philgeps, box = $id('subPhilgeps');
    if (!list.length) {
      box.innerHTML = '<div class="sub-pg sub-pg--none"><p class="lcy-muted">No PhilGEPS registration recorded yet. Add it with its documents to see if you can bid.</p>'
        + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="subPgAdd"><i class="fa-solid fa-plus"></i> Add PhilGEPS</button></div>';
      $id('subPgAdd').addEventListener('click', function () { itemForm(null, 'philgeps'); });
      return;
    }
    box.innerHTML = list.map(function (p) {
      var why;
      if (p.canBid) {
        why = p.until ? 'Until ' + L.fmtDate(p.until) + (p.limitedBy ? ', limited by ' + p.limitedBy + '.' : '.') : 'No expiry dates recorded yet.';
      } else if (p.expired.length) {
        why = 'Expired: ' + p.expired.join(', ') + '.';
      } else {
        why = 'The PhilGEPS certificate has expired.';
      }
      var lines = [p.current + ' of ' + p.total + ' documents current'];
      if (p.next) { lines.push(p.next.name + ' due ' + L.fmtDate(p.next.date)); }
      if (p.certMissing) { lines.push('certificate expiry not entered'); }
      return '<div class="sub-pg" data-open="' + p.id + '" role="button" tabindex="0">'
        + '<div class="sub-pg__verdict sub-pg__verdict--' + (p.canBid ? 'yes' : 'no') + '">' + (p.canBid ? 'Yes' : 'No') + '</div>'
        + '<div class="sub-pg__text">' + (list.length > 1 || company === 'all' ? '<b>' + esc(p.name) + (p.company ? ' · ' + esc(p.company) : '') + '</b><br>' : '')
        + '<span>' + esc(why) + '</span><br><span class="lcy-muted">' + esc(lines.join(' · ')) + '</span></div></div>';
    }).join('');
    wireRows(box);
  }

  function drawTimeline() {
    var t = data.timeline, months = t.months;
    var max = Math.max.apply(null, months.map(function (m) { return m.amount; }).concat([1]));
    var noCost = data.items.filter(function (i) { return i.status === 'active' && i.expiresOn && i.cost === null; }).length;
    $id('subCostNote').textContent = t.total > 0 ? money(t.total, 0) + ' total' : '';
    if (pickedMonth === null || !months.some(function (m) { return m.ym === pickedMonth; })) {
      pickedMonth = (months.filter(function (m) { return m.count > 0; })[0] || months[0]).ym;
    }
    var busiest = t.busiest ? months.filter(function (m) { return m.ym === t.busiest; })[0] : null;
    var html = '<div class="sub-bars" role="list">' + months.map(function (m, k) {
      var h = m.amount > 0 ? Math.max(6, Math.round(m.amount / max * 100)) : (m.count ? 6 : 2);
      return '<button type="button" role="listitem" class="sub-bar' + (k === 0 ? ' is-now' : '') + (m.ym === pickedMonth ? ' is-picked' : '') + (m.count ? '' : ' is-empty') + '" data-ym="' + m.ym + '"'
        + ' title="' + esc(m.label + ' ' + m.year + ': ' + (m.count ? money(m.amount, 0) + ', ' + plural(m.count, 'renewal') : 'nothing due')) + '">'
        + '<span class="sub-bar__fill" style="height:' + h + '%"></span><span class="sub-bar__lbl">' + esc(m.label) + '</span></button>';
    }).join('') + '</div>';
    var pm = months.filter(function (m) { return m.ym === pickedMonth; })[0];
    html += '<div class="sub-month">';
    if (pm && pm.count) {
      html += '<div class="sub-month__head"><b>' + esc(pm.label + ' ' + pm.year) + '</b><span>' + esc(money(pm.amount, 0)) + '</span></div><ul>'
        + pm.items.map(function (x) {
          return '<li data-open="' + x.id + '"><span>' + esc(x.name) + ' <span class="lcy-muted">' + esc(L.fmtDate(x.date)) + '</span></span><span>' + esc(x.cost === null ? 'no cost' : money(x.cost, 0)) + '</span></li>';
        }).join('') + '</ul>';
    } else if (pm) {
      html += '<p class="lcy-muted">Nothing renews in ' + esc(pm.label + ' ' + pm.year) + '.</p>';
    }
    if (busiest && t.total > 0) { html += '<p class="lcy-muted sub-month__note">Busiest month: ' + esc(busiest.label + ' ' + busiest.year) + ' (' + esc(money(busiest.amount, 0)) + ').'
      + (noCost ? ' ' + plural(noCost, 'item has', 'items have') + ' no cost entered.' : '') + '</p>'; }
    html += '</div>';
    $id('subTimeline').innerHTML = html;
    each($id('subTimeline'), '[data-ym]', function (b) {
      b.addEventListener('click', function () { pickedMonth = b.getAttribute('data-ym'); drawTimeline(); });
    });
    wireRows($id('subTimeline'));
  }

  /* ------------------------------------------------------------ the register */
  var filterBy = '';    // a tile's filter: expired | due7 | due30 | autooff

  function setTab(t) {
    tab = t;
    each($id('subTabs'), '.lcy-tab', function (b) { b.classList.toggle('is-on', b.getAttribute('data-tab') === t); });
    $id('subPaneList').classList.toggle('lcy-hidden', t === 'reminders');
    $id('subPaneReminders').classList.toggle('lcy-hidden', t !== 'reminders');
    if (t === 'reminders') { loadReminders(); } else { filterBy = ''; drawList(); }
  }

  function drawList() {
    if (!data) { return; }
    var q = $id('subQ').value.trim().toLowerCase(), showCancelled = $id('subShowCancelled').checked;
    var rows = data.items.filter(function (i) {
      if (tab !== 'all' && i.category !== tab) { return false; }
      if (!showCancelled && i.status === 'cancelled') { return false; }
      if (q && (i.name + ' ' + i.provider + ' ' + i.accountRef + ' ' + i.plan + ' ' + i.owner).toLowerCase().indexOf(q) < 0) { return false; }
      if (filterBy === 'expired') { return i.tone === 'expired' || (i.eligibility && i.eligibility.expired.length); }
      if (filterBy === 'due7') { return i.days !== null && i.days >= 0 && i.days <= 7; }
      if (filterBy === 'due30') { return i.days !== null && i.days >= 0 && i.days <= 30; }
      if (filterBy === 'autooff') { return i.status === 'active' && !i.autoRenew && i.category !== 'philgeps' && i.cycle > 0; }
      return true;
    });
    var box = $id('subList');
    var chip = filterBy ? '<div class="sub-filterchip">Showing: ' + esc({ expired: 'expired', due7: 'due in 7 days', due30: 'due in 30 days', autooff: 'auto-renew off' }[filterBy])
      + ' <button type="button" class="sub-x" id="subClearFilter" aria-label="Clear filter">&times;</button></div>' : '';
    if (!rows.length) {
      box.innerHTML = chip + (data.items.length ? empty(filterBy || q ? 'Nothing matches.' : 'Nothing in this category yet.')
        : '<div class="lcy-empty">Start with your main domain: add it, its hosting and the security service on top.<br><br><button type="button" class="wd-btn wd-btn--primary" id="subFirst"><i class="fa-solid fa-plus"></i> Add your first subscription</button></div>');
      if ($id('subFirst')) { $id('subFirst').addEventListener('click', function () { itemForm(null, tab !== 'all' ? tab : 'domain'); }); }
    } else {
      var showCo = company === 'all';
      box.innerHTML = chip + '<div class="wd-tablewrap"><table class="wd-table lcy-table sub-table"><thead><tr><th>Name</th><th>Kind</th>' + (showCo ? '<th>Company</th>' : '')
        + '<th>Expires</th><th>Cost</th><th>Renewal</th><th>Owner</th></tr></thead><tbody>' + rows.map(function (i) {
          var exp = i.expiresOn ? esc(L.fmtDate(i.expiresOn)) : '<span class="lcy-muted">not set</span>';
          var extra = i.eligibility ? '<br>' + (i.eligibility.canBid ? pill('ok', 'Can bid') : pill('danger', 'Cannot bid')) : '';
          return '<tr class="sub-tr" data-open="' + i.id + '">'
            + '<td data-label="Name"><span class="sub-name">' + catIcon(i.category) + ' <b>' + esc(i.name) + '</b></span>'
            + (i.provider || i.plan ? '<br><span class="lcy-muted">' + esc([i.provider, i.plan].filter(Boolean).join(' · ')) + '</span>' : '') + '</td>'
            + '<td data-label="Kind">' + esc(i.categoryLabel) + '</td>'
            + (showCo ? '<td data-label="Company">' + esc(i.company) + '</td>' : '')
            + '<td data-label="Expires">' + exp + '<br>' + duePill(i.tone, i.days) + extra + '</td>'
            + '<td data-label="Cost">' + (i.cost !== null ? esc(money(i.cost)) + '<br><span class="lcy-muted">' + esc(i.cycleLabel.toLowerCase()) + '</span>' : '<span class="lcy-muted">—</span>') + '</td>'
            + '<td data-label="Renewal">' + (i.category === 'philgeps' ? '<span class="lcy-muted">manual</span>' : (i.autoRenew ? '<i class="fa-solid fa-rotate"></i> Auto' : '<span class="sub-manual">Manual</span>'))
            + (i.paymentNote ? '<br><span class="lcy-muted">' + esc(i.paymentNote) + '</span>' : '') + '</td>'
            + '<td data-label="Owner">' + (i.owner ? esc(i.owner) : '<span class="sub-manual">No owner</span>') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }
    if ($id('subClearFilter')) { $id('subClearFilter').addEventListener('click', function () { filterBy = ''; drawList(); }); }
    wireRows(box);
  }

  /* ------------------------------------------------------------ reminders tab */
  function loadReminders() {
    var box = $id('subReminders');
    box.innerHTML = empty('Loading…');
    L.post('reminders', {}).then(function (res) {
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      var m = res.mine, html = '<div class="sub-remind">';
      html += '<div class="sub-remind__how"><h3>How reminders work</h3><ul>'
        + '<li>Every day from ' + res.runHour + ':00 AM, each person gets <b>one</b> phone notification listing what needs renewing.</li>'
        + '<li>They start ' + look.notifyDefault + ' days before expiry (' + look.notifyPhilgeps + ' for PhilGEPS documents) and repeat daily until someone taps <b>Mark renewed</b> or the item is cancelled.</li>'
        + '<li>The owner and watchers get them. Once something has expired, everyone with Subscriptions access in that company gets them too.</li>'
        + '<li>On the website the reminder shows on the bell, the dashboard, and as a desktop pop-up once a day.</li></ul></div>';
      html += '<div class="sub-remind__mine"><h3>Your reminder today</h3>' + (m.entries.length
        ? '<div class="sub-notif"><i class="fa-solid fa-bell"></i><div><b>' + esc(m.title) + '</b><br><span>' + esc(m.body) + '</span></div></div>'
        : '<p class="lcy-muted">Nothing for you today: you own or watch nothing inside its reminder window.</p>')
        + '<p class="lcy-muted" id="subDeskState"></p></div></div>';
      html += '<div class="wd-card__head sub-subhead"><h3>Sent in the last 14 days</h3></div>';
      if (!res.runs.length) {
        html += empty('No reminders have gone out yet. The first run happens at ' + res.runHour + ':00 AM (or on the first page load after it).');
      } else {
        var byDay = {};
        res.sent.forEach(function (s) { (byDay[s.day] = byDay[s.day] || []).push(s); });
        html += '<div class="wd-tablewrap"><table class="wd-table lcy-table"><thead><tr><th>Day</th><th>Sent by</th><th>People</th><th>Who got what</th></tr></thead><tbody>'
          + res.runs.map(function (r) {
            var who = (byDay[r.day] || []).map(function (s) { return '<div class="sub-sent"><b>' + esc(s.emp) + '</b>: ' + esc(s.title) + '</div>'; }).join('');
            return '<tr><td data-label="Day">' + esc(L.fmtDate(r.day)) + '<br><span class="lcy-muted">' + esc((r.startedAt || '').slice(11, 16)) + '</span></td>'
              + '<td data-label="Sent by">' + (r.source === 'cron' ? 'Scheduled job' : 'Page-load fallback') + (r.finishedAt ? '' : '<br><span class="lcy-muted">did not finish</span>') + '</td>'
              + '<td data-label="People">' + r.people + '</td><td data-label="Who got what">' + (who || '<span class="lcy-muted">nobody needed one</span>') + '</td></tr>';
          }).join('') + '</tbody></table></div>';
      }
      box.innerHTML = html;
      deskState();
    });
  }

  /** Desktop pop-ups need the browser's permission: say where it stands, offer to turn it on. */
  function deskState() {
    var el = $id('subDeskState');
    if (!el) { return; }
    if (!('Notification' in window)) { el.textContent = 'This browser cannot show desktop pop-ups.'; return; }
    if (Notification.permission === 'granted') { el.innerHTML = '<i class="fa-solid fa-desktop"></i> Desktop pop-ups are on for this browser.'; return; }
    if (Notification.permission === 'denied') { el.textContent = 'Desktop pop-ups are blocked for this site in your browser settings.'; return; }
    el.innerHTML = '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="subDeskOn"><i class="fa-solid fa-desktop"></i> Turn on desktop pop-ups</button>';
    $id('subDeskOn').addEventListener('click', function () { Notification.requestPermission().then(deskState); });
  }

  /* ------------------------------------------------------------ the panel */
  function showPanel(title, body, foot, locked) {
    $id('subModalTitle').textContent = title;
    $id('subModalBody').innerHTML = body;
    $id('subModalBody').scrollTop = 0;
    $id('subModalFoot').innerHTML = foot;
    $('#subModal').modal('show');
    var bs = $('#subModal').data('bs.modal');
    bs.options.backdrop = locked ? 'static' : true;
    bs.options.keyboard = !locked;
  }
  var plainClose = false;   // our own close: nothing to step back through, nothing to ask
  var formBack = null;      // the open form's way back (fn), or null = close
  var formStart = '';       // what the open form held when it opened
  function closePanel() { openId = 0; plainClose = true; $('#subModal').modal('hide'); }

  function fact(label, value) { return value ? '<div><dt>' + esc(label) + '</dt><dd>' + value + '</dd></div>' : ''; }

  function openItem(id) {
    openId = id;
    L.post('item', { id: id }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      var i = res.item, h = '';
      var big;
      if (i.status === 'cancelled') { big = '<div class="sub-count sub-count--cancelled"><b>Cancelled</b><span>No reminders. Resume tracking to renew it.</span></div>'; }
      else if (i.days === null) { big = '<div class="sub-count sub-count--none"><b>No expiry date</b><span>Add one so reminders can start.</span></div>'; }
      else {
        var n = i.days < 0 ? -i.days : i.days;
        big = '<div class="sub-count sub-count--' + i.tone + '"><b>' + (i.days === 0 ? 'Today' : n) + '</b><span>'
          + (i.days === 0 ? 'expires today' : (n === 1 ? 'day' : 'days') + (i.days < 0 ? ' since it expired' : ' left'))
          + '<br>' + esc(L.fmtDate(i.expiresOn)) + (i.cycle ? ' · ' + esc(i.cycleLabel.toLowerCase()) : '') + '</span></div>';
      }
      h += '<div class="sub-top">' + big + '<div class="sub-top__btns">'
        + (i.status === 'active' ? '<button type="button" class="wd-btn wd-btn--primary" data-act="renew"><i class="fa-solid fa-check"></i> Mark renewed</button>' : '<button type="button" class="wd-btn wd-btn--primary" data-act="resume"><i class="fa-solid fa-rotate-left"></i> Resume tracking</button>')
        + (i.renewUrl ? '<a class="wd-btn wd-btn--ghost" href="' + esc(i.renewUrl) + '" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Renewal page</a>' : '')
        + '</div></div>';

      if (i.eligibility) {
        var el = i.eligibility;
        h += '<div class="sub-elig sub-elig--' + (el.canBid ? 'yes' : 'no') + '"><b>' + (el.canBid ? 'Eligible to bid' : 'Not eligible to bid') + '</b> '
          + esc(el.canBid ? (el.until ? 'until ' + L.fmtDate(el.until) + (el.limitedBy ? ' (limited by ' + el.limitedBy + ')' : '') : '— no expiry dates entered yet')
            : (el.expired.length ? 'expired: ' + el.expired.join(', ') : 'the certificate has expired')) + '</div>';
      }

      // if it lapses
      var lapse = '';
      if (i.impact) { lapse += '<p>' + esc(i.impact) + '</p>'; }
      if (i.children.length) { lapse += '<p class="lcy-muted">Needed by: ' + i.children.map(function (c) { return '<a href="#" data-open="' + c.id + '">' + esc(c.name) + '</a>'; }).join(', ') + '</p>'; }
      if (i.parent) { lapse += '<p class="lcy-muted">Depends on: <a href="#" data-open="' + i.parentId + '">' + esc(i.parent) + '</a></p>'; }
      if (lapse) { h += '<h4 class="sub-h">If it lapses</h4><div class="sub-lapse">' + lapse + '</div>'; }

      // reminders
      var r = i.reminders, rem;
      if (i.status !== 'active') { rem = 'No reminders while it is cancelled.'; }
      else if (!i.expiresOn) { rem = 'No reminders: there is no expiry date.'; }
      else if (!r.people.length) { rem = 'Nobody will be reminded until it expires — choose an owner.'; }
      else {
        rem = (r.active ? 'Reminding ' : 'Daily reminders from ' + L.fmtDate(r.from) + ' to ') + r.people.join(', ') + (r.active ? ' every day until it is marked renewed.' : '.')
          + ' ' + (r.withApp === r.people.length ? '' : (r.withApp === 0 ? 'None of them has' : (r.withApp === 1 ? '1 of them has' : r.withApp + ' of them have')) + ' the WeDo app; the others see it on the website.');
      }
      h += '<h4 class="sub-h">Reminders</h4><p class="sub-remline' + (r.problems.length ? ' sub-remline--warn' : '') + '"><i class="fa-solid fa-bell"></i> ' + esc(rem)
        + (r.problems.length ? '<br><b>' + esc(r.problems.join('; ').replace(/^./, function (c) { return c.toUpperCase(); })) + '.</b>' : '') + '</p>';

      // PhilGEPS documents
      if (i.requirements) {
        h += '<h4 class="sub-h">Eligibility documents <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-act="reqadd"><i class="fa-solid fa-plus"></i> Add</button></h4>';
        h += i.requirements.length ? '<ul class="sub-reqs">' + i.requirements.map(function (q) {
          var files = i.files.filter(function (f) { return f.reqId === q.id; });
          return '<li><div><b>' + esc(q.name) + '</b><br><span class="lcy-muted">' + (q.expiresOn ? 'Expires ' + esc(L.fmtDate(q.expiresOn)) : 'Does not expire / not entered')
            + (q.issuedOn ? ' · issued ' + esc(L.fmtDate(q.issuedOn)) : '') + '</span>'
            + (files.length ? '<br>' + files.map(fileLink).join(' ') : '') + '</div>'
            + '<div class="sub-reqs__end">' + (q.expiresOn ? duePill(q.tone, q.days) : '')
            + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-reqrenew="' + q.id + '"><i class="fa-solid fa-check"></i> Renewed</button>'
            + '<button type="button" class="sub-x" data-reqedit="' + q.id + '" title="Edit" aria-label="Edit ' + esc(q.name) + '"><i class="fa-solid fa-pen"></i></button></div></li>';
        }).join('') + '</ul>' : empty('No documents listed.');
      }

      // details
      h += '<h4 class="sub-h">Details</h4><dl class="lcy-facts">'
        + fact('Kind', esc(i.categoryLabel)) + fact('Company', esc(i.company)) + fact('Provider', esc(i.provider)) + fact('Plan', esc(i.plan))
        + fact('Account / login', esc(i.accountRef)) + fact('Cost', i.cost !== null ? esc(money(i.cost) + ' · ' + i.cycleLabel.toLowerCase()) : '')
        + fact('Renews', i.category === 'philgeps' ? 'Manually' : (i.autoRenew ? 'Automatically' : 'Manually')) + fact('Payment', esc(i.paymentNote))
        + fact('Owner', esc(i.owner || 'No owner')) + fact('Watchers', esc(i.watcherNames.join(', ')))
        + fact('Reminders start', i.notifyDays + ' days before expiry') + '</dl>';
      if (i.notes) { h += '<h4 class="sub-h">Notes</h4><p class="sub-notes">' + esc(i.notes) + '</p>'; }
      h += vaultHtml(i);

      // files
      var loose = i.files.filter(function (f) { return !f.reqId; });
      h += '<h4 class="sub-h">Receipts and files</h4>' + (loose.length ? '<div class="sub-files">' + loose.map(fileLink).join('') + '</div>' : '<p class="lcy-muted">None yet. A receipt added with Mark renewed lands here.</p>')
        + '<form class="sub-upload" id="subUpload"><select class="wd-select" name="kind">' + Object.keys(look.fileKinds).map(function (k) { return '<option value="' + k + '">' + esc(look.fileKinds[k]) + '</option>'; }).join('') + '</select>'
        + '<label class="wd-btn wd-btn--ghost wd-btn--sm"><i class="fa-solid fa-paperclip"></i> Attach file<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"></label></form>';

      // history
      h += '<h4 class="sub-h">Renewal history</h4>' + (i.renewals.length ? '<ul class="lcy-timeline sub-history">' + i.renewals.map(function (x) {
        return '<li class="is-hired"><div class="t-date">' + esc(L.fmtDateTime(x.at)) + ' · ' + esc(x.by) + '</div><div class="t-what">' + (x.what ? esc(x.what) + ': ' : '')
          + (x.oldExpiry ? esc(L.fmtDate(x.oldExpiry)) + ' → ' : 'until ') + esc(L.fmtDate(x.newExpiry)) + '</div><div class="t-note">'
          + esc([x.amount !== null ? money(x.amount) + ' paid' + (x.paidOn ? ' on ' + L.fmtDate(x.paidOn) : '') : '', x.reference ? 'ref ' + x.reference : '', x.notes].filter(Boolean).join(' · ')) + '</div></li>';
      }).join('') + '</ul>' : '<p class="lcy-muted">Not renewed in WeDo yet.</p>');

      var foot = '<div class="sub-footmore">'
        + (i.status === 'active' ? '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-act="cancel">Stop tracking</button>' : '')
        + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm sub-danger" data-act="delete">Delete</button></div>'
        + '<button type="button" class="wd-btn wd-btn--ghost" data-act="edit"><i class="fa-solid fa-pen"></i> Edit</button>'
        + (i.status === 'active' ? '<button type="button" class="wd-btn wd-btn--primary" data-act="renew"><i class="fa-solid fa-check"></i> Mark renewed</button>' : '');
      showPanel(i.name, h, foot, false);

      var root = document.getElementById('subModal');
      each(root, '[data-act]', function (b) {
        b.addEventListener('click', function () {
          var a = b.getAttribute('data-act');
          if (a === 'renew') { renewForm(i.id, 0, i); }
          if (a === 'edit') { itemForm(i); }
          if (a === 'reqadd') { reqForm(i, null); }
          if (a === 'resume') { simple('item_resume', { id: i.id }, 'Tracking resumed.'); }
          if (a === 'cancel' && confirm('Stop tracking ' + i.name + '? Reminders stop; the history is kept and you can resume later.')) { simple('item_cancel', { id: i.id }); }
          if (a === 'delete' && confirm('Delete ' + i.name + ' with its documents, files and renewal history? This cannot be undone.\n\nIf it was simply cancelled, use Stop tracking instead.')) {
            L.post('item_delete', { id: i.id }).then(function (res) { if (res.status !== 'ok') { alert(res.msg); return; } closePanel(); load(); });
          }
        });
      });
      each(root, '[data-reqrenew]', function (b) { b.addEventListener('click', function () { renewForm(i.id, +b.getAttribute('data-reqrenew'), i); }); });
      each(root, '[data-reqedit]', function (b) {
        b.addEventListener('click', function () { reqForm(i, i.requirements.filter(function (q) { return q.id === +b.getAttribute('data-reqedit'); })[0]); });
      });
      each($id('subModalBody'), 'a[data-open]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openItem(+a.getAttribute('data-open')); }); });
      each($id('subModalBody'), '[data-delfile]', function (b) {
        b.addEventListener('click', function () {
          if (!confirm('Remove this file?')) { return; }
          L.post('file_delete', { fileId: b.getAttribute('data-delfile') }).then(function (res) { if (res.status !== 'ok') { alert(res.msg); return; } openItem(i.id); });
        });
      });
      wireVault(i);
      var up = $id('subUpload');
      up.elements.file.addEventListener('change', function () {
        if (!up.elements.file.files.length) { return; }
        postForm('file_add', up, { id: i.id }).then(function (res) { if (res.status !== 'ok') { alert(res.msg); return; } openItem(i.id); });
      });
    });
  }

  /* ------------------------------------------------------------ logins vault */
  var VAULT_HIDE_MS = 30000;   // a shown password hides itself again
  var vaultTimers = [];
  function vaultHtml(i) {
    var v = i.vault;
    if (!v) { return ''; }
    var h = '<h4 class="sub-h">Logins' + (v.can && !v.locked ? ' <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-act="credadd"><i class="fa-solid fa-plus"></i> Add</button>' : '') + '</h4>';
    if (!v.can) {
      return h + '<p class="lcy-muted sub-vault-note"><i class="fa-solid fa-lock"></i> ' + (v.count
        ? esc(plural(v.count, 'saved login')) + '. Seeing them needs the Subscription logins right.'
        : 'No saved logins.') + '</p>';
    }
    if (v.locked) {
      return h + '<p class="sub-remline sub-remline--warn"><i class="fa-solid fa-lock"></i> The logins vault is locked on this server: its key (sub_vault_key in config.local.php) is not set up.</p>';
    }
    if (!v.creds.length) {
      h += '<p class="lcy-muted">No saved logins. Add the registrar, hosting or portal account so whoever renews can sign in. Passwords are stored encrypted and every view is logged.</p>';
    } else {
      h += '<ul class="sub-creds">' + v.creds.map(function (c) {
        return '<li class="sub-cred" data-cred="' + c.id + '">'
          + '<div class="sub-cred__head"><b>' + esc(c.label) + '</b>'
          + (c.url ? ' <a href="' + esc(c.url) + '" target="_blank" rel="noopener noreferrer" class="sub-cred__link"><i class="fa-solid fa-arrow-up-right-from-square"></i> Sign-in page</a>' : '')
          + '<span class="sub-cred__tools"><button type="button" class="sub-x" data-credit="' + c.id + '" title="Edit" aria-label="Edit ' + esc(c.label) + '"><i class="fa-solid fa-pen"></i></button></span></div>'
          + (c.unreadable ? '<p class="sub-remline sub-remline--warn">This login cannot be read with this server\'s key. Edit it and enter it again.</p>' : '')
          + (c.username ? '<div class="sub-cred__row"><span class="sub-cred__k">Username</span><span class="sub-cred__v">' + esc(c.username) + '</span>'
            + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-copyuser="' + esc(c.username) + '"><i class="fa-regular fa-copy"></i><span class="sub-hide-sm"> Copy</span></button></div>' : '')
          + (c.hasSecret ? '<div class="sub-cred__row"><span class="sub-cred__k">Password</span><span class="sub-cred__v sub-cred__secret" data-secret>••••••••••</span>'
            + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-credshow="' + c.id + '"><i class="fa-regular fa-eye"></i><span class="sub-hide-sm"> Show</span></button>'
            + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-credcopy="' + c.id + '"><i class="fa-regular fa-copy"></i><span class="sub-hide-sm"> Copy</span></button></div>' : '')
          + (c.hasNotes ? '<div class="sub-cred__notes lcy-hidden" data-notes></div>' + (c.hasSecret ? '' : '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-credshow="' + c.id + '"><i class="fa-regular fa-eye"></i> Show notes</button>') : '')
          + '</li>';
      }).join('') + '</ul>';
    }
    if (v.log.length) {
      h += '<details class="sub-credlog"><summary>Access log</summary><ul>' + v.log.map(function (l) {
        return '<li><b>' + esc(l.by) + '</b> ' + esc(l.action.toLowerCase()) + ' ' + esc(l.label) + ' <span class="lcy-muted">· ' + esc(L.fmtDateTime(l.at)) + '</span></li>';
      }).join('') + '</ul></details>';
    }
    return h;
  }

  /** Copy text: the async clipboard where allowed, else a hidden textarea. */
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return legacyCopy(text); });
    }
    return Promise.resolve(legacyCopy(text));
  }
  function legacyCopy(text) {
    var t = document.createElement('textarea');
    t.value = text;
    t.setAttribute('readonly', '');
    t.style.position = 'fixed';
    t.style.opacity = '0';
    document.body.appendChild(t);
    t.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    t.remove();
    return ok;
  }
  function flash(btn, text) {
    var old = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + esc(text);
    btn.disabled = true;
    setTimeout(function () { btn.innerHTML = old; btn.disabled = false; }, 1600);
  }

  function wireVault(i) {
    vaultTimers.forEach(clearTimeout);
    vaultTimers = [];
    var body = $id('subModalBody');
    each(body, '[data-act="credadd"]', function (b) { b.addEventListener('click', function () { credForm(i, null); }); });
    each(body, '[data-copyuser]', function (b) {
      b.addEventListener('click', function () { copyText(b.getAttribute('data-copyuser')).then(function (ok) { flash(b, ok ? 'Copied' : 'Could not copy'); }); });
    });
    each(body, '[data-credshow]', function (b) {
      b.addEventListener('click', function () {
        var li = b.closest('[data-cred]'), sec = li.querySelector('[data-secret]'), notes = li.querySelector('[data-notes]');
        if (li.classList.contains('is-shown')) { hideCred(li); return; }
        L.post('cred_reveal', { id: b.getAttribute('data-credshow'), why: 'view' }).then(function (res) {
          if (res.status !== 'ok') { alert(res.msg); return; }
          li.classList.add('is-shown');
          if (sec) { sec.textContent = res.cred.secret || '(no password)'; }
          if (notes && res.cred.notes) { notes.textContent = res.cred.notes; notes.classList.remove('lcy-hidden'); }
          each(li, '[data-credshow]', function (x) { x.__shown = x.innerHTML; x.innerHTML = '<i class="fa-regular fa-eye-slash"></i><span class="sub-hide-sm"> Hide</span>'; });
          vaultTimers.push(setTimeout(function () { hideCred(li); }, VAULT_HIDE_MS));
        });
      });
    });
    each(body, '[data-credcopy]', function (b) {
      b.addEventListener('click', function () {
        L.post('cred_reveal', { id: b.getAttribute('data-credcopy'), why: 'copy' }).then(function (res) {
          if (res.status !== 'ok') { alert(res.msg); return; }
          copyText(res.cred.secret).then(function (ok) { flash(b, ok ? 'Copied' : 'Could not copy'); });
        });
      });
    });
    each(body, '[data-credit]', function (b) {
      b.addEventListener('click', function () {
        L.post('cred_reveal', { id: b.getAttribute('data-credit'), why: 'edit' }).then(function (res) {
          if (res.status !== 'ok') { alert(res.msg); return; }
          credForm(i, res.cred);
        });
      });
    });
  }
  function hideCred(li) {
    if (!li.isConnected) { return; }
    li.classList.remove('is-shown');
    var sec = li.querySelector('[data-secret]'), notes = li.querySelector('[data-notes]');
    if (sec) { sec.textContent = '••••••••••'; }
    if (notes) { notes.textContent = ''; notes.classList.add('lcy-hidden'); }
    each(li, '[data-credshow]', function (x) { if (x.__shown) { x.innerHTML = x.__shown; } });
  }

  var CRED_LABELS = { domain: ['Registrar account', 'DNS / Cloudflare'], hosting: ['cPanel', 'Hosting account', 'FTP', 'Database'],
    security: ['Security dashboard', 'Vendor account'], philgeps: ['PhilGEPS portal', 'Authorized user'] };
  function genPassword() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*-_=+', out = '', a = new Uint32Array(20);
    crypto.getRandomValues(a);
    for (var k = 0; k < a.length; k++) { out += chars.charAt(a[k] % chars.length); }
    return out;
  }
  function credForm(i, c) {
    c = c || {};
    var labels = (CRED_LABELS[i.category] || []).concat(['Other']);
    var html = field('Name', input('label', c.label || (c.id ? '' : labels[0]), 'text', ' maxlength="100" required list="subCredLabels"')
        + '<datalist id="subCredLabels">' + labels.map(function (l) { return '<option value="' + esc(l) + '">'; }).join('') + '</datalist>', 'which account this is')
      + field('Sign-in page', input('url', c.url || (c.id ? '' : i.renewUrl || ''), 'url', ' maxlength="255" placeholder="https://"'))
      + field('Username or e-mail', input('username', c.username || (c.id ? '' : i.accountRef || ''), 'text', ' maxlength="255" autocomplete="off" autocapitalize="off" spellcheck="false"'))
      + field('Password', '<div class="sub-pass">' + input('secret', c.secret || '', 'password', ' maxlength="1000" autocomplete="new-password" spellcheck="false"')
        + '<button type="button" class="wd-btn wd-btn--ghost" id="subPassEye" aria-label="Show password"><i class="fa-regular fa-eye"></i></button>'
        + '<button type="button" class="wd-btn wd-btn--ghost" id="subPassGen" title="Make a strong password"><i class="fa-solid fa-wand-magic-sparkles"></i><span class="sub-hide-sm"> Generate</span></button></div>',
        c.id ? 'clear it to remove the password' : '')
      + field('Notes', '<textarea class="wd-textarea" rows="3" name="notes" maxlength="2000" placeholder="2FA phone, recovery codes, security answers…">' + esc(c.notes || '') + '</textarea>', 'encrypted too')
      + (c.id ? '<p><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm sub-danger" id="subCredDel"><i class="fa-solid fa-trash"></i> Delete this login</button></p>' : '')
      + '<p class="lcy-muted"><i class="fa-solid fa-lock"></i> Stored encrypted. Only people with the Subscription logins right can see it, and every view or copy is logged.</p>';
    formPanel(c.id ? 'Edit login' : 'Add login', html, 'cred_save', { itemId: i.id, id: c.id || '' }, 'Save login', function () {
      openItem(i.id);
    }, function () { openItem(i.id); });
    var pass = $id('subForm').elements.secret;
    $id('subPassEye').addEventListener('click', function () {
      pass.type = pass.type === 'password' ? 'text' : 'password';
      this.innerHTML = '<i class="fa-regular ' + (pass.type === 'password' ? 'fa-eye' : 'fa-eye-slash') + '"></i>';
    });
    $id('subPassGen').addEventListener('click', function () {
      if (pass.value && !confirm('Replace the password with a new one? Change it at the provider too.')) { return; }
      pass.value = genPassword();
      pass.type = 'text';
      $id('subPassEye').innerHTML = '<i class="fa-regular fa-eye-slash"></i>';
    });
    if ($id('subCredDel')) {
      $id('subCredDel').addEventListener('click', function () {
        if (!confirm('Delete the saved login ' + c.label + '? This cannot be undone.')) { return; }
        L.post('cred_delete', { id: c.id }).then(function (res) { if (res.status !== 'ok') { alert(res.msg); return; } openItem(i.id); });
      });
    }
  }

  function fileLink(f) {
    var url = BOOT.fileUrl + '?id=' + f.id;
    return '<span class="sub-file"><a href="' + url + '" target="_blank" rel="noopener" data-file><i class="fa-solid ' + (/\.pdf$/i.test(f.name) ? 'fa-file-pdf' : 'fa-file-image') + '"></i> '
      + esc(f.name || f.kindLabel) + '</a> <span class="lcy-muted">' + esc(f.kindLabel) + '</span><button type="button" class="sub-x" data-delfile="' + f.id + '" aria-label="Remove file">&times;</button></span>';
  }

  /** A quick action that needs no form; reloads the panel and the overview. */
  function simple(action, payload) {
    L.post(action, payload).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      load();
      openItem(payload.id);
    });
  }

  /** multipart POST of a form (files included) + extra fields */
  function postForm(action, form, extra) {
    var fd = new FormData(form);
    fd.append('action', action);
    fd.append('token', BOOT.token || '');
    Object.keys(extra || {}).forEach(function (k) {
      var v = extra[k];
      if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); } else if (v !== undefined && v !== null) { fd.append(k, v); }
    });
    var f = form.querySelector('input[type=file]');
    if (f && f.files.length && BOOT.maxUpload && f.files[0].size > BOOT.maxUpload) {
      return Promise.resolve({ status: 'error', msg: 'That file is too large for the server (' + Math.round(BOOT.maxUpload / 1048576) + ' MB at most).' });
    }
    return fetch(BOOT.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'Unexpected answer from the server.' }; }); })
      .catch(function () { return { status: 'error', msg: 'Could not reach the server — check your connection.' }; });
  }

  /* ------------------------------------------------------------ forms */
  function field(label, ctl, help) {
    return '<div class="wd-field"><label>' + esc(label) + (help ? ' <span class="lcy-muted">' + esc(help) + '</span>' : '') + '</label>' + ctl + '</div>';
  }
  function input(name, value, type, extra) {
    return '<input class="wd-input" type="' + (type || 'text') + '" name="' + name + '" value="' + esc(value === null || value === undefined ? '' : value) + '"' + (extra || '') + '>';
  }
  function select(name, opts, value) {
    return '<select class="wd-select" name="' + name + '">' + opts.map(function (o) {
      return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(value === null || value === undefined ? '' : value) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
    }).join('') + '</select>';
  }

  /** Shows a form in the panel; submit posts it (multipart) and calls done(res). */
  function formPanel(title, html, action, extra, submitText, done, back) {
    showPanel(title, '<form id="subForm" autocomplete="off">' + html + '<p class="lcy-modal-error" id="subFormErr" role="alert"></p><button type="submit" class="lcy-hidden" tabindex="-1" aria-hidden="true"></button></form>',
      '<button type="button" class="wd-btn wd-btn--ghost" id="subFormBack">' + (back ? 'Back' : 'Cancel') + '</button>'
      + '<button type="button" class="wd-btn wd-btn--primary" id="subFormGo"><i class="fa-solid fa-check"></i> ' + esc(submitText || 'Save') + '</button>', true);
    var f = $id('subForm');
    formBack = back || null;
    formStart = $(f).serialize();
    // Cancel / Back, Esc and the phone's Back button all go through hide.bs.modal: one level back, asking first
    $id('subFormBack').addEventListener('click', function () { $('#subModal').modal('hide'); });
    function go() {
      var b = $id('subFormGo');
      b.disabled = true;
      $id('subFormErr').textContent = '';
      postForm(action, f, typeof extra === 'function' ? extra(f) : extra).then(function (res) {
        b.disabled = false;
        if (res.status !== 'ok') { $id('subFormErr').textContent = res.msg; return; }
        done(res);
      });
    }
    f.addEventListener('submit', function (ev) { ev.preventDefault(); go(); });
    $id('subFormGo').addEventListener('click', go);
    return f;
  }

  /* ------------------------------------------------------------ add / edit a subscription
     One guided form: pick the kind (tiles), and a live card at the top answers "what will happen"
     (when it expires, when reminders start and who gets them, what it costs) while it is filled in.
     Quick picks (providers, cycles, reminder lead, people, what breaks) save typing; the optional
     "if it lapses" part is folded away. Field names are the endpoint's, unchanged. */
  var KIND = {
    domain:   { tip: 'Web address, e.g. wedo.ph', name: 'wedo.ph', providers: ['GoDaddy', 'dot.ph', 'Namecheap', 'Cloudflare'],
                impacts: ['Website, app and company e-mail stop working', 'Someone else can register the name'] },
    hosting:  { tip: 'Where the site and e-mail live', name: 'GoDaddy cPanel hosting', providers: ['GoDaddy', 'Hostinger', 'SiteGround', 'DigitalOcean'],
                impacts: ['WeDo site and app go down', 'Files and databases can be deleted'] },
    security: { tip: 'SSL, malware scan, firewall', name: 'SiteLock / SSL certificate', providers: ['SiteLock', 'Sucuri', 'Cloudflare', 'Sectigo'],
                impacts: ['Browsers show "Not secure"', 'Site loses malware scanning and firewall'] },
    philgeps: { tip: 'Certificate + eligibility documents', name: 'PhilGEPS Platinum registration', providers: ['PhilGEPS'],
                impacts: ['Cannot join government biddings'] }
  };
  var CYCLE_PICKS = [[1, 'Monthly'], [12, 'Yearly'], [24, '2 years'], [36, '3 years']];
  var LEAD_PICKS = [14, 30, 45, 60, 90];

  function isoAddMonths(iso, n) {
    var d = new Date(iso + 'T00:00:00'), day = d.getDate();
    d.setDate(1);
    d.setMonth(d.getMonth() + n);
    d.setDate(Math.min(day, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()));
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }
  function isoAddDays(iso, n) {
    var d = new Date(iso + 'T00:00:00');
    d.setDate(d.getDate() + n);
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }
  function daysBetween(a, b) { return Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000); }
  function initials(name) {
    var p = String(name || '').trim().split(/\s+/);
    return ((p[0] || '').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase() || '?';
  }
  function chipRadio(name, value, label, on, extra) {
    return '<label class="sub-chip' + (extra || '') + '"><input type="radio" name="' + name + '" value="' + esc(value) + '"' + (on ? ' checked' : '') + '><span>' + label + '</span></label>';
  }

  function itemForm(i, presetCat) {
    var editing = !!i;
    i = i || { category: presetCat || (tab !== 'all' && tab !== 'reminders' ? tab : 'domain'), cycle: 12, notifyDays: '', autoRenew: false, watchers: [],
               companyId: company !== 'all' ? company : look.myCompany, ownerId: BOOT.me };
    var defLead = function (c) { return c === 'philgeps' ? look.notifyPhilgeps : look.notifyDefault; };
    var lead = +i.notifyDays || defLead(i.category);
    var leads = LEAD_PICKS.indexOf(lead) < 0 ? LEAD_PICKS.concat([lead]).sort(function (a, b) { return a - b; }) : LEAD_PICKS;
    var cyclePicked = CYCLE_PICKS.some(function (c) { return c[0] === +i.cycle; });
    var parents = [['', 'Nothing — it stands on its own']].concat(data.items.filter(function (x) { return x.id !== i.id && x.status === 'active'; })
      .map(function (x) { return [x.id, x.name + ' (' + x.categoryLabel + ')']; }));
    var lapseOpen = editing && (i.impact || i.parentId || i.notes);

    var html = ''
      // the answer, live
      + '<div class="sub-preview" id="subPreview" aria-live="polite"></div>'
      // kind
      + '<section class="sub-sec"><h4 class="sub-h">What are you tracking?</h4><div class="sub-kinds">'
      + Object.keys(look.categories).map(function (k) {
          return '<label class="sub-kind"><input type="radio" name="category" value="' + k + '"' + (i.category === k ? ' checked' : '') + '>'
            + '<span><i class="fa-solid ' + (ICON[k] || 'fa-circle') + '"></i><b>' + esc(look.categories[k]) + '</b><small>' + esc((KIND[k] || {}).tip || '') + '</small></span></label>';
        }).join('') + '</div>'
      + (look.allCompanies && look.companies.length > 1 ? field('Company', select('companyId', look.companies.map(function (c) { return [c.id, c.name]; }), i.companyId)) : '')
      + field('Name', input('name', i.name, 'text', ' maxlength="150" required id="subFName" autocomplete="off" placeholder="' + esc(KIND[i.category].name) + '"'))
      + '<div class="wd-field"><label>Provider</label>' + input('provider', i.provider, 'text', ' maxlength="100" autocomplete="off" placeholder="Who you pay"')
      + '<div class="sub-picks" id="subProvPicks"></div></div>'
      + '<div class="sub-2col">' + field('Plan', input('plan', i.plan, 'text', ' maxlength="100" placeholder="Deluxe, Platinum…"'))
      + field('Account / login', input('accountRef', i.accountRef, 'text', ' maxlength="150" autocomplete="off" placeholder="it@company.ph"'), 'never a password') + '</div>'
      + '</section>'

      // renewal
      + '<section class="sub-sec"><h4 class="sub-h">When does it renew?</h4>'
      + '<div class="wd-field"><label>Expires on <span class="lcy-muted" id="subExpHelp">' + (i.category === 'philgeps' ? 'the certificate' : '') + '</span></label>'
      + input('expiresOn', i.expiresOn, 'date')
      + '<div class="sub-picks"><button type="button" class="sub-pick" id="subBoughtToday"><i class="fa-solid fa-bolt"></i> <span></span></button></div></div>'
      + '<div class="wd-field"><label>Renews</label><input type="hidden" name="cycle" value="' + esc(i.cycle) + '"><div class="sub-segs" id="subCycleSegs">'
      + CYCLE_PICKS.map(function (c) { return chipRadio('cyclePick', c[0], c[1], +i.cycle === c[0], ' sub-seg'); }).join('')
      + chipRadio('cyclePick', 'other', 'Other', !cyclePicked, ' sub-seg') + '</div>'
      + '<div class="sub-cycleother' + (cyclePicked ? ' lcy-hidden' : '') + '" id="subCycleOther">'
      + select('cycleOther', [1, 3, 6, 12, 24, 36, 60, 120, 0].map(function (k) { return [k, look.cycles[k]]; }), cyclePicked ? 12 : i.cycle) + '</div></div>'
      + '<div class="sub-2col">'
      + '<div class="wd-field"><label>Cost per renewal</label><div class="sub-money"><span>₱</span>' + input('cost', i.cost, 'text', ' inputmode="decimal" placeholder="0.00"') + '</div></div>'
      + field('Paid with', input('paymentNote', i.paymentNote, 'text', ' maxlength="100" placeholder="Visa ending 4411"')) + '</div>'
      + '<label class="sub-switch" id="subAutoRow"><input type="checkbox" name="autoRenew" value="1"' + (i.autoRenew ? ' checked' : '') + '><span class="sub-switch__track"></span>'
      + '<span class="sub-switch__text"><b>Renews automatically</b><small>Reminders still go out: auto-renew fails when a card expires.</small></span></label>'
      + field('Renewal page', input('renewUrl', i.renewUrl, 'url', ' maxlength="255" placeholder="https://"'), 'the reminder links here')
      + '</section>'

      // people
      + '<section class="sub-sec"><h4 class="sub-h">Who gets reminded?</h4>'
      + '<div class="wd-field"><label>Owner <span class="lcy-muted">renews it and gets the daily reminder</span></label><div class="sub-people" id="subOwners">'
      + chipRadio('ownerId', '', '<i class="sub-av sub-av--none">?</i> Nobody yet', !i.ownerId)
      + look.people.map(function (p) { return chipRadio('ownerId', p.id, '<i class="sub-av">' + esc(initials(p.name)) + '</i> ' + esc(p.name) + (p.id === BOOT.me ? ' <small>(you)</small>' : ''), i.ownerId === p.id); }).join('')
      + '</div></div>'
      + '<div class="wd-field"><label>Also remind</label><div class="sub-people" id="subWatchers">' + (look.people.length ? look.people.map(function (p) {
          return '<label class="sub-chip" data-person="' + esc(p.id) + '"><input type="checkbox" name="watchers[]" value="' + esc(p.id) + '"' + (i.watchers.indexOf(p.id) >= 0 ? ' checked' : '') + '>'
            + '<span><i class="sub-av">' + esc(initials(p.name)) + '</i> ' + esc(p.name) + '</span></label>';
        }).join('') : '<span class="lcy-muted">Nobody else has Subscriptions access yet.</span>') + '</div></div>'
      + '<div class="wd-field"><label>Start reminding</label><div class="sub-segs" id="subLeadSegs">'
      + leads.map(function (d) { return chipRadio('notifyDays', d, d + ' days', d === lead, ' sub-seg'); }).join('') + '</div>'
      + '<p class="lcy-muted sub-help">before it expires, then every day until it is marked renewed.</p></div>'
      + '</section>'

      // optional
      + '<details class="sub-fold"' + (lapseOpen ? ' open' : '') + '><summary><span><b>If it lapses</b><small>What breaks, what it depends on, notes — optional</small></span><i class="fa-solid fa-chevron-down"></i></summary><div class="sub-fold__body">'
      + '<div class="wd-field"><label>What breaks</label>' + input('impact', i.impact, 'text', ' maxlength="255" id="subFImpact" placeholder="Shown on every reminder"')
      + '<div class="sub-picks" id="subImpactPicks"></div></div>'
      + field('Depends on', select('parentId', parents, i.parentId || ''), 'e.g. the SSL depends on the hosting')
      + field('Notes', '<textarea class="wd-textarea" rows="3" name="notes" maxlength="1000">' + esc(i.notes || '') + '</textarea>')
      + '</div></details>'
      + '<p class="lcy-note sub-pgnote' + (!editing && i.category === 'philgeps' ? '' : ' lcy-hidden') + '" id="subPgNote"><i class="fa-solid fa-list-check"></i> The usual eligibility documents are added for you; enter their expiry dates afterwards.</p>';

    var f = formPanel(editing ? 'Edit ' + i.name : 'Add subscription', html, 'item_save', { id: editing ? i.id : '' }, editing ? 'Save changes' : 'Add subscription', function (res) {
      load().then(function () { openItem(res.id); });
    }, editing ? function () { openItem(i.id); } : null);
    var el = f.elements, leadTouched = editing && !!+i.notifyDays;
    var cat = function () { var c = f.querySelector('input[name="category"]:checked'); return c ? c.value : 'domain'; };
    var radio = function (n) { var c = f.querySelector('input[name="' + n + '"]:checked'); return c ? c.value : ''; };
    var personName = function (id) { var p = look.people.filter(function (x) { return x.id === id; })[0]; return p ? p.name : ''; };

    function picks(box, list, target) {
      $id(box).innerHTML = list.map(function (v) { return '<button type="button" class="sub-pick" data-v="' + esc(v) + '">' + esc(v) + '</button>'; }).join('');
      each($id(box), '[data-v]', function (b) {
        b.addEventListener('click', function () { el[target].value = b.getAttribute('data-v'); el[target].dispatchEvent(new Event('input', { bubbles: true })); });
      });
    }
    function onKind(first) {
      var c = cat(), k = KIND[c] || KIND.domain;
      el.name.placeholder = k.name;
      picks('subProvPicks', k.providers, 'provider');
      picks('subImpactPicks', k.impacts, 'impact');
      $id('subExpHelp').textContent = c === 'philgeps' ? 'the certificate' : '';
      $id('subAutoRow').classList.toggle('lcy-hidden', c === 'philgeps');
      $id('subPgNote').classList.toggle('lcy-hidden', editing || c !== 'philgeps');
      if (!first && c === 'philgeps' && !el.provider.value) { el.provider.value = 'PhilGEPS'; }
      if (!first && !leadTouched) {                     // follow the kind's default lead until it is chosen by hand
        var d = String(defLead(c)), r = f.querySelector('input[name="notifyDays"][value="' + d + '"]');
        if (r) { r.checked = true; }
      }
    }
    function syncCycle() {
      var p = radio('cyclePick');
      $id('subCycleOther').classList.toggle('lcy-hidden', p !== 'other');
      el.cycle.value = p === 'other' ? el.cycleOther.value : p;
    }
    function syncPeople() {
      var owner = radio('ownerId');
      each($id('subWatchers'), '[data-person]', function (l) {
        var me = l.getAttribute('data-person') === owner;
        l.classList.toggle('lcy-hidden', me);
        if (me) { l.querySelector('input').checked = false; }
      });
    }
    function preview() {
      var c = cat(), name = el.name.value.trim(), exp = el.expiresOn.value, cyc = +el.cycle.value, lead = +radio('notifyDays') || defLead(c);
      var owner = radio('ownerId'), watchers = [].filter.call(f.querySelectorAll('input[name="watchers[]"]:checked'), function (x) { return true; }).length;
      var bought = $id('subBoughtToday');
      bought.classList.toggle('lcy-hidden', !cyc);
      if (cyc) { bought.querySelector('span').textContent = 'Bought today: expires ' + L.fmtDate(isoAddMonths(BOOT.today, cyc)); }
      var lines = [], tone = 'ok';
      if (!exp) {
        lines.push(['fa-calendar-xmark', 'No expiry date yet, so no reminders can start.']);
        tone = 'none';
      } else {
        var left = daysBetween(BOOT.today, exp), from = isoAddDays(exp, -lead);
        tone = left < 0 ? 'expired' : left <= 7 ? 'urgent' : left <= lead ? 'soon' : 'ok';
        lines.push(['fa-calendar-day', 'Expires ' + L.fmtDate(exp) + ' · ' + (left === 0 ? 'today' : left > 0 ? 'in ' + plural(left, 'day') : plural(-left, 'day') + ' ago')]);
        var who = owner ? personName(owner) + (owner === BOOT.me ? ' (you)' : '') + (watchers ? ' + ' + watchers + ' more' : '') : '';
        lines.push(['fa-bell', !owner ? 'Nobody is reminded until it expires. Choose an owner.'
          : (from <= BOOT.today ? 'Reminders start today' : 'Reminders start ' + L.fmtDate(from)) + ' for ' + who + ', every day']);
      }
      var cost = el.cost.value.replace(/[^\d.]/g, '');
      if (cost) {
        lines.push(['fa-peso-sign', money(+cost) + ' ' + (cyc ? look.cycles[cyc].toLowerCase() : 'one time')
          + (c === 'philgeps' ? '' : el.autoRenew.checked ? ' · renews automatically' : ' · renewed by hand')]);
      }
      $id('subPreview').className = 'sub-preview sub-preview--' + tone;
      $id('subPreview').innerHTML = '<span class="sub-preview__icon">' + catIcon(c) + '</span><div><b>' + esc(name || (c === 'philgeps' ? 'New PhilGEPS registration' : 'New ' + look.categories[c].toLowerCase())) + '</b>'
        + lines.map(function (l) { return '<span><i class="fa-solid ' + l[0] + '"></i> ' + esc(l[1]) + '</span>'; }).join('') + '</div>';
    }

    each(f, 'input[name="category"]', function (r) { r.addEventListener('change', function () { onKind(false); preview(); }); });
    each(f, 'input[name="cyclePick"]', function (r) { r.addEventListener('change', function () { syncCycle(); preview(); }); });
    each(f, 'input[name="ownerId"]', function (r) { r.addEventListener('change', function () { syncPeople(); preview(); }); });
    each(f, 'input[name="notifyDays"]', function (r) { r.addEventListener('change', function () { leadTouched = true; preview(); }); });
    el.cycleOther.addEventListener('change', function () { syncCycle(); preview(); });
    f.addEventListener('input', preview);
    f.addEventListener('change', preview);
    $id('subBoughtToday').addEventListener('click', function () { el.expiresOn.value = isoAddMonths(BOOT.today, +el.cycle.value || 12); preview(); });
    el.name.addEventListener('blur', function () {      // a pasted address -> the bare domain
      if (cat() !== 'domain') { return; }
      el.name.value = el.name.value.trim().toLowerCase().replace(/^[a-z]+:\/\//, '').replace(/^www\./, '').replace(/[\/?#].*$/, '');
      preview();
    });
    onKind(true);
    syncCycle();
    syncPeople();
    preview();
    formStart = $(f).serialize();                       // after the quick setup above: untouched = nothing to discard
    if (!editing) { el.name.focus(); }
  }

  /** "Mark renewed" for an item, or for one PhilGEPS document (reqId). */
  function renewForm(itemId, reqId, item) {
    var get = item ? Promise.resolve(item) : L.post('item', { id: itemId }).then(function (r) { return r.status === 'ok' ? r.item : null; });
    get.then(function (i) {
      if (!i) { return; }
      var q = reqId ? (i.requirements || []).filter(function (x) { return x.id === reqId; })[0] : null;
      var cur = q ? q.expiresOn : i.expiresOn;
      var suggest = q ? (cur ? addYear(cur) : '') : i.suggestExpiry;
      var html = '<p class="sub-renewing">' + catIcon(i.category) + ' <b>' + esc(q ? q.name : i.name) + '</b>' + (q ? '<br><span class="lcy-muted">' + esc(i.name) + '</span>' : '')
        + '<br><span class="lcy-muted">Current expiry: ' + (cur ? esc(L.fmtDate(cur)) + ' (' + esc(rel(q ? q.days : i.days)) + ')' : 'not set') + '</span></p>'
        + field('New expiry date', input('newExpiry', suggest, 'date', ' required'), q ? 'from the new document' : (i.cycle ? 'suggested: one ' + i.cycleLabel.toLowerCase().replace('every ', '') + ' later' : ''))
        + (q ? field('Issued on', input('issuedOn', BOOT.today, 'date')) : '')
        + (!q ? '<div class="sub-2col">' + field('Amount paid (₱)', input('amount', i.cost !== null ? i.cost : '', 'text', ' inputmode="decimal"')) + field('Paid on', input('paidOn', BOOT.today, 'date')) + '</div>' : '')
        + field('Reference', input('reference', '', 'text', ' maxlength="100" placeholder="' + (q ? 'Document / OR no.' : 'Invoice or order no.') + '"'))
        + field(q ? 'Scan of the new document' : 'Receipt', '<input class="wd-input" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png">', 'optional, PDF / JPG / PNG')
        + field('Notes', '<textarea class="wd-textarea" rows="2" name="notes" maxlength="500"></textarea>')
        + '<p class="lcy-muted">Reminders stop as soon as this is saved.</p>';
      formPanel('Mark renewed', html, 'renew', { id: i.id, reqId: reqId || '' }, 'Mark renewed', function () {
        load();
        if (openId === i.id || item) { openItem(i.id); } else { closePanel(); }
      }, item ? function () { openItem(i.id); } : null);
    });
  }
  function addYear(d) {
    var x = new Date(d + 'T00:00:00');
    x.setFullYear(x.getFullYear() + 1);
    return x.getFullYear() + '-' + ('0' + (x.getMonth() + 1)).slice(-2) + '-' + ('0' + x.getDate()).slice(-2);
  }

  function reqForm(i, q) {
    q = q || {};
    var html = field('Document', input('name', q.name, 'text', ' maxlength="120" required placeholder="Tax clearance (BIR)"'))
      + '<div class="sub-2col">' + field('Issued on', input('issuedOn', q.issuedOn, 'date')) + field('Expires on', input('expiresOn', q.expiresOn, 'date'), 'blank if it does not expire') + '</div>'
      + field('Start reminders', input('notifyDays', q.notifyDays || '', 'number', ' min="1" max="365" placeholder="' + look.notifyPhilgeps + '"'), 'days before expiry')
      + field('Notes', '<textarea class="wd-textarea" rows="2" name="notes" maxlength="500">' + esc(q.notes || '') + '</textarea>')
      + (q.id ? '<p><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm sub-danger" id="subReqDel"><i class="fa-solid fa-trash"></i> Remove this document</button></p>' : '')
      + '<p class="lcy-muted">Changing the date here does not log a renewal; use <b>Renewed</b> for that.</p>';
    formPanel(q.id ? 'Edit document' : 'Add document', html, 'req_save', { itemId: i.id, id: q.id || '' }, 'Save', function () {
      load();
      openItem(i.id);
    }, function () { openItem(i.id); });
    if ($id('subReqDel')) {
      $id('subReqDel').addEventListener('click', function () {
        if (!confirm('Remove ' + q.name + ' and its scans?')) { return; }
        L.post('req_delete', { reqId: q.id }).then(function (res) { if (res.status !== 'ok') { alert(res.msg); return; } load(); openItem(i.id); });
      });
    }
  }

  /* ------------------------------------------------------------ start */
  $('#subModal').on('hide.bs.modal', function (ev) {
    if (plainClose) { plainClose = false; return; }
    var f = $id('subForm');
    if (!f) { return; }
    ev.preventDefault();
    if ($(f).serialize() !== formStart && !confirm('Discard what you typed?')) { return; }
    if (formBack) { formBack(); } else { closePanel(); }
  });
  $('#subModal').on('hidden.bs.modal', function () { openId = 0; vaultTimers.forEach(clearTimeout); vaultTimers = []; $id('subModalBody').innerHTML = ''; });
  $id('subAdd').addEventListener('click', function () { itemForm(null); });
  $id('subCompany').addEventListener('change', function () {
    company = $id('subCompany').value;
    try { localStorage.setItem('wd-sub-company', company); } catch (e) { /* private window */ }
    load();
  });
  each($id('subTabs'), '.lcy-tab', function (b) { b.addEventListener('click', function () { setTab(b.getAttribute('data-tab')); }); });
  $id('subQ').addEventListener('input', drawList);
  $id('subShowCancelled').addEventListener('change', drawList);

  loadLookups().then(function (ok) {
    if (!ok) { return; }
    load().then(function () { if (BOOT.open) { openItem(BOOT.open); } });
  });
})();
