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

   if (isset($_GET['getpayeelist'])){

    $payeelist=[];
    $stmt = $pdo->prepare("Select *  from listofpayee order by payee asc");
    $stmt->execute();
    $count = $stmt->rowCount();
        if($count > 0){
            while ($getdata = $stmt->fetch()) {
                $payeelist[]=$getdata;
                    }
                }
    echo json_encode(array("payee"=>$payeelist )); 
    }

    if (isset($_GET['store'])){
            $payee=$_POST['payee'];       
            $can=$_POST['can'];
            $errinsert = 0;
            
            $sql = "INSERT INTO listofpayee (payee,can,dti) 
            VALUES (:payee,:can,:dti)";
           $stmt = $pdo->prepare($sql);
           $stmt->bindParam(':payee' , $payee);
           $stmt->bindParam(':can' , $can);
           $stmt->bindParam(':dti' , $today);
           if($stmt->execute()){
              $errinsert=1;              
           }
            echo json_encode(array("errinsert"=>$errinsert ));             
        }

        if (isset($_GET['remove'])){
               
            $id=$_POST['id'];
            $errinsert = 0;
             
            $sql = "DELETE from listofpayee where id=:id";
           $stmt = $pdo->prepare($sql);
           $stmt->bindParam(':id' , $id);          
           if($stmt->execute()){
              $errinsert=1;              
           }
            echo json_encode(array("errinsert"=>$errinsert )); 
            
        }


   ?>