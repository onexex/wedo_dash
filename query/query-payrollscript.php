<?php 
include 'w_conn.php';

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
if (isset($_GET['approvedPayroll'])){
    $pydte=$_POST['pdate'];

        //fetch data 
    $getPayApproved = $pdo->prepare("Select * from payrol where PYDate=:pydate group by PYDate");
    $getPayApproved->bindParam(':pydate' , $pydte);
    $getPayApproved->execute();
    $getrow = $getPayApproved->fetch();
    $count = $getPayApproved->rowCount();

        //test if has data
    if ($count>0){
        if($getrow['hrApproved']==1){
            //validate data if approved
            echo json_encode(array("error"=>1));
        }
        else{  
            $valHR=1;
                //update the payroll date to approves                           
                $updatePayData = $pdo->prepare("Update payrol set hrApproved=:hr,approvedate=:approvedate  where PYDate=:pydates");
                $updatePayData->bindParam(':hr' , $valHR);
                $updatePayData->bindParam(':approvedate' , $today1);
                $updatePayData->bindParam(':pydates' , $pydte);
                $updatePayData->execute();
                echo json_encode(array("error"=>2));  
        }
    }
    else{
        //validate if no data found
        echo json_encode(array("error"=>0));
    }

}
if (isset($_GET['getemployee'])){
        $empdata=[];
        $empstats=1; 
        $getemployee = $pdo->prepare("Select * from employees where EmpStatusID=:stat order by EmpLN asc");
        $getemployee->bindParam(':stat' , $empstats);
        $getemployee->execute();
        $count = $getemployee->rowCount();
        while ($getrow = $getemployee->fetch()) {
            $empdata[]=$getrow;
        }
        if($count>0){
            echo json_encode(array("data"=>$empdata,"errcode"=>0,"cnt"=>$count));
        }else{
            $arr = array('data' => 0,"errcode"=>1);
            echo json_encode($arr); 
        }
    }
    
if (isset($_GET['cutoffdates'])) {
    $pdate=$_POST['vl'];
        $date = new DateTime($pdate);
        $getPdateDay=$date->format('d');
        $getPdatemonth=$date->format('m');

        $stmtdel = $pdo->prepare("Select * from cutoffinfo where PYDate=:pdate");
        $stmtdel->bindParam(':pdate' , $getPdateDay);
        $stmtdel->execute();
        $getrow = $stmtdel->fetch();
        $count = $stmtdel->rowCount();



        date("m-Y", strtotime("-1 months"));

        if($count>0){
            if($getPdateDay=="05"){
                $pdate1=$getPdatemonth -1;
            
                $cut1=$getrow['Cstart'];
                $cut11=date("Y-0".$pdate1."-".$cut1);
                $cut2=$getrow['Cend'];
                $cut22=date("Y-0".$pdate1."-t");
            }
            else{
                $x=0;
                $cut1=$getrow['Cstart'];
                $cut11=date("Y-".$getPdatemonth."-".$cut1);
                $cut2=$getrow['Cend'];
                $cut22=date("Y-".$getPdatemonth."-".$cut2);
            }          
            echo json_encode(array("cut1"=>$cut11,"cut2"=>$cut22,"errcode"=>1));
        } 
        else{
            $cut11=0;
            $cut22=0;
            echo json_encode(array("cut1"=>$cut11,"cut2"=>$cut22,"errcode"=>0));
        }
}  
if (isset($_GET['getadjustment'])) {
        $empdata=[];
        $pdate=$_POST['vl']; 

        $getadjustment2 = $pdo->prepare("Select * from adjusment2 inner join employees on (adjusment2.EmpID=employees.EmpID) where pdate=:stat order by EmpLN ");
        $getadjustment2->bindParam(':stat', $pdate);
        $getadjustment2->execute();
        $count = $getadjustment2->rowCount();
        while ($getrow = $getadjustment2->fetch()) {
            $empdata[]=$getrow;
        }
        if($count>0){
            echo json_encode(array("data"=>$empdata,"errcode"=>0,"cnt"=>$count));
        }else{
            $arr = array('data' => 0,"errcode"=>1);
            echo json_encode($arr); 
        }
}
if (isset($_GET['addadjustment'])) {
    $empID=$_POST['emp'];
    $empAmount=$_POST['amount'];
    $pdate=$_POST['pdate'];
    $sql = "INSERT INTO adjusment2 (EmpID,Amount,pdate) VALUES (:id,:amt,:pdate)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id' , $empID);
    $stmt->bindParam(':amt', $empAmount);
    $stmt->bindParam(':pdate', $pdate);
 
   if($stmt->execute()){
     echo json_encode(array("datas"=>$stmt,"statuscode"=>200));
   } else{
     echo json_encode(array("statuscode"=>201));
   }
}
if (isset($_GET['delAdjustment'])) {
    $id=$_POST['id'];
    $sql = "DELETE FROM adjusment2 WHERE id=:id;";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id' , $id);
 
   if($stmt->execute()){
     echo json_encode(array("data"=>$stmt,"statuscode"=>200));
   } else{
     echo json_encode(array("statuscode"=>201));
   }
}
?>