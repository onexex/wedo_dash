<?php
/* ==========================================================================
   signature.php  —  the signatories' signature images (assets/images/sign/,
   no direct web access: its .htaccess denies it), served only to people who
   are printing something signed:
     ogm (General Manager)  COE approvers (coefunction), ID card makers
                            (idcard), Debit Advise users (debitadvise), or
                            ?r=<COE request id> that the viewer may print
     elt, kll               Debit Advise users (debitadvise)
   GET s=<name>[&r=<request id>]. Never cached, never sniffed.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function sig_fail(int $code): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $code === 401 ? 'Please sign in.' : 'Not available.';
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { sig_fail(401); }

const SIG_FILES = ['ogm' => 'ogm.png', 'elt' => 'elt.png', 'kll' => 'kll.png'];
$s = (string) ($_GET['s'] ?? '');
if (!isset(SIG_FILES[$s])) { sig_fail(404); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/lifecycle-lib.php';
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sig_fail(500);
}

$ok = lcy_right($pdo, 'debitadvise');
if (!$ok && $s === 'ogm') {
    $ok = lcy_right($pdo, 'coefunction') || lcy_right($pdo, 'idcard');
    if (!$ok && !empty($_GET['r']) && lcy_ready($pdo)) {
        $ok = lcy_coe_can_print($pdo, lcy_coe_get($pdo, (int) $_GET['r']), (string) $_SESSION['id']);
    }
}
session_write_close();
if (!$ok) { sig_fail(403); }

$path = dirname(__DIR__) . '/assets/images/sign/' . SIG_FILES[$s];
if (!is_file($path)) { sig_fail(404); }
header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
