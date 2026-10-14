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
          return '<tr class="apl-row" data-id="' + a.id + '" tabindex="0"><td><b>' + esc(a.name) + '</b>' + rehireBadge(a.rehire)
            + '<br><span class="lcy-muted">' + esc(a.email) + ' · ' + esc(a.mobile) + '</span></td>'
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
  function dl(pairs) {
    var rows = pairs.filter(function (p) { return p[1] !== null && p[1] !== undefined && String(p[1]).trim() !== ''; });
    return rows.length ? '<dl class="lcy-facts">' + rows.map(function (p) { return '<div><dt>' + esc(p[0]) + '</dt><dd>' + esc(p[1]) + '</dd></div>'; }).join('') + '</dl>'
      : '<p class="lcy-muted">—</p>';
  }

  function open(id) {
    post('detail', { id: id }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      render(res.applicant);
      $('#aplModal').modal('show');
    });
  }

  function render(a) {
    current = a;
    var p = a.profile;
    $id('aplTitle').innerHTML = esc(a.name) + ' ' + stagePill(a.stage);
    var closed = a.stage === 'hired' || a.stage === 'rejected' || a.stage === 'withdrawn';
    var actions = '<div class="lcy-actions" style="margin-bottom:6px">'
      + (a.hasResume ? '<a class="wd-btn wd-btn--ghost wd-btn--sm" href="query/applicant-resume.php?id=' + a.id + '" rel="noopener"><i class="fa-solid fa-download"></i> Resume</a>' : '')
      + (BOOT.canHire && !closed ? '<button type="button" class="wd-btn wd-btn--success wd-btn--sm" id="aplHireBtn"><i class="fa-solid fa-user-plus"></i> Hire</button>' : '')
      + (a.stage === 'rejected' || a.stage === 'withdrawn' ? '<button type="button" class="wd-btn wd-btn--danger wd-btn--sm" id="aplDeleteBtn"><i class="fa-solid fa-trash"></i> Delete application</button>' : '')
      + (a.empId ? '<span class="wd-pill wd-pill--ok">Employee ' + esc(a.empId) + '</span>'
          + (BOOT.canLife ? ' <a class="wd-btn wd-btn--ghost wd-btn--sm" href="separation?emp=' + encodeURIComponent(a.empId) + '">Lifecycle</a>' : '') : '')
      + '</div>';
    if (BOOT.canHire === false && !closed) {
      actions += '<p class="lcy-muted">Hiring creates a WeDo account, so it also needs the "Enroll Employee" right.</p>';
    }

    var matches = a.matches.length ? a.matches.map(function (m) {
      var cls = m.rehire === 'no' ? ' is-no' : (m.rehire === 'review' ? ' is-review' : '');
      return '<div class="apl-match' + cls + '"><b>' + esc(m.name) + '</b> '
        + (m.source === 'ams' ? '<span class="wd-pill wd-pill--info">Archive</span>' : L.statusPill(m.status))
        + (m.strong ? ' <span class="wd-pill wd-pill--warn">Same birth date</span>' : ' <span class="lcy-muted">(same name)</span>')
        + (m.rehire ? ' ' + L.rehirePill(m.rehire) : '')
        + dl([['Position', m.position], ['Employed', [m.hired ? L.fmtDate(m.hired) || m.hired : '', m.lastDay ? L.fmtDate(m.lastDay) || m.lastDay : ''].filter(Boolean).join(' – ')],
              ['Separation', m.separation], ['Reason for leaving', m.reason], ['Rehire note', m.rehireNote], ['Derogatory records', m.derogatory]])
        + '</div>';
    }).join('') : '<p class="lcy-muted">No former employee or archive record with this name.</p>';

    var edu = a.education.filter(function (e) { return e.School; });
    var fam = a.family;
    $id('aplBody').innerHTML = actions + '<div class="apl-dl">'
      + '<h5>Application</h5>' + dl([['Position', a.opening || 'Any position'], ['Registered', L.fmtDateTime(a.appliedAt)], ['Heard about us', p.Source],
                                      ['Data Privacy consent', L.fmtDateTime(p.ConsentAt)]])
      + '<h5>Worked here before?</h5>' + matches
      + '<h5>Personal</h5>' + dl([['Birth date', L.fmtDate(a.birthDate)], ['Gender', p.Gender], ['Civil status', p.CivilStatus],
                                   ['Citizenship', p.Citizenship], ['Religion', p.Religion]])
      + '<h5>Contact &amp; address</h5>' + dl([['Email', a.email], ['Mobile', a.mobile], ['Home phone', p.HomePhone],
          ['Address', [p.Street, p.Barangay, p.City, p.Province, p.Zip, p.Country].filter(Boolean).join(', ')]])
      + '<h5>Government IDs</h5>' + dl([['SSS', p.SSS], ['TIN', p.TIN], ['PhilHealth', p.PhilHealth], ['Pag-IBIG', p.PagIbig], ['UMID', p.UMID],
          ['Passport', [p.PassportNo, p.PassportExpiry ? 'exp. ' + L.fmtDate(p.PassportExpiry) : '', p.PassportIssuer].filter(Boolean).join(' · ')]])
      + '<h5>Education</h5>' + (edu.length ? dl(edu.map(function (e) {
          return [e.Program, e.School + ([e.YearStarted, e.YearEnded].filter(Boolean).length ? ' (' + [e.YearStarted, e.YearEnded].filter(Boolean).join('–') + ')' : '')];
        })) : '<p class="lcy-muted">—</p>')
      + '<h5>Most recent job</h5>' + dl([['Company', p.PrevCompany], ['Position', p.PrevPosition], ['Department', p.PrevDept], ['Start', L.fmtDate(p.PrevStart)]])
      + '<h5>Family &amp; emergency contacts</h5>' + (fam.length ? '<table class="wd-table lcy-table"><tbody>' + fam.map(function (f) {
          return '<tr><td><b>' + esc(f.Name) + '</b>' + (+f.Emergency ? ' <span class="wd-pill wd-pill--warn">Emergency</span>' : '') + '</td><td>' + esc(f.Relationship)
            + '</td><td>' + esc(f.Contact || '') + '</td><td>' + esc(f.Address || '') + '</td></tr>';
        }).join('') + '</tbody></table>' : '<p class="lcy-muted">—</p>')
      + '<h5>History</h5><ul class="lcy-timeline" style="padding-left:0">' + a.events.map(function (e) {
          return '<li><div class="t-date">' + esc(L.fmtDateTime(e.at)) + '</div><div class="t-what">'
            + esc(e.from && e.from !== e.to ? (STAGES[e.from] || e.from) + ' → ' + e.toLabel : (e.from ? 'Note' : e.toLabel)) + '</div>'
            + (e.notes ? '<div class="t-note">' + esc(e.notes) + '</div>' : '') + '<div class="t-note lcy-muted">' + esc(e.by) + '</div></li>';
        }).join('') + '</ul></div>';

    var sel = $id('aplStage');
    sel.innerHTML = Object.keys(STAGES).filter(function (k) { return k !== 'hired' || a.stage === 'hired'; }).map(function (k) {
      return '<option value="' + k + '"' + (k === a.stage ? ' selected' : '') + '>' + esc(STAGES[k]) + '</option>';
    }).join('');
    $id('aplStageForm').classList.toggle('lcy-hidden', a.stage === 'hired');
    $id('aplNotes').value = '';
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

  $id('aplStageForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    post('stage', { id: current.id, stage: $id('aplStage').value, notes: $id('aplNotes').value }).then(function (res) {
      if (res.status !== 'ok') { $id('aplStageErr').textContent = res.msg; return; }
      render(res.applicant);
      load();
    });
  });

  /* ------------------------------------------------------------ hire */
  function fillDepts() {
    var c = $id('aplHCompany').value;
    options($id('aplHDept'), hireOpts.departments.filter(function (d) { return String(d.company) === c; }), '—', '');
    fillPositions();
  }
  function fillPositions() {
    var d = $id('aplHDept').value;
    options($id('aplHPos'), hireOpts.positions.filter(function (p) { return String(p.department) === d; }), '—', '');
  }

  function openHire() {
    var go = function () {
      var f = $id('aplHireForm');
      f.reset();
      $id('aplHireErr').textContent = '';
      $id('aplHireDone').classList.add('lcy-hidden');
      $id('aplHireSave').classList.remove('lcy-hidden');
      $id('aplHireCancel').textContent = 'Cancel';
      $id('aplHireTitle').textContent = 'Hire ' + current.name;
      var o = hireOpts;
      options($id('aplHCompany'), o.companies, o.companies.length > 1 ? '—' : null, o.companies.length === 1 ? o.companies[0].id : '');
      options($id('aplHSup'), [{ id: 'N/A', name: 'N/A' }].concat(o.superiors), null, 'N/A');
      options($id('aplHClass'), o.classes, null, o.trainee);
      options($id('aplHAgency'), o.agencies, '—', '');
      options($id('aplHHmo'), o.hmos, '—', '');
      $id('aplHDate').value = o.today;
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

  $id('aplHCompany').addEventListener('change', fillDepts);
  $id('aplHDept').addEventListener('change', fillPositions);
  $id('aplHireSave').addEventListener('click', function () {
    var btn = this, d = L.formData($id('aplHireForm'));
    d.id = current.id;
    btn.disabled = true;
    post('hire', d).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'ok') { $id('aplHireErr').textContent = res.msg; return; }
      $id('aplHireErr').textContent = '';
      $id('aplHireSave').classList.add('lcy-hidden');
      $id('aplHireCancel').textContent = 'Done';
      var box = $id('aplHireDone');
      box.innerHTML = '<b>' + esc(current.name) + ' is now employee ' + esc(res.empId) + '.</b><br>'
        + 'Username: <code>' + esc(res.username) + '</code><br>Temporary password: <code>' + esc(res.password) + '</code><br>'
        + '<span>Give these to the new hire privately and ask them to change the password after signing in. '
        + 'The password is shown only now — it is not stored anywhere in readable form.</span>';
      box.classList.remove('lcy-hidden');
      render(res.applicant);
      load();
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
  var link = new URL('apply.php', window.location.href).href;
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
