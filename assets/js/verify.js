/* ==========================================================================
   verify.js  —  screen logic for verify.php (Employment Verification).
   Look a person up in WeDo + the AMS archive, show what HR may confirm and
   the rehire flag, and log each verification call.
   ========================================================================== */
(function () {
  'use strict';

  var L = window.LCY, esc = L.esc, BOOT = L.boot;
  var lastQuery = '';

  function $id(id) { return document.getElementById(id); }
  function fact(label, value) {
    return value ? '<div><dt>' + esc(label) + '</dt><dd>' + esc(value) + '</dd></div>' : '';
  }

  function callsBlock(calls) {
    if (!calls.length) { return '<div class="lcy-calls lcy-muted">No verification calls logged yet.</div>'; }
    return '<div class="lcy-calls"><b>' + calls.length + (calls.length === 10 ? '+' : '') + ' earlier call' + (calls.length > 1 ? 's' : '') + '</b><ul>'
      + calls.map(function (c) {
        return '<li>' + esc(L.fmtDate(c.at)) + ' — ' + esc(c.company) + ' (' + esc(c.caller) + '), ' + esc(c.purposeLabel)
          + (c.by ? ' · ' + esc(c.by) : '') + '</li>';
      }).join('') + '</ul></div>';
  }

  function logBtn(kind, id, name) {
    return '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-log="' + kind + '" data-id="' + esc(id)
      + '" data-name="' + esc(name) + '"><i class="fa-solid fa-phone"></i> Log call</button>';
  }

  /* ------------------------------------------------------------ search */
  function search(q) {
    lastQuery = q;
    L.post('verify_lookup', { q: q }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      $id('verResults').classList.remove('lcy-hidden');
      $id('verEmpCount').textContent = res.employees.length + ' found';
      $id('verAmsCount').textContent = res.archive.length + ' found';

      $id('verEmployees').innerHTML = res.employees.length ? res.employees.map(function (e) {
        var tenure = (e.hired ? L.fmtDate(e.hired) : '?') + ' – ' + (e.separated ? (e.lastDay ? L.fmtDate(e.lastDay) : '?') : 'present');
        return '<div class="lcy-item"><div class="lcy-item__head"><span><b>' + esc(e.name) + '</b> <span class="lcy-muted">' + esc(e.empId) + '</span> '
          + L.statusPill(e.status) + ' ' + (e.separated ? L.rehirePill(e.rehire) : '') + '</span>' + logBtn('emp', e.empId, e.name) + '</div>'
          + '<dl class="lcy-facts">' + fact('Employed', tenure) + fact('Last position', e.position) + fact('Classification', e.classDesc)
          + fact('Separation', e.separation) + fact('Reason for leaving', e.reason) + fact('Rehire note', e.rehireNote)
          + (e.clearance === null ? '' : fact('Clearance', e.clearance ? 'Completed' : 'Not completed')) + '</dl>'
          + callsBlock(e.calls) + '</div>';
      }).join('') : '<div class="lcy-empty">No WeDo record matches.</div>';

      $id('verArchive').innerHTML = res.archive.length ? res.archive.map(function (a) {
        return '<div class="lcy-item"><div class="lcy-item__head"><span><b>' + esc(a.name) + '</b> <span class="wd-pill wd-pill--info">Archive</span></span>'
          + logBtn('ams', a.amsId, a.name) + '</div>'
          + '<dl class="lcy-facts">' + fact('Employed', [a.from, a.to].filter(Boolean).join(' – ')) + fact('Position', a.position)
          + fact('Status', a.status) + fact('Reason for leaving', a.reason) + fact('Derogatory records', a.derogatory)
          + fact('Clearance', a.clearance) + fact('Remarks', a.remarks) + '</dl>'
          + callsBlock(a.calls) + '</div>';
      }).join('') : '<div class="lcy-empty">No archived record matches.</div>';

      Array.prototype.forEach.call(document.querySelectorAll('[data-log]'), function (b) {
        b.addEventListener('click', function () { openLog(b.getAttribute('data-log'), b.getAttribute('data-id'), b.getAttribute('data-name')); });
      });
    });
  }

  /* ------------------------------------------------------------ recent */
  function loadRecent() {
    L.post('verify_recent', {}).then(function (res) {
      var box = $id('verRecent');
      if (res.status !== 'ok') { box.innerHTML = '<div class="lcy-empty">' + esc(res.msg) + '</div>'; return; }
      if (!res.calls.length) { box.innerHTML = '<div class="lcy-empty">No calls logged yet.</div>'; return; }
      var lbl = BOOT.disclose || {};
      box.innerHTML = '<table class="wd-table lcy-table"><thead><tr><th>When</th><th>About</th><th>Caller</th><th>Purpose</th><th>Confirmed</th><th>Handled by</th></tr></thead><tbody>'
        + res.calls.map(function (c) {
          return '<tr><td>' + esc(L.fmtDateTime(c.at)) + '</td><td><b>' + esc(c.subject) + '</b>' + (c.amsId ? ' <span class="lcy-muted">(archive)</span>' : '') + '</td>'
            + '<td>' + esc(c.caller) + '<br><span class="lcy-muted">' + esc(c.company) + (c.contact ? ' · ' + esc(c.contact) : '') + '</span></td>'
            + '<td>' + esc(c.purposeLabel) + '</td>'
            + '<td>' + esc(c.disclosed.map(function (k) { return lbl[k] || k; }).join(', ') || '—')
            + (c.consent ? '<br><span class="wd-pill wd-pill--ok">Consent confirmed</span>' : '') + '</td>'
            + '<td>' + esc(c.by) + '</td></tr>';
        }).join('') + '</tbody></table>';
    });
  }

  /* ------------------------------------------------------------ log modal */
  function openLog(kind, id, name) {
    var f = $id('verLogForm');
    f.reset();
    $id('verErr').textContent = '';
    f.elements.emp.value = kind === 'emp' ? id : '';
    f.elements.ams.value = kind === 'ams' ? id : '';
    $id('verModalTitle').textContent = 'Log verification call — ' + name;
    $('#verModal').modal('show');
  }

  function saveLog() {
    var btn = $id('verSave');
    btn.disabled = true;
    L.post('verify_log', L.formData($id('verLogForm'))).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'ok') { $id('verErr').textContent = res.msg; return; }
      $('#verModal').modal('hide');
      loadRecent();
      if (lastQuery) { search(lastQuery); }
    });
  }

  /* ------------------------------------------------------------ wire up */
  var sel = $id('verPurpose');
  Object.keys(BOOT.purposes || {}).forEach(function (k) {
    var o = document.createElement('option'); o.value = k; o.textContent = BOOT.purposes[k]; sel.appendChild(o);
  });
  $id('verDisclose').innerHTML = Object.keys(BOOT.disclose || {}).map(function (k) {
    return '<label class="lcy-check"><input type="checkbox" name="disclosed[]" value="' + esc(k) + '"> ' + esc(BOOT.disclose[k]) + '</label>';
  }).join('');
  $id('verForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var q = $id('verQ').value.trim();
    if (q.length >= 2) { search(q); }
  });
  $id('verSave').addEventListener('click', saveLog);
  $id('verCoeForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var box = $id('verCoeResult');
    L.post('coe_check', { controlNo: $id('verCoeNo').value }).then(function (res) {
      if (res.status !== 'ok') { box.innerHTML = '<p class="lcy-modal-error">' + esc(res.msg) + '</p>'; return; }
      var c = res.coe;
      if (!c) {
        box.innerHTML = '<p class="lcy-note" style="margin-top:12px;background:var(--danger-bg);color:var(--danger-text)">'
          + '<b>No certificate with this number was issued.</b> The document may be fake — do not confirm it.</p>';
        return;
      }
      var valid = c.status === 'approved';
      box.innerHTML = '<div class="lcy-note" style="margin-top:12px;' + (valid ? 'background:var(--ok-bg);color:var(--ok-text)' : '') + '">'
        + '<b>' + (valid ? 'Genuine' : 'Not valid (' + esc(c.status) + ')') + ':</b> ' + esc(c.controlNo) + ' was issued to <b>' + esc(c.name) + '</b>'
        + (c.approvedAt ? ' on ' + esc(L.fmtDate(c.approvedAt)) : '') + (c.withSalary ? ', with salary' : ', without salary') + '.</div>'
        + '<dl class="lcy-facts">' + fact('Position', c.position) + fact('Hired', L.fmtDate(c.hired)) + fact('Employment now', c.employment)
        + fact('Last day', L.fmtDate(c.lastDay)) + fact('Purpose stated', c.purpose) + fact('First printed', L.fmtDate(c.printedAt)) + '</dl>';
    });
  });
  loadRecent();
})();
