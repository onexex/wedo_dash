<?php
/* ==========================================================================
   msgreview-file.php  —  open / download a picture or document for
   Management > Message Review, including the kept original of a deleted one.

   GET id=MSID[&dl=1]   only with the `msgaudit` access right; opening a file
   is recorded in msg_review_log. &t=1 = a thumbnail inside a transcript, which
   is covered by that conversation's own "open" entry. Chat members use
   query/msg-file.php instead.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function mrf_fail(int $code, string $text): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $text;
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { mrf_fail(401, 'Please sign in again.'); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/msg-review.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    mrf_fail(500, 'Database unavailable.');
}
$me = (string) $_SESSION['id'];
session_write_close();   // a large download must not lock the session for other requests

if (!mr_can_review($pdo, $me)) { mrf_fail(403, 'You do not have access to Message Review.'); }

$id = (int) ($_GET['id'] ?? 0);
$f  = mr_file($pdo, $id);
if (!$f) { mrf_fail(404, 'This file isn’t available.'); }

$path = mf_dir() . '/' . $f['card']['k'];
if (!is_file($path)) { mrf_fail(404, 'This file is no longer on the server.'); }

$name = (string) $f['card']['n'];
if (empty($_GET['t'])) { mr_log($pdo, $me, 'file', $f['mhid'], $name . ' (message #' . $id . ')'); }

$ext    = $f['card']['ext'];
$type   = mf_types()[$ext][1];
$inline = empty($_GET['dl']) && ($f['kind'] === 'image' || $ext === 'pdf');
$ascii  = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: 'file';

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if ($ext !== 'pdf') { header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox"); }
readfile($path);
