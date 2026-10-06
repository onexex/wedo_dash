<?php
/* ==========================================================================
   msg-file.php  —  open / download a picture or document sent in Messages.

   GET id=MSID[&dl=1]   only for people in that conversation (includes/msg-files.php)
   Pictures and PDFs open in the browser; other documents (and dl=1) download.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function mf_fail(int $code, string $text): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $text;
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { mf_fail(401, 'Please sign in again.'); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/msg-files.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    mf_fail(500, 'Database unavailable.');
}
session_write_close();   // a large download must not lock the session for other requests

$f = mf_for_viewer($pdo, (string) $_SESSION['id'], (int) ($_GET['id'] ?? 0));
if (!$f) { mf_fail(404, 'This file isn’t available.'); }

$path = mf_dir() . '/' . $f['card']['k'];
if (!is_file($path)) { mf_fail(404, 'This file is no longer on the server.'); }

$ext    = $f['card']['ext'];
$type   = mf_types()[$ext][1];
$inline = empty($_GET['dl']) && ($f['kind'] === 'image' || $ext === 'pdf');
$name   = (string) $f['card']['n'];
$ascii  = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: 'file';

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
if ($ext !== 'pdf') { header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox"); }
readfile($path);
