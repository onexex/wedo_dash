<?php
/* ==========================================================================
   dbbackup-download.php  —  Maintenance > Database Backup: "Back up now".

   POST token=<dbb_csrf_token()> [dl=<nonce>]
     -> the whole database as <db>-backup-YYYY-MM-DD_HHMMSS.sql.gz (attachment)

   Gated by the `dbbackup` access right (includes/db-backup-lib.php) — checked
   here, the page is not the guard. Built and gzipped on the fly straight to
   the browser: nothing is written to disk. Every download goes to dars.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

function dbb_fail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $msg;
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    dbb_fail(401, 'Your session has expired — please sign in again.');
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/db-backup-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    dbb_fail(500, 'Database unavailable.');
}

$me = (string) $_SESSION['id'];
if (!dbb_can_backup($pdo, $me)) {
    dbb_fail(403, 'You do not have access to Database Backup.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !hash_equals(dbb_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    dbb_fail(400, 'This page has expired — reload Database Backup and try again.');
}
if (!function_exists('deflate_init')) {
    dbb_fail(500, 'This server has no zlib support, so a .sql.gz backup cannot be made.');
}
session_write_close();   // a big download must not lock the user's other tabs

try {
    $dump = dbb_connect($servername, $username, $password, $db);
} catch (PDOException $e) {
    dbb_fail(500, 'Database unavailable.');
}

$file = dbb_filename($db);
dbb_log($pdo, $me, DBB_LOG_PREFIX . " downloaded: $file");

// stream: no time limit, no PHP/zlib output buffering in the way
@set_time_limit(0);
@ini_set('zlib.output_compression', 'Off');
while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/gzip');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
// tells the page the download has started (it polls for this cookie to stop its spinner)
$nonce = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['dl'] ?? ''));
if ($nonce !== '') { setcookie('dbb_dl', substr($nonce, 0, 32), ['path' => '/', 'samesite' => 'Lax']); }

$gz  = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
$buf = '';
$write = function (string $chunk) use ($gz, &$buf) {
    $buf .= $chunk;
    if (strlen($buf) >= 262144) {   // compress + send in ~256 KB steps
        echo deflate_add($gz, $buf, ZLIB_NO_FLUSH);
        $buf = '';
        flush();
    }
};

try {
    $done = dbb_dump($dump, $write, $db);
} catch (Throwable $e) {
    // headers are gone already: say so inside the file, which then has no "Dump completed" line
    $write("\n-- !!! BACKUP INCOMPLETE — the database reported an error. Do not use this file.\n");
    try { dbb_log($pdo, $me, DBB_LOG_PREFIX . " FAILED: $file"); } catch (Throwable $ignored) {}
}
echo deflate_add($gz, $buf, ZLIB_FINISH);
flush();
