<?php
/* ==========================================================================
   msgreview-action.php  —  data for Management > Message Review (read-only).

   GET action=list    [emp, q, from, to, type, page]  conversations matching the filters
   GET action=thread  key=MHID [before=MSID]           one conversation's messages
   GET action=people  q                                employees for the "Involving" filter

   Gated by the `msgaudit` access right (includes/msg-review.php) — checked
   here, the page is not the guard.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function mr_out(int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    mr_out(401, ['ok' => false, 'error' => 'Your session has expired — please sign in again.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/msg-review.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    mr_out(500, ['ok' => false, 'error' => 'Database unavailable.']);
}

$me = (string) $_SESSION['id'];
if (!mr_can_review($pdo, $me)) {
    mr_out(403, ['ok' => false, 'error' => 'You do not have access to Message Review.']);
}
session_write_close();

$action = (string) ($_REQUEST['action'] ?? '');

if ($action === 'list') {
    $f = [];
    foreach (['emp', 'q', 'from', 'to', 'type', 'page'] as $k) { $f[$k] = trim((string) ($_GET[$k] ?? '')); }
    mr_out(200, ['ok' => true] + mr_conversations($pdo, $f));
}

if ($action === 'thread') {
    $key    = (string) ($_GET['key'] ?? '');
    $before = (int) ($_GET['before'] ?? 0);
    $t = $key === '' ? null : mr_thread($pdo, $key, $before);
    if (!$t) { mr_out(404, ['ok' => false, 'error' => 'That conversation no longer exists.']); }
    mr_out(200, ['ok' => true] + $t);
}

if ($action === 'people') {
    mr_out(200, ['ok' => true, 'people' => mr_people($pdo, (string) ($_GET['q'] ?? ''))]);
}

mr_out(400, ['ok' => false, 'error' => 'Unknown action.']);
