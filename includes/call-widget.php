<?php
/* ==========================================================================
   call-widget.php — video calls on every signed-in page: the incoming-call
   ringer and the call window. Included next to the Corner bubble by
   includes/wd-footer.php (themed pages) and includes/header.php (legacy pages).
   Everything visible is built by assets/js/wedo-call.js (styles in
   assets/css/wedo-call.css, self-contained so legacy pages need no theme).
   Server side: query/Query-calls.php, includes/msg-calls.php.
   ========================================================================== */
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { return; }
if (defined('WD_CALL_WIDGET')) { return; }   // once per page
define('WD_CALL_WIDGET', 1);

require_once __DIR__ . '/messages-lib.php';
$wdcRoot = dirname(__DIR__);
$wdcV    = function ($f) use ($wdcRoot) { return @filemtime($wdcRoot . '/' . $f) ?: 0; };
$wdcCfg  = ['api' => 'query/Query-calls.php', 'token' => msg_csrf_token(), 'me' => (string) $_SESSION['id']];
?>
<link rel="stylesheet" href="assets/css/wedo-call.css?v=<?php echo $wdcV('assets/css/wedo-call.css'); ?>">
<script>window.WD_CALL = <?php echo json_encode($wdcCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="assets/js/wedo-call.js?v=<?php echo $wdcV('assets/js/wedo-call.js'); ?>" defer></script>
