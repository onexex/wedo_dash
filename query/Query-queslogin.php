<?php
include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
date_default_timezone_set("Asia/Manila");

            $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
            $statement->bindParam(':un' , $_SESSION['quesID']);
            $statement->execute(); 
            
            $count=$statement->rowCount();
            $row=$statement->fetch();
            
            	$_SESSION['id']=$row['EmpID'];
            	    $epass=password_hash($row['EmpID'], PASSWORD_DEFAULT);
                    setcookie("WeDoID",$epass, time()+28800, "/");
            		$_SESSION['UserType']=$row['EmpRoleID'];
            		$cid=$row['EmpCompID'];
            		$_SESSION['CompID']=$row['EmpCompID'];
            		$_SESSION['EmpISID']=$row['EmpISID'];
            		$statement = $pdo->prepare("select * from companies where CompanyID = :pw");
            		$statement->bindParam(':pw' , $cid);
            		$statement->execute(); 
            		$comcount=$statement->rowCount();
            		$row=$statement->fetch();
            		if ($comcount>0){
            			$_SESSION['CompanyName']=$row['CompanyDesc'];
            			$_SESSION['CompanyLogo']=$row['logopath'];
            			$_SESSION['CompanyColor']=$row['comcolor'];
            		}else{
            			$_SESSION['CompanyName']="ADMIN";
            			$_SESSION['CompanyLogo']="";
            			$_SESSION['CompanyColor']="red";
            		}
            		$_SESSION['PassHash']=$hash;
            		
            		$id=$_SESSION['id'];
                    $ch="Answered YES to the questionnaire";
                    $today = date("Y-m-d H:i:s");
                    // insert into dars
                    $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id' , $id);
                    $stmt->bindParam(':empact', $ch);
                    $stmt->bindParam(':ddt', $today);
                    $stmt->execute();

?>