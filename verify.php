<?php
/* ==========================================================================
   verify.php  —  Management > Employment Verification (CI).
   When another company calls to check on a current or former employee
   (credit / character investigation, background check, loan), HR looks the
   person up — WeDo records of any status plus the legacy AMS archive — sees
   dates, position, separation and the rehire flag, and logs the call.
   Rules: includes/lifecycle-lib.php. Reads and writes:
   query/lifecycle-action.php. Gated by the `empverify` access right.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/lifecycle-lib.php';

try {
    $lcypdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $lcypdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!lcy_can_verify($lcypdo)) { header('location: 404?'); exit(); }

$boot = ['token' => lcy_csrf_token(), 'purposes' => LCY_PURPOSE, 'disclose' => LCY_DISCLOSE];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Employment Verification" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
  </head>
  <body>
    <?php
      $wd_active = 'verify';
      include 'includes/wd-header.php';
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>Employment Verification (CI)</h1>
          <p>A bank, agency or company is checking on someone who works or worked here: look them up and log the call.</p>
        </div>
      </div>

<?php if (!lcy_ready($lcypdo)): ?>
      <section class="wd-card"><div class="lcy-empty">This page is not set up on this server yet — run <code>sql/2026-10-14-add-employee-lifecycle.sql</code>.</div></section>
<?php else: ?>
      <section class="wd-card">
        <div class="wd-card__head"><h3>Look up a person</h3></div>
        <div class="lcy-card-body">
          <p class="lcy-note">Data Privacy Act: confirm only what the caller needs (dates, position, status). Disclose salary only when the person's written authorization has been shown.</p>
          <form class="lcy-row" id="verForm" autocomplete="off">
            <div class="wd-field">
              <label for="verQ">Name of the person being checked</label>
              <input type="search" class="wd-input" id="verQ" placeholder="e.g. Juan Dela Cruz" minlength="2" required>
            </div>
            <button type="submit" class="wd-btn wd-btn--primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
          </form>
        </div>
      </section>

      <section class="wd-card">
        <div class="wd-card__head"><h3>Check a COE control number</h3><span class="lcy-muted">The caller has a certificate from us — is it genuine?</span></div>
        <div class="lcy-card-body">
          <form class="lcy-row" id="verCoeForm" autocomplete="off">
            <div class="wd-field" style="max-width:280px">
              <label for="verCoeNo">Control No.</label>
              <input type="text" class="wd-input" id="verCoeNo" placeholder="COE-2026-0001" maxlength="20" required>
            </div>
            <button type="submit" class="wd-btn wd-btn--ghost"><i class="fa-solid fa-certificate"></i> Check</button>
          </form>
          <div id="verCoeResult"></div>
        </div>
      </section>

      <section class="wd-card lcy-hidden" id="verResults">
        <div class="wd-card__head"><h3>WeDo records</h3><span class="lcy-muted" id="verEmpCount"></span></div>
        <div id="verEmployees"></div>
        <div class="wd-card__head" style="border-top:1px solid var(--border)"><h3>Archived records (AMS)</h3><span class="lcy-muted" id="verAmsCount"></span></div>
        <div id="verArchive"></div>
      </section>

      <section class="wd-card">
        <div class="wd-card__head"><h3>Recent verification calls</h3></div>
        <div id="verRecent"><div class="lcy-empty">Loading&hellip;</div></div>
      </section>

      <!-- log a call -->
      <div class="modal fade" id="verModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title" id="verModalTitle">Log verification call</h4>
            </div>
            <form id="verLogForm" class="modal-body" autocomplete="off">
              <input type="hidden" name="emp"><input type="hidden" name="ams">
              <div class="lcy-row">
                <div class="wd-field"><label for="verCaller">Caller's name</label><input type="text" class="wd-input" id="verCaller" name="caller" maxlength="120" required></div>
                <div class="wd-field"><label for="verCompany">Company / bank</label><input type="text" class="wd-input" id="verCompany" name="company" maxlength="150" required></div>
              </div>
              <div class="lcy-row" style="margin-top:14px">
                <div class="wd-field"><label for="verContact">Contact number or email</label><input type="text" class="wd-input" id="verContact" name="contact" maxlength="120"></div>
                <div class="wd-field"><label for="verPurpose">Purpose</label><select class="wd-select" id="verPurpose" name="purpose"></select></div>
              </div>
              <div class="wd-field" style="margin-top:14px">
                <label>What was confirmed to the caller</label>
                <div class="lcy-checks" id="verDisclose"></div>
              </div>
              <div class="wd-field">
                <label class="lcy-check"><input type="checkbox" name="consent" value="1"> The person's authorization / consent was shown or confirmed</label>
              </div>
              <div class="wd-field">
                <label for="verNotes">Notes</label>
                <textarea class="wd-textarea" id="verNotes" name="notes" rows="2" maxlength="500"></textarea>
              </div>
              <p class="lcy-modal-error" id="verErr"></p>
            </form>
            <div class="modal-footer">
              <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
              <button type="button" class="wd-btn wd-btn--primary" id="verSave"><i class="fa-solid fa-check"></i> Log call</button>
            </div>
          </div>
        </div>
      </div>

      <script>window.LCY_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
      <script src="assets/js/lifecycle-common.js?v=<?php echo $v('assets/js/lifecycle-common.js'); ?>"></script>
      <script src="assets/js/verify.js?v=<?php echo $v('assets/js/verify.js'); ?>"></script>
<?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>
  </body>
</html>
