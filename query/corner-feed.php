<?php
/* ==========================================================================
   corner-feed.php  —  JSON API for the Corner announcements feed
   (corner.php, assets/js/corner-feed.js, includes/corner-lib.php).

   GET  action=more&before=AID              {html, more}   older posts
   POST action=post&token&body[&title]      {html}         new announcement (wd_corner_can_post)
   POST action=edit&token&aid&body          {html}         author or super user
   POST action=delete&token&aid             {}             author or super user

   Errors: {status:"error", msg:"…"} with a 4xx code.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function feed_out($code, array $body) { http_response_code($code); echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit; }

set_exception_handler(function (Throwable $e) {
    $ref = substr(md5(uniqid('', true)), 0, 6);
    error_log('[wedo corner ' . $ref . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['status' => 'error', 'msg' => 'Something went wrong on the server (ref ' . $ref . '). Please try again.']);
    exit;
});

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    feed_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/corner-lib.php';

$me      = (string) $_SESSION['id'];
$tokenOk = hash_equals(wd_corner_csrf_token(), (string) ($_POST['token'] ?? ''));
session_write_close();

try {
    // same connection settings as the page that has always written announcements
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    feed_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}
$root   = dirname(__DIR__);
$action = (string) ($_REQUEST['action'] ?? '');

function feed_row(PDO $pdo, $aid) {
    $st = $pdo->prepare("SELECT a.aid, a.EmpID, a.Title, a.ADesc, a.ADate, e.EmpFN, e.EmpLN,
            (SELECT p.EmpPPath  FROM empprofiles p WHERE p.EmpID = a.EmpID LIMIT 1) AS EmpPPath,
            (SELECT p.EmpGender FROM empprofiles p WHERE p.EmpID = a.EmpID LIMIT 1) AS EmpGender, 0 AS is_new
        FROM announcements a INNER JOIN employees e ON e.EmpID = a.EmpID WHERE a.aid = ?");
    $st->execute([(int) $aid]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* body: 6..1000 characters; title: optional, up to 100 */
function feed_body($s) { return trim(str_replace("\r", '', (string) $s)); }
function feed_check_body($body) {
    $len = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
    if ($len < 6)    { return 'Write at least 6 characters.'; }
    if ($len > 1000) { return 'Keep it under 1,000 characters.'; }
    return null;
}

if ($action === 'more') {
    [$rows, $more] = wd_corner_feed($pdo, $me, (int) ($_GET['before'] ?? 0));
    feed_out(200, ['status' => 'ok', 'html' => wd_corner_feed_html($rows, $me, $root), 'more' => $more]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { feed_out(405, ['status' => 'error', 'msg' => 'Use POST.']); }
if (!$tokenOk) { feed_out(403, ['status' => 'error', 'msg' => 'This page is out of date — reload it and try again.']); }

if ($action === 'post') {
    if (!wd_corner_can_post()) { feed_out(403, ['status' => 'error', 'msg' => 'You can\'t post announcements.']); }
    $body  = feed_body($_POST['body'] ?? '');
    $title = trim(preg_replace('/\s+/', ' ', (string) ($_POST['title'] ?? '')));
    if ($err = feed_check_body($body)) { feed_out(422, ['status' => 'error', 'msg' => $err]); }
    if ((function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title)) > 100) {
        feed_out(422, ['status' => 'error', 'msg' => 'Keep the title under 100 characters.']);
    }
    $pdo->prepare("INSERT INTO announcements (EmpID, Title, ADesc, ADate) VALUES (?, ?, ?, ?)")
        ->execute([$me, $title !== '' ? $title : 'Announcement', $body, date('Y-m-d H:i:s')]);
    $aid = (int) $pdo->lastInsertId();
    require_once __DIR__ . '/../includes/push-lib.php';
    push_announcement($pdo, $me, ($title !== '' ? $title . ': ' : '') . $body);
    feed_out(200, ['status' => 'ok', 'html' => wd_corner_post_card(feed_row($pdo, $aid), $me, $root)]);
}

if ($action === 'edit' || $action === 'delete') {
    $row = feed_row($pdo, $_POST['aid'] ?? 0);
    if (!$row) { feed_out(404, ['status' => 'error', 'msg' => 'That post no longer exists.']); }
    if (!wd_corner_can_edit($row, $me)) { feed_out(403, ['status' => 'error', 'msg' => 'Only the person who posted this can change it.']); }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM annseen WHERE aid = ?")->execute([(int) $row['aid']]);
        $pdo->prepare("DELETE FROM announcements WHERE aid = ?")->execute([(int) $row['aid']]);
        feed_out(200, ['status' => 'ok']);
    }
    $body = feed_body($_POST['body'] ?? '');
    if ($err = feed_check_body($body)) { feed_out(422, ['status' => 'error', 'msg' => $err]); }
    $pdo->prepare("UPDATE announcements SET ADesc = ? WHERE aid = ?")->execute([$body, (int) $row['aid']]);
    feed_out(200, ['status' => 'ok', 'html' => wd_corner_post_card(feed_row($pdo, $row['aid']), $me, $root)]);
}

feed_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
