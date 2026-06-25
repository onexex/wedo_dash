<?php 

    // DATABASE CONNECTION 
    {
      include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }
      date_default_timezone_set("Asia/Manila"); 

      if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
      else{ header ('location: login.php'); }

      try{
        $customTime = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('P');
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET time_zone='$customTime';");
      }
      catch(PDOException $e)
      {
        die("ERROR: Could not connect. " . $e->getMessage());
      }
    }
    
    // Determine the subject employee. By default the filer files for themselves;
    // an immediate superior may file on behalf of a direct report.
    {
      $loggedInId = $_SESSION['id'];
      $targetId   = (isset($_POST['target_empid']) && $_POST['target_empid'] !== '') ? $_POST['target_empid'] : $loggedInId;
      $onBehalf   = ($targetId !== $loggedInId);
      $targetSuperiorId = $_SESSION['EmpISID'];

      if ($onBehalf) {
          // Authorize: the logged-in user must be the immediate superior of the target.
          $authStmt = $pdo->prepare("SELECT EmpISID FROM empdetails WHERE EmpID = :tid");
          $authStmt->execute([':tid' => $targetId]);
          $authRow = $authStmt->fetch();
          if (!$authRow || $authRow['EmpISID'] !== $loggedInId) {
              echo "You are not authorized to file a leave for this employee.";
              return;
          }
          $targetSuperiorId = $authRow['EmpISID'];
      }
    }

     //admin time validation (on-behalf filing is exempt from the 8:30 AM cutoff)
  {
     $datenow = date("Y-m-d");
     $datenow1 = date("Y-m-d H:i");
     $timenow = strtotime($datenow1);
     $startTime = strtotime($datenow ." 8:30:00");
     $id=$_SESSION['id'];
     if($id=="WeDoinc-012" || $onBehalf){
     }else{
         if($timenow > $startTime){
            echo "The leave application must be completed on or before 8:30 AM.";
            return;
        }
     }
  }

    #declaration
    {
      global $ifPayroll;
      $companyid=$_SESSION['CompID'];
      $id=$targetId;
      $isid=$targetSuperiorId;
      $statid=1;
      $statiddiss=3;
      $today =date("Y-m-d"); 
      $today2 =date("Y-m-d H:i:s");
      $used = "used";
      $newdurationleave=$_POST['leavedur'];
      $start=$_POST['lstarts'];
      $end=$_POST['lenddate'];
      $dteStart = new DateTime($_POST['lstarts']);
      $dteEnd   = new DateTime($_POST['lenddate']); 
      $lfdate = new DateTime($_POST['lfdates']);

      $dteDiff  = $dteStart->diff($dteEnd); 
      $numdays = ($dteDiff->format("%a")) + 1;
      $ltype = $_POST['leavetype'];

      if($_POST['leavetype']==22){
          $_POST['leavetype'] = $_POST['leave_subcategory'];
      }
      
      $filingTOstart  = $lfdate->diff($dteStart); 
      $numdays_frm_fdate_sdate = $filingTOstart->format("%D");
      
      //for validation
      $dteStartValid=date("Y-m-d",strtotime($_POST['lstarts']));
      $dteEndValid=date("Y-m-d",strtotime($_POST['lenddate']));
      $ifLacking=0;
    }

       {//get the leave credit (15 or 10)
          $varTH=0;
          $varCT=0;
        
          $getTH = "select * from credit where EmpID = :id";
          $stmtTH = $pdo->prepare($getTH);
          $stmtTH->bindParam(':id',$id );
          $stmtTH->execute();
          $crdetailTH = $stmtTH->fetch();
          $crcntTH = $stmtTH->rowCount();

          if ($crcntTH > 0) {
            // if( $crdetailTH['CTH']==15){
                $varTH= $crdetailTH['CTH'];
                $varCT= $crdetailTH['CT'];
            // }
          }    
    }
    //validate if leave type is maternity or paternity
    {
      
       if($id=="WeDoinc-0145"){
          
       }else{
          if (in_array($_POST['leavetype'], [22, 38, 24, 30])) {
            if ($varCT < $newdurationleave && $_POST['leavepay']==1) {
                echo "You don't have enough leave credits.";
                return;
            }
          }
       }


    }
    //validate if schedule is not correct
    {    
      while ($dteEndValid>=$dteStartValid){
        $day_descValid = date ("l", strtotime($dteStartValid));
        $statement = $pdo->prepare("SELECT * FROM workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID 
        INNER JOIN schedeffectivity AS c ON workdays.EFID=c.efids 
        WHERE (workdays.empid='$id') AND (workdays.Day_s='$day_descValid') AND ('$dteStartValid' >= dfrom) AND ('$dteStartValid' <= dto) ");
        $statement->execute();
          if ($statement->rowCount()>0 ){}
          else{ $ifLacking= $ifLacking+1; }
          $dteStartValid = date ("Y-m-d", strtotime($dteStartValid. "+1 day"));
      }
    }

    if( $ifLacking >0){
      echo "Missing Schedule. Please contact your systems administrator.";
      return ;
    }

    // if leave date is already filled
    {
      $statement = $pdo->prepare(" SELECT * FROM hleaves WHERE EmpID=:id AND ((LStart BETWEEN :dts AND :dte) OR (LEnd BETWEEN :dts AND :dte)) AND (LStatus=1 OR LStatus=2 OR LStatus=4 OR LStatus=8 OR LStatus=9)");
      $statement->bindParam(':id' , $id);
      $statement->bindParam(':dts' , $_POST['lstarts']);
      $statement->bindParam(':dte' , $_POST['lenddate']);
      $statement->execute();
      if ($statement->rowCount()>0){
        echo "You have already applied in this inclusive date ";
        return;
      }
    }

    // Missing Regularization Date validation
    if ($_POST['lcredit']=="Missing Regularization Date"){
      echo "Missing Regularization Date.";
      return;
    }
    
    if (empty($_POST['leavepay']) || $_POST['leavepay'] == 0) {
        echo "Missing Schedule. Please contact your systems administrator.";
        return;
    }

    // Employee status check
    {
      $statement = $pdo->prepare(" SELECT * FROM empdetails WHERE EmpID=:id");
      $statement->bindParam(':id' , $id);
      $statement->execute();
      $EmpRow=$statement->fetch();

      if ($EmpRow['EmpStatID']<>1 and $_POST['leavepay']==1){
        echo "You cant apply leave pay ";
        return;   
      }
      else if($statement->rowCount()<1) {
        echo "You cant Apply Leave Pay ";
        return; 
      }
    }

    // if leave kind is paid 
    if ($_POST['leavepay']==1){
      $statement = $pdo->prepare(" SELECT * FROM leaves INNER JOIN leaves_validation ON leaves.LeaveID=leaves_validation.lid WHERE leaves_validation.lid=:lid AND compid=:id");
      $statement->bindParam(':id' , $companyid);
      $statement->bindParam(':lid' , $_POST['leavetype']);
      $statement->execute();
      $valid = $statement->fetch();
      if ($statement->rowCount()>0){
        $vl_leave_id=$valid['lid']; $vl_credits=$valid['leave_credits']; $vl_short=$valid['leave_short'];
        $vl_long=$valid['leave_long']; $vl_before=$valid['leave_before']; $vl_after=$valid['leave_file_after'];
        $vl_min=$valid['leave_min']; $vl_duration_after=$valid['filing_after_duration'];
        $vl_duration_before=$valid['filing_before_duration']; $vl_max_day=$valid['max_days_before'];
        $vl_duringfile=$valid['file_during']; $vl_halfday=$valid['IsHalfDay'];
      }else{
        echo "No Leave Data Found ! ";
        return;
      }

      if(($_POST['leavetype'] == 29 || $_POST['leavetype'] == 27) && $vl_credits <> $_POST['leavedur']){
          echo "Invalid Duration for Special Leave";
          return;
      }

      // Restday Calculation
      $num_off=0;
      if($numdays>1){
        $ctr=0;
        while ($numdays>$ctr){
          $day_desc = date ("l", strtotime($_POST['lstarts']. "+".$ctr." day"));
          $statement = $pdo->prepare("SELECT * FROM workdays WHERE empid='$id' AND Day_s='$day_desc' AND SchedTime='0' ");
          $statement->execute(); 
          if($statement->rowCount()>0){ $num_off=$num_off+1; }
          $ctr=$ctr+1;
        } 
      }
      $newduration=$numdays-$num_off;
      $dtd=date("Y-m-d",strtotime($_POST['lstarts']));
      $dateend=date("Y-m-d",strtotime($_POST['lenddate']));

      // Leave Timing Logic
      if ($today>=$dtd){
          $date1=date_create($today); $date2=date_create($dateend);
          $diff=date_diff($date1,$date2);
          $DaysAL = floatval($diff->format('%a')); 
          $cnt=0; $DaysLogin=0;
          while ($today>$dateend){
            $day_desc = date ("l", strtotime($dateend));
            $statement = $pdo->prepare("SELECT * FROM workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID INNER JOIN 
            schedeffectivity AS c ON workdays.EFID=c.efids WHERE (workdays.empid='$id') AND (workdays.Day_s='$day_desc') AND ('$today' >= dfrom) AND ('$today' <= dto) AND SchedTime <> 0 ");
            $statement->execute();
            $dateend = date ("Y-m-d", strtotime($dateend. "+1 day"));
            if ($statement->rowCount()>0 && $today>=$dateend){
              $cnt=$cnt+1;
              $dy=date("d", strtotime($dateend)); $mnth=date("m", strtotime($dateend)); $yr=date("Y", strtotime($dateend));
              $sql = "SELECT * FROM attendancelog WHERE EmpID=:id AND day(TimeIn)=:dy AND year(TimeIn)=:yr AND month(TimeIn)=:mnth";
              $stmt = $pdo->prepare($sql);
              $stmt->execute([':id'=>$id, ':dy'=>$dy, ':mnth'=>$mnth, ':yr'=>$yr]); 
              if ($stmt->rowCount()>0){ $DaysLogin=$DaysLogin+1; }
            }
          }
          $WDaysAL=$DaysLogin;
      }else{
          $date1=date_create($today); $date2=date_create($dtd);
          $diff=date_diff($date2,$date1);
          $DaysBL = floatval($diff->format('%a')); 
          $WDaysBL=$DaysBL; 
      }
    
      // Filing Validation
      if ($today==$dtd){
        if ($vl_duringfile==0){ echo "Filing not allowed"; return false; }
        if ($_POST['leavedur']==0.5 && $vl_halfday==0){ echo "You cant file halfday on this Leave"; return; }
      }else if ($today>=$dtd){
        if ($vl_after==1){
          if ($WDaysAL > $vl_duration_after){ echo "Your leave request couldn't be processed as the deadline has passed."; return; }
        }else{ echo "You Cant File After a Leave in this LeaveType"; return; }
      }else {
        // Vacation Leave specific logic
        if ($valid['lid']==22){
          $subjectRole = $EmpRow['EmpRoleID'];
          $limit = ($subjectRole==3) ? (($id=='WeDoinc-0010' || $id=='WeDoinc-009') ? 10 : 5) : 15;
          if ($WDaysBL < $limit){ echo "Notice period insufficient ($limit days required)."; return; }
        }
        if ($vl_before==1){
          if ($vl_max_day<$DaysBL and $vl_max_day<>0){ echo "You cant file ! Maximum" . $vl_max_day . " days"; return; }
          if ($vl_duration_before!=0 && $WDaysBL<$vl_duration_before){ echo "You Cant File Before Leave (Validation)"; return; }
        }else{ echo "You Cant File Before a Leave in this LeaveType"; return; }
      }
    }

    // --- START TRANSACTION ---
    try {
      $pdo->beginTransaction();

      $lvtype=$_POST['leavetype'];
      $streason = str_replace('\'', '', $_POST['reason']);
      
      // Determine status
      if($id=="WeDoinc-012") { $statid = 9; }

      // Insert main hleaves record
      $sql1 = "INSERT INTO hleaves (EmpID,EmpSID,LType,LFDate,LStart,LEnd,LPurpose,LDuration,LStatus,LInputDate,Lpaid,LDateTimeUpdated) 
              VALUES (:id,:is,:ltype,:lfdate,:lstart,:lend,:LPurpose,:lduration,:LStatus,:DTin,:lpay,:ldupdated)";
      $stmt = $pdo->prepare($sql1);
      $stmt->execute([
          ':id' => $id, ':is' => $isid, ':ltype' => $_POST['leavetype'], ':lfdate' => $today,
          ':lstart' => $_POST['lstarts'], ':lend' => $_POST['lenddate'], ':LPurpose' => $streason,
          ':lduration' => $_POST['leavedur'], ':LStatus' => $statid, ':DTin' => $today2,
          ':lpay' => $_POST['leavepay'], ':ldupdated' => $today2
      ]);

      // BIG FIX: Instead of SELECT MAX(LeaveID), use lastInsertId to avoid "Wrong ID" bugs
      $fkeyid = $pdo->lastInsertId();

      // Insert breakdown hleavesbd
      $dtendleave = $_POST['lenddate'];
      $dtstartleave = $_POST['lstarts'];
      $drt = ($_POST['leavedur']>=1) ? 600 : 300;
     //   $leave_type_ampm = null;
          $leave_type_ampm = 'whole_day'; // Default

       if (isset($_POST['is_half_day'])) {
          $leave_type_ampm = $_POST['half_day_type']; 
      }

      $bdInserted = 0; // count breakdown rows actually written (rest days / holidays are skipped)
      while ($dtstartleave <= $dtendleave) {
          $shouldInsert = false;
          
          if($ltype == 27){
              $shouldInsert = true;
          } else {
              $day_desc = date ("l", strtotime($dtstartleave));
              $statementleaveadd = $pdo->prepare("SELECT * FROM workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID inner join schedeffectivity as c on workdays.EFID=c.efids where (workdays.empid='$id') and (workdays.Day_s='$day_desc') and ('$dtstartleave' >= dfrom) and ('$dtstartleave' <= dto) and (SchedTime > 0)");
              $statementleaveadd->execute();
              
              $mtnh = date("Y-m-d", strtotime($dtstartleave));
              $statementhol = $pdo->prepare("SELECT * FROM holidays WHERE Hdate=:mnth");
              $statementhol->execute([':mnth' => $mtnh]);
              
              if($statementhol->rowCount() == 0 && $statementleaveadd->rowCount() > 0){
                  $shouldInsert = true;
                  if($id=="WeDoinc-012") { $statid = 4; }
              }
          }

          if($shouldInsert){
              $sqlbd = "INSERT INTO hleavesbd (FID,EmpID,EmpSID,LType,LFDate,LStart,LEnd,LPurpose,LDuration,LStatus,LInputDate,Lpaid,LDateTimeUpdated,am_pm) VALUES (:fidsl,:id,:is,:ltype,:lfdate,:lstart,:lend,:LPurpose,:lduration,:LStatus,:DTin,:lpay,:ldupdated,:am_pm)";
              $stmtbd = $pdo->prepare($sqlbd);
              $stmtbd->execute([
                  ':fidsl' => $fkeyid, ':id' => $id, ':is' => $isid, ':ltype' => $_POST['leavetype'],
                  ':lfdate' => $today, ':lstart' => $dtstartleave, ':lend' => $dtstartleave,
                  ':LPurpose' => $streason, ':lduration' => $drt, ':LStatus' => $statid,
                  ':DTin' => $today2, ':lpay' => $_POST['leavepay'], ':ldupdated' => $today2,':am_pm' => $leave_type_ampm
              ]);
              $ida = $pdo->lastInsertId();
              $bdInserted++;

              // Payroll check
              $sqlst = "SELECT * FROM payrol WHERE (:dts BETWEEN PYDateFrom AND PYDateTo)";
              $state = $pdo->prepare($sqlst);
              $state->execute([':dts' => $dtstartleave]);
              
              if($rowCheck = $state->fetch()){
                  $pdateData = $rowCheck['PYDate'];
                  // Logic for next payroll date
                  if(date("d", strtotime($pdateData)) == "05"){
                      $pdateData = date("Y-m", strtotime($pdateData))."-20";
                  } else {
                      $pdateData = date("Y-m-d", strtotime(date("Y-m-05", strtotime($pdateData)). " +1 month"));
                  }
                  
                  // Check if payroll already exists
                  $statemax = $pdo->query("SELECT MAX(PYDate) as MaxD FROM payrol");
                  $maxdate = $statemax->fetch()['MaxD'];
                  if($maxdate >= $pdateData){
                    if(date("d", strtotime($maxdate)) == "05") $pdateData = date("Y-m", strtotime($maxdate))."-20";
                    else $pdateData = date("Y-m-d", strtotime(date("Y-m-05", strtotime($maxdate)). " +1 month"));
                  }

                  $tagSQL = $pdo->prepare("INSERT INTO tbl (refID,refEmpID,refPayDate,inputdate) VALUES(:id,:empid,:pdate,:inputdate)");
                  $tagSQL->execute([':id'=>$ida, ':empid'=>$id, ':pdate'=>$pdateData, ':inputdate'=>$today2]);
              }
          }
          $dtstartleave = date ("Y-m-d", strtotime($dtstartleave. "+1 day"));
      }

      // No schedulable workday in the selected range (all rest days / holidays):
      // there is nothing to approve and the orphan master would never show in the
      // history (it INNER JOINs hleavesbd). Reject instead of saving an empty leave.
      if ($bdInserted == 0) {
          $pdo->rollBack();
          echo "The selected date(s) fall on your rest day or a holiday. There is no working day to apply leave for.";
          return;
      }

      // Insert into dars (log against the actual filer; note when filed on behalf)
      $darActor    = $loggedInId;
      $darActivity = $onBehalf ? ('Filed Leave for ' . $id) : 'Applied Leave';
      $stmtDar = $pdo->prepare("INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:act,:tdy)");
      $stmtDar->execute([':id' => $darActor, ':act' => $darActivity, ':tdy' => $today2]);

      $pdo->commit();
      echo 1;

    } catch (Exception $e) {
      $pdo->rollBack();
      echo 'Error: ' . $e->getMessage();
    }
?>