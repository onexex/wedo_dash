<?php
/* ==========================================================================
   msgreview-action.php  —  data for Management > Message Review (read-only).

   GET action=list    [emp, q, from, to, type, page]  conversations matching the filters
   GET action=thread  key=MHID [before=MSID]           one conversation's messages
   GET action=people  q                                employees for the "Involving" filter
   GET action=log                                      who reviewed what, newest first
   POST action=print  key=MHID token                   records that a transcript was printed

   Gated by the `msgaudit` access right (includes/msg-review.php) — checked
   here, the page is not the guard. Opening a conversation, searching and
   printing are recorded in msg_review_log.
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
$token = mr_csrf_token();
session_write_close();

$action = (string) ($_REQUEST['action'] ?? '');

if ($action === 'list') {
    $f = [];
    foreach (['emp', 'q', 'from', 'to', 'type', 'page'] as $k) { $f[$k] = trim((string) ($_GET[$k] ?? '')); }
    $res = mr_conversations($pdo, $f);
    if ((int) $f['page'] === 0 && ($f['q'] !== '' || $f['emp'] !== '')) {
        $bits = [];
        if ($f['emp'] !== '') { $bits[] = 'involving ' . mr_person($pdo, $f['emp'])['name']; }
        if ($f['q'] !== '')   { $bits[] = 'containing "' . $f['q'] . '"'; }
        if ($f['from'] !== '' || $f['to'] !== '') { $bits[] = 'dated ' . ($f['from'] ?: '…') . ' to ' . ($f['to'] ?: '…'); }
        mr_log($pdo, $me, 'search', null, implode(', ', $bits));
    }
    mr_out(200, ['ok' => true] + $res);
}

if ($action === 'thread') {
    $key    = (string) ($_GET['key'] ?? '');
    $before = (int) ($_GET['before'] ?? 0);
    $t = $key === '' ? null : mr_thread($pdo, $key, $before);
    if (!$t) { mr_out(404, ['ok' => false, 'error' => 'That conversation no longer exists.']); }
    if ($before === 0) { mr_log($pdo, $me, 'open', $key); }
    mr_out(200, ['ok' => true] + $t);
}

if ($action === 'people') {
    mr_out(200, ['ok' => true, 'people' => mr_people($pdo, (string) ($_GET['q'] ?? ''))]);
}

if ($action === 'log') {
    mr_out(200, ['ok' => true, 'log' => mr_log_list($pdo)]);
}

if ($action === 'print' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        mr_out(419, ['ok' => false, 'error' => 'This page has expired — reload it and try again.']);
    }
    $key = (string) ($_POST['key'] ?? '');
    if ($key === '' || !mr_conversation($pdo, $key)) { mr_out(404, ['ok' => false, 'error' => 'That conversation no longer exists.']); }
    mr_log($pdo, $me, 'print', $key);
    mr_out(200, ['ok' => true]);
}

mr_out(400, ['ok' => false, 'error' => 'Unknown action.']);
