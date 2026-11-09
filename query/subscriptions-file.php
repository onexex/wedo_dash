<?php
/* ==========================================================================
   subscriptions-file.php  —  a receipt or document of a subscription.
   GET id=<sub_files id>[&dl=1]. Needs the `subscriptions` right and the
   item's company in the viewer's scope. Files live in uploads/subscriptions/
   (no web access of their own).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function subf_fail(int $code): void
{
    http_response_code($code);
    header('Cache-Control: no-store');
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { subf_fail(401); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/subscriptions-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    subf_fail(500);
}
if (!sub_can_manage($pdo)) { subf_fail(403); }
$scope = sub_scope($pdo);
session_write_close();

try {
    $f = sub_file_get($pdo, (int) ($_GET['id'] ?? 0), $scope);
} catch (Throwable $e) {
    subf_fail(404);   // migration not run
}
if (!$f) { subf_fail(404); }
$path = sub_file_path($f['FileKey']);
if (!$path) { subf_fail(404); }

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$type = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'][$ext];
$name = (string) ($f['Name'] ?: 'file.' . $ext);
$ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: 'file';

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . (empty($_GET['dl']) ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
if ($ext !== 'pdf') { header("Content-Security-Policy: default-src 'none'; sandbox"); }
readfile($path);
