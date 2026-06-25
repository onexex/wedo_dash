<?php
    include 'w_conn.php';

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else{ header ('location: login.php'); }
    
    date_default_timezone_set("Asia/Manila");
    $today = date("Y-m-d H:i:s");
    $today1 = date("Y-m-d");
    
    try{
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       }
    
    catch(PDOException $e){
        die("ERROR: Could not connect. " . $e->getMessage());
       }

   $id=$_SESSION['id'];
 $status=7;
   if (isset($_GET['delupdateot'])){
        $id2=$_POST['id'];
    $sql = "UPDATE otattendancelog SET Status=:status
                                         where OTLOGID=:id";      
               
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':status' , $status);
               $stmt->bindParam(':id' , $id2);
               $stmt->execute();
    echo json_encode(array("errcode"=>1));

}


?>