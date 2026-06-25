<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
	include 'w_conn.php';
?>
<?php
	try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		   
		  $id=$_SESSION['id'];
		// insert into dars
		  if ($_GET['mponoff']=="OFF"){
		  			$d=1;
		  	 		$id=$_SESSION['id'];
                       $ch="Updated Access Rights to OFF";
                  // insert into dars
                      $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
                     $stmt = $pdo->prepare($sql);
                     $stmt->bindParam(':id' , $id);
                     $stmt->bindParam(':empact', $ch);
                     $stmt->execute();
		  }else{
				  	$d=2;
				  	$id=$_SESSION['id'];
                       $ch="Updated Access Rights to ON";
                  // insert into dars
                      $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
                     $stmt = $pdo->prepare($sql);
                     $stmt->bindParam(':id' , $id);
                     $stmt->bindParam(':empact', $ch);
                     $stmt->execute();
		  }
			$sql = "update accessrights set " . $_REQUEST['term'] ."=" . $d . " where EmpID='" . $_GET['empid'] . "'";
		   $stmt = $pdo->prepare($sql);
		   $stmt->execute(); 

?>
