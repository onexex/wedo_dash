<?php
/* ==========================================================================
   applicant-file.php  —  one exam result image of an application, for HR.
   GET id=<applicant_files id>   only with the `applicants` access right.
   The image was re-drawn as a JPEG when it was uploaded (apl_store_image)
   and is served as an image only (nosniff, sandboxed). Files live in
   uploads/applicants/ (no web access of their own).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function apf_fail(int $code): void
{
    http_response_code($code);
    header('Cache-Control: no-store');
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { apf_fail(401); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/applicants-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    apf_fail(500);
}
$canSee = apl_can_manage($pdo);
session_write_close();   // a gallery loads several at once: don't hold the session lock
if (!$canSee) { apf_fail(403); }

$path = apl_exam_file_path($pdo, (int) ($_GET['id'] ?? 0));
if (!$path) { apf_fail(404); }

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, max-age=600');
readfile($path);
