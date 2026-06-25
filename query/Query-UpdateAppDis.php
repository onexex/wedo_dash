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
   $tdy= date("Y-m-d H:i:s"); 
   $rss=$_POST['reas'];
   if ($_SESSION['UserType']==3){
      $stat = 3;

   }else if ($_SESSION['UserType']==2){
         $stat = 3;

   }else{
      $stat = 5;
   }

   // Validate the record id once (all ntypes use an integer PK)
   $rawId = $_GET['id'] ?? null;
   if ($rawId === null || !ctype_digit((string)$rawId)) {
       http_response_code(400);
       echo json_encode(array("error" => "bad id"));
       return;
   }
   $leaveId = (int)$rawId;

   if (isset($_GET['ntype'])){
   	if ($_GET['ntype']=="EO"){

    if ($_SESSION['UserType']==1){
          	$sql = "UPDATE earlyout SET Status=:st,HR_remark=:rs,DateTimeUpdated=:dtu where SID=:id";
    }else{
          	$sql = "UPDATE earlyout SET Status=:st,IS_remark=:rs,DateTimeUpdated=:dtu where SID=:id";
    }
		$stmt = $pdo->prepare($sql);
    $stmt->bindParam(':st' ,$stat);
	  $stmt->bindParam(':rs' ,$rss);
    $stmt->bindParam(':dtu' ,$tdy);
  	$stmt->bindParam(':id' ,$leaveId);
  	$stmt->execute();

            $sql2="select employees.EmpLN as LN,employees.EmpFN as FN from employees inner join earlyout on employees.EmpID=earlyout.EMPID where SID=:id";
            $stmt = $pdo->prepare($sql2);
            $stmt->bindParam(':id' ,$leaveId);
            $stmt->execute();
            $row=$stmt->fetch();
            $nameE=$row['FN'] . " " . $row['LN'];


                      $id=$_SESSION['id'];
                      $ch="DisApproved EO of " . $nameE;
                      // insert into dars
                      $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                      $stmt = $pdo->prepare($sql);
                      $stmt->bindParam(':id' , $id);
                      $stmt->bindParam(':empact', $ch);
                      $stmt->bindParam(':ddt', $tdy);
                      $stmt->execute(); 


   	}
   	else if($_GET['ntype']=="OB"){
   	      if ($_SESSION['UserType']==1){
   	   
          $sql2 = "UPDATE obshbd SET OBStatus=:st,OBHRReason=:rs,OBUpdated=:dtu where OBID=:id";
   		  $sql = "UPDATE obs SET OBStatus=:st,OBHRReason=:rs,OBUpdated=:dtu where OBID=:id";
   	     }else{

          $sql2 = "UPDATE obshbd SET OBStatus=:st,OBISReason=:rs,OBUpdated=:dtu where OBID=:id";
   		  $sql = "UPDATE obs SET OBStatus=:st,OBISReason=:rs,OBUpdated=:dtu where OBID=:id";
   	     }
   	    $stmt2 = $pdo->prepare($sql2);
        $stmt2->bindParam(':st' ,$stat);
        $stmt2->bindParam(':rs' ,$rss);
        $stmt2->bindParam(':dtu' ,$tdy);
        $stmt2->bindParam(':id' ,$leaveId);
        $stmt2->execute();


		$stmt = $pdo->prepare($sql);
        $stmt->bindParam(':st' ,$stat);
        $stmt->bindParam(':rs' ,$rss);
        $stmt->bindParam(':dtu' ,$tdy);
        $stmt->bindParam(':id' ,$leaveId);
		$stmt->execute();

            $sql2="select employees.EmpLN as LN,employees.EmpFN as FN from employees inner join obs on employees.EmpID=obs.EmpID where OBID=:id";
            $stmt = $pdo->prepare($sql2);
            $stmt->bindParam(':id' ,$leaveId);
            $stmt->execute();
            $row=$stmt->fetch();
            $nameE=$row['FN'] . " " . $row['LN'];

     $id=$_SESSION['id'];
                       $ch="DisApproved OB of " . $nameE;
                  // insert into dars
                     $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                      $stmt = $pdo->prepare($sql);
                      $stmt->bindParam(':id' , $id);
                      $stmt->bindParam(':empact', $ch);
                      $stmt->bindParam(':ddt', $tdy);
                     $stmt->execute(); 
   	}
   		else if($_GET['ntype']=="HL"){
    // Authorization guard: only an approver acting within scope may disapprove this leave
    $g = $pdo->prepare("SELECT h.EmpID, h.EmpSID, h.LStatus, e.EmpCompID
        FROM hleaves h INNER JOIN empdetails e ON e.EmpID = h.EmpID WHERE h.LeaveID = :id");
    $g->execute([':id' => $leaveId]);
    $lv = $g->fetch();
    if (!$lv) { http_response_code(404); echo json_encode(array("error"=>"leave not found")); return; }
    if ($_SESSION['UserType'] != 1 && $_SESSION['UserType'] != 2) {
        echo json_encode(array("error"=>"not authorized")); return;
    }
    if ($_SESSION['UserType'] == 2 && $lv['EmpSID'] !== $_SESSION['id']) {
        echo json_encode(array("error"=>"not your subordinate")); return;
    }
    if ($_SESSION['UserType'] == 1 && $lv['EmpCompID'] !== $_SESSION['CompID']) {
        echo json_encode(array("error"=>"outside your company")); return;
    }

   if ($_SESSION['UserType']==1){
       	$sql = "UPDATE hleaves SET LStatus=:st,LHRReason=:rs,LDateTimeUpdated=:dtu where LeaveID=:id";
   }else{
   		$sql = "UPDATE hleaves SET LStatus=:st,LISReason=:rs,LDateTimeUpdated=:dtu where LeaveID=:id";
   }
		$stmt = $pdo->prepare($sql);
    $stmt->bindParam(':st' ,$stat);
    $stmt->bindParam(':rs' ,$rss);
    $stmt->bindParam(':dtu' ,$tdy);
    $stmt->bindParam(':id' ,$leaveId);
		$stmt->execute();

    $sql = "UPDATE hleavesbd set LStatus=:st,LISReason=:rs,LDateTimeUpdated=:dtu where FID=:lid";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':st' ,$stat);
    $stmt->bindParam(':rs' ,$rss);
    $stmt->bindParam(':dtu' ,$tdy);
    $stmt->bindParam(':lid' ,$leaveId);
    $stmt->execute();

    $sql2="select employees.EmpLN as LN,employees.EmpFN as FN from employees inner join hleaves on employees.EmpID=hleaves.EmpID where LeaveID=:id";
    $stmt = $pdo->prepare($sql2);
    $stmt->bindParam(':id' ,$leaveId);
    $stmt->execute();
    $row=$stmt->fetch();
    $nameE=$row['FN'] . " " . $row['LN'];

    $id=$_SESSION['id'];
    $ch="DisApproved Leaves of " . $nameE;
  // insert into dars
     $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                      $stmt = $pdo->prepare($sql);
                      $stmt->bindParam(':id' , $id);
                      $stmt->bindParam(':empact', $ch);
                      $stmt->bindParam(':ddt', $tdy);
    $stmt->execute(); 
   	
    }
    else if($_GET['ntype']=="OT"){
          if ($_SESSION['UserType']==1){
              
             $sql = "UPDATE otattendancelog SET Status=:st,HRReason=:rs,DateTimeUpdate=:dtu where OTLOGID=:id";
          }else{

      $sql = "UPDATE otattendancelog SET Status=:st,ISReason=:rs,DateTimeUpdate=:dtu where OTLOGID=:id";
          }
     $stmt = $pdo->prepare($sql);
     $stmt->bindParam(':st' ,$stat);
      $stmt->bindParam(':rs' ,$rss);
      $stmt->bindParam(':dtu' ,$tdy);
     $stmt->bindParam(':id' ,$leaveId);
    $stmt->execute();

         $sql2="select employees.EmpLN as LN,employees.EmpFN as FN from employees inner join otattendancelog on employees.EmpID=otattendancelog.EmpID where OTLOGID=:id";
            $stmt = $pdo->prepare($sql2);
            $stmt->bindParam(':id' ,$leaveId);
            $stmt->execute();
            $row=$stmt->fetch();
            $nameE=$row['FN'] . " " . $row['LN'];

     $id=$_SESSION['id'];
                       $ch="DisApproved OT of " . $nameE;
                  // insert into dars
                      $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                      $stmt = $pdo->prepare($sql);
                      $stmt->bindParam(':id' , $id);
                      $stmt->bindParam(':empact', $ch);
                      $stmt->bindParam(':ddt', $tdy);
                     $stmt->execute(); 
    }
   }
?>
