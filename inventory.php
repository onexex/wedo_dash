<?php
/* ==========================================================================
   inventory.php  —  Management > Inventory. The register of company property,
   documents and supplies: who holds what and where it is, repairs, stock,
   physical counts and alerts. Rules: includes/inventory-lib.php. Reads and
   writes: query/inventory-action.php. Gated by the `inventory` access right.

   ?tag=FUR-0012 opens one item: this is what the QR label points at. Someone
   signed out goes through sign-in first; someone without the right lands on
   My Items.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
require_once __DIR__ . '/includes/inventory-lib.php';

$tag = inv_clean_tag($_GET['tag'] ?? '');
if (!inv_valid_tag($tag)) { $tag = ''; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    if ($tag !== '') { $_SESSION['wd_after_tag'] = $tag; }   // wd_after_login() comes back here
    header('location: login.php');
    exit();
}

include 'w_conn.php';

/* access gate BEFORE any output so the redirect can fire */
try {
    $invpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $invpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!inv_can_manage($invpdo)) {
    header('location: ' . ($tag !== '' ? 'myitems' : '404?'));
    exit();
}

require_once __DIR__ . '/includes/bot-guard.php';
require_once __DIR__ . '/includes/mail-lib.php';

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
    'endpoint'  => 'query/inventory-action.php',
    'token'     => inv_csrf_token(),
    'today'     => date('Y-m-d'),
    'tag'       => $tag,
    'siteUrl'   => wd_site_url(),
    'company'   => (string) ($_SESSION['CompanyName'] ?? ''),
    'logo'      => (string) ($_SESSION['CompanyLogo'] ?? '') !== '' ? (string) $_SESSION['CompanyLogo'] : 'assets/images/logos/WeDo.png',   // printed TSDR header
    'letterhead' => [   // printed forms, from the company letterhead (Letter-Head.docx)
        'logo'    => 'assets/images/letterhead/wedo-logo.png',
        'address' => 'Unit 304-A Tektite Tower West Exchange Road and Pearl Drive, Ortigas Center, Pasig City 1605 Philippines',
        'email'   => 'admin@wedoinc.ph',
    ],
    'maxUpload' => (int) min($iniBytes(ini_get('upload_max_filesize')), $iniBytes(ini_get('post_max_size'))),
];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Inventory" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>

    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <link rel="stylesheet" href="assets/css/inventory.css?v=<?php echo $v('assets/css/inventory.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
  </head>
  <body>
    <?php
      $wd_active = 'inventory';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead inv-head">
        <div>
          <h1>Inventory</h1>
          <p>Company property, documents and supplies: where everything is, who holds it, and what needs attention.</p>
        </div>
      </div>

<?php if (!inv_ready($invpdo)): ?>
      <section class="wd-card"><div class="lcy-empty">This page is not set up on this server yet — run <code>sql/2026-11-05-add-inventory.sql</code>.</div></section>
<?php else: ?>
      <section class="wd-stats" id="invStats"></section>

      <div class="lcy-tabs inv-tabs" id="invTabs" role="tablist">
        <button type="button" class="lcy-tab is-on" data-tab="items"><i class="fa-solid fa-boxes-stacked"></i> Items</button>
        <button type="button" class="lcy-tab" data-tab="supplies"><i class="fa-solid fa-dolly"></i> Supplies</button>
        <button type="button" class="lcy-tab" data-tab="people"><i class="fa-solid fa-users"></i> People</button>
        <button type="button" class="lcy-tab" data-tab="tsdr"><i class="fa-solid fa-file-signature"></i> TSDR</button>
        <button type="button" class="lcy-tab" data-tab="repairs"><i class="fa-solid fa-screwdriver-wrench"></i> Repairs</button>
        <button type="button" class="lcy-tab" data-tab="counts"><i class="fa-solid fa-list-check"></i> Counts</button>
        <button type="button" class="lcy-tab" data-tab="alerts"><i class="fa-solid fa-bell"></i> Alerts <span class="inv-tabcount" id="invAlertCount"></span></button>
        <button type="button" class="lcy-tab" data-tab="setup"><i class="fa-solid fa-gear"></i> Setup</button>
      </div>

      <!-- Items -->
      <section class="wd-card inv-pane" id="invPaneItems">
        <div class="inv-toolbar">
          <input type="search" class="wd-input" id="invQ" placeholder="Search tag, name, serial&hellip;" aria-label="Search items">
          <div class="inv-filters">
          <select class="wd-select" id="invFCat" aria-label="Category"></select>
          <select class="wd-select" id="invFLoc" aria-label="Location"></select>
          <select class="wd-select" id="invFStatus" aria-label="Status">
            <option value="">On the register</option>
            <option value="available">Available</option>
            <option value="issued">Issued</option>
            <option value="repair">Under repair</option>
            <option value="lost">Lost</option>
            <option value="disposed">Disposed</option>
            <option value="all">All, including disposed</option>
          </select>
          <span class="inv-spacer"></span>
          <button type="button" class="wd-btn wd-btn--ghost" id="invExport"><i class="fa-solid fa-file-excel"></i> Export</button>
          </div>
          <button type="button" class="wd-btn wd-btn--primary" id="invAdd"><i class="fa-solid fa-plus"></i> Add item</button>
        </div>
        <div class="inv-bulk lcy-hidden" id="invBulk">
          <span id="invBulkCount"></span>
          <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invBulkLabels"><i class="fa-solid fa-qrcode"></i> Print labels</button>
          <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invBulkIssue"><i class="fa-solid fa-hand-holding"></i> Issue</button>
          <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invBulkMove"><i class="fa-solid fa-right-left"></i> Move</button>
        </div>
        <div id="invItems"><div class="lcy-empty">Loading&hellip;</div></div>
      </section>

      <!-- Supplies -->
      <section class="wd-card inv-pane lcy-hidden" id="invPaneSupplies">
        <div class="inv-toolbar">
          <input type="search" class="wd-input" id="invSupQ" placeholder="Search supplies&hellip;" aria-label="Search supplies">
          <select class="wd-select" id="invSupLoc" aria-label="Location"></select>
          <span class="inv-spacer"></span>
          <button type="button" class="wd-btn wd-btn--primary" id="invSupAdd"><i class="fa-solid fa-plus"></i> Add supply</button>
        </div>
        <div id="invSupplies"><div class="lcy-empty">Loading&hellip;</div></div>
        <div class="wd-card__head inv-subhead">
          <h3>Usage report</h3>
          <div class="inv-toolbar inv-toolbar--tight">
            <label for="invUseFrom" class="lcy-muted">From</label>
            <input type="date" class="wd-input" id="invUseFrom">
            <label for="invUseTo" class="lcy-muted">To</label>
            <input type="date" class="wd-input" id="invUseTo">
            <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invUseRun"><i class="fa-solid fa-rotate"></i> Refresh</button>
            <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invUseExport"><i class="fa-solid fa-file-excel"></i> Export</button>
          </div>
        </div>
        <div id="invUsage"></div>
      </section>

      <!-- People -->
      <section class="wd-card inv-pane lcy-hidden" id="invPanePeople">
        <div class="wd-card__head"><h3>Who holds company property</h3></div>
        <div id="invPeople"></div>
      </section>

      <!-- TSDR (Technical Services Data Report) -->
      <section class="wd-card inv-pane lcy-hidden" id="invPaneTsdr">
        <div class="wd-card__head"><h3>Technical Services Data Reports</h3></div>
        <div class="inv-toolbar">
          <input type="search" class="wd-input" id="invTsdrQ" placeholder="Search TSDR #, name, account, item&hellip;" aria-label="Search TSDRs">
          <span class="inv-spacer"></span>
          <button type="button" class="wd-btn wd-btn--primary" id="invTsdrNew"><i class="fa-solid fa-file-signature"></i> Make TSDR</button>
        </div>
        <div id="invTsdrs"></div>
      </section>

      <!-- Repairs -->
      <section class="wd-card inv-pane lcy-hidden" id="invPaneRepairs">
        <div class="wd-card__head"><h3>Repairs and servicing</h3></div>
        <div id="invRepairs"></div>
      </section>

      <!-- Counts -->
      <section class="wd-card inv-pane lcy-hidden" id="invPaneCounts">
        <div id="invCounts"></div>
      </section>

      <!-- Alerts -->
      <section class="wd-card inv-pane lcy-hidden" id="invPaneAlerts">
        <div class="wd-card__head"><h3>Needs attention</h3></div>
        <div id="invAlerts"></div>
      </section>

      <!-- Setup -->
      <section class="inv-pane lcy-hidden" id="invPaneSetup">
        <div class="lcy-grid2">
          <section class="wd-card">
            <div class="wd-card__head"><h3>Categories</h3><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invCatAdd"><i class="fa-solid fa-plus"></i> Add</button></div>
            <div id="invCats"></div>
          </section>
          <section class="wd-card">
            <div class="wd-card__head"><h3>Locations</h3><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="invLocAdd"><i class="fa-solid fa-plus"></i> Add</button></div>
            <div id="invLocs"></div>
          </section>
        </div>
      </section>

      <!-- one modal: an item, or a form; its content is swapped, so nothing ever stacks -->
      <div class="modal fade" id="invModal" tabindex="-1">
        <div class="modal-dialog" id="invModalDialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title" id="invModalTitle"></h4>
            </div>
            <div class="modal-body" id="invModalBody"></div>
            <div class="modal-footer" id="invModalFoot"></div>
          </div>
        </div>
      </div>

      <script>window.LCY_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
      <script src="assets/js/lifecycle-common.js?v=<?php echo $v('assets/js/lifecycle-common.js'); ?>"></script>
      <script src="assets/js/inventory.js?v=<?php echo $v('assets/js/inventory.js'); ?>"></script>
<?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>
    <div id="invPrint"></div>
  </body>
</html>
