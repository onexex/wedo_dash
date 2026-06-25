 <?php
       if (session_status() === PHP_SESSION_NONE) { session_start(); }
        if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
        else{ header ('location: login.php'); }
          include 'w_conn.php';
          date_default_timezone_set("Asia/Manila");
          
       
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

                    try {
                        
                        $dt1= date('Y-m-d', strtotime(date("Y-m-d")  . ' - 7 days'));
                        $dt2= date('Y-m-d', strtotime(date("Y-m-d")  . ' + 1 days'));
                        
                        // for Admin
                  if ($_SESSION['UserType']==1){
                          
                      $sql="SELECT 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') , '<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and a.OBStatus<>1  and a.OBStatus<>7 and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT 'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y') , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and a.Status<>1 and a.Status<>7 and a.DateTimeInputed between :dt1 and :dt2

                  UNION ALL 

                  SELECT 'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id and a.LStatus<>1 and a.LStatus<>7 and a.LDateTimeUpdated between :dt1 and :dt2
                  
                   UNION ALL 

                  SELECT 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and  a.Status<>1 and a.Status<>7 and a.DateTimeUpdate between :dt1 and :dt2   

                  UNION ALL 
                            SELECT 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where  a.OBStatus=2 or (a.OBStatus=1 and a.EmpSID=:id) and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT 'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where   a.Status=2 or (a.Status=1 and a.EmpISID=:id) and a.DateTimeUpdated between :dt1 and :dt2

                  UNION ALL 

                  SELECT 'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where   a.LStatus=2 or (a.LStatus=1 and a.EmpSID=:id) and a.LDateTimeUpdated between :dt1 and :dt2
                  
                   UNION ALL 

                  SELECT 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.Status=2 or (a.Status=1 and a.EmpISID=:id) and a.DateTimeUpdate between :dt1 and :dt2      order by dtp desc limit 20 
                

                  ";
                  }
                  //Manager
                  else if($_SESSION['UserType']==2){
                           
                        $sql="SELECT a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and a.OBStatus<>1  and a.OBStatus<>7 and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and a.Status<>1 and a.Status<>7 and a.DateTimeUpdated between :dt1 and :dt2

                  UNION ALL 

                  SELECT a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id and a.LStatus<>1 and a.LStatus<>7 and a.LDateTimeUpdated between :dt1 and :dt2
                  
                   UNION ALL 

                  SELECT a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and  a.Status<>1 and a.Status<>7 and a.DateTimeUpdate between :dt1 and :dt2   

                  UNION ALL 

                  SELECT a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat , a.OBISReason as rsn from 
                    obs as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpSID=:id  and a.OBStatus=1 and a.OBUpdated between :dt1 and :dt2   
                    
                  UNION ALL 

                  SELECT a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn  from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where (a.EmpISID=:id ) and a.Status=1  and a.DateTimeUpdated between :dt1 and :dt2   

                  UNION ALL 

                  SELECT a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpISID=:id and  a.Status=1  and a.DateTimeUpdate between :dt1 and :dt2   

                  UNION ALL 

                  SELECT a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat, a.LISReason as rsn from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpSID=:id and a.LStatus=1 and a.LDateTimeUpdated between :dt1 and :dt2    order by dtp desc 
                  
                      ";
                  }
                 
                  else{
//user

                  $sql="SELECT a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.OBUpdated as dtp,c.StatusDesc as stat from 
                    obs as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and d.EmpCompID=:cmpid and a.OBStatus<>1 and a.OBStatus<>7 and a.OBUpdated between :dt1 and :dt2
                    
                  UNION ALL 

                  SELECT a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ',  DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.DateTimeUpdated as dtp,c.StatusDesc as stat from 
                    earlyout as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status<>1 and a.Status<>7 and a.DateTimeUpdated between :dt1 and :dt2

                   UNION ALL 

                  SELECT a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.LDateTimeUpdated as dtp,c.StatusDesc as stat from 
                    hleaves as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.LStatus<>1 and a.LStatus<>7  and a.LDateTimeUpdated between :dt1 and :dt2
                  
                   UNION ALL 

                  SELECT a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.DateTimeUpdate as dtp,c.StatusDesc as stat from 
                    otattendancelog as a 
                    INNER JOIN employees as b ON a.EmpID=b.EmpID 
                    INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                    INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status<>1 and a.Status<>7 and a.DateTimeUpdate between :dt1 and :dt2 order by dtp desc

                  ";

                  }
                  if ($_SESSION['UserType']==1){
                     
                      $statement = $pdo->prepare($sql);
                      $statement->bindParam(':id' , $isid);
                      $statement->bindParam(':dt1' , $dt1);
                      $statement->bindParam(':dt2' , $dt2);
                      $statement->execute();
                  }else  if ($_SESSION['UserType']==2){
                      $statement = $pdo->prepare($sql);
                      $statement->bindParam(':id' , $isid);
                      $statement->bindParam(':dt1' , $dt1);
                      $statement->bindParam(':dt2' , $dt2);
                      $statement->execute();
                  }else{
                      $statement = $pdo->prepare($sql);
                       $statement->bindParam(':id' , $_SESSION['id']);
                      $statement->bindParam(':cmpid' , $_SESSION['CompID']);
                        $statement->bindParam(':dt1' , $dt1);
                      $statement->bindParam(':dt2' , $dt2);
                      $statement->execute();
                  }
                    } catch (Exception $e) {
                        echo 'Caught exception: ',  $e->getMessage(), "\n";
                    }
                  $nrow=$statement->rowCount();
                  
        ?>
