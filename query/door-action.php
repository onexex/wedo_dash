<?php
/* ==========================================================================
   door-action.php  —  reads + writes for door.php (Management > Door Control).
   POST action=status|enrolled|unlock, returns JSON. Every action needs the `doorctl`
   right and the per-session token from door_csrf_token(). Rules and the
   device conversation: includes/door-lib.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function door_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    door_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    door_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/door-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    door_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!hash_equals(door_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    door_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}
if (!door_can($pdo)) {
    door_out(403, ['status' => 'error', 'msg' => 'You do not have access to Door Control.']);
}
if (!door_ready($pdo)) {
    door_out(503, ['status' => 'error', 'msg' => 'This feature is not set up on this server yet (database update pending).']);
}

$action = (string) ($_POST['action'] ?? '');
$actor  = (string) $_SESSION['id'];
session_write_close();   // the device can take seconds to answer: don't hold up the person's other tabs

try {
    switch ($action) {
    case 'status':
        door_out(200, ['status' => 'ok', 'device' => door_status(), 'log' => door_log_rows($pdo)]);

    case 'enrolled':
        $r = door_enrolled();
        door_out($r['ok'] ? 200 : 422, ['status' => $r['ok'] ? 'ok' : 'error'] + $r);

    case 'unlock':
        $r = door_unlock($pdo, $actor, (string) ($_POST['note'] ?? ''));
        door_out($r['ok'] ? 200 : 422, ['status' => $r['ok'] ? 'ok' : 'error', 'log' => door_log_rows($pdo)] + $r);
    }
    door_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('[wedo door] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    door_out(500, ['status' => 'error', 'msg' => 'Something went wrong. The door was not unlocked. Try again.']);
}
