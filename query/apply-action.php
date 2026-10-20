<?php
/* ==========================================================================
   apply-action.php  —  receives the PUBLIC job application (apply.php).
   No sign-in. POST multipart: the form fields + resume + camera photo, returns JSON.
   Emails the applicant a confirmation when Brevo is set up (includes/applicants-mail.php).
   Guards (includes/applicants-lib.php apl_submit): the form's session token,
   a hidden honeypot field, a minimum fill time, per-address and overall
   hourly limits, Cloudflare Turnstile when configured, one application per
   email + position per 30 days, strict field validation, PDF/Word resumes
   only (content checked, random file name, no web access).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function apa_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { apa_out(405, ['status' => 'error', 'msg' => 'POST only.']); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/applicants-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    apl_db_clock($pdo);   // applicant times are Manila time (the server MySQL runs on UTC)
} catch (PDOException $e) {
    apa_out(500, ['status' => 'error', 'msg' => 'We could not receive applications right now — please try again later.']);
}
if (!apl_ready($pdo)) { apa_out(503, ['status' => 'error', 'msg' => 'We are not accepting applications online yet.']); }

try {
    $r = apl_submit($pdo, $_POST, $_FILES['resume'] ?? null, apl_client_ip(), true, $_FILES['photo'] ?? null);
} catch (Throwable $e) {
    error_log('[wedo apply] ' . $e->getMessage());
    apa_out(500, ['status' => 'error', 'msg' => 'Something went wrong — please try again.']);
}
if (!$r['ok']) { apa_out($r['code'] ?? 422, ['status' => 'error', 'msg' => $r['msg']]); }

if ($r['id'] > 0) {
    try {
        require_once __DIR__ . '/../includes/push-lib.php';
        if (push_enabled()) { push_applicant_filed($pdo, apl_get($pdo, $r['id']), apl_reviewers($pdo)); }
    } catch (Throwable $e) { /* a notification never blocks the application */ }
}
// a reference to quote when calling HR; the honeypot gets a plausible fake so a bot can't tell
$ref = 'APL-' . sprintf('%06d', $r['id'] > 0 ? $r['id'] : random_int(1000, 999999));
$mailed = false;
if ($r['id'] > 0) {
    try {   // "we received your application" — a failed email never blocks the application
        require_once __DIR__ . '/../includes/applicants-mail.php';
        $mailed = apl_mail_after_submit($pdo, $r['id'], $ref);
    } catch (Throwable $e) { error_log('[wedo apply] mail: ' . $e->getMessage()); }
}
apa_out(200, ['status' => 'ok', 'ref' => $ref, 'mailed' => $mailed,
              'msg' => 'Thank you! Your application was received. We will contact you by email or phone.']);
