<?php 

  include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){
  }else{
     header ('location: login.php'); 
  }

  try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }catch(PDOException $e) {
      die("ERROR: Could not connect. ");
  }

    date_default_timezone_set("Asia/Manila");
    $day_desc=date("l");
    $id=$_SESSION['id'];
    $compid=$_SESSION['CompID'];
    $today = date("Y-m-d H:i:s");
    $today2 = date("Y-m-d H:i:s");
    $today3 = date("Y-m-d");

    $gp=0;   
    $result1 = mysqli_query($con, "Select * FROM lilovalidation where EmpCompID='$compid'");
    $rowd = mysqli_fetch_array($result1); 
    $cnt1= mysqli_num_rows ($result1);

    if($cnt1==0){
      //$gp=$rowd['EmpGP'];
    }else{
      $gp=0; 
    }

    $dayt=date("d");
    $mntht=date("m");
    $yrt=date("Y");

    //search ot 
    $sql = "SELECT * from otattendancelog where EmpID=:id and year(TimeIn)=:yr and day(TimeIn)=:dy and month(TimeIn)=:mnh";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':mnh', $mntht);
    $stmt->bindParam(':dy', $dayt);
    $stmt->bindParam(':yr', $yrt);
    $stmt->execute();
    $ftchot = $stmt->fetch();
    $cntot= $stmt->rowCount();
    $otvalid=0;

    //if there is OT 
    if($cntot>0){
      $ottimein=$ftchot['TimeIn'];
      $otTI = strtotime($ftchot['TimeIn']);
      $tdy = strtotime($today);
      $otID = $ftchot['OTLOGID'];

      if ($otTI<$tdy){
        $otvalid=0;
        $resultlilo = mysqli_query($con, "Select * from attendancelog where EmpID='$id' order by LogID DESC");
        $reslilo = mysqli_fetch_array($resultlilo); 
        $cntlilo= mysqli_num_rows ($resultlilo);

        if(($cntlilo>0) && ($reslilo['TimeOut']==NULL)){
          $otvalid=1;
        }else{
          $sql = "UPDATE otattendancelog set Status=6 where OTLOGID=:idot";
          $stmt = $pdo->prepare($sql);
          $stmt->bindParam(':idot', $otID);
          $stmt->execute();
        }
        $tfrom = date("H:i:s" ,strtotime($ftchot['TimeIn']));
        $tto = date("H:i:s" ,strtotime($ftchot['TimeOut']));

      }else{
        // echo "Not Late ";
        $otvalid=1;
        $tfrom = date("H:i:s" ,strtotime($ftchot['TimeIn']));
        $tto = date("H:i:s" ,strtotime($ftchot['TimeOut']));
      }
    }

    //new update for leave validation
    // function validateUserLeave($id) {
      // 1. IMPORT CONNECTION: Para mabasa ang $con connection galing sa labas
      global $con; 

      // 2. TIMEZONE SETTING: Gawin ito bago mag-create ng date objects
      date_default_timezone_set('Asia/Manila');
      
      $now = new DateTime(); 
      $currentTime   = $now->format('H:i');
      $today_compare = $now->format('Y-m-d');

      // 3. SQL QUERY: Inayos ang date parameter gamit ang "?" placeholder
      $sql = "SELECT * FROM hleavesbd WHERE EmpID = ? AND LStatus = '4' AND LStart = ? LIMIT 1";
      
      $stmt = $con->prepare($sql);
      
      if ($stmt) {
          // "ss" means dalawang strings (id at today_compare)
          $stmt->bind_param("ss", $id, $today_compare); 
          $stmt->execute();

          $result = $stmt->get_result();
          $leave = $result->fetch_assoc();
          
          if ($leave) {
         
              // Check Whole Day (LDuration 600)
              if ($leave['LDuration'] == 600) {
                  print 1000;
                  return;
              }

              // Check Half-day (LDuration 300)
              if ($leave['LDuration'] == 300) {
                  if ($leave['am_pm'] === 'half_day_am') {
                      if ($currentTime < '13:00') {
                          print 1001;
                          return;
                      }
                  } elseif ($leave['am_pm'] === 'half_day_pm') {
                      if ($currentTime >= '13:00') {
                          print 1002;
                          return;
                      }
                  } 
              }
          }
          $stmt->close(); // Ugaliing i-close ang statement
      }

    //   return ['can_login' => true];
    // }

    //   validateUserLeave($id);
      //new update for leave validation

    $resultsched = mysqli_query($con, "Select * from workdays INNER JOIN 
    workschedule ON workdays.SchedTime=workschedule.WorkSchedID 
    inner join schedeffectivity as c on workdays.EFID=c.efids
		where (workdays.empid='$id') and (workdays.Day_s='$day_desc')
		and ('$today3' >= dfrom) and ('$today3' <= dto) and workschedule.WorkSchedID <> 0");
    $row = mysqli_fetch_array($resultsched); 
    $cntsched= mysqli_num_rows ($resultsched);

    if($cntsched==0 and $otvalid==0){
        print 100 ;
    }else{

      if ($row['SchedTime'] != 0){
        //initialized data for attendance
        $tfrom = $row['TimeFrom'];

        // ogm lilo script end
        $tto = $row['TimeTo'];
        $tcross = $row['TimeCross'];
        $today = date("Y-m-d H:i:s");
        $dnow = date("Y-m-d");
        $wfromtime = $dnow. ' ' .$tfrom;
        $timestamp = strtotime($wfromtime);
        $timestamp2 = strtotime($today);

        //get minslck during timein using timestamp
        if($timestamp > $timestamp2){ 
            $nminlack=0;
        }else{  

          $nminlack = (time() - strtotime($wfromtime)) / 60;
        }

        //validate graceperiod
        if ($nminlack <= $gp){
          $nminlack = 0;
        }

        //validate if schedule crosses date
        if($tcross == 1){
          $tdom = date('Y-m-d',strtotime($dnow . "+1 days"));
        }else{
          $tdom = $dnow;
        }              
      }
               
      $has_log=0;
      //check if has login
      $resultlilo = mysqli_query($con, "Select * from attendancelog where EmpID='$id' order by LogID DESC");
      $reslilo = mysqli_fetch_array($resultlilo); 
      $cntlilo= mysqli_num_rows ($resultlilo);    
      $datetimein = date("Y-m-d" ,strtotime($reslilo['TimeIn']));
      if ($cntlilo==0){ 
          $has_log = 1;
      }else{
        if(($cntlilo>0) && ($reslilo['TimeOut']==NULL)) {   
          //initialized data from login
          $lid=$reslilo['LogID'];
          $timin=$reslilo['TimeIn'];
          $dfrom=$reslilo['WSFrom'];
          $fromwcs=$dfrom. ' ' .$tfrom;
          $dto=$reslilo['WSTo'];
          $late=$reslilo['MinsLack'];
          $wto=$dto. ' ' .$tto;

          //check for late
          if($late > 0){
            $calcu=$timin;
          } else{
            $calcu= $fromwcs;
          }
                                                
          $tswto = strtotime($wto);
          $timenow = strtotime($today);

          //check for total time
          if ($timin > $fromwcs){
            $calcu1= $wto;
            if(time() >= strtotime($wto)){
              $hrsSum= (strtotime($wto) - strtotime($timin)) / 60 / 60;
            }else{
              $hrsSum= ( time() -strtotime($timin)) /60 / 60;   
            }       
          }else{
            $calcu1= date("Y-m-d H:i:s");
            if(time() >= strtotime($wto)) {
              $hrsSum= (strtotime($wto) - strtotime($fromwcs)) /60 / 60;
            }else{
                $hrsSum= (time() -strtotime($fromwcs)) /60 / 60;
            }
          }

          //user ruling
          if ($hrsSum >= 6 and $hrsSum <=11){
            $minhrs=660 - ($hrsSum*60);
          }elseif ($hrsSum >= 6 and $hrsSum > 11){
            $minhrs=660 - ($hrsSum*60);
          }else{
            $minhrs=600 - ($hrsSum*60);
          }
                     
          if($timenow > $tswto ){
            //if you are end of shift
            $has_log=2;
            print 1;
              
            // if logout
            if ($datetimein< $dnow){
                $dt2=date('Y-m-d', strtotime($datetimein . '+1 days'));
                $today = $timin;
                $hrsSum=0;
                
            }
          } 

          // check if how many hours 
          {
            //search value of number of hour per day
            $sql="SELECT * from empdetails inner join cal_values on empdetails.CatType=cal_values.category where EmpID=:id";
            $statement = $pdo->prepare($sql);
            $statement->bindParam(':id' , $id);
            $statement->execute();
            $ftch = $statement->fetch();

            $rwhrsprday =  $ftch['HrsPerDay'];
            //out
            $dteStart = date_create($calcu1); 
            //in
            $dteEnd   = date_create($calcu); 
            //$dteDiff  = $dteStart->diff($dteEnd); 
            $dteDiff=date_diff($dteEnd,$dteStart);
            //get hour in min
            $gethr = floatval($dteDiff->format('%h'))*60;
            //get total min
            $getmin = floatval($dteDiff->format('%i')); 
            //get total hrs
            $gettotalmin=floatval(($gethr + $getmin))/60;
            //if under time
          }
          
          if ($_SESSION['UserType']==2){
            $rwhrsprday = $rwhrsprday - 1.25;
          }else{
            $rwhrsprday = $rwhrsprday +1;
          }
                    
          if ($cntot<=0){
            if ($rwhrsprday>$gettotalmin && $_SESSION['UserType']==3){
              //search if has OB
              $day=date("d");
              $yr=date("Y");
              $mnth=date("m");
              $sqlemp="SELECT * from obs where day(OBDateFrom)=:dy and month(OBDateFrom)=:mn and year(OBDateFrom)=:yr and EmpID=:id";
              $statementeo = $pdo->prepare($sqlemp);
              $statementeo->bindParam(':id' , $id);
              $statementeo->bindParam(':dy' , $day);
              $statementeo->bindParam(':yr' , $yr);
              $statementeo->bindParam(':mn' , $mnth);
              $statementeo->execute();
              $roweo=$statementeo->fetch();

              if ($statementeo->rowCount()>0){
                $timeOB=$roweo['OBDateFrom'] . " " . $roweo['OBTimeFrom'];
                $timeOBC=strtotime($timeOB);

                if ($timenow>=$timeOBC){
                  $has_log=2;
                  print 1;
                }else{
                  $has_log=2;
                  print 1;
                }
                  
              }else{
                //searh if has EO
                $sqlemp="SELECT * from earlyout where LogID=:lid";
                $statementeo = $pdo->prepare($sqlemp);
                $statementeo->bindParam(':lid' , $lid);
                $statementeo->execute();
                $roweo=$statementeo->fetch();
                if ($statementeo->rowCount()>0){
                  if ($roweo['Status']==2 || $roweo['Status']==4){
                        //if has EO filed and Approved
                        $has_log=2;
                        print 1;
                  }else if ($roweo['Status']==1){
                      //if EO filed but not Approved
                      print 101;
                      return;
                  }else{
                      //if EO filed but Cancel or Disapproved
                      print 102;
                      return;
                  }
                }else{
                  //if No EO Filed
                  if($timenow > $tswto ){
                      $has_log=2;
                      print 1;
                  }else{
                    $has_log=2;
                    print 1;
                  }
                }
              }
            }else{
              //if complete hours
              $has_log=2;
              print 1;
            }     

          }else{
            //if complete hours
            $has_log=2;
            print 1;
          }
        
        }else{
          $has_log=1;
          print 2;
        }          
      }  

      if ($has_log==1){

         // Login absence gate: block a regular employee's Cenar clock-in when they
         // have an unaccounted absence, until a superior files leave/OB. Fail-open.
         if ($_SESSION['UserType']==3){
             require_once __DIR__ . '/../includes/loginabsencegate.php';
             $gateDates = getUnaccountedAbsences($pdo, $id, $compid);
             if (!empty($gateDates)){
                 print '108|' . implode(',', $gateDates);
                 return;
             }
         }

         //ogm lilo script remove this script and enable print 2 above if you want to disable this
      
        {
            if($_SESSION['id']=="WeDoinc-0010") {
              $tfrom="08:00:00";
            }

            $wfromtime = $dnow. ' ' .$tfrom;
            $timestamp = strtotime($wfromtime);
            if($timestamp > $timestamp2){ 
                $nminlack=0;
            }else{  
              $nminlack = (time() - strtotime($wfromtime)) / 60;
            }
          
            if ($_SESSION['UserType']==3){
              if($nminlack > 0){
                $id=$_SESSION['id'];
                $ch=" Tardy Log In attempt";
                $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id' , $id);
                $stmt->bindParam(':empact', $ch);
                $stmt->bindParam(':ddt', $today2);
                $stmt->execute(); 
                // print 105;
                // return; 
              }else{
                print 2;
              }
            }else{
              print 2;
            }
        }      
           //ogm script end

        $rsss=2;    
        $wsched= $tfrom;
        $wsched2=$tto;

        $id=$_SESSION['id'];
        $ch="Logged In to Cenar";
        $sql = "INSERT INTO attendancelog (EmpID,wsched,wsched2,WSFrom,TimeIn,WSTo,MinsLack,DateTimeInput) VALUES (:id,:sched,:sched2,:wfrom,:tin,:wto,:mlac,:tdy)";
        //print $sql;
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $id);
        $stmt->bindParam(':sched', $wsched);
        $stmt->bindParam(':sched2', $wsched2);
        $stmt->bindParam(':wfrom', $dnow);
        $stmt->bindParam(':tin', $today);
        $stmt->bindParam(':wto',  $tdom);
        $stmt->bindParam(':mlac', $nminlack);
        $stmt->bindParam(':tdy', $today);
        $stmt->execute(); 
        // insert into dars
        $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $id);
        $stmt->bindParam(':empact', $ch);
        $stmt->bindParam(':ddt', $today2);
        $stmt->execute(); 
    
      }elseif ($has_log==2){
        $rsss=1;
        //out
        $dteStart = date_create($calcu1); 
        //in
        $dteEnd   = date_create($calcu); 
        //$dteDiff  = $dteStart->diff($dteEnd); 
        $dteDiff=date_diff($dteEnd,$dteStart);
        //get hour in min
        $gethr = floatval($dteDiff->format('%h'))*60;
        //get total min
        $getmin = floatval($dteDiff->format('%i')); 
        //get total hrs
        $gettotalmin=floatval(($gethr + $getmin))/60;
        //print $calcu;
        //print $calcu1;
        if ($hrsSum>=11){
            $hrsSum=11;
        }
      
        //managers script
        if ($_SESSION['UserType']==2){
          $sql="select * from lilovalidation where EmpCompID=:ecid";
          $stliloval = $pdo->prepare($sql);
          $stliloval->bindParam(':ecid', $compid);
          $stliloval->execute();
          $ftchrow=$stliloval->fetch();

          if ($stliloval->rowCount() > 0){
            if ($ftchrow['ManagersOverride']==1){
              $hrsSum=11;
            }else{
              if ($hrsSum< $ftchrow['ManagersTime']){
                $hrsx = $ftchrow['ManagersTime']-$hrsSum;
                $hrsSum=11-$hrsx;
              }else{
                $hrsSum=11;
              }
            }
          }
        }

        $sql = "UPDATE attendancelog SET TimeOut=:tout, MinsLack2=:min2,durationtime=:bh1 WHERE LogID=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $lid);
        $stmt->bindParam(':tout', $today);
        $stmt->bindParam(':bh1',  $hrsSum);
        $stmt->bindParam(':min2',$minhrs);
        $stmt->execute();
              
        $id=$_SESSION['id'];
        $ch="Logged Out from Cenar";
        // insert into dars
        $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id' , $id);
        $stmt->bindParam(':empact', $ch);
        $stmt->bindParam(':ddt', $today2);
        $stmt->execute();

      }       
    }

  ?>
