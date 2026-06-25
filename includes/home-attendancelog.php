 <!-- view all OB OT EO ALAS ATTENDANCE IN ONE -->
 <?php
  try{
      include 'w_conn.php';
      $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }catch(PDOException $e){
  die("ERROR: Could not connect. " . $e->getMessage());
  }
  $id=$_SESSION['id'];


	// protected $casts = [

	// ];
  while($dt1<=$dt2){
    $dis='';
    $dytoday=date("d", strtotime($dt2));
    $yrtoday=date("Y", strtotime($dt2));
    $mnthtoday=date("m", strtotime($dt2));
      $formatted_date = date('Y-m-d', strtotime($dt2));

        // <!-- search worksched  -->
  
        $day_desc=date("l", strtotime($dt2));
        // $day_desc="Firday";
      
        // $resultsched = mysqli_query($con, "Select * from workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID where workdays.empid='$id' and workdays.Day_s='$day_desc'  ");
        
        //  $resultsched = mysqli_query($con, "Select * from workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID inner join schedeffectivity as c on workdays.EFID=c.efids where (workdays.empid='$id') 
        // and (workdays.Day_s='$day_desc') and  SchedTime <> 0 ");
        $resultsched = mysqli_query($con, "Select * from workdays INNER JOIN 
        workschedule ON workdays.SchedTime=workschedule.WorkSchedID 
        inner join schedeffectivity as c on workdays.EFID=c.efids
        where (workdays.empid='$id') and (workdays.Day_s='$day_desc')
        order by workdays.WID desc Limit 1");

        $row = mysqli_fetch_array($resultsched); 
        $cntsched= mysqli_num_rows ($resultsched);
        // search attendance in OT OB Leave and cenar
        // $sql="SELECT a.OBID as mid,'OB' as st,a.OBDateFrom as dateattend from 
        // obs as a 
        // INNER JOIN employees as b ON a.EmpID=b.EmpID 
        // INNER JOIN empdetails as d ON b.EmpID=d.EmpID where a.EmpID=:id and (OBStatus=1 or OBStatus=2 or OBStatus=4) and (day(a.OBDateFrom)=:dytod and year(a.OBDateFrom)=:yrtod and month(a.OBDateFrom)=:mnth)

        // UNION ALL 

        // SELECT a.SID as mid,'EO' as st,a.DateTimeInputed as dateattend from 
        // earlyout as a 
        // INNER JOIN employees as b ON a.EmpID=b.EmpID 
        // INNER JOIN empdetails as d ON b.EmpID=d.EmpID where a.EmpID=:id and (Status=1 or Status=2 or Status=4) and (day(a.DateTimeInputed)=:dytod and year(a.DateTimeInputed)=:yrtod and month(a.DateTimeInputed)=:mnth)
        // UNION ALL 

        // SELECT a.LeaveID as mid,'Leave' as st,LEnd as dateattend from 
        // hleavesbd as a 
        // INNER JOIN employees as b ON a.EmpID=b.EmpID 
        // INNER JOIN empdetails as d ON b.EmpID=d.EmpID where a.EmpID=:id and (LStatus=1 or LStatus=2 or LStatus=4) and (day(LEnd)=:dytod and year(LEnd)=:yrtod and month(LEnd)=:mnth)

        // UNION ALL 

        // SELECT a.OTLOGID as mid, 'OT' as st,TimeIn as dateattend from 
        // otattendancelog as a 
        // INNER JOIN employees as b ON a.EmpID=b.EmpID 
        // INNER JOIN empdetails as d ON b.EmpID=d.EmpID  where a.EmpID=:id and (Status=1 or Status=2 or Status=4) and (day(TimeIn)=:dytod and year(TimeIn)=:yrtod and month(TimeIn)=:mnth)

        // UNION ALL 

        // SELECT a.LogID as mid, 'Cenar' as st, TimeIn as dateattend from 
        // attendancelog as a 
        // INNER JOIN employees as b ON a.EmpID=b.EmpID 
        // INNER JOIN empdetails as d ON b.EmpID=d.EmpID  where a.EmpID=:id and (day(TimeIn)=:dytod and year(TimeIn)=:yrtod and month(TimeIn)=:mnth)
        // order by dateattend desc ";
        
          $sql="SELECT a.OBID as mid,'OB' as st,a.OBDateFrom as dateattend from 
        obshbd as a 
        INNER JOIN employees as b ON a.EmpID=b.EmpID 
        INNER JOIN empdetails as d ON b.EmpID=d.EmpID where a.EmpID=:id and (OBStatus=1 or OBStatus=2 or OBStatus=4) and OBDateFrom = '$formatted_date'

        UNION ALL 

        SELECT a.SID as mid,'EO' as st,a.DateTimeInputed as dateattend from 
        earlyout as a 
        INNER JOIN employees as b ON a.EmpID=b.EmpID 
        INNER JOIN empdetails as d ON b.EmpID=d.EmpID where a.EmpID=:id and (Status=1 or Status=2 or Status=4) and a.DateTimeInputed = '$formatted_date'
        UNION ALL 

        SELECT a.LeaveID as mid,'Leave' as st,LEnd as dateattend from 
        hleavesbd as a 
        INNER JOIN employees as b ON a.EmpID=b.EmpID 
        INNER JOIN empdetails as d ON b.EmpID=d.EmpID where a.EmpID=:id and (LStatus=1 or LStatus=2 or LStatus=4) and a.LEnd = '$formatted_date'

        UNION ALL 

        SELECT a.OTLOGID as mid, 'OT' as st,TimeIn as dateattend from 
        otattendancelog as a 
        INNER JOIN employees as b ON a.EmpID=b.EmpID 
        INNER JOIN empdetails as d ON b.EmpID=d.EmpID  where a.EmpID=:id and (Status=1 or Status=2 or Status=4) and a.TimeIn = '$formatted_date'

        UNION ALL 

        SELECT a.LogID as mid, 'Cenar' as st, TimeIn as dateattend from 
        attendancelog as a 
        INNER JOIN employees as b ON a.EmpID=b.EmpID 
        INNER JOIN empdetails as d ON b.EmpID=d.EmpID  where a.EmpID=:id and WSFrom='$formatted_date'
        order by dateattend desc ";

        $attendancelog = $pdo->prepare($sql);
        $attendancelog->bindParam(':id' , $id);
        // $attendancelog->bindParam(':dytod' , $dytoday);
        // $attendancelog->bindParam(':yrtod' , $yrtoday);
        // $attendancelog->bindParam(':mnth' , $mnthtoday);
        $attendancelog->execute();
        $rowCountCenar=$attendancelog->rowCount();

        if ($rowCountCenar<=0){
              if ($cntsched<=0){ 
                    $dis =  "No schedule"; 
              // }else if(is_null($cntsched)){
              //   $dis = "EMPTY";
              }else{ 
                  $tdyhol=date("Y-m-d", strtotime($dt2));
                  $sql2="select * from holidays where HCompID=:cmid and Hdate=:dth";
                  $attendancelog2 = $pdo->prepare($sql2);
                  $attendancelog2->bindParam(':dth' , $tdyhol);
                  $attendancelog2->bindParam(':cmid' , $_SESSION['CompID']);
                  $attendancelog2->execute();
                  if ($attendancelog2->rowCount()>0){
                      $row4=$attendancelog2->fetch();
                      $dis = $row4['Hdescription']; 
                  }else{
                    if( $row['SchedTime']==0){
                      $dis = "Rest Day"; 
                    }else{
                      $dis = "No Attendance"; 
                    } 
                     
                  }
              }
            ?>
          <tr>
            <td class="darth"><?php echo date("F j, Y", strtotime($dt2));?></td>
            <td class="darth"><?php echo date("l", strtotime($dt2));?></td>
            <td class="darth <?php if($dis=="No Attendance") {echo "flash bg-danger";}?>" ><?php echo $dis; ?></td>
            <td class="darth <?php if($dis=="No Attendance") {echo "flash bg-danger";}?>"><?php echo $dis; ?></td>
            <td class="darth <?php if($dis=="No Attendance") {echo "flash bg-danger";}?>"><?php echo $dis; ?></td>
            <td class="darth <?php if($dis=="No Attendance") {echo "flash bg-danger";}?>"><?php echo $dis; ?></td>
            <td class="darth <?php if($dis=="No Attendance") {echo "flash bg-danger";}?>"><?php echo $dis; ?></td>
          </tr>
          <?php
        }else{                  
          while ($row2 = $attendancelog->fetch()){
            //search for attendancelog
            if ($row2['st']=="Cenar"){
              $attendancelog2 = $pdo->prepare("select * from attendancelog where LogID = :id");
              $attendancelog2->bindParam(':id' , $row2['mid']);
              $attendancelog2->execute();
              $rowcenar = $attendancelog2->fetch();
              ?>
              <tr>
                <td class="darth"><?php   echo  date("F j, Y", strtotime($dt2));?></td>
                <td class="darth"><?php   echo  date("l", strtotime($dt2));?></td>
                <td class="darth"><?php if ($cntsched==0){ echo $rowcenar['wsched']; }else{ echo $rowcenar['wsched'] . " - " . $rowcenar['wsched2'];  } ?></td>
                <td class="darth td-dar"> <?php echo date("h:i:s A", strtotime($rowcenar['TimeIn']));  ?></td> 
                <td class="darth td-dar"> <?php  if ($rowcenar['TimeOut']==NULL){  }else{ echo date(" h:i:s A", strtotime($rowcenar['TimeOut'])); }?></td>
                <td class="darth"><?php echo "Onsite";?></td> 
                <td class="darth td-dar"> <?php if ($rowcenar['TimeOut']==NULL){ }else{echo $rowcenar['durationtime']; }?></td>   
              </tr>
              <?php            
            }
            //ob attendance
            elseif ($row2['st']=="OB"){
                // $attendancelog2 = $pdo->prepare("select * from obshbd where OBID = :id ");
                  $attendancelog2 = $pdo->prepare("select * from obshbd where OBID = :id  and OBDateFrom = '$formatted_date'");
                $attendancelog2->bindParam(':id' , $row2['mid']);
                $attendancelog2->execute();
                $rowcenar = $attendancelog2->fetch();

                $daysched=date("l", strtotime($rowcenar['OBDateFrom']));
                // $getWsched = mysqli_query($con, "Select * from workdays INNER JOIN 
                // workschedule ON workdays.SchedTime=workschedule.WorkSchedID 
                // inner join schedeffectivity as c on workdays.EFID=c.efids
                // where (workdays.empid='$id') and (workdays.Day_s='$daysched')
                // order by workdays.WID ");
                // $getWsched = $pdo->prepare("select * from workdays inner join workschedule on workdays.SchedTime=workschedule.WorkSchedID where empid = :id and Day_s = :wday");
                $getWsched = $pdo->prepare("Select * from workdays INNER JOIN 
                workschedule ON workdays.SchedTime=workschedule.WorkSchedID 
                inner join schedeffectivity as c on workdays.EFID=c.efids
                where (workdays.empid=:id ) and (workdays.Day_s= :wday)
                order by workdays.WID  Limit 1 ");
                $getWsched->bindParam(':wday' , $daysched);
                $getWsched->bindParam(':id' , $_SESSION['id']);
                $getWsched->execute();
                $rowWrkSched=$getWsched->fetch();
              ?>
              <tr>
                <td class="darth">  <?php   echo  date("F j, Y", strtotime($rowcenar['OBDateFrom']));?></td>
                <td class="darth">  <?php   echo  date("l", strtotime($rowcenar['OBDateFrom']));?></td>
                <td class="darth">  <?php   echo  date("h:i:s A", strtotime($rowcenar['OBTimeFrom'])) . " - " . date("h:i:s A", strtotime($rowcenar['OBTimeTo']));?></td>
                <td class="darth">  <?php   echo  date("h:i:s A", strtotime($rowcenar['OBTimeFrom']));?></td>
                <td class="darth">  <?php   echo  date("h:i:s A", strtotime($rowcenar['OBTimeTo']));?></td>
                <td class="darth">  <?php   echo  $row2['st']; ?> </td>
                <td class="darth">  <?php   echo  $rowcenar['OBDuration'];?></td>
              </tr>

              <?php
            }
            //ot
            elseif ($row2['st']=="OT"){
              $attendancelog25 = $pdo->prepare("select * from otattendancelog inner join status on otattendancelog.Status=status.StatusID where OTLOGID=:id");
              $attendancelog25->bindParam(':id' , $row2['mid']);
              $attendancelog25->execute();
              $rowcenar3 = $attendancelog25->fetch();
              
              $day_desc=date("l", strtotime($rowcenar3['TimeIn']));
              $dayfrom=date("h:i:s A", strtotime($rowcenar3['TimeIn']));
              $dayto=date("h:i:s A", strtotime($rowcenar3['TimeOut']));
              ?>
              <tr>
                <td class="darth"><?php  echo  date("F j, Y", strtotime($rowcenar3['TimeIn']));?></td>
                <td class="darth"><?php  echo  $day_desc;?></td>
                <td class="darth"><?php  echo  $dayfrom .  "-" . $dayto;?></td>
                <td class="darth"><?php  echo  $dayfrom;?></td>
                <td class="darth"><?php  echo  $dayto;?></td>
                <td class="darth"><?php echo  $row2['st'];  ?>/<?php  echo  $rowcenar3['StatusDesc'];?></td>
                <td class="darth"><?php echo  $rowcenar3['Duration'];  ?></td>
              </tr>
              <?php
            }
            //leave attendance
            elseif ($row2['st']=="Leave"){?>
              <tr>
                <td class="darth"><?php  echo date("F j, Y", strtotime($dt2)); ?></td>
                <td class="darth"><?php  echo  $day_desc;?></td>
                <td class="darth"><?php  echo  $row['TimeFrom'] . " - " . $row['TimeTo'];?></td>
                <td class="darth">Leave</td>
                <td class="darth">Leave</td>
                <td class="darth"><?php echo  $row2['st'];  ?></td>
                <td class="darth">0</td>
              </tr>

              <?php
            }
            //eo
            elseif ($row2['st']=="EO"){
              $attendancelog2 = $pdo->prepare("select * from earlyout inner join attendancelog on earlyout.LogID=attendancelog.LogID where SID = :id");
              $attendancelog2->bindParam(':id' , $row2['mid']);
              $attendancelog2->execute();
              $rowcenar = $attendancelog2->fetch();
              
              $day_desc=date("l", strtotime($lend));
              $resultsched = mysqli_query($con, "Select * from workdays INNER JOIN workschedule ON workdays.SchedTime=workschedule.WorkSchedID where workdays.empid='$id' and workdays.Day_s='$day_desc' and SchedTime<>0");
              $row = mysqli_fetch_array($resultsched); 
              $cntsched2= mysqli_num_rows ($resultsched);
              
              ?>
                <tr>
                  <td class="darth"><?php   echo  date("F j, Y", strtotime($dt2));?></td>      
                  <td class="darth"><?php   echo  date("l", strtotime($dt2));?></td>
                  <td class="darth"><?php  echo  $row['TimeFrom'] . " - " . $row['TimeTo'];?></td>
                  <td class="darth"><?php echo date("h:i:s A", strtotime($rowcenar['TimeIn']));  ?></td>
                  <td class="darth"><?php echo date("h:i:s A", strtotime($rowcenar['DateTimeInputed']));  ?></td>
                  <td class="darth"><?php  echo  $row2['st'];?></td>
                  <td class="darth">0</td>
              </tr>
              <?php
            }
            ?> 
            <?php
          }
        }
    $dt2=date('Y-m-d', strtotime($dt2 . ' - 1 days'));

  }
?>
