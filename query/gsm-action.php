<?php
/* ==========================================================================
   gsm-action.php  —  reads + edits for gsm.php (Management > SIM Inbox).
   POST action=refresh|messages|save_line, returns JSON. Every action needs the
   `gsminbox` right and the per-session token from gsm_csrf_token().
   Rules: includes/gsm-lib.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function gsm_out($code, array $body) { http_response_code($code); echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    gsm_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gsm_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/gsm-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    gsm_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!hash_equals(gsm_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    gsm_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}
if (!gsm_can($pdo)) {
    gsm_out(403, ['status' => 'error', 'msg' => 'You do not have access to SIM Inbox.']);
}
if (!gsm_ready($pdo)) {
    gsm_out(503, ['status' => 'error', 'msg' => 'This feature is not set up on this server yet (database update pending).']);
}

$action = (string) ($_POST['action'] ?? '');
$actor  = (string) $_SESSION['id'];
session_write_close();

$filters = [
    'line'    => (int) ($_POST['line'] ?? 0),
    'q'       => (string) ($_POST['q'] ?? ''),
    'carrier' => ($_POST['carrier'] ?? '') === '1',
    'before'  => (int) ($_POST['before'] ?? 0),
    'after'   => (int) ($_POST['after'] ?? 0),
    'limit'   => 50,
];

try {
    switch ($action) {
    case 'refresh':      // lines + collectors, and texts newer than `after`
        gsm_out(200, ['status' => 'ok', 'lines' => gsm_lines_view($pdo), 'collectors' => gsm_collectors_view($pdo),
                      'messages' => $filters['after'] > 0 ? gsm_messages($pdo, ['before' => 0] + $filters) : []]);

    case 'messages':     // a fresh list, or older ones with `before`
        gsm_out(200, ['status' => 'ok', 'messages' => gsm_messages($pdo, ['after' => 0] + $filters)]);

    case 'save_line':
        $r = gsm_save_line($pdo, $actor, (int) ($_POST['line'] ?? 0), (string) ($_POST['label'] ?? ''), (string) ($_POST['number'] ?? ''),
                           (string) ($_POST['expires'] ?? ''), (string) ($_POST['balance'] ?? ''));
        gsm_out($r['ok'] ? 200 : 422, ['status' => $r['ok'] ? 'ok' : 'error', 'lines' => gsm_lines_view($pdo)] + $r);
    }
    gsm_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('[wedo gsm] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    gsm_out(500, ['status' => 'error', 'msg' => 'Something went wrong. Try again.']);
}
