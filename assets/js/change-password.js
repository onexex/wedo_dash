/* change-password.js  —  change-password.php: posts to query/force-password.php, then opens WeDo. */
(function () {
  'use strict';
  var form = document.getElementById('pwForm'), err = document.getElementById('pwErr'), btn = document.getElementById('pwSave');
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (form.elements['new'].value !== form.elements.confirm.value) { err.textContent = 'The two new passwords are not the same.'; return; }
    btn.disabled = true;
    err.textContent = '';
    fetch('query/force-password.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { status: 'error', msg: 'Could not reach the server — try again.' }; })
      .then(function (res) {
        btn.disabled = false;
        if (res.status === 'ok') { window.location.replace('index.php'); return; }
        if (res.signedOut) { window.location.replace('login.php'); return; }
        err.textContent = res.msg;
      });
  });
})();
