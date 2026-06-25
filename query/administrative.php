<?php 
include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
else{ header ('location: login.php'); }
date_default_timezone_set("Asia/Manila");
$today = date("Y-m-d H:i ");

try{
    $customTime = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('P');
	$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone='$customTime';");
   }

catch(PDOException $e){
	die("ERROR: Could not connect. " . $e->getMessage());
   }
    if (isset($_GET['validateTime'])){

        $id=$_SESSION['id'];
        if($id=="WeDoinc-012"){

        }else{
            $datenow = date("Y-m-d");
            $timenow = strtotime($today);
            $startTime = strtotime($datenow . " 8:30:00");
            
            if($timenow > $startTime){
                echo json_encode(array("errcode"=>"1"));
            }else{
                echo json_encode(array("errcode"=>"2"));
            }
        }
       
    }
?>    