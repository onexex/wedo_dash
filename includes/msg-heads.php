<?php
/* ==========================================================================
   msg-heads.php — Messenger-style chat heads on every signed-in page: a
   round photo per conversation with something unread, stacked above the
   Corner bubble, with a pop-up preview, a soft ping and a mini chat window.
   Nothing shows while everything is read.

   Included by includes/wd-footer.php and includes/header.php (legacy pages),
   BEFORE call-widget.php: the ringer's check-in (assets/js/wedo-call.js)
   carries the heads. Behaviour: assets/js/wedo-msg-heads.js; styles:
   assets/css/wedo-msg-heads.css (self-contained, legacy pages have no theme).
   Data: includes/msg-heads-lib.php. Not shown on the Messages page itself.
   ========================================================================== */
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { return; }
if (defined('WD_MSG_HEADS')) { return; }               // once per page
if (($wd_active ?? '') === 'messages') { return; }     // the full chat is already on screen
define('WD_MSG_HEADS', 1);

require_once __DIR__ . '/msg-heads-lib.php';

/* first paint: heads already unread when the page loads (no 3-second wait) */
$wdmhState = null;
try {
    $wdmhPdo = (isset($wdpdo) && $wdpdo instanceof PDO) ? $wdpdo : null;
    if (!$wdmhPdo) {
        include 'w_conn.php';
        $wdmhPdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
        $wdmhPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    $wdmhState = mh_state($wdmhPdo, (string) $_SESSION['id']);
} catch (Throwable $e) {
    return;   // Messages not set up on this server: no heads
}

$wdmhRoot = dirname(__DIR__);
$wdmhV    = function ($f) use ($wdmhRoot) { return @filemtime($wdmhRoot . '/' . $f) ?: 0; };
$wdmhCfg  = [
    'api'   => 'query/Query-messages.php',
    'page'  => 'messages',
    'token' => msg_csrf_token(),
    'me'    => (string) $_SESSION['id'],
    'sig'   => $wdmhState['sig'],
    'total' => $wdmhState['total'],
    'heads' => $wdmhState['heads'] ?? [],
];
?>
<link rel="stylesheet" href="assets/css/wedo-msg-heads.css?v=<?php echo $wdmhV('assets/css/wedo-msg-heads.css'); ?>">
<script>window.WD_HEADS = <?php echo json_encode($wdmhCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="assets/js/wedo-msg-heads.js?v=<?php echo $wdmhV('assets/js/wedo-msg-heads.js'); ?>" defer></script>
