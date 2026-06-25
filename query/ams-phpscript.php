<?php 
include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
else{ header ('location: login.php'); }
date_default_timezone_set("Asia/Manila");
$today = date("Y-m-d H:i:s");

try{
	$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }

catch(PDOException $e){
	die("ERROR: Could not connect. " . $e->getMessage());
   }

   
   if (isset($_GET['amview'])){
    $id=$_GET['id']; 
    $getemployee = $pdo->prepare("Select * from amsarchive where id =:id");
    $getemployee->bindParam(':id' , $id);
    $getemployee->execute();

    $count = $getemployee->rowCount();
    while ($getrow = $getemployee->fetch()) {
        $empdata[]=$getrow;
    }
    if($count>0){
         echo json_encode(array("data"=> $empdata,"errcode"=>0));
    }else{
        echo json_encode(array("errcode"=>1));
    }
   }
   
   
   
   
if (isset($_GET['viewall'])){
          $getemployee = $pdo->prepare("Select * from amsarchive as a INNER JOIN employees as b on (a.verifiedby=b.EmpID) order by a.lname asc");
        $getemployee->execute();
        $count = $getemployee->rowCount();
        while ($getrow = $getemployee->fetch()) {
            $empdata[]=$getrow;
        }
        if($count>0){
             echo json_encode(array("data"=> $empdata,"errcode"=>0));
        }else{
            echo json_encode(array("errcode"=>1));
        }
    }   


if (isset($_GET['amssearch'])){
        $query=$_GET['query']; 
        $getemployee = $pdo->prepare("Select * from amsarchive as a INNER JOIN employees as b on (a.verifiedby=b.EmpID) where a.lname like '%$query%' order by a.lname asc");
        $getemployee->execute();
        $count = $getemployee->rowCount();
        while ($getrow = $getemployee->fetch()) {
            $empdata[]=$getrow;
        }
        if($count>0){
             echo json_encode(array("data"=> $empdata,"errcode"=>0));
        }else{
            echo json_encode(array("errcode"=>1));
        }
    }
    //save 
    if (isset($_GET['save'])){
        $fname=$_GET['fname'];
        $lname=$_GET['lname'];
        $dfrom=$_GET['empfrom'];
        $dto=$_GET['empto'];
        $stat=$_GET['stat'];
        $clearance=$_GET['clearance'];
        $reason=$_GET['reason'];
        $derogatory=$_GET['derogatory'];
        $salary=$_GET['salary'];
        $resignation=$_GET['resignation'];
        $addrem=$_GET['addrem'];
        $addver=trim($_GET['addver']);
        $pos=$_GET['pos'];
         
        $sql = "INSERT INTO amsarchive (fname,lname,empdatesfrom,empdatesto,employmentstatus,reasonforleaving,derogatoryrecords,clearance,salary,addremarks,pedngingresignation,verifiedby,datetime,pos) 
                VALUES (:fname,:lname,:dfrom,:dto,:stat,:reason,:der,:clearance,:salary,:addremarks,:pendingres,:ver,:datetime,:pos)";
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':fname' , $fname);
               $stmt->bindParam(':lname' , $lname);
               $stmt->bindParam(':dfrom' , $dfrom);
               $stmt->bindParam(':dto' , $dto);
               $stmt->bindParam(':stat' , $stat);
               $stmt->bindParam(':reason' , $reason);
               $stmt->bindParam(':der' , $derogatory);
               $stmt->bindParam(':clearance' , $clearance);
               $stmt->bindParam(':salary' , $salary);
               $stmt->bindParam(':addremarks' , $addrem);
               $stmt->bindParam(':pendingres' , $resignation);
               $stmt->bindParam(':ver' , $addver);
               $stmt->bindParam(':datetime' , $today);
               $stmt->bindParam(':pos' , $pos);
               

               if($stmt->execute()){
                echo json_encode(array("errcode"=>0));
                $id=$_SESSION['id'];
                $ch="Register user " . $lname . " " .$fname . " by user " . $id . " on AMS!" ;
                // insert into dars
                $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id' , $id);
                $stmt->bindParam(':empact', $ch);
                $stmt->bindParam(':ddt', $today);
                $stmt->execute();
               }
               else{
                echo json_encode(array("errcode"=>1));  
               }
        }

        //update 
    if (isset($_GET['update'])){
        $id=$_GET['id'];
        $fname=$_GET['fname'];
        $lname=$_GET['lname'];
        $dfrom=$_GET['empfrom'];
        $dto=$_GET['empto'];
        $stat=$_GET['stat'];
        $clearance=$_GET['clearance'];
        $reason=$_GET['reason'];
        $derogatory=$_GET['derogatory'];
        $salary=$_GET['salary'];
        $resignation=$_GET['resignation'];
        $addrem=$_GET['addrem'];
        $addver=trim($_GET['addver']);
        $pos=$_GET['pos'];
         
         	$sql = "UPDATE amsarchive SET fname=:fname,lname=:lname,empdatesfrom=:dfrom,empdatesto=:dto, 
                                        employmentstatus=:stat,reasonforleaving=:reason,derogatoryrecords=:der,clearance=:clearance, 
                                        salary=:salary,addremarks=:addremarks,pedngingresignation=:pendingres,verifiedby=:ver,datetime=:datetime,pos=:pos
                                         where id=:id";      
               
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':id' , $id);
               $stmt->bindParam(':fname' , $fname);
               $stmt->bindParam(':lname' , $lname);
               $stmt->bindParam(':dfrom' , $dfrom);
               $stmt->bindParam(':dto' , $dto);
               $stmt->bindParam(':stat' , $stat);
               $stmt->bindParam(':reason' , $reason);
               $stmt->bindParam(':der' , $derogatory);
               $stmt->bindParam(':clearance' , $clearance);
               $stmt->bindParam(':salary' , $salary);
               $stmt->bindParam(':addremarks' , $addrem);
               $stmt->bindParam(':pendingres' , $resignation);
               $stmt->bindParam(':ver' ,$_SESSION['id']);
               $stmt->bindParam(':datetime' , $today);
               $stmt->bindParam(':pos' , $pos);

               if($stmt->execute()){
                echo json_encode(array("errcode"=>0));
                $id=$_SESSION['id'];
                $ch="Updated user " . $lname . " " .$fname . " by user " . $id . " on AMS." ;
                // insert into dars
                $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id' , $id);
                $stmt->bindParam(':empact', $ch);
                $stmt->bindParam(':ddt', $today);
                $stmt->execute();

               }else{
                echo json_encode(array("errcode"=>1));  
               }
        }
?>    