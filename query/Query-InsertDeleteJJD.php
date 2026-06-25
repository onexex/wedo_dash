<?php
 include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
if (isset($_GET['insert'])){
	$eid = $_POST['EmIDJD'];
	$jid = $_POST['JobId'];

	$st = $pdo->prepare("SELECT * FROM empjobdesc WHERE JID=:jid and EmpID=:eid");
	$st->bindParam(':jid' , $jid);
	$st->bindParam(':eid' , $eid);
	$st->execute();

	$res=$st->rowCount();
	if($res>0){
		echo 2;
	}else{
		$statement = $pdo->prepare("INSERT INTO empjobdesc(JID,EmpID) VALUES(:jid,:eid)");
		$statement->bindParam(':jid' , $jid);
		$statement->bindParam(':eid' , $eid);
		$statement->execute();
		echo 1;
	}
}elseif (isset($_GET['insertnewjd'])){
		$jid = $_POST['JobId'];
		$statement = $pdo->prepare("INSERT INTO jobdescription (JDescription) VALUES(:jids)");
		$statement->bindParam(':jids' , $jid);
		$statement->execute();
}else{
	$jid = $_POST['JobId'];

	$statement = $pdo->prepare("DELETE FROM empjobdesc WHERE EJID=:jid");
	$statement->bindParam(':jid' , $jid);
	$statement->execute();
}

