<?php
	/* Reset an employee's password to the default (Electronic 201 → key button).
	   Only signed-in users who are shown that button (UserType other than 3). */
	if (session_status() === PHP_SESSION_NONE) { session_start(); }
	if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { http_response_code(401); echo "Not logged in"; exit; }
	if (!isset($_SESSION['UserType']) || $_SESSION['UserType'] == 3) { http_response_code(403); echo "Not allowed"; exit; }
	include 'w_conn.php';
	try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   	}
		catch(PDOException $e)
   	{
		die("ERROR: Could not connect. " . $e->getMessage());
   	}
   	$EmpD=(string)($_REQUEST['data'] ?? '');

	   $pass="wedoinc2023";
	   $epass=password_hash($pass, PASSWORD_DEFAULT);

		$sql = "UPDATE empdetails SET EmpPW=:pw WHERE EmpID=:id";
   		$stmt = $pdo->prepare($sql);
   		$stmt->execute([':pw' => $epass, ':id' => $EmpD]);
