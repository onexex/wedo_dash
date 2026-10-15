/* ==========================================================================
   apply.js  —  the public application wizard (apply.php).
   Six steps shown one at a time with a progress bar; each step is checked
   before moving on (errors appear under the field); job cards pick the
   position; family rows can be added/removed; the resume can be dropped on
   the drop zone; the last step shows a review of everything typed. Posts the
   whole form (multipart) to query/apply-action.php, which re-checks it all.
   Applicant text is only ever written with textContent (never as HTML).
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('aplForm');
  if (!form) { return; }
  var $ = function (id) { return document.getElementById(id); };
  var steps = Array.prototype.slice.call(form.querySelectorAll('.apl-step'));
  var dots = Array.prototype.slice.call(document.querySelectorAll('#aplStepper li'));
  var names = dots.map(function (li) { return li.querySelector('.apl-lbl').textContent; });
  var cur = 0, reached = 0, dirty = false, sent = false, tsId = null;
  var MAX_FILE = 5 * 1024 * 1024, TYPES = ['pdf', 'doc', 'docx'];

  /* ------------------------------------------------------------ helpers */
  function val(name) { var el = form.elements[name]; return el ? String(el.value || '').trim() : ''; }
  function scrollToApp() { $('aplApp').scrollIntoView({ behavior: 'smooth', block: 'start' }); }

  function fieldBox(el) { return el.closest('.apl-f') || el.closest('.apl-person') || el.closest('.apl-drop') || el.closest('.apl-consent') || el.parentNode; }
  function setErr(el, msg) {
    var box = fieldBox(el), e = box.querySelector(':scope > .apl-ferr');
    el.classList.add('is-invalid');
    el.setAttribute('aria-invalid', 'true');
    if (!e) { e = document.createElement('small'); e.className = 'apl-ferr'; box.appendChild(e); }
    e.textContent = msg;
  }
  function clearErr(el) {
    var box = fieldBox(el), e = box.querySelector(':scope > .apl-ferr');
    el.classList.remove('is-invalid');
    el.removeAttribute('aria-invalid');
    if (e) { e.remove(); }
  }
  form.addEventListener('input', function (ev) { dirty = true; clearErr(ev.target); $('aplErr').textContent = ''; });
  form.addEventListener('change', function (ev) { dirty = true; clearErr(ev.target); });

  /* ------------------------------------------------------------ checks per step */
  var CHECK = {
    0: function () { return []; },
    1: function () {
      var bad = required(1), b = form.elements.birthDate;
      ['firstName', 'middleName', 'lastName'].forEach(function (k) {
        var el = form.elements[k];
        if (el.value.trim() && !/^\p{L}[\p{L}\p{M} .'\-]*$/u.test(el.value.trim())) { bad.push([el, 'Letters only.']); }
      });
      var sx = form.elements.suffix;
      if (sx.value.trim() && !/^(jr|sr|ii|iii|iv|v|vi)\.?$/i.test(sx.value.trim())) { bad.push([sx, 'e.g. Jr, Sr, III']); }
      if (b.value) {
        var d = new Date(b.value + 'T00:00:00'), age = (Date.now() - d) / 31557600000;
        if (isNaN(d) || age < 15 || age > 80) { bad.push([b, 'Check your date of birth.']); }
      }
      return bad;
    },
    2: function () {
      var bad = required(2), em = form.elements.email, mo = form.elements.mobile, hp = form.elements.homePhone;
      if (em.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value.trim())) { bad.push([em, 'Enter a valid email address.']); }
      if (mo.value && !/^(?:\+?63|0)9\d{9}$/.test(mo.value.replace(/[\s\-()]/g, ''))) { bad.push([mo, 'Enter a valid mobile number (e.g. 0917 123 4567).']); }
      if (hp.value && !/^[0-9+()\-\s]{7,30}$/.test(hp.value.trim())) { bad.push([hp, 'Check this phone number.']); }
      return bad;
    },
    3: function () {
      var bad = [], any = false, max = new Date().getFullYear() + 5;
      ['Primary', 'Secondary', 'Tertiary'].forEach(function (p) {
        var s = form.elements['edu[' + p + '][school]'], f = form.elements['edu[' + p + '][from]'], t = form.elements['edu[' + p + '][to]'];
        if (s.value.trim()) { any = true; }
        [f, t].forEach(function (y) {
          if (y.value.trim() && !(/^\d{4}$/.test(y.value.trim()) && +y.value >= 1940 && +y.value <= max)) { bad.push([y, 'Year as YYYY.']); }
        });
        if (/^\d{4}$/.test(f.value) && /^\d{4}$/.test(t.value) && +t.value < +f.value) { bad.push([t, 'Before the start year.']); }
      });
      if (!any) { bad.push([form.elements['edu[Tertiary][school]'], 'Enter at least one school you attended.']); }
      ['sss', 'tin', 'philhealth', 'pagibig', 'umid', 'passportNo'].forEach(function (k) {
        var el = form.elements[k];
        if (el.value.trim() && !/^[0-9A-Za-z][0-9A-Za-z\- ]{3,29}$/.test(el.value.trim())) {
          el.closest('details').open = true;
          bad.push([el, 'Letters, numbers and dashes only.']);
        }
      });
      return bad;
    },
    4: function () {
      var bad = [], ice = false;
      $('aplFamily').querySelectorAll('.apl-person').forEach(function (row) {
        var g = function (k) { return row.querySelector('[name$="[' + k + ']"]'); };
        var n = g('name'), r = g('relationship'), c = g('contact'), em = g('emergency');
        if (!n.value.trim()) {
          if (r.value || c.value.trim()) { bad.push([n, 'Enter this person’s name.']); }
          return;
        }
        if (!r.value) { bad.push([r, 'Choose the relationship.']); }
        if (c.value.trim() && !/^[0-9+()\-\s]{7,30}$/.test(c.value.trim())) { bad.push([c, 'Check this number.']); }
        if (em.checked) {
          if (!c.value.trim()) { bad.push([c, 'An emergency contact needs a number.']); } else { ice = true; }
        }
      });
      if (!bad.length && !ice) {
        var first = $('aplFamily').querySelector('[name$="[name]"]');
        bad.push([first, 'Add at least one emergency contact with a contact number.']);
      }
      return bad;
    },
    5: function () {
      var bad = [], f = form.elements.resume.files[0], c = form.elements.consent;
      if (!f) { bad.push([form.elements.resume, 'Attach your resume (PDF or Word).']); }
      if (!c.checked) { bad.push([c, 'Please agree so we can process your application.']); }
      return bad;
    },
  };
  function required(step) {
    var bad = [];
    steps[step].querySelectorAll('[required]').forEach(function (el) {
      if (!String(el.value || '').trim()) { bad.push([el, 'Required.']); }
    });
    return bad;
  }
  function anyMarkup(step) {
    var bad = [];
    steps[step].querySelectorAll('input[type=text], input[type=email], input[type=tel], input:not([type]), textarea').forEach(function (el) {
      if (/[<>]/.test(el.value)) { bad.push([el, 'Please remove the < and > characters.']); }
      else if (el.type !== 'email' && /(https?:\/\/|www\.|\b[a-z0-9-]+\.(com|net|org|xyz|ru|top|info|biz|io|co|site|online|click|link)\b)/i.test(el.value)) {
        bad.push([el, 'Please remove web links.']);
      }
    });
    return bad;
  }
  function check(step) {
    var bad = CHECK[step]().concat(anyMarkup(step));
    bad.forEach(function (b) { setErr(b[0], b[1]); });
    if (bad.length) {
      $('aplErr').textContent = bad.length === 1 ? 'Please fix the highlighted field.' : 'Please fix the ' + bad.length + ' highlighted fields.';
      var el = bad[0][0];
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (el.type !== 'file' && el.type !== 'checkbox') { setTimeout(function () { el.focus({ preventScroll: true }); }, 250); }
    }
    return !bad.length;
  }

  /* ------------------------------------------------------------ navigation */
  function go(n, quiet) {
    cur = n;
    reached = Math.max(reached, n);
    steps.forEach(function (s, i) { s.hidden = i !== n; s.classList.toggle('is-on', i === n); });
    dots.forEach(function (d, i) {
      d.classList.toggle('is-on', i === n);
      d.classList.toggle('is-done', i < n || (i <= reached && i !== n));
      d.setAttribute('aria-current', i === n ? 'step' : 'false');
    });
    $('aplBar').style.width = Math.round((n / (steps.length - 1)) * 100) + '%';
    $('aplStepNow').textContent = 'Step ' + (n + 1) + ' of ' + steps.length + ' · ' + names[n];
    $('aplBack').hidden = n === 0;
    $('aplNext').hidden = n === steps.length - 1;
    $('aplSend').hidden = n !== steps.length - 1;
    $('aplErr').textContent = '';
    if (n === steps.length - 1) { review(); renderTurnstile(); }
    if (!quiet) { scrollToApp(); }
  }
  $('aplNext').addEventListener('click', function () { if (check(cur)) { go(cur + 1); } });
  $('aplBack').addEventListener('click', function () { go(cur - 1); });
  dots.forEach(function (d, i) {
    d.addEventListener('click', function () {
      if (i === cur) { return; }
      if (i < cur) { go(i); return; }
      // forward: every step on the way must pass its checks (stop at the first that doesn't)
      for (var s = cur; s < i; s++) {
        if (!check(s)) { if (s !== cur) { go(s); check(s); } return; }
      }
      go(i);
    });
  });

  /* ------------------------------------------------------------ jobs */
  function markJob(id) {
    document.querySelectorAll('[data-job-card]').forEach(function (c) { c.classList.toggle('is-picked', c.getAttribute('data-job-card') === String(id)); });
  }
  document.querySelectorAll('[data-job]').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-job'), r = form.querySelector('input[name=opening][value="' + id + '"]');
      if (r) { r.checked = true; }
      markJob(id);
      go(0);
    });
  });
  form.querySelectorAll('input[name=opening]').forEach(function (r) { r.addEventListener('change', function () { markJob(r.value); }); });
  var picked = form.querySelector('input[name=opening]:checked');
  if (picked) { markJob(picked.value); }
  document.querySelectorAll('.apl-job__more').forEach(function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.apl-job'), open = card.classList.toggle('is-open');
      b.textContent = open ? 'Show less' : 'Read more';
      b.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  /* ------------------------------------------------------------ family rows */
  var fam = $('aplFamily'), addFam = $('aplAddFam'), tpl = $('aplFamTpl'), nextRow = 1;
  function syncFam() {
    var rows = fam.querySelectorAll('.apl-person');
    rows.forEach(function (r, i) {
      r.querySelector('.apl-person__n').textContent = i + 1;
      r.querySelector('.apl-person__del').hidden = rows.length === 1;
    });
    addFam.hidden = rows.length >= +addFam.getAttribute('data-max');
  }
  addFam.addEventListener('click', function () {
    var div = document.createElement('div');
    div.innerHTML = tpl.innerHTML.replace(/__i__/g, String(nextRow++));
    var row = div.firstElementChild;
    row.querySelector('input[type=checkbox]').checked = false;
    fam.appendChild(row);
    syncFam();
    row.querySelector('input').focus();
  });
  fam.addEventListener('click', function (ev) {
    var del = ev.target.closest('.apl-person__del');
    if (del) { del.closest('.apl-person').remove(); syncFam(); }
  });
  syncFam();

  /* ------------------------------------------------------------ resume drop zone */
  var drop = $('aplDrop'), file = $('aplResume');
  function showFile() {
    var f = file.files[0];
    clearErr(file);
    if (f) {
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (TYPES.indexOf(ext) < 0) { file.value = ''; setErr(file, 'Your resume must be a PDF or Word file.'); }
      else if (f.size > MAX_FILE) { file.value = ''; setErr(file, 'That file is over 5 MB.'); }
    }
    f = file.files[0];
    drop.classList.toggle('has-file', !!f);
    $('aplFileName').textContent = f ? f.name : '';
    $('aplFileSize').textContent = f ? (f.size < 1048576 ? Math.ceil(f.size / 1024) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB') : '';
  }
  file.addEventListener('change', showFile);
  ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (ev) { ev.preventDefault(); drop.classList.add('is-over'); }); });
  ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('is-over'); }); });
  drop.addEventListener('drop', function (ev) {
    ev.preventDefault();
    if (ev.dataTransfer && ev.dataTransfer.files.length) {
      try { var dt = new DataTransfer(); dt.items.add(ev.dataTransfer.files[0]); file.files = dt.files; } catch (e) { file.files = ev.dataTransfer.files; }
      showFile();
    }
  });
  $('aplFileDel').addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); file.value = ''; showFile(); });

  /* ------------------------------------------------------------ review */
  function review() {
    var box = $('aplReview');
    box.textContent = '';
    var opening = form.querySelector('input[name=opening]:checked');
    var job = opening ? opening.closest('.apl-choice').querySelector('b').textContent : 'Any open position';
    var name = [val('firstName'), val('middleName'), val('lastName'), val('suffix')].filter(Boolean).join(' ');
    var bday = val('birthDate') ? new Date(val('birthDate') + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : '';
    var schools = ['Tertiary', 'Secondary', 'Primary'].map(function (p) { return val('edu[' + p + '][school]'); }).filter(Boolean);
    var people = [];
    fam.querySelectorAll('.apl-person').forEach(function (r) {
      var n = r.querySelector('[name$="[name]"]').value.trim();
      if (!n) { return; }
      var rel = r.querySelector('[name$="[relationship]"]').value, ice = r.querySelector('[name$="[emergency]"]').checked;
      people.push(n + (rel ? ' (' + rel + ')' : '') + (ice ? ' — emergency contact' : ''));
    });
    var ids = ['sss', 'tin', 'philhealth', 'pagibig', 'umid', 'passportNo'].filter(function (k) { return val(k); }).length;
    [
      [0, 'Position', [job, val('source') ? 'Heard about us: ' + val('source') : '']],
      [1, 'Personal', [name, [bday, val('gender'), val('civilStatus')].filter(Boolean).join(' · ')]],
      [2, 'Contact', [val('email'), val('mobile'), [val('street'), val('barangay'), val('city'), val('province'), val('zip')].filter(Boolean).join(', ')]],
      [3, 'Background', [schools.join(' · ') || '—', ids ? ids + ' government ID' + (ids > 1 ? 's' : '') + ' added' : 'No government IDs added',
                         val('prevCompany') ? 'Last job: ' + [val('prevPosition'), val('prevCompany')].filter(Boolean).join(', ') : '']],
      [4, 'Family', people.length ? people : ['—']],
    ].forEach(function (sec) {
      var card = document.createElement('div'), head = document.createElement('div'), h = document.createElement('b'), edit = document.createElement('button');
      card.className = 'apl-review__card';
      head.className = 'apl-review__head';
      h.textContent = sec[1];
      edit.type = 'button';
      edit.className = 'apl-review__edit';
      edit.textContent = 'Edit';
      edit.addEventListener('click', function () { go(sec[0]); });
      head.appendChild(h);
      head.appendChild(edit);
      card.appendChild(head);
      sec[2].filter(Boolean).forEach(function (line) { var p = document.createElement('p'); p.textContent = line; card.appendChild(p); });
      box.appendChild(card);
    });
  }

  /* ------------------------------------------------------------ Turnstile (only when configured) */
  function renderTurnstile(tries) {
    var el = $('aplTurnstile');
    if (!el || tsId !== null) { return; }
    if (!window.turnstile) {
      if ((tries || 0) < 40) { setTimeout(function () { renderTurnstile((tries || 0) + 1); }, 250); }
      return;
    }
    tsId = window.turnstile.render(el, { sitekey: el.getAttribute('data-sitekey'), theme: 'light' });
  }

  /* ------------------------------------------------------------ submit */
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    for (var s = 0; s < steps.length; s++) { if (!check(s)) { if (s !== cur) { go(s); check(s); } return; } }
    var send = $('aplSend');
    send.disabled = true;
    send.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';
    fetch('query/apply-action.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { status: 'error', msg: 'Could not reach the server — check your connection and try again.' }; })
      .then(function (res) {
        send.disabled = false;
        send.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit application';
        if (res.status !== 'ok') {
          $('aplErr').textContent = res.msg;
          if (window.turnstile && tsId !== null) { try { window.turnstile.reset(tsId); } catch (e) { /* ignore */ } }
          return;
        }
        sent = true;
        form.hidden = true;
        document.querySelector('.apl-panel__head').hidden = true;
        $('aplDoneMsg').textContent = res.msg;
        if (res.ref) { $('aplRefNo').textContent = res.ref; $('aplRef').hidden = false; }
        $('aplDone').hidden = false;
        scrollToApp();
      });
  });

  window.addEventListener('beforeunload', function (ev) {
    if (dirty && !sent) { ev.preventDefault(); ev.returnValue = ''; }
  });

  go(0, true);
  if (new URLSearchParams(location.search).get('job')) { scrollToApp(); }   // a shared "apply for this job" link
})();
