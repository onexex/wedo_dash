<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
   if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
   else{
    if(!isset($_COOKIE["WeDoID"])){
        header ('location: login'); 
    }else{
        if(!isset($_COOKIE["WeDoID"])) {
          session_destroy();
          header ('location: login'); 
        }
        else{
          try{
              include 'w_conn.php';
              $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          }
          catch(PDOException $e){
              die("ERROR: Could not connect. " . $e->getMessage());
            }
            
          $statement = $pdo->prepare("select * from empdetails");
          $statement->execute();    

          while ($row=$statement->fetch()) {
            if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])){
                $_SESSION['id']=$row['EmpID'];
                $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                $statement->bindParam(':un' , $_SESSION['id']);
                $statement->execute(); 
                $count=$statement->rowCount();
                $row=$statement->fetch();
                $hash = $row['EmpPW'];
                $_SESSION['UserType']=$row['EmpRoleID'];
                $cid=$row['EmpCompID'];
                $_SESSION['CompID']=$row['EmpCompID'];
                $_SESSION['EmpISID']=$row['EmpISID'];
                $statement = $pdo->prepare("select * from companies where CompanyID = :pw");
                $statement->bindParam(':pw' , $cid);
                $statement->execute(); 
                $comcount=$statement->rowCount();
                $row=$statement->fetch();
                if ($comcount>0){
                  $_SESSION['CompanyName']=$row['CompanyDesc'];
                  $_SESSION['CompanyLogo']=$row['logopath'];
                  $_SESSION['CompanyColor']=$row['comcolor'];
                }else{
                  $_SESSION['CompanyName']="ADMIN";
                  $_SESSION['CompanyLogo']="";
                  $_SESSION['CompanyColor']="red";
                }
                 $_SESSION['PassHash']=$hash;
            }
            else{}
          }
        }
    }

  }
  
  include 'w_conn.php';
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
  date_default_timezone_set("Asia/Manila");
  $id=$_SESSION['id'];
  $isid=$_SESSION['EmpISID'];  
  $statement = $pdo->prepare("select * from accessrights where EmpID=:id");
  $statement->bindParam(':id' , $id);
  $statement->execute();
  $r = $statement->fetch();
 if ($r['notif']==1)
  	{
    header('location: 404?');	    
  	}
  else{
  }
 
  
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : htmlspecialchars($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (Bootstrap modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/script-notifications.js"></script>

    <style type="text/css">
        /* reason box stays hidden until DisApprove is clicked (JS toggles .rsn<id>) */
        .rsn { display: none; }
        /* Details column carries multi-line HTML — let it wrap inside the table */
        .wd-table td.notif-details { white-space: normal; min-width: 240px; line-height: 1.55; }
        .wd-table td.notif-type { white-space: normal; font-weight: 600; color: var(--text); }
        /* eye / action button keeps its .btn-warning JS hook but reads as a clean icon button */
        .wd-iconbtn.btn-warning { color: var(--text-2); background: var(--surface); }
        .wd-iconbtn.btn-warning:hover { color: var(--brand); background: var(--surface-2); }
        /* themed notification modals */
        .notif-modal .modal-content { border: 0; border-radius: 14px; overflow: hidden; box-shadow: 0 24px 60px rgba(9, 21, 46, .28); }
        .notif-modal .modal-header { background: #f93627; padding: 12px 16px; border: 0; }
        .notif-modal .modal-header .close { color: #fff; opacity: 1; text-shadow: none; font-size: 26px; }
        .notif-modal .modal-body { padding: 20px; color: var(--text); }
        .notif-modal .modal-body h4 { font-family: var(--font-head); font-weight: 700; color: var(--text); margin: 0 0 10px; }
        .notif-modal .modal-body h5 { color: var(--text-2); line-height: 1.6; margin: 8px 0; font-size: 14px; }
        .notif-modal .modal-footer { border-top: 1px solid var(--border); padding: 14px 16px; text-align: left; }
        .notif-modal .modal-footer .wd-btn + .wd-btn { margin-left: 8px; }
        /* pin Approve=green / DisApprove=red past Bootstrap's .btn-danger/.btn-info hover colours */
        .notif-modal .modal-footer .wd-btn--success,
        .notif-modal .modal-footer .wd-btn--success:hover,
        .notif-modal .modal-footer .wd-btn--success:focus { background: var(--ok-text); color: #fff; }
        .notif-modal .modal-footer .wd-btn--danger,
        .notif-modal .modal-footer .wd-btn--danger:hover,
        .notif-modal .modal-footer .wd-btn--danger:focus { background: var(--danger-text); color: #fff; }
    </style>
</head>
<body>
    <?php
        /* status text -> themed pill (guarded; shared with other migrated pages).
           Order matters: "Disapproved..." contains "approve", so test disapprove first. */
        if (!function_exists('wd_status_pill')) {
            function wd_status_pill($desc) {
                $d = strtolower((string) $desc);
                if (strpos($d, 'disapprove') !== false || strpos($d, 'reject') !== false || strpos($d, 'cancel') !== false
                 || strpos($d, 'deny') !== false || strpos($d, 'decline') !== false)   { $c = 'danger'; }
                elseif (strpos($d, 'pending') !== false)                               { $c = 'warn'; }
                elseif (strpos($d, 'approve') !== false || strpos($d, 'released') !== false) { $c = 'ok'; }
                else                                                                   { $c = 'info'; }
                return '<span class="wd-pill wd-pill--' . $c . '">' . htmlspecialchars($desc) . '</span>';
            }
        }
        $wd_active = 'notifications';
        include 'includes/wd-header.php';
    ?>
    <div class="wd-pagehead">
        <div>
            <h1>Notifications</h1>
            <p>Approvals and status updates on your filed applications.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Recent notifications</h3>
            <div class="srchpar" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
                <div class="wd-field" style="margin:0">
                    <label for="fromdp">From</label>
                    <input type="date" class="wd-input" id="fromdp" style="width:auto;padding:7px 10px" value="<?php echo date('Y-m-d'); ?>" placeholder="Begin Date">
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="todp">To</label>
                    <input type="date" class="wd-input" id="todp" style="width:auto;padding:7px 10px" value="<?php echo date('Y-m-d', strtotime(date("Y-m-d") . ' + 1 days')); ?>" placeholder="End Date">
                </div>
                <button class="wd-btn wd-btn--ghost btnrefreshnotif" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i> Refresh</button>
            </div>
        </div>

        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>Notification Type</th>
                        <th>Details</th>
                        <th>Date Time Updated</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="addob">
             
                
                <?php
      
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
                        
                        $dt1= date('Y-m-d', strtotime(date("Y-m-d")  . ' - 5 days'));
                        $dt2= date('Y-m-d', strtotime(date("Y-m-d")  . ' + 1 days'));
                        
                        // for Admin
                  if ($_SESSION['UserType']==1){
                      
                      $sql="SELECT a.OBID as mid, 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') , '<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn, a.OBHRReason as hrsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and a.OBStatus<>1  and a.OBStatus<>7 and a.OBUpdated
                    
                        UNION ALL 
    
                        SELECT a.SID as mid,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y') , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn,a.HR_remark as hrsn from 
                        earlyout as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and a.Status<>1 and a.Status<>7 and a.DateTimeInputed

                        UNION ALL 

                        SELECT a.LeaveID as mid,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn,a.LHRReason as hrsn from 
                        hleaves as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id and a.LStatus<>1 and a.LStatus<>7 and a.LDateTimeUpdated and a.LStatus<>9
                  
                        UNION ALL 

                        SELECT a.OTLOGID as mid, 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn,a.HRReason as  hrsn from 
                        otattendancelog as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and  a.Status<>1 and a.Status<>7 and a.DateTimeUpdate

                        UNION ALL 
                            SELECT a.OBID as mid, 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn, a.OBHRReason as hrsn from 
                        obs as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.OBStatus=c.StatusID  where  a.OBStatus=2 or (a.OBStatus=1 and a.EmpSID=:id) and a.OBUpdated
                    
                        UNION ALL 

                        SELECT a.SID as mid,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn,a.HR_remark as hrsn from 
                        earlyout as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.Status=c.StatusID where   a.Status=2 or (a.Status=1 and a.EmpISID=:id) and a.DateTimeUpdated

                        UNION ALL 

                        SELECT a.LeaveID as mid,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn,a.LHRReason as hrsn from 
                        hleaves as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.LStatus=c.StatusID where   a.LStatus=2 or (a.LStatus=1 and a.EmpSID=:id) and a.LDateTimeUpdated
                  
                        UNION ALL 

                        SELECT a.OTLOGID as mid, 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn,a.HRReason as  hrsn from 
                        otattendancelog as a 
                        INNER JOIN employees as b ON a.EmpID=b.EmpID 
                        INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                        INNER JOIN status as c ON a.Status=c.StatusID where a.Status=2 or (a.Status=1 and a.EmpISID=:id) and a.DateTimeUpdate order by dtp desc limit 20 
                

                    ";
                  }
                  //Manager
                  else if($_SESSION['UserType']==2){
                              
                        $sql="SELECT a.OBID as mid, a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat, a.OBISReason as rsn, a.OBHRReason as hrsn from 
                          obs as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and a.OBStatus=1
                          
                          UNION ALL 

                          SELECT a.SID as mid,a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn,a.HR_remark as hrsn from 
                          earlyout as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and a.Status=1

                          UNION ALL 

                          SELECT a.LeaveID as mid,a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn,a.LHRReason as hrsn from 
                          hleaves as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id and a.LStatus=1
                      
                          UNION ALL 

                          SELECT a.OTLOGID as mid, a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn,a.HRReason as  hrsn from 
                          otattendancelog as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and  a.Status=1

                          UNION ALL 

                          SELECT a.OBID as mid, a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.EmpID as IfIS, a.OBUpdated as dtp,c.StatusDesc as stat , a.OBISReason as rsn, a.OBHRReason as hrsn from 
                          obs as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpSID=:id  and a.OBStatus=1 and a.OBUpdated between :dt1 and :dt2   
                          
                          UNION ALL 

                          SELECT a.SID as mid,a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ', DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn,a.HR_remark as hrsn  from 
                          earlyout as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where (a.EmpISID=:id ) and a.Status=1  and a.DateTimeUpdated between :dt1 and :dt2   

                          UNION ALL 

                          SELECT a.OTLOGID as mid,  a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.EmpID as IfIS, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn,a.HRReason as  hrsn from 
                          otattendancelog as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where a.EmpISID=:id and  a.Status=1  and a.DateTimeUpdate between :dt1 and :dt2   

                          UNION ALL 

                          SELECT a.LeaveID as mid,a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.EmpID as IfIS, a.LDateTimeUpdated	 as dtp,c.StatusDesc as stat, a.LISReason as rsn,a.LHRReason as hrsn from 
                          hleaves as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpSID=:id and a.LStatus=1 and a.LDateTimeUpdated between :dt1 and :dt2 order by dtp desc 
                      
                        ";
                      }
                 
                  else{
                      
                        $sql="SELECT a.OBID as mid, a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.OBUpdated as dtp,c.StatusDesc as stat,a.OBISReason as rsn, a.OBHRReason as hrsn from 
                          obs as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and d.EmpCompID=:cmpid and a.OBStatus=1
                          
                          UNION ALL 

                          SELECT a.SID as mid,a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ',  DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn,a.HR_remark as hrsn from 
                          earlyout as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status=1

                          UNION ALL 

                          SELECT a.LeaveID as mid,a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn,a.LHRReason as hrsn from 
                          hleaves as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.LStatus=1
                        
                          UNION ALL 

                          SELECT a.OTLOGID as mid, a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn,a.HRReason as  hrsn from 
                          otattendancelog as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status=1

                          UNION ALL 

                          SELECT a.OBID as mid, a.EmpID,'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ', DATE_FORMAT(a.OBDateFrom, '%M %d, %Y'),' To : ', DATE_FORMAT(a.OBDateTo, '%M %d, %Y') ,'<br><br> Time From : ' , TIME_FORMAT(a.OBTimeFrom , '%h:%i:%s %p'), ' To : ' , TIME_FORMAT(a.OBTimeTo , '%h:%i:%s %p') , '<br><br> Cash Advance Amount : ' , a.OBCAAmt , ' Reason : ', a.OBCAPurpose , '<br><br> Remarks : ') as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.OBUpdated as dtp,c.StatusDesc as stat,a.OBISReason as rsn, a.OBHRReason as hrsn from 
                          obs as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and d.EmpCompID=:cmpid and a.OBStatus<>4 and a.OBStatus<>7

                          UNION ALL

                          SELECT a.SID as mid,a.EmpID,'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ',  DATE_FORMAT(a.FDate, '%M %d, %Y')  , ' Time Applied : ', TIME_FORMAT(a.DateTimeInputed , '%h:%i:%s %p')) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.DateTimeUpdated as dtp,c.StatusDesc as stat,a.IS_remark as rsn,a.HR_remark as hrsn from 
                          earlyout as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status<>1 and a.DateTimeUpdated between :dt1 and :dt2

                          UNION ALL 

                          SELECT a.LeaveID as mid,a.EmpID,'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.LDateTimeUpdated as dtp,c.StatusDesc as stat,a.LISReason as rsn,a.LHRReason as hrsn from 
                          hleaves as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.LStatus<>1 and a.LDateTimeUpdated between :dt1 and :dt2

                          UNION ALL 

                          SELECT a.OTLOGID as mid, a.EmpID,'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.DateTimeUpdate as dtp,c.StatusDesc as stat,a.ISReason as rsn,a.HRReason as  hrsn from 
                          otattendancelog as a 
                          INNER JOIN employees as b ON a.EmpID=b.EmpID 
                          INNER JOIN empdetails as d ON b.EmpID=d.EmpID 
                          INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id  and d.EmpCompID=:cmpid and a.Status<>1 and a.DateTimeUpdate between :dt1 and :dt2

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
                  while ($row = $statement->fetch()){
                        
                    ?>
                     <tr>
                    <td class="notif-type"><?php echo $row['ntype']; ?></td>
                    <td class="notif-details"><?php echo $row['dtfromto']; ?></td>
                    <td><?php echo date("F d, Y h:i:s A", strtotime($row['dtp']));   ?></td>
                    <td><?php echo wd_status_pill($row['stat']); ?></td>
                    <td><button class="wd-iconbtn btn-warning" data-toggle="modal" data-target="#myModal<?php echo  $row['id']; ?>" title="View details"><i class="fa-solid fa-eye" aria-hidden="true"></i></button></td>
                   


            
                        <!-- Modal -->
                  <div class="modal fade notif-modal" id="myModal<?php echo  $row['id']; ?>" role="dialog">
                    <div class="modal-dialog">

                      <!-- Modal content-->
                      <div class="modal-content" >
                        <div class="modal-header">
                          <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                          <h4><?php  echo $row['ntype']; ?></h4>
                         <h5><?php  echo $row['descript']; ?></h5>
                         <h5> <?php  echo $row['dtfromto']; ?></h5>
                         <h5><?php if ($row['st']=="EO"){
                                $statementeo = $pdo->prepare("select * from earlyout inner join attendancelog on earlyout.LogID=attendancelog.LogID where SID=:id");
                                $statementeo->bindParam(':id' , $row['mid']);
                                $statementeo->execute();
                                $roweo = $statementeo->fetch();
                             echo "Time Login : ". date("h:i:s A", strtotime($roweo['TimeIn']));
                         }
                         else if($row['st']=="HL"){
                            $statementleave = $pdo->prepare("SELECT TRUNCATE (a.LDuration, 1) as duration, a.EmpID,a.LeaveID, a.LStatus,a.LType,a.LHRReason,b.StatusID,b.StatusDesc,d.LeaveID,d.LeaveDesc as LDescription from hleaves as a 
                        
                                
                                 INNER JOIN status as b on a.LStatus=b.StatusID 
                                              -- INNER JOIN leaves_validation c ON c.sid=a.LType 9-11
                                              INNER JOIN leaves as d on d.LeaveID=a.LType 
                                              where a.LeaveID=:id");
                            $statementleave->bindParam(':id' , $row['mid']);
                            $statementleave->execute();
                            $rowleave = $statementleave->fetch();
                            echo "Leave Type : " . $rowleave['LDescription'];
                            if ($rowleave['LStatus']==6){

                              echo "<br><br> Reason : " . $rowleave['LHRReason'];
                            }
                            
                           if ($rowleave['duration']==0.5){
                                echo "<br><br>";
                                echo "Duration : Halfday";
                            }

                         }
                         ?></h5>
                         <h5> Datetime Updated : <?php echo date("F d, Y h:i:s A", strtotime($row['dtp']));   ?></h5>
                         
                          <?php 
                            if ($row['rsn']!=""){
                                echo "<br>Reason : " . $row['rsn'] . " " . $row['hrsn'];
                            }
                          ?>
                          <br>
                          <div class="form-group rsn rsn<?php echo  $row['id']; ?>">
                            <label style="font-weight:600;color:var(--text-2)">Reason :</label>
                            <textarea name="rs<?php echo  $row['id']; ?>" id="rs<?php echo  $row['id']; ?>" class="wd-textarea"></textarea>
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
                                <button type="button" class="wd-btn wd-btn--success btn-danger" id="<?php echo  $row['id']; ?>"><i class="fa-solid fa-check"></i> Approve</button>
                                <button type="button" class="wd-btn wd-btn--danger btn-info" id="<?php echo  $row['id']; ?>"><i class="fa-solid fa-xmark"></i> DisApprove</button>
                                    <br>
                                <label style="display:block;margin-top:10px"><?php echo wd_status_pill($row['stat']); ?></label>
                            <?php
                                }
                                 }
                              ?>
                              
                              <?php
                          }else{
                               if(($_SESSION['UserType']==1) && ($rowleave['EmpID']=="WeDoinc-012")){
                            
                                }else{
                                  ?>
                                        <button type="button" class="wd-btn wd-btn--success btn-danger" id="<?php echo  $row['id']; ?>"><i class="fa-solid fa-check"></i> Approve</button>
                                        <button type="button" class="wd-btn wd-btn--danger btn-info" id="<?php echo  $row['id']; ?>"><i class="fa-solid fa-xmark"></i> DisApprove</button>
                                            <br>
                                        <label style="display:block;margin-top:10px"><?php echo wd_status_pill($row['stat']); ?></label>
                                  <?php
                                }   
                                
                          }
                                
                                ?>
                         
                        
                   
                        </div>
                      </div>
                      
                    </div>
                  </div>
       </tr>
                    <?php
                  }
                ?>

            
   
                </tbody>
            </table>
        </div><!-- /.wd-tablewrap -->
    </section>

    <!-- Success / warning feedback modal (JS targets #modalWarning .alert) -->
    <div class="modal fade notif-modal" id="modalWarning">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="alert alert-success" style="margin:0;border:0;border-radius:10px"></div>
          </div>
        </div>
      </div>
    </div>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
