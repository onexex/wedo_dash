
<?php 
  include 'w_conn.php';
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
 //set default time  
  date_default_timezone_set("Asia/Manila"); 
 //value pre initialized  
  $id=$_SESSION['id'];
  $isid=$_SESSION['EmpISID'];
  $compid=$_SESSION['CompID'];
  $statid=1;
  $today =date("Y-m-d h:i A"); 
  $todaydar = date("Y-m-d H:i:s");
  $today1 =date("Y-m-d H:i:s", strtotime($_POST['dttd']));
    $todayFdate =date("Y-m-d", strtotime($_POST['dttd']));
   $today2 =date("Y-m-d H:i:s"); 
   $tdy3=date("Y-m-d");
   $filed_date=date("Y-m-d h:i A", strtotime($_POST['dttd']));
   $filed_date1=$_POST['dttd'];
   $comment=$_POST['pur'];
   
//geteo validation
$stmt= $pdo->prepare("select * from eovalidation where CompID='$compid'");
$stmt->execute();
$rowvalid   = $stmt->fetch(); 
$EOIsBefore = "0";
$EOIsTardy  = "0";
$EOIsAfter  = "0";
$EOIsBeforeDays = "0";
$EOIsAfterDays  = "0";
$EOIsNoIN = "0";
$EOIsAutoOut  = "0";

if($stmt->rowCount() > 0) 
{
  $EOIsBefore = $rowvalid['IsBefore'];
  $EOIsTardy  = $rowvalid['IsTardy'];
  $EOIsAfter  = $rowvalid['IsAfter'];
  $EOIsBeforeDays = $rowvalid['IsBeforeDays'];
  $EOIsAfterDays  = $rowvalid['IsAfterDays'];
  $EOIsNoIN  = $rowvalid['IsLogoutNoIN'];
  $EOIsAutoOut  = $rowvalid['IsAutoLogout'];
}
  
//check worksched
$day_desc= date("l");
$today =date("Y-m-d"); 

//Get schedule
$stmt= $pdo->prepare("Select * from workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID inner join schedeffectivity as c on workdays.EFID=c.efids where (workdays.empid='$id') 
and (workdays.Day_s='$day_desc') and ('$today' >= dfrom) and ('$today' <= dto)  and SchedTime <> 0 ");

    $stmt->execute();
    $sched = $stmt->fetch();

if($stmt->rowCount() > 0) 
    {
        $from=$sched['TimeFrom'];
        $to=$sched['TimeTo'];
    }


//get the work schedule

$resultsched = mysqli_query($con, "Select * from workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID inner join schedeffectivity as c on workdays.EFID=c.efids where (workdays.empid='$id') 
and (workdays.Day_s='$day_desc') and ('$today' >= dfrom) and ('$today' <= dto)  and SchedTime <> 0 ");

$rowsc = mysqli_fetch_array($resultsched); 
$tfrom = $rowsc['TimeFrom'];
$dnow = date("Y-m-d");
$wfromtime = $dnow. ' ' .$tfrom;

//check eovalidation
$stmteoval= $pdo->prepare("SELECT * FROM eovalidation WHERE CompID='$compid'");
$stmteoval->execute();
$roweoval = $stmteoval->fetch();



//check if late to validate access to eo
$stmt= $pdo->prepare("SELECT * FROM attendancelog WHERE EmpID='$id' AND WSFrom='$filed_date1' ORDER BY LogID DESC");
$stmt->execute();
$row = $stmt->fetch();
$logid=1;


if($stmt->rowCount() > 0) 
 {
    $dfrom=$row['WSFrom'];
    $dto=$row['WSTo'];
    $minlate=$row['MinsLack'];
    $timein=$row['TimeIn'];
    $logid=$row['LogID'];
  
    if ($roweoval['sworkhours']==1){
        $wh = $roweoval['workhours'];
        // $wh = 3;
        
        //rs is hours to complete before filing eo
        $rs=($wh/2) + 1;
        // $rs=3;
    
        if ($wfromtime>$timein){
            $datecomp=$wfromtime;
        }else{
            $datecomp=$timein;
        }
        $dteStart = new DateTime($today2); 
        $dteEnd   = new DateTime($datecomp);
        $dteDiff  = $dteStart->diff($dteEnd); 
        $sumhr = $dteDiff->format("%h" ); 
        // $summin = $dteDiff->format("%i" ); 
        // $sumsec = $dteDiff->format("%s" ); 
        // $sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);

        // $hourdiff = round((strtotime($today) - strtotime($datecomp))/3600, 1);
        // $hourdiff = $hourdiff + 1;

        if ($sumhr<$rs){
           print 104;
           return;
        }else{
            
        }
    }

}else{
    print 103;
    return;
}



//value from calculation
   //get totaldays 
   $dnow = new DateTime($today); 
   $dfiled   = new DateTime($filed_date);  

   $dteDiff  = $dnow->diff($dfiled); 
   $Totaldays = $dteDiff->format("%D"); 
   $statusval=1;


//after eo
if ($todayFdate < $tdy3){
  if ($EOIsAfter == 1){
    $statusval=1;
  }else{
    $statusval=6;
  }
}
//during and before eo
else if ($todayFdate > $tdy3){
  if ($EOIsBefore == 1){
    $statusval=1;
  }else{
    $statusval=6;
  }
}
else{
   $statusval=1;
}

try {


// check if eo already exist
$sql = "SELECT * FROM earlyout WHERE EmpID=:id and  EmpISID=:is and LogID=:lid and DFile=:df and Purpose=:pur and Status=:status";
$stmtslct = $pdo->prepare($sql);
$stmtslct->bindParam(':id' , $id);
$stmtslct->bindParam(':is', $isid);
$stmtslct->bindParam(':lid', $logid);
$stmtslct->bindParam(':df', $filed_date1);
$stmtslct->bindParam(':pur',  $_POST['pur']);
$stmtslct->bindParam(':status', $statusval);
$stmtslct->execute();
$rwscnt =  $stmtslct->rowCount(); 
if ($rwscnt>0){
    print 102;
    return;
}              
else{
    

if ($comment == "" || $comment == "")
{
  print 7;
}
else
{
   
    //if notallowed no in
    if ($EOIsNoIN == 1){
      //after
      if ($filed_date1 < $tdy3)
      {

                  if ($EOIsAfter == 1){
                       if ($Totaldays > $EOIsAfterDays )
                        {
                         Print 101;//exceed max 
                        }
                      else
                      {
                         if ($EOIsTardy == 1) 
                         {
                        //   print 98;
                        //   return;
                        try {
                            insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval); 
                        } catch (Exception $e) {
                            echo $e->getMessage();
                        }
                          

                         }
                          elseif ($minlate == 0 || $minlate == '' )
                          { 
                            insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval); 

                          }
                        else
                          {
                            Print 2;//not allowed if tardy
                          }
                      }
               }
              else{  
                        // if (($EOIsTardy == 0) && ($minlate == 0 || $minlate == '' ))
                        //   { 
                        //     insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval); 
                        //   }
                        // else
                        //   {
                            Print 3;//not allowed after
                         // }                
                    }         
       }  
       //before      
      elseif ($filed_date1 > $tdy3){
              if ($EOIsBefore == 1)
                 {
                   if (($Totaldays + 1) > $EOIsBeforeDays )
                      {
                        Print 1;//exceed max 
                      }else {
                       insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval);                  
                      }
                  }else{  
                        // if (($EOIsTardy == 0) && ($minlate == 0 || $minlate == '' ))
                        //   { print $EOIsTardy .'' . $minlate ;
                        //     //insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval); 
                        //   }
                        // else
                        //   {
                            Print 4;//Late Filling
                          //}               
                    }                  
      }else{  
              if ($EOIsTardy == 1){
                    
                insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval);
              }
              elseif ($minlate == 0 || $minlate == '' ) { 
                      insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval); 
              }else{
                // print $minlate;
                Print 2;//Tardy
              }                
              }  
    }
    else
    {
      Print 6;
    }
  }
}  
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
//insert_eo($pdo,$id,$isid,$logid,$today1,$today2,$statusval);   
//insert_eo($pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval);        
function insert_eo(PDO $pdo,$id,$isid,$logid,$today1,$filed_date1,$today2,$statusval)
{
    
  $sql = "INSERT INTO earlyout (EmpID,EmpISID,LogID,FDate,DFile,Purpose,DateTimeUpdated,DateTimeInputed,Status) VALUES (:id,:is,:lid,:fd,:df,:pur,:dtup,:dtin,:status)";
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':id' , $id);
               $stmt->bindParam(':is', $isid);
               $stmt->bindParam(':lid', $logid);
               $stmt->bindParam(':fd', $today1);
               $stmt->bindParam(':df', $filed_date1);
               $stmt->bindParam(':pur',  $_POST['pur']);
               $stmt->bindParam(':dtup', $today2);
               $stmt->bindParam(':dtin', $today2);
               $stmt->bindParam(':status', $statusval);
               $stmt->execute();                 
                  
}
try{

                    $id=$_SESSION['id'];
                    $ch="Applied EO";
                    // insert into dars
                    $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id' , $id);
                    $stmt->bindParam(':empact', $ch);
                    $stmt->bindParam(':ddt', $todaydar);
                    $stmt->execute();

    $todaylogout = date("Y-m-d H:i:s");
  $stmt= $pdo->prepare("SELECT * FROM attendancelog WHERE EmpID='$id' AND WSFrom='$filed_date1' ORDER BY LogID DESC");
                    $stmt->execute();
                    $row = $stmt->fetch();
                    $lid=$row['LogID'];
                   
                   
                        $gettotalmin=0;
                        $minlack2=0;
                       $sql = "UPDATE attendancelog SET TimeOut=:tout, MinsLack2=:min2,durationtime=:bh1 WHERE LogID=:id";
                                                                  $stmt = $pdo->prepare($sql);
                                                                  $stmt->bindParam(':id', $lid);
                                                                  $stmt->bindParam(':tout', $todaylogout);
                                                                  $stmt->bindParam(':bh1',  $gettotalmin);
                                                                  $stmt->bindParam(':min2',$minlack2);
                                                                //   $stmt->execute();
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
 ?>


