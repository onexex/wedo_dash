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
    <!--  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
    <script src="assets/js/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-updateemployee.js"></script>
    <script src="assets/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <title>Wedo Dashboard</title>
    <script type="text/javascript">

    </script>
  </head>
  <body style="background-image: none">
  <?php
    include 'includes/header.php';
    $q = $_GET['sid'];
    $result = mysqli_query($con, "SELECT empdetails.Seq_ID,hmo.HMO_ID,hmo.HMO_PROVIDER,agency.AgencyID,agency.AgencyName,empdetails.EmpID,empdetails.EmpDateHired,empdetails.EmpDateResigned,empstatus.EmpStatDesc,empstatus.EmpStatID,workschedule.WorkSchedID,
      workschedule.TimeFrom,workschedule.TimeTo,workdays.WID,workdays.WDesc,companies.CompanyID,companies.CompanyDesc,empdetails2.EmpBasic,
      empdetails2.EmpHRate,empdetails2.EmpAllowance,employees.EmpID as id,employees.EmpSuffix as sf,employees.EmpFN AS fn,employees.EmpLN as ln,employees.EmpMN as mn,employees.EmpSuffix,
      empprofiles.EmpAddress1,empprofiles.EmpDOB,empprofiles.EmpHMONumber,empprofiles.EmpGender,empprofiles.EmpEmail,empprofiles.EmpMobile,
      empprofiles.EmpPPNo,empprofiles.EmpPINo,empprofiles.EmpPHNo,empprofiles.EmpSSS,empprofiles.EmpTIN,
      empprofiles.EmpUMIDNo,empprofiles.EmpCitezen,empprofiles.EmpReligion,empprofiles.EmpPhone,empprofiles.EmpPPIA,
      empprofiles.EmpPP,empprofiles.EmpPPSD,empprofiles.EmpPPDept,empprofiles.EmpPPPos,empprofiles.EmpCS,empprofiles.EmpPPED,
      empprofiles.EmpAddDis,empprofiles.EmpAddCity,empprofiles.EmpAddProv,empprofiles.EmpAddZip,empprofiles.EmpAddCountry,joblevel.jobLevelID,joblevel.jobLevelDesc,
      empprofiles.EmpPPAth,estatus.StatusEmpDesc,estatus.ID,t.EmpSuffix,t.EmployeeIDNumber,t.EmpFN,t.EmpLN,t.EmpMN,positions.PositionDesc,positions.PSID,departments.DepartmentDesc,departments.DepartmentID,joblevel.JobLevelDesc,departments.DepartmentID,empdetails.EmpISID
    FROM empdetails 
    LEFT JOIN empstatus ON empdetails.EmpStatID=empstatus.EmpStatID
    LEFT JOIN hmo ON hmo.HMO_ID=empdetails.HMO_ID
    LEFT JOIN agency ON agency.AgencyID=empdetails.AgencyID
    LEFT JOIN workschedule ON empdetails.EmpWSID=workschedule.WorkSchedID
    LEFT JOIN workdays ON empdetails.EmpRDID=workdays.WID
    LEFT JOIN companies ON empdetails.EmpCompID=companies.CompanyID
    LEFT JOIN empdetails2 ON empdetails2.EmpID= empdetails.EmpID
    LEFT JOIN employees ON empdetails.EmpISID=employees.EmpID
    LEFT JOIN empprofiles ON empprofiles.EmpID=empdetails.EmpID                       
    LEFT JOIN employees as t ON empprofiles.EmpID=t.EmpID
    LEFT JOIN estatus ON estatus.ID=t.EmpStatusID
    LEFT JOIN positions ON positions.PSID=t.PosID
    LEFT JOIN joblevel ON joblevel.JobLevelID=positions.EmpJobLevelID
    LEFT JOIN departments ON positions.DepartmentID=departments.DepartmentID 
    WHERE empdetails.EmpID = trim('$q')");
    $res = mysqli_fetch_array($result); 
    $cnt= mysqli_num_rows ($result);
    $ISname= $res['fn'] . " " .  $res['ln'] . " " . $res['mn'] . " " . ucfirst($res['sf']);
    $isid=$res['id'];
  ?>
  <div class="w-container">
    <div class="row">
      <div class="col-lg-3"></div>
        <!-- website content -->
        <div class="col-lg-9 wd-login">
          <h4>Update Employee</h4>
          <h6 class="required-f">(Required Fields)</h6>
          <div class="btn-group btn-group-lg btn-click">
            <button class="btn btn-danger btn-active" id="btn-ci" >General Information (6)</button>
            <button class="btn btn-danger" id="btn-eb" >Educational Background</button>
            <button class="btn btn-danger" id="btn-es">Employment Information (8)</button>
            <button class="btn btn-danger" id="btn-ws">Work Schedule</button>
            <button class="btn btn-danger" id="btn-cdd">Compliance Document Data</button>
            <button class="btn btn-danger" id="btn-fd">Family Details</button>
            <button class="btn btn-danger" id="btn-prf">Profile</button>   
          </div>
          <form id="fdata" method="post" enctype="multipart/form-data">
            <h5 class="frm-title">Contact Information</h5>
              <div class="row frm-ci">
                <div class="col-lg-4">
                  <div class="form-group">
                    <label for="fname">First Name: <i>*</i></label>
                    <input type="text" class="form-control" required="required" name="empfn" id="empfname" placeholder="First Name" value="<?php echo $res['EmpFN']; ?>">
                  </div>
                  <div class="form-group">
                    <label for="fname">Middle Name: </label>
                    <input type="text" class="form-control"  name="empmn"  id="empmname" placeholder="Middle Name" value="<?php echo $res['EmpMN']; ?>">
                  </div>
                  <div class="form-group">
                    <label for="fname">Last Name: <i>*</i></label>
                    <input type="text" class="form-control" required="required" name="empln" id="emplname" placeholder="Last Name" value="<?php echo $res['EmpLN']; ?>">
                  </div>
                  <div class="form-group">
                    <label for="fname">Suffix: </label>
                    <input type="text" class="form-control" required="required" name="empsuff" id="empsuff" placeholder="Suffix" value="<?php echo $res['EmpSuffix']; ?>">
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="form-group">
                    <label for="sel1">Gender: <i>*</i></label>
                    <select class="form-control"  required="required" name="pempgender"  id="empgender" >
                      value="<?php echo $res['EmpLN']; ?>"
                      <option value="<?php echo $res['EmpGender']; ?>" > <?php echo $res['EmpGender']; ?></option>
                      <option value="Female">Female</option>
                      <option value="Male">Male</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="fname">Citizenship: </label>
                    <input type="text" class="form-control" id="empphome" name="empcitizen"  placeholder="Citizenship" value="<?php echo $res['EmpCitezen']; ?>">
                  </div>
                  <div class="form-group">
                    <label for="fname">Religion: </label>
                    <input type="text" class="form-control" id="empphome" name="empreligion"  placeholder="Religion" value="<?php echo $res['EmpReligion']; ?>">
                  </div>
                  <div class="form-group">
                    <label for="fname">Home Phone Number: </label>
                    <input type="text" class="form-control" id="empphome" name="emphomenumber"  placeholder="Home Phone Number" value="<?php echo $res['EmpPhone']; ?>">
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="form-group">
                    <label for="fname">Date of Birth: <i>*</i></label>
                    <input type="date" class="form-control" id="empdob" placeholder="Date of Birth" name="pempdob" value="<?php echo $res['EmpDOB']; ?>">
                  </div>
                  <div class="form-group">
                    <label for="sel1">Civil Status: <i>*</i></label>
                    <select class="form-control" required="required" id="empcs" name="pempcs">
                      <option value="<?php echo $res['EmpCS']; ?>"> <?php echo $res['EmpCS']; ?></option>
                      <option value="Single">Single</option>
                      <option value="Married">Married</option>
                      <option value="Divorced">Divorced</option>
                      <option value="Widowed">Widowed</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="fname">Mobile Number: </label>
                    <input type="text" class="form-control" id="phoneno" name="pempn0" placeholder="Mobile Number" value="<?php echo $res['EmpMobile']; ?>" >
                  </div>
                  <div class="form-group">
                    <label for="fname">Email Address: </label>
                    <input type="email" class="form-control"  id="empeadd" name="pempeadd" placeholder="Email Address" value="<?php echo $res['EmpEmail']; ?>">
                  </div>
                </div>
                <div class="col-lg-12">
                  <label for="comment">Complete Mailing Address:  <i style="color: red;">*</i></label>
                  <div class="row">
                    <div class="col-lg-4">
                      <div class="form-group">
                        <input type="text" id="pempstreetno" name="pempstreetno" class="form-control" required="required" placeholder="Street No/Street Name/Subdivision"value="<?php echo $res['EmpAddress1']; ?>">
                      </div>
                      <div class="form-group">
                        <input type="text" id="pempdistrict" name="pempdistrict" class="form-control" required="required" placeholder="Barangay" value="<?php echo $res['EmpAddDis']; ?>">
                      </div>
                    </div>
                    <div class="col-lg-4">
                      <div class="form-group">
                        <input type="text" id="pempcity" name="pempcity" class="form-control" required="required" placeholder="City" value="<?php echo $res['EmpAddCity']; ?>">
                      </div>
                      <div class="form-group">
                        <input type="text" id="pempprovince" name="pempprovince" class="form-control" required="required" placeholder="Province" value="<?php echo $res['EmpAddProv']; ?>">
                      </div>
                    </div>
                    <div class="col-lg-4">
                      <div class="form-group">
                        <input type="text" id="pempzipcode" name="pempzipcode" class="form-control" required="required" placeholder="Zip Code" value="<?php echo $res['EmpAddZip']; ?>">
                      </div>
                      <div class="form-group">
                        <input type="text" id="pempcountry" name="pempcountry" class="form-control" required="required" placeholder="Country" value="<?php echo $res['EmpAddCountry']; ?>">
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
                          <?php
                          $sqlpr=mysqli_query($con, "SELECT * FROM empeducationalbackground WHERE Program='Primary' AND EmpID='" . $res['EmpID']  . "'");
                          $rowpr=mysqli_fetch_array($sqlpr);
                          ?>
                          <tr>
                            <td>Primary</td>
                            <td><input type="text" value="<?php echo $rowpr['Name_of_School']; ?>" name="p_nameofschool" class="form-control"></td>
                            <td><input type="number" value="<?php echo $rowpr['Year_Started']; ?>" name="p_yearstarted" class="form-control"></td>
                            <td><input type="number" value="<?php echo $rowpr['Year_End']; ?>" name="p_yeargraduated" class="form-control"></td>
                            <td><input type="text" value="<?php echo $rowpr['School_Address']; ?>" name="p_schooladdress" class="form-control"></td>
                          </tr>
                          <?php
                          $sqlpr=mysqli_query($con, "SELECT * FROM empeducationalbackground WHERE Program='Secondary' AND EmpID='" . $res['EmpID']  . "'");
                          $rowpr=mysqli_fetch_array($sqlpr);
                          ?>
                           <tr>
                            <td>Secondary</td>
                            <td><input type="text" value="<?php echo $rowpr['Name_of_School']; ?>"  name="s_nameofschool" class="form-control"></td>
                            <td><input type="number" value="<?php echo $rowpr['Year_Started']; ?>" name="s_yearstarted" class="form-control"></td>
                            <td><input type="number" value="<?php echo $rowpr['Year_End']; ?>" name="s_yeargraduated" class="form-control"></td>
                            <td><input type="text" value="<?php echo $rowpr['School_Address']; ?>" name="s_schooladdress" class="form-control"></td>
                          </tr>
                            <?php
                                  $sqlpr=mysqli_query($con, "SELECT * FROM empeducationalbackground WHERE Program='Tertiary' AND EmpID='" . $res['EmpID']  . "'");
                                  $rowpr=mysqli_fetch_array($sqlpr);
                          ?>
                           <tr>
                            <td>Tertiary</td>
                            <td><input type="text" value="<?php echo $rowpr['Name_of_School']; ?>" name="t_nameofschool" class="form-control"></td>
                            <td><input type="number" value="<?php echo $rowpr['Year_Started']; ?>" name="t_yearstarted" class="form-control"></td>
                            <td><input type="number"  value="<?php echo $rowpr['Year_End']; ?>"  name="t_yeargraduated" class="form-control"></td>
                            <td><input type="text"  value="<?php echo $rowpr['School_Address']; ?>"  name="t_schooladdress" class="form-control"></td>
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
                      <label for="fname">Employee ID: <i>*</i></label>
                      <input type="text" class="form-control" required="required" name="empcid" id="empcid" placeholder="Employee ID" value="<?php echo $res['EmployeeIDNumber']; ?>">
                     </div>
                        <div class="col-lg-6">
                      <label for="fname">Employee Number: <i>*</i></label>
                      <input type="text" class="form-control" required="required" readonly="readonly" name="empidn" id="empID" placeholder="Employee Number" value="<?php echo $res['EmpID']; ?>">
                      <input type="hidden" class="form-control" required="required" name="empidhidden" id="empID" placeholder="Employee ID" value="<?php echo $res['EmpID']; ?>">
                    </div>
                    </div>
                       <div class="form-group">
                      <label for="sel1">Company:</label>
                      <select class="form-control" id="empcompid" name="empcompany">
                         <option value="<?php echo $res['CompanyID']; ?>"> <?php echo $res['CompanyDesc']; ?></option>

                        <?php
                      $sql=mysqli_query($con, "SELECT * FROM companies WHERE CompanyID<>'" . $res['CompanyID'] . "' AND CompanyID='" . $res['CompanyID'] . "'");
                                  while($row=mysqli_fetch_array($sql)){
                              ?>
                        <option  value="<?php echo $row['CompanyID']; ?>"> <?php echo $row['CompanyDesc']; ?></option>
                              <?php   
                                  }
                                  ?>
                     
                      </select>
                    </div> 
                    <div class="form-group">  
                          <label for="sel1">Department: <i>*</i></label>
                          <select class="form-control" id="empdepartment1" required="required" name="empdep">
                          <option value="<?php echo $res['DepartmentID']; ?>"> <?php echo $res['DepartmentDesc']; ?></option>  

                          <?php
                             $sql=mysqli_query($con, "SELECT * FROM departments WHERE CompID='" . $res['CompanyID'] . "'");
                                    while($row1=mysqli_fetch_array($sql)){
                                ?>
                              
                                    <option class="dep<?php echo $row1['CompID']; ?>" value="<?php echo $row1['DepartmentID']; ?>"><?php echo $row1['DepartmentDesc']; ?>  </option>
                             <?php 
                             }
                             ?>
                             
                          </select>
                    </div>  
                     <div class="form-group">
                          <label for="sel1">Position: <i>*</i></label>
                          <select class="form-control" id="idempposition" required="required" name="empposition">
                          <option value="<?php echo $res['PSID']; ?>"> <?php echo $res['PositionDesc']; ?></option> 
                          <?php
                            $sql=mysqli_query($con, "SELECT * FROM positions");
                                while($row=mysqli_fetch_array($sql)){
                            ?>
                                <option class="pos<?php echo $row['DepartmentID']; ?>" value="<?php echo $row['PSID']; ?>"><?php echo $row['PositionDesc']; ?>  </option>
                            <?php   
                                }
                                ?>
                                   
                          </select>
                    </div>

                    <div class="form-group">
                          <label for="sel1">Classification: <i>*</i></label>
                          <select class="form-control" id="empdesccode" required="required" name="empclassification">
                            <option value="<?php echo $res['EmpStatID']; ?>"> <?php echo $res['EmpStatDesc']; ?></option> 
                            <?php
                            $sql=mysqli_query($con, "SELECT * FROM empstatus");
                                while($row=mysqli_fetch_array($sql)){
                            ?>
                                <option value="<?php echo $row['EmpStatID']; ?>"><?php echo $row['EmpStatDesc']; ?>  </option>
                            <?php   
                                }
                                ?>
                               
                          </select>
                    </div>
                    
                    <div class="form-group" id="dorGroup" hidden>
                          <label for="sel1">Date of Regularization : <i>*</i></label>
                          <input class="form-control" type="date" id="dorInput" name="dorInput">
                    </div>
                    
                       <div class="form-group">
                          <label for="sel1">Immediate Superior: <i>*</i></label>
                          <select class="form-control" id="empis" required="required" name="empis">
                            
                          <?php 
                            if ($res['EmpISID']=="N/A"){
                            ?>
                              <option>N/A</option>
                            <?php
                            }else{
                          ?>
                              <option  value="<?php echo $res['EmpISID']; ?>"><?php echo $ISname; ?></option>
                          <?php
                            }
                          ?>
                        
                         <?php
                            $sql=mysqli_query($con, "SELECT * FROM employees a INNER JOIN empdetails b ON a.empid=b.EmpID WHERE a.empid<>'" . $res['EmpID']  . "'");
                                while($row=mysqli_fetch_array($sql)){
                              ?>
                                  <option class="is<?php echo $row['EmpdepID']; ?>" value="<?php echo $row['EmpID']; ?>"><?php echo $row['EmpFN'] . ", " . $row['EmpLN']; ?>  </option>
                              <?php   
                                  }
                                
                                ?>
                          </select>
                    </div> 
                    <div class="form-group">
                          <label for="sel1">Status: <i>*</i></label>
                          <select class="form-control" required="required" id="empstatus" name="empst">
                            <option value="<?php echo  $res['ID']; ?>"><?php echo $res['StatusEmpDesc']; ?></option>
                            <?php
                            $sql=mysqli_query($con, "SELECT * FROM estatus WHERE ID<>'$res[ID]'");
                                  while($row=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $row['ID']; ?>"><?php echo $row['StatusEmpDesc']; ?>  </option>
                              <?php   
                                  }
                                  ?>
                          </select>
                    </div>
          
                       
                    <div class="form-group">
                        <label for="fname">Previous Position: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpPP']; ?>" name="pemppp" id="emppp" placeholder="Previous Position">
                    </div>
                     <div class="form-group">
                        <label for="fname">Start Date: </label>
                        <input type="date" class="form-control" value="<?php echo $res['EmpPPSD']; ?>"accept=" " name="pemppsd" id="emppsd" placeholder="Start Date">
                    </div>
                    
                     
                   
                       
                  </div>
                  <div class="col-lg-6">
                         <div class="form-group">
                          <label for="sel1">Job Level:</label>
                          <select class="form-control" id="idempposition" required="required" name="empjoblevel">
                            <option value="<?php echo $res['jobLevelID']; ?>"><?php echo $res['jobLevelDesc']; ?>  </option>
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

                            $statement = $pdo->prepare("SELECT * FROM joblevel");
                            $statement->execute(); 
                            while ($row = $statement->fetch()){
                            ?>
                                <option value="<?php echo $row['jobLevelID']; ?>"><?php echo $row['jobLevelDesc']; ?>  </option>
                            <?php   
                                }
                                ?>

                          </select>
                    </div> 

                        <div class="form-group">
                          <label for="sel1">Agency:</label>
                          <select class="form-control" id="idempposition" required="required" name="empagency">
                            <option value="<?php echo $res['AgencyID']; ?>"><?php echo $res['AgencyName']; ?>  </option>
                          <?php
                            $sql=mysqli_query($con, "SELECT * FROM agency");
                                while($row=mysqli_fetch_array($sql)){
                            ?>
                                <option value="<?php echo $row['AgencyID']; ?>"><?php echo $row['AgencyName']; ?>  </option>
                            <?php   
                                }
                                ?>

                          </select>
                    </div> 
                     <div class="form-group">
                          <label for="sel1">HMO Number:</label>
                          <input type="text" id="idemphmo" value="<?php echo $res['EmpHMONumber']; ?>" class="form-control" name="emphmono">
                    </div> 
                      <div class="form-group">
                          <label for="sel1">HMO:</label>
                          <select class="form-control" id="idempposition" required="required" name="emphmo">
                            <option value="<?php echo $res['HMO_ID']; ?>"><?php echo $res['HMO_PROVIDER']; ?>  </option>
                          <?php
                            $sql=mysqli_query($con, "SELECT * FROM hmo");
                                while($row=mysqli_fetch_array($sql)){
                            ?>
                                <option value="<?php echo $row['HMO_ID']; ?>"><?php echo $row['HMO_PROVIDER']; ?>  </option>
                            <?php   
                                }
                                ?>

                          </select>
                    </div> 
                    
                    <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label for="fname">Date Hired: <i>*</i></label>
                          <input type="date" class="form-control" id="empdth" required="required" name="empdatehired" value="<?php echo $res['EmpDateHired']; ?>">

                        </div>
                      </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                        <label for="fname">Date Resigned: </label>
                        <input type="date" class="form-control"  name="empdateresigned" id="empdtr" value="<?php echo $res['EmpDateResigned']; ?>">
                      </div>
                      </div>
                    </div>
                                         
                     
                     <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label for="fname">Basic: <i>*</i></label>
                          <input type="text" class="form-control" value="<?php echo $res['EmpBasic']; ?>.00" required="required" id="empbasic" name="empbasic" placeholder="0.00" >
                        </div>
                      </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                        <label for="fname">Allowance: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpAllowance']; ?>.00" id="fname" name="empallowance" placeholder="0.00">
                      </div>
                      </div>
                    </div> 
                     <div class="form-group">
                        <label for="fname">Hourly Rate: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpHRate']; ?>.00" id="fname" name="emphourlyrate" placeholder="0.00">
                      </div> 
                       <div class="form-group">
                        <label for="fname">Previous Department: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpPPDept']; ?>" name="pemppdept" id="emppdept" placeholder="Department">
                    </div>
                     <div class="form-group">
                        <label for="fname">Previous Designation: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpPPPos']; ?>" name="pempppd" id="empppd" placeholder="Designation">
                    </div>
                  </div>
              </div>
              <!-- compliance document data -->
              <div class="row frm-cdd">
                   <div class="col-lg-6">
                     <div class="form-group">
                        <label for="fname">Passport Number: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpPPNo']; ?>" name="pempppno" id="empppno" placeholder="Passport Number">
                      </div>
                                
                        <div class="form-group">
                        <label for="fname">Passport Expiry Date: </label>
                        <input type="date" class="form-control" value="<?php echo $res['EmpPPED']; ?>" name="pemppped" id="emppped" placeholder="Expiry Date">
                      </div>
                   
                        <div class="form-group">
                        <label for="fname">Issuing Authority: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpPPIA']; ?>" name="pempppia" id="empppia" placeholder="Issuing Authority">
                      </div>
               
                     <div class="form-group">
                        <label for="fname">PAG-IBIG:</label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpPINo']; ?>" name="pemppagibig" id="emppagibig" placeholder="PAG-IBIG">
                      </div>
                  </div>
                  <div class="col-lg-6">
                     <div class="form-group">
                        <label for="fname">PhilHealth:</label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpPHNo']; ?>" name="pempphno" id="empphno" placeholder="PhilHealth">
                      </div>
              
                     <div class="form-group">
                        <label for="fname">SSS Number: </label>
                        <input type="text" class="form-control"  value="<?php echo $res['EmpSSS']; ?>" name="pempsss" id="empsss" placeholder="SSS Number">
                      </div>
               
                     <div class="form-group">
                        <label for="fname">TIN: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpTIN']; ?>" name="pemptin" id="emptin" placeholder="TIN">
                      </div>
                 
                     <div class="form-group">
                        <label for="fname">UMID: </label>
                        <input type="text" class="form-control" value="<?php echo $res['EmpUMIDNo']; ?>" name="pempumid"  id="empumid" placeholder="UMID">
                      </div>
                  </div>
              </div>
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
                           

                            <?php
                            $sqlsc="SELECT * FROM workdays WHERE Day_s='Monday' AND empid='$q'";
                            $scqry=mysqli_query($con,$sqlsc);
                            $scres=mysqli_fetch_array($scqry);
                            $scnrow=mysqli_num_rows($scqry);
                            if ($scnrow>0){

                                $sql=mysqli_query($con, "SELECT * FROM workschedule WHERE WorkSchedID=$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                             
                              <?php  
                            }
                              ?>
                                
                                 <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "SELECT * FROM workschedule WHERE WorkSchedID<>$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }

                            }else{
                                 $sql=mysqli_query($con, "SELECT * FROM workschedule");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }
                                ?>
                                <option value="0">Rest Day</option>
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
                            <?php
                            $sqlsc="SELECT * FROM workdays WHERE Day_s='Tuesday' AND empid='$q'";
                            $scqry=mysqli_query($con,$sqlsc);
                            $scres=mysqli_fetch_array($scqry);
                            $scnrow=mysqli_num_rows($scqry);
                            if ($scnrow>0){
                              
                                $sql=mysqli_query($con, "SELECT * FROM workschedule WHERE WorkSchedID=$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                                
                              <?php  
                            }
                             ?>
                                
                                 <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "SELECT * FROM workschedule WHERE WorkSchedID<>$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }

                            }else{
                                 $sql=mysqli_query($con, "SELECT * FROM workschedule");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }
                                ?>
                                <option value="0">Rest Day</option>
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
                             <?php
                            $sqlsc="select * from workdays where Day_s='Wednesday' and empid='$q'";
                            $scqry=mysqli_query($con,$sqlsc);
                            $scres=mysqli_fetch_array($scqry);
                            $scnrow=mysqli_num_rows($scqry);
                            if ($scnrow>0){
                            
                                $sql=mysqli_query($con, "select * from workschedule where WorkSchedID=$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                                  
                              <?php  
                            }
                              ?>
                                
                                 <option value="0" >Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "select * from workschedule where WorkSchedID<>$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }

                            }else{
                                 $sql=mysqli_query($con, "select * from workschedule");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }
                                ?>
                                <option value="0" >Rest Day</option>
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
                            <?php
                            $sqlsc="select * from workdays where Day_s='Thursday' and empid='$q'";
                            $scqry=mysqli_query($con,$sqlsc);
                            $scres=mysqli_fetch_array($scqry);
                            $scnrow=mysqli_num_rows($scqry);
                            if ($scnrow>0){
                          
                                $sql=mysqli_query($con, "select * from workschedule where WorkSchedID=$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              
                              <?php  
                            }
                              ?>
                                
                                 <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "select * from workschedule where WorkSchedID<>$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }

                            }else{
                                 $sql=mysqli_query($con, "select * from workschedule");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }
                                ?>
                                <option value="0">Rest Day</option>
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
                            <?php
                            try{
                                
                           
                            $sqlsc="SELECT * FROM workdays WHERE Day_s='Friday' AND empid='$q'";
                            $scqry=mysqli_query($con,$sqlsc);
                            $scres=mysqli_fetch_array($scqry);
                            $scnrow=mysqli_num_rows($scqry);
                            if ($scnrow>0){
                            
                                $sql=mysqli_query($con, "select * from workschedule where WorkSchedID=$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                            
                              <?php  
                            }
                              ?>
                                 <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "select * from workschedule where WorkSchedID<>$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }

                            }else{
                                  ?>
                                <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "select * from workschedule");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }
                            

                            }
                            }
                            catch (Exception $e) {
                                echo 'Caught exception: ',  $e->getMessage(), "\n";
                            }

                          ?>
                          </select>
                        </td>
                      </tr>
                       <tr>
                        <td>Saturday</td>
                        <td>
                            <select class="form-control" id="wrkschsat" required="required" name="wrkschsat">
                            <?php
                            $sqlsc="select * from workdays where Day_s='Saturday' and empid='$q'";
                            $scqry=mysqli_query($con,$sqlsc);
                            $scres=mysqli_fetch_array($scqry);
                            $scnrow=mysqli_num_rows($scqry);
                            if ($scnrow>0){
                        
                                $sql=mysqli_query($con, "select * from workschedule where WorkSchedID=$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                                 
                              <?php  
                            }
                              ?>
                                
                                 <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "select * from workschedule where WorkSchedID<>$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }

                            }else{
                                  ?>
                                <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "select * from workschedule");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }
                              

                            }

                          ?>
                          </select>
                        </td>
                      </tr>
                       <tr>
                        <td>Sunday</td>
                        <td>
                            <select class="form-control" id="wrkschsun" required="required" name="wrkschsun">
                             <?php
                            $sqlsc="SELECT * FROM workdays WHERE Day_s='Sunday' AND empid='$q'";
                            $scqry=mysqli_query($con,$sqlsc);
                            $scres=mysqli_fetch_array($scqry);
                            $scnrow=mysqli_num_rows($scqry);
                            if ($scnrow>0){
                             
                                // $sql=mysqli_query($con, "SELECT * FROM workschedule WHERE WorkSchedID=$scres["SchedTime"]");
                                $sql=mysqli_query($con, "SELECT * FROM workschedule WHERE WorkSchedID=$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>

                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                               
                              <?php  
                            }
                              ?>
                                
                                 <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "SELECT * FROM workschedule WHERE WorkSchedID<>$scres[SchedTime]");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }

                            }else{
                                  ?>
                                <option value="0">Rest Day</option>
                              <?php
                                 $sql=mysqli_query($con, "SELECT * FROM workschedule");
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                                  <option value="<?php echo $res['WorkSchedID']; ?>"><?php echo $res['TimeFrom']. "-" .$res['TimeTo']; ?>  </option>
                              <?php  
                              }
                              
                            }

                          ?>
                          </select>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <div class="col-lg-6">
                 
                 </div>
                 <div class="col-lg-6"></div>
              </div>
              <div class="row frm-fd">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#Newmodalform">+ ADD</button>
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
                        <?php
                          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                          $statement = $pdo->prepare("SELECT * FROM fdetails WHERE FDetID = :id");
                          $statement->bindParam(':id' , $q);
                          $statement->execute(); 
                          $id=0;
                          while ($row = $statement->fetch()){
                            $id=$id+1;
                          ?>
                              <tr id="<?php echo $id; ?>">
                              <td class="name<?php echo $id ?>"><?php echo $row['FName']; ?></td>
                              <td class="add<?php echo $id ?>"> <?php echo $row['FAdd']; ?></td>
                              <td class="rel<?php echo $id ?>"><?php echo $row['FRel']; ?></td>
                              <td class="con<?php echo $id ?>"><?php echo $row['FContact']; ?></td>
                              <td class="ice<?php echo $id ?>"><?php echo $row['FICE']; ?></td>
                              <!-- <td><button class="btn btn-success" data-toggle="modal" data-target="#myModalrel<?php echo $row['FSID']; ?>">Update</button> -->
                                <td><button class="btn btn-link" onclick="myremovetr(this)">Remove</button></td>
                              </tr>
                                <div class="modal" id="myModalrel<?php echo $row['FSID']; ?>">
                          <div class="modal-dialog">
                            <div class="modal-content">
                            
                            
                               <div class="modal-header">
                                    <h4 class="modal-title">Family Details</h4>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                  </div>
                              <!-- Modal body -->
                              <div class="modal-body">
                                  <form method="post">
                                      <div class="form-gorup">
                                          <label>Name</label>
                                          <input type="text" value="<?php echo $row['FName']; ?>" required="required" id="famname<?php echo $row['FSID']; ?>" class="form-control">
                                      </div>
                                       <div class="form-gorup">
                                          <label>Address</label>
                                          <input type="text" value="<?php echo $row['FAdd']; ?>" id="famadd<?php echo $row['FSID']; ?>" required="required" class="form-control">
                                      </div>
                                       <div class="form-gorup">
                                          <label>Relationship</label>
                                            <label class="form-check-label">
                                              <!-- <input type="checkbox" class="relchk" value="">Others -->
                                              <option value="16">Sister</option>
                                                <option value="16">Brother</option>
                                                <option value="16">Mother</option>
                                                <option value="16">Father</option>
                                                <option value="16">Cousin</option>
                                                <option value="16">Grandmother</option>
                                                <option value="16">Grandfather</option>
                                                <option value="16">Spouse</option>
                                            </label>
                                          <select class="form-control" id="relsendName"  required="required" name="Frel<?php echo $row['FSID']; ?>">
                                            <option value="<?php echo $row['FRel']; ?>"><?php echo $row['FRel']; ?></option>

                                            <?php
                                          $sql=mysqli_query($con, "SELECT * FROM frelationship");
                                                      while($res1=mysqli_fetch_array($sql)){
                                                  ?>
                                            <option  value="<?php echo $res1[0]; ?>"><?php echo $res1[1]; ?>  </option>
                                                  <?php   
                                                      }
                                                      ?>
                                          </select>
                                          <input type="text" value="<?php echo $row['FContact']; ?>" style="display: none;" id="relinput<?php echo $row['FSID']; ?>" placeholder="Relationship" class="form-control">
                                      </div>
                                       <div class="form-gorup">
                                          <label>Contact Number</label>
                                          <input type="text" id="famnumber<?php echo $row['FSID']; ?>" value="<?php echo $row['FContact']; ?>" required="required" class="form-control">
                                      </div>
                                       <br>

                                       <div class="form-gorup">
                                            <label class="form-check-label">
                                              <input type="checkbox" class="relice" value="">Tag as ICE?
                                            </label>
                                      </div>
                                      <br>
                                      <button type="button" class="btn btn-success btn-block btn-updaterel">Submit</button>
                                  </form>
                              </div>
                              
                            <!-- Modal footer -->
                            <div class="modal-footer">
                              <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                            </div>
                            
                          </div>
                        </div>
                      </div>
                              <?php }?>

                                          <input type="hidden" name="non" id="rnum" value="<?php echo $id; ?>">
                    </tbody>
                   



                     <div class="modal" id="Newmodalform">
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
                                            <option></option>

                                            <?php
                                            
                                          $sql=mysqli_query($con, "SELECT * FROM frelationship");
                                                      while($rrow=mysqli_fetch_array($sql)){
                                                  ?>
                                            <option  value="<?php echo $rrow[0]; ?>"><?php echo $rrow[1]; ?>  </option>
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
                <div class="prof-pic" style="background-image: 
                <?php 
                    if ($res['EmpPPAth']==""){
                        echo "url('assets/images/profiles/default.png')";
                    }
                    else{
                      echo  "url('" .$res['EmpPPAth'] . "')";
                    }

                    ?>;">
                </div>
                 <input type="file" style="display: inline-block;" name="file" id="file" accept="image/*">
              </div>
              <button style="float: right; bottom: 0;" type="button" class="btn btn-primary" id="submit-form">Update</button>
            </form>
         </div>
       </div>
     </div>

    <!-- The Modal -->      
    <div class="modal" id="myModal">
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
    
    <script type="text/javascript">
      $('#empdesccode').change(function(){
        if($('#empdesccode').val() == 1){
          $('#dorGroup').removeAttr('hidden');
          $('#dorInput').attr('required', true);
        }
        else{
          $("#dorGroup").attr("hidden",true);
          $('#dorInput').attr('required', false);
        }
      });
    </script>
</body>
</html>