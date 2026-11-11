/* ==========================================================================
   inventory.js  —  screen logic for inventory.php. One modal shows an item or
   a form (its content is swapped, so nothing stacks). Tables are drawn here
   from query/inventory-action.php; every value goes through esc().
   ========================================================================== */
(function () {
  'use strict';

  var L = window.LCY, esc = L.esc, BOOT = L.boot;
  var look = null;            // lookups: categories, locations, departments, ...
  var rows = [];              // the Items tab's current list
  var supplies = [];          // the Supplies tab's current list
  var usage = null;           // the last usage report
  var selected = {};          // item id -> true (bulk actions)
  var tab = 'items';

  function $id(id) { return document.getElementById(id); }
  function money(n) { return n === null || n === undefined ? '' : '₱' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function empty(msg) { return '<div class="lcy-empty">' + esc(msg) + '</div>'; }
  function pill(tone, text) { return '<span class="wd-pill wd-pill--' + tone + '">' + esc(text) + '</span>'; }
  function each(box, sel, fn) { Array.prototype.forEach.call(box.querySelectorAll(sel), fn); }
  /** cls: a phone layout of its own (inv-grid inv-t-*); without one a phone gets label / value rows. */
  function table(head, body, cls) {
    var t = document.createElement('table');
    t.innerHTML = '<tbody>' + body + '</tbody>';
    each(t, 'tr', function (tr) {
      Array.prototype.forEach.call(tr.cells, function (td, n) { td.setAttribute('data-label', /^</.test(head[n] || '') ? '' : (head[n] || '')); });
    });
    return '<div class="wd-tablewrap inv-tablewrap"><table class="wd-table lcy-table' + (cls ? ' ' + cls : '') + '"><thead><tr>' + head.map(function (h) { return '<th>' + h + '</th>'; }).join('')
      + '</tr></thead><tbody>' + t.tBodies[0].innerHTML + '</tbody></table></div>';
  }
  /** Inside the app a new tab goes nowhere: the phone's own viewer opens photos and files. */
  function openInApp(ev) {
    if (window.__wdDownload) { ev.preventDefault(); window.__wdDownload(this.href); }
  }

  var STATUS_TONE = { active: 'ok', repair: 'warn', lost: 'danger', disposed: 'info', merged: 'info' };
  function statusPill(i) { return pill(STATUS_TONE[i.status] || 'info', i.statusLabel); }

  /* ------------------------------------------------------------ lookups */
  function options(list, blank, val, text, onlyActive) {
    var out = blank === null ? '' : '<option value="">' + esc(blank) + '</option>';
    return out + list.filter(function (x) { return !onlyActive || x.active !== false; }).map(function (x) {
      return '<option value="' + esc(x[val || 'id']) + '">' + esc(x[text || 'name']) + '</option>';
    }).join('');
  }
  function kv(map) { return Object.keys(map).map(function (k) { return { id: k, name: map[k] }; }); }
  function catsOf(fn) { return look.categories.filter(fn); }
  function isSupply(c) { return c.kind === 'consumable'; }

  function loadLookups() {
    return L.post('lookups', {}).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      look = res;
      $id('invFCat').innerHTML = options(catsOf(function (c) { return !isSupply(c); }), 'All categories');
      $id('invFLoc').innerHTML = options(look.locations, 'All locations');
      $id('invSupLoc').innerHTML = options(look.locations, 'All locations');
    });
  }

  /* ------------------------------------------------------------ the modal */
  function showModal(title, body, foot, panel, locked) {
    $id('invModalTitle').textContent = title;
    $id('invModalBody').innerHTML = body;
    $id('invModalBody').scrollTop = 0;
    $id('invModalFoot').innerHTML = foot;
    $id('invModalDialog').className = 'modal-dialog' + (panel ? ' inv-panel' : '');
    $('#invModal').modal('show');
    var bs = $('#invModal').data('bs.modal');   // a click outside or Esc must not throw away what was typed
    bs.options.backdrop = locked ? 'static' : true;
    bs.options.keyboard = !locked;
  }
  var plainClose = false;     // our own close, or the X: nothing to step back through
  var formBack = null;        // the open form's way back
  var formStart = '';         // what it held when it opened
  function closeModal() { plainClose = true; $('#invModal').modal('hide'); }

  /**
   * A form in the modal. spec: title, fields (or body: ready html), action, data
   * (sent as is), submit, tone ('danger'), back (fn: the Cancel button returns
   * there), done (fn(res)), panel, another (fn(res): adds "Save and add another").
   * field: name, label, type (text number date select textarea emp checkbox hidden),
   * options (html), value, help, required, placeholder, max.
   */
  function form(spec) {
    var err = '<p class="lcy-modal-error" id="invFormErr" role="alert"></p>';
    var html = '<form id="invForm" autocomplete="off">' + (spec.body || spec.fields.map(fieldHtml).join('') + err) + '<button type="submit" class="lcy-hidden" tabindex="-1" aria-hidden="true"></button></form>';
    var btn = spec.tone === 'danger' ? 'wd-btn--danger' : 'wd-btn--primary';
    showModal(spec.title, html,
      (spec.body ? '<div class="inv-formstatus"><p class="inv-formok" id="invFormOk" role="status"></p>' + err + '</div>' : '')
      + '<button type="button" class="wd-btn wd-btn--ghost inv-formback" id="invFormBack">' + (spec.back ? 'Back' : 'Cancel') + '</button>'
      + (spec.another ? '<button type="button" class="wd-btn wd-btn--ghost" id="invFormMore"><i class="fa-solid fa-plus"></i> Save and add another</button>' : '')
      + '<button type="button" class="wd-btn ' + btn + '" id="invFormGo"><i class="fa-solid fa-check"></i> ' + esc(spec.submit || 'Save') + '</button>',
      spec.panel || !!spec.back, true);
    var f = $id('invForm');
    formBack = spec.back || null;
    (spec.fields || []).forEach(function (fd) {
      if (fd.type === 'select' && fd.value !== undefined && fd.value !== null) { f.elements[fd.name].value = fd.value; }
      if (fd.type === 'emp') {
        L.employeeSearch(f.querySelector('[data-emp="' + fd.name + '"]'), f.querySelector('[data-empbox="' + fd.name + '"]'), function (e) { f.elements[fd.name].value = e.empId; });
      }
    });
    formStart = $(f).serialize();
    $id('invFormBack').addEventListener('click', function () { if (spec.back) { spec.back(); } else { closeModal(); } });
    f.addEventListener('submit', function (ev) { ev.preventDefault(); go(); });
    $id('invFormGo').addEventListener('click', function () { go(); });
    if (spec.another) { $id('invFormMore').addEventListener('click', function () { go(spec.another); }); }
    function go(then) {
      var d = L.formData(f), b = $id('invFormGo');
      Object.keys(spec.data || {}).forEach(function (k) { d[k] = spec.data[k]; });
      if (spec.prepare) { spec.prepare(d); }
      b.disabled = true;
      $id('invFormErr').textContent = '';
      L.post(spec.action, d).then(function (res) {
        b.disabled = false;
        if (res.status !== 'ok') { $id('invFormErr').textContent = res.msg; return; }
        (then || spec.done)(res);
      });
    }
  }

  function fieldHtml(fd) {
    if (fd.type === 'hidden') { return '<input type="hidden" name="' + esc(fd.name) + '" value="' + esc(fd.value) + '">'; }
    if (fd.type === 'checkbox') {
      return '<div class="wd-field"><label class="lcy-check"><input type="checkbox" name="' + esc(fd.name) + '" value="1"' + (fd.value ? ' checked' : '') + '> ' + esc(fd.label) + '</label></div>';
    }
    var req = fd.required ? ' required' : '', ph = fd.placeholder ? ' placeholder="' + esc(fd.placeholder) + '"' : '';
    var max = fd.max ? ' maxlength="' + fd.max + '"' : '', val = fd.value === undefined || fd.value === null ? '' : fd.value;
    var ctl;
    if (fd.type === 'select') { ctl = '<select class="wd-select" name="' + esc(fd.name) + '"' + req + '>' + fd.options + '</select>'; }
    else if (fd.type === 'textarea') { ctl = '<textarea class="wd-textarea" rows="3" name="' + esc(fd.name) + '"' + max + ph + req + '>' + esc(val) + '</textarea>'; }
    else if (fd.type === 'emp') {
      ctl = '<div class="lcy-search"><input type="hidden" name="' + esc(fd.name) + '" value="' + esc(val) + '">'
        + '<input type="search" class="wd-input" data-emp="' + esc(fd.name) + '" autocomplete="off" placeholder="Start typing a name&hellip;">'
        + '<div class="lcy-results" data-empbox="' + esc(fd.name) + '" role="listbox"></div></div>';
    } else {
      ctl = '<input class="wd-input" type="' + esc(fd.type || 'text') + '" name="' + esc(fd.name) + '" value="' + esc(val) + '"'
        + (fd.type === 'number' ? ' min="' + (fd.min === undefined ? 0 : fd.min) + '" step="' + (fd.step || 1) + '"' : '') + max + ph + req + '>';
    }
    return '<div class="wd-field"><label>' + esc(fd.label) + (fd.help ? ' <span class="lcy-muted">(' + esc(fd.help) + ')</span>' : '') + '</label>' + ctl + '</div>';
  }

  function locOptions() { return options(look.locations, null, 'id', 'name', true); }
  function condOptions(blank) { return options(kv(look.conditions), blank === undefined ? null : blank); }

  /* ------------------------------------------------------------ item panel */
  function openItem(id) {
    L.post('item', { id: id }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      drawItem(res);
    });
  }

  function fact(label, value) { return value === '' || value === null || value === undefined ? '' : '<div><dt>' + esc(label) + '</dt><dd>' + esc(value) + '</dd></div>'; }

  var MOVE = {
    added: 'Added', edited: 'Details edited', issued: 'Issued', returned: 'Returned', acknowledged: 'Receipt confirmed',
    transferred: 'Moved', split: 'Lot split', merged: 'Merged', repair_open: 'Sent to repair', repair_close: 'Repair closed',
    counted: 'Counted', lost: 'Marked lost', found: 'Found', disposed: 'Disposed',
  };

  function drawItem(d) {
    var i = d.item, doc = i.kind === 'document', sup = i.kind === 'consumable';
    var facts = '<dl class="lcy-facts">'
      + fact('Category', i.category) + fact('Quantity', i.qty + ' ' + i.unit) + fact('Condition', i.conditionLabel)
      + fact('Location', i.location + (i.spot ? ' · ' + i.spot : ''))
      + fact(doc ? 'Holder of the original' : 'Issued to', i.holder ? i.holder + (i.holderLeft ? ' (no longer employed)' : '') : '')
      + fact('Issued on', L.fmtDate(i.issuedOn)) + fact('Receipt', i.holderId ? (i.ackAt ? 'Confirmed ' + L.fmtDate(i.ackAt) : 'Not confirmed yet') : '')
      + fact('Brand', i.brand) + fact('Model', i.model) + fact('Serial no.', i.serial)
      + fact('Document no.', i.docNumber) + fact('Issued by', i.docIssuer) + fact('Date issued', L.fmtDate(i.docIssuedOn)) + fact('Expires', L.fmtDate(i.expiresOn))
      + fact('Acquired', L.fmtDate(i.acquiredOn)) + fact('Supplier', i.supplier) + fact('Unit cost', money(i.unitCost)) + fact('Total cost', money(i.totalCost))
      + fact('Reference', i.reference) + fact('Warranty until', L.fmtDate(i.warrantyUntil))
      + fact('Reorder at', i.reorderLevel === null ? '' : i.reorderLevel + ' ' + i.unit) + fact('Last counted', L.fmtDate(i.lastCountedOn))
      + fact('Disposed', i.disposedOn ? L.fmtDate(i.disposedOn) + ' · ' + (look.disposal[i.disposalMethod] || i.disposalMethod) : '') + fact('Disposal note', i.disposalNote)
      + fact('Description', i.description) + fact('Notes', i.notes)
      + '</dl>';

    var fam = d.family.length ? '<h5 class="inv-h">Other parts of this lot</h5><ul class="inv-family">' + d.family.map(function (x) {
      return '<li><a href="#" data-item="' + x.id + '">' + esc(x.tag) + '</a> · ' + x.qty + ' ' + esc(x.unit) + ' · ' + esc(x.location) + (x.holder ? ' · ' + esc(x.holder) : '') + ' ' + statusPill(x) + '</li>';
    }).join('') + '</ul>' : '';

    var photos = d.files.filter(function (f) { return f.kind === 'photo'; });
    var papers = d.files.filter(function (f) { return f.kind !== 'photo'; });
    var filesHtml = '<h5 class="inv-h">Photos and files</h5><div class="inv-files">'
      + photos.map(function (f) {
        return '<span class="inv-thumb"><a href="query/inventory-file.php?id=' + f.id + '" target="_blank" rel="noopener"><img src="query/inventory-file.php?id=' + f.id + '" alt="Photo of ' + esc(i.name) + '" loading="lazy"></a>'
          + '<button type="button" class="inv-x" data-delfile="' + f.id + '" aria-label="Delete photo">&times;</button></span>';
      }).join('')
      + papers.map(function (f) {
        return '<span class="inv-paper"><a href="query/inventory-file.php?id=' + f.id + '" target="_blank" rel="noopener"><i class="fa-solid ' + (f.pdf ? 'fa-file-pdf' : 'fa-file-image') + '"></i> ' + esc(f.name || look.fileKinds[f.kind]) + '</a>'
          + ' <small class="lcy-muted">' + esc(look.fileKinds[f.kind]) + '</small><button type="button" class="inv-x" data-delfile="' + f.id + '" aria-label="Delete file">&times;</button></span>';
      }).join('')
      + (d.files.length ? '' : '<span class="lcy-muted">Nothing attached yet.</span>') + '</div>'
      + '<div class="inv-upload"><select class="wd-select" id="invUpKind">' + options(kv(look.fileKinds), null) + '</select>'
      + '<label class="wd-btn wd-btn--ghost wd-btn--sm"><i class="fa-solid fa-paperclip"></i> Attach<input type="file" id="invUpFile" accept=".pdf,.jpg,.jpeg,.png,image/*" hidden></label>'
      + '<span class="lcy-muted" id="invUpMsg"></span></div>';

    var repairs = !d.repairs.length ? '' : '<h5 class="inv-h">Repairs</h5><ul class="inv-family">' + d.repairs.map(function (r) {
      return '<li>' + repairPill(r) + ' ' + esc(r.problem) + (r.vendor ? ' · ' + esc(r.vendor) : '') + (r.cost !== null ? ' · ' + esc(money(r.cost)) : '') + ' <span class="lcy-muted">' + esc(L.fmtDate(r.createdAt)) + '</span></li>';
    }).join('') + '</ul>';

    var ledger = !sup ? '' : '<h5 class="inv-h">Stock ledger</h5>' + (d.ledger.length ? ledgerTable(d.ledger) : '<p class="lcy-muted">No movements yet.</p>');

    var hist = '<h5 class="inv-h">History</h5><ul class="lcy-timeline inv-timeline">' + d.history.map(function (h) {
      var note = [];
      if (h.toEmp) { note.push('to ' + h.toEmp); }
      if (h.fromEmp) { note.push('from ' + h.fromEmp); }
      if (h.fromLoc && h.toLoc) { note.push(h.fromLoc + ' → ' + h.toLoc); } else if (h.toLoc || h.fromLoc) { note.push(h.toLoc ? 'at ' + h.toLoc : 'from ' + h.fromLoc); }
      if (h.qty !== null) { note.push('qty ' + h.qty); }
      return '<li><div class="t-date">' + esc(L.fmtDateTime(h.at)) + '</div><div class="t-what">' + esc(MOVE[h.type] || h.type) + '</div>'
        + (note.length ? '<div class="t-note">' + esc(note.join(' · ')) + '</div>' : '')
        + (h.notes ? '<div class="t-note">' + esc(h.notes) + '</div>' : '')
        + (h.by ? '<div class="t-note lcy-muted">by ' + esc(h.by) + '</div>' : '') + '</li>';
    }).join('') + '</ul>';

    var tsdrs = !(d.tsdrs || []).length ? '' : '<h5 class="inv-h">TSDRs</h5><ul class="inv-family">' + d.tsdrs.map(function (t) {
      return '<li><a href="#" data-tsdr="' + t.id + '">TSDR ' + esc(t.no) + '</a> · ' + esc(t.requisitioner) + ' ' + tsdrPill(t) + ' <span class="lcy-muted">' + esc(L.fmtDate(t.createdAt)) + '</span></li>';
    }).join('') + '</ul>';

    var countBar = d.openCount && i.status !== 'disposed' ? '<p class="lcy-note">Count open here: ' + esc(d.openCount.title)
      + ' <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invCountHere"><i class="fa-solid fa-check"></i> Mark found</button></p>' : '';

    var body = '<div class="inv-itemhead"><div><span class="inv-tag">' + esc(i.tag) + '</span> ' + statusPill(i)
      + (i.low ? ' ' + pill('danger', 'Low stock') : '') + '</div></div>' + countBar + facts + fam + filesHtml + repairs + tsdrs + ledger + hist;

    var list = itemActions(i, d), main = list.filter(function (a) { return a[3] === 'primary'; })[0];
    var button = function (a, cls) {
      return '<button type="button" class="wd-btn wd-btn--' + (a[3] || 'ghost') + ' ' + cls + '" data-act="' + a[0] + '"><i class="fa-solid ' + a[1] + '"></i> ' + esc(a[2]) + '</button>';
    };
    var many = list.length > 2;   // a phone shows the main action, the rest are one tap away
    var bar = '<div class="inv-phonebar">' + (many ? (main ? button(main, '') : '') + '<button type="button" class="wd-btn wd-btn--ghost" id="invMore"><i class="fa-solid fa-ellipsis"></i> More</button>'
      : list.map(function (a) { return button(a, ''); }).join('')) + '</div>';
    var sheet = '<div class="inv-moresheet" id="invMoreSheet"><div class="inv-scrim"></div><div class="inv-actions"><div class="inv-sheethead">' + esc(i.name) + '</div>'
      + list.map(function (a) { return button(a, 'wd-btn--sm' + (many && a === main ? ' inv-ismain' : '')); }).join('') + '</div></div>';
    showModal(i.name, body, bar + sheet, true);
    var closeSheet = function () { $id('invMoreSheet').classList.remove('is-open'); };
    if (many) {
      $id('invMore').addEventListener('click', function () { $id('invMoreSheet').classList.add('is-open'); });
      $id('invMoreSheet').querySelector('.inv-scrim').addEventListener('click', closeSheet);
    }

    var run = { edit: function () { itemForm(i); }, dup: function () { itemForm(i, true); }, label: function () { printLabels([i]); } };
    run.del = function () {
      if (!confirm('Delete ' + i.tag + '? This cannot be undone.')) { return; }
      L.post('item_delete', { id: i.id }).then(function (res) {
        if (res.status !== 'ok') { alert(res.msg); return; }
        closeModal(); refresh();
      });
    };
    run.merge = function () {
      L.post('lot_merge', { id: i.id }).then(function (res) {
        if (res.status !== 'ok') { alert(res.msg); return; }
        refresh(); openItem(res.id);
      });
    };
    Object.keys(ACTIONS).forEach(function (k) { run[k] = function () { ACTIONS[k](i); }; });
    each($id('invModalFoot'), '[data-act]', function (b) {
      b.addEventListener('click', function () { closeSheet(); run[b.getAttribute('data-act')](); });
    });
    var box = $id('invModalBody');
    each(box, '.inv-thumb a, .inv-paper a', function (a) { a.addEventListener('click', openInApp); });
    each(box, '[data-item]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openItem(+a.getAttribute('data-item')); }); });
    each(box, '[data-tsdr]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openTsdr(+a.getAttribute('data-tsdr'), function () { openItem(i.id); }); }); });
    each(box, '[data-delfile]', function (b) {
      b.addEventListener('click', function () {
        if (!confirm('Delete this file?')) { return; }
        L.post('file_delete', { id: b.getAttribute('data-delfile') }).then(function (res) {
          if (res.status !== 'ok') { alert(res.msg); return; }
          openItem(i.id);
        });
      });
    });
    $id('invUpFile').addEventListener('change', function () { attach(i.id, this.files[0]); });
    if ($id('invCountHere')) {
      $id('invCountHere').addEventListener('click', function () {
        L.post('count_check', { id: d.openCount.id, itemId: i.id }).then(function (res) {
          if (res.status !== 'ok') { alert(res.msg); return; }
          openCountId = d.openCount.id;   // the Counts tab opens on it
          closeModal(); go('counts');
        });
      });
    }
  }

  /** [act key, icon, label, tone] for what can be done to the item right now. */
  function itemActions(i, d) {
    var out = [], sup = i.kind === 'consumable', off = i.status === 'disposed' || i.status === 'merged';
    if (off) { return [['dup', 'fa-copy', 'Duplicate']]; }
    if (sup) {
      out.push(['stockIn', 'fa-arrow-down', 'Stock in', 'primary'], ['stockOut', 'fa-arrow-up', 'Stock out'], ['stockAdjust', 'fa-scale-balanced', 'Adjust']);
    } else {
      if (i.status === 'active' && !i.holderId) { out.push(['issue', 'fa-hand-holding', 'Issue', 'primary'], ['transfer', 'fa-right-left', 'Move']); }
      if (i.holderId && i.status !== 'lost') { out.push(['ret', 'fa-rotate-left', 'Return', 'primary'], ['tsdr', 'fa-file-signature', 'Make TSDR']); }
      if (i.status === 'active' && i.kind === 'asset') { out.push(['repair', 'fa-screwdriver-wrench', 'Send to repair']); }
      if (i.status === 'active') { out.push(['lost', 'fa-circle-question', 'Mark lost']); }
      if (i.status === 'lost') { out.push(['found', 'fa-magnifying-glass', 'Mark found', 'primary']); }
      if (i.status !== 'repair') { out.push(['dispose', 'fa-trash-can', 'Dispose']); }
      if (d.canMerge) { out.push(['merge', 'fa-object-group', 'Merge back']); }
    }
    out.push(['label', 'fa-qrcode', 'Label'], ['edit', 'fa-pen', 'Edit'], ['dup', 'fa-copy', 'Duplicate'], ['del', 'fa-eraser', 'Delete']);
    return out;
  }

  function partField(i, label) {
    return i.qty > 1 ? [{ name: 'qty', label: label, type: 'number', min: 1, placeholder: 'All ' + i.qty + ' ' + i.unit, help: 'part of the lot is split off' }] : [];
  }
  function backTo(i) { return function () { openItem(i.id); }; }
  function reopen(res, i) { refresh(); openItem(res.id || i.id); }

  /* every form a button in the item panel can open */
  var ACTIONS = {
    issue: function (i) {
      var want = false;
      form({ title: 'Issue ' + i.tag, action: 'issue', data: { id: i.id }, back: backTo(i), submit: 'Issue',
        fields: [{ name: 'emp', label: 'Issue to', type: 'emp' }].concat(partField(i, 'Quantity'), [
          { name: 'date', label: 'Date issued', type: 'date', value: BOOT.today }, { name: 'notes', label: 'Notes', type: 'textarea', max: 500 },
          { name: 'tsdr', label: 'Make a TSDR for it next', type: 'checkbox', value: i.kind === 'asset' }]),
        prepare: function (d) { want = !!d.tsdr; delete d.tsdr; },
        done: function (res) {
          if (!want) { reopen(res, i); return; }
          refresh();
          makeTsdr(res.empId, function () { openItem(res.id); }, [res.id]);
        } });
    },
    tsdr: function (i) { makeTsdr(i.holderId, backTo(i), [i.id]); },
    ret: function (i) {
      form({ title: 'Return ' + i.tag + ' from ' + i.holder, action: 'return', data: { id: i.id }, back: backTo(i), submit: 'Return',
        fields: partField(i, 'Quantity').concat([
          { name: 'location', label: 'Returned to', type: 'select', options: locOptions(), value: i.locationId },
          { name: 'condition', label: 'Condition', type: 'select', options: condOptions(), value: i.condition },
          { name: 'notes', label: 'Notes', type: 'textarea', max: 500 }]),
        done: function (res) { reopen(res, i); } });
    },
    transfer: function (i) {
      form({ title: 'Move ' + i.tag, action: 'transfer', data: { id: i.id }, back: backTo(i), submit: 'Move',
        fields: [{ name: 'location', label: 'New location', type: 'select', options: options(look.locations, 'Choose…', 'id', 'name', true) },
          { name: 'spot', label: 'Spot', help: 'cabinet, shelf, room corner', max: 100 }].concat(partField(i, 'Quantity'), [{ name: 'notes', label: 'Notes', type: 'textarea', max: 500 }]),
        done: function () { refresh(); openItem(i.id); } });
    },
    repair: function (i) {
      form({ title: 'Send ' + i.tag + ' to repair', action: 'repair_open', data: { id: i.id }, back: backTo(i), submit: 'Send',
        fields: [{ name: 'kind', label: 'Type', type: 'select', options: '<option value="repair">Repair</option><option value="service">Servicing</option>' },
          { name: 'problem', label: 'Problem or work needed', type: 'textarea', max: 500 }, { name: 'vendor', label: 'Vendor', max: 120 },
          { name: 'sentOn', label: 'Date sent', type: 'date', value: BOOT.today }].concat(partField(i, 'Quantity')),
        done: function (res) { refresh(); openItem(res.itemId); } });
    },
    lost: function (i) {
      form({ title: 'Mark ' + i.tag + ' lost', action: 'mark_lost', data: { id: i.id }, back: backTo(i), submit: 'Mark lost', tone: 'danger',
        fields: partField(i, 'Quantity').concat([{ name: 'notes', label: 'What happened', type: 'textarea', max: 500 }]),
        done: function (res) { reopen(res, i); } });
    },
    found: function (i) {
      form({ title: 'Mark ' + i.tag + ' found', action: 'mark_found', data: { id: i.id }, back: backTo(i), submit: 'Mark found',
        fields: [{ name: 'location', label: 'Found at', type: 'select', options: locOptions(), value: i.locationId }, { name: 'notes', label: 'Notes', type: 'textarea', max: 500 }],
        done: function (res) { reopen(res, i); } });
    },
    dispose: function (i) {
      form({ title: 'Dispose of ' + i.tag, action: 'dispose', data: { id: i.id }, back: backTo(i), submit: 'Dispose', tone: 'danger',
        fields: [{ name: 'method', label: 'How', type: 'select', options: options(kv(look.disposal), 'Choose…') },
          { name: 'date', label: 'Date', type: 'date', value: BOOT.today }].concat(partField(i, 'Quantity'), [{ name: 'notes', label: 'Notes', type: 'textarea', max: 300 }]),
        done: function (res) { reopen(res, i); } });
    },
    stockIn: function (i) {
      form({ title: 'Stock in: ' + i.name, action: 'stock_in', data: { id: i.id }, back: backTo(i), submit: 'Stock in',
        fields: [{ name: 'qty', label: 'Quantity received', type: 'number', min: 1, help: i.unit }, { name: 'unitCost', label: 'Unit cost', type: 'number', step: '0.01', value: i.unitCost },
          { name: 'supplier', label: 'Supplier', max: 120, value: i.supplier }, { name: 'reference', label: 'Reference', help: 'invoice or PO', max: 60 }, { name: 'notes', label: 'Notes', max: 300 }],
        done: function () { refresh(); openItem(i.id); } });
    },
    stockOut: function (i) {
      form({ title: 'Stock out: ' + i.name + ' (' + i.qty + ' ' + i.unit + ' on hand)', action: 'stock_out', data: { id: i.id }, back: backTo(i), submit: 'Stock out',
        fields: [{ name: 'qty', label: 'Quantity released', type: 'number', min: 1, help: i.unit }, { name: 'emp', label: 'To employee', type: 'emp' },
          { name: 'department', label: 'or to department', type: 'select', options: options(look.departments, 'Choose…') },
          { name: 'notes', label: 'Purpose', max: 300 }, { name: 'reference', label: 'Reference', help: 'request or slip number', max: 60 }],
        done: function () { refresh(); openItem(i.id); } });
    },
    stockAdjust: function (i) {
      form({ title: 'Adjust: ' + i.name, action: 'stock_adjust', data: { id: i.id }, back: backTo(i), submit: 'Adjust',
        fields: [{ name: 'qty', label: 'Correct quantity on hand', type: 'number', min: 0, value: i.qty, help: i.unit }, { name: 'notes', label: 'Reason', max: 300 }],
        done: function () { refresh(); openItem(i.id); } });
    },
  };

  /* ------------------------------------------------------------ add / edit */
  var CAT_ICON = { APP: 'fa-tv', FUR: 'fa-couch', FIX: 'fa-lightbulb', EQP: 'fa-laptop', DOC: 'fa-file-lines', SUP: 'fa-box-open', OTH: 'fa-cube' };
  var KIND_ICON = { asset: 'fa-cube', document: 'fa-file-lines', consumable: 'fa-box-open' };
  var UNITS = ['pc', 'set', 'unit', 'box', 'pack', 'ream', 'roll', 'bottle'];

  function section(title, body, kinds) {
    return '<section class="inv-sec"' + (kinds ? ' data-kind="' + kinds + '"' : '') + '><h5 class="inv-sec__title">' + esc(title) + '</h5>' + body + '</section>';
  }
  function fold(title, hint, body, open) {
    return '<details class="inv-fold"' + (open ? ' open' : '') + '><summary><span>' + esc(title) + '<small>' + esc(hint) + '</small></span><i class="fa-solid fa-chevron-down"></i></summary>'
      + '<div class="inv-fold__body">' + body + '</div></details>';
  }
  function two(a, b, kinds) { return '<div class="inv-two"' + (kinds ? ' data-kind="' + kinds + '"' : '') + '>' + a + b + '</div>'; }
  function radios(name, list, on) {
    return list.map(function (o) {
      return '<label class="inv-seg"><input type="radio" name="' + name + '" value="' + esc(o.id) + '"' + (String(o.id) === String(on) ? ' checked' : '') + '><span>' + esc(o.name) + '</span></label>';
    }).join('');
  }

  function tagRange(units) { return units[0].tag + ' to ' + units[units.length - 1].tag; }

  /** Several units saved as separate items: the next job is their labels. */
  function unitsSaved(res) {
    var n = res.units.length;
    showModal(n + ' items added', '<p class="inv-unitsok"><b>' + esc(res.name) + '</b> is saved as ' + n + ' separate items, ' + esc(tagRange(res.units)) + '.</p>'
      + '<p class="inv-unitstags">' + res.units.map(function (u) { return '<span class="inv-tag">' + esc(u.tag) + '</span>'; }).join('') + '</p>',
      '<button type="button" class="wd-btn wd-btn--ghost" id="invUnitsClose">Close</button>'
      + '<button type="button" class="wd-btn wd-btn--primary" id="invUnitsLabels"><i class="fa-solid fa-qrcode"></i> Print ' + n + ' labels</button>', true);
    $id('invUnitsClose').addEventListener('click', closeModal);
    $id('invUnitsLabels').addEventListener('click', function () {
      printLabels(res.units.map(function (u) { return { tag: u.tag, name: res.name }; }));
    });
  }

  /** i: edit it; dup: start a new item from it; supply: a new consumable. */
  function itemForm(i, dup, supply) {
    var editing = !!i && !dup, v = i || {}, isSup = i ? i.kind === 'consumable' : !!supply;
    var cats = catsOf(function (c) {
      if (i) { return c.kind === i.kind && (c.active || c.id === i.categoryId); }   // keeps a category switched off since
      return c.active && (supply ? isSupply(c) : !isSupply(c));
    });
    if (!cats.length) { alert('Add a category in Setup first.'); return; }
    if (!look.locations.some(function (l) { return l.active; })) { alert('Add a location in Setup first.'); return; }
    var catId = v.categoryId || cats[0].id;
    var catOf = function (id) { return catsOf(function (x) { return x.id === +id; })[0] || cats[0]; };
    var text = function (name, label, val, extra) { var o = extra || {}; o.name = name; o.label = label; o.value = val; return fieldHtml(o); };

    var picker = cats.length === 1 ? '<input type="hidden" name="categoryId" value="' + catId + '">'
      : section('What are you adding?', '<div class="inv-cats" role="radiogroup" aria-label="Category">' + cats.map(function (c) {
        return '<label class="inv-cat"><input type="radio" name="categoryId" value="' + c.id + '"' + (c.id === catId ? ' checked' : '') + '>'
          + '<span><i class="fa-solid ' + (CAT_ICON[c.code] || KIND_ICON[c.kind]) + '"></i>' + esc(c.name) + '</span></label>';
      }).join('') + '</div>');

    var qtyField = editing && isSup
      ? '<div class="wd-field"><label>Quantity on hand</label><div class="inv-readonly">' + v.qty + ' ' + esc(v.unit) + '<small>Changes through Stock in, Stock out or Adjust</small></div></div>'
      : '<div class="wd-field"><label for="invQty">' + (isSup ? 'Opening quantity' : 'Quantity') + '</label><div class="inv-stepper">'
        + '<button type="button" data-step="-1" aria-label="One less"><i class="fa-solid fa-minus"></i></button>'
        + '<input class="wd-input" type="number" id="invQty" name="qty" min="' + (isSup ? 0 : 1) + '" step="1" value="' + (dup ? (isSup ? 0 : 1) : (v.qty === undefined ? (isSup ? 0 : 1) : v.qty)) + '">'
        + '<button type="button" data-step="1" aria-label="One more"><i class="fa-solid fa-plus"></i></button></div></div>';
    var unitField = '<div class="wd-field"><label for="invUnit">Unit</label><input class="wd-input" id="invUnit" name="unit" maxlength="20" list="invUnits" value="' + esc(v.unit || 'pc') + '">'
      + '<datalist id="invUnits">' + UNITS.map(function (u) { return '<option value="' + u + '">'; }).join('') + '</datalist></div>';
    var eachField = editing || isSup ? '' : '<div class="inv-eachunit lcy-hidden" id="invEachBox"><label class="lcy-check"><input type="checkbox" name="eachUnit" value="1"> Give each one its own tag and QR label</label><small id="invEachHint"></small></div>';

    var basics = section('Basics', text('name', 'Name', v.name, { required: true, max: 150, placeholder: isSup ? 'e.g. Bond paper A4, Printer ink' : 'e.g. Split-type aircon, Monobloc chair, Business permit' })
      + two(qtyField, unitField) + eachField
      + '<div class="wd-field" data-kind="asset"><label>Condition</label><div class="inv-segs" role="radiogroup" aria-label="Condition">' + radios('condition', kv(look.conditions), v.condition || 'good') + '</div></div>');

    var place = editing
      ? '<div class="wd-field"><label>Location</label><div class="inv-readonly">' + esc(v.location) + '<small>Use Move on the item to change it</small></div></div>'
      : text('locationId', 'Location', v.locationId, { type: 'select', options: locOptions() });
    var tag = editing ? text('tag', 'Tag number', v.tag, { max: 30, help: 'change it if the sticker was replaced' })
      : '<div class="inv-tagline"><i class="fa-solid fa-qrcode"></i><span id="invTagHint"></span><button type="button" class="inv-link" id="invTagOwn">Use an existing sticker</button></div>'
        + '<div class="lcy-hidden" id="invTagBox">' + text('tag', 'Tag number on the sticker', '', { max: 30 }) + '</div>';
    var where = section('Where it is', two(place, text('spot', 'Spot', v.spot, { max: 100, placeholder: 'Cabinet, shelf, room corner' })) + tag);

    var product = section('Product details', two(text('brand', 'Brand', v.brand, { max: 80 }), text('model', 'Model', v.model, { max: 80 }))
      + two(text('serial', 'Serial no.', dup ? '' : v.serial, { max: 80 }), text('warrantyUntil', 'Warranty until', dup ? '' : v.warrantyUntil, { type: 'date' }), 'asset'), 'asset consumable');
    var paper = section('Document details', two(text('docNumber', 'Document no.', dup ? '' : v.docNumber, { max: 80 }), text('docIssuer', 'Issued by', v.docIssuer, { max: 120, placeholder: 'Agency or office' }))
      + two(text('docIssuedOn', 'Date issued', v.docIssuedOn, { type: 'date' }), text('expiresOn', 'Expires on', v.expiresOn, { type: 'date' }))
      + text('remindDays', 'Remind me this many days before it expires', v.remindDays === undefined ? 30 : v.remindDays, { type: 'number' }), 'document');
    var stock = section('Low-stock alert', text('reorderLevel', 'Alert me at this quantity', v.reorderLevel, { type: 'number', placeholder: 'Leave blank for no alert' }), 'consumable');

    var bought = !!(v.companyId || v.acquiredOn || v.supplier || v.unitCost !== undefined && v.unitCost !== null || v.reference);
    var purchase = fold('Purchase details', 'Company, supplier, cost, invoice',
      two(text('companyId', 'Company', v.companyId, { type: 'select', options: options(look.companies, 'Not specified') }), text('acquiredOn', 'Date acquired', v.acquiredOn, { type: 'date' }))
      + two(text('supplier', 'Supplier', v.supplier, { max: 120 }), text('unitCost', 'Unit cost', v.unitCost, { type: 'number', step: '0.01', placeholder: '0.00' }))
      + text('reference', 'Reference', v.reference, { max: 60, placeholder: 'Invoice or PO number' }), bought);
    var notes = fold('Description and notes', 'Anything else worth keeping',
      text('description', 'Description', v.description, { type: 'textarea', max: 500 }) + text('notes', 'Notes', v.notes, { type: 'textarea', max: 500 }), !!(v.description || v.notes));

    var submit = editing ? 'Save changes' : (isSup ? 'Save supply' : 'Save item');
    form({ title: editing ? 'Edit ' + v.tag : (isSup ? 'Add supply' : 'Add item'), action: 'item_save', data: { id: editing ? v.id : '' }, panel: true,
      back: i ? function () { openItem(i.id); } : null, submit: submit,
      body: picker + basics + where + product + paper + stock + purchase + notes,
      done: function (res) { refresh(); if (res.units) { unitsSaved(res); } else { openItem(res.id); } },
      another: editing ? null : function (res) {
        ['name', 'tag', 'serial', 'warrantyUntil', 'docNumber', 'docIssuedOn', 'expiresOn', 'reference', 'description', 'notes'].forEach(function (n) { el[n].value = ''; });
        $id('invFormOk').textContent = 'Saved ' + (res.units ? res.units.length + ' items, ' + tagRange(res.units) : res.tag) + '. Ready for the next one.';
        $id('invModalBody').scrollTop = 0;
        el.name.focus();
        refresh();
      } });

    var box = $id('invForm'), el = box.elements;
    function fit() {
      var c = catOf(el.categoryId.value);
      each(box, '[data-kind]', function (s) { s.classList.toggle('lcy-hidden', s.getAttribute('data-kind').split(' ').indexOf(c.kind) < 0); });
      var q = $id('invQty'), n = q ? parseInt(q.value, 10) || 0 : 0, can = !!el.eachUnit && c.kind === 'asset' && n > 1;
      if (el.eachUnit) {
        $id('invEachBox').classList.toggle('lcy-hidden', !can);
        if (!can) { el.eachUnit.checked = false; }
      }
      var apart = can && el.eachUnit.checked;
      if (can) { $id('invEachHint').textContent = apart ? 'Saves ' + n + ' separate items of 1 ' + (el.unit.value || 'pc') + ' each.' : 'Leave this off to keep them as one row.'; }
      el.serial.closest('.wd-field').classList.toggle('lcy-hidden', apart);   // one serial cannot be true of every unit
      if ($id('invTagHint')) {
        $id('invTagHint').innerHTML = apart ? 'Gets ' + n + ' <b>' + esc(c.code) + '</b> tag numbers when you save' : 'Gets the next <b>' + esc(c.code) + '</b> tag number when you save';
        $id('invTagOwn').classList.toggle('lcy-hidden', apart);
      }
      if (apart) {
        el.serial.value = '';
        if ($id('invTagBox')) {
          el.tag.value = '';
          $id('invTagBox').classList.add('lcy-hidden');
          $id('invTagOwn').closest('.inv-tagline').classList.remove('lcy-hidden');
        }
      }
      $id('invFormGo').lastChild.textContent = ' ' + (apart ? 'Save ' + n + ' items' : submit);
    }
    box.addEventListener('change', function (ev) { if (ev.target.name === 'categoryId' || ev.target.name === 'eachUnit') { fit(); } });
    box.addEventListener('input', function (ev) { if (ev.target.name === 'qty' || ev.target.name === 'unit') { fit(); } });
    each(box, '[data-step]', function (b) {
      b.addEventListener('click', function () {
        var q = $id('invQty'), n = (parseInt(q.value, 10) || 0) + +b.getAttribute('data-step');
        q.value = Math.max(+q.min, n);
        fit();
      });
    });
    if ($id('invTagOwn')) {
      $id('invTagOwn').addEventListener('click', function () {
        $id('invTagBox').classList.remove('lcy-hidden');
        this.closest('.inv-tagline').classList.add('lcy-hidden');
        el.tag.focus();
      });
    }
    fit();
    if (!editing) { setTimeout(function () { el.name.focus(); }, 320); }
  }

  /* ------------------------------------------------------------ files */
  /** A phone photo is several MB: shrink it in the browser before sending, as Applicants does. */
  function shrink(file) {
    return new Promise(function (done) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !window.URL || !document.createElement('canvas').toBlob) { done(file); return; }
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        var k = Math.min(1, 2400 / Math.max(img.naturalWidth, img.naturalHeight));
        if (k === 1 && file.size < 1024 * 1024) { done(file); return; }
        var c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k);
        c.height = Math.round(img.naturalHeight * k);
        var g = c.getContext('2d');
        g.fillStyle = '#fff';
        g.fillRect(0, 0, c.width, c.height);
        g.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) { done(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.85);
      };
      img.onerror = function () { URL.revokeObjectURL(url); done(file); };
      img.src = url;
    });
  }

  function attach(itemId, file) {
    if (!file) { return; }
    var kind = $id('invUpKind').value, msg = $id('invUpMsg');
    msg.textContent = 'Uploading…';
    (file.type.indexOf('image/') === 0 ? shrink(file) : Promise.resolve(file)).then(function (f) {
      if (BOOT.maxUpload && f.size > BOOT.maxUpload) {
        msg.textContent = 'That file is ' + (f.size / 1048576).toFixed(1) + ' MB; this server accepts ' + (BOOT.maxUpload / 1048576).toFixed(0) + ' MB at most.';
        return;
      }
      var fd = new FormData();
      fd.append('action', 'file_add');
      fd.append('token', BOOT.token);
      fd.append('id', itemId);
      fd.append('kind', kind);
      fd.append('file', f, f.name);
      fetch(BOOT.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'The file may be too large for the server.' }; }); })
        .catch(function () { return { status: 'error', msg: 'Could not reach the server — check your connection.' }; })
        .then(function (res) {
          if (res.status !== 'ok') { msg.textContent = res.msg; return; }
          openItem(itemId);
        });
    });
  }

  /* ------------------------------------------------------------ Items */
  function loadItems() {
    return L.post('items', { kind: 'property', q: $id('invQ').value.trim(), category: $id('invFCat').value, location: $id('invFLoc').value, filterStatus: $id('invFStatus').value })
      .then(function (res) {
        if (res.status !== 'ok') { $id('invItems').innerHTML = empty(res.msg); return; }
        rows = res.items;
        selected = {};
        drawStats(res.stats);
        drawItems(res.limit);
      });
  }

  function drawStats(s) {
    function tile(label, value) { return '<div class="wd-stat"><div class="wd-stat__label">' + esc(label) + '</div><div class="wd-stat__value">' + esc(value) + '</div></div>'; }
    $id('invStats').innerHTML = tile('Items on record', s.items) + tile('Issued', s.issued) + tile('Under repair', s.repair) + tile('Alerts', s.alerts) + tile('Recorded value', money(s.cost));
    $id('invAlertCount').textContent = s.alerts > 0 ? s.alerts : '';
  }

  function drawItems(limit) {
    var box = $id('invItems');
    if (!rows.length) { box.innerHTML = empty(look.locations.length ? 'No items match.' : 'Nothing here yet — add your locations in Setup, then add your first item.'); updateBulk(); return; }
    box.innerHTML = table(['<input type="checkbox" id="invAll" aria-label="Select all">', 'Tag', 'Item', 'Category', 'Qty', 'Location', 'Issued to', 'Status', 'Value'], rows.map(function (i) {
      return '<tr class="inv-row" data-id="' + i.id + '"><td class="inv-tick"><input type="checkbox" data-sel="' + i.id + '" aria-label="Select ' + esc(i.tag) + '"></td>'
        + '<td><span class="inv-tag">' + esc(i.tag) + '</span></td><td><b>' + esc(i.name) + '</b>' + (i.brand || i.model ? '<br><span class="lcy-muted">' + esc((i.brand + ' ' + i.model).trim()) + '</span>' : '')
        + (i.serial ? '<br><span class="lcy-muted">SN ' + esc(i.serial) + '</span>' : '') + '</td>'
        + '<td>' + esc(i.category) + '</td><td>' + i.qty + ' ' + esc(i.unit) + '</td><td>' + esc(i.location) + (i.spot ? '<br><span class="lcy-muted">' + esc(i.spot) + '</span>' : '') + '</td>'
        + '<td>' + esc(i.holder) + (i.holderId && !i.ackAt ? '<br><span class="lcy-muted">not confirmed</span>' : '')
        + (i.holderId ? '<br><span class="lcy-muted">' + (i.tsdr ? 'TSDR ' + esc(i.tsdr.no) : 'No TSDR yet') + '</span>' : '') + '</td><td>' + statusPill(i) + '</td><td>' + esc(money(i.totalCost)) + '</td></tr>';
    }).join(''), 'inv-grid inv-t-items') + (rows.length >= limit ? '<p class="lcy-muted inv-more">Showing the first ' + limit + '. Narrow the search to see the rest.</p>' : '');
    each(box, 'tr.inv-row', function (tr) {
      tr.addEventListener('click', function (ev) {
        if (ev.target.closest('.inv-tick')) { return; }
        openItem(+tr.getAttribute('data-id'));
      });
    });
    each(box, '[data-sel]', function (c) {
      c.addEventListener('change', function () {
        if (c.checked) { selected[c.getAttribute('data-sel')] = true; } else { delete selected[c.getAttribute('data-sel')]; }
        updateBulk();
      });
    });
    $id('invAll').addEventListener('change', function () {
      var on = this.checked;
      each(box, '[data-sel]', function (c) { c.checked = on; if (on) { selected[c.getAttribute('data-sel')] = true; } else { delete selected[c.getAttribute('data-sel')]; } });
      updateBulk();
    });
    updateBulk();
  }

  function selectedRows() { return rows.filter(function (i) { return selected[i.id]; }); }
  function updateBulk() {
    var n = Object.keys(selected).length;
    $id('invBulk').classList.toggle('lcy-hidden', n === 0);
    $id('invBulkCount').textContent = n + ' selected';
  }

  /** Several selected items to one person, then (by default) one TSDR for all of them. */
  function bulkIssue() {
    var list = selectedRows(), want = false;
    form({ title: 'Issue ' + list.length + ' item(s)', action: 'issue_many', data: { ids: list.map(function (i) { return i.id; }) }, submit: 'Issue',
      fields: [{ name: 'emp', label: 'Issue to', type: 'emp' }, { name: 'date', label: 'Date issued', type: 'date', value: BOOT.today },
        { name: 'notes', label: 'Notes', type: 'textarea', max: 500 }, { name: 'tsdr', label: 'Make one TSDR for them next', type: 'checkbox', value: true }],
      prepare: function (d) { want = !!d.tsdr; delete d.tsdr; },
      done: function (res) {
        refresh();
        if (want) { makeTsdr(res.empId, null, res.ids); } else { closeModal(); }
      } });
    var f = $id('invForm');
    f.insertAdjacentHTML('afterbegin', '<p class="lcy-muted">' + list.map(function (i) { return esc(i.tag); }).join(', ') + '</p>');
  }

  function bulkMove() {
    var list = selectedRows();
    form({ title: 'Move ' + list.length + ' item(s)', action: 'transfer', data: { ids: list.map(function (i) { return i.id; }) }, submit: 'Move',
      fields: [{ name: 'location', label: 'New location', type: 'select', options: options(look.locations, 'Choose…', 'id', 'name', true) }, { name: 'spot', label: 'Spot', max: 100 }, { name: 'notes', label: 'Notes', type: 'textarea', max: 500 }],
      done: function () { closeModal(); refresh(); } });
  }

  /* ------------------------------------------------------------ Excel (HTML table saved as .xls, like the viewer pages) */
  function saveXls(html, name) {
    var blob = new Blob(['﻿<html><head><meta charset="utf-8"></head><body>' + html + '</body></html>'], { type: 'application/vnd.ms-excel' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name + '_' + BOOT.today + '.xls';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
  }
  function xlsTable(head, lines) {
    return '<table border="1"><tr>' + head.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('') + '</tr>'
      + lines.map(function (l) { return '<tr>' + l.map(function (c) { return '<td>' + esc(c) + '</td>'; }).join('') + '</tr>'; }).join('') + '</table>';
  }
  function exportItems() {
    if (!rows.length) { alert('Nothing to export.'); return; }
    saveXls(xlsTable(['Tag', 'Item', 'Category', 'Qty', 'Unit', 'Location', 'Spot', 'Issued to', 'Issued on', 'Condition', 'Status', 'Brand', 'Model', 'Serial no.', 'Document no.', 'Expires', 'Warranty until', 'Acquired', 'Supplier', 'Unit cost', 'Total cost', 'Reference'],
      rows.map(function (i) {
        return [i.tag, i.name, i.category, i.qty, i.unit, i.location, i.spot, i.holder, i.issuedOn, i.conditionLabel, i.statusLabel, i.brand, i.model, i.serial, i.docNumber, i.expiresOn, i.warrantyUntil, i.acquiredOn, i.supplier, i.unitCost, i.totalCost, i.reference];
      })), 'Inventory');
  }

  /* ------------------------------------------------------------ Supplies */
  function loadSupplies() {
    return L.post('items', { kind: 'consumable', q: $id('invSupQ').value.trim(), location: $id('invSupLoc').value }).then(function (res) {
      var box = $id('invSupplies');
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      supplies = res.items;
      if (!supplies.length) { box.innerHTML = empty('No supplies recorded yet.'); return; }
      box.innerHTML = table(['Tag', 'Supply', 'On hand', 'Reorder at', 'Location', 'Unit cost', ''], supplies.map(function (i, n) {
        return '<tr class="inv-row" data-id="' + i.id + '"><td><span class="inv-tag">' + esc(i.tag) + '</span></td><td><b>' + esc(i.name) + '</b></td>'
          + '<td>' + i.qty + ' ' + esc(i.unit) + (i.low ? ' ' + pill(i.qty === 0 ? 'danger' : 'warn', i.qty === 0 ? 'Out' : 'Low') : '') + '</td>'
          + '<td>' + (i.reorderLevel === null ? '<span class="lcy-muted">—</span>' : i.reorderLevel) + '</td><td>' + esc(i.location) + (i.spot ? '<br><span class="lcy-muted">' + esc(i.spot) + '</span>' : '') + '</td>'
          + '<td>' + esc(money(i.unitCost)) + '</td><td class="inv-tick"><span class="lcy-actions">'
          + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-stock="stockIn" data-n="' + n + '"><i class="fa-solid fa-arrow-down"></i> In</button>'
          + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-stock="stockOut" data-n="' + n + '"><i class="fa-solid fa-arrow-up"></i> Out</button></span></td></tr>';
      }).join(''), 'inv-grid inv-t-sup');
      each(box, 'tr.inv-row', function (tr) {
        tr.addEventListener('click', function (ev) { if (!ev.target.closest('.inv-tick')) { openItem(+tr.getAttribute('data-id')); } });
      });
      each(box, '[data-stock]', function (b) {
        b.addEventListener('click', function () { ACTIONS[b.getAttribute('data-stock')](supplies[+b.getAttribute('data-n')]); });
      });
    });
  }

  function ledgerTable(list) {
    var TYPE = { 'in': ['ok', 'In'], out: ['warn', 'Out'], adjust: ['info', 'Adjust'] };
    return table(['When', 'Type', 'Qty', 'Balance', 'To / from', 'Note', 'By'], list.map(function (m) {
      return '<tr><td>' + esc(L.fmtDateTime(m.at)) + '</td><td>' + pill(TYPE[m.type][0], TYPE[m.type][1]) + '</td><td>' + (m.qty > 0 ? '+' : '') + m.qty + '</td><td>' + m.balance + '</td>'
        + '<td>' + esc(m.to || m.department || m.supplier) + '</td><td>' + esc(m.notes) + (m.reference ? ' <span class="lcy-muted">' + esc(m.reference) + '</span>' : '') + '</td><td>' + esc(m.by) + '</td></tr>';
    }).join(''));
  }

  function loadUsage() {
    return L.post('usage_report', { from: $id('invUseFrom').value, to: $id('invUseTo').value }).then(function (res) {
      var box = $id('invUsage');
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      usage = res;
      box.innerHTML = !res.rows.length ? empty('Nothing released in this period.') : table(['Supply', 'Department', 'Employee', 'Released', 'Times', 'Value'], res.rows.map(function (r) {
        return '<tr><td><b>' + esc(r.name) + '</b><br><span class="lcy-muted">' + esc(r.tag) + '</span></td><td>' + esc(r.department) + '</td><td>' + esc(r.employee) + '</td><td>' + r.released + ' ' + esc(r.unit) + '</td><td>' + r.lines + '</td><td>' + esc(money(r.value)) + '</td></tr>';
      }).join(''));
    });
  }

  /* ------------------------------------------------------------ People + accountability form */
  function loadPeople() {
    return L.post('people', {}).then(function (res) {
      var box = $id('invPeople');
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      if (!res.people.length) { box.innerHTML = empty('Nothing is issued to anyone yet.'); return; }
      box.innerHTML = table(['Employee', 'Department', 'Items', 'Not confirmed', 'Last issued', ''], res.people.map(function (p) {
        return '<tr><td><b>' + esc(p.name) + '</b><br><span class="lcy-muted">' + esc(p.empId) + (p.position ? ' · ' + esc(p.position) : '') + '</span>' + (p.left ? ' ' + L.statusPill(p.status) : '') + '</td>'
          + '<td>' + esc(p.department) + '</td><td>' + p.items + '</td><td>' + (p.pending ? pill('warn', p.pending) : '') + '</td><td>' + esc(L.fmtDate(p.lastIssued)) + '</td>'
          + '<td><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-emp="' + esc(p.empId) + '">View</button></td></tr>';
      }).join(''), 'inv-grid inv-t-people');
      each(box, '[data-emp]', function (b) { b.addEventListener('click', function () { openPerson(b.getAttribute('data-emp')); }); });
    });
  }

  function openPerson(empId) {
    L.post('person', { emp: empId }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      var e = res.employee;
      var body = '<p class="lcy-muted">' + esc(e.empId) + (e.position ? ' · ' + esc(e.position) : '') + (e.department ? ' · ' + esc(e.department) : '') + '</p>'
        + (res.items.length ? table(['Tag', 'Item', 'Qty', 'Issued', 'Confirmed', ''], personRows(res.items)) : empty('Nothing issued.'));
      body += '<h5 class="inv-sec__title inv-tsdr-sub">TSDRs</h5><div id="invPersonTsdrs"><div class="lcy-empty">Loading&hellip;</div></div>';
      showModal(e.name, body, '<button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>'
        + '<button type="button" class="wd-btn wd-btn--ghost" id="invFormPrint"><i class="fa-solid fa-print"></i> Accountability form</button>'
        + '<button type="button" class="wd-btn wd-btn--primary" id="invMakeTsdr"><i class="fa-solid fa-file-signature"></i> Make TSDR</button>', true);
      each($id('invModalBody'), '[data-item]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openItem(+a.getAttribute('data-item')); }); });
      each($id('invModalBody'), '[data-tsdrgroup]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openTsdr(+a.getAttribute('data-tsdrgroup'), function () { openPerson(e.empId); }); }); });
      $id('invFormPrint').addEventListener('click', function () { accountabilityPicker(res, function () { openPerson(e.empId); }); });
      $id('invMakeTsdr').addEventListener('click', function () { makeTsdr(e.empId, function () { openPerson(e.empId); }); });
      L.post('tsdr_list', { emp: e.empId }).then(function (r) {
        var box = $id('invPersonTsdrs');
        if (!box) { return; }
        if (r.status !== 'ok') { box.innerHTML = empty(r.msg); return; }
        box.innerHTML = r.tsdrs.length ? tsdrTable(r.tsdrs, false) : empty('No TSDR yet.');
        wireTsdrs(box, function () { openPerson(e.empId); });
      });
    });
  }

  /** Items in groups by the TSDR that covers them (newest first), then the ones without one: [{key, tsdr, items}]. */
  function byTsdr(items) {
    var groups = [], by = {};
    items.forEach(function (i) {
      var k = i.tsdr ? 't' + i.tsdr.id : 'none';
      if (!by[k]) { by[k] = { key: k, tsdr: i.tsdr || null, items: [] }; groups.push(by[k]); }
      by[k].items.push(i);
    });
    return groups.sort(function (a, b) {
      if (!a.tsdr || !b.tsdr) { return a.tsdr ? -1 : (b.tsdr ? 1 : 0); }
      return String(b.tsdr.createdAt).localeCompare(String(a.tsdr.createdAt)) || b.tsdr.id - a.tsdr.id;
    });
  }

  /** A person's issued items, grouped under the TSDR that covers them (newest first), then the ones without one. */
  function personRows(items) {
    var groups = byTsdr(items), many = groups.length > 1 || groups[0].tsdr;   // nothing to group when no item has a TSDR
    return groups.map(function (g) {
      var head = !many ? '' : '<tr class="inv-group"><td colspan="6">' + (g.tsdr
        ? '<a href="#" data-tsdrgroup="' + g.tsdr.id + '"><i class="fa-solid fa-file-signature"></i> TSDR ' + esc(g.tsdr.no) + '</a> ' + tsdrPill(g.tsdr) + ' <span class="lcy-muted">' + esc(L.fmtDate(g.tsdr.createdAt)) + ' · ' + g.items.length + ' item(s)</span>'
        : '<span class="lcy-muted">No TSDR yet · ' + g.items.length + ' item(s)</span>') + '</td></tr>';
      return head + g.items.map(function (i) {
        return '<tr><td><span class="inv-tag">' + esc(i.tag) + '</span></td><td>' + esc(i.name) + (i.serial ? '<br><span class="lcy-muted">SN ' + esc(i.serial) + '</span>' : '') + '</td><td>' + i.qty + ' ' + esc(i.unit) + '</td><td>' + esc(L.fmtDate(i.issuedOn)) + '</td>'
          + '<td>' + (i.ackAt ? esc(L.fmtDate(i.ackAt)) : pill('warn', 'No')) + '</td><td><a href="#" data-item="' + i.id + '">Open</a></td></tr>';
      }).join('');
    }).join('');
  }

  function absUrl(path) { try { return new URL(path, location.href).href; } catch (err) { return path; } }

  /**
   * Prints html once its pictures (the letterhead logo) have loaded: a print started before that leaves them blank.
   * title names the saved PDF.
   */
  function printSheet(html, title) {
    function whenReady(doc, imgs, go) {   // the page and its pictures are in, or 5 s have passed
      var tries = 0, timer = setInterval(function () {
        var done = doc.readyState === 'complete' && [].every.call(imgs(), function (i) { return i.complete; });
        if (done || ++tries > 50) { clearInterval(timer); go(); }
      }, 100);
    }
    if (document.documentElement.classList.contains('wdapp')) {   // the app prints a page of its own, not this one
      var w = window.open('about:blank');
      w.document.open();
      w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + esc(title || document.title) + '</title>'
        + '<link rel="stylesheet" href="' + absUrl('assets/css/inventory.css') + '"></head>'
        + '<body class="inv-printing"><div id="invPrint">' + html + '</div></body></html>');
      w.document.close();
      whenReady(w.document, function () { return w.document.images; }, function () { w.focus(); w.print(); });
      return;
    }
    var was = document.title;
    $id('invPrint').innerHTML = html;
    document.body.classList.add('inv-printing');
    if (title) { document.title = title; }
    var off = function () {
      document.body.classList.remove('inv-printing');
      $id('invPrint').innerHTML = '';
      document.title = was;
      window.removeEventListener('afterprint', off);
    };
    window.addEventListener('afterprint', off);
    whenReady(document, function () { return $id('invPrint').querySelectorAll('img'); }, function () { window.print(); });
  }

  /* ------------------------------------------------------------ printed forms on the company letterhead */
  /** cls: the form's own class; meta: docMeta() pieces; body: docSec() sections. */
  function docSheet(cls, title, meta, body) {
    var lh = BOOT.letterhead || {}, logo = lh.logo || BOOT.logo;
    var foot = [lh.address, lh.email ? 'E-mail: ' + lh.email : ''].filter(Boolean).map(esc).join('<i></i>');
    return '<div class="inv-doc ' + cls + '">'
      + '<table class="inv-doc__page">'
      + '<thead><tr><td><header class="inv-doc__head">'
      +   (logo ? '<img class="inv-doc__logo" src="' + esc(absUrl(logo)) + '" alt="' + esc(BOOT.company) + '">' : '<b class="inv-doc__org">' + esc(BOOT.company) + '</b>')
      +   '<div class="inv-doc__title"><h1>' + esc(title) + '</h1><div class="inv-doc__meta">' + meta + '</div></div>'
      + '</header></td></tr></thead>'
      + '<tfoot><tr><td><div class="inv-doc__footspace"></div></td></tr></tfoot>'   // keeps the page's text clear of the footer
      + '<tbody><tr><td>' + body + '</td></tr></tbody>'
      + '</table>'
      + (foot ? '<footer class="inv-doc__foot">' + foot + '</footer>' : '')
      + '</div>';
  }
  function docMeta(label, html) { return '<span><small>' + esc(label) + '</small><b>' + html + '</b></span>'; }
  function docSec(n, title, hint, html) {
    return '<section class="inv-doc__sec"><h2><span>' + n + '</span>' + esc(title) + (hint ? '<small>' + esc(hint) + '</small>' : '') + '</h2>' + html + '</section>';
  }
  /** html: already escaped; empty prints a blank line to write on. */
  function docField(label, html) { return '<div class="inv-doc__f"><label>' + esc(label) + '</label><div>' + (html || '&nbsp;') + '</div></div>'; }
  function docFields(list, cols) { return '<div class="inv-doc__fields inv-doc__fields--' + (cols || list.length) + '">' + list.join('') + '</div>'; }
  function docOpt(on, label, round) {
    return '<span class="inv-doc__opt' + (round ? ' inv-doc__opt--round' : '') + (on ? ' is-on' : '') + '"><i>' + (on && !round ? '&#10003;' : '') + '</i>' + esc(label) + '</span>';
  }
  function docNote(label, text) { return '<div class="inv-doc__note"><label>' + esc(label) + '</label><div>' + esc(text || '') + '</div></div>'; }
  function docSign(name, label, hint) {
    return '<div class="inv-doc__sign"><b>' + esc(name || '') + '</b><span></span><strong>' + esc(label) + '</strong>' + (hint ? '<small>' + esc(hint) + '</small>' : '') + '</div>';
  }
  function ymd(s) { return s ? String(s).slice(0, 10) : ''; }
  /** A TSDR item's Confirmed cell: when it was confirmed, or when it came back (each item on its own). */
  function tsdrItemCell(x) {
    if (x.left) { return '<td class="is-nowrap inv-doc__pending">' + (x.left.type === 'disposed' ? 'Disposed' : 'Returned') + '<small>' + esc(ymd(x.left.on)) + '</small></td>'; }
    if (x.held === false) { return '<td class="is-nowrap inv-doc__pending">No longer issued</td>'; }
    return docConfirmed(x.ackAt);
  }
  /** A table cell for when the holder confirmed receipt: "2025-10-14" over "03:05 PM", or "Not yet". */
  function docConfirmed(s) {
    var m = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/.exec(String(s || ''));
    if (!m) { return '<td class="is-nowrap inv-doc__pending">Not yet</td>'; }
    var h = +m[2], ap = h < 12 ? 'AM' : 'PM';
    h = h % 12 || 12;
    return '<td class="is-nowrap">' + m[1] + '<small>' + (h < 10 ? '0' : '') + h + ':' + m[3] + ' ' + ap + '</small></td>';
  }

  /** Choose which of a person's issued items go on the accountability form. pick: item ids to tick (default: all). */
  function accountabilityPicker(res, back, pick) {
    var e = res.employee;
    var groups = res.items.length ? byTsdr(res.items) : [], many = groups.length > 1 || (groups[0] && groups[0].tsdr);
    var body = !res.items.length ? empty('Nothing is issued to ' + e.name + '.')
      : '<div id="invAccPick"><p class="lcy-muted">Tick the items to put on the form. Leave out what was already signed for, or print one form per TSDR.</p>'
      + '<label class="lcy-check inv-accpick__all"><input type="checkbox" id="invAccAll"> <b>All items</b> <span class="lcy-muted" id="invAccCount"></span></label>'
      + groups.map(function (g) {
        return '<div class="inv-accpick__group">'
          + (many ? '<label class="lcy-check inv-accpick__head"><input type="checkbox" data-accgroup="' + g.key + '"> '
            + (g.tsdr ? '<b>TSDR ' + esc(g.tsdr.no) + '</b> <span class="lcy-muted">' + esc(L.fmtDate(g.tsdr.createdAt)) + '</span>' : '<b>No TSDR yet</b>')
            + ' <span class="lcy-muted">&middot; ' + g.items.length + ' item(s)</span></label>' : '')
          + g.items.map(function (i) {
            return '<label class="lcy-check inv-accpick__item"><input type="checkbox" name="accItem" value="' + i.id + '" data-g="' + g.key + '"' + (!pick || pick.indexOf(i.id) >= 0 ? ' checked' : '') + '>'
              + ' <span><span class="inv-tag">' + esc(i.tag) + '</span> ' + esc(i.name) + (i.serial ? ' <span class="lcy-muted">SN ' + esc(i.serial) + '</span>' : '') + '</span></label>';
          }).join('') + '</div>';
      }).join('') + '</div>';
    showModal('Accountability form · ' + e.name, body,
      '<button type="button" class="wd-btn wd-btn--ghost inv-formback" id="invAccBack">Back</button>'
      + '<button type="button" class="wd-btn wd-btn--primary" id="invAccPrint"' + (res.items.length ? '' : ' disabled') + '><i class="fa-solid fa-print"></i> Print form</button>', true);
    $id('invAccBack').addEventListener('click', function () { if (back) { back(); } else { closeModal(); } });
    var box = $id('invAccPick');   // its own wrapper: a listener on the modal body would outlive this picker
    if (!box) { return; }          // nothing issued
    var boxes = function (sel) { return [].slice.call(box.querySelectorAll(sel)); };
    function setGroup(cb, list) {
      var on = list.filter(function (x) { return x.checked; }).length;
      cb.checked = on > 0 && on === list.length;
      cb.indeterminate = on > 0 && on < list.length;
      return on;
    }
    function sync() {
      boxes('[data-accgroup]').forEach(function (g) { setGroup(g, boxes('input[data-g="' + g.getAttribute('data-accgroup') + '"]')); });
      var on = setGroup($id('invAccAll'), boxes('input[name="accItem"]'));
      $id('invAccCount').textContent = '(' + on + ' of ' + res.items.length + ' ticked)';
      $id('invAccPrint').disabled = !on;
    }
    box.addEventListener('change', function (ev) {
      var t = ev.target;
      if (t.id === 'invAccAll') { boxes('input[name="accItem"]').forEach(function (x) { x.checked = t.checked; }); }
      if (t.hasAttribute('data-accgroup')) { boxes('input[data-g="' + t.getAttribute('data-accgroup') + '"]').forEach(function (x) { x.checked = t.checked; }); }
      sync();
    });
    sync();
    $id('invAccPrint').addEventListener('click', function () {
      var ids = boxes('input[name="accItem"]:checked').map(function (x) { return +x.value; });
      printAccountability({ employee: e, printedOn: res.printedOn, items: res.items.filter(function (i) { return ids.indexOf(i.id) >= 0; }) });
    });
  }

  /** The property accountability form for the items given. */
  function printAccountability(a) {
    var e = a.employee, withTsdr = a.items.some(function (i) { return i.tsdr !== undefined; });
    var head = ['#', 'Tag', 'Item', 'Serial no.', 'Qty', 'Condition', 'Issued', 'Confirmed'].concat(withTsdr ? ['TSDR #'] : []);
    var rows = a.items.map(function (i, n) {
      var bm = ((i.brand || '') + ' ' + (i.model || '')).trim();
      return '<tr><td class="is-num">' + (n + 1) + '</td><td class="is-nowrap">' + esc(i.tag) + '</td>'
        + '<td><b>' + esc(i.name) + '</b>' + (bm ? '<small>' + esc(bm) + '</small>' : '') + '</td><td>' + esc(i.serial) + '</td>'
        + '<td class="is-nowrap">' + i.qty + ' ' + esc(i.unit) + '</td><td>' + esc(i.conditionLabel) + '</td><td class="is-nowrap">' + esc(ymd(i.issuedOn)) + '</td>' + docConfirmed(i.ackAt)
        + (withTsdr ? '<td class="is-nowrap">' + esc(i.tsdr ? i.tsdr.no : '') + '</td>' : '') + '</tr>';
    }).join('');
    var body = docSec(1, 'Employee', '', docFields([docField('Name', esc(e.name)), docField('Employee ID', esc(e.empId)), docField('Position', esc(e.position)), docField('Account / department', esc(e.department))]))
      + docSec(2, 'Items issued', a.items.length + ' item(s)', a.items.length
        ? '<table class="inv-doc__table"><thead><tr>' + head.map(function (h, n) { return '<th' + (n ? '' : ' class="is-num"') + '>' + esc(h) + '</th>'; }).join('') + '</tr></thead><tbody>' + rows + '</tbody></table>'
        : '<p class="inv-doc__none">No items.</p>')
      + docSec(3, 'Acknowledgement', '', '<div class="inv-doc__keep"><p>By signing below, I acknowledge that:</p><ol class="inv-doc__terms">'
        + '<li>I received the items listed above in the condition stated.</li>'
        + '<li>I will take care of them and use them for company work only.</li>'
        + '<li>I will report any loss or damage right away.</li>'
        + '<li>I will return all of them upon request or when my employment ends.</li></ol>'
        + '<div class="inv-doc__signs">' + docSign(e.name, 'Employee', 'Signature over printed name / Date') + docSign('', 'Released by', 'Signature over printed name / Date') + '</div></div>');
    printSheet(docSheet('inv-doc--acc', 'Property Accountability Form',
      docMeta('Date', esc(ymd(a.printedOn))) + docMeta('Employee ID', esc(e.empId)) + docMeta('Items', String(a.items.length)), body), 'Accountability - ' + e.name);
  }

  /* ------------------------------------------------------------ TSDR (Technical Services Data Report) */
  var TSDR_TONE = { done: 'ok', pending: 'warn', cannot: 'danger', others: 'info' };
  function tsdrPill(t) { return pill(TSDR_TONE[t.status] || 'info', t.statusLabel); }

  function tsdrTable(list, withEmp) {
    return table(['TSDR #'].concat(withEmp ? ['Requisitioner'] : []).concat(['Services', 'Items', 'Status', 'Accomplished', '']), list.map(function (t) {
      return '<tr><td><b>' + esc(t.no) + '</b>' + (t.source === 'projinventory' ? '<br><span class="lcy-muted">from projInventory</span>' : '') + '</td>'
        + (withEmp ? '<td>' + esc(t.requisitioner) + '<br><span class="lcy-muted">' + esc(t.accountDept || t.empId) + '</span></td>' : '')
        + '<td>' + esc(t.serviceLabels.join(', ')) + '</td><td>' + t.itemCount + '</td><td>' + tsdrPill(t) + '</td>'
        + '<td>' + esc(L.fmtDateTime(t.accomplishedAt)) + '</td>'
        + '<td><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-tsdr="' + t.id + '">View</button></td></tr>';
    }).join(''));
  }
  /** back: where the TSDR panel's Back button returns (the person, or nothing for the TSDR tab). */
  function wireTsdrs(box, back) {
    each(box, '[data-tsdr]', function (b) { b.addEventListener('click', function () { openTsdr(+b.getAttribute('data-tsdr'), back); }); });
  }

  function loadTsdrs() {
    return L.post('tsdr_list', { q: $id('invTsdrQ').value }).then(function (res) {
      var box = $id('invTsdrs');
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      if (!res.tsdrs.length) { box.innerHTML = empty($id('invTsdrQ').value ? 'No TSDR matches.' : 'No TSDR yet. Press Make TSDR and choose the employee.'); return; }
      box.innerHTML = tsdrTable(res.tsdrs, true) + (res.tsdrs.length >= res.limit ? '<p class="lcy-muted">Showing the newest ' + res.limit + '. Search to find older ones.</p>' : '');
      wireTsdrs(box, null);
    });
  }

  /** pick: item ids to tick (default: the ones no TSDR covers yet). */
  function makeTsdr(empId, back, pick) {
    L.post('tsdr_draft', { emp: empId }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      tsdrForm(res, null, back, pick);
    });
  }

  function openTsdr(id, back) {
    L.post('tsdr', { id: id }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      var t = res.tsdr;
      var facts = '<dl class="lcy-facts">' + fact('Requisitioner', t.requisitioner + ' · ' + t.empId) + fact('Account / department', t.accountDept)
        + fact('Date needed', L.fmtDate(t.dateNeeded)) + fact('Services', t.serviceLabels.join(', ') + (t.otherService ? ' (' + t.otherService + ')' : ''))
        + fact('Approved by', t.approvedBy) + fact('Requested from', res.from[t.requestedFrom]) + fact('Received by', t.receivedBy)
        + fact('SysAd personnel', t.sysad) + fact('Status details', t.statusDetails) + fact('Evaluation', t.evaluation)
        + fact('Recommendation', t.recommendation) + fact('Accomplished', L.fmtDateTime(t.accomplishedAt)) + '</dl>';
      var items = t.items.length ? '<ul class="inv-tsdr-items">' + t.items.map(function (x) {
        return '<li>' + (x.itemId ? '<a href="#" data-item="' + x.itemId + '">' + esc(x.text) + '</a>' : esc(x.text))
          + (x.left ? ' ' + pill('info', (x.left.type === 'disposed' ? 'Disposed ' : 'Returned ') + L.fmtDate(x.left.on)) : '') + '</li>';
      }).join('') + '</ul>' : empty('No items on this TSDR.');
      showModal('TSDR ' + t.no, '<p>' + tsdrPill(t) + (t.source === 'projinventory' ? ' <span class="lcy-muted">Brought over from projInventory</span>' : '') + '</p>'
        + facts + '<h5 class="inv-sec__title">Items</h5>' + items,
        '<button type="button" class="wd-btn wd-btn--ghost inv-formback" id="invTsdrBack">' + (back ? 'Back' : 'Close') + '</button>'
        + '<button type="button" class="wd-btn wd-btn--ghost" id="invTsdrEdit"><i class="fa-solid fa-pen"></i> Edit</button>'
        + '<button type="button" class="wd-btn wd-btn--ghost" id="invTsdrAcc"><i class="fa-solid fa-file-contract"></i> Accountability form</button>'
        + '<button type="button" class="wd-btn wd-btn--primary" id="invTsdrPrint"><i class="fa-solid fa-print"></i> Print</button>', true);
      each($id('invModalBody'), '[data-item]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openItem(+a.getAttribute('data-item')); }); });
      $id('invTsdrBack').addEventListener('click', function () { if (back) { back(); } else { closeModal(); } });
      $id('invTsdrEdit').addEventListener('click', function () { tsdrForm(res, t, function () { openTsdr(t.id, back); }); });
      $id('invTsdrPrint').addEventListener('click', function () { printTsdr(t, res); });
      $id('invTsdrAcc').addEventListener('click', function () {   // the person's items, with this TSDR's ones ticked
        L.post('person', { emp: t.empId }).then(function (p) {
          if (p.status !== 'ok') { alert(p.msg); return; }
          accountabilityPicker(p, function () { openTsdr(t.id, back); }, t.items.map(function (x) { return x.itemId; }).filter(Boolean));
        });
      });
    });
  }

  /** ctx: the draft (or the tsdr answer) with employee, items, services, from, statuses; t: the TSDR being edited. */
  function tsdrForm(ctx, t, back, pick) {
    var v = t || ctx.defaults, e = ctx.employee || { name: t.requisitioner, empId: t.empId };
    var text = function (name, label, val, extra) { var o = extra || {}; o.name = name; o.label = label; o.value = val; return fieldHtml(o); };
    var on = {}, items = ctx.items.slice();
    if (t) {
      t.items.forEach(function (x) {
        if (x.itemId) { on[x.itemId] = true; }
        if (x.itemId && !items.some(function (i) { return i.id === x.itemId; })) { items.push({ id: x.itemId, tag: x.tag, text: x.text, gone: true }); }
      });
    } else {
      if (pick) { pick.forEach(function (id) { on[+id] = true; }); }
      else { items.forEach(function (i) { if (i.pending) { on[i.id] = true; } }); }
    }
    var svcOn = {};
    (t ? t.services : []).forEach(function (s) { svcOn[s] = true; });

    var who = section('Requisitioner', '<div class="wd-field"><label>Employee</label><div class="inv-readonly">' + esc(e.name) + '<small>' + esc(e.empId) + (t ? ' · TSDR ' + esc(t.no) : ' · gets number ' + esc(ctx.nextNo) + ' when saved') + '</small></div></div>'
      + two(text('accountDept', 'Account / department', v.accountDept, { max: 120 }), text('dateNeeded', 'Date needed', v.dateNeeded, { type: 'date' })));
    var svcs = section('Service being requested', '<div class="inv-tsdr-svcs">' + Object.keys(ctx.services).map(function (k) {
      return '<label class="lcy-check"><input type="checkbox" name="services[]" value="' + esc(k) + '"' + (svcOn[k] ? ' checked' : '') + '> ' + esc(ctx.services[k]) + '</label>';
    }).join('') + '</div><div id="invTsdrOther" class="lcy-hidden">' + text('otherService', 'Other service', t ? t.otherService : '', { max: 200 }) + '</div>');
    var list = items.length ? '<div class="inv-tsdr-picks">' + items.map(function (i) {
      return '<label class="lcy-check"><input type="checkbox" name="items[]" value="' + i.id + '"' + (on[i.id] ? ' checked' : '') + '> <span>' + esc(i.text)
        + (i.pending && !t ? ' ' + pill('warn', 'No TSDR yet') : '') + (i.gone ? ' ' + pill('info', 'No longer issued') : '') + '</span></label>';
    }).join('') + '</div>' : empty('Nothing is issued to ' + e.name + '. A TSDR can still be made for a service alone.');
    var picks = section('Items', '<p class="lcy-muted">Items issued to ' + esc(e.name) + '. The ones without a TSDR yet are ticked.</p>' + list);
    var approval = section('Approval', fieldHtml({ type: 'emp', name: 'approvedBy', label: 'Approved by', value: t ? t.approvedById : '' }));
    var receiver = section('Receiver', '<div class="wd-field"><label>Requested from</label><div class="inv-segs">' + radios('requestedFrom', kv(ctx.from), v.requestedFrom || 'it') + '</div></div>'
      + text('receivedBy', 'Received by', t ? t.receivedBy : e.name, { max: 150, placeholder: 'Leave blank to write it by hand on the printout' }));
    var sysad = section('SysAd personnel', fieldHtml({ type: 'emp', name: 'sysad', label: 'SysAd personnel assigned', value: v.sysadId })
      + '<div class="wd-field"><label>Status of request</label><div class="inv-segs">' + radios('status', kv(ctx.statuses), v.status || 'done') + '</div></div>'
      + text('statusDetails', 'Status details', t ? t.statusDetails : '', { max: 300, placeholder: 'N/A' })
      + two(text('evaluation', 'Evaluation', t ? t.evaluation : '', { type: 'textarea', max: 500, placeholder: 'N/A' }),
            text('recommendation', 'Recommendation', t ? t.recommendation : '', { type: 'textarea', max: 500, placeholder: 'N/A' }))
      + text('accomplishedAt', 'Date and time accomplished', (v.accomplishedAt || '').replace(' ', 'T').slice(0, 16), { type: 'datetime-local' }));

    form({ title: t ? 'Edit TSDR ' + t.no : 'Make TSDR', action: 'tsdr_save', panel: true, back: back || null, submit: t ? 'Save changes' : 'Save TSDR',
      data: t ? { id: t.id } : { emp: e.empId },
      body: who + svcs + picks + approval + receiver + sysad,
      done: function (res) { if (tab === 'tsdr') { loadTsdrs(); } openTsdr(res.id, back); } });

    var box = $id('invForm');
    [['approvedBy', t ? t.approvedBy : ''], ['sysad', t ? t.sysad : v.sysad]].forEach(function (p) {
      var input = box.querySelector('[data-emp="' + p[0] + '"]');
      input.value = p[1] || '';
      L.employeeSearch(input, box.querySelector('[data-empbox="' + p[0] + '"]'), function (x) { box.elements[p[0]].value = x.empId; });
      input.addEventListener('input', function () { if (!input.value.trim()) { box.elements[p[0]].value = ''; } });
    });
    function fit() { $id('invTsdrOther').classList.toggle('lcy-hidden', !box.querySelector('input[name="services[]"][value="others"]').checked); }
    box.addEventListener('change', fit);
    fit();
    formStart = $(box).serialize();
  }

  /** The printed TSDR on the letterhead, with the old projInventory form's sections and fields. */
  function printTsdr(t, ctx) {
    function stamp(s) {   // "2024-03-12 / 01:27 PM", as the old form printed it
      if (!s) { return ''; }
      var m = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/.exec(String(s));
      if (!m) { return String(s); }
      var h = +m[2], ap = h < 12 ? 'AM' : 'PM';
      h = h % 12 || 12;
      return m[1] + ' / ' + (h < 10 ? '0' : '') + h + ':' + m[3] + ' ' + ap;
    }
    function opts(map, isOn, round) {
      return '<div class="inv-doc__opts' + (round ? ' inv-doc__opts--inline' : '') + '">' + Object.keys(map).map(function (k) { return docOpt(isOn(k), map[k], round); }).join('') + '</div>';
    }
    var svc = {};
    t.services.forEach(function (s) { svc[s] = true; });
    var linked = t.items.some(function (x) { return x.itemId; });   // items in Inventory: show when each was confirmed
    var items = t.items.length
      ? '<table class="inv-doc__table"><thead><tr><th class="is-num">#</th><th>Tag</th><th>Item</th>' + (linked ? '<th>Confirmed</th>' : '') + '</tr></thead><tbody>' + t.items.map(function (x, n) {
          var text = x.tag && x.text.slice(-(x.tag.length + 3)) === ' (' + x.tag + ')' ? x.text.slice(0, -(x.tag.length + 3)) : x.text;
          return '<tr><td class="is-num">' + (n + 1) + '</td><td class="is-nowrap">' + esc(x.tag || '') + '</td><td>' + esc(text) + '</td>' + (!linked ? '' : x.itemId ? tsdrItemCell(x) : '<td></td>') + '</tr>';
        }).join('') + '</tbody></table>'
      : '<p class="inv-doc__none">No item: service only.</p>';

    var body = docSec(1, 'Request', 'To be filled out by the requisitioner',
        docFields([docField('Requisitioner', esc(t.requisitioner)), docField('Employee ID', esc(t.empId)), docField('Account / department', esc(t.accountDept)), docField('Date needed', esc(ymd(t.dateNeeded)))])
        + '<div class="inv-doc__label">Service being requested</div>' + opts(ctx.services, function (k) { return svc[k]; })
        + (svc.others ? docFields([docField('Others, please specify', esc(t.otherService))], 1) : '')
        + '<div class="inv-doc__label">Items (' + t.items.length + ')</div>' + items
        + '<div class="inv-doc__signs">' + docSign(t.requisitioner, 'Requisitioner', 'Signature over printed name') + docSign(t.approvedBy, 'Approved by', 'Signature over printed name') + '</div>')
      + docSec(2, 'Receiving', 'To be filled out by the receiver',
        '<div class="inv-doc__split inv-doc__split--r"><div><div class="inv-doc__label">Requested from</div>' + opts(ctx.from, function (k) { return t.requestedFrom === k; }, true) + '</div>'
        + '<div class="inv-doc__signs">' + docSign(t.receivedBy, 'Received by', 'Name and signature') + docSign('', 'Date & time received', 'Write the current date and time') + '</div></div>')
      + docSec(3, 'Service report', 'To be filled out by the SysAd personnel',
        docFields([docField('SysAd personnel assigned', esc(t.sysad)), docField('Date & time accomplished', esc(stamp(t.accomplishedAt)))])
        + '<div class="inv-doc__split"><div><div class="inv-doc__label">Status of request</div>' + opts(ctx.statuses, function (k) { return t.status === k; }, true)
        + docFields([docField('Status details', esc(t.statusDetails))], 1) + '</div>'
        + '<div class="inv-doc__signs inv-doc__signs--one">' + docSign(t.sysad, 'SysAd personnel', 'Signature over printed name') + '</div></div>'
        + '<div class="inv-doc__notes">' + docNote('Evaluation', t.evaluation) + docNote('Recommendation', t.recommendation) + '</div>')
      + docSec(4, 'Acknowledgement', '', '<div class="inv-doc__ack"><p>The requisitioner or a representative of the requesting account or department signs here once the request is completed.</p>'
        + docSign(t.requisitioner, 'Requisitioner / Date', 'Signature over printed name') + '</div>');
    printSheet(docSheet('inv-doc--tsdr', 'Technical Services Data Report',
      docMeta('TSDR No.', esc(t.no)) + docMeta('Date', esc(ymd(t.createdAt))) + docMeta('Status', esc(t.statusLabel)), body), 'TSDR ' + t.no);
  }

  /* ------------------------------------------------------------ QR labels */
  function qrSvg(text) {
    var q = window.qrcode(0, 'M');
    q.addData(text);
    q.make();
    return q.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
  }
  function printLabels(list) {
    if (!window.qrcode) { alert('The QR library could not be loaded — check the internet connection and reload the page.'); return; }
    printSheet('<div class="inv-labels">' + list.map(function (i) {
      return '<div class="inv-label"><div class="inv-label__qr">' + qrSvg(BOOT.siteUrl + '/inventory?tag=' + encodeURIComponent(i.tag)) + '</div>'
        + '<div class="inv-label__text"><b>' + esc(i.tag) + '</b><span>' + esc(i.name) + '</span><small>' + esc(BOOT.company) + '</small></div></div>';
    }).join('') + '</div>');
  }

  /* ------------------------------------------------------------ Repairs */
  var REP_TONE = { reported: 'danger', open: 'warn', done: 'ok', cancelled: 'info' };
  var REP_TEXT = { reported: 'Reported', open: 'Open', done: 'Done', cancelled: 'Cancelled' };
  function repairPill(r) { return pill(REP_TONE[r.status], REP_TEXT[r.status]); }

  function loadRepairs() {
    return L.post('repairs', {}).then(function (res) {
      var box = $id('invRepairs');
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      if (!res.repairs.length) { box.innerHTML = empty('No repairs on file. Open an item to send it to repair.'); return; }
      box.innerHTML = table(['Item', 'Status', 'Problem', 'Vendor', 'Sent', 'Back', 'Cost', ''], res.repairs.map(function (r, n) {
        var b = '';
        if (r.status === 'reported') { b = '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-rep="accept" data-n="' + n + '">Accept</button> <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-rep="cancel" data-n="' + n + '">Dismiss</button>'; }
        if (r.status === 'open') { b = '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-rep="close" data-n="' + n + '">Close</button> <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-rep="cancel" data-n="' + n + '">Cancel</button>'; }
        return '<tr><td><a href="#" data-item="' + r.itemId + '"><b>' + esc(r.tag) + '</b></a><br><span class="lcy-muted">' + esc(r.name) + '</span></td><td>' + repairPill(r) + '</td>'
          + '<td>' + esc(r.problem) + (r.reportedBy ? '<br><span class="lcy-muted">reported by ' + esc(r.reportedBy) + '</span>' : '') + (r.result ? '<br><span class="lcy-muted">' + esc(r.result) + '</span>' : '') + '</td>'
          + '<td>' + esc(r.vendor) + '</td><td>' + esc(L.fmtDate(r.sentOn)) + '</td><td>' + esc(L.fmtDate(r.returnedOn)) + '</td><td>' + esc(money(r.cost)) + '</td><td class="inv-nowrap">' + b + '</td></tr>';
      }).join(''), 'inv-grid inv-t-rep');
      each(box, '[data-item]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openItem(+a.getAttribute('data-item')); }); });
      each(box, '[data-rep]', function (b) { b.addEventListener('click', function () { repairAct(b.getAttribute('data-rep'), res.repairs[+b.getAttribute('data-n')]); }); });
    });
  }

  function repairAct(what, r) {
    var after = function () { closeModal(); refresh(); };
    if (what === 'accept') {
      form({ title: 'Accept report: ' + r.tag, action: 'repair_open', data: { repairId: r.id }, submit: 'Send to repair',
        fields: [{ name: 'problem', label: 'Problem or work needed', type: 'textarea', max: 500, value: r.problem }, { name: 'vendor', label: 'Vendor', max: 120 }, { name: 'sentOn', label: 'Date sent', type: 'date', value: BOOT.today }], done: after });
    } else if (what === 'close') {
      form({ title: 'Close repair: ' + r.tag, action: 'repair_close', data: { repairId: r.id }, submit: 'Close repair',
        fields: [{ name: 'returnedOn', label: 'Date back', type: 'date', value: BOOT.today }, { name: 'cost', label: 'Cost', type: 'number', step: '0.01' }, { name: 'result', label: 'What was done', type: 'textarea', max: 500 }], done: after });
    } else {
      form({ title: 'Cancel: ' + r.tag, action: 'repair_cancel', data: { repairId: r.id }, submit: 'Cancel it', tone: 'danger',
        fields: [{ name: 'notes', label: 'Why', type: 'textarea', max: 500 }], done: after });
    }
  }

  /* ------------------------------------------------------------ Counts */
  var openCountId = null;

  function loadCounts() {
    if (openCountId) { return openCount(openCountId); }
    return L.post('counts', {}).then(function (res) {
      var box = $id('invCounts');
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      box.innerHTML = '<div class="wd-card__head"><h3>Physical counts</h3><button type="button" class="wd-btn wd-btn--primary wd-btn--sm" id="invCountNew"><i class="fa-solid fa-plus"></i> Start a count</button></div>'
        + (res.counts.length ? table(['Count', 'Location', 'Status', 'Checked', 'Differences', 'Started', ''], res.counts.map(function (c) {
          return '<tr><td><b>' + esc(c.title) + '</b></td><td>' + esc(c.location) + '</td><td>' + pill(c.status === 'open' ? 'warn' : 'ok', c.status === 'open' ? 'Open' : 'Closed') + '</td>'
            + '<td>' + c.checked + ' of ' + c.total + '</td><td>' + (c.variances || '') + '</td><td>' + esc(L.fmtDate(c.startedAt)) + '</td>'
            + '<td><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-count="' + c.id + '">Open</button></td></tr>';
        }).join('')) : empty('No counts yet. Start one for a location, then tick off what you find.'));
      $id('invCountNew').addEventListener('click', startCount);
      each(box, '[data-count]', function (b) { b.addEventListener('click', function () { openCount(+b.getAttribute('data-count')); }); });
    });
  }

  function startCount() {
    form({ title: 'Start a count', action: 'count_start', submit: 'Start',
      fields: [{ name: 'location', label: 'Location', type: 'select', options: options(look.locations, 'Choose…', 'id', 'name', true) }, { name: 'title', label: 'Title', max: 120, help: 'optional' }, { name: 'notes', label: 'Notes', type: 'textarea', max: 500 }],
      done: function (res) { closeModal(); openCount(res.id); } });
  }

  var RESULT = { found: ['ok', 'Found'], missing: ['danger', 'Missing'], misplaced: ['warn', 'Misplaced'], extra: ['info', 'Unrecorded'], moved: ['info', 'Moved away'] };

  function openCount(id) {
    openCountId = id;
    return L.post('count', { id: id }).then(function (res) {
      if (res.status !== 'ok') { openCountId = null; alert(res.msg); return; }
      drawCount(res.count);
    });
  }

  function drawCount(c) {
    var open = c.status === 'open', box = $id('invCounts');
    var done = c.lines.filter(function (l) { return l.checkedAt; }).length;
    var head = '<div class="wd-card__head"><h3>' + esc(c.title) + '</h3><span class="lcy-actions">'
      + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invCountBack"><i class="fa-solid fa-arrow-left"></i> All counts</button>'
      + (open ? '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invCountExtra"><i class="fa-solid fa-plus"></i> Unrecorded item</button><button type="button" class="wd-btn wd-btn--primary wd-btn--sm" id="invCountClose"><i class="fa-solid fa-flag-checkered"></i> Close count</button>' : '')
      + '</span></div>';
    var bar = open ? '<div class="inv-toolbar"><span class="lcy-muted">' + esc(c.location) + ' · ' + done + ' of ' + c.lines.length + ' checked</span><span class="inv-spacer"></span>'
      + '<form id="invCountScan" class="inv-scan"><input type="text" class="wd-input" id="invCountTag" placeholder="Type or scan a tag, then Enter" autocomplete="off" aria-label="Tag number"><button type="submit" class="wd-btn wd-btn--primary wd-btn--sm">Check</button></form></div>'
      + '<p class="lcy-modal-error inv-more" id="invCountMsg"></p>' : applyBar(c);
    var body = c.lines.length ? table(['Tag', 'Item', 'Expected', 'Found', 'Result', ''], c.lines.map(function (l) {
      var r = RESULT[l.result], short = l.result === 'found' && l.found < l.expected;
      var found = open && !l.checkedAt ? '<input type="number" min="0" class="wd-input inv-qty" data-found="' + l.id + '" value="' + l.expected + '" aria-label="Quantity found">' : (l.found === null ? '' : l.found);
      var act = !open ? (l.applied ? '<span class="lcy-muted">applied</span>' : '') : (l.checkedAt
        ? '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-undo="' + l.id + '">Undo</button>'
        : '<button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-tick="' + l.itemId + '" data-line="' + l.id + '"><i class="fa-solid fa-check"></i> Found</button>');
      return '<tr><td><span class="inv-tag">' + esc(l.tag || '—') + '</span></td><td><b>' + esc(l.name) + '</b>' + (l.spot ? '<br><span class="lcy-muted">' + esc(l.spot) + '</span>' : '')
        + (l.holder ? '<br><span class="lcy-muted">with ' + esc(l.holder) + '</span>' : '') + (l.result === 'misplaced' ? '<br><span class="lcy-muted">recorded at ' + esc(l.itemLocation) + '</span>' : '') + '</td>'
        + '<td>' + l.expected + ' ' + esc(l.unit) + '</td><td>' + found + '</td><td>' + (r ? pill(short ? 'warn' : r[0], short ? 'Short by ' + (l.expected - l.found) : r[1]) : '') + '</td><td class="inv-nowrap">' + act + '</td></tr>';
    }).join(''), 'inv-grid inv-t-lines') : empty('Nothing is recorded at this location.');
    box.innerHTML = head + bar + body;

    $id('invCountBack').addEventListener('click', function () { openCountId = null; loadCounts(); });
    if (open) {
      $id('invCountClose').addEventListener('click', function () {
        var left = c.lines.length - done;
        if (!confirm(left ? left + ' item(s) are not checked and will be listed as missing. Close the count?' : 'Close this count?')) { return; }
        L.post('count_close', { id: c.id }).then(function (res) {
          if (res.status !== 'ok') { alert(res.msg); return; }
          openCount(c.id); refreshStats();
        });
      });
      $id('invCountExtra').addEventListener('click', function () {
        form({ title: 'Unrecorded item', action: 'count_extra', data: { id: c.id }, submit: 'Add',
          fields: [{ name: 'name', label: 'What was found', max: 150 }, { name: 'qty', label: 'Quantity', type: 'number', min: 1, value: 1 }, { name: 'notes', label: 'Notes', max: 300 }],
          done: function () { closeModal(); openCount(c.id); } });
      });
      $id('invCountScan').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var t = $id('invCountTag'), tag = t.value.trim();
        if (!tag) { return; }
        L.post('count_check', { id: c.id, tag: tag }).then(function (res) {
          if (res.status !== 'ok') { $id('invCountMsg').textContent = res.msg; t.select(); return; }
          openCount(c.id).then(function () { var n = $id('invCountTag'); if (n) { n.focus(); } });
        });
      });
      each(box, '[data-tick]', function (b) {
        b.addEventListener('click', function () {
          var inp = box.querySelector('[data-found="' + b.getAttribute('data-line') + '"]');
          L.post('count_check', { id: c.id, itemId: b.getAttribute('data-tick'), found: inp ? inp.value : '' }).then(function (res) {
            if (res.status !== 'ok') { alert(res.msg); return; }
            openCount(c.id);
          });
        });
      });
      each(box, '[data-undo]', function (b) {
        b.addEventListener('click', function () {
          L.post('count_uncheck', { lineId: b.getAttribute('data-undo') }).then(function (res) {
            if (res.status !== 'ok') { alert(res.msg); return; }
            openCount(c.id);
          });
        });
      });
    } else {
      each(box, '[data-apply]', function (b) {
        b.addEventListener('click', function () {
          if (!confirm(b.getAttribute('data-confirm'))) { return; }
          L.post('count_apply', { id: c.id, what: b.getAttribute('data-apply') }).then(function (res) {
            if (res.status !== 'ok') { alert(res.msg); return; }
            openCount(c.id); refreshStats();
          });
        });
      });
    }
  }

  function applyBar(c) {
    var open = function (fn) { return c.lines.filter(function (l) { return !l.applied && fn(l); }).length; };
    var short = function (l) { return (l.result === 'missing' || l.result === 'found') && l.found < l.expected; };
    var mis = open(function (l) { return l.result === 'misplaced' && l.kind !== 'consumable'; });
    var miss = open(function (l) { return short(l) && l.kind !== 'consumable'; });
    var stock = open(function (l) { return (l.result === 'missing' || l.result === 'found') && l.kind === 'consumable' && l.found !== l.expected; });
    var btn = function (what, n, label, text) {
      return n ? '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-apply="' + what + '" data-confirm="' + esc(text) + '">' + esc(label) + ' (' + n + ')</button>' : '';
    };
    var b = btn('misplaced', mis, 'Move misplaced items here', 'Move the misplaced items to ' + c.location + '?')
      + btn('missing', miss, 'Mark shortages lost', 'Mark the missing items and quantities as lost?')
      + btn('stock', stock, 'Post supply differences', 'Correct the supply balances to the counted quantities?');
    return '<div class="inv-toolbar"><span class="lcy-muted">' + esc(c.location) + ' · closed ' + esc(L.fmtDate(c.closedAt)) + '</span><span class="inv-spacer"></span>' + (b || '<span class="lcy-muted">Nothing left to apply.</span>') + '</div>';
  }

  /* ------------------------------------------------------------ Alerts */
  var ALERT_ICON = { expiry: 'fa-file-circle-exclamation', warranty: 'fa-shield-halved', low: 'fa-battery-quarter', problem: 'fa-triangle-exclamation', unconfirmed: 'fa-hourglass-half' };
  function loadAlerts() {
    return L.post('alerts', {}).then(function (res) {
      var box = $id('invAlerts');
      if (res.status !== 'ok') { box.innerHTML = empty(res.msg); return; }
      if (!res.alerts.length) { box.innerHTML = empty('Nothing needs attention.'); return; }
      box.innerHTML = table(['', 'Item', 'What', 'Date'], res.alerts.map(function (a) {
        return '<tr class="inv-row" data-id="' + a.itemId + '"><td><span class="wd-pill wd-pill--' + a.tone + '"><i class="fa-solid ' + (ALERT_ICON[a.type] || 'fa-bell') + '"></i></span></td>'
          + '<td><span class="inv-tag">' + esc(a.tag) + '</span> ' + esc(a.name) + '</td><td>' + esc(a.detail) + '</td><td>' + esc(L.fmtDate(a.date)) + '</td></tr>';
      }).join(''), 'inv-grid inv-t-alerts');
      each(box, 'tr.inv-row', function (tr) { tr.addEventListener('click', function () { openItem(+tr.getAttribute('data-id')); }); });
    });
  }

  /* ------------------------------------------------------------ Setup */
  function drawSetup() {
    $id('invCats').innerHTML = !look.categories.length ? empty('No categories yet.') : table(['Code', 'Name', 'Kind', 'Items', ''], look.categories.map(function (c, n) {
      return '<tr><td><span class="inv-tag">' + esc(c.code) + '</span></td><td>' + esc(c.name) + (c.active ? '' : ' ' + pill('info', 'Off')) + '</td><td>' + esc({ asset: 'Asset', document: 'Document', consumable: 'Consumable' }[c.kind]) + '</td><td>' + c.items + '</td>'
        + '<td><button type="button" class="wd-iconbtn" data-cat="' + n + '" aria-label="Edit ' + esc(c.name) + '"><i class="fa-solid fa-pen"></i></button></td></tr>';
    }).join(''), 'inv-grid inv-t-cats');
    $id('invLocs').innerHTML = !look.locations.length ? empty('No locations yet. Add every office, floor or storeroom you keep things in.') : table(['Name', 'Items', 'Notes', ''], look.locations.map(function (l, n) {
      return '<tr><td>' + esc(l.name) + (l.active ? '' : ' ' + pill('info', 'Off')) + '</td><td>' + l.items + '</td><td>' + esc(l.notes) + '</td>'
        + '<td><button type="button" class="wd-iconbtn" data-loc="' + n + '" aria-label="Edit ' + esc(l.name) + '"><i class="fa-solid fa-pen"></i></button></td></tr>';
    }).join(''), 'inv-grid inv-t-locs');
    each($id('invCats'), '[data-cat]', function (b) { b.addEventListener('click', function () { catForm(look.categories[+b.getAttribute('data-cat')]); }); });
    each($id('invLocs'), '[data-loc]', function (b) { b.addEventListener('click', function () { locForm(look.locations[+b.getAttribute('data-loc')]); }); });
  }
  function setupDone() { closeModal(); loadLookups().then(drawSetup); }
  function catForm(c) {
    var v = c || { active: true, kind: 'asset', sort: look.categories.length + 1 };
    form({ title: c ? 'Edit category' : 'Add category', action: 'cat_save', data: { id: c ? c.id : '' }, done: setupDone,
      fields: [{ name: 'name', label: 'Name', max: 60, value: v.name }, { name: 'code', label: 'Code', max: 6, value: v.code, help: 'tag prefix, e.g. FUR' },
        { name: 'kind', label: 'Kind', type: 'select', options: options(kv({ asset: 'Asset (has a holder and a location)', document: 'Document (has an expiry)', consumable: 'Consumable (stock in and out)' }), null), value: v.kind },
        { name: 'sort', label: 'Order', type: 'number', value: v.sort }, { name: 'active', label: 'In use', type: 'checkbox', value: v.active }] });
  }
  function locForm(l) {
    var v = l || { active: true };
    form({ title: l ? 'Edit location' : 'Add location', action: 'loc_save', data: { id: l ? l.id : '' }, done: setupDone,
      fields: [{ name: 'name', label: 'Name', max: 80, value: v.name, placeholder: 'e.g. 12F Pantry, HR Storeroom' }, { name: 'notes', label: 'Notes', max: 200, value: v.notes }, { name: 'active', label: 'In use', type: 'checkbox', value: v.active }] });
  }

  /* ------------------------------------------------------------ tabs */
  var PANES = { items: 'Items', supplies: 'Supplies', people: 'People', tsdr: 'Tsdr', repairs: 'Repairs', counts: 'Counts', alerts: 'Alerts', setup: 'Setup' };
  var LOAD = { items: loadItems, supplies: function () { return loadSupplies().then(loadUsage); }, people: loadPeople, tsdr: loadTsdrs, repairs: loadRepairs, counts: loadCounts, alerts: loadAlerts, setup: function () { drawSetup(); return Promise.resolve(); } };

  function go(name) {
    tab = name;
    Object.keys(PANES).forEach(function (k) { $id('invPane' + PANES[k]).classList.toggle('lcy-hidden', k !== name); });
    each($id('invTabs'), '.lcy-tab', function (b) {
      var on = b.getAttribute('data-tab') === name, T = $id('invTabs');
      b.classList.toggle('is-on', on);
      if (on) { T.scrollLeft += b.getBoundingClientRect().left - T.getBoundingClientRect().left - (T.clientWidth - b.offsetWidth) / 2; }
    });
    LOAD[name]();
  }

  /** The stat tiles live on the Items answer. */
  function refreshStats() {
    L.post('stats', {}).then(function (res) { if (res.status === 'ok') { drawStats(res.stats); } });
  }
  function refresh() { LOAD[tab](); if (tab !== 'items') { refreshStats(); } }

  /* ------------------------------------------------------------ wire up */
  $('#invModal .close').on('click', function () { plainClose = true; });
  // The phone's Back key hides the modal outright, so it steps out one level here: the
  // actions sheet, then a form back to its item, and typed work is never lost unasked.
  $('#invModal').on('hide.bs.modal', function (ev) {
    var plain = plainClose, sheet = $id('invMoreSheet'), f = $id('invForm');
    plainClose = false;
    if (ev.target !== this || plain) { return; }
    if (sheet && sheet.classList.contains('is-open')) { ev.preventDefault(); sheet.classList.remove('is-open'); return; }
    if (!f) { return; }
    if (formBack) { ev.preventDefault(); formBack(); return; }
    if ($(f).serialize() !== formStart && !confirm('Discard what you typed?')) { ev.preventDefault(); }
  });

  var timer = null;
  function later(fn) { clearTimeout(timer); timer = setTimeout(fn, 250); }

  each($id('invTabs'), '.lcy-tab', function (b) { b.addEventListener('click', function () { go(b.getAttribute('data-tab')); }); });
  $id('invQ').addEventListener('input', function () { later(loadItems); });
  ['invFCat', 'invFLoc', 'invFStatus'].forEach(function (id) { $id(id).addEventListener('change', loadItems); });
  $id('invSupQ').addEventListener('input', function () { later(loadSupplies); });
  $id('invSupLoc').addEventListener('change', loadSupplies);
  $id('invTsdrQ').addEventListener('input', function () { later(loadTsdrs); });
  // like the old app's TSDR button: choose the employee, then the form with their items without a TSDR ticked
  $id('invTsdrNew').addEventListener('click', function () {
    form({ title: 'Make TSDR', action: 'tsdr_draft', submit: 'Next', fields: [{ name: 'emp', label: 'Requisitioner (employee)', type: 'emp' }],
      done: function (res) { tsdrForm(res, null, null); } });
  });
  $id('invAdd').addEventListener('click', function () { itemForm(null); });
  $id('invSupAdd').addEventListener('click', function () { itemForm(null, false, true); });
  $id('invExport').addEventListener('click', exportItems);
  $id('invBulkLabels').addEventListener('click', function () { printLabels(selectedRows()); });
  $id('invBulkMove').addEventListener('click', bulkMove);
  $id('invBulkIssue').addEventListener('click', bulkIssue);
  $id('invCatAdd').addEventListener('click', function () { catForm(null); });
  $id('invLocAdd').addEventListener('click', function () { locForm(null); });
  $id('invUseRun').addEventListener('click', loadUsage);
  $id('invUseExport').addEventListener('click', function () {
    if (!usage || !usage.rows.length) { alert('Nothing to export.'); return; }
    saveXls(xlsTable(['Tag', 'Supply', 'Department', 'Employee', 'Released', 'Unit', 'Times', 'Value'], usage.rows.map(function (r) {
      return [r.tag, r.name, r.department, r.employee, r.released, r.unit, r.lines, r.value];
    })), 'Supply_usage_' + usage.from + '_to_' + usage.to);
  });
  $id('invUseFrom').value = BOOT.today.slice(0, 8) + '01';
  $id('invUseTo').value = BOOT.today;

  loadLookups().then(function () {
    if (!look) { return; }
    loadItems();
    if (BOOT.tag) {   // opened from a QR label
      L.post('tag_lookup', { tag: BOOT.tag }).then(function (res) {
        if (res.status !== 'ok') { alert(res.msg); return; }
        openItem(res.id);
      });
    }
  });
})();
