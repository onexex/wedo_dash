<?php
/* ==========================================================================
   Query-devicetoken.php  —  JSON API the WeDo mobile app uses to (un)register
   the phone for push notifications (see includes/push-lib.php).

   POST action=register&device=FCM_TOKEN&platform=android|ios&token=CSRF
   POST action=unregister&device=FCM_TOKEN&token=CSRF     (before signing out)

   CSRF token = msg_csrf_token(), exposed on every signed-in page as WD_CALL.token.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function dt_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    dt_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dt_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/messages-lib.php';
require_once __DIR__ . '/../includes/push-lib.php';

$me      = (string) $_SESSION['id'];
$tokenOk = hash_equals(msg_csrf_token(), (string) ($_POST['token'] ?? ''));
session_write_close();
if (!$tokenOk) { dt_out(419, ['status' => 'error', 'msg' => 'Page expired — please reload.']); }

$device = trim((string) ($_POST['device'] ?? ''));
if ($device === '' || strlen($device) > 255 || !preg_match('/^[A-Za-z0-9_:\-\.]+$/', $device)) {
    dt_out(422, ['status' => 'error', 'msg' => 'Invalid device token.']);
}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'register') {
        push_register($pdo, $me, $device, (string) ($_POST['platform'] ?? 'android'));
        dt_out(200, ['status' => 'ok', 'push' => push_enabled()]);
    }
    if ($action === 'unregister') {
        push_unregister($pdo, $me, $device);
        dt_out(200, ['status' => 'ok']);
    }
    dt_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('[wedo devicetoken] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    dt_out(500, ['status' => 'error', 'msg' => 'Could not save this device.']);
}
