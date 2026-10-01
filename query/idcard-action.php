<?php
/* ==========================================================================
   idcard-action.php  —  writes for Management > ID Card Generator.
   POST action = save_crop | save_back | upload_sign | remove_sign | issue, returns JSON.

   Gated by the `idcard` access right (==2) and a per-session token from
   idc_csrf_token(), both checked here — the page is not the guard.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');

function idc_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    idc_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    idc_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/idcard-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    idc_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!idc_can_manage($pdo)) {
    idc_out(403, ['status' => 'error', 'msg' => 'You do not have access to the ID Card Generator.']);
}
if (!hash_equals(idc_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    idc_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}

$action = $_POST['action'] ?? '';
$actor  = (string) $_SESSION['id'];

function idc_log(PDO $pdo, $text) {
    try {
        $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity) VALUES (:id, :act)")
            ->execute([':id' => $_SESSION['id'], ':act' => $text]);
    } catch (Throwable $e) { /* audit is best-effort, never blocks the change */ }
}

/* ------------------------------------------------------------- save_crop */
if ($action === 'save_crop') {
    $emp = (string) ($_POST['emp'] ?? '');
    if ($emp === '' || !idc_employees($pdo, [$emp])) {
        idc_out(404, ['status' => 'error', 'msg' => 'That employee no longer exists.']);
    }
    idc_save_crop($pdo, $emp, $_POST['x'] ?? 0, $_POST['y'] ?? 0, $_POST['zoom'] ?? 1, $actor);
    idc_out(200, ['status' => 'ok', 'msg' => 'Photo position saved.']);
}

/* ------------------------------------------------- employee signature (front) */
if ($action === 'upload_sign' || $action === 'remove_sign') {
    $emp = (string) ($_POST['emp'] ?? '');
    if ($emp === '' || !idc_employees($pdo, [$emp])) {
        idc_out(404, ['status' => 'error', 'msg' => 'That employee no longer exists.']);
    }
    if ($action === 'remove_sign') {
        idc_remove_signature($emp);
        idc_log($pdo, 'ID card signature removed for ' . $emp);
        idc_out(200, ['status' => 'ok', 'card' => idc_employees($pdo, [$emp])[0]]);
    }
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        idc_out(422, ['status' => 'error', 'msg' => 'Choose a PNG or JPG image of the signature.']);
    }
    if ($f['size'] > 5 * 1024 * 1024) { idc_out(422, ['status' => 'error', 'msg' => 'That file is over 5 MB.']); }
    $err = idc_store_signature($emp, $f['tmp_name']);
    if ($err !== '') { idc_out(422, ['status' => 'error', 'msg' => $err]); }
    idc_log($pdo, 'ID card signature uploaded for ' . $emp);
    idc_out(200, ['status' => 'ok', 'card' => idc_employees($pdo, [$emp])[0]]);
}

/* ------------------------------------------------------------- save_back */
if ($action === 'save_back') {
    $errors = idc_save_back($pdo, $_POST, $actor);
    if ($errors) { idc_out(422, ['status' => 'invalid', 'error' => $errors]); }
    idc_log($pdo, 'ID card back details updated');
    idc_out(200, ['status' => 'ok', 'msg' => 'Card back saved. It applies to every card printed from now on.',
                  'back' => idc_back_config($pdo), 'values' => idc_back_values($pdo)]);
}

/* ----------------------------------------------------------------- issue */
if ($action === 'issue') {
    $ids = $_POST['emp'] ?? [];
    if (!is_array($ids)) { $ids = [$ids]; }
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), 'strlen')));
    if (!$ids) { idc_out(422, ['status' => 'error', 'msg' => 'Select at least one employee.']); }
    if (count($ids) > 200) { idc_out(422, ['status' => 'error', 'msg' => 'Print at most 200 cards at a time.']); }
    // the back's company details must be confirmed by HR before any card is issued
    if (!idc_back_is_set($pdo)) {
        idc_out(409, ['status' => 'needs_back', 'msg' => 'Confirm the company details on the back of the card before printing.']);
    }
    $found = [];
    foreach (idc_employees($pdo, $ids) as $c) { $found[$c['empId']] = $c; }
    foreach ($ids as $id) {
        if (!isset($found[$id])) { idc_out(404, ['status' => 'error', 'msg' => 'Employee not found. (' . $id . ')']); }
    }
    // the back says the card bears the holder's signature: no signature, no card.
    // All-or-nothing, so a batch never half-prints.
    $unsigned = array_values(array_filter($found, function ($c) { return !$c['signature']; }));
    if ($unsigned) {
        idc_out(409, ['status' => 'needs_sign',
            'msg' => count($unsigned) . ' employee' . (count($unsigned) > 1 ? 's have' : ' has') . ' no signature on file. Upload it before printing.',
            'missing' => array_map(function ($c) { return ['empId' => $c['empId'], 'name' => $c['listName']]; }, $unsigned)]);
    }

    $issued = [];
    foreach ($ids as $id) {
        try {
            $r = idc_issue($pdo, $id, $actor);
        } catch (RuntimeException $e) {
            idc_out(404, ['status' => 'error', 'msg' => $e->getMessage() . ' (' . $id . ')']);
        }
        $issued[] = $id;
        idc_log($pdo, 'ID card ' . ($r['action'] === 'issue' ? 'issued' : 'reprinted') . ' for ' . $id . ' (' . $r['id_number'] . ')');
    }
    // print with the back as saved right now, not as the page loaded it
    idc_out(200, ['status' => 'ok', 'cards' => idc_employees($pdo, $issued), 'nextSeq' => idc_next_seq($pdo, date('Y')),
                  'back' => idc_back_config($pdo)]);
}

idc_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
