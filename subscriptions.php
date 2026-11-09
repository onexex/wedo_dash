<?php
/* ==========================================================================
   subscriptions.php  —  Management > Subscriptions. The company's domains,
   hosting, hosting security services and PhilGEPS registration. Opens on
   answers (what needs action, what it costs, can we bid), then the register.
   Rules: includes/subscriptions-lib.php. Reads and writes:
   query/subscriptions-action.php. Gated by the `subscriptions` access right.
   ?id=12 opens one item (the reminder push and pop-up link here).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
require_once __DIR__ . '/includes/subscriptions-lib.php';

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    header('location: login.php');
    exit();
}

include 'w_conn.php';
try {
    $subpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $subpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!sub_can_manage($subpdo)) {
    header('location: 404?');
    exit();
}

$scope = sub_scope($subpdo);
$iniBytes = function ($v) {
    $n = (float) $v;
    switch (strtoupper(substr(trim((string) $v), -1))) {
        case 'G': return $n * 1073741824;
        case 'M': return $n * 1048576;
        case 'K': return $n * 1024;
    }
    return $n;
};
$boot = [
    'endpoint'  => 'query/subscriptions-action.php',
    'fileUrl'   => 'query/subscriptions-file.php',
    'token'     => sub_csrf_token(),
    'today'     => date('Y-m-d'),
    'me'        => (string) $_SESSION['id'],
    'open'      => (int) ($_GET['id'] ?? 0),
    'maxUpload' => (int) min($iniBytes(ini_get('upload_max_filesize')), $iniBytes(ini_get('post_max_size'))),
];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Subscriptions" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <link rel="stylesheet" href="assets/css/subscriptions.css?v=<?php echo $v('assets/css/subscriptions.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
  </head>
  <body>
    <?php
      $wd_active = 'subscriptions';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead sub-head">
        <div>
          <h1>Subscriptions</h1>
          <p>Domains, hosting, security services and PhilGEPS: what needs renewing, what it costs, and who's on it.</p>
        </div>
<?php if (sub_ready($subpdo)): ?>
        <div class="sub-headtools">
          <select class="wd-select sub-company lcy-hidden" id="subCompany" aria-label="Company"></select>
          <button type="button" class="wd-btn wd-btn--primary" id="subAdd"><i class="fa-solid fa-plus"></i> Add subscription</button>
        </div>
<?php endif; ?>
      </div>

<?php if (!sub_ready($subpdo)): ?>
      <section class="wd-card"><div class="lcy-empty">This page is not set up on this server yet — run <code>sql/2026-11-09-add-subscriptions.sql</code>.</div></section>
<?php else: ?>
      <!-- the answer, in one sentence -->
      <section class="sub-answer" id="subAnswer" aria-live="polite"><div class="lcy-empty">Loading&hellip;</div></section>

      <section class="wd-stats sub-stats" id="subStats"></section>

      <div class="sub-grid">
        <section class="wd-card sub-attention">
          <div class="wd-card__head">
            <h3>Needs attention</h3>
            <span class="lcy-muted" id="subAttnNote"></span>
          </div>
          <div id="subAttention"></div>
        </section>
        <div class="sub-side">
          <section class="wd-card" id="subPhilgepsCard">
            <div class="wd-card__head"><h3>Can we bid on PhilGEPS?</h3></div>
            <div id="subPhilgeps"></div>
          </section>
          <section class="wd-card">
            <div class="wd-card__head"><h3>Next 12 months</h3><span class="lcy-muted" id="subCostNote"></span></div>
            <div id="subTimeline"></div>
          </section>
        </div>
      </div>

      <div class="lcy-tabs sub-tabs" id="subTabs" role="tablist">
        <button type="button" class="lcy-tab is-on" data-tab="all"><i class="fa-solid fa-layer-group"></i> All</button>
        <button type="button" class="lcy-tab" data-tab="domain"><i class="fa-solid fa-globe"></i> Domains</button>
        <button type="button" class="lcy-tab" data-tab="hosting"><i class="fa-solid fa-server"></i> Hosting</button>
        <button type="button" class="lcy-tab" data-tab="security"><i class="fa-solid fa-shield-halved"></i> Security</button>
        <button type="button" class="lcy-tab" data-tab="philgeps"><i class="fa-solid fa-landmark"></i> PhilGEPS</button>
        <button type="button" class="lcy-tab" data-tab="reminders"><i class="fa-solid fa-bell"></i> Reminders</button>
      </div>

      <section class="wd-card sub-pane" id="subPaneList">
        <div class="sub-toolbar">
          <input type="search" class="wd-input" id="subQ" placeholder="Search name, provider, account&hellip;" aria-label="Search subscriptions">
          <label class="lcy-check"><input type="checkbox" id="subShowCancelled"> Show cancelled</label>
        </div>
        <div id="subList"><div class="lcy-empty">Loading&hellip;</div></div>
      </section>

      <section class="wd-card sub-pane lcy-hidden" id="subPaneReminders">
        <div id="subReminders"></div>
      </section>

      <!-- one side panel: an item, or a form; its content is swapped, so nothing stacks -->
      <div class="modal fade" id="subModal" tabindex="-1">
        <div class="modal-dialog sub-panel" id="subModalDialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title" id="subModalTitle"></h4>
            </div>
            <div class="modal-body" id="subModalBody"></div>
            <div class="modal-footer" id="subModalFoot"></div>
          </div>
        </div>
      </div>

      <script>window.LCY_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
      <script src="assets/js/lifecycle-common.js?v=<?php echo $v('assets/js/lifecycle-common.js'); ?>"></script>
      <script src="assets/js/subscriptions.js?v=<?php echo $v('assets/js/subscriptions.js'); ?>"></script>
<?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>
  </body>
</html>
