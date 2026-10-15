/* ==========================================================================
   applicants.js  —  screen logic for applicants.php (Management > Applicants).
   Pipeline list + filters, one application (profile, resume, rehire check,
   history, stage changes), Hire (creates the employee from the registration
   and shows the temporary password once), delete closed applications, and
   the job openings. Everything goes to query/applicants-action.php; every
   value from applicants is escaped before it reaches the page.
   ========================================================================== */
(function () {
  'use strict';

  var L = window.LCY, esc = L.esc, BOOT = L.boot;
  var STAGES = BOOT.stages || {};
  var TONE = { new: 'info', screening: 'info', interview: 'info', exam: 'info', offer: 'warn', hired: 'ok', rejected: 'danger', withdrawn: 'info' };
  var filter = { stage: '', opening: '', q: '' };
  var current = null;      // the application in the modal
  var hireOpts = null;

  function $id(id) { return document.getElementById(id); }
  function stagePill(s) { return '<span class="wd-pill wd-pill--' + (TONE[s] || 'info') + '">' + esc(STAGES[s] || s) + '</span>'; }

  function post(action, data) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('token', BOOT.token || '');
    Object.keys(data || {}).forEach(function (k) { if (data[k] !== undefined && data[k] !== null) { fd.append(k, data[k]); } });
    return fetch('query/applicants-action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'Unexpected answer from the server.' }; }); })
      .catch(function () { return { status: 'error', msg: 'Could not reach the server — check your connection.' }; });
  }

  function options(sel, list, blank, pick) {
    sel.innerHTML = (blank !== null ? '<option value="">' + esc(blank) + '</option>' : '')
      + list.map(function (o) { return '<option value="' + esc(o.id) + '"' + (String(o.id) === String(pick) ? ' selected' : '') + '>' + esc(o.name) + '</option>'; }).join('');
  }

  /* ------------------------------------------------------------ list */
  function renderChips(counts) {
    var open = 0;
    ['new', 'screening', 'interview', 'exam', 'offer'].forEach(function (k) { open += counts[k] || 0; });
    var chips = [['', 'In progress (' + open + ')']].concat(Object.keys(STAGES).map(function (k) { return [k, STAGES[k] + ' (' + (counts[k] || 0) + ')']; }))
      .concat([['all', 'All']]);
    $id('aplChips').innerHTML = chips.map(function (c) {
      return '<button type="button" class="lcy-tab' + (c[0] === filter.stage ? ' is-on' : '') + '" data-stage="' + c[0] + '">' + esc(c[1]) + '</button>';
    }).join('');
    Array.prototype.forEach.call($id('aplChips').querySelectorAll('[data-stage]'), function (b) {
      b.addEventListener('click', function () { filter.stage = b.getAttribute('data-stage'); load(); });
    });
  }

  function rehireBadge(flag) {
    if (!flag) { return ''; }
    if (flag === 'former') { return ' <span class="wd-pill wd-pill--info" title="Worked here before">Former employee</span>'; }
    return ' ' + L.rehirePill(flag);
  }

  function load() {
    post('list', filter).then(function (res) {
      var box = $id('aplList');
      if (res.status !== 'ok') { box.innerHTML = '<div class="lcy-empty">' + esc(res.msg) + '</div>'; return; }
      renderChips(res.counts);
      if (!res.applicants.length) { box.innerHTML = '<div class="lcy-empty">No applications here.</div>'; return; }
      box.innerHTML = '<div class="wd-tablewrap" style="max-height:60vh"><table class="wd-table lcy-table"><thead><tr><th>Applicant</th><th>Position</th><th>City</th><th>Registered</th><th>Stage</th></tr></thead><tbody>'
        + res.applicants.map(function (a) {
          return '<tr class="apl-row" data-id="' + a.id + '" tabindex="0"><td><div class="apl-who"><span class="apl-av">' + face(a) + '</span><div>'
            + '<b>' + esc(a.name) + '</b>' + rehireBadge(a.rehire)
            + '<br><span class="lcy-muted">' + esc(a.email) + ' · ' + esc(a.mobile) + '</span></div></div></td>'
            + '<td>' + esc(a.opening || 'Any position') + '</td><td>' + esc(a.city) + '</td>'
            + '<td>' + esc(L.fmtDate(a.appliedAt)) + '</td><td>' + stagePill(a.stage) + '</td></tr>';
        }).join('') + '</tbody></table></div>';
      Array.prototype.forEach.call(box.querySelectorAll('.apl-row'), function (tr) {
        var go = function () { open(+tr.getAttribute('data-id')); };
        tr.addEventListener('click', go);
        tr.addEventListener('keydown', function (e) { if (e.key === 'Enter') { go(); } });
      });
    });
  }

  /* ------------------------------------------------------------ one application */
  var PIPE = ['new', 'screening', 'interview', 'exam', 'offer', 'hired'];

  function filled(v) { return v !== null && v !== undefined && String(v).trim() !== ''; }

  /** label/value grid: [label, value, wide?]; `copy` adds a copy button to each value (government IDs). */
  function kv(pairs, copy) {
    var rows = pairs.filter(function (p) { return filled(p[1]); });
    if (!rows.length) { return '<p class="apl-none">Not provided</p>'; }
    return '<dl class="apl-kv' + (copy ? ' apl-kv--ids' : '') + '">' + rows.map(function (p) {
      return '<div' + (p[2] ? ' class="is-wide"' : '') + '><dt>' + esc(p[0]) + '</dt><dd>' + esc(p[1])
        + (copy ? ' <button type="button" class="apl-copy" data-copy="' + esc(p[1]) + '" title="Copy" aria-label="Copy ' + esc(p[0]) + '"><i class="fa-regular fa-copy"></i></button>' : '')
        + '</dd></div>';
    }).join('') + '</dl>';
  }

  function card(icon, title, body, wide) {
    return '<section class="apl-card' + (wide ? ' is-wide' : '') + '"><h5 class="apl-card__title"><i class="fa-solid ' + icon + '"></i> ' + esc(title) + '</h5>' + body + '</section>';
  }

  function initials(name) {
    return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase();
  }

  /** the applicant's camera photo (query/applicant-photo.php), or their initials */
  function photoUrl(a) { return 'query/applicant-photo.php?id=' + encodeURIComponent(a.id); }
  function face(a) {
    return a.hasPhoto ? '<img src="' + photoUrl(a) + '" alt="" loading="lazy">' : esc(initials(a.name));
  }

  function age(d) {
    if (!d) { return ''; }
    var b = new Date(String(d).slice(0, 10) + 'T00:00:00'), n = new Date();
    if (isNaN(b)) { return ''; }
    var y = n.getFullYear() - b.getFullYear();
    if (n.getMonth() < b.getMonth() || (n.getMonth() === b.getMonth() && n.getDate() < b.getDate())) { y--; }
    return y > 0 && y < 120 ? y + ' yrs old' : '';
  }

  function ago(s) {
    var d = new Date(String(s || '').replace(' ', 'T'));
    if (isNaN(d)) { return ''; }
    var m = Math.round((Date.now() - d) / 60000);
    if (m < 1) { return 'just now'; }
    if (m < 60) { return m + ' min ago'; }
    if (m < 1440) { return Math.round(m / 60) + ' h ago'; }
    if (m < 43200) { return Math.round(m / 1440) + ' d ago'; }
    return L.fmtDate(s);
  }

  function open(id) {
    post('detail', { id: id }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      render(res.applicant);
      $('#aplModal').modal('show');
    });
  }

  /** the pipeline stepper; a closed (rejected / withdrawn) application shows how far it got. */
  function track(a) {
    var closed = a.stage === 'rejected' || a.stage === 'withdrawn';
    var reached = PIPE.indexOf(a.stage);
    if (closed) {
      a.events.forEach(function (e) { reached = Math.max(reached, PIPE.indexOf(e.from), PIPE.indexOf(e.to)); });
    }
    var steps = PIPE.map(function (k, i) {
      var cls = i < reached || (i === reached && k === 'hired') ? 'is-done' : (i === reached ? (closed ? 'is-stop' : 'is-on') : '');
      return '<li class="' + cls + '"><span class="apl-track__dot">' + (i + 1) + '</span><span class="apl-track__lbl">' + esc(STAGES[k] || k) + '</span></li>';
    }).join('');
    return '<ol class="apl-track' + (closed ? ' is-closed' : '') + '">' + steps + '</ol>'
      + (closed ? '<div class="apl-track__end"><i class="fa-solid fa-circle-xmark"></i> ' + esc(STAGES[a.stage]) + '</div>' : '');
  }

  function rehireBox(a) {
    if (!a.matches.length) {
      return '<div class="apl-callout is-ok"><i class="fa-solid fa-circle-check"></i><div><b>No previous record</b>'
        + '<span>Nobody with this name in former employees or the archive.</span></div></div>';
    }
    var tone = a.rehire === 'no' ? 'is-danger' : (a.rehire === 'review' ? 'is-warn' : 'is-info');
    return '<div class="apl-callout ' + tone + '"><i class="fa-solid fa-clock-rotate-left"></i><div><b>Worked here before?</b>'
      + '<span>' + a.matches.length + ' record' + (a.matches.length === 1 ? '' : 's') + ' with the same name — check before moving on.</span>'
      + a.matches.map(function (m) {
        var cls = m.rehire === 'no' ? ' is-no' : (m.rehire === 'review' ? ' is-review' : '');
        return '<div class="apl-match' + cls + '"><div class="apl-match__head"><b>' + esc(m.name) + '</b> '
          + (m.source === 'ams' ? '<span class="wd-pill wd-pill--info">Archive</span>' : L.statusPill(m.status))
          + (m.strong ? ' <span class="wd-pill wd-pill--warn">Same birth date</span>' : ' <span class="lcy-muted">same name only</span>')
          + (m.rehire ? ' ' + L.rehirePill(m.rehire) : '') + '</div>'
          + kv([['Position', m.position], ['Employed', [m.hired ? L.fmtDate(m.hired) || m.hired : '', m.lastDay ? L.fmtDate(m.lastDay) || m.lastDay : ''].filter(Boolean).join(' – ')],
                ['Separation', m.separation], ['Reason for leaving', m.reason, true], ['Rehire note', m.rehireNote, true], ['Derogatory records', m.derogatory, true]])
          + '</div>';
      }).join('') + '</div></div>';
  }

  function render(a) {
    current = a;
    var p = a.profile;
    var closed = a.stage === 'hired' || a.stage === 'rejected' || a.stage === 'withdrawn';

    /* ---- header */
    var av = $id('aplAvatar');
    av.innerHTML = a.hasPhoto ? '<a href="' + photoUrl(a) + '" target="_blank" rel="noopener" title="Open the photo">' + face(a) + '</a>' : face(a);
    av.classList.toggle('has-photo', !!a.hasPhoto);
    $id('aplTitle').innerHTML = esc(a.name) + ' ' + stagePill(a.stage) + rehireBadge(a.rehire);
    $id('aplSub').innerHTML = 'Applied for <b>' + esc(a.opening || 'Any position') + '</b> · ' + esc(L.fmtDateTime(a.appliedAt))
      + (p.Source ? ' · via ' + esc(p.Source) : '');
    var city = [p.City, p.Province].filter(Boolean).join(', ');
    $id('aplContact').innerHTML = (a.email ? '<a href="mailto:' + esc(a.email) + '"><i class="fa-regular fa-envelope"></i> ' + esc(a.email) + '</a>' : '')
      + (a.mobile ? '<a href="tel:' + esc(a.mobile) + '"><i class="fa-solid fa-phone"></i> ' + esc(a.mobile) + '</a>' : '')
      + (city ? '<span><i class="fa-solid fa-location-dot"></i> ' + esc(city) + '</span>' : '');

    $id('aplActions').innerHTML = (a.hasResume ? '<a class="wd-btn wd-btn--ghost wd-btn--sm" href="query/applicant-resume.php?id=' + a.id + '" rel="noopener"><i class="fa-solid fa-file-arrow-down"></i> Resume</a>' : '')
      + (BOOT.canHire && !closed ? (a.stage === BOOT.hireStage
          ? '<button type="button" class="wd-btn wd-btn--success wd-btn--sm" id="aplHireBtn"><i class="fa-solid fa-user-plus"></i> Hire</button>'
          : '<button type="button" class="wd-btn wd-btn--sm apl-pro__locked" disabled title="Hire unlocks at the ' + esc(STAGES[BOOT.hireStage]) + ' stage">'
            + '<i class="fa-solid fa-lock"></i> Hire at ' + esc(STAGES[BOOT.hireStage]) + '</button>') : '')
      + (a.stage === 'rejected' || a.stage === 'withdrawn' ? '<button type="button" class="wd-btn wd-btn--danger wd-btn--sm" id="aplDeleteBtn"><i class="fa-solid fa-trash"></i> Delete</button>' : '')
      + (a.empId ? '<span class="wd-pill wd-pill--ok"><i class="fa-solid fa-id-badge"></i> Employee ' + esc(a.empId) + '</span>'
          + (BOOT.canLife ? '<a class="wd-btn wd-btn--ghost wd-btn--sm" href="separation?emp=' + encodeURIComponent(a.empId) + '">Lifecycle</a>' : '') : '');
    $id('aplTrack').innerHTML = track(a);

    /* ---- body */
    var edu = a.education.filter(function (e) { return e.School; });
    var eduHtml = edu.length ? '<ul class="apl-edu">' + edu.map(function (e) {
      var yrs = [e.YearStarted, e.YearEnded].filter(Boolean).join(' – ');
      return '<li><span class="apl-edu__lvl">' + esc(e.Program) + '</span><b>' + esc(e.School) + '</b>' + (yrs ? '<small>' + esc(yrs) + '</small>' : '') + '</li>';
    }).join('') + '</ul>' : '<p class="apl-none">Not provided</p>';

    var famHtml = a.family.length ? '<div class="apl-people">' + a.family.map(function (f) {
      return '<div class="apl-person-card' + (+f.Emergency ? ' is-emergency' : '') + '"><span class="apl-person-card__av">' + esc(initials(f.Name)) + '</span>'
        + '<div class="apl-person-card__body"><b>' + esc(f.Name) + '</b>'
        + '<span class="apl-person-card__rel">' + esc(f.Relationship || '') + (+f.Emergency ? ' <span class="wd-pill wd-pill--warn">Emergency contact</span>' : '') + '</span>'
        + (f.Contact ? '<a href="tel:' + esc(f.Contact) + '"><i class="fa-solid fa-phone"></i> ' + esc(f.Contact) + '</a>' : '')
        + (f.Address ? '<span><i class="fa-solid fa-location-dot"></i> ' + esc(f.Address) + '</span>' : '') + '</div></div>';
    }).join('') + '</div>' : '<p class="apl-none">Not provided</p>';

    var hint = BOOT.canHire === false && !closed
      ? '<div class="apl-callout is-info"><i class="fa-solid fa-circle-info"></i><div><span>Hiring creates a WeDo account, so it also needs the "Enroll Employee" right.</span></div></div>' : '';

    $id('aplBody').innerHTML = hint + rehireBox(a) + '<div class="apl-cards">'
      + card('fa-user', 'Personal', kv([['Birth date', [L.fmtDate(a.birthDate), age(a.birthDate)].filter(Boolean).join(' · ')], ['Gender', p.Gender],
          ['Civil status', p.CivilStatus], ['Citizenship', p.Citizenship], ['Religion', p.Religion]]))
      + card('fa-address-book', 'Contact & address', kv([['Email', a.email, true], ['Mobile', a.mobile], ['Home phone', p.HomePhone],
          ['Address', [p.Street, p.Barangay, p.City, p.Province, p.Zip, p.Country].filter(Boolean).join(', '), true]]))
      + card('fa-id-card', 'Government IDs', kv([['SSS', p.SSS], ['TIN', p.TIN], ['PhilHealth', p.PhilHealth], ['Pag-IBIG', p.PagIbig], ['UMID', p.UMID],
          ['Passport', p.PassportNo]], true)
          + (p.PassportNo && (p.PassportExpiry || p.PassportIssuer) ? '<p class="apl-foot-note">Passport ' + esc([p.PassportExpiry ? 'expires ' + L.fmtDate(p.PassportExpiry) : '', p.PassportIssuer ? 'issued by ' + p.PassportIssuer : ''].filter(Boolean).join(', ')) + '</p>' : ''))
      + card('fa-briefcase', 'Most recent job', kv([['Company', p.PrevCompany], ['Position', p.PrevPosition], ['Department', p.PrevDept], ['Started', L.fmtDate(p.PrevStart)]]))
      + card('fa-graduation-cap', 'Education', eduHtml, true)
      + card('fa-people-roof', 'Family & emergency contacts', famHtml, true)
      + '</div>'
      + '<p class="apl-consent-note"><i class="fa-solid fa-shield-halved"></i> Data Privacy consent given ' + esc(L.fmtDateTime(p.ConsentAt) || '—') + '</p>';

    $id('aplHistory').innerHTML = a.events.length ? a.events.slice().reverse().map(function (e) {
      var what = e.from && e.from !== e.to ? (STAGES[e.from] || e.from) + ' → ' + e.toLabel : (e.from ? 'Note' : e.toLabel);
      return '<li class="apl-feed__item apl-feed--' + esc(TONE[e.to] || 'info') + '"><div class="apl-feed__what">' + esc(what) + '</div>'
        + (e.notes ? '<div class="apl-feed__note">' + esc(e.notes) + '</div>' : '')
        + '<div class="apl-feed__meta" title="' + esc(L.fmtDateTime(e.at)) + '">' + esc(e.by) + ' · ' + esc(ago(e.at)) + '</div></li>';
    }).join('') : '<li class="apl-none">No activity yet.</li>';

    // stages go in order: the ones the server would refuse are shown but can't be picked
    var sel = $id('aplStage'), ok = a.allowedStages || [];
    sel.innerHTML = Object.keys(STAGES).filter(function (k) { return k !== 'hired' || a.stage === 'hired'; }).map(function (k) {
      var can = k === a.stage || ok.indexOf(k) >= 0;
      return '<option value="' + k + '"' + (k === a.stage ? ' selected' : '') + (can ? '' : ' disabled') + '>'
        + esc(STAGES[k]) + (k === a.stage ? ' (now)' : '') + '</option>';
    }).join('');
    var nextIdx = PIPE.indexOf(a.stage) + 1;
    $id('aplStageHint').textContent = a.stage === 'rejected' || a.stage === 'withdrawn'
      ? 'To reopen, pick the stage it had reached or an earlier one.'
      : (nextIdx > 0 && nextIdx < PIPE.length - 1 ? 'Stages go in order — next is ' + STAGES[PIPE[nextIdx]] + '.' : '');
    $id('aplStageForm').classList.toggle('lcy-hidden', a.stage === 'hired');
    $id('aplNotes').value = '';
    $id('aplMailOn').checked = false;
    $id('aplMailMsg').value = '';
    $id('aplMailRes').textContent = '';
    syncMailOpt();
    $id('aplStageErr').textContent = '';

    var hb = $id('aplHireBtn');
    if (hb) { hb.addEventListener('click', openHire); }
    var db = $id('aplDeleteBtn');
    if (db) {
      db.addEventListener('click', function () {
        if (!confirm('Delete this application, its resume and personal data for good?')) { return; }
        post('delete', { id: a.id }).then(function (res) {
          if (res.status !== 'ok') { alert(res.msg); return; }
          $('#aplModal').modal('hide');
          load();
        });
      });
    }
  }

  $id('aplBody').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-copy]');
    if (!b || !navigator.clipboard) { return; }
    navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function () {
      b.innerHTML = '<i class="fa-solid fa-check"></i>';
      setTimeout(function () { b.innerHTML = '<i class="fa-regular fa-copy"></i>'; }, 1400);
    });
  });

  /* email the applicant on a move to Job offer / Not selected (only when Brevo is set up) */
  var MAIL_HINT = { offer: 'e.g. Start date, where and when to report, what to bring', rejected: 'e.g. Thank you for coming to the interview' };
  function syncMailOpt() {
    var st = $id('aplStage').value;
    var can = !!BOOT.canMail && current && st !== current.stage && (BOOT.mailStages || []).indexOf(st) >= 0;
    $id('aplMailOpt').classList.toggle('lcy-hidden', !can);
    $id('aplMailMsgBox').classList.toggle('lcy-hidden', !can || !$id('aplMailOn').checked);
    $id('aplMailMsg').placeholder = MAIL_HINT[st] || '';
  }
  $id('aplStage').addEventListener('change', syncMailOpt);
  $id('aplMailOn').addEventListener('change', syncMailOpt);

  $id('aplStageForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var email = !$id('aplMailOpt').classList.contains('lcy-hidden') && $id('aplMailOn').checked;
    post('stage', { id: current.id, stage: $id('aplStage').value, notes: $id('aplNotes').value,
                    email: email ? '1' : '', emailMessage: email ? $id('aplMailMsg').value : '' }).then(function (res) {
      if (res.status !== 'ok') { $id('aplStageErr').textContent = res.msg; return; }
      render(res.applicant);
      load();
      var r = $id('aplMailRes');
      if (res.mailed === true) { r.className = 'apl-mailres is-ok'; r.textContent = 'Saved and emailed ' + res.applicant.email + '.'; }
      else if (res.mailed === false) { r.className = 'apl-mailres is-bad'; r.textContent = 'Saved, but the email could not be sent: ' + (res.mailError || 'unknown error') + '.'; }
    });
  });

  /* ------------------------------------------------------------ hire */
  var MONEY = /^\d{1,8}(\.\d{1,2})?$/;

  function fillDepts() {
    var c = $id('aplHCompany').value;
    options($id('aplHDept'), hireOpts.departments.filter(function (d) { return String(d.company) === c; }), '—', '');
    fillPositions();
  }
  function fillPositions() {
    var d = $id('aplHDept').value;
    options($id('aplHPos'), hireOpts.positions.filter(function (p) { return String(p.department) === d; }), '—', '');
    fillSuperiors();
  }
  /** superiors in the chosen department first, then everyone else */
  function fillSuperiors() {
    var sel = $id('aplHSup'), keep = sel.value || 'N/A', d = $id('aplHDept').value;
    var mine = hireOpts.superiors.filter(function (s) { return d && String(s.department) === d; });
    var rest = hireOpts.superiors.filter(function (s) { return !(d && String(s.department) === d); });
    var opt = function (s) { return '<option value="' + esc(s.id) + '">' + esc(s.name) + '</option>'; };
    sel.innerHTML = '<option value="N/A">None (N/A)</option>'
      + (mine.length ? '<optgroup label="In this department">' + mine.map(opt).join('') + '</optgroup>' : '')
      + (rest.length ? '<optgroup label="' + (mine.length ? 'Other departments' : 'Everyone') + '">' + rest.map(opt).join('') + '</optgroup>' : '');
    sel.value = keep;
    if (sel.value !== keep) { sel.value = 'N/A'; }
  }

  function hireErr(el, msg) {
    var f = el.closest('.wd-field'), e = f.querySelector('.apl-hire__ferr');
    el.classList.add('is-invalid');
    if (!e) { e = document.createElement('small'); e.className = 'apl-hire__ferr'; f.appendChild(e); }
    e.textContent = msg;
  }
  function hireClear(el) {
    var f = el.closest('.wd-field'), e = f && f.querySelector('.apl-hire__ferr');
    el.classList.remove('is-invalid');
    if (e) { e.remove(); }
  }
  /** the same checks the server makes, shown under each field */
  function hireCheck() {
    var form = $id('aplHireForm'), bad = [];
    Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), hireClear);
    Array.prototype.forEach.call(form.querySelectorAll('[required]'), function (el) {
      if (!String(el.value || '').trim()) { bad.push([el, 'Required.']); }
    });
    ['aplHBasic', 'aplHAllow', 'aplHRate'].forEach(function (id) {
      var el = $id(id), v = el.value.replace(/,/g, '').trim();
      if (v && !MONEY.test(v)) { bad.push([el, 'A number, e.g. 18000 or 103.45']); }
    });
    bad.forEach(function (b) { hireErr(b[0], b[1]); });
    if (bad.length) { bad[0][0].focus(); }
    return !bad.length;
  }

  function hireMode(done) {
    $id('aplHireForm').classList.toggle('lcy-hidden', done);
    $id('aplHireDone').classList.toggle('lcy-hidden', !done);
    $id('aplHireSave').classList.toggle('lcy-hidden', done);
    $id('aplHireFootNote').classList.toggle('lcy-hidden', done);
    var c = $id('aplHireCancel');
    c.textContent = done ? 'Done' : 'Cancel';
    c.className = 'wd-btn ' + (done ? 'wd-btn--primary' : 'wd-btn--ghost');
  }

  function openHire() {
    var go = function () {
      var f = $id('aplHireForm');
      f.reset();
      Array.prototype.forEach.call(f.querySelectorAll('.is-invalid'), hireClear);
      $id('aplHireErr').textContent = '';
      $id('aplHireWelcomeRow').classList.toggle('lcy-hidden', !BOOT.canMail);
      $id('aplHireWelcome').checked = !!BOOT.canMail;
      hireMode(false);
      $id('aplHireAv').innerHTML = face(current);
      $id('aplHireAv').classList.toggle('has-photo', !!current.hasPhoto);
      $id('aplHireTitle').textContent = current.name;
      $id('aplHireSub').textContent = 'Applied for ' + (current.opening || 'any position') + ' · ' + (STAGES[current.stage] || current.stage);
      $id('aplHireNotePhoto').hidden = !current.hasPhoto;
      var o = hireOpts;
      options($id('aplHCompany'), o.companies, o.companies.length > 1 ? '—' : null, o.companies.length === 1 ? o.companies[0].id : '');
      options($id('aplHClass'), o.classes, null, o.trainee);
      options($id('aplHAgency'), o.agencies, '—', '');
      options($id('aplHHmo'), o.hmos, '—', '');
      $id('aplHDate').value = o.today;
      $id('aplHSup').innerHTML = '';
      fillDepts();
      $('#aplHireModal').modal('show');
    };
    if (hireOpts) { go(); return; }
    post('hire_options', {}).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      hireOpts = res.options;
      go();
    });
  }

  function showHired(res) {
    var cred = function (label, value, icon) {
      return '<div class="apl-cred"><i class="fa-solid ' + icon + '"></i><div><small>' + label + '</small><code>' + esc(value) + '</code></div>'
        + '<button type="button" class="apl-cred__copy" data-copy="' + esc(value) + '" aria-label="Copy ' + label + '"><i class="fa-regular fa-copy"></i></button></div>';
    };
    $id('aplHireDone').innerHTML = '<div class="apl-hire__badge"><i class="fa-solid fa-check"></i></div>'
      + '<h4>' + esc(current.name) + ' is now an employee</h4>'
      + '<p class="apl-hire__id">Employee no. <b>' + esc(res.empId) + '</b></p>'
      + '<div class="apl-creds">' + cred('Username', res.username, 'fa-user') + cred('Temporary password', res.password, 'fa-key') + '</div>'
      + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm apl-cred__both" data-copy="' + esc('Username: ' + res.username + '\nTemporary password: ' + res.password) + '">'
      + '<i class="fa-regular fa-copy"></i> Copy both</button>'
      + (res.mailed === true ? '<p class="apl-mailres is-ok"><i class="fa-regular fa-envelope"></i> Welcome email sent to ' + esc(current.email) + ' (without the password).</p>'
        : res.mailed === false ? '<p class="apl-mailres is-bad"><i class="fa-solid fa-envelope-circle-check"></i> The welcome email could not be sent: ' + esc(res.mailError || 'unknown error') + '</p>' : '')
      + '<p class="apl-hire__warn"><i class="fa-solid fa-triangle-exclamation"></i> Give these to the new hire privately. The password is shown <b>only now</b> — it is not stored anywhere in readable form, and they must set their own at first sign-in.</p>';
    hireMode(true);
  }

  $id('aplHCompany').addEventListener('change', fillDepts);
  $id('aplHDept').addEventListener('change', fillPositions);
  $id('aplHireForm').addEventListener('input', function (ev) { hireClear(ev.target); $id('aplHireErr').textContent = ''; });
  $id('aplHireForm').addEventListener('change', function (ev) { hireClear(ev.target); });
  $id('aplHireSave').addEventListener('click', function () {
    if (!hireCheck()) { $id('aplHireErr').textContent = 'Please fill in the highlighted fields.'; return; }
    var btn = this, d = L.formData($id('aplHireForm'));
    d.id = current.id;
    d.welcome = BOOT.canMail && $id('aplHireWelcome').checked ? '1' : '';
    ['basic', 'allowance', 'hourly'].forEach(function (k) { d[k] = String(d[k] || '').replace(/,/g, '').trim(); });
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating…';
    post('hire', d).then(function (res) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-user-plus"></i> Create employee';
      if (res.status !== 'ok') { $id('aplHireErr').textContent = res.msg; return; }
      $id('aplHireErr').textContent = '';
      showHired(res);
      render(res.applicant);
      load();
    });
  });
  $id('aplHireDone').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-copy]');
    if (!b || !navigator.clipboard) { return; }
    var was = b.innerHTML;
    navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function () {
      b.innerHTML = '<i class="fa-solid fa-check"></i>' + (b.classList.contains('apl-cred__both') ? ' Copied' : '');
      setTimeout(function () { b.innerHTML = was; }, 1400);
    });
  });
  // the Hire dialog opens over the profile: dim the profile, and keep it scrollable after Hire closes
  $('#aplHireModal').on('shown.bs.modal', function () { $('.modal-backdrop').last().addClass('apl-hire-backdrop'); })
    .on('hidden.bs.modal', function () { if ($('#aplModal').hasClass('in')) { $('body').addClass('modal-open'); } });

  /* ------------------------------------------------------------ email setup (Brevo) */
  var CHECK_ICON = { ok: 'fa-circle-check', warn: 'fa-triangle-exclamation', bad: 'fa-circle-xmark' };
  function mailStatus() {
    var list = $id('aplMailChecks');
    list.innerHTML = '<li class="is-wait"><i class="fa-solid fa-spinner fa-spin"></i> Checking with Brevo…</li>';
    post('mail_status', {}).then(function (res) {
      if (res.status !== 'ok') { list.innerHTML = '<li class="is-bad"><i class="fa-solid fa-circle-xmark"></i> ' + esc(res.msg) + '</li>'; return; }
      var m = res.mail;
      list.innerHTML = m.checks.map(function (c) {
        return '<li class="is-' + esc(c.level) + '"><i class="fa-solid ' + (CHECK_ICON[c.level] || 'fa-circle') + '"></i> ' + esc(c.text) + '</li>';
      }).join('')
        + (function () {
          var bad = m.checks.some(function (c) { return c.level === 'bad'; });
          var tone = !m.enabled ? 'bad' : (bad ? 'warn' : 'ok');
          return '<li class="apl-checks__sum is-' + tone + '">' + (!m.enabled
            ? '<b>Email is off on this server.</b> Fix the red items, then press "Check again".'
            : bad ? '<b>Email is switched on, but Brevo will refuse it</b> until the red items are fixed.'
                  : '<b>Email is on.</b> Send a test below to confirm it arrives.') + '</li>';
        })();
    });
  }
  $id('aplMailBtn').addEventListener('click', function () {
    $id('aplMailTestRes').textContent = '';
    if (!$id('aplMailTo').value) { $id('aplMailTo').value = BOOT.myEmail || ''; }
    mailStatus();
    $('#aplMailModal').modal('show');
  });
  $id('aplMailRecheck').addEventListener('click', mailStatus);
  $id('aplMailTestForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $id('aplMailTestBtn'), r = $id('aplMailTestRes');
    btn.disabled = true;
    r.className = 'apl-mailres';
    r.textContent = 'Sending…';
    post('mail_test', { to: $id('aplMailTo').value.trim() }).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'ok') { r.className = 'apl-mailres is-bad'; r.textContent = res.msg; return; }
      r.className = 'apl-mailres ' + (res.sent ? 'is-ok' : 'is-bad');
      r.textContent = res.sent ? 'Brevo accepted it — check the inbox (and Spam) of ' + $id('aplMailTo').value.trim() + '.'
        : 'Not sent: ' + (res.error || 'unknown error');
    });
  });

  /* ------------------------------------------------------------ openings */
  function renderOpenings(list) {
    BOOT.openings = list;
    options($id('aplFOpening'), [{ id: 'any', name: 'Any position (not specified)' }].concat(list.map(function (o) { return { id: o.id, name: o.title + (o.open ? '' : ' (closed)') }; })),
            'All positions', filter.opening);
    $id('aplOpeningsList').innerHTML = list.length ? '<table class="wd-table lcy-table"><tbody>' + list.map(function (o, i) {
      return '<tr><td><b>' + esc(o.title) + '</b><br><span class="lcy-muted">' + esc(o.location) + '</span></td>'
        + '<td>' + (o.open ? '<span class="wd-pill wd-pill--ok">Open</span>' : '<span class="wd-pill wd-pill--info">Closed</span>') + '</td>'
        + '<td>' + o.applicants + ' applicant' + (o.applicants === 1 ? '' : 's') + '</td>'
        + '<td><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-edit="' + i + '">Edit</button></td></tr>';
    }).join('') + '</tbody></table>' : '<div class="lcy-empty">No openings yet — add one below.</div>';
    Array.prototype.forEach.call($id('aplOpeningsList').querySelectorAll('[data-edit]'), function (b) {
      b.addEventListener('click', function () { editOpening(list[+b.getAttribute('data-edit')]); });
    });
  }

  function editOpening(o) {
    var f = $id('aplOpeningForm');
    f.reset();
    $id('aplOpeningErr').textContent = '';
    f.elements.id.value = o ? o.id : 0;
    f.elements.title.value = o ? o.title : '';
    f.elements.location.value = o ? o.location : '';
    f.elements.description.value = o ? o.description : '';
    f.elements.open.checked = o ? o.open : true;
    $id('aplOpeningFormTitle').textContent = o ? 'Edit opening' : 'Add an opening';
  }

  $id('aplOpeningsBtn').addEventListener('click', function () { editOpening(null); $('#aplOpeningsModal').modal('show'); });
  $id('aplOpeningNew').addEventListener('click', function () { editOpening(null); });
  $id('aplOpeningForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = L.formData(ev.target);
    if (!d.open) { d.open = '0'; }
    post('save_opening', d).then(function (res) {
      if (res.status !== 'ok') { $id('aplOpeningErr').textContent = res.msg; return; }
      renderOpenings(res.openings);
      editOpening(null);
    });
  });

  /* ------------------------------------------------------------ wire up */
  var link = new URL('apply', window.location.href).href;   // the clean public address (.htaccess)
  $id('aplLink').textContent = link;
  $id('aplCopy').addEventListener('click', function () {
    var b = this;
    (navigator.clipboard ? navigator.clipboard.writeText(link) : Promise.reject()).then(function () {
      b.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
      setTimeout(function () { b.innerHTML = '<i class="fa-regular fa-copy"></i> Copy'; }, 1600);
    }, function () { window.prompt('Copy the link:', link); });
  });
  var t = null;
  $id('aplQ').addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { filter.q = $id('aplQ').value.trim(); load(); }, 250); });
  $id('aplFOpening').addEventListener('change', function () { filter.opening = this.value; load(); });
  $id('aplFilters').addEventListener('submit', function (ev) { ev.preventDefault(); });

  renderOpenings(BOOT.openings || []);
  load();
  if (BOOT.id) { open(BOOT.id); }
})();
