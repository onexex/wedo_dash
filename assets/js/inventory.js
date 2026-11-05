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
  function table(head, body) {
    return '<div class="wd-tablewrap inv-tablewrap"><table class="wd-table lcy-table"><thead><tr>' + head.map(function (h) { return '<th>' + h + '</th>'; }).join('')
      + '</tr></thead><tbody>' + body + '</tbody></table></div>';
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
  function closeModal() { $('#invModal').modal('hide'); }

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
    (spec.fields || []).forEach(function (fd) {
      if (fd.type === 'select' && fd.value !== undefined && fd.value !== null) { f.elements[fd.name].value = fd.value; }
      if (fd.type === 'emp') {
        L.employeeSearch(f.querySelector('[data-emp="' + fd.name + '"]'), f.querySelector('[data-empbox="' + fd.name + '"]'), function (e) { f.elements[fd.name].value = e.empId; });
      }
    });
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

    var countBar = d.openCount && i.status !== 'disposed' ? '<p class="lcy-note">Count open here: ' + esc(d.openCount.title)
      + ' <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invCountHere"><i class="fa-solid fa-check"></i> Mark found</button></p>' : '';

    var body = '<div class="inv-itemhead"><div><span class="inv-tag">' + esc(i.tag) + '</span> ' + statusPill(i)
      + (i.low ? ' ' + pill('danger', 'Low stock') : '') + '</div></div>' + countBar + facts + fam + filesHtml + repairs + ledger + hist;

    var acts = itemActions(i, d).map(function (a) {
      return '<button type="button" class="wd-btn wd-btn--' + (a[3] || 'ghost') + ' wd-btn--sm" data-act="' + a[0] + '"><i class="fa-solid ' + a[1] + '"></i> ' + esc(a[2]) + '</button>';
    }).join('');
    showModal(i.name, body, '<div class="inv-actions">' + acts + '</div>', true);

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
      b.addEventListener('click', function () { run[b.getAttribute('data-act')](); });
    });
    var box = $id('invModalBody');
    each(box, '[data-item]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openItem(+a.getAttribute('data-item')); }); });
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
      if (i.holderId && i.status !== 'lost') { out.push(['ret', 'fa-rotate-left', 'Return', 'primary']); }
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
      form({ title: 'Issue ' + i.tag, action: 'issue', data: { id: i.id }, back: backTo(i), submit: 'Issue',
        fields: [{ name: 'emp', label: 'Issue to', type: 'emp' }].concat(partField(i, 'Quantity'), [
          { name: 'date', label: 'Date issued', type: 'date', value: BOOT.today }, { name: 'notes', label: 'Notes', type: 'textarea', max: 500 }]),
        done: function (res) { reopen(res, i); } });
    },
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

    var basics = section('Basics', text('name', 'Name', v.name, { required: true, max: 150, placeholder: isSup ? 'e.g. Bond paper A4, Printer ink' : 'e.g. Split-type aircon, Monobloc chair, Business permit' })
      + two(qtyField, unitField)
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

    form({ title: editing ? 'Edit ' + v.tag : (isSup ? 'Add supply' : 'Add item'), action: 'item_save', data: { id: editing ? v.id : '' }, panel: true,
      back: i ? function () { openItem(i.id); } : null, submit: editing ? 'Save changes' : (isSup ? 'Save supply' : 'Save item'),
      body: picker + basics + where + product + paper + stock + purchase + notes,
      done: function (res) { refresh(); openItem(res.id); },
      another: editing ? null : function (res) {
        ['name', 'tag', 'serial', 'warrantyUntil', 'docNumber', 'docIssuedOn', 'expiresOn', 'reference', 'description', 'notes'].forEach(function (n) { el[n].value = ''; });
        $id('invFormOk').textContent = 'Saved ' + res.tag + '. Ready for the next one.';
        $id('invModalBody').scrollTop = 0;
        el.name.focus();
        refresh();
      } });

    var box = $id('invForm'), el = box.elements;
    function fit() {
      var c = catOf(el.categoryId.value);
      each(box, '[data-kind]', function (s) { s.classList.toggle('lcy-hidden', s.getAttribute('data-kind').split(' ').indexOf(c.kind) < 0); });
      if ($id('invTagHint')) { $id('invTagHint').innerHTML = 'Gets the next <b>' + esc(c.code) + '</b> tag number when you save'; }
    }
    box.addEventListener('change', function (ev) { if (ev.target.name === 'categoryId') { fit(); } });
    each(box, '[data-step]', function (b) {
      b.addEventListener('click', function () {
        var q = $id('invQty'), n = (parseInt(q.value, 10) || 0) + +b.getAttribute('data-step');
        q.value = Math.max(+q.min, n);
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
        + '<td><span class="inv-tag">' + esc(i.tag) + '</span></td><td><b>' + esc(i.name) + '</b>' + (i.brand || i.model ? '<br><span class="lcy-muted">' + esc((i.brand + ' ' + i.model).trim()) + '</span>' : '') + '</td>'
        + '<td>' + esc(i.category) + '</td><td>' + i.qty + ' ' + esc(i.unit) + '</td><td>' + esc(i.location) + (i.spot ? '<br><span class="lcy-muted">' + esc(i.spot) + '</span>' : '') + '</td>'
        + '<td>' + esc(i.holder) + (i.holderId && !i.ackAt ? '<br><span class="lcy-muted">not confirmed</span>' : '') + '</td><td>' + statusPill(i) + '</td><td>' + esc(money(i.totalCost)) + '</td></tr>';
    }).join('')) + (rows.length >= limit ? '<p class="lcy-muted inv-more">Showing the first ' + limit + '. Narrow the search to see the rest.</p>' : '');
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
      }).join(''));
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
      }).join(''));
      each(box, '[data-emp]', function (b) { b.addEventListener('click', function () { openPerson(b.getAttribute('data-emp')); }); });
    });
  }

  function openPerson(empId) {
    L.post('person', { emp: empId }).then(function (res) {
      if (res.status !== 'ok') { alert(res.msg); return; }
      var e = res.employee;
      var body = '<p class="lcy-muted">' + esc(e.empId) + (e.position ? ' · ' + esc(e.position) : '') + (e.department ? ' · ' + esc(e.department) : '') + '</p>'
        + (res.items.length ? table(['Tag', 'Item', 'Qty', 'Issued', 'Confirmed', ''], res.items.map(function (i) {
          return '<tr><td><span class="inv-tag">' + esc(i.tag) + '</span></td><td>' + esc(i.name) + (i.serial ? '<br><span class="lcy-muted">SN ' + esc(i.serial) + '</span>' : '') + '</td><td>' + i.qty + ' ' + esc(i.unit) + '</td><td>' + esc(L.fmtDate(i.issuedOn)) + '</td>'
            + '<td>' + (i.ackAt ? esc(L.fmtDate(i.ackAt)) : pill('warn', 'No')) + '</td><td><a href="#" data-item="' + i.id + '">Open</a></td></tr>';
        }).join('')) : empty('Nothing issued.'));
      showModal(e.name, body, '<button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button><button type="button" class="wd-btn wd-btn--primary" id="invFormPrint"><i class="fa-solid fa-print"></i> Accountability form</button>', true);
      each($id('invModalBody'), '[data-item]', function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); openItem(+a.getAttribute('data-item')); }); });
      $id('invFormPrint').addEventListener('click', function () { printAccountability(res); });
    });
  }

  function printSheet(html) {
    $id('invPrint').innerHTML = html;
    document.body.classList.add('inv-printing');
    var off = function () { document.body.classList.remove('inv-printing'); $id('invPrint').innerHTML = ''; window.removeEventListener('afterprint', off); };
    window.addEventListener('afterprint', off);
    window.print();
  }

  function printAccountability(a) {
    var e = a.employee;
    printSheet('<div class="inv-form"><h2>Property Accountability Form</h2><p class="inv-form__co">' + esc(BOOT.company) + '</p>'
      + '<table class="inv-form__who"><tr><th>Employee</th><td>' + esc(e.name) + '</td><th>Employee ID</th><td>' + esc(e.empId) + '</td></tr>'
      + '<tr><th>Position</th><td>' + esc(e.position) + '</td><th>Department</th><td>' + esc(e.department) + '</td></tr>'
      + '<tr><th>Date</th><td colspan="3">' + esc(L.fmtDate(a.printedOn)) + '</td></tr></table>'
      + '<table class="inv-form__items"><thead><tr><th>Tag</th><th>Item</th><th>Serial no.</th><th>Qty</th><th>Condition</th><th>Date issued</th></tr></thead><tbody>'
      + a.items.map(function (i) {
        return '<tr><td>' + esc(i.tag) + '</td><td>' + esc(i.name) + '</td><td>' + esc(i.serial) + '</td><td>' + i.qty + ' ' + esc(i.unit) + '</td><td>' + esc(i.conditionLabel) + '</td><td>' + esc(L.fmtDate(i.issuedOn)) + '</td></tr>';
      }).join('') + '</tbody></table>'
      + '<p class="inv-form__text">I acknowledge receipt of the items listed above in the stated condition. I will take care of them, use them for company work, and return all of them upon request or when my employment ends.</p>'
      + '<div class="inv-form__sign"><div><span></span>Employee signature over printed name</div><div><span></span>Released by</div></div></div>');
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
      }).join(''));
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
    }).join('')) : empty('Nothing is recorded at this location.');
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
      }).join(''));
      each(box, 'tr.inv-row', function (tr) { tr.addEventListener('click', function () { openItem(+tr.getAttribute('data-id')); }); });
    });
  }

  /* ------------------------------------------------------------ Setup */
  function drawSetup() {
    $id('invCats').innerHTML = !look.categories.length ? empty('No categories yet.') : table(['Code', 'Name', 'Kind', 'Items', ''], look.categories.map(function (c, n) {
      return '<tr><td><span class="inv-tag">' + esc(c.code) + '</span></td><td>' + esc(c.name) + (c.active ? '' : ' ' + pill('info', 'Off')) + '</td><td>' + esc({ asset: 'Asset', document: 'Document', consumable: 'Consumable' }[c.kind]) + '</td><td>' + c.items + '</td>'
        + '<td><button type="button" class="wd-iconbtn" data-cat="' + n + '" aria-label="Edit ' + esc(c.name) + '"><i class="fa-solid fa-pen"></i></button></td></tr>';
    }).join(''));
    $id('invLocs').innerHTML = !look.locations.length ? empty('No locations yet. Add every office, floor or storeroom you keep things in.') : table(['Name', 'Items', 'Notes', ''], look.locations.map(function (l, n) {
      return '<tr><td>' + esc(l.name) + (l.active ? '' : ' ' + pill('info', 'Off')) + '</td><td>' + l.items + '</td><td>' + esc(l.notes) + '</td>'
        + '<td><button type="button" class="wd-iconbtn" data-loc="' + n + '" aria-label="Edit ' + esc(l.name) + '"><i class="fa-solid fa-pen"></i></button></td></tr>';
    }).join(''));
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
  var PANES = { items: 'Items', supplies: 'Supplies', people: 'People', repairs: 'Repairs', counts: 'Counts', alerts: 'Alerts', setup: 'Setup' };
  var LOAD = { items: loadItems, supplies: function () { return loadSupplies().then(loadUsage); }, people: loadPeople, repairs: loadRepairs, counts: loadCounts, alerts: loadAlerts, setup: function () { drawSetup(); return Promise.resolve(); } };

  function go(name) {
    tab = name;
    Object.keys(PANES).forEach(function (k) { $id('invPane' + PANES[k]).classList.toggle('lcy-hidden', k !== name); });
    each($id('invTabs'), '.lcy-tab', function (b) { b.classList.toggle('is-on', b.getAttribute('data-tab') === name); });
    LOAD[name]();
  }

  /** The stat tiles live on the Items answer. */
  function refreshStats() {
    L.post('stats', {}).then(function (res) { if (res.status === 'ok') { drawStats(res.stats); } });
  }
  function refresh() { LOAD[tab](); if (tab !== 'items') { refreshStats(); } }

  /* ------------------------------------------------------------ wire up */
  var timer = null;
  function later(fn) { clearTimeout(timer); timer = setTimeout(fn, 250); }

  each($id('invTabs'), '.lcy-tab', function (b) { b.addEventListener('click', function () { go(b.getAttribute('data-tab')); }); });
  $id('invQ').addEventListener('input', function () { later(loadItems); });
  ['invFCat', 'invFLoc', 'invFStatus'].forEach(function (id) { $id(id).addEventListener('change', loadItems); });
  $id('invSupQ').addEventListener('input', function () { later(loadSupplies); });
  $id('invSupLoc').addEventListener('change', loadSupplies);
  $id('invAdd').addEventListener('click', function () { itemForm(null); });
  $id('invSupAdd').addEventListener('click', function () { itemForm(null, false, true); });
  $id('invExport').addEventListener('click', exportItems);
  $id('invBulkLabels').addEventListener('click', function () { printLabels(selectedRows()); });
  $id('invBulkMove').addEventListener('click', bulkMove);
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
