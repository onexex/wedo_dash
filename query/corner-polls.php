<?php
/* ==========================================================================
   corner-polls.php  —  JSON API for Corner polls (includes/corner-polls-lib.php).
   Used by assets/js/corner-polls.js on corner.php.

   GET  action=cards&ids=1,2,3                    {cards:{id:html}} (live refresh; deleted polls come back as "")
   POST action=create&token&question&details&options[]&multi&allow_add&anonymous&closes[&closes_at]
                                                  start a poll (super users + the "Create Corner polls" right)
   POST action=vote&id&option&token               pick / un-pick an option
   POST action=add_option&id&label&token          suggest an option (it also gets your vote)
   POST action=close&id&token                     stop voting   (poll author or super user)
   POST action=delete&id&token                    remove it     (poll author or super user)

   Every write answers {status:"ok", html:<the poll's new card>} or
   {status:"error", msg:"…"} with a 4xx code.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function poll_out($code, array $body) { http_response_code($code); echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); exit; }

set_exception_handler(function (Throwable $e) {
    $ref = substr(md5(uniqid('', true)), 0, 6);
    error_log('[wedo polls ' . $ref . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['status' => 'error', 'msg' => 'Something went wrong on the server (ref ' . $ref . '). Please try again.']);
    exit;
});

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    poll_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/corner-polls-lib.php';

$me      = (string) $_SESSION['id'];
$tokenOk = hash_equals(wd_poll_csrf_token(), (string) ($_POST['token'] ?? ''));
session_write_close();   // $_SESSION stays readable (wd_poll_can_manage) without holding the lock

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    poll_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}
if (!wd_polls_ready($pdo)) {
    poll_out(503, ['status' => 'error', 'msg' => 'Polls are not set up on this server yet.']);
}

$action = (string) ($_REQUEST['action'] ?? '');
$id     = (int) ($_POST['id'] ?? 0);

/* the poll's freshly rendered card, or "" when it is gone */
function poll_card_html(PDO $pdo, $me, $id) {
    $polls = wd_polls_load($pdo, $me, [$id]);
    return isset($polls[$id]) ? wd_poll_card($polls[$id], $me) : '';
}

if ($action === 'cards') {
    $ids = array_slice(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? '')))), 0, 50);
    $polls = wd_polls_load($pdo, $me, $ids);
    $cards = [];
    foreach ($ids as $pid) { $cards[$pid] = isset($polls[$pid]) ? wd_poll_card($polls[$pid], $me) : ''; }
    poll_out(200, ['status' => 'ok', 'cards' => (object) $cards]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { poll_out(405, ['status' => 'error', 'msg' => 'Use POST.']); }
if (!$tokenOk) { poll_out(403, ['status' => 'error', 'msg' => 'This page is out of date — reload it and try again.']); }

switch ($action) {
    case 'create':
        if (!wd_poll_can_create($pdo, $me)) { poll_out(403, ['status' => 'error', 'msg' => 'Starting polls needs the "Create Corner polls" access right.']); }
        $newId = 0;
        $err = wd_poll_create($pdo, $me, $_POST, $newId);
        if ($err !== null) { poll_out(422, ['status' => 'error', 'msg' => $err]); }
        require_once __DIR__ . '/../includes/push-lib.php';
        push_announcement($pdo, $me, wd_poll_clean($_POST['question'] ?? ''), 'New poll — tap to vote');
        poll_out(200, ['status' => 'ok', 'id' => $newId, 'html' => poll_card_html($pdo, $me, $newId)]);

    case 'vote':
        $err = wd_poll_vote($pdo, $me, $id, (int) ($_POST['option'] ?? 0));
        break;
    case 'add_option':
        $err = wd_poll_add_option($pdo, $me, $id, (string) ($_POST['label'] ?? ''));
        break;
    case 'close':
        $err = wd_poll_close($pdo, $me, $id);
        break;
    case 'delete':
        $err = wd_poll_delete($pdo, $me, $id);
        break;
    default:
        poll_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
}

if ($err !== null) {
    poll_out(strpos($err, 'Only ') === 0 ? 403 : 409, ['status' => 'error', 'msg' => $err, 'html' => poll_card_html($pdo, $me, $id)]);
}
poll_out(200, ['status' => 'ok', 'html' => poll_card_html($pdo, $me, $id)]);
