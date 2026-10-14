/* ==========================================================================
   coe.js  —  screen logic for coe.php (Certificate of Employment).
   Employees request and print their own approved certificates; COE
   approvers (BOOT.issuer) work the request queue and issue COEs for anyone.
   The certificate text is present tense for current employees and past
   tense for people who have left. Print shows only #forprint (lifecycle.css).
   ========================================================================== */
(function () {
  'use strict';

  var L = window.LCY, esc = L.esc, BOOT = L.boot;
  var queueStatus = 'pending';
  var shown = null;          // request id in the preview
  var rejecting = null;      // request id in the reject modal

  function $id(id) { return document.getElementById(id); }
  var STATUS = { pending: ['warn', 'Waiting'], approved: ['ok', 'Approved'], rejected: ['danger', 'Rejected'], cancelled: ['info', 'Cancelled'] };
  function statusPill(s) { var x = STATUS[s] || ['info', s]; return '<span class="wd-pill wd-pill--' + x[0] + '">' + esc(x[1]) + '</span>'; }
  function b(s) { return '<span class="coe-val">' + esc(s) + '</span>'; }

  function bindButtons(box, list) {
    var byId = {};
    list.forEach(function (r) { byId[r.id] = r; });
    Array.prototype.forEach.call(box.querySelectorAll('[data-act]'), function (btn) {
      btn.addEventListener('click', function () {
        var r = byId[btn.getAttribute('data-id')], act = btn.getAttribute('data-act');
        if (act === 'view') { preview(r.id); }
        if (act === 'cancel' && confirm('Cancel this request?')) { L.post('coe_cancel', { id: r.id }).then(done); }
        if (act === 'approve') { L.post('coe_decide', { id: r.id, decision: 'approved' }).then(done); }
        if (act === 'reject') { rejecting = r.id; $id('coeRejectWhy').value = ''; $id('coeRejectErr').textContent = ''; $('#coeRejectModal').modal('show'); }
      });
    });
  }

  function done(res) {
    if (res.status !== 'ok') { alert(res.msg); }
    refresh();
  }

  function refresh() { loadMine(); if (BOOT.issuer) { loadQueue(); } }

  /* ------------------------------------------------------------ my requests */
  function loadMine() {
    L.post('coe_mine', {}).then(function (res) {
      var box = $id('coeMine');
      if (res.status !== 'ok') { box.innerHTML = '<div class="lcy-empty">' + esc(res.msg) + '</div>'; return; }
      if (!res.requests.length) { box.innerHTML = '<div class="lcy-empty">No requests yet.</div>'; return; }
      box.innerHTML = '<table class="wd-table lcy-table"><thead><tr><th>Requested</th><th>Purpose</th><th>Salary</th><th>Status</th><th>Control no.</th><th></th></tr></thead><tbody>'
        + res.requests.map(function (r) {
          return '<tr><td>' + esc(L.fmtDate(r.requestedAt)) + '</td><td>' + esc(r.purpose) + '</td><td>' + (r.salary ? 'Yes' : 'No') + '</td>'
            + '<td>' + statusPill(r.status) + (r.remarks ? '<br><span class="lcy-muted">' + esc(r.remarks) + '</span>' : '') + '</td>'
            + '<td>' + esc(r.controlNo || '—') + '</td><td>'
            + (r.status === 'approved' ? '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-act="view" data-id="' + r.id + '"><i class="fa-solid fa-print"></i> View &amp; print</button>' : '')
            + (r.status === 'pending' ? '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-act="cancel" data-id="' + r.id + '">Cancel</button>' : '')
            + '</td></tr>';
        }).join('') + '</tbody></table>';
      bindButtons(box, res.requests);
    });
  }

  $id('coeRequestForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var f = ev.target;
    $id('coeRequestErr').textContent = '';
    L.post('coe_request', L.formData(f)).then(function (res) {
      if (res.status !== 'ok') { $id('coeRequestErr').textContent = res.msg; return; }
      f.reset();
      refresh();
    });
  });

  /* ------------------------------------------------------------ HR queue */
  function loadQueue() {
    L.post('coe_queue', { status: queueStatus }).then(function (res) {
      var box = $id('coeQueue');
      if (res.status !== 'ok') { box.innerHTML = '<div class="lcy-empty">' + esc(res.msg) + '</div>'; return; }
      $id('coePendingN').textContent = res.pending ? '(' + res.pending + ')' : '';
      if (!res.requests.length) { box.innerHTML = '<div class="lcy-empty">Nothing here.</div>'; return; }
      box.innerHTML = '<table class="wd-table lcy-table"><thead><tr><th>Employee</th><th>Purpose</th><th>Salary</th><th>Requested</th><th>Status</th><th></th></tr></thead><tbody>'
        + res.requests.map(function (r) {
          var acts = r.status === 'pending'
            ? '<button type="button" class="wd-btn wd-btn--success wd-btn--sm" data-act="approve" data-id="' + r.id + '"><i class="fa-solid fa-check"></i> Approve</button> '
              + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-act="reject" data-id="' + r.id + '">Reject</button>'
            : (r.status === 'approved' ? '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-act="view" data-id="' + r.id + '"><i class="fa-solid fa-print"></i> View &amp; print</button>' : '');
          return '<tr><td><b>' + esc(r.name) + '</b> ' + L.statusPill(r.empStatus) + '<br><span class="lcy-muted">' + esc(r.empId)
            + (r.channel === 'hr' ? ' · logged by HR' : '') + '</span></td>'
            + '<td>' + esc(r.purpose) + '</td><td>' + (r.salary ? 'Yes' : 'No') + '</td><td>' + esc(L.fmtDateTime(r.requestedAt)) + '</td>'
            + '<td>' + statusPill(r.status) + (r.controlNo ? '<br><span class="lcy-muted">' + esc(r.controlNo) + '</span>' : '')
            + (r.printedAt ? '<br><span class="lcy-muted">printed ' + esc(L.fmtDate(r.printedAt)) + '</span>' : '')
            + (r.remarks ? '<br><span class="lcy-muted">' + esc(r.remarks) + '</span>' : '') + '</td>'
            + '<td>' + acts + '</td></tr>';
        }).join('') + '</tbody></table>';
      bindButtons(box, res.requests);
    });
  }

  if (BOOT.issuer) {
    Array.prototype.forEach.call(document.querySelectorAll('#coeTabs .lcy-tab'), function (t) {
      t.addEventListener('click', function () {
        document.querySelectorAll('#coeTabs .lcy-tab').forEach(function (o) { o.classList.toggle('is-on', o === t); });
        queueStatus = t.getAttribute('data-status');
        loadQueue();
      });
    });

    var issueForm = $id('coeIssueForm');
    L.employeeSearch($id('coeIssueSearch'), $id('coeIssueResults'), function (e) { issueForm.elements.emp.value = e.empId; });
    $id('coeIssueBtn').addEventListener('click', function () {
      issueForm.reset();
      issueForm.elements.emp.value = '';
      $id('coeIssueErr').textContent = '';
      $('#coeIssueModal').modal('show');
    });
    $id('coeIssueSave').addEventListener('click', function () {
      var d = L.formData(issueForm);
      if (!d.emp) { $id('coeIssueErr').textContent = 'Pick the employee from the list.'; return; }
      L.post('coe_issue', d).then(function (res) {
        if (res.status !== 'ok') { $id('coeIssueErr').textContent = res.msg; return; }
        $('#coeIssueModal').modal('hide');
        refresh();
        preview(res.id);
      });
    });
    $id('coeRejectSave').addEventListener('click', function () {
      L.post('coe_decide', { id: rejecting, decision: 'rejected', remarks: $id('coeRejectWhy').value }).then(function (res) {
        if (res.status !== 'ok') { $id('coeRejectErr').textContent = res.msg; return; }
        $('#coeRejectModal').modal('hide');
        refresh();
      });
    });
  }

  /* ------------------------------------------------------------ certificate */
  function preview(id) {
    L.post('coe_cert', { id: id }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      var c = res.cert, who = c.salutation + ' ' + c.lastName, body;
      if (c.separated) {
        body = '<p>This is to certify that ' + b(c.fullName) + ' was connected with WeDo BPO Inc. as ' + b(c.position)
          + ' from ' + b(c.hired) + ' to ' + b(c.lastDay || '—') + '.</p>';
        if (c.salary) {
          body += '<p>' + esc(c.salutation) + ' ' + b(c.lastName) + ' received a gross monthly compensation of Pesos ' + b(c.salary)
            + ', exclusive of overtime pay, bonuses and other salary-related fringe benefits.</p>';
        }
      } else {
        body = '<p>This is to certify that ' + b(c.fullName) + ' is presently connected with WeDo BPO Inc. In ' + esc(c.his)
          + ' capacity as ' + b(c.position) + ', ' + esc(c.salutation) + ' ' + b(c.lastName)
          + ' has been connected with this company since ' + b(c.hired) + '.</p>';
        if (c.salary) {
          body += '<p>' + esc(c.salutation) + ' ' + b(c.lastName) + ' receives a gross monthly compensation of Pesos ' + b(c.salary)
            + ', exclusive of overtime pay, bonuses and other salary-related fringe benefits, of which ' + esc(c.he) + ' is entitled to receive.</p>';
        }
      }
      $id('coeBody').innerHTML = body;
      $id('coeControl').textContent = c.controlNo ? 'Control No. ' + c.controlNo : '';
      $id('coeIssuedFor').innerHTML = 'This certification is issued upon the request of ' + b(c.fullName) + ' for whatever legal purpose it may serve.';
      $id('coeIssuedOn').textContent = 'Issued on ' + c.issued + ', at Pasig City, Philippines.';
      $id('coeVerifyNote').textContent = c.controlNo
        ? 'To verify this certificate, call HR at the number above and quote Control No. ' + c.controlNo + '.' : '';
      $id('coeSign').src = 'query/signature.php?s=ogm&r=' + encodeURIComponent(id);
      $id('coePreviewNo').textContent = '· ' + who + (c.controlNo ? ' · ' + c.controlNo : '');
      shown = id;
      var card = $id('coePreview');
      card.classList.remove('lcy-hidden');
      card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  $id('coePrint').addEventListener('click', function () {
    if (!shown) { return; }
    L.post('coe_printed', { id: shown }).then(function () { if (BOOT.issuer) { loadQueue(); } });
    window.print();
  });
  $id('coeClose').addEventListener('click', function () { $id('coePreview').classList.add('lcy-hidden'); shown = null; });

  refresh();
})();
