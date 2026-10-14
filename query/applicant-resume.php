<?php
/* ==========================================================================
   applicant-resume.php  —  open / download an applicant's resume.
   GET id=<applicant id>   only with the `applicants` access right; always a
   download (sandboxed, nosniff), and each one is written to the activity log.
   Files live in uploads/applicants/ (no web access of their own).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function apr_fail(int $code, string $text): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $text;
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { apr_fail(401, 'Please sign in again.'); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/applicants-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    apr_fail(500, 'Database unavailable.');
}
$canSee = apl_can_manage($pdo);
session_write_close();   // a large download must not lock the session for other requests
if (!$canSee) { apr_fail(403, 'You do not have access to Applicants.'); }

$st = $pdo->prepare("SELECT ResumeKey, ResumeName FROM applicants WHERE id = :id");
$st->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$r = $st->fetch(PDO::FETCH_ASSOC);
$path = $r ? apl_resume_path($r['ResumeKey']) : null;
if (!$path) { apr_fail(404, 'This resume isn’t available.'); }

$ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['pdf' => 'application/pdf', 'doc' => 'application/msword',
          'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$name  = (string) ($r['ResumeName'] ?: 'resume.' . $ext);
$ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?: 'resume';

lcy_audit($pdo, 'Applicant #' . (int) $_GET['id'] . ' resume downloaded');

// files from the public are always downloaded, never rendered on WeDo's own origin
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store');
readfile($path);
