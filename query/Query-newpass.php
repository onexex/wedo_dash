<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
  date_default_timezone_set("Asia/Manila");
  $today = date("Y-m-d H:i:s");
	 $epass=password_hash($_POST['cupass'], PASSWORD_DEFAULT);
	if (password_verify($_POST['cupass'], $_SESSION['PassHash'])) {
		include 'w_conn.php';
		try{
			$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
		catch(PDOException $e)
			{
		die("ERROR: Could not connect. " . $e->getMessage());
			}
			$newhash=password_hash($_POST['npass'],PASSWORD_DEFAULT);
			$_SESSION['PassHash']=$newhash;
			$sql = "UPDATE empdetails SET EmpPW=:newpass WHERE EmpID=:empidn";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':empidn', $_SESSION['id']);
		   $stmt->bindParam(':newpass', $newhash);
		   $stmt->execute(); 

		   	$id=$_SESSION['id'];
                       $ch="Youve Changed your Password.";
                  // insert into dars
                      $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                     $stmt = $pdo->prepare($sql);
                     $stmt->bindParam(':id' , $id);
                     $stmt->bindParam(':empact', $ch);
                     $stmt->bindParam(':ddt', $today);
                     $stmt->execute();
		print 0;
	}
	else{
		print 1;
	}
?>