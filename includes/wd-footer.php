      </div><!-- /.wd-content-inner -->
    </main>
  </div>
</div>

<!-- Change password modal (Bootstrap; handled by assets/js/script.js .btnchangepass) -->
<div class="modal" id="changepass">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="color:#fff;background-color:#f93627">
        <h4 class="modal-titles">Account settings</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <form class="frmchangepass">
          <label class="wrningpass" style="display:none;font-size:18px;color:#f93627"></label>
          <label class="validpass" style="display:none;color:red;">
            Your new password should:<br>
            - Be 8-15 characters long<br>
            - Contain at least one uppercase letter<br>
            - Contain at least one lowercase letter<br>
            - Contain at least one numeric digit<br>
            - Contain at least one special character<br>
          </label>
          <div class="form-group">
            <label>Current password:</label>
            <input type="password" class="form-control" name="cupass" id="cupass">
          </div>
          <div class="form-group">
            <label>New password:</label>
            <input type="password" class="form-control" name="npass" id="npass">
            <label class="vw-pass" style="text-decoration:underline;cursor:pointer;font-style:italic;font-size:12px;color:#15cccc;">View password</label>
          </div>
          <div class="form-group">
            <label>Confirm new password:</label>
            <input type="password" class="form-control" name="cnpass" id="cnpass">
          </div>
          <button type="button" class="btn btn-info btn-block btnchangepass">Change password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="passchanged">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="color:#fff;background-color:#f93627">
        <h4 class="modal-titles">Password changed</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
    </div>
  </div>
</div>

<script>
  // close the avatar dropdown when clicking outside it
  document.addEventListener('click', function (e) {
    document.querySelectorAll('.wd-user.is-open').forEach(function (u) {
      if (!u.contains(e.target)) u.classList.remove('is-open');
    });
  });
  // give every Bootstrap modal the themed fade/scale transition. Deferred so
  // modals declared after this footer include (e.g. #newform) are covered too.
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (m) { m.classList.add('fade'); });
  });
</script>
