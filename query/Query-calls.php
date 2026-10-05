<?php
/* ==========================================================================
   Query-calls.php  —  JSON API for video calls (see includes/msg-calls.php).
   Used by assets/js/wedo-call.js on every signed-in page.

   GET  action=incoming[&hs=SIG]                 a call ringing for me right now (or null) + my unread
                                                 conversations (top-bar envelope badge); also marks me online.
                                                 With hs: the chat-heads signature, plus the heads
                                                 themselves when it differs from SIG (msg-heads-lib.php)
   GET  action=state&id=N&after=SIGID            call + members + signals addressed to me; keeps me "present"
   POST action=start&with=EmpID|grp:ID&token     call a person or a whole group
   POST action=join&id=N&token                   answer / join a group call late   (alias: answer)
   POST action=leave&id=N&token                  decline / cancel / hang up        (alias: hangup)
   POST action=signal&id=N&to=EmpID&kind=offer|answer|ice&payload=JSON&token

   The session is released right after reading it: the ringer polls every few
   seconds from every page and must never queue behind the user's other requests.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function call_out($code, array $body) { http_response_code($code); echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); exit; }

/* Anything unexpected: log it with a short reference and answer in JSON (never an
   HTML error page the browser can't read). Super users see the actual error. */
set_exception_handler(function (Throwable $e) {
    $ref = substr(md5(uniqid('', true)), 0, 6);
    error_log('[wedo calls ' . $ref . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
    $admin = (string) ($GLOBALS['wdApiUserType'] ?? '') === '1';
    echo json_encode(['status' => 'error', 'ref' => $ref,
        'msg' => $admin ? 'Server error (ref ' . $ref . '): ' . $e->getMessage() : 'Something went wrong on the server (ref ' . $ref . '). Please try again.']);
    exit;
});

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    call_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/msg-calls.php';
require_once __DIR__ . '/../includes/msg-heads-lib.php';

$me       = (string) $_SESSION['id'];
$userType = $_SESSION['UserType'] ?? '';
$wdApiUserType = $userType;
$tokenOk  = hash_equals(msg_csrf_token(), (string) ($_POST['token'] ?? ''));
session_write_close();

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    call_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

$action = (string) ($_REQUEST['action'] ?? '');
$action = ['answer' => 'join', 'hangup' => 'leave'][$action] ?? $action;

// the ringer checks in from every page, so it also keeps my "online" status fresh site-wide
if ($action === 'incoming') { msg_touch($pdo, $me); }

/** The check-in's message part: unread count for the envelope, and chat heads when the page shows them. */
function call_inbox(PDO $pdo, string $me): array
{
    if (!isset($_GET['hs'])) { return ['unread' => msg_unread_threads($pdo, $me)]; }
    $s   = mh_state($pdo, $me, (string) $_GET['hs']);
    $out = ['unread' => $s['total'], 'hs' => $s['sig']];
    if ($s['heads'] !== null) { $out['heads'] = $s['heads']; }
    return $out;
}

if (!call_ready($pdo)) {
    // migrations not applied yet: the ringer quietly does nothing
    if ($action === 'incoming') { call_out(200, ['status' => 'ok', 'call' => null, 'disabled' => true] + call_inbox($pdo, $me)); }
    // 409, not 5xx: some hosts swap a 5xx body for their own HTML error page, which the browser can't read
    call_out(409, ['status' => 'error', 'code' => 'disabled', 'msg' => 'Video calls aren’t set up on this server yet.']);
}

/* ---------------------------------------------------------------- reads */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($action === 'incoming') {
        call_out(200, ['status' => 'ok', 'call' => call_incoming($pdo, $me)] + call_inbox($pdo, $me));
    }
    if ($action === 'state') {
        $call = call_for_member($pdo, (int) ($_GET['id'] ?? 0), $me);
        if (!$call) { call_out(404, ['status' => 'error', 'msg' => 'Call not found.']); }
        if ($call['status'] !== 'ended') {
            call_ping($pdo, $call, $me);
            $call = call_tick($pdo, call_get($pdo, (int) $call['id']));
        }
        call_out(200, ['status' => 'ok', 'call' => call_public($pdo, $call, $me),
                       'signals' => call_signals($pdo, $call, $me, max(0, (int) ($_GET['after'] ?? 0)))]);
    }
    call_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
}

/* ---------------------------------------------------------------- writes */
if (!$tokenOk) { call_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']); }

if ($action === 'start') {
    $with = trim((string) ($_POST['with'] ?? ''));
    $gid  = grp_id_from_key($with);
    $res  = call_start($pdo, $me, $gid ? ['group' => $gid] : ['emp' => $with], $userType);
    if (!$res['ok']) { call_out(409, ['status' => 'error', 'code' => $res['code'], 'msg' => $res['error']]); }
    call_out(200, ['status' => 'ok', 'call' => call_public($pdo, $res['call'], $me), 'iceServers' => call_ice_servers()]);
}

$call = call_for_member($pdo, (int) ($_POST['id'] ?? 0), $me);
if (!$call) { call_out(404, ['status' => 'error', 'msg' => 'Call not found.']); }

if ($action === 'join') {
    $res = call_join($pdo, $call, $me);
    if (!$res['ok']) { call_out(409, ['status' => 'error', 'code' => $res['code'], 'msg' => $res['error']]); }
    call_out(200, ['status' => 'ok', 'call' => call_public($pdo, $res['call'], $me), 'iceServers' => call_ice_servers()]);
}
if ($action === 'leave') {
    call_out(200, ['status' => 'ok', 'call' => call_public($pdo, call_leave($pdo, $call, $me), $me)]);
}
if ($action === 'signal') {
    $res = call_add_signal($pdo, $call, $me, (string) ($_POST['to'] ?? ''), (string) ($_POST['kind'] ?? ''), (string) ($_POST['payload'] ?? ''));
    if (!$res['ok']) { call_out(422, ['status' => 'error', 'msg' => $res['error']]); }
    call_out(200, ['status' => 'ok']);
}
call_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
