<?php
	/* Reset an employee's password (Electronic 201 → key button). Returns JSON
	   with a random one-time temporary password for HR to hand over privately;
	   the employee must set their own at the next sign-in (must_change_pw) and
	   is signed out on every device now.
	   Allowed: admins (UserType 1) and "Update 201 Files" users (updte = 2).
	   Only an admin may reset an admin's password. Not for one's own account
	   (use Change password). The password is never written to any log. */
	if (session_status() === PHP_SESSION_NONE) { session_start(); }
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { http_response_code(401); echo json_encode(['status' => 'error', 'msg' => 'Not logged in']); exit; }
	if (!isset($_SESSION['UserType']) || $_SESSION['UserType'] == 3) { http_response_code(403); echo json_encode(['status' => 'error', 'msg' => 'Not allowed']); exit; }
	include 'w_conn.php';
	require_once __DIR__ . '/../includes/account-gate.php';
	require_once __DIR__ . '/../includes/bot-guard.php';
	try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   	}
		catch(PDOException $e)
   	{
		http_response_code(500); echo json_encode(['status' => 'error', 'msg' => 'Database unavailable.']); exit;
   	}
	$me     = (string) $_SESSION['id'];
	$isAdmin = (string) $_SESSION['UserType'] === '1';
	$ar = $pdo->prepare('SELECT updte FROM accessrights WHERE EmpID = ?');
	$ar->execute([$me]);
	if (!$isAdmin && (string) $ar->fetchColumn() !== '2') {
		http_response_code(403); echo json_encode(['status' => 'error', 'msg' => 'Only HR (Update 201 Files) may reset passwords.']); exit;
	}

   	$EmpD = (string) ($_REQUEST['data'] ?? '');
	$t = $pdo->prepare('SELECT EmpRoleID, EmpUN FROM empdetails WHERE EmpID = :id');
	$t->execute([':id' => $EmpD]);
	$emp = $t->fetch(PDO::FETCH_ASSOC);
	if ($emp === false) { echo json_encode(['status' => 'error', 'msg' => 'Employee not found.']); exit; }
	if ($EmpD === $me) { http_response_code(422); echo json_encode(['status' => 'error', 'msg' => 'Use Change password for your own account.']); exit; }
	if ((string) $emp['EmpRoleID'] === '1' && !$isAdmin) {
		http_response_code(403); echo json_encode(['status' => 'error', 'msg' => "Only an admin may reset an admin's password."]); exit;
	}

	$pass  = wd_temp_password();
	$epass = password_hash($pass, PASSWORD_DEFAULT);
	$stmt = $pdo->prepare("UPDATE empdetails SET EmpPW=:pw WHERE EmpID=:id");
	$stmt->execute([':pw' => $epass, ':id' => $EmpD]);
	wd_set_must_change_password($pdo, $EmpD, true);
	wd_account_revoke($pdo, $EmpD);   // whoever had the old password is signed out everywhere
	wd_login_unlock($pdo, (string) $emp['EmpUN']);   // a locked account can use the temporary password at once
	try {
		$pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :act, NOW())")
			->execute([':id' => $me, ':act' => 'Password reset for ' . $EmpD]);
	} catch (Throwable $e) { }
	echo json_encode(['status' => 'ok', 'password' => $pass]);
