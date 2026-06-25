<?php

//approve application status update
  include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }

  try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }catch(PDOException $e){
    die("ERROR: Could not connect. " . $e->getMessage());
  }

   date_default_timezone_set("Asia/Manila");
   $tdy= date("Y-m-d H:i:s"); 
   $todaydt=date("Y-m-d H:i:s"); 

    if ($_SESSION['UserType']==3){
        $stat = 2;
    }else if ($_SESSION['UserType']==2){
        $stat = 2;
    }else{
        $stat = 4;
    }

    // Validate the record id once (all ntypes use an integer PK: SID/OBID/LeaveID/OTLOGID)
    $rawId = $_GET['id'] ?? null;
    if ($rawId === null || !ctype_digit((string)$rawId)) {
        http_response_code(400);
        echo json_encode(array("error" => "bad id"));
        return;
    }
    $leaveId = (int)$rawId;

   if (isset($_GET['ntype'])){
      if ($_GET['ntype']=="EO"){
          $sql = "UPDATE earlyout SET Status=:st,DateTimeUpdated=:dtu where SID=:id";
          $stmt = $pdo->prepare($sql);
          $stmt->bindParam(':st' ,$stat);
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
          $ch="Approved EO of " .  $nameE;
          $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
          $stmt = $pdo->prepare($sql);
          $stmt->bindParam(':id' , $id);
          $stmt->bindParam(':empact', $ch);
          $stmt->bindParam(':ddt', $todaydt);
          $stmt->execute(); 
      }else if($_GET['ntype']=="OB"){
          // update table obhd
          $sql2 = "UPDATE obshbd SET OBStatus=:st,OBUpdated=:dtu where OBID=:id";
          $stmt2 = $pdo->prepare($sql2);
          $stmt2->bindParam(':st' ,$stat);
          $stmt2->bindParam(':dtu' ,$tdy);
          $stmt2->bindParam(':id' ,$leaveId);
          $stmt2->execute();
          //ob
          $sql = "UPDATE obs SET OBStatus=:st,OBUpdated=:dtu where OBID=:id";
          $stmt = $pdo->prepare($sql);
          $stmt->bindParam(':st' ,$stat);
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
          $ch="Approved OB of " . $nameE;
          // insert into dars
          $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
          $stmt = $pdo->prepare($sql);
          $stmt->bindParam(':id' , $id);
          $stmt->bindParam(':empact', $ch);
          $stmt->bindParam(':ddt', $todaydt);
          $stmt->execute(); 
      }else if($_GET['ntype']=="HL"){//for leave function loading and approving
            // Authorization guard: only an approver acting within scope may touch this leave
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

             $sql2 = "SELECT employees.EmpLN as LN, employees.EmpFN as FN FROM employees INNER JOIN hleaves ON employees.EmpID=hleaves.EmpID WHERE LeaveID=:idd";
            $stmt = $pdo->prepare($sql2);
            $stmt->bindParam(':idd', $leaveId);
            $stmt->execute();
            $row = $stmt->fetch();
            $nameE = $row['FN'] . " " . $row['LN'];

            if ($_SESSION['UserType'] == 2) { // this is for IS function
                // if immediate
                $sql = "UPDATE hleaves SET LStatus=:st, LDateTimeUpdated=:ldtup WHERE LeaveID=:lid";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':st', $stat);
                $stmt->bindParam(':ldtup', $todaydt);
                $stmt->bindParam(':lid', $leaveId);
                $stmt->execute();

                $sql = "UPDATE hleavesbd SET LStatus=:st, LDateTimeUpdated=:ldtup WHERE FID=:lid";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':st', $stat);
                $stmt->bindParam(':ldtup', $todaydt);
                $stmt->bindParam(':lid', $leaveId);
                $stmt->execute();

                $id = $_SESSION['id'];
                $ch = "Approved Leaves of " . $nameE;
                // insert into dars
                $sql = "INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :empact, :ddt)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':empact', $ch);
                $stmt->bindParam(':ddt', $todaydt);
                $stmt->execute(); 
                echo json_encode(array("uid" => 0, "dd" => '0')); 
            } else { // for hr function
                try { 
                    // { -- get leave
                    $sql = "SELECT * FROM hleaves as a WHERE a.LeaveID=:id ORDER BY LStart";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':id' => $leaveId]);
                    $rowh = $stmt->fetch();
                    $rowhcount = $stmt->rowCount();

                    $leaveType = 0;
                    $creditEarned = 0;
                    $lduration = 0;
                    $creditloop = 0;
                    $earnedCredit = 0;

                    if ($rowhcount == 1) { // if found load all the necessary 
                        // { -- initializing the data to be use
                        $leaveType = $rowh['LType'];
                        $EmplID    = $rowh['EmpID'];
                        $datestart = $rowh['LStart'];
                        $dateend   = $rowh['LEnd'];
                        $lduration = $rowh['LDuration'];
                        $date1     = date_create($dateend);
                        $date2     = date_create($datestart);
                        $diff      = date_diff($date1, $date2);
                        $DayDur    = $diff->format("%a");
                        // }
                        
                        if ($EmplID == "WeDoinc-0003" && $leaveType == 34) { // for terminal leave function
                            $sql = "SELECT SUM(LDuration) as SumOfDur FROM hleavesbd WHERE LType=12 AND Lstatus=4";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute();
                            $leaveterminal = $stmt->fetch();
                            if ((($leaveterminal['SumOfDur'] / 60) / 10) < 8) {
                                // Handled if terminal leave criteria is met
                            } else {
                                        $reas = "0 Terminal Leave";
                                        $sql = "UPDATE hleaves SET LStatus=6, LDateTimeUpdated=:dtu, LHRReason=:rsn WHERE LeaveID=:idd";
                                        $stmt = $pdo->prepare($sql);                       
                                        $stmt->bindParam(':dtu', $todaydt);
                                        $stmt->bindParam(':rsn', $reas);
                                        $stmt->bindParam(':idd', $leaveId);
                                        $stmt->execute();

                                        $sql = "UPDATE hleavesbd SET LStatus=6, LDateTimeUpdated=:dtu, LHRReason=:rsn WHERE FID=:idd";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->bindParam(':dtu', $todaydt);
                                        $stmt->bindParam(':rsn', $reas);
                                        $stmt->bindParam(':idd', $leaveId);
                                        $stmt->execute();   

                                        $id = $_SESSION['id'];
                                        $ch = "Disapproved Leaves of " . $nameE . " Reason : 0 Terminal Leave";
                                        // insert into dars
                                        $sql = "INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :empact, :ddt)";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->bindParam(':id', $id);
                                        $stmt->bindParam(':empact', $ch);
                                        $stmt->bindParam(':ddt', $todaydt);
                                        $stmt->execute(); 
                                        return;
                                    }
                        }

                        // new logic for earning credit process and threshold classification

                        // { -- get the leave credit (15 or 10)
                        $varTH = 0;
                        $varCT = 0;
                        $useCRDT= 0;

                        $sqlEmp = "SELECT a.EmpDOR, c.CT, c.CTH 
                                FROM empdetails as a 
                                INNER JOIN credit as c ON a.EmpID = c.EmpID 
                                WHERE a.EmpID = :id";
                        $stmtTH = $pdo->prepare($sqlEmp);
                        $stmtTH->bindParam(':id', $EmplID);
                        $stmtTH->execute();
                        $crdetailTH = $stmtTH->fetch();
                        $crcntTH = $stmtTH->rowCount();

                        $isSpecialEmployee = ($EmplID == "WeDoinc-0145");

                        if ($crcntTH > 0) {
                            if (!$isSpecialEmployee) {
                                $varTH = $crdetailTH['CTH'];
                                $varCT = $crdetailTH['CT'];
                            } else {
                                // SPECIAL CASE FOR WeDoinc-0145
                                $cth = $crdetailTH['CTH'];
                                $ct  = $crdetailTH['CT']; // Purong accrued credits mula sa DB
                                $useCRDT=  $crdetailTH['CTH'] - $crdetailTH['CT'] ; // Use the accrued credits as the base for calculation
                                

                                $dor = $crdetailTH['EmpDOR']; 

                                if (!empty($dor)) {
                                    $currentYear = date("Y");
                                    $dateNow     = date_create(date("Y-m-d"));

                                    // Strictly gamitin ang DOR bilang start date para sa pro-rating (Umiwas sa Enero 1 override)
                                    $calcStart = date_create($dor);

                                    // Daily Accrual Calculation
                                    $dateJan1     = date_create($currentYear . "-01-01");
                                    $dateNextJan1 = date_create(($currentYear + 1) . "-01-01");
                                    $daysInYear   = date_diff($dateJan1, $dateNextJan1)->format("%a");

                                    $cdPerDay   = $cth / $daysInYear;
                                    $daysActive = date_diff($calcStart, $dateNow)->format("%a");
                                    
                                    if ($calcStart > $dateNow) { $daysActive = 0; }

                                    // Pinal na kalkulasyon ng purong credit earned (Strictly base sa pro-rating mula DOR, walang bawas mula sa LType 24)
                                    $varCT = $cdPerDay * $daysActive; 
                                    $varTH = $cth; 

                                } else {
                                    $varCT = $ct;
                                    $varTH = $cth;
                                }
                                $varCT= $varCT-$useCRDT; // Adjust the available credit by subtracting the accrued credits that are being used for calculation
                            }
                        }

                      

                      

                        // { -- approval block
                        try {
                            $durData = $varCT; // Static Total Earned value galing sa DB/Accrual
                            $usedInThisLoop = 0; // Running accumulator para sa Standard Logic validation
                            $pdo->beginTransaction();
                            
                            $medicalSilTypes = [22, 30, 38, 24]; // Vacation, Medical, Force, Emergency
                            $emergencyTH = 0;

                            // Fetch the employee's role para sa limit ng Emergency Leave
                            $getRoleSql = "SELECT EmpRoleID FROM empdetails WHERE EmpID = :id";
                            $stmtRole = $pdo->prepare($getRoleSql);
                            $stmtRole->execute([':id' => $EmplID]);
                            $empData = $stmtRole->fetch();

                            if ($empData && $leaveType == 24) { 
                                $role = $empData['EmpRoleID'];
                                if ($role == 1)      { $emergencyTH = 15; }
                                elseif ($role == 2)  { $emergencyTH = 5; }
                                elseif ($role == 3)  { $emergencyTH = 4; } // Para kay WeDoinc-0145, ang limit ay 4
                            }

                            if (in_array($leaveType, $medicalSilTypes)) {
                                $currentYear = date('Y');
                                $localCount = 0;
                                
                                // Patakbuhin LAMANG ang query na ito kung SPECIAL CASE employee ang nag-fa-file ng Emergency Leave
                                $totalApprovedCount = 0;
                                if ($isSpecialEmployee && $leaveType == 24) {
                                    $sqlCount = "SELECT SUM(hb.LDuration) FROM hleavesbd hb 
                                                WHERE hb.EmpID = :empid 
                                                AND hb.LType = 24 
                                                AND hb.LStatus = 4 
                                                AND YEAR(hb.LStart) = :year";
                                    $stmtCount = $pdo->prepare($sqlCount);
                                    $stmtCount->execute([':empid' => $EmplID, ':year' => $currentYear]);
                                    $totalMinutesUsedBefore = (float)$stmtCount->fetchColumn() ?: 0;
                                    
                                    // Convert to days (600 mins = 1 day)
                                    $totalApprovedCount = $totalMinutesUsedBefore / 600;
                                }

                                while ($DayDur >= 0) {
                                    $dayWeight = 1; // day-weight for the on-the-spot accounting (overridden below)
                                    // 1. Determine the status base sa leave type at empleyado
                                    if ($isSpecialEmployee && $leaveType == 24) {
                                        // SPECIAL CASE: Para sa Emergency Leave ni WeDoinc-0145:
                                        // Weight each day by its actual duration (half-day = 0.5) so the 4-day
                                        // on-the-spot limit is measured in days, consistent with the baseline.
                                        $wStmt = $pdo->prepare("SELECT LDuration FROM hleavesbd WHERE FID=:idd AND LStart=:dtstart");
                                        $wStmt->execute([':idd' => $leaveId, ':dtstart' => $datestart]);
                                        $dayMins = (float)$wStmt->fetchColumn();
                                        $dayWeight = $dayMins > 0 ? ($dayMins / 600) : 1.0;
                                        if (($totalApprovedCount + $localCount + $dayWeight) <= $emergencyTH) {
                                            $statusFiD = 4; // Pasok sa 4-day limit -> With Pay (Hindi babawasan ang CT sa database!)
                                        } else {
                                            $statusFiD = 8; // Lumampas na sa limit -> Approved Without Pay (excused)
                                        }
                                    } else {
                                        // STANDARD LOGIC: Para sa Medical Leave o ibang mga empleyado
                                        // Alamin kung ang kasalukuyang pinoprosesong araw ay Whole day (1.0) o Half day (0.5) base sa lduration ng header row bilang basehan
                                        $currentDayWeight = ($lduration == 300) ? 0.5 : 1.0; 

                                        // Tumpak na tinitingnan kung ang (mga nagamit na sa loop na ito + ang timbang ng araw na ito) ay kasya pa sa static $durData
                                        if (($usedInThisLoop + $currentDayWeight) <= $durData) {
                                            $statusFiD = 4; // Kasya pa ang credit -> With Pay
                                            $usedInThisLoop += $currentDayWeight; // Idagdag sa running balance na nagamit na sa loop
                                        } else {
                                            $statusFiD = 8; // Kulang o Ubos na ang credit -> Approved Without Pay (excused)
                                        }
                                    }

                                    // 2. Attempt the Update sa hleavesbd
                                    $sql = "UPDATE hleavesbd SET LStatus=:st, LDateTimeUpdated=:dtu 
                                            WHERE FID=:idd AND LStart=:dtstart";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute([
                                        ':st'      => $statusFiD,
                                        ':dtu'     => $todaydt,
                                        ':idd'     => $leaveId,
                                        ':dtstart' => $datestart
                                    ]);

                                    if ($stmt->rowCount() > 0) {
                                        if ($statusFiD == 4) {
                                            $localCount += ($isSpecialEmployee && $leaveType == 24) ? $dayWeight : 1;
                                        }
                                    }

                                    $datestart = date('Y-m-d', strtotime($datestart . ' + 1 days'));
                                    $DayDur--;
                                }

                                //      print  $varCT . "-" . $statusFiD ;
                                // return ;

                                // 4. Update the main leave header status
                                $sqlHLeaves = "UPDATE hleaves SET LStatus=9, LDateTimeUpdated=:dtu WHERE LeaveID=:idd";
                                $stmtHLeaves = $pdo->prepare($sqlHLeaves);
                                $stmtHLeaves->execute([':dtu' => $todaydt, ':idd' => $leaveId]);
 

                                // 5. PAGBAWAS NG CREDIT SA DATABASE:
                                if ($isSpecialEmployee && $leaveType == 24) {
                                    // Kung Emergency Leave (24) ni WeDoinc-0145:
                                    // DO NOT DEDUCT. Mananatiling buo ang regular credit earning balance ($varCT) sa DB
                                    // $xcrd = $varCT; 
                                } else {
                                    // // Kung Medical Leave o ibang leave types/empleyado: Ibabawas ang nagamit sa kabuuang static wallet ($durData - $usedInThisLoop)
                                    // $xcrd = $durData - $usedInThisLoop; 

                                    // // In-uncomment ang code na ito para ma-save na ng permanente ang bawas sa credit table sa DB
                                    // $sqlCredit = "UPDATE credit SET CT=:ncrd WHERE EmpID=:idd";
                                    // $stmtCredit = $pdo->prepare($sqlCredit);
                                    // $stmtCredit->execute([':ncrd' => number_format($xcrd, 4, '.', ''), ':idd' => $EmplID]);

                                    // 1. I-compute lang kung magkano ang ibabawas sa loop na ito
                                    $amountToDeduct = number_format($usedInThisLoop, 4, '.', '');

                                    // 2. Ang SQL ay dapat magbawas mismo sa kasalukuyang value ng CT (CT = CT - :deduction)
                                    $sqlCredit = "UPDATE credit SET CT = CT - :deduction WHERE EmpID = :idd";
                                    $stmtCredit = $pdo->prepare($sqlCredit);

                                    // 3. I-execute gamit ang ibabawas na halaga, hindi ang computed total
                                    $stmtCredit->execute([
                                        ':deduction' => $amountToDeduct, 
                                        ':idd'       => $EmplID
                                    ]);
                                }
                                
                                

                                echo json_encode(array("uid" => $_SESSION['UserType'], "dd" => 35, "lc" => $localCount)); 
                            } else {
                                // Logic for other leave types (No earning checking required)
                                $sqlHLeaves = "UPDATE hleaves SET LStatus=9, LDateTimeUpdated=:dtu WHERE LeaveID=:idd";
                                $stmtHLeaves = $pdo->prepare($sqlHLeaves);
                                $stmtHLeaves->execute([':dtu' => $tdy, ':idd' => $leaveId]);

                                $ch = "Approved Leaves of " . $nameE;
                                $sqlDar = "INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :empact, :ddt)";
                                $stmtDar = $pdo->prepare($sqlDar);
                                $stmtDar->execute([
                                    ':id'      => $_SESSION['id'],
                                    ':empact'  => $ch,
                                    ':ddt'     => $todaydt
                                ]);

                                $sqlBd = "UPDATE hleavesbd SET LStatus=:st, LDateTimeUpdated=:dtu WHERE FID=:idd";
                                $stmtBd = $pdo->prepare($sqlBd);
                                $stmtBd->execute([':st' => $stat, ':dtu' => $tdy, ':idd' => $leaveId]);

                                echo json_encode(array("uid" => 0, "dd" => '0'));
                            }

                            $pdo->commit();

                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            error_log($e->getMessage());
                            echo json_encode(array("error" => "Transaction failed: " . $e->getMessage()));
                        }
                        // end p1
                        
                    } else { // nothing here
                        print 1;
                    }
                    
                } catch (Exception $e) {
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                }
            }
      
      }else if($_GET['ntype']=="OT"){
            $sql = "UPDATE otattendancelog SET Status=:st,DateTimeUpdate=:dtu where OTLOGID=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':st' ,$stat);
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
            $ch="Approved OT of " .  $nameE;
            // insert into dars
            $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id' , $id);
            $stmt->bindParam(':empact', $ch);
            $stmt->bindParam(':ddt', $todaydt);
            $stmt->execute(); 
      }
   }
?>