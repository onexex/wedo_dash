<?php 

  include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
    date_default_timezone_set("Asia/Manila");
  $day_desc=date("l");
  $id=$_SESSION['id'];
  $compid=$_SESSION['CompID'];
  $minlack2=0;
  $gettotalmin=0;
  $today = date("Y-m-d H:i:s");
  $resultlilo = mysqli_query($con, "Select * from attendancelog where EmpID='$id' order by LogID DESC");
  $reslilo = mysqli_fetch_array($resultlilo); 
  $cntlilo= mysqli_num_rows ($resultlilo);
  $lid=$reslilo['LogID'];
  $tdy=date("Y-m-d 00:00:00");

  $sql = "UPDATE attendancelog SET TimeOut=:tout, MinsLack2=:min2,durationtime=:bh1 WHERE LogID=:id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id', $lid);
  $stmt->bindParam(':tout', $today);
  $stmt->bindParam(':bh1',  $gettotalmin);
  $stmt->bindParam(':min2',$minlack2);
  $stmt->execute();
                                                                  
  $sql = "UPDATE earlyout SET Status=6 WHERE LogID=:id";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id', $lid);
  $stmt->execute();                                              

  $id=$_SESSION['id'];
  $ch="Logged Out from Cenar";
                                                        // insert into dars
  $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
  $stmt = $pdo->prepare($sql);
  $stmt->bindParam(':id' , $id);
  $stmt->bindParam(':empact', $ch);
  $stmt->bindParam(':ddt', $today);
  $stmt->execute();

