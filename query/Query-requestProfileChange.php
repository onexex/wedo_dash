<?php
/* Employee submits changes to THEIR OWN 201 record for approval.
   Only the requestable fields in includes/profile-change.php are considered;
   the record itself is not touched until a reviewer approves.
   A new submission replaces the employee's still-pending one. */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/profile-change.php';
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    pcr_json(401, ['ok' => false, 'message' => 'Your session has expired. Please sign in again.']);
}
include 'w_conn.php';

$me  = (string)$_SESSION['id'];     // never taken from the form: employees can only request for themselves
$pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password,
               [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$current = pcr_current($pdo, $me);
if ($current === null) { pcr_json(404, ['ok' => false, 'message' => 'Your employee record was not found.']); }

$missing = [];
foreach (PCR_REQUIRED as $f) {
    if (array_key_exists($f, $_POST) && pcr_norm($_POST[$f]) === '') { $missing[] = pcr_fields()[$f][3]; }
}
if ($missing) {
    pcr_json(422, ['ok' => false, 'message' => 'Please fill in: ' . implode(', ', $missing) . '.']);
}

$fields = pcr_diff($current, $_POST);
$family = null;
if (array_key_exists('family', $_POST)) {
    $old = pcr_family_from_post(pcr_family($pdo, $me));
    $new = pcr_family_from_post($_POST['family']);
    if ($new != $old) { $family = ['old' => $old, 'new' => $new]; }
}
if (!$fields && $family === null) {
    pcr_json(422, ['ok' => false, 'message' => 'There are no changes to submit.']);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE profile_change_requests SET Status = 'cancelled' WHERE EmpID = ? AND Status = 'pending'")
        ->execute([$me]);
    $pdo->prepare('INSERT INTO profile_change_requests (EmpID, RequestedBy, Changes, Status) VALUES (?, ?, ?, ?)')
        ->execute([$me, $me, json_encode(['fields' => $fields, 'family' => $family]), 'pending']);
    $pdo->prepare('INSERT INTO dars (EmpID, EmpActivity) VALUES (?, ?)')
        ->execute([$me, 'Profile : Requested 201 information update']);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $setup = stripos($e->getMessage(), 'profile_change_requests') !== false;
    pcr_json(500, ['ok' => false, 'message' => $setup
        ? 'Change requests are not set up yet on this server. Please contact HR.'
        : 'Could not submit your request. Please try again.']);
}

// mobile push: everyone who can review profile changes (same rule as pcr_can_update)
require_once __DIR__ . '/../includes/push-lib.php';
if (push_enabled()) {
    $rv = $pdo->query("SELECT EmpID FROM accessrights WHERE updte = '2'")->fetchAll(PDO::FETCH_COLUMN);
    push_profile_change_filed($pdo, $me, $rv);
}

pcr_json(200, ['ok' => true, 'changes' => count($fields) + ($family ? 1 : 0)]);
