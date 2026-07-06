<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); exit; }
	include 'w_conn.php';
?>
<?php
	try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect.");
		   }

	// --- Authorization: only a Super User (UserType 1) or a user who holds the
	//     'arights' permission may change access rights. Previously this endpoint
	//     accepted any logged-in user, so anyone could grant themselves admin. ---
	$callerOk = (isset($_SESSION['UserType']) && $_SESSION['UserType'] == 1);
	if (!$callerOk) {
		$chk = $pdo->prepare("SELECT arights FROM accessrights WHERE EmpID = :id");
		$chk->execute([':id' => $_SESSION['id']]);
		$callerOk = ((int)$chk->fetchColumn() === 2);
	}
	if (!$callerOk) {
		http_response_code(403);
		echo "Forbidden";
		exit;
	}

	// --- Validate the target column against the REAL accessrights columns
	//     (a column name can't be a bound parameter, so it must be whitelisted).
	//     EmpID / ARID (the key columns) are never toggleable. ---
	$term  = $_REQUEST['term'] ?? '';
	$empid = $_GET['empid'] ?? '';
	$validCols = array_column($pdo->query("SHOW COLUMNS FROM accessrights")->fetchAll(PDO::FETCH_ASSOC), 'Field');
	$blocked   = ['ARID', 'EmpID'];
	if ($empid === '' || !in_array($term, $validCols, true) || in_array($term, $blocked, true)) {
		http_response_code(400);
		echo "Invalid request";
		exit;
	}

	// mponoff -> value (1 == OFF, 2 == ON), and audit-log the change
	$d  = ($_GET['mponoff'] ?? '') === "OFF" ? 1 : 2;
	$ch = $d === 1 ? "Updated Access Rights to OFF" : "Updated Access Rights to ON";
	$stmt = $pdo->prepare("INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)");
	$stmt->execute([':id' => $_SESSION['id'], ':empact' => $ch]);

	// $term is now guaranteed to be an exact real column name; value + target are bound
	$sql  = "UPDATE accessrights SET `$term` = :d WHERE EmpID = :empid";
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':d' => $d, ':empid' => $empid]);
?>
