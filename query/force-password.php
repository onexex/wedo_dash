<?php
/* ==========================================================================
   force-password.php  —  sets the signed-in person's own password
   (change-password.php). POST token, current, new, confirm -> JSON.
   The current password is checked against the database (not the session);
   5 wrong tries sign the session out. Rules: 8+ characters, letters and
   digits, not the username, not a known default, not the same as before.
   Clears empdetails.must_change_pw and starts a fresh session id.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function pwc_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { pwc_out(401, ['status' => 'error', 'signedOut' => true, 'msg' => 'Please sign in again.']); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { pwc_out(405, ['status' => 'error', 'msg' => 'POST only.']); }
if (empty($_SESSION['pwc_csrf']) || !hash_equals($_SESSION['pwc_csrf'], (string) ($_POST['token'] ?? ''))) {
    pwc_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/account-gate.php';
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    pwc_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

/** Passwords WeDo hands out (enrolment, admin reset): never accepted as one's own. */
const PWC_DEFAULTS = ['p@zzword', 'wedoinc2023', 'password', 'password1', '12345678', 'wedo1234'];

$id  = (string) $_SESSION['id'];
$cur = (string) ($_POST['current'] ?? '');
$new = (string) ($_POST['new'] ?? '');

$st = $pdo->prepare("SELECT EmpUN, EmpPW FROM empdetails WHERE EmpID = :id");
$st->execute([':id' => $id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { pwc_out(404, ['status' => 'error', 'msg' => 'Account not found.']); }

if (!password_verify($cur, (string) $row['EmpPW'])) {
    $_SESSION['pwc_fail'] = (int) ($_SESSION['pwc_fail'] ?? 0) + 1;
    if ($_SESSION['pwc_fail'] >= 5) {
        $_SESSION = [];
        session_destroy();
        pwc_out(401, ['status' => 'error', 'signedOut' => true, 'msg' => 'Too many wrong tries — please sign in again.']);
    }
    pwc_out(422, ['status' => 'error', 'msg' => 'Your current password is not correct.']);
}
if ($new !== (string) ($_POST['confirm'] ?? '')) { pwc_out(422, ['status' => 'error', 'msg' => 'The two new passwords are not the same.']); }
if (strlen($new) < 8 || strlen($new) > 72) { pwc_out(422, ['status' => 'error', 'msg' => 'Use 8 to 72 characters.']); }
if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) { pwc_out(422, ['status' => 'error', 'msg' => 'Use both letters and numbers.']); }
if (in_array(strtolower($new), PWC_DEFAULTS, true)) { pwc_out(422, ['status' => 'error', 'msg' => 'That password is too common — choose another.']); }
$un = strtolower(trim((string) $row['EmpUN']));
if ($un !== '' && strpos(strtolower($new), $un) !== false) { pwc_out(422, ['status' => 'error', 'msg' => 'Your password must not contain your username.']); }
if (password_verify($new, (string) $row['EmpPW'])) { pwc_out(422, ['status' => 'error', 'msg' => 'Choose a password different from the current one.']); }

$hash = password_hash($new, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE empdetails SET EmpPW = :pw WHERE EmpID = :id")->execute([':pw' => $hash, ':id' => $id]);
wd_set_must_change_password($pdo, $id, false);
try {
    $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, 'Changed own password', NOW())")->execute([':id' => $id]);
} catch (Throwable $e) { }

session_regenerate_id(true);   // the session that knew the temporary password is gone
$_SESSION['PassHash'] = $hash;
$_SESSION['wd_pw_ok'] = 1;
unset($_SESSION['pwc_fail'], $_SESSION['pwc_csrf']);
pwc_out(200, ['status' => 'ok']);
