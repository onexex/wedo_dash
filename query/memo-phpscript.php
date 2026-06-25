<?php 
include 'w_conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
else{ header ('location: login.php'); }

date_default_timezone_set("Asia/Manila");
$today = date("Y-m-d H:i:s");
$today1 = date("Y-m-d");
$today2 = date("d F Y");
$today3 = date("Y");

try{
	$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }

catch(PDOException $e){
	die("ERROR: Could not connect. " . $e->getMessage());
   }

   $id=$_SESSION['id'];
   if (isset($_GET['getdatenow'])){
       $maxid=0;
      
    $stmt = $pdo->prepare("SELECT  MAX(id) as idMax FROM memo");
    
    $stmt->execute();
    $count = $stmt->rowCount();
    $getrow = $stmt->fetch();

    if($count > 0){
        $maxid= $getrow['idMax'];
        $maxid =$maxid + 1;
        $maxid = sprintf('%03d',$maxid);
        $maxid= $today3 . "_" . $maxid;

    }else{
        $maxid =$maxid + 1;
        $maxid = sprintf('%03d',$maxid);
        $maxid= $today3 . "_" . $maxid;
    }


    echo json_encode(array("data"=>$today2,"newID"=>$maxid));                   
}
if (isset($_GET['store'])){
    $errinsert=0;   
    $data_to=$_POST['data_to'];
    $data_from=$_POST['data_from'];
    $data_date=$_POST['data_date'];
    $data_subject=$_POST['data_subject'];
    $data_body=$_POST['data_body'];
    $memoid=$_POST['memoid'];

    $sql = "INSERT INTO memo (memoto,memofrom,memoid,date,subject,body,dti) 
    VALUES (:memoto,:memofrom,:memoid,:date,:subject,:body,:dti)";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':memoto' , $data_to);
   $stmt->bindParam(':memofrom' , $data_from);
   $stmt->bindParam(':memoid' , $memoid);
   $stmt->bindParam(':date' , $today1 );
   $stmt->bindParam(':subject' , $data_subject);
   $stmt->bindParam(':body' , $data_body);
   $stmt->bindParam(':dti' , $today);

   if($stmt->execute()){
      $errinsert=1;              
   } 
   $errinsert=1;   
   echo json_encode(array("data"=>$errinsert));                   
}

if (isset($_GET['findWord'])){


    $valueWord=$_POST['inputVal'];
    $data=[];
    $stmt = $pdo->prepare("Select * from memo ");
    $stmt->execute();
    $count = $stmt->rowCount();
      if($count > 0){
          
       while ($getdata = $stmt->fetch()) {
          $data[]=$getdata;
      }
      
      }
      echo json_encode(array("data"=>$data));
    
  


}


if (isset($_GET['loadDatasearchID'])){

    $data=[];
    $searchID=$_POST['searchID'];
    
    $stmt = $pdo->prepare("Select * from memo where id=$searchID ");
    $stmt->execute();

    $count = $stmt->rowCount();
      if($count > 0){
       while ($getdata = $stmt->fetch()) {
          $data[]=$getdata;
          $date=$getdata['date'];
      }
      
      }
      
    $date1=date_create($date);
    $d=date_format($date1,"d F Y");

     echo json_encode(array("data"=>$data,"date"=>$d)); 

}



   ?>