<?php 
	include 'w_conn.php';
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
     date_default_timezone_set("Asia/Manila");
	if (isset($_SESSION['rid'])){
			try{
				$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				   }
				catch(PDOException $e)
				   {
				die("ERROR: Could not connect. " . $e->getMessage());
				   }

		$q=$_SESSION['rid'];
		$result = mysqli_query($con,"select * from messageheader where SenderID='$_SESSION[id]' and RecieverID='$q'");
		$res = mysqli_fetch_array($result); 
		$cnt= mysqli_num_rows ($result);
		if ($cnt>0){
					$today =date("Y-m-d H:i:s"); 
				  	$sql = "INSERT INTO messages (MHID,SenderID,Message,DateSent) VALUES (:id,:sdr,:ms,:ds)";
				   	$stmt = $pdo->prepare($sql);
				   	$stmt->bindParam(':id' , $res['MHID']);
				   	$stmt->bindParam(':sdr', $_SESSION['id']);
				   	$stmt->bindParam(':ms', $_REQUEST['data']);
				   	$stmt->bindParam(':ds', $today);
				  	$stmt->execute(); 
				  	echo 1;
		}else {
			$result = mysqli_query($con,"select * from messageheader where RecieverID='$_SESSION[id]' and SenderID='$q'");
			$res = mysqli_fetch_array($result); 
			$cnt= mysqli_num_rows ($result);
			if ($cnt>0){
					$today =date("Y-m-d H:i:s"); 
				  	$sql = "INSERT INTO messages (MHID,SenderID,Message,DateSent) VALUES (:id,:sdr,:ms,:ds)";
				   	$stmt = $pdo->prepare($sql);
				   	$stmt->bindParam(':id' , $res['MHID']);
				   	$stmt->bindParam(':sdr', $_SESSION['id']);
				   	$stmt->bindParam(':ms', $_REQUEST['data']);
				   	$stmt->bindParam(':ds', $today);
				  	$stmt->execute(); 
				  	  	echo 1;
			}
			else{
			
				   $id=$_SESSION['id'] . "_" .  $q;
				// insert into dars
					$sql = "INSERT INTO messageheader (MHID,SenderID,RecieverID) VALUES (:id,:sdr,:rvr)";
				   	$stmt = $pdo->prepare($sql);
				   	$stmt->bindParam(':id' , $id);
				   	$stmt->bindParam(':sdr', $_SESSION['id']);
				   	$stmt->bindParam(':rvr', $q);
				  	$stmt->execute(); 
				  	  $today =date("Y-m-d H:i:s"); 
				  	$sql = "INSERT INTO messages (MHID,SenderID,Message,DateSent) VALUES (:id,:sdr,:ms,:ds)";
				   	$stmt = $pdo->prepare($sql);
				   	$stmt->bindParam(':id' , $id);
				   	$stmt->bindParam(':sdr', $_SESSION['id']);
				   	$stmt->bindParam(':ms', $_REQUEST['data']);
				   	$stmt->bindParam(':ds', $today);
				  	$stmt->execute(); 
				  	  	echo 1;
			}

		}
	}
	else{
		echo "Please Search employee ";
	}
?>