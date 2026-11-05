<?php
/* ==========================================================================
   myitems.php  —  Modules > My Items. What is issued to me: every employee
   sees it, confirms receipt and reports problems. No access right needed; the
   list is always the signed-in person's own. Rules: includes/inventory-lib.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/inventory-lib.php';

try {
    $invpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $invpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }

$boot = ['endpoint' => 'query/inventory-action.php', 'token' => inv_csrf_token()];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "My Items" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <link rel="stylesheet" href="assets/css/inventory.css?v=<?php echo $v('assets/css/inventory.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
  </head>
  <body>
    <?php
      $wd_active = 'myitems';
      include 'includes/wd-header.php';
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>My Items</h1>
          <p>Company property and documents issued to you. Confirm what you received, and tell us if something is wrong with it.</p>
        </div>
      </div>

<?php if (!inv_ready($invpdo)): ?>
      <section class="wd-card"><div class="lcy-empty">This page is not set up on this server yet.</div></section>
<?php else: ?>
      <div id="invMine"><div class="lcy-empty">Loading&hellip;</div></div>

      <div class="modal fade" id="invReport" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title" id="invReportTitle">Report a problem</h4>
            </div>
            <form id="invReportForm" class="modal-body" autocomplete="off">
              <div class="wd-field">
                <label for="invProblem">What is wrong?</label>
                <textarea class="wd-textarea" id="invProblem" name="problem" rows="3" maxlength="500" required></textarea>
              </div>
              <p class="lcy-modal-error" id="invReportErr"></p>
            </form>
            <div class="modal-footer">
              <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
              <button type="button" class="wd-btn wd-btn--primary" id="invReportSend"><i class="fa-solid fa-paper-plane"></i> Send</button>
            </div>
          </div>
        </div>
      </div>

      <script>window.LCY_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
      <script src="assets/js/lifecycle-common.js?v=<?php echo $v('assets/js/lifecycle-common.js'); ?>"></script>
      <script src="assets/js/myitems.js?v=<?php echo $v('assets/js/myitems.js'); ?>"></script>
<?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>
  </body>
</html>
