<?php
/* ==========================================================================
   applicant-photo.php  —  an applicant's camera photo, for the HR screen.
   GET id=<applicant id>   only with the `applicants` access right. The file
   was re-drawn as a JPEG when it was received (apl_store_photo), and is
   served as an image only (nosniff, sandboxed). Files live in
   uploads/applicants/ (no web access of their own).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function app_fail(int $code): void
{
    http_response_code($code);
    header('Cache-Control: no-store');
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { app_fail(401); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/applicants-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    app_fail(500);
}
$canSee = apl_can_manage($pdo);
session_write_close();   // the list loads many of these at once: don't hold the session lock
if (!$canSee) { app_fail(403); }
if (!apl_has_photo($pdo)) { app_fail(404); }

$st = $pdo->prepare("SELECT PhotoKey FROM applicants WHERE id = :id");
$st->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$path = apl_photo_path($st->fetchColumn());
if (!$path) { app_fail(404); }

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, max-age=600');
readfile($path);
