/* ==========================================================================
   apply.js  —  the public applicant registration form (apply.php).
   "Apply for this role" picks the position; "Add another person" adds a
   family row (up to the page's limit); the form posts (multipart, with the
   resume) to query/apply-action.php. The server re-checks everything.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('aplForm');
  if (!form) { return; }
  var err = document.getElementById('aplErr'), send = document.getElementById('aplSend');
  var famBox = document.getElementById('aplFamily'), addFam = document.getElementById('aplAddFam');
  var tpl = document.getElementById('aplFamTpl');
  var nextRow = famBox.querySelectorAll('.apl-fam').length;

  Array.prototype.forEach.call(document.querySelectorAll('[data-job]'), function (b) {
    b.addEventListener('click', function () {
      document.getElementById('aplOpening').value = b.getAttribute('data-job');
      document.getElementById('aplFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
      document.getElementById('apl_firstName').focus({ preventScroll: true });
    });
  });

  function syncAdd() { addFam.disabled = famBox.querySelectorAll('.apl-fam').length >= +addFam.getAttribute('data-max'); }
  addFam.addEventListener('click', function () {
    var div = document.createElement('div');
    div.innerHTML = tpl.innerHTML.replace(/__i__/g, String(nextRow++));
    var row = div.firstElementChild;
    var cb = row.querySelector('input[type=checkbox]');
    if (cb) { cb.checked = false; }
    famBox.appendChild(row);
    syncAdd();
  });
  syncAdd();

  function check() {
    Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (el) { el.classList.remove('is-invalid'); });
    var bad = Array.prototype.filter.call(form.querySelectorAll('[required]'), function (el) {
      return el.type === 'checkbox' ? !el.checked : !String(el.value || '').trim();
    });
    bad.forEach(function (el) { el.classList.add('is-invalid'); });
    if (bad.length) {
      if (bad[0].name === 'consent') { return 'Please agree to the Data Privacy notice.'; }
      if (bad[0].name === 'resume') { return 'Attach your resume (PDF or Word).'; }
      bad[0].focus();
      return 'Please fill in the fields marked *.';
    }
    var school = Array.prototype.some.call(form.querySelectorAll('input[name$="[school]"]'), function (el) { return el.value.trim(); });
    if (!school) { return 'Enter at least one school you attended.'; }
    var ice = Array.prototype.some.call(famBox.querySelectorAll('.apl-fam'), function (row) {
      var g = function (k) { var el = row.querySelector('[name$="[' + k + ']"]'); return el ? el : {}; };
      return g('emergency').checked && String(g('name').value || '').trim() && String(g('contact').value || '').trim();
    });
    if (!ice) { return 'Add at least one emergency contact with a name and a contact number.'; }
    var file = form.elements.resume.files[0];
    if (file && file.size > 5 * 1024 * 1024) { return 'Your resume is too large (5 MB at most).'; }
    return '';
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    err.textContent = check();
    if (err.textContent) { return; }

    send.disabled = true;
    send.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';
    fetch('query/apply-action.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { status: 'error', msg: 'Could not reach the server — check your connection and try again.' }; })
      .then(function (res) {
        send.disabled = false;
        send.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit registration';
        if (res.status !== 'ok') {
          err.textContent = res.msg;
          if (window.turnstile) { try { window.turnstile.reset(); } catch (e) { /* widget not shown */ } }
          return;
        }
        form.classList.add('lcy-hidden');
        document.getElementById('aplDoneMsg').textContent = res.msg;
        document.getElementById('aplDone').classList.remove('lcy-hidden');
        document.getElementById('aplFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
  });
})();
