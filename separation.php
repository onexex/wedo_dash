<?php
/* ==========================================================================
   separation.php  —  Management > Separation & Lifecycle.
   Pick anyone (any status) to see their history — hired, classification
   changes (Trainee -> Independent Contractor), status changes, separation,
   COEs issued — record a separation (Resigned / Terminated / AWOL) and keep
   the rehire flag. Rules: includes/lifecycle-lib.php. Writes and reads:
   query/lifecycle-action.php. Gated by the `separation` access right.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/lifecycle-lib.php';

/* access gate BEFORE any output so the redirect can fire */
try {
    $lcypdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $lcypdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!lcy_can_separate($lcypdo)) { header('location: 404?'); exit(); }

$boot = [
    'token' => lcy_csrf_token(),
    'today' => date('Y-m-d'),
    'emp'   => (string) ($_GET['emp'] ?? ''),
];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Separation & Lifecycle" : $e($_SESSION['CompanyName']); ?></title>
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
      $wd_active = 'separation';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>Separation &amp; Lifecycle</h1>
          <p>Follow a person from hiring to separation: classification changes, resignation, termination or AWOL, rehire eligibility and COEs issued.</p>
        </div>
      </div>

<?php if (!lcy_ready($lcypdo)): ?>
      <section class="wd-card"><div class="lcy-empty">This page is not set up on this server yet — run <code>sql/2026-10-14-add-employee-lifecycle.sql</code>.</div></section>
<?php else: ?>
      <section class="wd-card lcy-visible">
        <div class="wd-card__head"><h3>Find employee</h3></div>
        <div class="lcy-card-body">
          <div class="wd-field lcy-search" style="margin:0">
            <label for="lcySearch">Name or employee ID — current and former employees</label>
            <input type="search" class="wd-input" id="lcySearch" autocomplete="off" placeholder="Start typing a name&hellip;">
            <div class="lcy-results" id="lcyResults" role="listbox"></div>
          </div>
        </div>
      </section>

      <section class="wd-card lcy-hidden" id="lcyPerson">
        <div class="lcy-person">
          <div>
            <h2 id="lcyName"></h2>
            <div class="lcy-meta" id="lcyMeta"></div>
          </div>
          <div class="lcy-actions">
            <button type="button" class="wd-btn wd-btn--danger" id="lcySeparateBtn"><i class="fa-solid fa-user-minus"></i> Record separation</button>
          </div>
        </div>
        <div class="lcy-grid2">
          <div>
            <div class="wd-card__head"><h3>History</h3></div>
            <ul class="lcy-timeline" id="lcyTimeline"></ul>
          </div>
          <div>
            <div class="wd-card__head"><h3>Separation records</h3></div>
            <div id="lcySeps"></div>
          </div>
        </div>
      </section>

      <section class="wd-card">
        <div class="wd-card__head">
          <h3>Separation records to finish</h3>
          <span class="lcy-muted">Rehire still "to review" or no reason yet — e.g. a status set in Update Employee Information</span>
        </div>
        <div id="lcyOpen"><div class="lcy-empty">Loading&hellip;</div></div>
      </section>

      <!-- record / edit a separation -->
      <div class="modal fade" id="lcySepModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title" id="lcySepTitle">Record separation</h4>
            </div>
            <form id="lcySepForm" class="modal-body" autocomplete="off">
              <input type="hidden" name="id">
              <div class="wd-field" id="lcyTypeField">
                <label>Type</label>
                <div class="lcy-radios">
                  <label><input type="radio" name="type" value="resigned" checked> Resigned</label>
                  <label><input type="radio" name="type" value="terminated"> Terminated</label>
                  <label><input type="radio" name="type" value="awol"> AWOL</label>
                </div>
              </div>
              <div class="wd-field">
                <label for="lcyLastDay">Last day worked</label>
                <input type="date" class="wd-input" id="lcyLastDay" name="lastDay" required>
              </div>
              <div class="wd-field">
                <label for="lcyReason">Reason for leaving</label>
                <textarea class="wd-textarea" id="lcyReason" name="reason" rows="3" maxlength="500" required placeholder="e.g. Better opportunity; no call, no show since Oct 1"></textarea>
              </div>
              <div class="wd-field">
                <label>Eligible for rehire?</label>
                <div class="lcy-radios">
                  <label><input type="radio" name="rehire" value="yes"> Yes</label>
                  <label><input type="radio" name="rehire" value="no"> No</label>
                  <label><input type="radio" name="rehire" value="review" checked> To review</label>
                </div>
              </div>
              <div class="wd-field">
                <label for="lcyRehireNote">Rehire note <span class="lcy-muted">(why — shown on Employment Verification)</span></label>
                <input type="text" class="wd-input" id="lcyRehireNote" name="rehireNote" maxlength="500">
              </div>
              <div class="wd-field">
                <label class="lcy-check"><input type="checkbox" name="clearance" value="1"> Clearance completed</label>
              </div>
              <p class="lcy-note" id="lcySepWarn">The account is signed out on every device right away and can no longer sign in.</p>
              <p class="lcy-modal-error" id="lcySepErr"></p>
            </form>
            <div class="modal-footer">
              <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
              <button type="button" class="wd-btn wd-btn--primary" id="lcySepSave"><i class="fa-solid fa-check"></i> Save</button>
            </div>
          </div>
        </div>
      </div>

      <script>window.LCY_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
      <script src="assets/js/lifecycle-common.js?v=<?php echo $v('assets/js/lifecycle-common.js'); ?>"></script>
      <script src="assets/js/separation.js?v=<?php echo $v('assets/js/separation.js'); ?>"></script>
<?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>
  </body>
</html>
