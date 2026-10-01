<?php
/* Approve or reject an employee's profile change request.
   Only users with "Update 201 Files" (accessrights.updte = 2). Approving writes
   the requested values into the record (includes/profile-change.php: pcr_apply). */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/profile-change.php';
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    pcr_json(401, ['ok' => false, 'message' => 'Your session has expired. Please sign in again.']);
}
include 'w_conn.php';

$me  = (string)$_SESSION['id'];
$pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password,
               [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if (!pcr_can_update($pdo, $me)) {
    pcr_json(403, ['ok' => false, 'message' => 'You do not have permission to review profile changes.']);
}

$id      = (int)($_POST['id'] ?? 0);
$action  = (string)($_POST['action'] ?? '');
$remarks = trim((string)($_POST['remarks'] ?? ''));
if (!in_array($action, ['approve', 'reject'], true)) { pcr_json(422, ['ok' => false, 'message' => 'Unknown action.']); }
if ($action === 'reject' && $remarks === '') {
    pcr_json(422, ['ok' => false, 'message' => 'Please give a reason so the employee knows what to fix.']);
}

try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT * FROM profile_change_requests WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $req = $st->fetch(PDO::FETCH_ASSOC);
    if (!$req) { $pdo->rollBack(); pcr_json(404, ['ok' => false, 'message' => 'Request not found.']); }
    if ($req['Status'] !== 'pending') {
        $pdo->rollBack();
        pcr_json(409, ['ok' => false, 'message' => 'This request was already ' . $req['Status'] . '.']);
    }

    if ($action === 'approve') {
        pcr_apply($pdo, $req['EmpID'], json_decode($req['Changes'], true) ?: []);
    }
    $pdo->prepare('UPDATE profile_change_requests SET Status = ?, ReviewedBy = ?, ReviewedAt = NOW(), Remarks = ? WHERE id = ?')
        ->execute([$action === 'approve' ? 'approved' : 'rejected', $me, $remarks !== '' ? $remarks : null, $id]);
    $pdo->prepare('INSERT INTO dars (EmpID, EmpActivity) VALUES (?, ?)')
        ->execute([$req['EmpID'], $action === 'approve' ? 'Profile : 201 update request approved' : 'Profile : 201 update request rejected']);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    pcr_json(500, ['ok' => false, 'message' => 'Could not save the review. Please try again.']);
}

pcr_json(200, ['ok' => true, 'status' => $action === 'approve' ? 'approved' : 'rejected']);
