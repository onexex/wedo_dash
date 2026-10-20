<?php
/* ==========================================================================
   applicants-action.php  —  reads + writes for Management > Applicants
   (applicants.php). POST action=..., returns JSON. Every action needs the
   `applicants` access right and the per-session token from apl_csrf_token():
     list, detail, stage, delete, openings, save_opening
     exam_upload (images[] + caption), exam_delete   (exam result pictures)
     hire_options, contract_preview, hire   (+ "Enroll Employee": hiring creates an account)
     mail_status, mail_test (the "Email setup" check + a test email)
   Emails to the applicant (includes/applicants-mail.php, only when Brevo is
   set up): stage with email=1 + emailMessage (Job offer / Not selected only),
   hire with welcome=1 (employee no. + username, never the password) and
   contract=1 (the Trainee / Independent Contractor contract, filled in).
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
require_once __DIR__ . '/../includes/applicants-contract.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    apl_db_clock($pdo);   // applicant times are Manila time (the server MySQL runs on UTC)
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
/** The Hire dialog's fields (agency and HMO are not asked). */
$hireIn = function () use ($p) {
    $d = [];
    foreach (['company', 'department', 'position', 'superior', 'classification', 'hired', 'basic', 'allowance', 'hourly', 'idNumber'] as $k) { $d[$k] = $p($k); }
    return $d;
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

    case 'exam_upload':   // pictures of the exam result, to review later
        $id = (int) $p('id');
        $up = $_FILES['images'] ?? null;
        $files = [];
        if (is_array($up) && is_array($up['name'] ?? null)) {   // images[]: one $_FILES entry per picture
            foreach (array_keys($up['name']) as $i) {
                $files[] = ['name' => $up['name'][$i], 'tmp_name' => $up['tmp_name'][$i], 'size' => $up['size'][$i], 'error' => $up['error'][$i]];
            }
        } elseif (is_array($up)) {
            $files[] = $up;
        }
        $fail(apl_add_exam_files($pdo, $id, $files, $p('caption'), $actor));
        apx_out(200, ['status' => 'ok', 'applicant' => apl_detail($pdo, $id)]);

    case 'exam_delete':
        $id = (int) $p('id');
        $fail(apl_delete_exam_file($pdo, $id, (int) $p('file'), $actor));
        apx_out(200, ['status' => 'ok', 'applicant' => apl_detail($pdo, $id)]);

    case 'openings':
        apx_out(200, ['status' => 'ok', 'openings' => apl_openings($pdo, false)]);

    case 'save_opening':
        $r = apl_save_opening($pdo, (int) $p('id'), $p('title'), $p('location'), $p('description'), $p('open') === '1', $actor);
        $fail($r);
        apx_out(200, ['status' => 'ok', 'id' => $r['id'], 'openings' => apl_openings($pdo, false)]);

    case 'mail_status':   // the "Email setup" check: each step, Brevo's own answer included
        apx_out(200, ['status' => 'ok', 'mail' => wd_mail_diagnose()]);

    case 'mail_test':
        $to = trim($p('to'));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { apx_out(422, ['status' => 'error', 'msg' => 'Enter a valid email address.']); }
        if (!wd_mail_enabled()) {
            apx_out(200, ['status' => 'ok', 'sent' => false, 'error' => 'Email is off on this server — fix the red items above first.']);
        }
        $body = '<p>This is a test from the WeDo Applicant Pool.</p><p>If you can read this, emails to applicants work: '
              . 'application received, job offer, not selected and the welcome on hire.</p>';
        $r = wd_mail_send($to, '', 'WeDo test email', wd_mail_layout('Email works!', $body),
                          "This is a test from the WeDo Applicant Pool.\nIf you can read this, emails to applicants work.");
        lcy_audit($pdo, 'Applicants: test email to ' . $to . ($r['ok'] ? '' : ' failed: ' . mb_substr($r['error'], 0, 120)));
        apx_out(200, ['status' => 'ok', 'sent' => $r['ok'], 'error' => $r['error']]);

    case 'hire_options':
        $needHire();
        apx_out(200, ['status' => 'ok', 'options' => apl_hire_options($pdo) + ['contracts' => apl_contract_info()]]);

    case 'contract_preview':   // the contract as it would be emailed, from the Hire dialog's current values
        $needHire();
        $r = apl_row($pdo, (int) $p('id'));
        if (!$r) { apx_out(404, ['status' => 'error', 'msg' => 'That application no longer exists.']); }
        $chk = apl_hire_values($pdo, $hireIn());
        $fail($chk);
        $c = apl_contract_render($r, $chk['v']);
        $fail($c);
        apx_out(200, ['status' => 'ok', 'title' => $c['title'], 'draft' => $c['draft'], 'page' => apl_contract_page($c)]);

    case 'hire':
        $needHire();
        $id = (int) $p('id');
        $row = apl_row($pdo, $id);   // before hire: the contract uses the registration as it was
        $r = apl_hire($pdo, $id, $hireIn(), $actor);
        $fail($r);
        $a = apl_get($pdo, $id);
        // the welcome email has the employee no. and username — the password only ever goes to HR, below
        $mail = $p('welcome') === '1'
            ? $mailOut($a, $a ? apl_mail_welcome($a, $r['empId'], $r['username'], wd_site_url() . '/login.php') : null)
            : ['mailed' => null, 'mailError' => ''];
        // the Trainee / Independent Contractor contract, filled in from the dialog
        $contract = ['contractMailed' => null, 'contractError' => '', 'contractTitle' => ''];
        if ($p('contract') === '1' && $a && $row) {
            $c = apl_contract_render($row, $r['values'], $r['empId']);
            if ($c['ok']) {
                $m = $mailOut($a, apl_mail_contract($a, $c, $r['values']['hired']));
                $contract = ['contractMailed' => $m['mailed'], 'contractError' => $m['mailError'], 'contractTitle' => $c['title']];
                if ($m['mailed']) { apl_event($pdo, $id, 'hired', 'hired', 'Contract emailed (' . $c['title'] . ')', $actor); }
            } else {
                $contract = ['contractMailed' => false, 'contractError' => $c['msg'], 'contractTitle' => ''];
            }
        }
        // the temporary password is shown once to HR and never stored in plain text
        apx_out(200, ['status' => 'ok', 'empId' => $r['empId'], 'username' => $r['username'], 'password' => $r['password'],
                      'applicant' => apl_detail($pdo, $id)] + $mail + $contract);
    }
} catch (Throwable $e) {
    error_log('[wedo applicants] ' . $action . ': ' . $e->getMessage());
    apx_out(500, ['status' => 'error', 'msg' => 'Something went wrong — please try again.']);
}

apx_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
