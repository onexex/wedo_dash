/* ==========================================================================
   lifecycle-common.js  —  helpers shared by separation.js, coe.js and
   verify.js: escaping, dates, the POST helper for query/lifecycle-action.php
   and the employee search dropdown. Data/token arrive in window.LCY_BOOT.
   ========================================================================== */
(function () {
  'use strict';

  var BOOT = window.LCY_BOOT || {};

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function fmtDate(s) {
    if (!s) { return ''; }
    var d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? s : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function fmtDateTime(s) {
    if (!s) { return ''; }
    var d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? s : d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  /** POST an action to query/lifecycle-action.php; resolves with the JSON (status ok|error, msg). */
  function post(action, data) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('token', BOOT.token || '');
    Object.keys(data || {}).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); }
      else if (v !== undefined && v !== null) { fd.append(k, v); }
    });
    return fetch('query/lifecycle-action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'Unexpected answer from the server.' }; }); })
      .catch(function () { return { status: 'error', msg: 'Could not reach the server — check your connection.' }; });
  }

  /** Form fields -> plain object (checkboxes: checked ones only; repeated names -> arrays). */
  function formData(form) {
    var out = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.disabled) { return; }
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { return; }
      if (el.name.slice(-2) === '[]') {
        var k = el.name.slice(0, -2);
        (out[k] = out[k] || []).push(el.value);
      } else { out[el.name] = el.value; }
    });
    return out;
  }

  var STATUS_TONE = { Employed: 'ok', Resigned: 'warn', Terminated: 'danger', AWOL: 'danger' };
  function statusPill(status) {
    return status ? '<span class="wd-pill wd-pill--' + (STATUS_TONE[status] || 'info') + '">' + esc(status) + '</span>' : '';
  }

  var REHIRE = { yes: ['ok', 'Eligible for rehire'], no: ['danger', 'Not eligible for rehire'], review: ['warn', 'Rehire: to review'] };
  function rehirePill(flag) {
    var r = REHIRE[flag];
    return r ? '<span class="wd-pill wd-pill--' + r[0] + '">' + r[1] + '</span>' : '';
  }

  /**
   * Live employee search: typing in `input` lists matches (any status) in
   * `box`; picking one calls onPick(employee).
   */
  function employeeSearch(input, box, onPick) {
    var timer = null, seq = 0;
    function close() { box.innerHTML = ''; }
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        var mine = ++seq;
        post('emp_search', { q: q }).then(function (res) {
          if (mine !== seq) { return; }
          if (res.status !== 'ok') { box.innerHTML = '<div class="lcy-empty">' + esc(res.msg) + '</div>'; return; }
          if (!res.employees.length) { box.innerHTML = '<div class="lcy-empty">No one matches.</div>'; return; }
          box.innerHTML = res.employees.map(function (e, i) {
            return '<button type="button" data-i="' + i + '"><span><b>' + esc(e.name) + '</b><br><small>'
              + esc(e.empId) + (e.position ? ' · ' + esc(e.position) : '') + '</small></span>' + statusPill(e.status) + '</button>';
          }).join('');
          Array.prototype.forEach.call(box.querySelectorAll('button'), function (b) {
            b.addEventListener('click', function () {
              var e = res.employees[+b.getAttribute('data-i')];
              close();
              input.value = e.name;
              onPick(e);
            });
          });
        });
      }, 220);
    });
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { close(); }
      if (ev.key === 'ArrowDown') { var f = box.querySelector('button'); if (f) { ev.preventDefault(); f.focus(); } }
    });
    document.addEventListener('click', function (ev) { if (!box.contains(ev.target) && ev.target !== input) { close(); } });
  }

  window.LCY = {
    boot: BOOT, esc: esc, fmtDate: fmtDate, fmtDateTime: fmtDateTime, post: post, formData: formData,
    statusPill: statusPill, rehirePill: rehirePill, employeeSearch: employeeSearch,
  };
})();
