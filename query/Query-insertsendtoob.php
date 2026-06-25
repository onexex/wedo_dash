<?php 
  include 'w_conn.php';
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
   date_default_timezone_set("Asia/Manila");
    try{
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }catch(PDOException $e){
        die("ERROR: Could not connect. " . $e->getMessage());
    }
  
   //admin time validation
    ################################################
  $datenow = date("Y-m-d");
  $datenow1 = date("Y-m-d H:i");
  $timenow = strtotime($datenow1);
  $startTime = strtotime($datenow ." 8:30:00");
  $id=$_SESSION['id'];
  if($id=="WeDoinc-012"){
  }else{
  if($timenow > $startTime){
    echo 4;
    return;
    }
  }
 ################################################

    $todaydar = date("Y-m-d H:i:s");

    $id=$_SESSION['id'];

    $isid=$_SESSION['EmpISID'];
    //   if employee role
    if ($_SESSION['UserType']==1){
        $statid=4;
        $obtype=2;
        $ESID= $_POST['empidob'];
    }else{
        if ($_POST['empidob']==$_SESSION['id']){
            $ESID= $_POST['empidob'];
            $statid=1;
            $obtype=1;
            $id=$_SESSION['EmpISID'];
        }else{
            $ESID=$_POST['empidob'];
            $statid=2;
            $obtype=2;
        }
    }

    $day_desc=date("l", strtotime($_POST['obdf']));  
    //   $workschedst = $pdo->prepare("select * from workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID where workdays.empid=:id and workdays.Day_s=:daydesc");
    $dstarts=date("Y-m-d", strtotime($_POST['obdf']));
    //  $workschedst = $pdo->prepare("select * from workdays INNER JOIN workschedule 
    //                               ON workdays.SchedTime=workschedule.WorkSchedID 
    //                               where workdays.empid=:id and workdays.Day_s=:daydesc");
    $workschedst = $pdo->prepare("Select * from workdays INNER JOIN workschedule 
        ON workdays.SchedTime=workschedule.WorkSchedID 
        inner join schedeffectivity as c 
        on workdays.EFID=c.efids
        where (workdays.empid=:id) and (workdays.Day_s=:daydesc)
        and ('$dstarts' >= dfrom) and ('$dstarts' <= dto)");
    $workschedst->bindParam(':id' , $ESID);
    $workschedst->bindParam(':daydesc' ,$day_desc);
    $workschedst->execute();
    $rowwrktime=$workschedst->fetch();
     $ifNoAttendance=$workschedst->rowCount(); 

    if($ifNoAttendance==0){
        echo 3;
        return;
    }

    $wrktimein = $rowwrktime['TimeFrom'];
    $wrktimeout = $rowwrktime['TimeTo'];

    $filetimefrom =  date("H:i", strtotime($_POST['depart']));  
    $filetimeto =  date("H:i", strtotime($_POST['return']));
    $dbtfrom =  date("H:i", strtotime($wrktimein));
    $dbtto =  date("H:i", strtotime($wrktimeout));

    if ($filetimefrom<$dbtfrom or $filetimefrom>$dbtto or  $filetimeto>$dbtto or $filetimeto<$dbtfrom){
        echo 3;
        return;
    }

    $today =date("Y-m-d"); 
    $today2 =date("Y-m-d H:i:s"); 
    $dteStart = new DateTime($_POST['obdf']. ' ' .$_POST['depart']); 
    $dteEnd   = new DateTime($_POST['obdt']. ' ' .$_POST['return']); 
    $dteDiff  = $dteStart->diff($dteEnd); 
    $interval = $dteDiff->format("%H");

    $stmtcheckob = $pdo->prepare("select * from obs where EmpID=:id and (OBDateFrom=:odfrom or OBDateTo=:odto or OBDateFrom=:odto or OBDateTo=:odfrom or (OBDateFrom<:odfrom and OBDateTo>:odfrom) or (OBDateFrom<:odto and OBDateTo>:odto))  and (OBStatus=1 or OBStatus=2 or OBStatus=4)");
    $stmtcheckob->bindParam(':id' , $ESID);
    $stmtcheckob->bindParam(':odfrom' ,$_POST['obdf']);
    $stmtcheckob->bindParam(':odto' ,$_POST['obdt']);
    $stmtcheckob->execute();
    $rwCount=$stmtcheckob->rowCount();
    if ($rwCount>0){
        while($rwftch=$stmtcheckob->fetch()){
            $dtfromob=$rwftch['OBTimeFrom'];
            $dttoob=$rwftch['OBTimeTo'];
            if ($dtfromob<=$_POST['depart'] && $dttoob>=$_POST['depart']){
                // cant file because within date
                echo 2;
                return;
            }elseif($dtfromob<=$_POST['return'] && $dttoob>=$_POST['return']){
                echo 2;
                return;
            }elseif($_POST['depart']<=$dtfromob && $_POST['return']>=$dtfromob){
                echo 2;
                return;
            }elseif($_POST['depart']<=$dttoob && $_POST['return']>=$dttoob){
                echo 2;
                return;
            }
        }
    }


    // insert into ob
    $sql = "INSERT INTO obs (EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBType,OBInputDate) 
        VALUES (:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:obtt,:ipd)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id' , $ESID);
    $stmt->bindParam(':is', $id);
    $stmt->bindParam(':fd' ,$today);
    $stmt->bindParam(':odfrom' ,$_POST['obdf']);
    $stmt->bindParam(':odto' ,$_POST['obdt']);
    $stmt->bindParam(':oifrom' ,$_POST['itfrom']);
    $stmt->bindParam(':oduration' ,$interval);
    $stmt->bindParam(':oito' ,$_POST['itto']);
    $stmt->bindParam(':opurpose' ,$_POST['emppurpose']);
    $stmt->bindParam(':otfrom' ,$_POST['depart']);
    $stmt->bindParam(':otto' ,$_POST['return']);
    $stmt->bindParam(':ocamt' ,$_POST['ca']);
    $stmt->bindParam(':obcp' ,$_POST['capurpose']);
    $stmt->bindParam(':obs' ,$statid);
    $stmt->bindParam(':obup' ,$today2);
    $stmt->bindParam(':obtt' ,$obtype);
    $stmt->bindParam(':ipd' ,$today2);
    $stmt->execute(); 

    //   $id=$_POST['empidob'];
    //   $ch="You've Sent to OB";
    // // insert into dars
    //   $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
    //   $stmt = $pdo->prepare($sql);
    //   $stmt->bindParam(':id' , $id);
    //   $stmt->bindParam(':empact', $ch);
    //   $stmt->bindParam(':ddt', $todaydar);
    //   $stmt->execute(); 

    // $id=$_SESSION['id'];
    // $ch="Applied Send to OB for" . $ESID;
    // // insert into dars
    // $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
    // $stmt = $pdo->prepare($sql);
    // $stmt->bindParam(':id' , $id);
    // $stmt->bindParam(':empact', $ch);
    // $stmt->bindParam(':ddt', $todaydar);
    // $stmt->execute();
    
        $statementGet = $pdo->prepare("SELECT * FROM employees where EmpID =  :dd");
    $statementGet->bindParam(':dd' ,$ESID);

    $statementGet->execute();
    $rowGet=$statementGet->fetch();
    if ($statementGet->rowCount()>0){
        $dd= $rowGet['EmpLN'] . ' ' . $rowGet['EmpFN'];
        // $ESID= "dfjghdfgjdfk";
    }

    // $id=$_SESSION['id'];
    $ch="Applied Send to OB " . $dd;
    // insert into dars
    $sqls = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
    $stmt23 = $pdo->prepare($sqls);
    $stmt23->bindParam(':id' , $id);
    $stmt23->bindParam(':empact', $ch);
    $stmt23->bindParam(':ddt', $todaydar);
    $stmt23->execute();
    
        //                     $statement = $pdo->prepare("SELECT * from obs WHERE EmpID=:id and EmpSID=:is and OBType=2 and OBDuration=:oduration and OBFD=:fd and OBPurpose=:opurpose and OBDateFrom=:odfrom and OBDateTo=:odto and OBTimeFrom=:otfrom and OBTimeTo=:otto");
        //                     $statement->bindParam(':id' , $_POST['empidob']);
        //                     $statement->bindParam(':is', $id);
        //                     $statement->bindParam(':oduration' ,$interval);
        //                     $statement->bindParam(':odfrom' ,$_POST['obdf']);
        //                     $statement->bindParam(':odto' ,$_POST['obdt']);
        //                     $statement->bindParam(':fd' ,$today);
        //                     $statement->bindParam(':opurpose' ,$_POST['emppurpose']);
        //                     $statement->bindParam(':otfrom' ,$_POST['depart']);
        //                     $statement->bindParam(':otto' ,$_POST['return']);
        //                     $statement->execute();
        //                     $row21 = $statement->fetch();
        //                     $obsid=$row21[0];
        
        // $obty = "STOB";
        // $dsc= "Send To Official Business Trip";
        // $sql = "INSERT INTO notifications (NotifType,NotifID,SenderID,RecieverID,Description,Status) 
        // VALUES (:obt,:obtid,:sid,:id,:descr,1)";
        // $stmt = $pdo->prepare($sql);
        // $stmt->bindParam(':obt', $obty);
        // $stmt->bindParam(':obtid', $obsid);
        // $stmt->bindParam(':sid', $id);
        // $stmt->bindParam(':descr', $dsc);
        // $stmt->bindParam(':id' , $_POST['empidob']);
        // $stmt->execute(); 

    $statement = $pdo->prepare("SELECT max(OBID) FROM obs ");
    $statement->execute();
    $roww=$statement->fetch();
    if ($statement->rowCount()>0){
        $maxid= $roww[0];
    }else{
        $maxid= 1;
    }
    $fkeyid=$maxid;
    $obdf = $_POST['obdf'];
    $obdt = $_POST['obdt'];

    if ($obdf==$obdt){
        // insert into dars
        $sql = "INSERT INTO obshbd (OBID,EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBType,OBInputDate) 
        VALUES (:oid,:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:obtt,:ipd)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':oid' , $fkeyid);
        $stmt->bindParam(':id' , $ESID);
        $stmt->bindParam(':is', $id);
        $stmt->bindParam(':fd' ,$today);
        $stmt->bindParam(':odfrom' ,$_POST['obdf']);
        $stmt->bindParam(':odto' ,$_POST['obdt']);
        $stmt->bindParam(':oifrom' ,$_POST['itfrom']);
        $stmt->bindParam(':oduration' ,$interval);
        $stmt->bindParam(':oito' ,$_POST['itto']);
        $stmt->bindParam(':opurpose' ,$_POST['emppurpose']);
        $stmt->bindParam(':otfrom' ,$_POST['depart']);
        $stmt->bindParam(':otto' ,$_POST['return']);
        $stmt->bindParam(':ocamt' ,$_POST['ca']);
        $stmt->bindParam(':obcp' ,$_POST['capurpose']);
        $stmt->bindParam(':obs' ,$statid);
        $stmt->bindParam(':obup' ,$today2);
        $stmt->bindParam(':obtt' ,$obtype);
        $stmt->bindParam(':ipd' ,$today2);
        $stmt->execute(); 
    }else{
        $dtstartob=$obdf;
        $dtendob=$obdt;

        while ($dtstartob<=$dtendob) {
            $day_desc = date ("l", strtotime($dtstartob));
            $dtstartob1 = date ("Y-m-d", strtotime($dtstartob));

            // $statementleaveadd = $pdo->prepare("select * from workdays where empid='$ESID' and Day_s='$day_desc'and SchedTime<>0 ");
            
            $statementleaveadd  = $pdo->prepare("Select * from workdays INNER JOIN                                                                           //update 7/31/2024
            workschedule ON workdays.SchedTime=workschedule.WorkSchedID 
            inner join schedeffectivity as c on workdays.EFID=c.efids
            where (workdays.empid='$ESID') and (workdays.Day_s='$day_desc') and ('$dtstartob1' >= dfrom) and ('$dtstartob1 ' <= dto)  and (SchedTime > 0)");
            
            $statementleaveadd->execute();
            
            if ($statementleaveadd->rowCount()>0){  
                // insert into dars
                $sql = "INSERT INTO obshbd (OBID,EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBType,OBInputDate) 
                    VALUES (:oid,:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:obtt,:ipd)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':oid' , $fkeyid);
                $stmt->bindParam(':id' , $ESID);
                $stmt->bindParam(':is', $id);
                $stmt->bindParam(':fd' ,$today);
                $stmt->bindParam(':odfrom' ,$dtstartob);
                $stmt->bindParam(':odto' ,$dtstartob);
                $stmt->bindParam(':oifrom' ,$_POST['itfrom']);
                $stmt->bindParam(':oduration' ,$interval);
                $stmt->bindParam(':oito' ,$_POST['itto']);
                $stmt->bindParam(':opurpose' ,$_POST['emppurpose']);
                $stmt->bindParam(':otfrom' ,$_POST['depart']);
                $stmt->bindParam(':otto' ,$_POST['return']);
                $stmt->bindParam(':ocamt' ,$_POST['ca']);
                $stmt->bindParam(':obcp' ,$_POST['capurpose']);
                $stmt->bindParam(':obs' ,$statid);
                $stmt->bindParam(':obup' ,$today2);
                $stmt->bindParam(':obtt' ,$obtype);
                $stmt->bindParam(':ipd' ,$today2);
                $stmt->execute();
            }
            $dtstartob = date ("Y-m-d", strtotime($dtstartob. "+1 day"));
        }
    } 
 ?>