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
      
   if (isset($_GET['updateEffectivity'])){
    $id=$_POST['id'];
    $from=$_POST['dfrom'];
    $to=$_POST['dto'];

    $sql = "UPDATE schedeffectivity SET dfrom=:dfrom,dto=:dto
            where efids=:id";      

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id' , $id);
    $stmt->bindParam(':dfrom' , $from);
    $stmt->bindParam(':dto' , $to);

    if($stmt->execute()){
        echo json_encode(array("errcode"=>0));
    }else{
        echo json_encode(array("errcode"=>1));
    }
}


   if (isset($_GET['setEffectivity'])){
    $id=$_POST['id']; ;
    $sql = "Select * from schedeffectivity 
             where efids=:id";      

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id' , $id);

    if($stmt->execute()){
        while ($getrow = $stmt->fetch()) {
            $empdata[]=$getrow;
        }
        echo json_encode(array("errcode"=>0,"data"=>$empdata));
    }else{
        echo json_encode(array("errcode"=>1));
    }
}

//   if (isset($_GET['updateTimeNow'])){
//     $id=$_POST['id']; 
//     $timeid=$_POST['timeid'];
//     $sql = "UPDATE workdays SET SchedTime=:schedid
//      where WID=:id";      

//     $stmt = $pdo->prepare($sql);
//     $stmt->bindParam(':id' , $id);
//     $stmt->bindParam(':schedid' , $timeid);

//     if($stmt->execute()){
        
//         echo json_encode(array("errcode"=>0));
//     }else{
//         echo json_encode(array("errcode"=>1));
//     }
// }
    if (isset($_GET['updateTimeNow'])){
    $id=$_POST['id']; 
    $timeid=$_POST['timeid'];
    $sql = "UPDATE workdays SET SchedTime=:schedid
     where WID=:id";      

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id' , $id);
    $stmt->bindParam(':schedid' , $timeid);

    if($stmt->execute()){
                $id=$_SESSION['id'];
        // $ch="Updated Schedule to ".   $timeid  . " of employee " .$id;
                $ch="Updated Schedule to ". $_POST['me'] . " of employeeid " . $id;

    // insert into dars
     
        $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $id);
        $stmt->bindParam(':empact', $ch);
        $stmt->bindParam(':ddt', $today);
        $stmt->execute();
        echo json_encode(array("errcode"=>0));
       
    }else{
        echo json_encode(array("errcode"=>1));
    }
}

   if (isset($_GET['setupdatesched'])){
    $id=$_POST['id']; 
    $stmt = $pdo->prepare("SELECT * FROM workschedule ");
    $stmt->execute();
    $count = $stmt->rowCount();
        while ($getrow = $stmt->fetch()) {
            $empdata[]=$getrow;
        }

        if($count>0){
             $id=$_SESSION['id'];
        $ch="Updated Schedule ID" . $timeid ;
        $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $id);
        $stmt->bindParam(':empact', $ch);
        $stmt->execute(); 
            echo json_encode(array("data"=> $empdata,"errcode"=>0));
        }
        else{
            echo json_encode(array("errcode"=>1));
        }
}

   if (isset($_GET['viewsched'])){
    $query=$_POST['id']; 
    $stmt = $pdo->prepare("SELECT * FROM `workdays` as a left join
                        workschedule as b  ON a.SchedTime=b.WorkSchedID  where a.EFID='$query' order by a.WID desc" );
    $stmt->execute();
    $count = $stmt->rowCount();
        while ($getrow = $stmt->fetch()) {
            $empdata[]=$getrow;
        }
   
        if($count>0){
            echo json_encode(array("data"=> $empdata,"errcode"=>0));
        }
        else{
            echo json_encode(array("errcode"=>1));
        }
}

   if (isset($_GET['search'])){
    $query=$_GET['query']; 
    $getemployee = $pdo->prepare("SELECT * FROM `schedeffectivity` as a inner join
                        workdays as b on a.efids=b.EFID inner join 
                        employees as c on b.empid=c.EmpID
                        where EmpLN like '%$query%'
                        group by b.EFID order by b.WID desc");
    $getemployee->execute();
    $count = $getemployee->rowCount();
        while ($getrow = $getemployee->fetch()) {
            $empdata[]=$getrow;
        }
   
        if($count>0){
            echo json_encode(array("data"=> $empdata,"errcode"=>0));
        }
        else{
            echo json_encode(array("errcode"=>1));
        }
}

   if (isset($_GET['loaddata'])){
        $getemployee = $pdo->prepare("SELECT * FROM `employees` where EmpStatusID='1' order by EmpLN");
        $getemployee->execute();
        $count = $getemployee->rowCount();
            while ($getrow = $getemployee->fetch()) {
                $empdata[]=$getrow;
            }
        $getSched = $pdo->prepare("SELECT * FROM `workschedule`");
        $getSched->execute();
        $countsched = $getSched->rowCount();
            if($countsched>0){
                while ($rowsched= $getSched->fetch()) {
                    $schedData[]=$rowsched;
                }
            }
            
            if($count>0){
                echo json_encode(array("data"=> $empdata,"errcode"=>0,"data2"=>$schedData));
            }
            else{
                echo json_encode(array("errcode"=>1));
            }
   }

   if (isset($_GET['save'])){
        $employee=$_POST['employee'];
        $dfrom=$_POST['dfrom'];
        $dto=$_POST['dto'];

        $monday=$_POST['monday'];
        $tuesday=$_POST['tuesday'];
        $wednesday=$_POST['wednesday'];
        $thursday=$_POST['thursday'];
        $friday=$_POST['friday'];
        $saturday=$_POST['saturday'];
        $sunday=$_POST['sunday'];

        $daymon="Monday";
        $daytues="Tuesday";
        $daywed="Wednesday";
        $daythurs="Thursday";
        $dayfri="Friday";
        $daysat="Saturday";
        $daysun="Sunday";

        $sql_schedeffectivity = "INSERT INTO schedeffectivity (dfrom,dto) 
                VALUES (:dfrom,:dto)";
                $stmt = $pdo->prepare($sql_schedeffectivity);
                $stmt->bindParam(':dfrom' , $dfrom);
                $stmt->bindParam(':dto' , $dto);
                $stmt->execute();
        $lastinsertedid= $pdo->lastInsertId();
        //sunday
        $sql = "INSERT INTO workdays (empid,Day_s,SchedTime,EFID) VALUES (:id,:day_s,:schedtime,:efid)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $employee);
        $stmt->bindParam(':day_s' , $daysun);
        $stmt->bindParam(':schedtime' , $sunday);
        $stmt->bindParam(':efid' , $lastinsertedid);
        $stmt->execute();
        //saturday
        $sql = "INSERT INTO workdays (empid,Day_s,SchedTime,EFID) VALUES (:id,:day_s,:schedtime,:efid)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $employee);
        $stmt->bindParam(':day_s' , $daysat);
        $stmt->bindParam(':schedtime' , $saturday);
        $stmt->bindParam(':efid' , $lastinsertedid);
        $stmt->execute();
         //friday
         $sql = "INSERT INTO workdays (empid,Day_s,SchedTime,EFID) VALUES (:id,:day_s,:schedtime,:efid)";
         $stmt = $pdo->prepare($sql);
         $stmt->bindParam(':id' , $employee);
         $stmt->bindParam(':day_s' , $dayfri);
         $stmt->bindParam(':schedtime' , $friday);
         $stmt->bindParam(':efid' , $lastinsertedid);
         $stmt->execute();
        //thursday
        $sql = "INSERT INTO workdays (empid,Day_s,SchedTime,EFID) VALUES (:id,:day_s,:schedtime,:efid)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $employee);
        $stmt->bindParam(':day_s' , $daythurs);
        $stmt->bindParam(':schedtime' , $thursday);
        $stmt->bindParam(':efid' , $lastinsertedid);
        $stmt->execute();
         //wednesday
         $sql = "INSERT INTO workdays (empid,Day_s,SchedTime,EFID) VALUES (:id,:day_s,:schedtime,:efid)";
         $stmt = $pdo->prepare($sql);
         $stmt->bindParam(':id' , $employee);
         $stmt->bindParam(':day_s' , $daywed);
         $stmt->bindParam(':schedtime' , $wednesday);
         $stmt->bindParam(':efid' , $lastinsertedid);
         $stmt->execute();
        //tuesday
        $sql = "INSERT INTO workdays (empid,Day_s,SchedTime,EFID) VALUES (:id,:day_s,:schedtime,:efid)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $employee);
        $stmt->bindParam(':day_s' , $daytues);
        $stmt->bindParam(':schedtime' , $tuesday);
        $stmt->bindParam(':efid' , $lastinsertedid);
        $stmt->execute();
        //monday
        $sql = "INSERT INTO workdays (empid,Day_s,SchedTime,EFID) VALUES (:id,:day_s,:schedtime,:efid)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $employee);
        $stmt->bindParam(':day_s' , $daymon);
        $stmt->bindParam(':schedtime' , $monday);
        $stmt->bindParam(':efid' , $lastinsertedid);
        $stmt->execute();
        echo "Successfully save!";
    }
   ?>    