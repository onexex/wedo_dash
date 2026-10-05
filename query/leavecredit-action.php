<?php
/* ==========================================================================
   leavecredit-action.php  —  writes for Modules > Leave Credit Viewer.
   POST action = start_year | save, returns JSON.

   Gated by the `lcreditedit` access right (==2) and a per-session token from
   lc_csrf_token(), both checked here — the page is not the guard.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');

function lc_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    lc_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lc_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/leave-credit-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    lc_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!lc_can_manage($pdo)) {
    lc_out(403, ['status' => 'error', 'msg' => 'You do not have access to change leave credits.']);
}
if (!hash_equals(lc_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    lc_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}

$action = $_POST['action'] ?? '';
$actor  = (string) $_SESSION['id'];

function lc_dars(PDO $pdo, $text) {
    try {
        $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity) VALUES (:id, :act)")
            ->execute([':id' => $_SESSION['id'], ':act' => $text]);
    } catch (Throwable $e) { /* audit is best-effort, never blocks the change */ }
}

/* ------------------------------------------------------------ start_year */
if ($action === 'start_year') {
    $year    = (int) ($_POST['year'] ?? 0);
    $credits = is_array($_POST['credit'] ?? null) ? $_POST['credit'] : [];
    $errors  = lc_start_year($pdo, $year, $credits, $actor);
    if ($errors) { lc_out(422, ['status' => 'error', 'msg' => implode("\n", $errors)]); }
    lc_dars($pdo, "Started the $year leave year (leave credits reset)");
    lc_out(200, ['status' => 'ok', 'msg' => "The $year leave year has started."]);
}

/* ------------------------------------------------------------------ save */
if ($action === 'save') {
    $emp    = (string) ($_POST['emp'] ?? '');
    $errors = lc_save($pdo, $emp, $_POST['ct'] ?? '', $_POST['cth'] ?? '', $actor);
    if ($errors) { lc_out(422, ['status' => 'error', 'msg' => implode("\n", $errors)]); }
    lc_dars($pdo, "Updated leave credit of $emp");
    lc_out(200, ['status' => 'ok', 'msg' => 'Leave credit saved.']);
}

lc_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
