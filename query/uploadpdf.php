<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); exit; }
?>
<?php
	include_once ("w_conn.php");

	if (!empty($_FILES['file']['name'])) {
		// PDF ONLY — never trust the client filename/extension (previously this
		// stored the raw client filename under assets/pdf/, allowing e.g. shell.php
		// = remote code execution).
		$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
		if ($ext !== 'pdf') { http_response_code(400); echo "0"; exit; }

		$empid   = isset($_GET['q']) ? $_GET['q'] : '';
		$safeEmp = preg_replace('/[^A-Za-z0-9._-]/', '', basename($empid));
		// generated, collision-resistant name inside assets/pdf/ (no traversal)
		$filename = $safeEmp . '_' . date('YmdHis') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.pdf';
		$location = "assets/pdf/" . $filename;

		if (move_uploaded_file($_FILES['file']['tmp_name'], $location)) {
			$stmt = mysqli_prepare($con, "INSERT INTO empe201files (EMPID, EmpfileN, EmpProFPath) VALUES (?, ?, ?)");
			mysqli_stmt_bind_param($stmt, "sss", $empid, $filename, $location);
			mysqli_stmt_execute($stmt);
			echo $location;
		} else {
			echo "0";
		}
	}
?>
