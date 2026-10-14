/* ==========================================================================
   separation.js  —  screen logic for separation.php (Separation & Lifecycle).
   Search a person, show their history and separation records, record a
   separation or edit one (rehire flag, reason, last day, clearance).
   ========================================================================== */
(function () {
  'use strict';

  var L = window.LCY, esc = L.esc;
  var current = null;   // timeline answer for the person on screen

  function $id(id) { return document.getElementById(id); }

  var EVENT = {
    hired:          function (e) { return ['is-hired', 'Hired' + (e.to ? ' as ' + e.to : '')]; },
    classification: function (e) { return ['', 'Classification: ' + (e.from || '?') + ' → ' + (e.to || '?')]; },
    status:         function (e) { return ['', 'Status: ' + (e.from || '?') + ' → ' + (e.to || '?')]; },
    separated:      function (e) { return ['is-separated', 'Separated: ' + (e.to || '')]; },
    rehire_flag:    function (e) { return ['', (e.to || 'Rehire flag changed')]; },
    coe_issued:     function (e) { return ['is-coe', 'COE issued' + (e.to ? ' · ' + e.to : '')]; },
  };

  /* ------------------------------------------------------------ one person */
  function load(empId) {
    L.post('timeline', { emp: empId }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      current = res;
      render();
    });
  }

  function render() {
    var emp = current.employee;
    $id('lcyPerson').classList.remove('lcy-hidden');
    $id('lcyName').textContent = emp.name;
    $id('lcyMeta').innerHTML = esc(emp.empId)
      + (emp.position ? ' · ' + esc(emp.position) : '')
      + (emp.classDesc ? ' <span class="wd-pill wd-pill--info">' + esc(emp.classDesc) + '</span>' : '')
      + ' ' + L.statusPill(emp.status)
      + (emp.hired ? ' · hired ' + esc(L.fmtDate(emp.hired)) : '');
    $id('lcySeparateBtn').classList.toggle('lcy-hidden', emp.separated);

    var ev = current.events;
    $id('lcyTimeline').innerHTML = ev.length ? ev.map(function (e) {
      var f = (EVENT[e.type] || function () { return ['', e.type]; })(e);
      return '<li class="' + f[0] + '"><div class="t-date">' + esc(L.fmtDate(e.date)) + '</div>'
        + '<div class="t-what">' + esc(f[1]) + '</div>'
        + (e.notes ? '<div class="t-note">' + esc(e.notes) + '</div>' : '')
        + (e.by ? '<div class="t-note lcy-muted">by ' + esc(e.by) + '</div>' : '') + '</li>';
    }).join('') : '<li class="lcy-muted">No history yet.</li>';

    var seps = current.separations;
    $id('lcySeps').innerHTML = seps.length ? seps.map(sepItem).join('')
      : '<div class="lcy-empty">' + (emp.separated ? 'No separation record — use Record separation.' : 'Currently employed.') + '</div>';
    bindEdit($id('lcySeps'), seps);
  }

  function sepItem(s, i) {
    return '<div class="lcy-item">'
      + '<div class="lcy-item__head"><b>' + esc(s.typeLabel) + ' · last day ' + esc(L.fmtDate(s.lastDay)) + '</b>'
      + '<span class="lcy-actions">' + L.rehirePill(s.rehire)
      + ' <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-edit="' + i + '"><i class="fa-solid fa-pen"></i> Edit</button></span></div>'
      + '<dl class="lcy-facts">'
      + '<div><dt>Reason</dt><dd>' + (s.reason ? esc(s.reason) : '<span class="lcy-muted">not given yet</span>') + '</dd></div>'
      + (s.rehireNote ? '<div><dt>Rehire note</dt><dd>' + esc(s.rehireNote) + '</dd></div>' : '')
      + '<div><dt>Clearance</dt><dd>' + (s.clearance ? 'Completed' : 'Not yet') + '</dd></div>'
      + '</dl></div>';
  }

  function bindEdit(box, list) {
    Array.prototype.forEach.call(box.querySelectorAll('[data-edit]'), function (b) {
      b.addEventListener('click', function () { openModal(list[+b.getAttribute('data-edit')]); });
    });
  }

  /* ------------------------------------------------------------ open records */
  function loadOpen() {
    L.post('sep_open', {}).then(function (res) {
      var box = $id('lcyOpen');
      if (res.status !== 'ok') { box.innerHTML = '<div class="lcy-empty">' + esc(res.msg) + '</div>'; return; }
      if (!res.open.length) { box.innerHTML = '<div class="lcy-empty">All separation records are complete.</div>'; return; }
      box.innerHTML = '<table class="wd-table lcy-table"><thead><tr><th>Employee</th><th>Type</th><th>Last day</th><th>Reason</th><th>Rehire</th><th></th></tr></thead><tbody>'
        + res.open.map(function (s) {
          return '<tr><td><b>' + esc(s.name) + '</b><br><span class="lcy-muted">' + esc(s.empId) + '</span></td>'
            + '<td>' + esc(s.typeLabel) + '</td><td>' + esc(L.fmtDate(s.lastDay)) + '</td>'
            + '<td>' + (s.reason ? esc(s.reason) : '<span class="lcy-muted">—</span>') + '</td>'
            + '<td>' + L.rehirePill(s.rehire) + '</td>'
            + '<td><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-open="' + esc(s.empId) + '">Open</button></td></tr>';
        }).join('') + '</tbody></table>';
      Array.prototype.forEach.call(box.querySelectorAll('[data-open]'), function (b) {
        b.addEventListener('click', function () { load(b.getAttribute('data-open')); window.scrollTo({ top: 0, behavior: 'smooth' }); });
      });
    });
  }

  /* ------------------------------------------------------------ modal */
  function openModal(sep) {
    var f = $id('lcySepForm');
    f.reset();
    $id('lcySepErr').textContent = '';
    var editing = !!sep;
    $id('lcySepTitle').textContent = editing ? 'Edit separation record' : 'Record separation — ' + current.employee.name;
    $id('lcyTypeField').classList.toggle('lcy-hidden', editing);
    $id('lcySepWarn').classList.toggle('lcy-hidden', editing);
    f.elements.id.value = editing ? sep.id : '';
    f.elements.lastDay.value = editing ? sep.lastDay : L.boot.today;
    f.elements.reason.value = editing ? sep.reason : '';
    f.elements.rehireNote.value = editing ? sep.rehireNote : '';
    f.elements.clearance.checked = editing ? sep.clearance : false;
    Array.prototype.forEach.call(f.querySelectorAll('[name=rehire]'), function (r) { r.checked = r.value === (editing ? sep.rehire : 'review'); });
    $('#lcySepModal').modal('show');
  }

  function save() {
    var f = $id('lcySepForm'), d = L.formData(f), btn = $id('lcySepSave');
    var editing = d.id !== '';
    if (!editing) { d.emp = current.employee.empId; }
    btn.disabled = true;
    L.post(editing ? 'sep_update' : 'separate', d).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'ok') { $id('lcySepErr').textContent = res.msg; return; }
      $('#lcySepModal').modal('hide');
      load(editing ? current.employee.empId : d.emp);
      loadOpen();
    });
  }

  /* ------------------------------------------------------------ wire up */
  L.employeeSearch($id('lcySearch'), $id('lcyResults'), function (e) { load(e.empId); });
  $id('lcySeparateBtn').addEventListener('click', function () { openModal(null); });
  $id('lcySepSave').addEventListener('click', save);
  loadOpen();
  if (L.boot.emp) { load(L.boot.emp); }
})();
