<?php
	if (session_status() === PHP_SESSION_NONE) { session_start(); }
  	if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  	else{ header ('location: login'); }

	if ($_SESSION['UserType']==3){
		  	include 'w_conn.php';
			try{
				$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			   }
			catch(PDOException $e)
			   {
			die("ERROR: Could not connect. " . $e->getMessage());
			   }
			   date_default_timezone_set("Asia/Manila");
			  $id=$_SESSION['id'];
			//get work sched 
			  $stmt=$pdo->prepare("Select * from annseen where EmpID='$id' and aid='$_POST[idn]'");
			  $stmt->execute();
			  $row=$stmt->fetch();
			  $nrow=$stmt->rowCount();
			  $cid=$_POST['idn'];
			   $today =date("Y-m-d h:i:s A"); 
			  if ($nrow==0){
			  	   $sql = "INSERT INTO annseen (aid,EmpID,FSeenDate,LSeenDate,Status) VALUES (:crnid,:id,:sdate,:lsdate,1)";
				   $stmt = $pdo->prepare($sql);
				   $stmt->bindParam(':crnid' , $cid);
				   $stmt->bindParam(':id' , $id);
				   $stmt->bindParam(':sdate' , $today);
				   $stmt->bindParam(':lsdate' , $today);
				   $stmt->execute();
			  }else{
			  	   $sql = "UPDATE annseen SET LSeenDate=:lsdate where aid=:crnid and EmpID=:id";
				   $stmt = $pdo->prepare($sql);
				   $stmt->bindParam(':crnid' , $cid);
				   $stmt->bindParam(':id' , $id);
				   $stmt->bindParam(':lsdate' , $today);
				   $stmt->execute(); 	
			  }

	}