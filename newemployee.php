<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
   include 'w_conn.php';
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="assets/js/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-newemployee.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
        <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <title>Dashboard</title>
  <style>
        html body{
      font-family: Tahoma !important;
    }
  </style>

</head>

<body style="background-image: none">
   <?php 
    include 'includes/header.php'; ?>

     <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9 wd-login">
          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Enroll New Employee</h4>
           <h6 class="required-f">(Required Fields)</h6>
            <div class="btn-group btn-group-lg btn-click">
               <button class="btn btn-active text-white" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>" id="btn-ci" >General Information (6)</button>
               <button class="btn text-white" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>" id="btn-eb" >Educational Background</button>
               <button class="btn text-white" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>" id="btn-es">Employment Information (8)</button>
               <button class="btn text-white" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>" id="btn-ws">Work Schedule</button>
               <button class="btn text-white" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>"id="btn-cdd">Compliance Document Data</button>
               <!-- <button class="btn btn-danger" id="btn-ed">Employment Details</button> -->
               <button class="btn text-white" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>" id="btn-fd">Family Details</button>
               <button class="btn text-white" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>" id="btn-prf">Profile</button>   

            </div>

            <form id="fdata" method="post" enctype="multipart/form-data">

              <h5 class="frm-title">General Information</h5>
              <div class="row frm-ci">
                <div class="col-lg-4">
                  <div class="form-group">
                    <label for="uname">Create Username : <i>*</i></label>
                    <input type="text" class="form-control" required="required" name="uname" id="uname" placeholder="Enter Username">
                  </div>
                </div>
              </div>
              <div class="row frm-ci">
                <div class="col-lg-4">
                    <div class="form-group">
                      <label for="fname">First Name: <i>*</i></label>
                      <input type="text" class="form-control" required="required" name="empfn" id="empfname" placeholder="First Name">
                    </div>

                    <div class="form-group">
                      <label for="fname">Middle Name: </label>
                      <input type="text" class="form-control"  name="empmn"  id="empmname" placeholder="Middle Name">
                    </div>

                    <div class="form-group">
                      <label for="fname">Last Name: <i>*</i></label>
                      <input type="text" class="form-control" required="required" name="empln" id="emplname" placeholder="Last Name">
                    </div>

                    <div class="form-group">
                      <label for="fname">Suffix: </label>
                      <input type="text" class="form-control" required="required" name="empsuff" id="empsuff" placeholder="Suffix">
                    </div>
                </div>

                <div class="col-lg-4">
                  <div class="form-group">
                      <label for="sel1">Gender: <i>*</i></label>
                    <select class="form-control"  required="required" name="pempgender"  id="empgender" >
                      <option value="Female">Female</option>
                      <option value="Male">Male</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="fname">Citizenship: </label>
                    <input type="text" class="form-control" id="empcitizen" name="empcitizen" autocomplete="on"   placeholder="Citizenship">
                  </div>

                  <div class="cl-citizen">

                    <?php
                          $sql2=mysqli_query($con, "select DISTINCT EmpCitezen from empprofiles WHERE EmpCitezen IS NOT NULL");
                          while ($res1=mysqli_fetch_array($sql2)) {
                      ?>
                        <a class="ctzn-a"><?php echo $res1['EmpCitezen']; ?></a>
                      <?php
                          }
                    ?>                 
                  </div>

                  <div class="form-group">
                    <label for="fname">Religion: </label>
                    <input type="text" class="form-control" id="empreligion" name="empreligion" autocomplete="on"  placeholder="Religion">
                  </div>

                  <div class="cl-reli">
                    <?php
                          $sql2=mysqli_query($con, "select DISTINCT EmpReligion from empprofiles WHERE EmpReligion IS NOT NULL");
                          while ($res1=mysqli_fetch_array($sql2)) {
                      ?>
                        <a class="rel-a"><?php echo $res1['EmpReligion']; ?></a>
                      <?php }
                    ?>
                  </div>

                    <div class="form-group">
                      <label for="fname">Home Phone Number: </label>
                      <input type="text" class="form-control" id="empphome" name="emphomenumber"  placeholder="Home Phone Number">
                    </div>
                </div>

                <div class="col-lg-4">
                  <div class="form-group">
                    <label for="fname">Date of Birth: <i>*</i></label>
                    <input type="date" class="form-control" id="empdob" placeholder="Date of Birth" name="pempdob">
                  </div>

                  <div class="form-group">
                    <label for="sel1">Civil Status: <i>*</i></label>
                    <select class="form-control" required="required" id="empcs" name="pempcs">
                      <option ></option>
                      <option value="Single">Single</option>
                      <option value="Married">Married</option>
                      <option value="Divorced">Divorced</option>
                      <option value="Widowed">Widowed</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="fname">Mobile Number: </label>
                    <input type="text" class="form-control" id="phoneno" name="pempn0" placeholder="Mobile Number">
                  </div>

                    <div class="form-group">

                    <label for="fname">Email Address: </label>

                    <!--  -->

                    <input type="email" class="form-control" autocomplete="on"  id="empeadd" name="pempeadd" placeholder="Email Address">

                  </div>

                  

                </div>

                <div class="col-lg-12" style="padding: 0px">
                  <label style="padding-left:15px;" for="comment">Complete Mailing Address:  <i style="color: red;">*</i></label>
                  <div class="row">
                      <div class="col-lg-4">
                        <div class="form-group">
                            <input type="text" id="pempstreetno" name="pempstreetno" autocomplete="on"  class="form-control" required="required" placeholder="Street No/Street Name/Subdivision">
                        </div>
                        <div class="form-group">
                          <input type="text" id="pempdistrict" name="pempdistrict" autocomplete="on"  class="form-control" required="required" placeholder="Barangay">
                        </div>

                      </div>

                      <div class="col-lg-4" >
                        <div class="form-group">
                          <input type="text" id="pempcity" name="pempcity" autocomplete="on" class="form-control" required="required" placeholder="City">
                        </div>
                        <div class="form-group">
                          <input type="text" id="pempprovince" name="pempprovince" autocomplete="on" class="form-control" required="required" placeholder="Province">
                        </div>
                      </div>

                      <div class="col-lg-4" >
                        <div class="form-group">
                          <input type="text" id="pempzipcode" name="pempzipcode" autocomplete="on" class="form-control" required="required" placeholder="Zip Code">
                        </div>
                        <div class="form-group">
                          <input type="text" id="pempcountry" name="pempcountry" class="form-control" value="Philippines" required="required" placeholder="Country">
                        </div>
                      </div>
                  </div>
                </div>

              </div>

              <!-- Educational Background -->
              <div class="row frm-eb">
                <div class="col-lg-12">
                  <table class="table tbl-new">
                    <thead>
                      <tr>
                          <td></td>
                          <td>Name of School</td>
                          <td>Year Started</td>
                          <td>Year Graduated</td>
                          <td>School Address</td>
                      </tr>
                    </thead>
                    
                    <tbody>
                      <tr>
                        <td>Primary</td>
                        <td><input type="text" name="p_nameofschool" class="form-control"></td>
                        <td><input type="number" name="p_yearstarted" class="form-control"></td>
                        <td><input type="number" name="p_yeargraduated" class="form-control"></td>
                        <td><input type="text" name="p_schooladdress" class="form-control"></td>
                      </tr>
                      <tr>
                        <td>Secondary</td>
                        <td><input type="text" name="s_nameofschool" class="form-control"></td>
                        <td><input type="number" name="s_yearstarted" class="form-control"></td>
                        <td><input type="number" name="s_yeargraduated" class="form-control"></td>
                        <td><input type="text" name="s_schooladdress" class="form-control"></td>
                      </tr>
                      <tr>
                        <td>Tertiary</td>
                        <td><input type="text" name="t_nameofschool" class="form-control"></td>
                        <td><input type="number" name="t_yearstarted" class="form-control"></td>
                        <td><input type="number" name="t_yeargraduated" class="form-control"></td>
                        <td><input type="text" name="t_schooladdress" class="form-control"></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- employment status -->
              <div class="row frm-es">
                <div class="col-lg-6">
                  <div class="form-group">
                    <div class="col-lg-6">
                      <label>Employee ID : </label>
                      <input type="text" class="form-control" name="EmployeeIDNumber" id="EmployeeIDNumber" placeholder="Employee ID"/>
                    </div>
                    <div class="col-lg-6">
                          <label for="fname">Employee Number: <i>*</i></label>
                          <?php
                          $sql=mysqli_query($con, "select * from empdetails where EmpCompID='". $_SESSION['CompID'] ."'");
                          $nrows=mysqli_num_rows($sql);

                          $sql2=mysqli_query($con, "select * from companies where CompanyID='". $_SESSION['CompID'] ."'");
                          $res1=mysqli_fetch_array($sql2);

                          $getLastID=mysqli_query($con, "select * from empdetails where EmpCompID='". $_SESSION['CompID'] ."' ORDER BY Seq_ID desc Limit 1 ");
                          $getLastIDRow=mysqli_fetch_array($getLastID);


                       
                          $cnt="";
                          if ($nrows<10){
                            $cnt="000";
                          }elseif($nrows<100){
                            $cnt="00";
                          }else{
                            $cnt="0";
                          }

                          if($res1>0){
                            $em=$res1['compcode']  . '-' . $cnt . ($nrows+1);
                          }
                          
                          if ($_SESSION['UserType']==1){ ?>
                            <input type="text" readonly="readonly" class="form-control" required="required" name="empidn" id="empID" placeholder="Employee Number">
                        <?php }
                          else{ ?>
                            <input type="text" class="form-control" readonly="readonly" value="<?php echo $em; ?>" required="required" name="empidn" id="empID" placeholder="Employee Number">
                          <?php } ?>
                          <input type="hidden" class="form-control" name="empcheck" id="empcheck" placeholder="Employee Number">
                    </div>

                  </div>

                  <div class="form-group">
                    <label for="sel1">Company:</label>
                    <select class="form-control" id="empcompid" name="empcompany">
                      <?php
                      if ($_SESSION['UserType']==1){
                        $sql=mysqli_query($con, "select * from companies");
                        ?>
                        <option></option>
                        <?php
                          }else{
                            $sql=mysqli_query($con, "select * from companies where CompanyID='" . $_SESSION['CompID'] ."'");
                          }
                          while($res=mysqli_fetch_array($sql)){
                            ?>
                              <option  value="<?php echo $res['CompanyID']; ?>"><?php echo $res['CompanyDesc']; ?>  </option>
                            <?php }
                                ?>
                    </select>
                  </div> 

                  <div class="form-group">
                    <label for="sel1">Department: <i>*</i> <i data-toggle="modal" data-target="#modaladddep" class="fa fa-plus ne-addpl" id="depselect" aria-hidden="true"></i></label>
                      <?php
                        if ($_SESSION['UserType']==1){
                              $sql=mysqli_query($con, "select * from departments");
                              ?>
                            <select class="form-control DepEmp" id="empdepartment" required="required" name="empdep">
                            <option ></option>
                          <?php
                              }else{
                                $sql=mysqli_query($con, "select * from departments where CompID='" . $_SESSION['CompID'] ."'");
                                ?>
                                <select class="form-control DepEmp" id="empdepartment1" required="required" name="empdep">
                                <option ></option>
                                <?php
                              }
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                            <option class="dep<?php echo $res['CompID']; ?>" value="<?php echo $res[0]; ?>"><?php echo $res[1]; ?>  </option>
                            <?php } ?>
                        </select>
                  </div>  
                    <div class="form-group">
                        <label for="sel1">Position: <i>*</i></label> <i class="fa fa-plus ne-addpl Pos_Sel" data-toggle="modal" data-target="#modaladdpos" aria-hidden="true"></i>
                        <select class="form-control" id="idempposition" required="required" name="empposition">
                          <option ></option>
                          <?php
                          $sql=mysqli_query($con, "select * from positions");
                              while($res=mysqli_fetch_array($sql)){
                          ?>
                              <option class="pos<?php echo $res['DepartmentID']; ?>" value="<?php echo $res['PSID']; ?>"><?php echo $res['PositionDesc']; ?>  </option>
                          <?php   
                              }
                              ?>
                        </select>

                  </div>

                  <div class="form-group">
                        <label for="sel1">Classification: <i>*</i></label>
                        <select class="form-control" id="empdesccode" required="required" name="empclassification">
                          <option ></option>
                          <?php
                          $sql=mysqli_query($con, "select * from empstatus");
                              while($res=mysqli_fetch_array($sql)){
                          ?>
                              <option value="<?php echo $res['EmpStatID']; ?>"><?php echo $res['EmpStatDesc']; ?>  </option>
                          <?php   
                              }
                              ?>
                        </select>

                  </div> 

                  <div class="form-group">
                      <label for="sel1">Immediate Superior: <i>*</i></label>
                      <select class="form-control" id="empis" required="required" name="empis">
                      <option >N/A</option>
                      <?php
                        $sql=mysqli_query($con, "select * from employees as a inner join empdetails as b on a.EmpID=b.EmpID where a.EmpStatusID='1' and EmpRoleID!='3'");
                            while($row=mysqli_fetch_array($sql)){
                          ?>
                              <option class="is<?php echo $row['EmpdepID']; ?>" value="<?php echo $row['EmpID']; ?>"><?php echo $row['EmpFN'] . " " . $row['EmpLN']; ?>  </option>
                          <?php   
                              }
                            ?>
                              <!-- where c.DepartmentID=? -->
                      </select>
                  </div> 

                  <div class="form-group">
                      <label for="sel1">Status: <i>*</i></label>
                      <select class="form-control" required="required" id="empstatus" name="empst">
                        <option ></option>
                        <?php
                        $sql=mysqli_query($con, "select * from estatus");
                              while($res=mysqli_fetch_array($sql)){
                          ?>
                              <option value="<?php echo $res['ID']; ?>"><?php echo $res['StatusEmpDesc']; ?>  </option>
                          <?php   
                              }
                              ?>
                      </select>

                  </div>

                  <div class="form-group">
                        <label for="fname">Previous Position: </label>
                        <input type="text" class="form-control" name="pemppp" id="emppp" placeholder="Previous Position">
                  </div>

                    <div class="form-group">
                        <label for="fname">Start Date: </label>
                        <input type="date" class="form-control" name="pemppsd" id="emppsd" placeholder="Start Date">
                    </div>
                </div>

                <div class="col-lg-6">
                  <div class="form-group">
                        <label for="sel1">Job Level:</label>
                        <select class="form-control" id="idempjoblevel" required="required" name="empjoblevel">
                          <option ></option>
                          <?php
                            $sql=mysqli_query($con, "select * from joblevel");
                                while($res=mysqli_fetch_array($sql)){
                            ?>
                                <option value="<?php echo $res['jobLevelID']; ?>"><?php echo $res['jobLevelDesc']; ?>  </option>
                            <?php   
                                }
                                ?>
                        </select>
                  </div> 

                  <div class="form-group">
                    <label for="sel1">Agency: </label>
                    <select class="form-control" id="idempagency" required="required" name="empagency">
                      <option ></option>
                    <?php
                      $sql=mysqli_query($con, "select * from agency");
                          while($res=mysqli_fetch_array($sql)){
                      ?>
                          <option value="<?php echo $res['AgencyID']; ?>"><?php echo $res['AgencyName']; ?>  </option>
                      <?php   
                          }
                          ?>
                    </select>
                  </div> 

                  <div class="form-group">
                    <label for="sel1">HMO Number:</label>
                    <input type="text" id="idemphmo" class="form-control" name="emphmo">
                  </div> 

                  <div class="form-group">
                        <label for="sel1">HMO Provider:</label>
                        <select class="form-control" id="idhmoprovider" required="required" name="emphmoprovider">
                          <option ></option>
                        <?php
                          $sql=mysqli_query($con, "select * from hmo");
                              while($res=mysqli_fetch_array($sql)){
                          ?>
                              <option value="<?php echo $res['HMO_ID']; ?>"><?php echo $res['HMO_PROVIDER']; ?>  </option>
                          <?php   
                              }
                              ?>
                        </select>
                  </div> 
                
                  <div class="row">
                    <div class="col-lg-6">
                      <div class="form-group">
                        <label for="fname">Date Hired: <i>*</i></label>
                        <input type="date" class="form-control" id="empdth" value="<?php echo date('Y-m-d'); ?>" required="required" name="empdatehired" >
                      </div>
                    </div>

                    <div class="col-lg-6">
                      <div class="form-group">
                        <label for="fname">Date Resigned: </label>
                        <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>"  name="empdateresigned" id="empdtr">
                      </div>
                    </div>

                  </div>

                    <div class="row">

                      <div class="col-lg-6">
                        <div class="form-group">
                          <label for="fname">Basic: <i>*</i></label>
                          <input type="text" class="form-control" required="required" value="1.00"  id="empbasic" name="empbasic" placeholder="1">
                        </div>
                      </div>

                      <div class="col-lg-6">
                        <div class="form-group">
                        <label for="fname">Allowonce: </label>
                        <input type="text" class="form-control" id="fname" value="1.00"  name="empallowance" placeholder="1">
                        </div>
                      </div>

                    </div> 

                    <div class="form-group">
                      <label for="fname">Hourly Rate: </label>
                      <input type="text" class="form-control" id="fname" value="1.00" name="emphourlyrate" placeholder="1">
                    </div> 
                  
                    <div class="form-group">
                      <label for="fname">Previous Department: </label>
                      <input type="text" class="form-control" name="pemppdept"alue="1.00"  id="emppdept" placeholder="Department">
                    </div>

                    <div class="form-group">
                      <label for="fname">Previous Designation: </label>
                      <input type="text" class="form-control" name="pempppd"  id="empppd" placeholder="Designation">
                    </div>

                </div>

              </div>

              <!-- compliance document data -->

              <div class="row frm-cdd">

                   <div class="col-lg-6">

                     <div class="form-group">

                        <label for="fname">Passport Number: </label>

                        <input type="text" class="form-control" name="pempppno" id="empppno" placeholder="Passport Number">

                      </div>

                                

                        <div class="form-group">

                        <label for="fname">Passport Expiry Date: </label>

                        <input type="date" class="form-control" name="pemppped" id="emppped" placeholder="Expiry Date">

                      </div>

                   

                        <div class="form-group">

                        <label for="fname">Issuing Authority: </label>

                        <input type="text" class="form-control" name="pempppia" id="empppia" placeholder="Issuing Authority">

                      </div>

               

                     <div class="form-group">

                        <label for="fname">PAG-IBIG:</label>

                        <input type="text" class="form-control" name="pemppagibig" id="emppagibig" placeholder="PAG-IBIG">

                      </div>

                  </div>

                  <div class="col-lg-6">

                     <div class="form-group">

                        <label for="fname">PhilHealth:</label>

                        <input type="text" class="form-control" name="pempphno" id="empphno" placeholder="PhilHealth">

                      </div>

              

                     <div class="form-group">

                        <label for="fname">SSS Number: </label>

                        <input type="text" class="form-control"  name="pempsss" id="empsss" placeholder="SSS Number">

                      </div>

               

                     <div class="form-group">

                        <label for="fname">TIN: </label>

                        <input type="text" class="form-control" name="pemptin" id="emptin" placeholder="TIN">

                      </div>

                 

                     <div class="form-group">

                        <label for="fname">UMID: </label>

                        <input type="text" class="form-control" name="pempumid"  id="empumid" placeholder="UMID">

                      </div>

                  </div>

              </div>

              <!-- work days -->

              <div class="row frm-ws">

                    <table class="table tbl-relationship">

                    <thead>

                      <tr>

                        <th>Work Day</th>

                        <th>Work Time

                          <label style="float: right;">Check to Inherit <input type="checkbox" name="aaa" id="ifch"></label>

                        </th>

                       

                      </tr>

                    </thead>

                    <tbody>

                      <tr>

                        <td>Monday</td>

                        <td>

                            <select class="form-control" id="wrkschmon" required="required" name="wrkschmon">

                            <option >Restday</option>

                            <?php

                            $sql=mysqli_query($con, "select * from workschedule ");

                                  while($res=mysqli_fetch_array($sql)){

                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>

                              <?php   

                              }

                              ?>

                          </select>

                        </td>

                      </tr>

                       <tr>

                        <td>Tuesday</td>

                        <td>

                            <select class="form-control" id="wrkschtues" required="required" name="wrkschtues">

                            <option >Restday</option>

                            <?php

                            $sql=mysqli_query($con, "select * from workschedule ");

                                  while($res=mysqli_fetch_array($sql)){

                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>

                              <?php   

                              }

                              ?>

                          </select>

                        </td>

                      </tr>

                       <tr>

                        <td>Wednesday</td>

                        <td>

                            <select class="form-control" id="wrkschwed" required="required" name="wrkschwed">

                            <option >Restday</option>

                            <?php

                            $sql=mysqli_query($con, "select * from workschedule ");

                                  while($res=mysqli_fetch_array($sql)){

                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>

                              <?php   

                              }

                              ?>

                          </select>

                        </td>

                      </tr>

                       <tr>

                        <td>Thursday</td>

                        <td>

                            <select class="form-control" id="wrkschthu" required="required" name="wrkschthu">

                            <option >Restday</option>

                            <?php

                            $sql=mysqli_query($con, "select * from workschedule ");

                                  while($res=mysqli_fetch_array($sql)){

                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>

                              <?php   

                              }

                              ?>

                          </select>

                        </td>

                      </tr>

                       <tr>

                        <td>Friday</td>

                        <td>

                            <select class="form-control" id="wrkschfri" required="required" name="wrkschfri">

                            <option >Restday</option>

                            <?php

                            $sql=mysqli_query($con, "select * from workschedule ");

                                  while($res=mysqli_fetch_array($sql)){

                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>

                              <?php   

                              }

                              ?>

                          </select>

                        </td>

                      </tr>

                       <tr>

                        <td>Saturday</td>

                        <td>

                            <select class="form-control" id="wrkschsat" required="required" name="wrkschsat">

                            <option >Restday</option>

                            <?php

                            $sql=mysqli_query($con, "select * from workschedule ");

                                  while($res=mysqli_fetch_array($sql)){

                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>

                              <?php   

                              }

                              ?>

                          </select>

                        </td>

                      </tr>

                       <tr>

                        <td>Sunday</td>

                        <td>

                            <select class="form-control" id="wrkschsun" required="required" name="wrkschsun">

                            <option >Restday</option>

                            <?php

                            $sql=mysqli_query($con, "select * from workschedule ");

                                  while($res=mysqli_fetch_array($sql)){

                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>

                              <?php   

                              }

                              ?>

                          </select>

                        </td>

                      </tr>

                    </tbody>

                  </table>

                 <div class="col-lg-6"></div>

              </div>

              <div class="row frm-fd">

                <button class="btn btn-success" data-toggle="modal" data-target="#myModalrel">+ ADD</button>

                  <table class="table tbl-relationship">

                    <thead>

                      <tr>

                        <th>Name</th>

                        <th>Address</th>

                        <th>Relationship</th>

                        <th>Contact Number</th>

                        <th>ICE</th>

                        <th>Action</th>

                      </tr>

                    </thead>

                    <tbody>

                        <input type="hidden" name="non" id="rnum" value="0">

                    </tbody>

                     <div class="modal" id="myModalrel">

                          <div class="modal-dialog">

                            <div class="modal-content">

                            

                            

                               <div class="modal-header">

                                    <h4 class="modal-title">Family Details</h4>

                                    <button type="button" class="close" data-dismiss="modal">&times;</button>

                                  </div>

                              <!-- Modal body -->

                              <div class="modal-body">

                                  <form>

                                      <div class="form-gorup">

                                          <label>Name</label>

                                          <input type="text" placeholder="Firstname Middlename Lastname" required="required" id="famname" class="form-control">

                                      </div>

                                       <div class="form-gorup">

                                          <label>Address</label>

                                          <input type="text" placeholder="Address" id="famadd" required="required" class="form-control">

                                      </div>

                                       <div class="form-gorup">

                                          <label>Relationship</label>

                                            <label class="form-check-label">

                                              <!-- <input type="checkbox" class="relchk" value="">Others -->

                                            </label>

                                          <select class="form-control" id="rel" required="required" name="Frel">

                                            <option>
                                            </option>
                                                <option value="16">Sister</option>
                                                <option value="16">Brother</option>
                                                <option value="16">Mother</option>
                                                <option value="16">Father</option>
                                                <option value="16">Cousin</option>
                                                <option value="16">Grandmother</option>
                                                <option value="16">Grandfather</option>
                                                <option value="16">Spouse</option>


                                            <?php

                                          $sql=mysqli_query($con, "select * from fdetails");

                                                      while($res=mysqli_fetch_array($sql)){

                                                  ?>

                                            <option  value="<?php echo $res[0]; ?>"><?php echo $res[1]; ?>  </option>

                                                  <?php   

                                                      }

                                                      ?>

                                          </select>

                                          <input type="text" style="display: none;" id="relinput" placeholder="Relationship" class="form-control">

                                      </div>

                                       <div class="form-gorup">

                                          <label>Contact Number</label>

                                          <input type="text" id="famnumber" placeholder="Contact Number" required="required" class="form-control">

                                      </div>

                                       <br>



                                       <div class="form-gorup">

                                            <label class="form-check-label">

                                              <input type="checkbox" class="relice" value="">Tag as ICE?

                                            </label>

                                      </div>

                                      <br>

                                      <button type="button" class="btn btn-success btn-block btn-rel">Submit</button>

                                  </form>

                              </div>

                              

                            <!-- Modal footer -->

                            <div class="modal-footer">

                              <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>

                            </div>

                            

                          </div>

                        </div>

                      </div>

                  </table>

                   

              </div>

              <div class="row frm-prf">

                <div class="col-lg-12">

                  <div class="prof-pic">

                    

                  </div>

                  <input type="file" style="display: inline-block;" name="file" id="file" accept="image/*">

                </div>
                  <button style="float: right; bottom: 0; margin-top: 10px;" type="button" class="btn btn-primary" id="submit-form">Save All</button>

              </div>

            

            </form>

         </div>

       </div>

     </div>

        <!-- The Modal

                   -->      <div class="modal" id="myModal">

                          <div class="modal-dialog">

                            <div class="modal-content">

                            

                            

                               <div class="modal-header">

                                    <h4 class="modal-title">Warning</h4>

                                    <button type="button" class="close" data-dismiss="modal">&times;</button>

                                  </div>

                              <!-- Modal body -->

                              <div class="modal-body">

                                Under Construction......

                              </div>

                              

                            <!-- Modal footer -->

                            <div class="modal-footer">

                              <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>

                            </div>

                            

                          </div>

                        </div>

                      </div>



         <!-- The Modal add department -->

        <div class="modal" id="modaladddep">

          <div class="modal-dialog modal-dialog-centered" style="max-width: 750px">

            <div class="modal-content">

            

              <!-- Modal Header --> 

              <div class="modal-header" style="padding: 7px 8px;">

                  <h4 class="modal-title">Department</h4>

                <button type="button" class="close" data-dismiss="modal">&times;</button>

              </div>

              

              <!-- Modal body -->

              <div class="modal-body">

                  <form  method="post" class="frmdep">

                  

                  <div class="row">

                      <div class="col-lg-6">

                        <div class="form-group">

                          <label >Department Name:</label>

                          <input type="text" name="depname" id="depname" class="form-control" >

                        </div>

                    </div>

                       <div class="col-lg-6">

                        <div class="form-group">

                          <label >Company:</label>

                          <select class="form-control" name="compid" id="compid">

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

                                 if ($_SESSION['UserType']==1){



                                  $statement = $pdo->prepare("select * from companies");

                                 }else{

                                    $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");



                                 }

                              $statement->execute(array(':name' => ""));

                              while ($row = $statement->fetch()){

                              ?>

                                 <option value="<?php echo $row['CompanyID']; ?>"><?php echo $row['CompanyDesc']; ?></option> 

                                           

                              <?php

                            }

                              

                        ?>

                          </select>

                        </div>

                    </div>

                      



                    </div>

                           <button type="button" class="btn btn-success btn-block btndepartment">Save</button>

                          </form>

            </div>

          

              </div>

              

              <!-- Modal footer -->

           

              

            </div>

          </div>

        </div>

        <!-- modal end -->  





                 <!-- The Modal add position -->

        <div class="modal" id="modaladdpos">

          <div class="modal-dialog modal-dialog-centered" style="max-width: 750px">

            <div class="modal-content">

            

              <!-- Modal Header --> 

              <div class="modal-header" style="padding: 7px 8px;">

                  <h4 class="modal-title">Position</h4>

                <button type="button" class="close" data-dismiss="modal">&times;</button>

              </div>

              

              <!-- Modal body -->

              <div class="modal-body">

                  <form  method="post" class="frmpos">

                  

                    <div class="row">

                      <div class="col-lg-6">

                           <div class="form-group">

                          <label >Company:</label>

                           <select id="comchange" class="form-control">

                        

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

                                         if ($_SESSION['UserType']==1){

                          

                                            $statement = $pdo->prepare("select * from companies");

                                                echo "<option></option>";

                                          }  

                                          else if ($_SESSION['UserType']==2) {

                                            $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");



                                          }    

                                          else{

                                               $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");

                                          }                              



                                         $statement->execute(array(':name' => ""));

                                      while ($row = $statement->fetch()){

                                      ?>

                                        <option value="<?php echo $row['CompanyID']; ?>"><?php echo $row['CompanyDesc']; ?></option>

                                      <?php

                                    }

                                      

                                ?>

                          </select>

                        </div>

                         <div class="form-group">

                          <label >Department:</label>

                          <select id="dep" name="dep" required="required" class="form-control">

                                   

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

                                           if ($_SESSION['UserType']==1){

                                            $statement = $pdo->prepare("select * from departments inner join companies where departments.CompID=companies.CompanyID");

                                            echo "<option></option>";

                                          }else if($_SESSION['UserType']==2){

                                              $statement = $pdo->prepare("select * from departments inner join companies where departments.CompID=companies.CompanyID");

                                          }

                                      $statement->execute();

                                      while ($row = $statement->fetch()){

                                      ?>

                                        <option id="<?php echo $row['CompanyID']; ?>" value="<?php echo $row['DepartmentID']; ?>"><?php echo $row['DepartmentDesc']; ?></option>

                                      <?php



                                      }

                                    ?>

                          </select>

                      </div>

                    </div>



                      <div class="col-lg-6">

                         

                        <div class="form-group">

                          <label >Position:</label>

                          <input type="text" name="pos" id="pos" required="required" class="form-control" >

                        </div>

                          <div class="form-group">

                          <label >job Level:</label>

                           <select name="joblevel" id="joblevel" required="required" class="form-control">

                            <option></option>

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

                                      $statement = $pdo->prepare("select * from joblevel");

                                      $statement->execute();

                                      while ($row = $statement->fetch()){

                                      ?>

                                       <option value="<?php echo $row['jobLevelID']; ?>"><?php echo $row['jobLevelDesc']; ?></option>

                                                    

                                      <?php

                                    }

                                      

                                ?>

                          </select>

                      </div>

                      </div>



                    </div>

                           <button type="button" class="btn btn-success btn-block btnposition">Save</button>

                          </form>

            </div>

          

              </div>

              

              <!-- Modal footer -->

           

              

            </div>

          </div>

        </div>

        <!-- modal end -->  





        



                         <!-- The Modal -->

        <div class="modal" id="modalWarning">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>

              </div>
              <!-- Modal body -->
              <div class="modal-body">
                <div class="alert alert-danger">
                </div>
              </div>
              <!-- Modal footer -->
            </div>
          </div>
        </div>

        <!-- modal end -->  



        <!-- The Modal -->

        <div class="modal" id="modalWarning2">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>
              <!-- Modal body -->
              <div class="modal-body">
                <div class="alert alert-success">
            </div>
              </div>            
              <!-- Modal footer -->
            </div>
          </div>
        </div>
        <!-- modal end -->  
</body>

</html>