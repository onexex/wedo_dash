/* ==========================================================================
   idcard.js  —  screen logic for idcard.php (Management > ID Card Generator)
   List + filters, live front/back preview, photo drag/zoom, and printing.
   Card artwork comes from idcard-render.js (window.IDCard). Data arrives in
   window.IDC_BOOT; writes go to query/idcard-action.php.
   ========================================================================== */
(function () {
  'use strict';

  var BOOT = window.IDC_BOOT || {};
  var cards = BOOT.cards || [];
  var byId = {};
  cards.forEach(function (c) { byId[c.empId] = c; });

  var selected = {};          // empId -> true
  var current = null;         // empId shown in the preview
  var cropDirty = false;
  var nextSeq = BOOT.nextSeq || 1;

  function $id(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function pad3(n) { n = String(n); while (n.length < 3) { n = '0' + n; } return n; }
  function previewNumber(c) { return BOOT.year + c.initials + pad3(nextSeq); }
  function fmtDate(s) {
    if (!s) { return ''; }
    var d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? s : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  function post(data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) {
      if (Array.isArray(data[k])) { data[k].forEach(function (v) { fd.append(k + '[]', v); }); }
      else { fd.append(k, data[k]); }
    });
    fd.append('token', BOOT.token);
    return fetch('query/idcard-action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  /* ------------------------------------------------------------ filters */
  function fillFilter(sel, key) {
    var seen = {};
    cards.forEach(function (c) { if (c[key]) { seen[c[key]] = 1; } });
    Object.keys(seen).sort().forEach(function (v) {
      var o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o);
    });
  }

  function visibleCards() {
    var q = $id('fSearch').value.trim().toLowerCase();
    var dept = $id('fDept').value, type = $id('fType').value, withResigned = $id('fResigned').checked;
    return cards.filter(function (c) {
      if (!withResigned && !c.employed) { return false; }
      if (dept && c.department !== dept) { return false; }
      if (type && c.empType !== type) { return false; }
      if (q) {
        var hay = (c.name + ' ' + c.sortName + ' ' + (c.idNumber || '') + ' ' + c.position + ' ' + c.empId).toLowerCase();
        if (hay.indexOf(q) === -1) { return false; }
      }
      return true;
    });
  }

  function statusPill(c) {
    if (!c.signature) { return '<span class="wd-pill wd-pill--danger" title="Printing is locked until a signature is uploaded"><i class="fa-solid fa-lock"></i> No signature</span>'; }
    if (!c.photo) { return '<span class="wd-pill wd-pill--warn">No photo</span>'; }
    return c.idNumber ? '<span class="wd-pill wd-pill--ok">Issued</span>' : '<span class="wd-pill wd-pill--off">Not issued</span>';
  }

  function thumb(c) {
    return c.photo
      ? '<img class="idc-thumb" src="' + esc(c.photo) + '" alt="" loading="lazy" style="object-fit:cover">'
      : '<span class="idc-thumb">' + esc(c.initials.charAt(0) + c.initials.charAt(2)) + '</span>';
  }

  function renderList() {
    var list = visibleCards();
    var html = list.map(function (c) {
      return '<div class="idc-row' + (c.empId === current ? ' is-current' : '') + '" data-id="' + esc(c.empId) + '" role="option">' +
        '<input type="checkbox" class="js-sel" aria-label="Select ' + esc(c.name) + '"' + (selected[c.empId] ? ' checked' : '') + '>' +
        thumb(c) +
        '<div class="idc-row__main"><div class="idc-row__name">' + esc(c.listName) + '</div>' +
        '<div class="idc-row__sub">' + esc(c.listPosition || 'No position') + (c.idNumber ? ' · ' + esc(c.idNumber) : '') + '</div></div>' +
        statusPill(c) +
      '</div>';
    }).join('');
    $id('empList').innerHTML = html || '<div class="idc-empty">No employees match these filters.</div>';
    var issued = list.filter(function (c) { return c.idNumber; }).length;
    var unsigned = list.filter(function (c) { return !c.signature; }).length;
    $id('listFoot').textContent = list.length + ' shown · ' + issued + ' issued · ' + (list.length - issued) + ' not issued' +
      (unsigned ? ' · ' + unsigned + ' without signature' : '');
    var all = list.length > 0 && list.every(function (c) { return selected[c.empId]; });
    $id('fAll').checked = all;
    updateSelCount();
  }

  function updateSelCount() {
    var n = Object.keys(selected).length;
    $id('selCount').textContent = n;
    $id('selCount').hidden = n === 0;
    $id('btnPrintSel').disabled = n === 0;
  }

  /* ------------------------------------------------------------ preview */
  function renderPreview() {
    var c = byId[current];
    var body = $id('pvBody');
    if (!c) {
      body.classList.add('is-empty');
      $id('pvFront').innerHTML = IDCard.front({ name: 'EMPLOYEE NAME', position: 'POSITION', crop: {} });
      $id('pvBack').innerHTML = IDCard.back(BOOT.back);
      IDCard.fit(body);
      return;
    }
    body.classList.remove('is-empty');
    $id('pvTitle').textContent = c.listName;
    $id('pvStatus').innerHTML = c.idNumber ? '<span class="wd-pill wd-pill--ok">Issued ' + esc(fmtDate(c.issuedAt)) + '</span>'
                                           : '<span class="wd-pill wd-pill--off">Not issued yet</span>';
    $id('pvFront').innerHTML = IDCard.front(c, { previewNumber: previewNumber(c) });
    $id('pvBack').innerHTML = IDCard.back(BOOT.back);
    IDCard.fit(body);

    $id('pvZoom').value = c.crop.zoom || 1;
    $id('pvZoom').disabled = !c.photo;
    $id('pvReset').disabled = !c.photo;
    $id('pvSaveCrop').disabled = !cropDirty;
    $id('pvPhotoLink').href = 'UpdateEmployeeInfo?sid=' + encodeURIComponent(c.empId);

    var num = $id('pvNumber');
    num.textContent = c.idNumber || previewNumber(c);
    num.classList.toggle('is-preview', !c.idNumber);
    $id('pvNumberNote').textContent = c.idNumber
      ? 'Kept on every reprint.' + (c.lastPrinted ? ' Last printed ' + fmtDate(c.lastPrinted) + '.' : '')
      : 'Preview — the number is assigned when the card is first printed.';

    $id('pvSignBox').innerHTML = c.signature ? '<img src="' + esc(c.signature) + '" alt="Signature">' : 'No signature yet';
    $id('pvSignRemove').disabled = !c.signature;
    $id('pvSignUpload').querySelector('span').textContent = c.signature ? 'Replace' : 'Upload';

    var warn = [];
    if (!c.photo) { warn.push('No photo on the 201 record — the circle will print blank.'); }
    else if (Math.min(c.photoW, c.photoH) < 400) { warn.push('The photo is only ' + c.photoW + '×' + c.photoH + ' px and may print blurry. Aim for 600 px or more.'); }
    if (!c.position) { warn.push('No position on the 201 record.'); }
    if (!c.employed) { warn.push('This employee is not marked Employed.'); }
    $id('pvWarnings').innerHTML = (BOOT.backSet ? '' :
        '<li class="is-block"><i class="fa-solid fa-lock"></i><span>Printing is locked until the company details on the back are confirmed.</span>' +
        '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm js-openback">Review</button></li>') +
      (c.signature ? '' :
        '<li class="is-block"><i class="fa-solid fa-lock"></i><span>Printing is locked until this employee’s signature is uploaded (the back says the card bears it).</span></li>') +
      warn.map(function (w) { return '<li><i class="fa-solid fa-triangle-exclamation"></i><span>' + esc(w) + '</span></li>'; }).join('');

    $id('pvPrint').querySelector('span').textContent = c.idNumber ? 'Reprint card' : 'Issue & print';
    syncPrintBtn();
  }

  /* the single-card print button is off while this employee has no signature */
  function syncPrintBtn() {
    var c = byId[current];
    var locked = !c || !c.signature;
    $id('pvPrint').disabled = locked;
    $id('pvPrint').title = c && !c.signature ? 'Upload the employee’s signature first' : '';
    $id('pvPrint').querySelector('i').className = locked ? 'fa-solid fa-lock' : 'fa-solid fa-print';
    if (c && !c.signature) { $id('pvPrint').querySelector('span').textContent = 'Locked — signature needed'; }
  }

  function namesList(names) {
    var shown = names.slice(0, 10).map(function (n) { return '  • ' + n; }).join('\n');
    return shown + (names.length > 10 ? '\n  …and ' + (names.length - 10) + ' more' : '');
  }

  function placePhoto() {
    var c = byId[current];
    var img = $id('pvFront').querySelector('.idc-photo');
    if (!c || !img) { return; }
    var b = IDCard.photoBox(c);
    img.setAttribute('x', b.x.toFixed(2)); img.setAttribute('y', b.y.toFixed(2));
    img.setAttribute('width', b.w.toFixed(2)); img.setAttribute('height', b.h.toFixed(2));
  }

  function markDirty() { cropDirty = true; $id('pvSaveCrop').disabled = false; }

  function saveCrop(silent) {
    var c = byId[current];
    if (!c || !cropDirty) { return Promise.resolve(); }
    $id('pvSaveCrop').disabled = true;
    return post({ action: 'save_crop', emp: c.empId, x: c.crop.x, y: c.crop.y, zoom: c.crop.zoom }).then(function (d) {
      if (d.status === 'ok') { cropDirty = false; }
      else { $id('pvSaveCrop').disabled = false; if (!silent) { alert(d.msg || 'Could not save the photo position.'); } }
    }).catch(function () { $id('pvSaveCrop').disabled = false; if (!silent) { alert('The server could not be reached. Try again.'); } });
  }

  function select(empId) {
    if (empId === current) { return; }
    saveCrop(true);
    cropDirty = false;
    current = empId;
    document.querySelectorAll('.idc-row').forEach(function (r) { r.classList.toggle('is-current', r.getAttribute('data-id') === empId); });
    renderPreview();
  }

  /* photo drag inside the front preview */
  (function () {
    var frame = $id('pvFront'), drag = null;
    frame.addEventListener('pointerdown', function (e) {
      var c = byId[current];
      if (!c || !c.photo || !e.target.classList.contains('idc-photo')) { return; }
      e.preventDefault();
      var svg = frame.querySelector('svg');
      drag = { x: e.clientX, y: e.clientY, scale: IDCard.W / svg.getBoundingClientRect().width };
      frame.classList.add('is-dragging');
      frame.setPointerCapture(e.pointerId);
    });
    frame.addEventListener('pointermove', function (e) {
      if (!drag) { return; }
      var c = byId[current], b = IDCard.photoBox(c);
      var dx = (e.clientX - drag.x) * drag.scale, dy = (e.clientY - drag.y) * drag.scale;
      drag.x = e.clientX; drag.y = e.clientY;
      if (b.panX > 0) { c.crop.x = Math.max(-1, Math.min(1, (+c.crop.x || 0) + dx / b.panX)); }
      if (b.panY > 0) { c.crop.y = Math.max(-1, Math.min(1, (+c.crop.y || 0) + dy / b.panY)); }
      placePhoto(); markDirty();
    });
    function end() { drag = null; frame.classList.remove('is-dragging'); }
    frame.addEventListener('pointerup', end);
    frame.addEventListener('pointercancel', end);
    frame.addEventListener('wheel', function (e) {
      var c = byId[current];
      if (!c || !c.photo || !e.target.classList.contains('idc-photo')) { return; }
      e.preventDefault();
      c.crop.zoom = Math.max(1, Math.min(3, (+c.crop.zoom || 1) - e.deltaY * 0.002));
      $id('pvZoom').value = c.crop.zoom;
      placePhoto(); markDirty();
    }, { passive: false });
  })();

  /* ------------------------------------------------------------ printing */
  function loadImage(src) {
    return new Promise(function (res) {
      if (!src) { return res(); }
      var i = new Image(); i.onload = i.onerror = function () { res(); }; i.src = src;
    });
  }

  function calibrationSvg(label) {
    var W = IDCard.W, H = IDCard.H;
    return '<svg xmlns="http://www.w3.org/2000/svg" class="idc-svg" viewBox="0 0 ' + W + ' ' + H + '">' +
      '<rect width="' + W + '" height="' + H + '" fill="#fff"/>' +
      '<rect x="5" y="5" width="' + (W - 10) + '" height="' + (H - 10) + '" fill="none" stroke="#000" stroke-width="3"/>' +
      '<rect x="30" y="30" width="' + (W - 60) + '" height="' + (H - 60) + '" fill="none" stroke="#E00A0A" stroke-width="2" stroke-dasharray="10 8"/>' +
      '<path d="M' + (W / 2) + ',' + (H / 2 - 40) + ' V' + (H / 2 + 40) + ' M' + (W / 2 - 40) + ',' + (H / 2) + ' H' + (W / 2 + 40) + '" stroke="#000" stroke-width="2"/>' +
      '<g font-family="Arial,sans-serif" text-anchor="middle" fill="#000">' +
        '<text x="' + (W / 2) + '" y="' + (H / 2 - 80) + '" font-size="34" font-weight="700">' + label + '</text>' +
        '<text x="' + (W / 2) + '" y="' + (H / 2 + 100) + '" font-size="20">Black line = 0.5 mm from the edge</text>' +
        '<text x="' + (W / 2) + '" y="' + (H / 2 + 128) + '" font-size="20" fill="#E00A0A">Red dashes = 3 mm safe zone for text</text>' +
        '<text x="' + (W / 2) + '" y="' + (H / 2 + 156) + '" font-size="20">Both should look even on all four sides</text>' +
      '</g></svg>';
  }

  function printPages(pagesHtml, images) {
    var box = $id('idcPrint');
    box.innerHTML = pagesHtml.map(function (h) { return '<div class="idc-page">' + h + '</div>'; }).join('');
    return Promise.all((images || []).map(loadImage)).then(function () { return IDCard.fit(box); }).then(function () {
      window.print();
    });
  }
  window.addEventListener('afterprint', function () { $id('idcPrint').innerHTML = ''; });

  function printCards(list) {
    var pages = [], imgs = [BOOT.back.logo, BOOT.back.signature];
    list.forEach(function (c) {
      pages.push(IDCard.front(c)); pages.push(IDCard.back(BOOT.back));
      if (c.photo) { imgs.push(c.photo); }
      if (c.signature) { imgs.push(c.signature); }
    });
    return printPages(pages, imgs);
  }

  function issueAndPrint(ids) {
    if (!BOOT.backSet) { openBack(); return; }
    var unsigned = ids.filter(function (id) { return !byId[id].signature; });
    if (unsigned.length) {
      alert('Printing is locked: ' + unsigned.length + ' of the selected employee' + (ids.length > 1 ? 's have' : ' has') +
        ' no signature on file.\n\n' + namesList(unsigned.map(function (id) { return byId[id].listName; })) +
        '\n\nUpload their signatures first. Nothing was printed.');
      return;
    }
    var fresh = ids.filter(function (id) { return !byId[id].idNumber; }).length;
    var msg = 'Print ' + ids.length + ' card' + (ids.length > 1 ? 's' : '') + ' (' + ids.length * 2 + ' pages, front and back)?';
    if (fresh) { msg += '\n\n' + fresh + ' will get a new ID number now. Numbers are permanent and are kept on reprints.'; }
    if (!confirm(msg)) { return; }

    var btns = [$id('pvPrint'), $id('btnPrintSel')];
    btns.forEach(function (b) { b.disabled = true; });
    var before = ids.indexOf(current) !== -1 ? saveCrop(true) : Promise.resolve();
    before.then(function () { return post({ action: 'issue', emp: ids }); }).then(function (d) {
      if (d.status === 'needs_back') { BOOT.backSet = false; renderPreview(); openBack(); return; }
      if (d.status === 'needs_sign') {   // someone removed a signature since this page loaded
        (d.missing || []).forEach(function (m) { if (byId[m.empId]) { byId[m.empId].signature = null; } });
        renderList(); renderPreview();
        alert(d.msg + '\n\n' + namesList((d.missing || []).map(function (m) { return m.name; })) + '\n\nNothing was printed.');
        return;
      }
      if (d.status !== 'ok') { alert(d.msg || 'Could not issue the cards.'); return; }
      if (d.back) { BOOT.back = d.back; }
      d.cards.forEach(function (fresh) {
        var c = byId[fresh.empId];
        Object.keys(fresh).forEach(function (k) { c[k] = fresh[k]; });
      });
      if (d.nextSeq) { nextSeq = d.nextSeq; }
      renderList(); renderPreview();
      return printCards(ids.map(function (id) { return byId[id]; }));
    }).catch(function () { alert('The server could not be reached. Try again.'); })
      .finally(function () { btns.forEach(function (b) { b.disabled = false; }); updateSelCount(); syncPrintBtn(); });
  }

  /* ------------------------------------------------------------ card back settings */
  var openBack = function () {};
  (function () {
    var form = $id('frmBack');
    var keys = Object.keys(BOOT.backValues || {});

    function fill(values) { keys.forEach(function (k) { form.elements[k].value = values[k] || ''; }); }
    function formValues() { var v = {}; keys.forEach(function (k) { v[k] = form.elements[k].value.trim(); }); return v; }
    function clearErrors() { form.querySelectorAll('[data-err]').forEach(function (el) { el.textContent = ''; }); }

    function cfgFrom(v) {
      var cfg = {};
      Object.keys(BOOT.back).forEach(function (k) { cfg[k] = BOOT.back[k]; });
      cfg.phone = v.phone; cfg.email = v.email; cfg.signatory = v.signatory; cfg.sign_title = v.sign_title;
      cfg.address = [v.address1, v.address2].filter(function (s) { return s; });
      return cfg;
    }
    function preview() {
      $id('bkPreview').innerHTML = IDCard.back(cfgFrom(formValues()));
      IDCard.fit($id('bkPreview'));
    }

    openBack = function () {
      fill(BOOT.backValues); clearErrors();
      $id('bkFirstTime').hidden = !!BOOT.backSet;
      $('#mdlBack').modal('show');
    };
    $id('btnBack').addEventListener('click', openBack);
    $id('pvWarnings').addEventListener('click', function (e) { if (e.target.closest('.js-openback')) { openBack(); } });
    $('#mdlBack').on('shown.bs.modal', function () { preview(); form.elements[keys[0]].focus(); });
    form.addEventListener('input', preview);
    $id('bkDefaults').addEventListener('click', function () { fill(BOOT.backDefaults); clearErrors(); preview(); });

    form.addEventListener('submit', function (e) {
      e.preventDefault(); clearErrors();
      var data = formValues(); data.action = 'save_back';
      var btn = $id('bkSave'); btn.disabled = true;
      post(data).then(function (d) {
        if (d.status === 'ok') {
          BOOT.back = d.back; BOOT.backValues = d.values; BOOT.backSet = true;
          $('#mdlBack').modal('hide');
          renderPreview();
        } else if (d.status === 'invalid') {
          Object.keys(d.error || {}).forEach(function (k) { var el = form.querySelector('[data-err="' + k + '"]'); if (el) { el.textContent = d.error[k]; } });
        } else { alert(d.msg || 'Could not save.'); }
      }).catch(function () { alert('The server could not be reached. Try again.'); })
        .finally(function () { btn.disabled = false; });
    });
  })();

  /* ------------------------------------------------------------ wiring */
  fillFilter($id('fDept'), 'department');
  fillFilter($id('fType'), 'empType');
  ['fSearch', 'fDept', 'fType', 'fResigned'].forEach(function (id) {
    $id(id).addEventListener(id === 'fSearch' ? 'input' : 'change', renderList);
  });

  $id('fAll').addEventListener('change', function () {
    var on = this.checked;
    visibleCards().forEach(function (c) { if (on) { selected[c.empId] = true; } else { delete selected[c.empId]; } });
    renderList();
  });

  $id('empList').addEventListener('click', function (e) {
    var row = e.target.closest('.idc-row');
    if (!row) { return; }
    var id = row.getAttribute('data-id');
    if (e.target.classList.contains('js-sel')) {
      if (e.target.checked) { selected[id] = true; } else { delete selected[id]; }
      updateSelCount();
      return;
    }
    select(id);
  });

  $id('pvZoom').addEventListener('input', function () {
    var c = byId[current]; if (!c) { return; }
    c.crop.zoom = +this.value; placePhoto(); markDirty();
  });
  $id('pvReset').addEventListener('click', function () {
    var c = byId[current]; if (!c) { return; }
    c.crop = { x: 0, y: 0, zoom: 1 }; $id('pvZoom').value = 1; placePhoto(); markDirty();
  });
  $id('pvSaveCrop').addEventListener('click', function () { saveCrop(false); });

  function applyCard(fresh) {
    var c = byId[fresh.empId];
    Object.keys(fresh).forEach(function (k) { if (k !== 'crop' || !cropDirty) { c[k] = fresh[k]; } });
    renderList(); renderPreview();
  }
  $id('pvSignFile').addEventListener('change', function () {
    var file = this.files[0], input = this, btn = $id('pvSignUpload');
    if (!file || !current) { return; }
    btn.classList.add('is-busy');
    post({ action: 'upload_sign', emp: current, file: file }).then(function (d) {
      if (d.status === 'ok') { applyCard(d.card); } else { alert(d.msg || 'Could not upload the signature.'); }
    }).catch(function () { alert('The server could not be reached. Try again.'); })
      .finally(function () { btn.classList.remove('is-busy'); input.value = ''; });
  });
  $id('pvSignRemove').addEventListener('click', function () {
    if (!current || !confirm('Remove this employee’s signature from their ID card?')) { return; }
    post({ action: 'remove_sign', emp: current }).then(function (d) {
      if (d.status === 'ok') { applyCard(d.card); } else { alert(d.msg || 'Could not remove the signature.'); }
    }).catch(function () { alert('The server could not be reached. Try again.'); });
  });
  $id('pvPrint').addEventListener('click', function () { if (current) { issueAndPrint([current]); } });
  $id('btnPrintSel').addEventListener('click', function () {
    var ids = Object.keys(selected).filter(function (id) { return byId[id]; });
    if (ids.length) { issueAndPrint(ids); }
  });
  $id('btnCalib').addEventListener('click', function () {
    printPages([calibrationSvg('FRONT'), calibrationSvg('BACK')]);
  });
  window.addEventListener('beforeunload', function () { if (cropDirty) { saveCrop(true); } });

  renderList();
  var first = visibleCards()[0];
  if (first) { select(first.empId); } else { renderPreview(); }
})();
