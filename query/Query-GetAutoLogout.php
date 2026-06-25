<?php 

  include 'w_conn.php';
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
 //set default time  
  date_default_timezone_set("Asia/Manila"); 
    $day_desc=date("l");
   $id=$_SESSION['id'];
   $compid=$_SESSION['CompID'];

//geteo validation
$stmt= $pdo->prepare("Select * from eovalidation where CompID='$compid'");
$stmt->execute();
$rowvalid   = $stmt->fetch(); 

$EOIsAutoOut  = 2;

if($stmt->rowCount() > 0) 
{
  $EOIsAutoOut  = $rowvalid['IsAutoLogout'];
  print $EOIsAutoOut;
}
?>