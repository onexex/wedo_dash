

<?php 



  include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }

  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}

  else{ header ('location: login.php'); }



date_default_timezone_set("Asia/Manila");

$today = date("Y-m-d H:i:s");

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
$Strdar = str_replace('\'', '', $_REQUEST['dreact']);

     $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";

   $stmt = $pdo->prepare($sql);

   $stmt->bindParam(':id' , $id);

   $stmt->bindParam(':empact', $Strdar);
   $stmt->bindParam(':ddt', $today);

   $stmt->execute();    
    



 ?>