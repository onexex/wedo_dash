<?php
/* ==========================================================================
   inventory-file.php  —  a photo, scan or receipt of an inventory item.
   GET id=<inv_files id>[&dl=1]
   The `inventory` right opens every file; the holder of an item its photos.
   Pictures and PDFs open in the browser. Files live in uploads/inventory/
   (no web access of their own).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function invf_fail(int $code): void
{
    http_response_code($code);
    header('Cache-Control: no-store');
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { invf_fail(401); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/inventory-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    invf_fail(500);
}
$actor  = (string) $_SESSION['id'];
$manage = inv_can_manage($pdo);
session_write_close();   // a gallery loads several at once: don't hold the session lock

try {
    $f = inv_file_for_viewer($pdo, (int) ($_GET['id'] ?? 0), $actor, $manage);
} catch (Throwable $e) {
    invf_fail(404);   // migration not run
}
if (!$f) { invf_fail($manage ? 404 : 403); }

$path = inv_file_path($f['FileKey']);
if (!$path) { invf_fail(404); }

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$type = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext];
$name = (string) ($f['Name'] ?: 'file.' . $ext);
$ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: 'file';

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . (empty($_GET['dl']) ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
if ($ext !== 'pdf') { header("Content-Security-Policy: default-src 'none'; sandbox"); }   // browsers' PDF viewers break under sandbox
readfile($path);
