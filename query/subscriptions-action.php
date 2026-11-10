<?php
/* ==========================================================================
   subscriptions-action.php  —  reads + writes for subscriptions.php.
   POST action=..., returns JSON. Every action needs the `subscriptions` right
   and the per-session token from sub_csrf_token(). Items outside the viewer's
   company are invisible unless they are a super user (sub_scope()).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function sub_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    sub_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sub_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}
if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    sub_out(413, ['status' => 'error', 'msg' => 'That file is too large for the server.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/subscriptions-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    sub_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!hash_equals(sub_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    sub_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}
if (!sub_ready($pdo)) {
    sub_out(503, ['status' => 'error', 'msg' => 'This feature is not set up on this server yet (database update pending).']);
}
if (!sub_can_manage($pdo)) {
    sub_out(403, ['status' => 'error', 'msg' => 'You do not have access to Subscriptions.']);
}

$action = (string) ($_POST['action'] ?? '');
$actor  = (string) $_SESSION['id'];
$scope  = sub_scope($pdo);
$p = function ($k) { return (string) ($_POST[$k] ?? ''); };
$result = function (array $r) {
    if (!$r['ok']) { sub_out(422, ['status' => 'error', 'msg' => $r['msg']]); }
    sub_out(200, ['status' => 'ok'] + $r);
};
$file = function ($k) { return isset($_FILES[$k]) && is_array($_FILES[$k]) ? $_FILES[$k] : null; };

try {
    switch ($action) {
    case 'lookups':
        sub_out(200, ['status' => 'ok'] + sub_lookups($pdo, $scope));

    case 'overview':
        sub_out(200, ['status' => 'ok'] + sub_overview($pdo, sub_company_filter($scope, $p('company'))));

    case 'item':
        $d = sub_item_detail($pdo, (int) $p('id'), $scope);
        if (!$d) { sub_out(404, ['status' => 'error', 'msg' => 'That subscription no longer exists.']); }
        sub_out(200, ['status' => 'ok', 'item' => $d]);

    case 'reminders':
        $mine = sub_my_due($pdo, $actor);
        [$title, $body] = sub_digest_text($mine);
        sub_out(200, ['status' => 'ok', 'mine' => ['entries' => $mine, 'title' => $title, 'body' => $body]] + sub_reminder_log($pdo, $scope));

    case 'item_save':
        $d = $_POST;
        $d['watchers'] = isset($_POST['watchers']) ? (array) $_POST['watchers'] : [];
        $result(sub_item_save($pdo, $d, $scope, $actor));

    // logins vault: a second right on top of `subscriptions`
    case 'cred_reveal':
    case 'cred_save':
    case 'cred_delete':
    case 'cred_import':
        if (!sub_can_creds($pdo)) { sub_out(403, ['status' => 'error', 'msg' => 'You do not have access to saved logins.']); }
        if ($action === 'cred_import') {
            $groups = json_decode($p('groups'), true);
            if (!is_array($groups)) { sub_out(422, ['status' => 'error', 'msg' => 'The import could not be read. Paste it again.']); }
            $result(sub_cred_import($pdo, $groups, $p('companyId'), $scope, $actor));
        }
        if ($action === 'cred_reveal') { $result(sub_cred_reveal($pdo, (int) $p('id'), $p('why'), $scope, $actor)); }
        if ($action === 'cred_save') { $result(sub_cred_save($pdo, $_POST, $scope, $actor)); }
        $result(sub_cred_delete($pdo, (int) $p('id'), $scope, $actor));

    case 'item_cancel':
        $result(sub_item_status($pdo, (int) $p('id'), 'cancelled', $scope, $actor));

    case 'item_resume':
        $result(sub_item_status($pdo, (int) $p('id'), 'active', $scope, $actor));

    case 'item_delete':
        $result(sub_item_delete($pdo, (int) $p('id'), $scope));

    case 'renew':
        $result(sub_renew($pdo, (int) $p('id'), (int) $p('reqId') ?: null, $_POST, $file('file'), $scope, $actor));

    case 'req_save':
        $result(sub_req_save($pdo, $_POST, $scope, $actor));

    case 'req_delete':
        $result(sub_req_delete($pdo, (int) $p('reqId'), $scope));

    case 'file_add':
        $f = $file('file');
        if (!$f) { sub_out(422, ['status' => 'error', 'msg' => 'Choose a file first.']); }
        $result(sub_file_add($pdo, (int) $p('id'), (int) $p('reqId') ?: null, $p('kind'), $f, $scope, $actor));

    case 'file_delete':
        $result(sub_file_delete($pdo, (int) $p('fileId'), $scope));
    }
    sub_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('[wedo subscriptions] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    sub_out(500, ['status' => 'error', 'msg' => 'Something went wrong — nothing was saved. Try again.']);
}
