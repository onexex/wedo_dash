<?php
	/* Unlock an employee's sign-in (Electronic 201 → "Unlock sign-in"), after
	   too many wrong passwords (includes/bot-guard.php). Their password is not
	   changed; they may sign in again at once.
	   Allowed: same as Reset password — admins (UserType 1) and "Update 201
	   Files" users (updte = 2); only an admin may unlock an admin. */
	if (session_status() === PHP_SESSION_NONE) { session_start(); }
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { http_response_code(401); echo json_encode(['status' => 'error', 'msg' => 'Not logged in']); exit; }
	if (!isset($_SESSION['UserType']) || $_SESSION['UserType'] == 3) { http_response_code(403); echo json_encode(['status' => 'error', 'msg' => 'Not allowed']); exit; }
	include 'w_conn.php';
	require_once __DIR__ . '/../includes/bot-guard.php';
	try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   	}
		catch(PDOException $e)
   	{
		http_response_code(500); echo json_encode(['status' => 'error', 'msg' => 'Database unavailable.']); exit;
   	}
	$me      = (string) $_SESSION['id'];
	$isAdmin = (string) $_SESSION['UserType'] === '1';
	$ar = $pdo->prepare('SELECT updte FROM accessrights WHERE EmpID = ?');
	$ar->execute([$me]);
	if (!$isAdmin && (string) $ar->fetchColumn() !== '2') {
		http_response_code(403); echo json_encode(['status' => 'error', 'msg' => 'Only HR (Update 201 Files) may unlock sign-ins.']); exit;
	}

	$EmpD = (string) ($_POST['data'] ?? '');
	$t = $pdo->prepare('SELECT EmpRoleID, EmpUN FROM empdetails WHERE EmpID = :id');
	$t->execute([':id' => $EmpD]);
	$emp = $t->fetch(PDO::FETCH_ASSOC);
	if (!$emp) { echo json_encode(['status' => 'error', 'msg' => 'Employee not found.']); exit; }
	if ((string) $emp['EmpRoleID'] === '1' && !$isAdmin) {
		http_response_code(403); echo json_encode(['status' => 'error', 'msg' => "Only an admin may unlock an admin's sign-in."]); exit;
	}

	wd_login_unlock($pdo, (string) $emp['EmpUN']);
	try {
		$pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :act, NOW())")
			->execute([':id' => $me, ':act' => 'Sign-in unlocked for ' . $EmpD]);
	} catch (Throwable $e) { }
	echo json_encode(['status' => 'ok']);
