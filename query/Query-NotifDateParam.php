<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else{ header ('location: login'); }
     
    try{
        try{
          include 'w_conn.php';
          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $e)
        {
            die("ERROR: Could not connect. " . $e->getMessage());
        }
                           $isid=$_SESSION['id'];
                        $dt2= date('Y-m-d', strtotime($_GET['dtto']  . ' + 1 days'));
                            // for Admin
                  if ($_SESSION['UserType']==1){
                              $sql="SELECT a.OBID as mid, 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') , '<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and a.OBStatus<>1  and a.OBStatus<>7 and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT a.SID as mid,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y') , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and a.Status<>1 and a.Status<>7 and a.DateTimeInputed between :dt1 and :dt2

                  UNION ALL 

                  SELECT a.LeaveID as mid,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id and a.LStatus<>1 and a.LStatus<>7 and a.LDateTimeUpdated between :dt1 and :dt2
                    
                  
                   UNION ALL 

                  SELECT a.OTLOGID as mid, 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and  a.Status<>1 and a.Status<>7 and a.DateTimeUpdate between :dt1 and :dt2   

                  UNION ALL 
                            SELECT a.OBID as mid, 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where  a.OBStatus=2 or (a.OBStatus=1 and a.EmpSID=:id) and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT a.SID as mid,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where   a.Status=2 or (a.Status=1 and a.EmpISID=:id) and a.DateTimeUpdated between :dt1 and :dt2

                  UNION ALL 

                  SELECT a.LeaveID as mid,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where  a.LStatus=2 or (a.LStatus=1 and a.EmpSID=:id) and a.LDateTimeUpdated between :dt1 and :dt2
                  
                   UNION ALL 

                  SELECT a.OTLOGID as mid, 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.Status=2 or (a.Status=1 and a.EmpISID=:id) and a.DateTimeUpdate between :dt1 and :dt2      order by dtp desc limit 20 
                

                  ";
                  }
                  //Manager
                  else if($_SESSION['UserType']==2){
                           
                        $sql="SELECT a.OBID as mid, a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and a.OBStatus<>1  and a.OBStatus<>7 and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT a.SID as mid,a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and a.Status<>1 and a.Status<>7 and a.DateTimeUpdated between :dt1 and :dt2

                  UNION ALL 

                  SELECT a.LeaveID as mid,a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id and a.LStatus<>1 and a.LStatus<>7 and a.LDateTimeUpdated between :dt1 and :dt2
                  
                   UNION ALL 

                  SELECT a.OTLOGID as mid, a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and  a.Status<>1 and a.Status<>7 and a.DateTimeUpdate between :dt1 and :dt2   

                  UNION ALL 

                  SELECT a.OBID as mid, a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat , a.OBISReason as rsn from 
                    obs as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpSID=:id  and a.OBStatus=1 and a.OBUpdated between :dt1 and :dt2   
                    
                  UNION ALL 

                  SELECT a.SID as mid,a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn  from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where (a.EmpISID=:id ) and a.Status=1  and a.DateTimeUpdated between :dt1 and :dt2   

                  UNION ALL 

                  SELECT a.OTLOGID as mid,  a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpISID=:id and  a.Status=1  and a.DateTimeUpdate between :dt1 and :dt2   

                  UNION ALL 

                  SELECT a.LeaveID as mid,a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat, a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpSID=:id and a.LStatus=1 and a.LDateTimeUpdated between :dt1 and :dt2    order by dtp desc 
                  
                      ";
                  }
                 
                  else{
                    //user

                  $sql="SELECT a.OBID as mid, a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.OBUpdated as dtp,c.StatusDesc as stat from 
                    obs as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and d.EmpCompID=:cmpid and a.OBStatus<>1 and a.OBStatus<>7 and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT a.SID as mid,a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ',  DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.DateTimeUpdated as dtp,c.StatusDesc as stat from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status<>1 and a.Status<>7 and a.DateTimeUpdated between :dt1 and :dt2

                   UNION ALL 

                  SELECT a.LeaveID as mid,a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.LDateTimeUpdated as dtp,c.StatusDesc as stat from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.LStatus<>1 and a.LStatus<>7  and a.LDateTimeUpdated between :dt1 and :dt2
                  
                   UNION ALL 

                  SELECT a.OTLOGID as mid, a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.DateTimeUpdate as dtp,c.StatusDesc as stat from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status<>1 and a.Status<>7 and a.DateTimeUpdate between :dt1 and :dt2 order by dtp desc

                  ";

                  }
                  if ($_SESSION['UserType']==3){
                    $statement = $pdo->prepare($sql);
                    $statement->bindParam(':id' ,  $_SESSION['id']);
                    $statement->bindParam(':cmpid' , $_SESSION['CompID']);
                    $statement->bindParam(':dt1' , $_GET['dtfrm']);
                    $statement->bindParam(':dt2' , $dt2);
                    $statement->execute();
                  }else if ($_SESSION['UserType']==2){
                    $statement = $pdo->prepare($sql);
                    $statement->bindParam(':id' , $isid);
                    $statement->bindParam(':dt1' , $_GET['dtfrm']);
                    $statement->bindParam(':dt2' , $dt2);
                    $statement->execute();
                  }else{
                    
                    $statement = $pdo->prepare($sql);
                    $statement->bindParam(':id' , $_SESSION['id']);
                    $statement->bindParam(':dt1' , $_GET['dtfrm']);
                    $statement->bindParam(':dt2' , $dt2);
                    $statement->execute();
                  }

              

                  while ($row = $statement->fetch()){
                   
                    ?>
                     <tr>
                    <td><?php  echo $row['ntype']; ?></td>
                    <td><?php  echo $row['dtfromto']; ?></td>
                    <td><?php echo date("F d, Y h:i:s A", strtotime($row['dtp']));   ?></td>
                    <td><?php  echo $row['stat']; ?></td>
                    <td><button class="btn btn-warning" data-toggle="modal" data-target="#myModal<?php echo  $row['id']; ?>"><i class="fa fa-eye" aria-hidden="true"></i></button>
                        </td>
                        

            
                        <!-- Modal -->
                  <div class="modal fade" id="myModal<?php echo  $row['id']; ?>" role="dialog">
                    <div class="modal-dialog">
                    
                      <!-- Modal content-->
                      <div class="modal-content" >
                        <div class="modal-header"  style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>;padding: 7px;">
                          <button type="button" class="close" style="color: #fff;" data-dismiss="modal">&times;</button>
   
                        </div>
                        <div class="modal-body">
                          <h4><?php  echo $row['ntype']; ?></h4>
                          <?php  echo $row['descript']; ?><br>
                          <?php  echo $row['dtfromto']; ?><br>
                            <h5><?php if ($row['st']=="EO"){
                                $statementeo = $pdo->prepare("select * from earlyout inner join attendancelog on earlyout.LogID=attendancelog.LogID where SID=:id");
                                $statementeo->bindParam(':id' , $row['mid']);
                                $statementeo->execute();
                                $roweo = $statementeo->fetch();
                             echo "Time Logout : ". date("h:i:s A", strtotime($roweo['TimeOut']));
                         }
                         
                         ?></h5>
                          Datetime Updated : <?php echo date("F d, Y h:i:s A", strtotime($row['dtp']));   ?>
                         
                          <?php 
                            if ($row['rsn']){
                                echo "<br>Reason : " . $row['rsn'];
                            }
                          
                          ?>
                          <br>
                          <div class="form-group rsn rsn<?php echo  $row['id']; ?>">
                            <label>Reason :</label>
                            <textarea name="rs<?php echo  $row['id']; ?>" id="rs<?php echo  $row['id']; ?>" class="form-control"></textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                                <?php
                           if ($_SESSION['UserType']==3){
                        

                          }else if ($_SESSION['UserType']==2){
                                if ($row['BSTat']==1){
                                 if ($row['EmpID']==$_SESSION['id']){
                                     
                                 }else{
                                     
                            ?>
                                <button type="button" class="btn btn-danger" id="<?php echo  $row['id']; ?>">Approve</button>
                                <button type="button" class="btn btn-info" id="<?php echo  $row['id']; ?>">DisApprove</button> 
                                    <br>
                                <label style="display:block;"><?php  echo $row['stat']; ?></label>
                            <?php
                                }
                                 }
                              ?>
                              <?php
                          }else{
                              ?>
                                 <button type="button" class="btn btn-danger" id="<?php echo  $row['id']; ?>">Approve</button>
                                        <button type="button" class="btn btn-info" id="<?php echo  $row['id']; ?>">DisApprove</button> 
                                            <br>
                                        <label style="display:block;"><?php  echo $row['stat']; ?></label>
                              <?php
                          }
                                
                                ?>
                   
                        </div>
                      </div>
                      
                    </div>
                  </div>
                      </tr>

                    <?php
                  }
                  }
                  //catch exception
catch(Exception $e) {
  echo 'Message: ' .$e->getMessage();
}
                ?>
                