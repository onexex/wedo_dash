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
   //get access rights
$id=$_SESSION['id'];
$errinsert=0;


if (isset($_GET['delbankinfo'])){
    $val=$_POST['id'];
    $sql="UPDATE bookletbankinfo  SET status = '0' WHERE id = $val";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
if (isset($_GET['delserries'])){
    $val=$_POST['val'];
    $sql="UPDATE bookletinfo  SET delstat = '1' WHERE id = $val";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}

if (isset($_GET['loadbankinfo'])){
        $bankinfo=[]; 
        $sql = "Select * from bookletbankinfo where status='1'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $count = $stmt->rowCount();
        if($count > 0){
        while ($getdata = $stmt->fetch()) {
            $bankinfo[]=$getdata;
        }
               
    }
    echo json_encode(array("bankinfo"=>$bankinfo)); 
}

if (isset($_GET['updatebooklet'])){
    $bankid=$_POST['bankid'];
    $bookletid=$_POST['bookletid'];

    $sql="UPDATE bookletinfo  SET status = '0' WHERE bankinfoid = $bankid";
    $stmt = $pdo->prepare($sql);
    if($stmt->execute()){

        $sql="UPDATE bookletinfo  SET status = '1' WHERE id = $bookletid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $errinsert=1;

     }

echo json_encode(array("errinsert"=>$errinsert)); 
}

if (isset($_GET['store'])){
    $bankd=$_POST['bankname'];
    $errinsert=0; 
    
    
     $sql = "INSERT INTO bookletbankinfo (bankname,dti) 
    VALUES (:bankname,:dti)";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':bankname' , $bankd);
   $stmt->bindParam(':dti' , $today);
  
   if($stmt->execute()){
      $errinsert=1;              
   }


   echo json_encode(array("errinsert"=>$errinsert)); 
}

if (isset($_GET['addbooklet'])){
    $id=$_POST['id'];
    $start=$_POST['start'];
    $end=$_POST['end'];
    $errinsert=0; 
     $olnum=[];
    $signaldup=0;

    $sql = "Select * from bookletinfo where bankinfoid=$id ";
            // $sql = "Select * from bookletinfo where ((bankinfoid=$id) and 
            // ((bookletfrom BETWEEN $start and  $end) or (bookleto  BETWEEN  $start and  $end)))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    if($count > 0){

        while ($getdata = $stmt->fetch()) {
             
            $from=$getdata['bookletfrom'];
            $to=$getdata['bookleto'];

            if(( ($start >= $from) and ($start <= $to) ) or ( ($end >= $from) and ($end <= $to) ) )
            {
                $signaldup=1;
                $olnum[]=$getdata;
            }

        }   

        $errinsert=20;
        // echo json_encode(array("errinsert"=>$errinsert,"olnum"=> $olnum)); 

    }
    
    if( $signaldup==0){

    
        $sql = "INSERT INTO bookletinfo (bankinfoid,bookletfrom,bookleto,dateinputed) 
        VALUES (:bankinfoid,:bookletfrom,:bookleto,:dateinputed)";
       $stmt = $pdo->prepare($sql);
       $stmt->bindParam(':bankinfoid' , $id);
       $stmt->bindParam(':bookletfrom' , $start);
       $stmt->bindParam(':bookleto' , $end);
       $stmt->bindParam(':dateinputed' , $today);
       if($stmt->execute()){
          $errinsert=1;              
       }

   
}
echo json_encode(array("errinsert"=>$errinsert,"olnum"=> $olnum )); 
  
}


if (isset($_GET['loadbooklet'])){
    $id=$_POST['val'];
    $booklet=[]; 
    $sql = "Select * from bookletinfo where bankinfoid=$id and delstat='0'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    if($count > 0){
    while ($getdata = $stmt->fetch()) {
        $booklet[]=$getdata;
    }         
}
echo json_encode(array("booklet"=>$booklet)); 
}



?>