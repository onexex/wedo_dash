<?php
/* ==========================================================================
   msgreview.php  —  Management > Message Review.
   Read-only access to every conversation in Messages (1-to-1 and groups) for
   HR / admin investigations, deleted messages included. Gated by the
   `msgaudit` access right; rules in includes/msg-review.php, data from
   query/msgreview-action.php, files from query/msgreview-file.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/msg-review.php';

/* access gate BEFORE any output so the redirect can fire (least privilege) */
try {
    $mrpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $mrpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!mr_can_review($mrpdo, (string) $_SESSION['id'])) { header('location: 404?'); exit(); }

$boot = [
    'archive' => mr_table($mrpdo, 'msg_deleted_archive'),
    'open'    => (string) ($_GET['key'] ?? ''),
];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Message Review" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <!-- Functional libs -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (after bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/msgreview.css?v=<?php echo $v('assets/css/msgreview.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
  </head>
  <body>
    <?php
      $wd_active = 'msgreview';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead mr-noprint">
        <div>
          <h1>Message Review</h1>
          <p>Read every conversation in Messages — 1-to-1 and groups, deleted messages included — for investigations.</p>
        </div>
      </div>

      <div class="mr-notice mr-noprint" role="note">
        <i class="fa-solid fa-user-shield"></i>
        <div>
          <b>Confidential — use only for an open investigation.</b>
          Nothing here marks messages as read or tells anyone you looked.
          <?php if (!$boot['archive']): ?>
            <br><span class="mr-warn">Deleted messages aren’t being kept yet: run <code>sql/2026-10-14-add-message-review.sql</code> on this server.</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="mr-layout" id="mrApp">
        <!-- ------------------------------------------------ filters + conversation list -->
        <section class="wd-card mr-side mr-noprint">
          <form class="mr-filters" id="mrFilters" autocomplete="off">
            <div class="mr-field mr-person">
              <label for="mrPerson">Involving</label>
              <div class="mr-picked" id="mrPicked" hidden>
                <span id="mrPickedName"></span>
                <button type="button" id="mrPickedClear" aria-label="Clear person"><i class="fa-solid fa-xmark"></i></button>
              </div>
              <input type="search" class="wd-input" id="mrPerson" placeholder="Any employee — type a name or ID">
              <div class="mr-suggest" id="mrSuggest" role="listbox" hidden></div>
            </div>
            <div class="mr-field">
              <label for="mrQ">Words in a message</label>
              <input type="search" class="wd-input" id="mrQ" placeholder="e.g. a threat, a name, a link">
            </div>
            <div class="mr-row">
              <div class="mr-field"><label for="mrFrom">From</label><input type="date" class="wd-input" id="mrFrom"></div>
              <div class="mr-field"><label for="mrTo">To</label><input type="date" class="wd-input" id="mrTo"></div>
            </div>
            <div class="mr-row mr-row--end">
              <div class="mr-seg" role="radiogroup" aria-label="Conversation type">
                <button type="button" class="is-on" data-type="">All</button>
                <button type="button" data-type="dm">1-to-1</button>
                <button type="button" data-type="group">Groups</button>
              </div>
              <button type="submit" class="wd-btn wd-btn--primary wd-btn--sm"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            </div>
          </form>
          <div class="mr-listhead"><span id="mrCount">Loading…</span><button type="button" class="mr-link" id="mrReset" hidden>Clear filters</button></div>
          <div class="mr-list" id="mrList" aria-live="polite"></div>
        </section>

        <!-- ------------------------------------------------ transcript -->
        <section class="wd-card mr-main" id="mrMain">
          <div class="mr-empty" id="mrEmpty">
            <i class="fa-regular fa-comments"></i>
            <p>Pick a conversation to read its full history.</p>
          </div>

          <div class="mr-thread" id="mrThread" hidden>
            <header class="mr-threadhead">
              <button type="button" class="mr-back mr-noprint" id="mrBack" aria-label="Back to the list"><i class="fa-solid fa-arrow-left"></i></button>
              <div class="mr-threadhead__main">
                <div class="mr-threadhead__type" id="mrType"></div>
                <h2 id="mrTitle"></h2>
                <div class="mr-people" id="mrPeople"></div>
              </div>
              <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm mr-noprint" id="mrPrint"><i class="fa-solid fa-print"></i> Print</button>
            </header>
            <div class="mr-printnote" id="mrPrintNote"></div>
            <div class="mr-msgs" id="mrMsgs"></div>
          </div>
        </section>
      </div>

    <?php include 'includes/wd-footer.php'; ?>

    <script>
      window.MR_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="assets/js/msgreview.js?v=<?php echo $v('assets/js/msgreview.js'); ?>"></script>
  </body>
</html>
