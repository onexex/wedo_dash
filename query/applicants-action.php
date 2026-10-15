<?php
/* ==========================================================================
   applicants-action.php  —  reads + writes for Management > Applicants
   (applicants.php). POST action=..., returns JSON. Every action needs the
   `applicants` access right and the per-session token from apl_csrf_token():
     list, detail, stage, delete, openings, save_opening
     hire_options, hire     (+ "Enroll Employee": hiring creates an account)
   Emails to the applicant (includes/applicants-mail.php, only when Brevo is
   set up): stage with email=1 + emailMessage (Job offer / Not selected only),
   hire with welcome=1 (employee no. + username, never the password).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');   // applicant personal data: never cached

function apx_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    apx_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { apx_out(405, ['status' => 'error', 'msg' => 'POST only.']); }

include 'w_conn.php';
require_once __DIR__ . '/../includes/applicants-lib.php';
require_once __DIR__ . '/../includes/applicants-mail.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    apx_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!apl_can_manage($pdo)) { apx_out(403, ['status' => 'error', 'msg' => 'You do not have access to Applicants.']); }
if (!hash_equals(apl_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    apx_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}
if (!apl_ready($pdo)) { apx_out(503, ['status' => 'error', 'msg' => 'Applicants is not set up on this server yet (database update pending).']); }

$action = (string) ($_POST['action'] ?? '');
$actor  = (string) $_SESSION['id'];
$p = function ($k) { return (string) ($_POST[$k] ?? ''); };
$fail = function (array $r) { if (!$r['ok']) { apx_out(422, ['status' => 'error', 'msg' => $r['msg']]); } };
/** Send one email to the applicant; never fails the request. @return array{mailed:?bool, mailError:string} */
$mailOut = function (?array $a, ?array $m) {
    if (!$a || !$m || !wd_mail_enabled()) { return ['mailed' => null, 'mailError' => '']; }
    try { $r = apl_mail_send($a, $m); } catch (Throwable $e) { $r = ['ok' => false, 'error' => 'Unexpected error.']; error_log('[wedo applicants] mail: ' . $e->getMessage()); }
    return ['mailed' => $r['ok'], 'mailError' => $r['ok'] ? '' : $r['error']];
};
$needHire = function () use ($pdo) {
    if (!apl_can_hire($pdo)) { apx_out(403, ['status' => 'error', 'msg' => 'Hiring creates a WeDo account — it needs the "Enroll Employee" right too.']); }
};

try {
    switch ($action) {
    case 'list':
        apx_out(200, ['status' => 'ok', 'applicants' => apl_list($pdo, ['stage' => $p('stage'), 'opening' => $p('opening'), 'q' => $p('q')]),
                      'counts' => apl_stage_counts($pdo)]);

    case 'detail':
        $a = apl_detail($pdo, (int) $p('id'));
        if (!$a) { apx_out(404, ['status' => 'error', 'msg' => 'That application no longer exists.']); }
        lcy_audit($pdo, 'Applicant #' . $a['id'] . ' viewed');
        apx_out(200, ['status' => 'ok', 'applicant' => $a]);

    case 'stage':
        $id = (int) $p('id');
        $before = apl_get($pdo, $id);
        $fail(apl_set_stage($pdo, $id, $p('stage'), $p('notes'), $actor));
        $mail = ['mailed' => null, 'mailError' => ''];
        // only a real move to Job offer / Not selected emails the applicant, and only when HR asked
        if ($p('email') === '1' && $before && $before['stage'] !== $p('stage') && in_array($p('stage'), APL_MAIL_STAGES, true)) {
            $a = apl_get($pdo, $id);
            $msg = mb_substr(trim($p('emailMessage')), 0, 2000);
            $mail = $mailOut($a, apl_mail_for_stage($a, $p('stage'), $msg));
            if ($mail['mailed']) {
                apl_event($pdo, $id, $p('stage'), $p('stage'), 'Applicant emailed (' . APL_STAGES[$p('stage')] . ')' . ($msg !== '' ? ': ' . $msg : ''), $actor);
            }
        }
        apx_out(200, ['status' => 'ok', 'applicant' => apl_detail($pdo, $id)] + $mail);

    case 'delete':
        $fail(apl_delete($pdo, (int) $p('id'), $actor));
        apx_out(200, ['status' => 'ok']);

    case 'openings':
        apx_out(200, ['status' => 'ok', 'openings' => apl_openings($pdo, false)]);

    case 'save_opening':
        $r = apl_save_opening($pdo, (int) $p('id'), $p('title'), $p('location'), $p('description'), $p('open') === '1', $actor);
        $fail($r);
        apx_out(200, ['status' => 'ok', 'id' => $r['id'], 'openings' => apl_openings($pdo, false)]);

    case 'hire_options':
        $needHire();
        apx_out(200, ['status' => 'ok', 'options' => apl_hire_options($pdo)]);

    case 'hire':
        $needHire();
        $r = apl_hire($pdo, (int) $p('id'), [
            'company' => $p('company'), 'department' => $p('department'), 'position' => $p('position'), 'superior' => $p('superior'),
            'agency' => $p('agency'), 'hmo' => $p('hmo'), 'hmoNumber' => $p('hmoNumber'), 'classification' => $p('classification'),
            'hired' => $p('hired'), 'basic' => $p('basic'), 'allowance' => $p('allowance'), 'hourly' => $p('hourly'),
            'idNumber' => $p('idNumber'),
        ], $actor);
        $fail($r);
        // the welcome email has the employee no. and username — the password only ever goes to HR, below
        $mail = $p('welcome') === '1'
            ? $mailOut($a = apl_get($pdo, (int) $p('id')), $a ? apl_mail_welcome($a, $r['empId'], $r['username'], wd_site_url() . '/login.php') : null)
            : ['mailed' => null, 'mailError' => ''];
        // the temporary password is shown once to HR and never stored in plain text
        apx_out(200, ['status' => 'ok', 'empId' => $r['empId'], 'username' => $r['username'], 'password' => $r['password'],
                      'applicant' => apl_detail($pdo, (int) $p('id'))] + $mail);
    }
} catch (Throwable $e) {
    error_log('[wedo applicants] ' . $action . ': ' . $e->getMessage());
    apx_out(500, ['status' => 'error', 'msg' => 'Something went wrong — please try again.']);
}

apx_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
