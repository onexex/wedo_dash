<?php
/* ==========================================================================
   subscriptions-daily.php  —  sends the day's renewal reminders
   (Subscriptions). Run by cron at 8:00 AM Manila; see
   tools/subscriptions-cron.md for the cPanel line.

     php tools/subscriptions-daily.php            send today's reminders (once a day)
     php tools/subscriptions-daily.php --dry-run  show who would get what; sends nothing

   Safe to run more than once: a day that already went out is skipped, and
   each person gets at most one reminder a day. If this never runs, the first
   page load after 8:00 AM sends them instead (includes/wd-header.php).
   Command line only.
   ========================================================================== */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Manila');

require dirname(__DIR__) . '/w_conn.php';
require_once dirname(__DIR__) . '/includes/subscriptions-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

$dry = in_array('--dry-run', $argv, true);
$r = sub_run_daily($pdo, 'cron', null, $dry);
$stamp = date('Y-m-d H:i');
switch ($r['status']) {
    case 'sent':
        echo "$stamp  reminders sent to {$r['people']} people ({$r['entries']} renewals)\n";
        break;
    case 'done':
        echo "$stamp  today's reminders already went out\n";
        break;
    case 'busy':
        echo "$stamp  another run is sending right now\n";
        break;
    case 'off':
        fwrite(STDERR, "$stamp  Subscriptions is not set up here: run sql/2026-11-09-add-subscriptions.sql\n");
        exit(1);
    case 'dry-run':
        echo "$stamp  dry run: {$r['people']} people would be reminded\n";
        foreach ($r['digests'] as $emp => $t) { echo "  $emp: {$t[0]} — {$t[1]}\n"; }
        break;
}
exit(0);
