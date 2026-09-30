<?php
/* ==========================================================================
   corner-bubble.php — floating chat-head style shortcut to the Corner
   (announcements + holiday calendar), shown on every signed-in page.
   Included by includes/wd-footer.php (themed pages) and includes/header.php
   (legacy pages; set $wdcb_legacy = true first so the design tokens and the
   calendar styles are provided without loading the full theme).
   Behaviour lives in assets/js/wedo-corner-bubble.js, styles in
   assets/css/wedo-corner-bubble.css.
   ========================================================================== */
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { return; }
if (defined('WD_CORNER_BUBBLE')) { return; }      // once per page
if (($wd_active ?? '') === 'corner') { return; }  // the Corner page already shows it all

/* same gate as the sidebar's Corner link */
$wdcbAllowed = false;
if (isset($ar) && is_array($ar) && array_key_exists('gcorner', $ar)) {
    $wdcbAllowed = ($ar['gcorner'] == 2);
} else {
    try {
        include 'w_conn.php';
        $wdcbPdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $wdcbPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $wdcbSt = $wdcbPdo->prepare("SELECT gcorner FROM accessrights WHERE EmpID = :id");
        $wdcbSt->execute([':id' => $_SESSION['id']]);
        $wdcbAllowed = ($wdcbSt->fetchColumn() == 2);
    } catch (Exception $e) { $wdcbAllowed = false; }
}
if (!$wdcbAllowed) { return; }
define('WD_CORNER_BUBBLE', 1);

/* unseen count: wd-header.php already computed it; legacy pages ask here */
if (isset($wdUnseenAnn)) {
    $wdcbUnseen = (int) $wdUnseenAnn;
} else {
    $wdcbUnseen = 0;
    try {
        require_once __DIR__ . '/corner-lib.php';
        if (!isset($wdcbPdo)) {
            include 'w_conn.php';
            $wdcbPdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
            $wdcbPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $wdcbUnseen = wd_corner_unseen_count($wdcbPdo, $_SESSION['id']);
    } catch (Exception $e) { $wdcbUnseen = 0; }
}

$wdcbName  = trim(($_SESSION['CompanyName'] ?? '') !== '' ? $_SESSION['CompanyName'] . ' Corner' : 'WeDo Corner');
$wdcbLabel = $wdcbName . ($wdcbUnseen > 0 ? ' — ' . $wdcbUnseen . ' new announcement' . ($wdcbUnseen > 1 ? 's' : '') : '');
$wdcbRoot  = dirname(__DIR__);
$wdcbV     = function ($rel) use ($wdcbRoot) { return @filemtime($wdcbRoot . '/' . $rel); };
$wdcbSvg   = [
  'mega'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
  'cal'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
  'x'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
  'open'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>',
];
?>
<?php if (!empty($wdcb_legacy)): ?>
<style>
  /* legacy shell: design tokens for the bubble only (see assets/css/wedo-theme.css) */
  :root{--brand:#f93627;--brand-tint:rgba(249,54,39,.10);--navy:#09152e;--navy-2:#0f2147;--bg:#f5f6f8;--surface:#fff;--surface-2:#f0f2f5;
    --text:#16203a;--text-2:#586173;--text-3:#909aab;--border:#e7eaf0;--border-2:#d6dbe4;
    --warn-bg:#fdf0dd;--warn-text:#97640f;--danger-bg:#fdebe9;--danger-text:#b22a1d;--info-bg:#e8effb;--info-text:#1f5bbf;
    --font-head:'Raleway',system-ui,-apple-system,Segoe UI,sans-serif;--font-body:system-ui,-apple-system,Segoe UI,sans-serif;
    --radius:10px;--radius-lg:14px;--radius-pill:999px;--shadow-sm:0 1px 2px rgba(16,24,40,.05)}
</style>
<?php endif; ?>
<link rel="stylesheet" href="assets/css/wedo-calendar.css?v=<?php echo $wdcbV('assets/css/wedo-calendar.css'); ?>">
<link rel="stylesheet" href="assets/css/wedo-corner-bubble.css?v=<?php echo $wdcbV('assets/css/wedo-corner-bubble.css'); ?>">

<div class="wdcb<?php echo $wdcbUnseen > 0 ? ' has-unseen' : ''; ?>" id="wdcb" data-side="right"
     data-api="includes/corner-bubble-api.php" data-cal="includes/calendar-ajax.php"
     data-unseen="<?php echo (int) $wdcbUnseen; ?>" data-name="<?php echo htmlspecialchars($wdcbName); ?>">

  <a class="wdcb-bubble" id="wdcbBubble" href="corner" role="button" draggable="false"
     aria-haspopup="dialog" aria-expanded="false" aria-controls="wdcbPanel"
     aria-label="<?php echo htmlspecialchars($wdcbLabel); ?>" title="<?php echo htmlspecialchars($wdcbLabel); ?>">
    <span class="wdcb-bubble__icon"><?php echo $wdcbSvg['mega']; ?></span>
    <span class="wdcb-bubble__close"><?php echo $wdcbSvg['x']; ?></span>
    <span class="wdcb-badge" aria-hidden="true"><?php echo $wdcbUnseen > 99 ? '99+' : (int) $wdcbUnseen; ?></span>
  </a>

  <div class="wdcb-dismiss" aria-hidden="true"><?php echo $wdcbSvg['x']; ?><span class="wdcb-dismiss__label">Drop to hide</span></div>
  <div class="wdcb-backdrop" data-wdcb-close></div>

  <section class="wdcb-panel" id="wdcbPanel" role="dialog" aria-labelledby="wdcbTitle">
    <header class="wdcb-head">
      <div class="wdcb-head__icon" aria-hidden="true"><?php echo $wdcbSvg['mega']; ?></div>
      <div class="wdcb-head__text">
        <h2 class="wdcb-head__title" id="wdcbTitle"><?php echo htmlspecialchars($wdcbName); ?></h2>
        <p class="wdcb-head__sub"><?php echo date('l, F j, Y'); ?></p>
      </div>
      <a class="wdcb-iconbtn" href="corner" title="Open the full Corner page" aria-label="Open the full Corner page"><?php echo $wdcbSvg['open']; ?></a>
      <button type="button" class="wdcb-iconbtn" data-wdcb-close title="Close" aria-label="Close"><?php echo $wdcbSvg['x']; ?></button>
    </header>

    <div class="wdcb-tabs" role="tablist" aria-label="Corner sections">
      <button type="button" class="wdcb-tab" role="tab" id="wdcbTabAnn" data-tab="ann" aria-controls="wdcbAnn" aria-selected="true">
        <?php echo $wdcbSvg['mega']; ?> Announcements
        <span class="wdcb-tabcount"<?php echo $wdcbUnseen > 0 ? '' : ' hidden'; ?>><?php echo $wdcbUnseen > 99 ? '99+' : (int) $wdcbUnseen; ?></span>
      </button>
      <button type="button" class="wdcb-tab" role="tab" id="wdcbTabCal" data-tab="cal" aria-controls="wdcbCal" aria-selected="false" tabindex="-1">
        <?php echo $wdcbSvg['cal']; ?> Calendar
      </button>
    </div>

    <div class="wdcb-body">
      <div class="wdcb-pane" role="tabpanel" id="wdcbAnn" aria-labelledby="wdcbTabAnn" tabindex="-1"></div>
      <div class="wdcb-pane" role="tabpanel" id="wdcbCal" aria-labelledby="wdcbTabCal" tabindex="-1" hidden>
        <div class="wdcb-legend">
          <span><i style="background:var(--navy)"></i> Today</span>
          <span><i style="background:var(--danger-bg);border:1px solid var(--danger-text)"></i> Regular holiday</span>
          <span><i style="background:var(--info-bg);border:1px solid var(--info-text)"></i> Special holiday</span>
          <span><svg viewBox="0 0 24 24" aria-hidden="true" style="width:14px;height:14px;fill:none;stroke:var(--bday-text);stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M5 16.5c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0"/><path d="M12 12V8.5"/><path d="M12 6c.8 0 1.3-.6 1.3-1.3C13.3 3.8 12 2.5 12 2.5s-1.3 1.3-1.3 2.2c0 .7.5 1.3 1.3 1.3z"/></svg> Birthday</span>
        </div>
        <div class="wdcb-calbox" tabindex="0" aria-label="Company calendar"></div>
      </div>
    </div>
  </section>

  <div class="wdcb-hint" role="status">
    <b>Your Corner, one tap away</b>
    Check announcements and holidays from any page. Drag the bubble to move it.
    <button type="button" class="wdcb-hint__x" aria-label="Dismiss tip"><?php echo $wdcbSvg['x']; ?></button>
  </div>
  <div class="wdcb-toast" role="status" aria-live="polite"><span class="wdcb-toast__msg"></span><button type="button" class="wdcb-toast__btn">Undo</button></div>
</div>
<script src="assets/js/wedo-corner-bubble.js?v=<?php echo $wdcbV('assets/js/wedo-corner-bubble.js'); ?>" defer></script>
