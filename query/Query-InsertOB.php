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
    
    try{
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
        $id=$_SESSION['id'];
        $isid=$_SESSION['EmpISID'];
        $statid=1;
        $today =date("Y-m-d"); 
        $today2 =date("Y-m-d H:i:s");
        $start = $_POST['obdatefrom'];
        $end = $_POST['obdateto'];
        $dtfrom = date("Y-m-d", strtotime($_POST['obdatefrom']));  
        $dttoo = date("Y-m-d", strtotime($_POST['obdateto']));  
        $dteStart = new DateTime($dtfrom . ' ' .$_POST['timefrom']); 
        $dteEnd   = new DateTime($dttoo . ' ' .$_POST['timeto']); 
        $filetimefrom =  date("H:i", strtotime($_POST['timefrom']));  
        $filetimeto =  date("H:i", strtotime($_POST['timeto']));
        $dteDiff  = $dteStart->diff($dteEnd); 
        $interval = $dteDiff->format("%H");
        $day_desc=date("l", strtotime($_POST['obdatefrom']));
  
        // if applied OB but there is a generated payroll on inclusive date 
        $sqlst = "SELECT * from payrol where PYEmpID=:id and hrApproved=1 and (:dts BETWEEN PYDateFrom and PYDateTo) OR (:dte BETWEEN PYDateFrom and PYDateTo)";
        $state = $pdo->prepare($sqlst);
        $state->bindParam(':id', $id);
        $state->bindParam(':dts', $start);
        $state->bindParam(':dte', $end);
        $state->execute();
        $checkerstate = $state->fetch();
        if($state->rowCount()>=1){
            echo 7;
            return;
        }
    
        //should not accpet
        $statement = $pdo->prepare("select * from obs where OBDateFrom=:odfrom and OBDateTo=:odto and OBTimeFrom=:ffrom and OBTimeTo=:fto and EmpID=:idn");
        $statement->bindParam(':idn' , $id);
        $statement->bindParam(':odfrom' ,$_POST['obdatefrom']);
        $statement->bindParam(':odto' ,$_POST['obdateto']);
        $statement->bindParam(':ffrom' ,$filetimefrom);
        $statement->bindParam(':fto' ,$filetimeto);
        $statement->execute();
        $count = $statement->rowCount();
        if ($count > 0 ){
            echo 4;
            return;
        }

        //holiday not file
        $cmpnyID=$_SESSION['CompID'];
        $stment=$pdo->prepare("select * from holidays where HCompID=:cmpid and Hdate=:hdte");
        $stment->bindParam(':cmpid', $cmpnyID);
        $stment->bindParam(':hdte', $dtfrom);
        $stment->execute();
        $countholiday = $stment->rowCount();
        if ($countholiday>0){
            echo 5;
            return;
        }
    
        //if employee leave cant file    
        $dtfromloop=$dtfrom;
        $cntload=0;
        
        while($dtfromloop<=$dttoo){
            $slscts=$pdo->prepare("select * from hleaves where EmpID=:idn and LStart>=:dtstart and LEnd>=:dtstart");
            $slscts->bindParam(':idn' , $id);
            $slscts->bindParam(':dtstart', $dtfromloop);
            $slscts->execute();
            $cntload = $slscts->rowCount();
            if ($cntload==1){
              break 1;
            }
            $dtfromloop= date('Y-m-d', strtotime($dtfromloop  . ' +1 days'));
        }
        if ($cntload>0){
            echo 6;
            return;
        }
    
        $resultsched = mysqli_query($con, "Select * from workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID where workdays.empid='$id' and workdays.Day_s='$day_desc'");
        $rowsc = mysqli_fetch_array($resultsched);
        $nmrows=mysqli_num_rows($resultsched);
        
        if ($dtfrom==$dttoo){
            if ($nmrows==0){
                //no schedule
                echo 2;
                return;
            }
            else{
                $filetimefrom =  date("H:i", strtotime($_POST['timefrom']));  
                $filetimeto =  date("H:i", strtotime($_POST['timeto']));
                $dbtfrom =  date("H:i", strtotime($rowsc['TimeFrom']));
                $dbtto =  date("H:i", strtotime($rowsc['TimeTo']));

                if (($filetimefrom >= $dbtfrom && $filetimefrom < $dbtto) && ($filetimeto > $dbtfrom && $filetimeto <= $dbtto)){
                    //invalid time   && ($_POST['timefrom'] <= $rowsc['TimeTo'] && $_POST['timeto'] <= $rowsc['TimeTo']) &&  $_POST['timefrom'] >= $rowsc['TimeFrom']
                }
                else{
                    echo 3;
                    return;
                }
            }
        }

        $sql2="select * from obvalidation where compid=:id";
        $stmt = $pdo->prepare($sql2);
        $stmt->bindParam(':id' , $_SESSION['CompID']);
        $stmt->execute();
        $count = $stmt->rowCount();
        $row=$stmt->fetch();
        if ($count==0){
            // insert into dars
            $sql = "INSERT INTO obs (EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBInputDate) 
                VALUES (:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:ipd)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id' , $id);
            $stmt->bindParam(':is', $isid);
            $stmt->bindParam(':fd' ,$today);
            $stmt->bindParam(':odfrom' ,$_POST['obdatefrom']);
            $stmt->bindParam(':odto' ,$_POST['obdateto']);
            $stmt->bindParam(':oifrom' ,$_POST['ifrom']);
            $stmt->bindParam(':oduration' ,$interval);
            $stmt->bindParam(':oito' ,$_POST['ito']);
            $stmt->bindParam(':opurpose' ,$_POST['purpose']);
            $stmt->bindParam(':otfrom' ,$_POST['timefrom']);
            $stmt->bindParam(':otto' ,$_POST['timeto']);
            $stmt->bindParam(':ocamt' ,$_POST['ca']);
            $stmt->bindParam(':obcp' ,$_POST['capurpose']);
            $stmt->bindParam(':obs' ,$statid);
            $stmt->bindParam(':obup' ,$today2);
            $stmt->bindParam(':ipd' ,$today2);
            $stmt->execute(); 
        }else{
            $filed_date1=$_POST['obdateto'];
            $today1 =date("Y-m-d");
            //after
            if ($filed_date1 > $today1){
                if ($row['IsAfter']==1){
                    // insert into dars
                    $sql = "INSERT INTO obs (EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBInputDate) 
                        VALUES (:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:ipd)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id' , $id);
                    $stmt->bindParam(':is', $isid);
                    $stmt->bindParam(':fd' ,$today);
                    $stmt->bindParam(':odfrom' ,$_POST['obdatefrom']);
                    $stmt->bindParam(':odto' ,$_POST['obdateto']);
                    $stmt->bindParam(':oifrom' ,$_POST['ifrom']);
                    $stmt->bindParam(':oduration' ,$interval);
                    $stmt->bindParam(':oito' ,$_POST['ito']);
                    $stmt->bindParam(':opurpose' ,$_POST['purpose']);
                    $stmt->bindParam(':otfrom' ,$_POST['timefrom']);
                    $stmt->bindParam(':otto' ,$_POST['timeto']);
                    $stmt->bindParam(':ocamt' ,$_POST['ca']);
                    $stmt->bindParam(':obcp' ,$_POST['capurpose']);
                    $stmt->bindParam(':obs' ,$statid);
                    $stmt->bindParam(':obup' ,$today2);
                    $stmt->bindParam(':ipd' ,$today2);
                    $stmt->execute(); 
                    echo "Successfully Applied OB";
                }else{
                    $statid=6;
                    $sql = "INSERT INTO obs (EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBInputDate) 
                        VALUES (:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:ipd)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id' , $id);
                    $stmt->bindParam(':is', $isid);
                    $stmt->bindParam(':fd' ,$today);
                    $stmt->bindParam(':odfrom' ,$_POST['obdatefrom']);
                    $stmt->bindParam(':odto' ,$_POST['obdateto']);
                    $stmt->bindParam(':oifrom' ,$_POST['ifrom']);
                    $stmt->bindParam(':oduration' ,$interval);
                    $stmt->bindParam(':oito' ,$_POST['ito']);
                    $stmt->bindParam(':opurpose' ,$_POST['purpose']);
                    $stmt->bindParam(':otfrom' ,$_POST['timefrom']);
                    $stmt->bindParam(':otto' ,$_POST['timeto']);
                    $stmt->bindParam(':ocamt' ,$_POST['ca']);
                    $stmt->bindParam(':obcp' ,$_POST['capurpose']);
                    $stmt->bindParam(':obs' ,$statid);
                    $stmt->bindParam(':obup' ,$today2);
                    $stmt->bindParam(':ipd' ,$today2);
                    $stmt->execute(); 
                    echo "Successfully Applied OB";
                    return false;
                }
            }
            elseif ($filed_date1 < $today1){
                if ($row['IsBefore']==1){
                    // insert into dars
                    $sql = "INSERT INTO obs (EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBInputDate) 
                        VALUES (:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:ipd)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id' , $id);
                    $stmt->bindParam(':is', $isid);
                    $stmt->bindParam(':fd' ,$today);
                    $stmt->bindParam(':odfrom' ,$_POST['obdatefrom']);
                    $stmt->bindParam(':odto' ,$_POST['obdateto']);
                    $stmt->bindParam(':oifrom' ,$_POST['ifrom']);
                    $stmt->bindParam(':oduration' ,$interval);
                    $stmt->bindParam(':oito' ,$_POST['ito']);
                    $stmt->bindParam(':opurpose' ,$_POST['purpose']);
                    $stmt->bindParam(':otfrom' ,$_POST['timefrom']);
                    $stmt->bindParam(':otto' ,$_POST['timeto']);
                    $stmt->bindParam(':ocamt' ,$_POST['ca']);
                    $stmt->bindParam(':obcp' ,$_POST['capurpose']);
                    $stmt->bindParam(':obs' ,$statid);
                    $stmt->bindParam(':obup' ,$today2);
                    $stmt->bindParam(':ipd' ,$today2);
                    $stmt->execute(); 
                    echo "Successfully Applied OB";
                }
                else{
                    $statid=6;
                    $sql = "INSERT INTO obs (EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBInputDate) 
                        VALUES (:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:ipd)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id' , $id);
                    $stmt->bindParam(':is', $isid);
                    $stmt->bindParam(':fd' ,$today);
                    $stmt->bindParam(':odfrom' ,$_POST['obdatefrom']);
                    $stmt->bindParam(':odto' ,$_POST['obdateto']);
                    $stmt->bindParam(':oifrom' ,$_POST['ifrom']);
                    $stmt->bindParam(':oduration' ,$interval);
                    $stmt->bindParam(':oito' ,$_POST['ito']);
                    $stmt->bindParam(':opurpose' ,$_POST['purpose']);
                    $stmt->bindParam(':otfrom' ,$_POST['timefrom']);
                    $stmt->bindParam(':otto' ,$_POST['timeto']);
                    $stmt->bindParam(':ocamt' ,$_POST['ca']);
                    $stmt->bindParam(':obcp' ,$_POST['capurpose']);
                    $stmt->bindParam(':obs' ,$statid);
                    $stmt->bindParam(':obup' ,$today2);
                    $stmt->bindParam(':ipd' ,$today2);
                    $stmt->execute(); 
                    echo "Successfully Applied OB";
                    return false;
                }
            }
            elseif ($filed_date1==$today1){
                // insert into dars
                $sql = "INSERT INTO obs (EmpID,EmpSID,OBFD,OBDateFrom,OBDateTo,OBIFrom,OBDuration,OBITo,OBPurpose,OBTimeFrom,OBTimeTo,OBCAAmt,OBCAPurpose,OBStatus,OBUpdated,OBInputDate) 
                    VALUES (:id,:is,:fd,:odfrom,:odto,:oifrom,:oduration,:oito,:opurpose,:otfrom,:otto,:ocamt,:obcp,:obs,:obup,:ipd)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id' , $id);
                $stmt->bindParam(':is', $isid);
                $stmt->bindParam(':fd' ,$today);
                $stmt->bindParam(':odfrom' ,$_POST['obdatefrom']);
                $stmt->bindParam(':odto' ,$_POST['obdateto']);
                $stmt->bindParam(':oifrom' ,$_POST['ifrom']);
                $stmt->bindParam(':oduration' ,$interval);
                $stmt->bindParam(':oito' ,$_POST['ito']);
                $stmt->bindParam(':opurpose' ,$_POST['purpose']);
                $stmt->bindParam(':otfrom' ,$_POST['timefrom']);
                $stmt->bindParam(':otto' ,$_POST['timeto']);
                $stmt->bindParam(':ocamt' ,$_POST['ca']);
                $stmt->bindParam(':obcp' ,$_POST['capurpose']);
                $stmt->bindParam(':obs' ,$statid);
                $stmt->bindParam(':obup' ,$today2);
                $stmt->bindParam(':ipd' ,$today2);
                $stmt->execute(); 
                echo "Successfully Applied OB";
            }
            else{
                echo "Cant File OB";
                return false;
            }
       }

        $id=$_SESSION['id'];
        $ch="Applied OB";
        // insert into dars
        $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $id);
        $stmt->bindParam(':empact', $ch);
        $stmt->execute(); 
                    
    }catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
 ?>