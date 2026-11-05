/* ==========================================================================
   myitems.js  —  screen logic for myitems.php: the items issued to me,
   confirm receipt, report a problem.
   ========================================================================== */
(function () {
  'use strict';

  var L = window.LCY, esc = L.esc, reportId = null;
  function $id(id) { return document.getElementById(id); }

  function load() {
    L.post('my_items', {}).then(function (res) {
      var box = $id('invMine');
      if (res.status !== 'ok') { box.innerHTML = '<div class="lcy-empty">' + esc(res.msg) + '</div>'; return; }
      if (!res.items.length) { box.innerHTML = '<section class="wd-card"><div class="lcy-empty">Nothing is issued to you right now.</div></section>'; return; }
      box.innerHTML = '<div class="inv-mine">' + res.items.map(card).join('') + '</div>';
      Array.prototype.forEach.call(box.querySelectorAll('[data-ack]'), function (b) {
        b.addEventListener('click', function () {
          b.disabled = true;
          L.post('my_ack', { id: b.getAttribute('data-ack') }).then(function (r) {
            if (r.status !== 'ok') { b.disabled = false; alert(r.msg); return; }
            load();
          });
        });
      });
      Array.prototype.forEach.call(box.querySelectorAll('[data-report]'), function (b) {
        b.addEventListener('click', function () {
          reportId = b.getAttribute('data-report');
          $id('invReportForm').reset();
          $id('invReportErr').textContent = '';
          $id('invReportTitle').textContent = 'Report a problem: ' + b.getAttribute('data-name');
          $('#invReport').modal('show');
        });
      });
    });
  }

  function card(i) {
    var state = i.status === 'lost' ? '<span class="wd-pill wd-pill--danger">Lost</span>'
      : i.status === 'repair' ? '<span class="wd-pill wd-pill--warn">Under repair</span>'
      : i.ackAt ? '<span class="wd-pill wd-pill--ok">Confirmed</span>' : '<span class="wd-pill wd-pill--warn">Please confirm</span>';
    var photos = i.photos.length ? '<div class="inv-card__photos">' + i.photos.map(function (id) {
      return '<a href="query/inventory-file.php?id=' + id + '" target="_blank" rel="noopener"><img src="query/inventory-file.php?id=' + id + '" alt="Photo of ' + esc(i.name) + '" loading="lazy"></a>';
    }).join('') + '</div>' : '';
    var meta = [i.qty + ' ' + i.unit, i.conditionLabel, i.serial ? 'SN ' + i.serial : ''].filter(Boolean).join(' · ');
    return '<article class="inv-card"><div class="lcy-item__head"><span class="inv-tag">' + esc(i.tag) + '</span>' + state + '</div>'
      + '<h3>' + esc(i.name) + '</h3><div class="inv-card__meta">' + esc(meta) + '</div>'
      + '<div class="inv-card__meta">Issued ' + esc(L.fmtDate(i.issuedOn)) + (i.ackAt ? ', confirmed ' + esc(L.fmtDate(i.ackAt)) : '') + '</div>' + photos
      + '<div class="inv-card__foot">'
      + (i.ackAt ? '' : '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-ack="' + i.id + '"><i class="fa-solid fa-check"></i> I received this</button>')
      + (i.status === 'active' ? '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-report="' + i.id + '" data-name="' + esc(i.tag) + '"><i class="fa-solid fa-triangle-exclamation"></i> Report a problem</button>' : '')
      + '</div></article>';
  }

  function send() {
    var btn = $id('invReportSend');
    btn.disabled = true;
    L.post('my_report', { id: reportId, problem: $id('invProblem').value }).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'ok') { $id('invReportErr').textContent = res.msg; return; }
      $('#invReport').modal('hide');
      alert('Thanks — your report was sent.');
    });
  }

  // inside the app a new tab goes nowhere: the phone's own viewer opens the photo
  $id('invMine').addEventListener('click', function (ev) {
    var a = ev.target.closest('.inv-card__photos a');
    if (a && window.__wdDownload) { ev.preventDefault(); window.__wdDownload(a.href); }
  });

  $id('invReportSend').addEventListener('click', send);
  $id('invReportForm').addEventListener('submit', function (ev) { ev.preventDefault(); send(); });
  load();
})();
