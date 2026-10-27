<?php
/* ==========================================================================
   dbbackup-file.php  —  Maintenance > Database Backup: download a kept copy.

   GET f=<file name as listed on the page>
     -> that .sql.gz from uploads/backups/ (attachment)

   Gated by the `dbbackup` access right (includes/db-backup-lib.php). Only
   names dbb_filename() makes are accepted (no paths), and only files that are
   still in the folder: query/dbbackup-download.php keeps the last dbb_keep()
   backups there. Every download goes to dars.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function dbf_fail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $msg;
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    dbf_fail(401, 'Your session has expired — please sign in again.');
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/db-backup-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    dbf_fail(500, 'Database unavailable.');
}

$me = (string) $_SESSION['id'];
if (!dbb_can_backup($pdo, $me)) {
    dbf_fail(403, 'You do not have access to Database Backup.');
}
session_write_close();   // a big download must not lock the user's other tabs

$name = basename((string) ($_GET['f'] ?? ''));
$path = dbb_file_path($name);
if ($path === null) {
    dbf_fail(404, 'This backup is no longer on the server. Only the most recent ones are kept — make a new backup instead.');
}

dbb_log($pdo, $me, DBB_LOG_PREFIX . " re-downloaded: $name");

@set_time_limit(0);
@ini_set('zlib.output_compression', 'Off');
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/gzip');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($path);
