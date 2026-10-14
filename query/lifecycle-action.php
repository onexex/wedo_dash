<?php
/* ==========================================================================
   lifecycle-action.php  —  reads + writes for the employee lifecycle pages:
   separation.php, coe.php and verify.php. POST action=..., returns JSON.

   Every action checks its own access right (the pages are not the guard)
   plus the per-session token from lcy_csrf_token():
     emp_search                       separation | coefunction | empverify
     timeline, separate, sep_update,
     sep_open                         separation
     coe_mine, coe_request, coe_cancel   any signed-in employee (own requests)
     coe_queue, coe_issue, coe_decide    coefunction
     coe_cert, coe_printed               the employee (own, approved) or coefunction
     verify_lookup, verify_log,
     verify_recent, coe_check         empverify
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');

function lcy_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    lcy_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lcy_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/lifecycle-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    lcy_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!hash_equals(lcy_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    lcy_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}
if (!lcy_ready($pdo)) {
    lcy_out(503, ['status' => 'error', 'msg' => 'This feature is not set up on this server yet (database update pending).']);
}

$action = (string) ($_POST['action'] ?? '');
$actor  = (string) $_SESSION['id'];
$p = function ($k) { return (string) ($_POST[$k] ?? ''); };
$deny = function ($what) { lcy_out(403, ['status' => 'error', 'msg' => "You do not have access to $what."]); };
$result = function (array $r, array $extra = []) {
    if (!$r['ok']) { lcy_out(422, ['status' => 'error', 'msg' => $r['msg']]); }
    lcy_out(200, ['status' => 'ok'] + $extra + $r);
};

try {
    switch ($action) {

    /* ---------------------------------------------------------- shared */
    case 'emp_search':
        if (!lcy_can_separate($pdo) && !lcy_can_issue_coe($pdo) && !lcy_can_verify($pdo)) { $deny('employee search'); }
        lcy_out(200, ['status' => 'ok', 'employees' => lcy_find_employees($pdo, $p('q'))]);

    /* ---------------------------------------------------------- separation */
    case 'timeline':
        if (!lcy_can_separate($pdo)) { $deny('Separation & Lifecycle'); }
        $t = lcy_timeline($pdo, $p('emp'));
        if (!$t) { lcy_out(404, ['status' => 'error', 'msg' => 'That employee no longer exists.']); }
        lcy_out(200, ['status' => 'ok'] + $t);

    case 'sep_open':
        if (!lcy_can_separate($pdo)) { $deny('Separation & Lifecycle'); }
        lcy_out(200, ['status' => 'ok', 'open' => lcy_separations($pdo, null, true)]);

    case 'separate':
        if (!lcy_can_separate($pdo)) { $deny('Separation & Lifecycle'); }
        $result(lcy_separate($pdo, $p('emp'), $p('type'), $p('lastDay'), $p('reason'), $p('rehire'),
                             $p('rehireNote'), $p('clearance') === '1', $actor));

    case 'sep_update':
        if (!lcy_can_separate($pdo)) { $deny('Separation & Lifecycle'); }
        $result(lcy_update_separation($pdo, (int) $p('id'), $p('lastDay'), $p('reason'), $p('rehire'),
                                      $p('rehireNote'), $p('clearance') === '1', $actor));

    /* ---------------------------------------------------------- COE */
    case 'coe_mine':
        lcy_out(200, ['status' => 'ok', 'requests' => lcy_coe_list($pdo, ['emp' => $actor])]);

    case 'coe_request':
        $r = lcy_coe_request($pdo, $actor, $p('purpose'), $p('salary') === '1', false, $actor);
        if ($r['ok']) {
            require_once __DIR__ . '/../includes/push-lib.php';
            if (push_enabled()) { push_coe_filed($pdo, $actor, lcy_coe_approvers($pdo)); }
        }
        $result($r);

    case 'coe_cancel':
        $result(lcy_coe_cancel($pdo, (int) $p('id'), $actor));

    case 'coe_queue':
        if (!lcy_can_issue_coe($pdo)) { $deny('the COE request queue'); }
        $status = in_array($p('status'), ['pending', 'approved', 'rejected', 'cancelled'], true) ? $p('status') : '';
        lcy_out(200, ['status' => 'ok', 'requests' => lcy_coe_list($pdo, ['status' => $status]),
                      'pending' => lcy_coe_pending_count($pdo)]);

    case 'coe_issue':
        if (!lcy_can_issue_coe($pdo)) { $deny('issuing COEs'); }
        $result(lcy_coe_request($pdo, $p('emp'), $p('purpose'), $p('salary') === '1', true, $actor));

    case 'coe_decide':
        if (!lcy_can_issue_coe($pdo)) { $deny('approving COE requests'); }
        $r = lcy_coe_decide($pdo, (int) $p('id'), $p('decision'), $p('remarks'), $actor);
        if ($r['ok']) {
            require_once __DIR__ . '/../includes/push-lib.php';
            if (push_enabled()) { push_coe_decided($pdo, $r['request']['empId'], $p('decision'), $actor, $p('remarks')); }
        }
        $result($r);

    case 'coe_cert':
    case 'coe_printed':
        $req = lcy_coe_get($pdo, (int) $p('id'));
        if (!lcy_coe_can_print($pdo, $req, $actor)) {
            lcy_out(403, ['status' => 'error', 'msg' => 'This certificate is not available to you.']);
        }
        if ($action === 'coe_printed') {
            lcy_coe_mark_printed($pdo, $req, $actor);
            lcy_out(200, ['status' => 'ok']);
        }
        $cert = lcy_coe_certificate($pdo, $req);
        if (!$cert) { lcy_out(404, ['status' => 'error', 'msg' => 'That employee no longer exists.']); }
        lcy_out(200, ['status' => 'ok', 'cert' => $cert]);

    /* ---------------------------------------------------------- verification */
    case 'verify_lookup':
        if (!lcy_can_verify($pdo)) { $deny('Employment Verification'); }
        lcy_out(200, ['status' => 'ok'] + lcy_verify_lookup($pdo, $p('q')));

    case 'coe_check':
        if (!lcy_can_verify($pdo)) { $deny('Employment Verification'); }
        lcy_out(200, ['status' => 'ok', 'coe' => lcy_coe_check($pdo, $p('controlNo'))]);

    case 'verify_recent':
        if (!lcy_can_verify($pdo)) { $deny('Employment Verification'); }
        lcy_out(200, ['status' => 'ok', 'calls' => lcy_verifications($pdo, null, null, 30)]);

    case 'verify_log':
        if (!lcy_can_verify($pdo)) { $deny('Employment Verification'); }
        $result(lcy_log_verification($pdo, [
            'emp' => $p('emp'), 'ams' => $p('ams'), 'caller' => $p('caller'), 'company' => $p('company'),
            'contact' => $p('contact'), 'purpose' => $p('purpose'), 'consent' => $p('consent') === '1',
            'disclosed' => (array) ($_POST['disclosed'] ?? []), 'notes' => $p('notes'),
        ], $actor));
    }
} catch (Throwable $e) {
    error_log('[wedo lifecycle] ' . $action . ': ' . $e->getMessage());
    lcy_out(500, ['status' => 'error', 'msg' => 'Something went wrong — please try again.']);
}

lcy_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
