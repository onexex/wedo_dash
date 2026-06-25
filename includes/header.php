<?php
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){


?>

 <!-- header -->
 <div class="employee-b-info" style="border: 1px solid <?php echo $_SESSION['CompanyColor'];  ?>">
    
    <div class="row">
      <div class="col-lg-4 col-md-12">
        <?php
          include 'w_conn.php';
            try{
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }

            $statement = $pdo->prepare("select * from empprofiles where EmpID='$_SESSION[id]'");
      
            $statement->execute(); 

            $count=$statement->rowCount();
            $row=$statement->fetch();
              $gender=$row['EmpGender'];
            if ($count<1){
                 $path="assets/images/profiles/default.png";
                $url = "assets/images/profiles/default.png'";
            }else{
                 $path="assets/images/profiles/default.png";
              try{
                if ($row['EmpPPath']==""){
                    $path="assets/images/profiles/default.png";
                    $url= "'assets/images/profiles/default.png'";

                }else{
                    $path=$row['EmpPPath'];
                    $url= $row['EmpPPath'];
                }
              }
              catch(Exception $e) {
                    $path="assets/images/profiles/default.png";
                    $url = "assets/images/profiles/default.png";
              }
            }
            
        ?>
      
        
      </div>
      
      <div class="col-lg-12 side-drp-menu">

         <div class="emp-image" style="<?php 
              if(file_exists($url)){
    //Directory does not exist, so lets create it.
          echo "background: url('" . $url . "');";
        }
        else{
           if ($row['EmpGender']=="Male"){
             echo 'background: url(assets/images/profiles/man_d.jpg)';
           }else{
             echo 'background: url(assets/images/profiles/woman_d.jpg)';
           }
         
        } ?>"></div>
        <?php
        try{
             $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }

            $statement = $pdo->prepare("select * from employees inner join positions on employees.posid=positions.psid where EmpID='$_SESSION[id]'");
      
            $statement->execute(); 
            $isid=$_SESSION['id'];
            $count=$statement->rowCount();
            $row=$statement->fetch();
                   if ($_SESSION['UserType']==2){
                    
                    $sql="SELECT 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ',a.OBDateFrom,' To : ',a.OBDateTo) as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.OBUpdated as dtp,c.StatusDesc as stat from 
                  obs as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpSID=:id and a.OBStatus<>1 and a.OBStatus<>3
                    
                  UNION ALL 

                  SELECT 'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ',a.FDate) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.DateTimeUpdated as dtp,c.StatusDesc as stat from 
                  earlyout as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.Status=c.StatusID where a.EmpISID=:id and a.Status<>1 and a.Status<>3

                   UNION ALL 

                  SELECT 'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.LDateTimeUpdated as dtp,c.StatusDesc as stat from 
                  hleaves as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpSID=:id and a.LStatus<>1  and a.LStatus<>3
                  
                   UNION ALL 

                  SELECT 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.DateTimeUpdate as dtp,c.StatusDesc as stat from 
                  otattendancelog as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.Status=c.StatusID where a.EmpISID=:id and  a.Status<>1 and  a.Status<>3 order by dtp desc

                  ";

                  }else{
                        $sql="SELECT 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ',a.OBDateFrom,' To : ',a.OBDateTo) as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.OBUpdated as dtp,c.StatusDesc as stat from 
                  obs as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.OBStatus=c.StatusID  where a.EmpID=:id  and a.OBStatus<>1
                    
                  UNION ALL 

                  SELECT 'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ',a.FDate) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.DateTimeUpdated as dtp,c.StatusDesc as stat from 
                  earlyout as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and a.Status<>1
                   UNION ALL 

                  SELECT 'HL' as st, CONCAT('HL-', a.LeaveID) as id,CONCAT('Leave Start : ',a.LStart,' Leave End : ',a.LEnd) as dtfromto, CONCAT('Purpose : ',a.LPurpose) as descript,a.LStatus as BSTat, CONCAT('Leave Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.LFDate as TimeFiled, a.LDateTimeUpdated as dtp,c.StatusDesc as stat from 
                  hleaves as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.LStatus=c.StatusID where a.EmpID=:id and a.LStatus<>1
                  
                   UNION ALL 

                  SELECT 'OT' as st, CONCAT('OT-', a.OTLOGID) as id,CONCAT('Overtime Start : ',a.TimeIn,' End : ',a.TimeOut) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('OverTime Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateFiling as TimeFiled, a.DateTimeUpdate as dtp,c.StatusDesc as stat from 
                  otattendancelog as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.Status=c.StatusID where a.EmpID=:id and  a.Status<>1   order by dtp desc

                  ";
                
                  }

                  $st = $pdo->prepare($sql);
                  $st->bindParam(':id' , $isid);
                  $st->execute();
                  $nrow=$st->rowCount();

        ?>
         <div><h6 style="padding-bottom: 8px;
    border-bottom: 2px solid <?php echo  $_SESSION['CompanyColor']; ?>;"><?php if ($_SESSION['UserType']==1){ echo "Super User"; } else { echo $row['PositionDesc']; } ?></h6></div>
          <a  data-toggle="modal" data-target="#changepass" title="Change Password"><i  class="fa fa-gear"></i> Change Password</a>
         <a href="login.php?logout" class="btn " title="Sign Out"><i class="fa fa-sign-out" aria-hidden="true"></i> Sign Out</a>
      </div>
    </div>
  </div>
  <?php
   try{
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }

            $statement = $pdo->prepare("select * from employees where EmpID='$_SESSION[id]'");
      
            $statement->execute(); 

            $count=$statement->rowCount();
            $rownm=$statement->fetch();
  ?>
<!-- navbar -->
  <div class="w-navbar" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>">
    <div class="lg-sm-dv">
       <img src="<?php if ($_SESSION['CompanyLogo']!=""){ 
                            echo $_SESSION['CompanyLogo'];
                          } 
                        else{
                          echo "assets/images/logos/logo-2.png";
                        }

        ?>">
    </div>
    <div class="lg-dv">
        <img src="<?php echo $_SESSION['CompanyLogo']; ?>">
        <h4><a href="index"><?php echo $rownm['EmpLN'] . ", " . $rownm['EmpFN']; ?> </a></h4>
    </div>
    <div class="right-menu-dv">
      <div class="menu-icons">
          <!--//notification bell-->
          <?php
           include 'query/Query-Notifications.php';
          ?>
         <a href="notifications.php" style="text-decoration:none;" data-toggle="tooltip" data-placement="left" title="You have <?php echo $nrow; ?> Notification(s)"><i class="fa fa-bell" id="faid-bell" aria-hidden="true"></i >
         <?php
        
         if ($nrow==0){
             
         }else{
          
         ?>
         <i style="font-size: 10px;float: revert;position: relative;color: #fff;background-color: #000;padding: 5px;border-radius: 10px;"><?php echo $nrow; ?></i></a> 
        <?php
        
         }
         ?>
        <a href="#" title="MENU"  data-toggle="tooltip" data-placement="left"class="sign-out" ><img src="<?php
        if(file_exists($path)){
    //Directory does not exist, so lets create it.
          
          echo $path;
        }
        else{
           if ($gender=="Male"){
                echo 'assets/images/profiles/man_d.jpg';
           }else{
               echo 'assets/images/profiles/woman_d.jpg';
           }
        
        } ?>"></a>

      </div>
        
    </div>
  </div>
  <div class="dv-nav"></div>

  <!-- sidebar  -->
    <div class="row sd-fxd">
       <div class="col-lg-3 col-md-12 s-menu">
          <!-- navbar toggle -->
   
              <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#sw-example-navbar-collapse-3" data-label-expanded="Close" aria-expanded="false">
              <span class="glyphicon glyphicon-align-justify"></span>
              </button>

                  
          <!-- navbar toggle end -->
           <div class="w-sidebarmenu">
            <ul>
              <li><a href="index" >HOME</a></li>
              <!-- <li><a href="dailyactivtyreport">DAR</a></li> -->
              <li class="toggle-header"><a href="#">MODULES <i class="fa fa-angle-down "></i></a></li>
              <ul>
                <?php
                   try{
                  $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                     }
                  catch(PDOException $e)
                     {
                  die("ERROR: Could not connect. " . $e->getMessage());
                     }



                  $statement = $pdo->prepare("select * from accessrights where EmpID = :id");
                  $statement->bindParam(':id' , $_SESSION['id']);
                  $statement->execute(); 
                  $row=$statement->fetch();

                ?>

                <?php if($row['e201']==2) { ?>
           <!--        <li class="li-child"><i style="outline: none;" class="fa fa-folder" ></i><a href="#">Electronic 201 File</a></li>
            --><li class="li-child"><a href="e201"><i style="outline: none;" class="fa fa-folder" href="#" title="Details" role="button" data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="*View User Profile<br/>*View Employment Status, Details, History<br/>*Change your Dashboard Password<br/>*Request for Certificate of Employment"></i>Electronic 201 File</a></li>
                <?php } ?>
            <?php if($row['alas']==2) { ?>      <li class="li-child"><a href="alas"><i class="fa fa-calendar-check-o"></i>Automated Leave Application</a></li><?php } ?>
            <?php if($row['ob']==2) { ?>  <li class="li-child"><a href="ob" ><i class="fa fa-briefcase"></i>Official Business Trip Tracker</a></li> <?php } ?>
            <?php if($row['sob']==2) { ?>    <li class="li-child"><a href="SendToOB" ><i class="fa fa-plane"></i>Send to OBT Filing</a></li><?php } ?> 
            <?php if($row['ot']==2) { ?>    <li class="li-child"><a href="otfilling" ><i class="fa fa-calendar-plus-o"></i>Overtime Filing</a></li> <?php } ?>
            <!--<?php if($row['otob']==2) { ?>   <li class="li-child"><a href="otob"><i class="fa fa-clock-o"></i>Overtime-Official Business</a></li><?php } ?>-->
             <?php if($row['eo']==2) { ?>  <li class="li-child"><a href="earlyout"><i class="fa fa-calendar-minus-o"></i>Early Out Application</a></li> <?php } ?>    
             
              <!--<?php if($row['coe']==2) { ?>  <li class="li-child"><a href="coe"><i class="fa fa-file"></i>My Documents (COE) </a></li><?php } ?>-->
              <!--<?php if($row['payslipt']==2) { ?>  <li class="li-child"><a href="payslip"><i class="fa fa-file"></i>My Documents (Payslip)</a></li><?php } ?>-->
              
              <!--
              <?php if($row['nwblogs']==2) { ?>  <li class="li-child"><a href="newblog" target="_blank"><i class="fa fa-clipboard"></i>My Access (Blogs)</a></li> <?php } ?>
              -->
              
              <?php if($row['payroll']==2) { ?>      <li class="li-child"><a href="payroll"><i class="fa fa-money"></i>Payroll Management System</a></li><?php } ?>
              <?php if($row['debitadvisesettings']==2) { ?><li class="li-child"><a href="maintenance?debitsetting" ><i class="fa fa-file"></i>PMS-Debit Advise (Settings)</a></li><?php } ?>
   
              <?php if($row['debitadvise']==2) { ?>  <li class="li-child"><a href="debitadvise"><i class="fa fa-file"></i>PMS-Debit Advise (Letter and Attachment)</a></li><?php } ?>
              <?php if($row['checkregister']==2) { ?>  <li class="li-child"><a href="checkregister"><i class="fa fa-file-o"></i>Check Register</a></li><?php } ?>
               <?php if($row['memo']==2) { ?>  <li class="li-child"><a href="memo"><i class="fa fa-sticky-note"></i>Memorandum Generator</a></li><?php } ?>
              
              <!--     <li class="li-child"><a href="#" data-toggle="modal" data-target="#myModal"><i class="fa fa-money"></i>Petty Cash Fund</a></li> -->
              <!-- <li class="li-child"><a href="#"><i class="fa fa-money"></i>Daily Activity Report Logger</a></li> -->
               
                
                 
                 
              </ul>
              <?php 
                if ($row['alasv']==2 || $row['lilov']==2 || $row['cddv']==2 || $row['darv']==2 || $row['eov']==2 || $row['emv']==2 || $row['alphav']==2){
              ?>
                   <li class="toggle-header "><a href="#" >REPORTS <i class="fa fa-angle-down "></i></a></li>
              <?php
                }
               ?>
             
              <ul class="report-i">
                <?php if($row['alasv']==2) { ?> <li class="li-child"><a href="alasviewer"><i style="outline: none;" class="fa fa-bar-chart" ></i>ALAS Viewer</a></li><?php } ?>
                <?php if($row['lilov']==2) { ?><li class="li-child"><a href="liloviewer"><i style="outline: none;" class="fa fa-bar-chart" ></i>Attendance Viewer</a></li> <?php } ?>
                
                
                
                <!--
                <?php if($row['cddv']==2) { ?><li class="li-child"><a href="ComDocData"><i style="outline: none;" class="fa fa-bar-chart" ></i>Compliance Document Data</a></li><?php  } ?>
                -->
               
                <?php if($row['darv']==2) { ?> <li class="li-child"><a href="dar"><i style="outline: none;" class="fa fa-bar-chart" ></i>Daily Activity Viewer</a></li><?php  } ?>
                
                 <!-- for 13 month start -->
                <?php if($row['access_13']==2) { ?> <li class="li-child"><a href="generalreport"><i style="outline: none;" class="fa fa-bar-chart" ></i>YTD 13<sup>th</sup> Month</a></li><?php  } ?>
                <?php if($row['access_13_attachement']==2) { ?> <li class="li-child"><a href="attachement_13"><i style="outline: none;" class="fa fa-bar-chart" ></i>13<sup>th</sup> Month Attachment</a></li><?php  } ?>
                <!-- for 13 month end -->
 
                
                <?php if($row['eov']==2) { ?><li class="li-child"><a href="earlyoutviewer"><i style="outline: none;" class="fa fa-bar-chart"></i>Early Out Viewer</a></li><?php } ?>
                
                <!--
                <?php if($row['emv']==2) { ?><li class="li-child"><a href="EmpInfo"><i style="outline: none;" class="fa fa-bar-chart" ></i>Employment Information</a></li><?php  } ?>
                -->
                
                <!--
                <?php if($row['alphav']==2) { ?><li class="li-child"><a href="reportse201"><i style="outline: none;" class="fa fa-bar-chart" ></i>Employee Alpha List Generator</a></li><?php } ?>
                -->
                 <?php if($row['coe']==2) { ?>  <li class="li-child"><a href="coe"><i class="fa fa-chart"></i>My Documents (COE) </a></li><?php } ?>
              <?php if($row['payslipt']==2) { ?>  <li class="li-child"><a href="payslip"><i class="fa fa-chart"></i>My Documents (Payslip)</a></li><?php } ?>
              
                <!--     <li class="li-child"><i style="outline: none;" class="fa fa-bar-chart" href="#" title="Details" role="button" data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="*View User Profile<br/>*View Employment Status, Details, History<br/>*Change your Dashboard Password<br/>*Request for Certificate of Employment"></i><a href="#">Loan Logger</a></li> -->
                <?php if($row['obv']==2) { ?> <li class="li-child"><a href="obviewer"><i style="outline: none;" class="fa fa-bar-chart"></i>Official Business Viewer</a></li><?php  } ?>
                <?php if($row['atv']==2) { ?><li class="li-child"><a href="overtimeviewer"><i style="outline: none;" class="fa fa-bar-chart" ></i>Overtime Viewer</a></li><?php } ?>
                
                <!--
                <?php if($row['piv']==2) { ?><li class="li-child"><a href="PersonalInfo"><i style="outline: none;" class="fa fa-bar-chart" ></i>Personal Information</a></li><?php  } ?>
                -->
                
                <?php if($row['fdetls']==2) { ?><li class="li-child"><a href="FamilyDetails"><i style="outline: none;" class="fa fa-bar-chart" ></i>Family Details</a></li><?php  } ?>
                <?php if($row['lcreaditview']==2) { ?><li class="li-child"><a href="leavecredit"><i style="outline: none;" class="fa fa-bar-chart" ></i>Leave Credit Viewer</a></li><?php  } ?>
                    
                  <!--   <li class="li-child"><i style="outline: none;" class="fa fa-bar-chart" href="#" title="Details" role="button" data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="*View User Profile<br/>*View Employment Status, Details, History<br/>*Change your Dashboard Password<br/>*Request for Certificate of Employment"></i><a href="#">Payroll Management System</a></li> -->
              </ul>
              <?php 

                if ($row['arights']==2 || $row['eemployee']==2 || $row['e201d']==2 || $row['agncy']==2 || $row['comp']==2 || $row['dep']==2 || $row['pos']==2 || $row['jl']==2 || $row['hmo']==2 || $row['est']==2 || $row['rel']==2 || $row['classf']==2 || $row['wt']==2 || $row['tlv']==2 || $row['lval']==2 || $row['ur']==2 || $row['otfs']==2){
              ?>
                  <li class="toggle-header" ><a href="#">MANAGEMENT <i class="fa fa-angle-down "></i></a></li>
              <?php 
                }
              ?>
          
              <ul class="report-i">
              
                <?php if($row['arights']==2) { ?><li class="li-child hdr-tgl"><a href="accessrights.php"><i style="outline: none;" class="fa fa-lock" href="#" title="Details" role="button" data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="*View User Profile<br/>*View Employment Status, Details, History<br/>*Change your Dashboard Password<br/>*Request for Certificate of Employment"></i>Access Rights</a></li><?php } ?>
                <?php if($row['ams']==2) { ?><li class="li-child"><a href="ams"><i class="fa fa-archive"></i>Archived Management System</a></li><?php } ?>   
                <?php if($row['payeereg']==2) { ?><li class="li-child"><a href="payeereg"><i class="fa fa-archive"></i>Payee Management System</a></li><?php } ?> 
                <?php if($row['bookletreg']==2) { ?><li class="li-child"><a href="bookletregistry"><i class="fa fa-archive"></i>Booklet Management System</a></li><?php } ?> 
                <?php if($row['eemployee']==2) { ?><li class="li-child hdr-tgl"><a href="newemployee"><i style="outline: none;" class="fa fa-user-plus" href="#" title="Details" role="button" data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="*View User Profile<br/>*View Employment Status, Details, History<br/>*Change your Dashboard Password<br/>*Request for Certificate of Employment"></i>Enroll Employee</a></li><?php } ?>
                <?php if($row['e201d']==2) { ?><li class="li-child hdr-tgl"><a href="e201files"><i style="outline: none;" class="fa fa-file-pdf-o" href="#" title="Details" role="button" data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="*View User Profile<br/>*View Employment Status, Details, History<br/>*Change your Dashboard Password<br/>*Request for Certificate of Employment"></i>Electronic 201 Document</a></li><?php } ?>
       
                  
                <?php if($row['EF']==2) { ?>  <li class="li-child"><a href="scheduler"><i class="fa fa-calendar-check-o"></i>Employee Scheduler</a></li><?php } ?>
                <?php if($row['schedv']==2) { ?> <li class="li-child"><a href="schedviewer"><i style="outline: none;" class="fa fa-bar-chart" ></i>Schedule Viewer</a></li><?php } ?>
                  
                  
                  <li class="toggle-header-main"><a href="#"><i class="fa fa-wrench"></i>Maintenance <i class="fa fa-angle-down "></i></a></li>
                  <ul class="report-i">
               
                   <?php if($row['agncy']==2) { ?><li class="li-child"><a href="maintenance?agency" ><i class="fa fa-plus-square-o"></i>Agencies</a></li><?php } ?>
                  <?php if($row['comp']==2) { ?><li class="li-child"><a href="maintenance?company" ><i class="fa fa-plus-square-o"></i>Companies</a></li><?php } ?>
                  <?php if($row['dep']==2) { ?><li class="li-child"><a href="maintenance?department" ><i class="fa fa-plus-square-o"></i>Departments</a></li><?php } ?>
                  
                  <?php if($row['pos']==2) { ?><li class="li-child"><a href="maintenance?position"><i class="fa fa-plus-square-o"></i>Positions</a></li><?php } ?>
                  <?php if($row['jl']==2) { ?><li class="li-child"><a href="maintenance?joblevel"><i class="fa fa-plus-square-o"></i>Job Levels</a></li><?php  } ?>
                  <?php if($row['hmo']==2) { ?><li class="li-child"><a href="maintenance?hmo"><i class="fa fa-plus-square-o"></i>HMOs</a></li><?php } ?>
                  <?php if($row['est']==2) { ?><li class="li-child"><a href="maintenance?employeestatus"><i class="fa fa-plus-square-o"></i>Employee Status</a></li><?php } ?>
                  <?php if($row['rel']==2) { ?> <li class="li-child"><a href="maintenance?relationship"><i class="fa fa-plus-square-o"></i>Relationships</a></li><?php  } ?>
                  <?php if($row['classf']==2) { ?><li class="li-child"><a href="maintenance?classification"><i class="fa fa-plus-square-o"></i>Classifications</a></li><?php } ?>

                  <?php if($row['wt']==2) { ?><li class="li-child"><a href="maintenance?worktime"><i class="fa fa-plus-square-o"></i>Work Shifts</a></li><?php } ?>
                 <!--  <?php if($row['wd']==2) { ?><li class="li-child"><a href="maintenance?workdays"><i class="fa fa-plus-square-o"></i>Work Days</a></li><?php } ?> -->
                   <?php if($row['tlv']==2) { ?><li class="li-child"><a href="maintenance?typesofleave"><i class="fa fa-plus-square-o"></i>Types of Leaves</a></li><?php } ?>
                  <?php if($row['lval']==2) { ?><li class="li-child"><a href="maintenance?leavevalidation"><i class="fa fa-plus-square-o"></i>Leave Validation</a></li><?php } ?>
                  <?php if($row['ur']==2) { ?><li class="li-child"><a href="maintenance?userrole"><i class="fa fa-plus-square-o"></i>User Roles</a></li><?php } ?>
                  <?php if($row['otfs']==2) { ?><li class="li-child"><a href="maintenance?otfsm"><i class="fa fa-plus-square-o"></i>OT Filing System Maintenance</a></li><?php } ?>
                  <?php if($row['hldy']==2) { ?><li class="li-child"><a href="maintenance?holiday"><i class="fa fa-plus-square-o"></i>Holiday Logger</a></li><?php } ?>
                  <?php if($row['gprdv']==2) { ?><li class="li-child"><a href="maintenance?lilovalidation"><i class="fa fa-plus-square-o"></i>Lilo Validation</a></li><?php } ?>
                  <?php if($row['obval']==2) { ?><li class="li-child"><a href="maintenance?obvalidation"><i class="fa fa-plus-square-o"></i>OB Validation</a></li><?php } ?>
                  <?php if($row['eoval']==2) { ?><li class="li-child"><a href="maintenance?eovalidation"><i class="fa fa-plus-square-o"></i>EO Validation</a></li><?php } ?>
                   <?php if($row['SPPContrib']==2) { ?>
                      <li class="li-child"><a href="maintenance?sss"><i class="fa fa-plus-square-o"></i>SSS Contribution</a></li>
                      <li class="li-child"><a href="maintenance?pagibig"><i class="fa fa-plus-square-o"></i>Pagibig Contribution</a></li>
                      <li class="li-child"><a href="maintenance?philhealth"><i class="fa fa-plus-square-o"></i>PhilHealth Contribution</a></li>
                         <li class="li-child"><a href="maintenance?silloan"><i class="fa fa-plus-square-o"></i>SIL LOAN</a></li>
                  <?php } ?>
                   <li class="li-child"><a href="maintenance?parentalfamilydetails"><i class="fa fa-plus-square-o"></i>Family Details for Parental</a></li>
                  <!-- <li class="li-child"><a href="#" data-toggle="modal" data-target="#myModal"><i class="fa fa-calendar"></i>Holiday Logger</a></li> -->
                  </ul>
              </ul>
          <!-- s -->
             <?php if($row['gcorner']==2) { ?> <li><a href="corner"><?php echo $_SESSION['CompanyName']; ?> CORNER
              <?php if ($_SESSION['UserType']==1){
                ?>
  <i title="Adding New Announcement - Click here and Click Add New Announcement then Save- The Employee view announcement below." data-toggle="tooltip" data-placement="bottom"  class="fa fa-question-circle" aria-hidden="true"></i>
                <?php
              }?>
            </a></li><?php } ?>
             <?php if($_SESSION['UserType']==5) { ?> <li><a href="Reset">RESET DATA</a></li><?php } ?>

            </ul>
              <!-- annnouncement view latest 2 -->
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
                        $id=$_SESSION['id'];
                        $statement = $pdo->prepare("select * from announcements inner join employees on announcements.EmpID=employees.EmpID  order by ADate desc limit 5");
                        $statement->execute();
                        if ($statement->rowCount()>=1){

            ?>
          <div class="gm-Corner" style="padding: 0px 10px;overflow-y: scroll;height: 200px;">
              <?php while ($rowann=$statement->fetch()) {
              ?>
              <div class="gmbull" style="padding-bottom: 10px;border-bottom: 1px solid;">
              <div>
                
              <h3 style="margin-bottom:3px;">Announcement : </h3>
              <h6 style="margin-top:0px;"><?php if( $rowann['EmpFN']=="admin") {
                                        echo "WeDo Family";
                                    }else{
                                      echo $rowann['EmpFN'] . " " . $rowann['EmpLN'];
                                    }
                                      ?></h6>
              <h5><?php
                if (date('Y-m-d')==date("Y-m-d", strtotime($rowann['ADate']))){
                     echo "Today, " .  date("h:i:s A", strtotime($rowann['ADate']));  
                }else{
                    echo date("F j, Y h:i:s A", strtotime($rowann['ADate']));  
                }
              ?></h5>
              <p > <?php echo $rowann['ADesc']; ?> </p>

              </div>
            </div>
              <?php 
              } 
              ?>
          </div>

          <?php  
                        }
                        ?>
        </div>
      </div>
 
<!-- The Modal -->
<div class="modal" id="changepass">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header" style="<?php if ($_SESSION['CompanyColor']){ echo "color: #fff; background-color: " . $_SESSION['CompanyColor']; } ?>">
     
        <h4 class="modal-titles">Account Settings</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <!-- Modal body -->
      <div class="modal-body">
        <form class="frmchangepass">
          <label class="wrningpass" style="<?php if ($_SESSION['CompanyColor']){ echo "display: none; font-size: 20px; color: " . $_SESSION['CompanyColor']; } ?>"></label>
            <label class="validpass" style="display: none; color:red;">
              Your New Password Should: <br>
              -Be 8-15 Characteristic Length<br>
              -Contain at least an Uppercase Letter<br>
              -Contain at least a Lowercase Letter<br>
              -Contain at least a Numeric Digit<br>
              -Contain at least a Special Character<br>
          </label>
          <div class="form-group">
            <label for="usr">Current Password:</label>
            <input type="password" class="form-control" name="cupass" id="cupass">
          </div>
          <div class="form-group">
            <label for="usr">New Password:</label>
            <input type="password" class="form-control" name="npass" id="npass">
             <label class="vw-pass" style="text-decoration: underline; cursor: pointer;font-style: italic; font-size: 12px; color: #15cccc;">View Password</label>
          </div>
          <div class="form-group">
            <label for="usr">Confirm New Password:</label>
            <input type="password" class="form-control" name="cnpass" id="cnpass">
          </div>
        <button type="button" class="btn btn-info btn-block btnchangepass">Change Password</button>
        </form>
      </div>

      <!-- Modal footer -->
      <div class="modal-footer">
        <!-- <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button> -->
      </div>

    </div>
  </div>
</div>

  <!-- The Modal -->
<div class="modal" id="passchanged">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header" style="<?php if ($_SESSION['CompanyColor']){ echo "color: #fff; background-color: " . $_SESSION['CompanyColor']; } ?>">
     
        <h4 class="modal-titles">Password Changed</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <!-- Modal body -->
     


    </div>
  </div>
</div>
 
  </div>
   
    <div class="wedo-clock" style="position:fixed; bottom:0;left:0;z-index: 1">
          
        	<div class="banner-login thisclockhide" style="display: none;">
        	      <button data-toggle="tooltip" data-placement="right" title="Minimize Time" class="btn btnminclock" style="float: right;
    background-color: transparent;a
    border: none;
    color: black;"><i class="fa fa-window-minimize" aria-hidden="true"></i></button>
				<div class="tmdate-display">
					<h5 style="font-size: 20px;" id="dtnow">OCT 2, 2020</h5>
					<h1 id="hr-mn">00:00</h1><h6 id="sec">:00 AM</h6>
				</div>
			</div>
			
			<button data-toggle="tooltip" data-placement="right" title="Maximize Time" class="btn btn-primary btnmaxclock" style="float: left;"><i class="fa fa-calendar" aria-hidden="true"></i></button>
      <div style="display: inline-block;padding: 0px 5px; background-color:#fff;" class="clckmin"><h1 style="display: inline-block; margin: 0px;" id="hr-mn2">00:00</h1><h6 style="display: inline-block; margin: 0px;" id="sec2">:00 AM</h6></div>
    </div>
  <?php
    }
    else{
      ?>
        <h1>ERROR</h1>
      <?php
    }
  ?>
<!--<div class="clock-wedo" >-->
<!--        <div class="tmdate-display" >          -->
<!--          <h1 id="hr-mn">00:00</h1><h6 id="sec">:00 AM</h6>-->
<!--        </div>-->
<!--</div>-->