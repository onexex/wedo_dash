<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once ("w_conn.php");
$q = $_GET['q'];
$sql = "SELECT empdetails.Seq_ID,hmo.HMO_PROVIDER,agency.AgencyName,empdetails.EmpID,empdetails.EmpDateHired,empdetails.EmpDateResigned,empstatus.EmpStatDesc,
                                      workschedule.TimeFrom,workschedule.TimeTo,workdays.WDesc,companies.CompanyDesc,empdetails2.EmpBasic,
                                      empdetails2.EmpHRate,empdetails2.EmpAllowance,employees.EmpFN AS fn,employees.EmpLN AS ln,employees.EmpMN AS mn,
                                      empprofiles.EmpAddress1,empprofiles.EmpDOB,empprofiles.EmpGender,empprofiles.EmpEmail,empprofiles.EmpMobile,
                                      empprofiles.EmpPPNo,empprofiles.EmpPINo,empprofiles.EmpPHNo,empprofiles.EmpSSS,empprofiles.EmpTIN,
                                      empprofiles.EmpUMIDNo,empprofiles.EmpCitezen,empprofiles.EmpReligion,empprofiles.EmpPhone,
                                      empprofiles.EmpPP,empprofiles.EmpPPSD,empprofiles.EmpPPDept,empprofiles.EmpPPPos,empprofiles.EmpCS,
                                       empprofiles.EmpHMONumber,
                                    empprofiles.EmpAddDis,empprofiles.EmpAddCity,empprofiles.EmpAddProv,empprofiles.EmpAddZip,empprofiles.EmpAddCountry,empprofiles.EmpPPED,empprofiles.EmpPPIA,
                                      empprofiles.EmpPPAth,estatus.StatusEmpDesc,t.EmployeeIDNumber,t.EmpFN,t.EmpLN,t.EmpMN,positions.PositionDesc,positions.PSID,departments.DepartmentDesc,joblevel.jobLevelDesc,employees.EmpSuffix
 FROM empdetails 

                              LEFT JOIN empstatus ON empdetails.EmpStatID=empstatus.EmpStatID
                              LEFT JOIN hmo ON hmo.HMO_ID=empdetails.HMO_ID
                              LEFT JOIN agency ON agency.AgencyID=empdetails.AgencyID
                              LEFT JOIN workschedule ON empdetails.EmpWSID=workschedule.WorkSchedID
                              LEFT JOIN workdays ON empdetails.EmpRDID=workdays.WID
                              LEFT JOIN companies ON empdetails.EmpCompID=companies.CompanyID
                              LEFT JOIN empdetails2 ON empdetails2.EmpID = empdetails.EmpID
                              LEFT JOIN employees ON empdetails.EmpISID=employees.EmpID
                              LEFT JOIN empprofiles ON empprofiles.EmpID=empdetails.EmpID                       
                              LEFT JOIN employees AS t ON empprofiles.EmpID=t.EmpID
                              LEFT JOIN estatus ON estatus.ID=t.EmpStatusID
                              LEFT JOIN positions ON positions.PSID=t.PosID
                              LEFT JOIN joblevel ON positions.EmpJobLevelID=joblevel.jobLevelID
                              LEFT JOIN departments ON empdetails.EmpdepID=departments.DepartmentID 
                              WHERE empdetails.EmpID = '$q'";



    try {
      $result=mysqli_query($con,$sql);
      }

    //catch exception
    catch(Exception $e) {
      echo 'Message: ';
    }

    $res = mysqli_fetch_array($result); 
    $cnt= mysqli_num_rows ($result);
    $ISname= $res['fn'] . " " .  $res['ln'] . " " . $res['mn'] ;
    $profile= ucfirst($res['EmpFN']) . " " .  ucfirst($res['EmpMN']) . " " . ucfirst($res['EmpLN']) . " " . ucfirst($res['EmpSuffix']) ;
    $dob= $res['EmpDOB'];
    //$comp= $res['CompanyDesc'];

    $wschedule=$res['TimeFrom'] . " " . $res['TimeTo'];
    if ($cnt == 0){
    ?>
    <div class="row">
      <div class="col-lg-3"></div>
        <!-- website content -->
          <div class="col-lg-9">
          <center><h1 >No Result Found !</h1></center>
          </div>
      </div>
    <script type="text/javascript"> 
        $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip(); 
        });
        $(document).ready(function(){
        $('[data-toggle="popover"]').popover();   
      });
    </script>

<?php
}
else{
  ?>
  <div class="row">
         <div class="col-lg-3"></div>
          <!-- website content -->
           <div class="col-lg-9">
             <div class="row">
                  <div class="msg" id="msg"><a href="">Messages <i>0</i></a></div>
                  <div class="col-lg-4" id="cdc-hide">
                  <div class="prof-img" style="background-image: 
               <?php 
               
                   if(file_exists($res['EmpPPAth'])){
                      echo  "url('" .$res['EmpPPAth']. "')";                 
                    }
                    else {
                      if ($res['EmpGender']=="Male") {
                        echo "url('assets/images/profiles/man_d.jpg')";                          
                      }else{
                        echo "url('assets/images/profiles/woman_d.jpg')";
                      }
                    }
                    ?>;">

                   </div>

                   <div class="emp-details" id="emp-detailscdc">
                      <h4 class="title dropdwn-title" >Compliance Document Data<i class="fa fa-angle-down toggle-btn"></i></h4>
                      <hr>
                      <div class="aaaa">
                        <table class="table">
                           <thead>
                             <tr class="cdd-tr"><th class="det-f">Passport Number <i style="outline: none;" class="fa fa-question-circle" href="#" role="button" data-trigger="focus" tabindex="0" data-toggle="popover" data-html="true" title="Details"  data-content="Expiry Date: <br/> " <?php echo $res['EmpPPNo']; ?> "Issuing Authority:"<?php echo $res['EmpPPNo']; ?>></i> </th><th class="com_number"><?php echo $res['EmpPPNo']; ?></th></tr>
                             <tr class="cdd-tr"><th class="det-f">PAG-IBIG:</th><th class="com_number"><?php echo $res['EmpPINo']; ?></th></tr>
                             <tr class="cdd-tr"><th class="det-f">PHILHEALTH:</th><th class="com_number"><?php echo $res['EmpPHNo']; ?></th></tr>
                             <tr class="cdd-tr"><th class="det-f">SSS Number:</th><th class="com_number"><?php echo $res['EmpSSS']; ?></th></tr>
                             <tr class="cdd-tr"><th class="det-f">TIN:</th><th class="com_number"><?php echo $res['EmpTIN']; ?></th></tr>                                       
                             <tr class="cdd-tr"><th class="det-f">UMID:</th><th class="com_number"><?php echo $res['EmpUMIDNo']; ?></th></tr>
                           </thead>
                         </table>
                      </div>

                   </div>
                      <div class="emp-details">
                       <h4 class="title dropdwn-title" >Job Description<i class="fa fa-angle-down toggle-btn"></i></h4>
                        <hr>
                       <div class="aaaa">
                        <table class="table">
                           <thead id="jdview">
                              <?php
                                $res12=mysqli_query($con,"select * from jobdescription inner join empjobdesc on jobdescription.JD_ID=empjobdesc.JID where empjobdesc.EmpID='" . $res['EmpID'] . "'");
                                while ($row3=mysqli_fetch_array($res12)) {
                              ?>
                              <tr class="cdd-tr"><th class="det-f"><?php echo $row3["JDescription"]; ?></th></tr>
                              <?php } ?>
                           </thead>

                            <!--  <tr class="cdd-tr"><th class="det-f"><button class="btn btn-success" data-toggle="modal" data-target="#newjobd"><i class="fa fa-plus" aria-hidden="true"></i> Add Job Description</button></th></tr> -->

                         </table>

                         </div>

                   </div>

                    <div id="ed-to-hide" class="emp-details ed-st">

                       <h4 class="title dropdwn-title">Employment Details<i class="fa fa-angle-down toggle-btn"></i></h4>

                        <hr>

                         <div class="aaaa">

                        <table class="table">

                           <thead>

                             <tr class="pos"><th><i class="fa fa-briefcase"></i> <?php echo $res['PositionDesc']; ?>/<i class="current-prev">Current</i></th></tr>

                          

                            <tr class="info-t"><th><?php  echo $res['DepartmentDesc']; ?></th></tr>

                             <tr class="info-t"><th><?php echo $res['EmpDateHired']; ?></th></tr>



                           </thead>

                           <thead>

                             <tr class="pos"><th><i class="fa fa-briefcase"></i> <?php echo $res['EmpPP']; ?>/<i class="current-prev">Previous</i></th></tr>

                             <tr class="info-t"><th><?php echo $res['EmpPPPos']; ?></th></tr>

                            <tr class="info-t"><th><?php echo $res['EmpPPDept']; ?></th></tr>

                             <tr class="info-t"><th><?php echo $res['EmpPPSD']; ?></th></tr>



                           </thead>

                         </table>

                         </div>

                     </div>

                        <div class="col-md-12 pd-none">

                              <div class="emp-details">

                               <h4 class="title dropdwn-title " >Electronic 201 Files<i class="fa fa-angle-down toggle-btn"></i></h4>

                               <hr>

                               <div class="row tbl-hide">

                                 <div class="col-lg-12 pd-none ">

                                    <div class="row e-files">



                                    <?php

                                      $sql="select * from empe201files where EMPID='" . $q ."'";

                                      $query=mysqli_query($con, $sql);

                                      if (mysqli_num_rows($query)<1){

                                       echo "<h3>Empty</h3>";

                                      }else{

                                         while($r=mysqli_fetch_array($query)){

                                      ?>

                                         <div class="col-lg-2"><a href="#" id="pdf-filename" data-toggle="modal" data-target="#myModaldd<?php echo $r[0]; ?>"><i class="fa fa-file-pdf-o "></i><?php  $rest = substr( $r['EmpfileN'], 0, +5);

                                       echo $rest;  ?></a></div>



                                       <!-- The Modal -->

                                         <div class="modal" id="myModaldd<?php echo $r[0]; ?>">

                                           <div class="modal-dialog mdl-files">

                                             <div class="modal-content">

                                            

                                            

                                                <div class="modal-header">

                                                     <h4 class="modal-title"><?php echo $r['EmpfileN']; ?></h4>

                                                     <button type="button" class="close" data-dismiss="modal">&times;</button>

                                                   </div>

                                               <!-- Modal body -->

                                               <div class="modal-body">

                                                 <embed src="<?php echo $r['EmpProFPath']  ?>" type="application/pdf"   height="700px" width="100%">

                                               </div>

                                              

                                             <!-- Modal footer -->

                                             <div class="modal-footer">

                                               <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>

                                             </div>

                                            

                                           </div>

                                         </div>

                                       </div>



                                    <?php

                                      }

                                   

                                      }

                                      ?>



                                       <br>

                                       <br>



                                   </div>

                                 </div>

                               </div>

                             

                           </div>

                         </div>

                 </div>

                 <div class="col-lg-8">

                   <?php 

                   if(file_exists($res['EmpPPAth']))

                    {

       

                      $fp= $res['EmpPPAth'];

                    }



                    else

                    {

                      if ($res['EmpGender']=="Male"){

                              $fp= "assets/images/profiles/man_d.jpg";

                      }else{

                               $fp= "assets/images/profiles/woman_d.jpg";

                      }

              

                    }



                    ?>

                    <img id="prof-img" style="display: none;" src="<?php echo $fp; ?>">

                     <h3 class="emp-name" id="emp-name"> <?php echo $profile; ?>  <a style="color: red;" data-toggle="modal" data-target="#myModal" href="#" ><!-- <i data-toggle="tooltip" data-placement="right" title="Send Message" style="outline: none; font-size: 15px; vertical-align: middle; cursor: pointer;"  class="fa fa-comment-o" href="#"></i> --></a>

                    

                      <button class="btn btn-info thisprint btnprint" id="btnprint" onclick='printthisDiv();'><i class="fa fa-print" aria-hidden="true"></i></button>

                 

                       <a id="Updateinfo" href="UpdateEmployeeInfo?sid=<?php echo $res['EmpID']; ?>" class="btn btn-danger btnupdate" title="Update Contact Information" data-dismiss="modal"><i class="fa fa-pencil-square" aria-hidden="true"></i></a>

                    <?php

                        if ($_SESSION['UserType']!=3){

                      ?>

                         <a  href="#" id="pdf-filename" data-toggle="modal" data-target="#myModaldd<?php echo $res['EmpID']; ?>" style="color:white;" class="btn btn-success btnresetpass" title="Reset Password" data-dismiss="modal"><i class="fa fa-key" aria-hidden="true"></i></a>

                      <?php

                    }

                    ?>





                   <!-- The Modal -->

                  <div class="modal mdlsc" id="myModaldd<?php echo $res['EmpID']; ?>" >

                    <div class="modal-dialog">

                      <div class="modal-content">

                      

                        <!-- Modal Header --> 

                        <div class="modal-header" style="padding: 7px 8px;">

                          <button type="button" class="close" data-dismiss="modal">&times;</button>

                        </div>

                        

                        <!-- Modal body -->

                        <div class="modal-body">  

                          <div class="alert alert-danger">

                              Reset Password of Mr. <?php echo $profile; ?>   to Default.               

                          </div>

                          <button id="<?php echo $res['EmpID']; ?>" class="btn btn-success btnyespass">Yes</button>

                          <button class="btn btn-danger"  class="close" data-dismiss="modal">No</button>

                        </div>

                        

                        <!-- Modal footer -->

                     

                        

                      </div>

                    </div>

                  </div>

                  <!-- modal end -->  



                     </h3>



                     <h6 class="emp-pos" id="emp-pos"><?php echo $res['PositionDesc']; ?> </h6>

                     <h6 class="emp-company" id="emp-company"><?php echo $res['CompanyDesc']; ?></h6>

                     <h6 class="emp-company" id="emp-company2"><?php echo $res['AgencyName']; ?></h6>



                     



                     <br>

                     <h6 class="cinfo-title">Contact Information</h6>

                       <hr class="cinfohr">

                     <div class="row">

                      

                       <div class="col-lg-8 p-info">

                         <table class="tbld">

                           <thead>

                             <tr class="ci-det"><th class="info-t">Address:</th><th> <?php echo $res['EmpAddress1'] . " " . $res['EmpAddDis'] . " " . $res['EmpAddCity']. " " . $res['EmpAddProv']. " " . $res['EmpAddZip']. " " . $res['EmpAddCountry'] ?></th></tr>

                             <tr class="ci-det"><th class="info-t">Date of Birth:</th><th><?php echo $res['EmpDOB']; ?></th></tr>

                             <tr class="ci-det"><th class="info-t">Age:</th><th><?php echo date_diff(date_create($dob), date_create('today'))->y, "\n". " Years Old"; ?></th></tr>

                             <tr class="ci-det"><th class="info-t">Gender:</th><th><?php echo $res['EmpGender']; ?></th></tr>

                             <tr class="ci-det"><th class="info-t">Civil Status:</th><th> <?php echo $res['EmpCS']; ?></th></tr>

                             <tr class="ci-det"><th class="info-t">Email:</th><th><?php echo $res['EmpEmail']; ?> </th></tr>

                             <tr class="ci-det"><th class="info-t">Home No:</th><th><?php echo $res['EmpMobile'] ;?> </th></tr>

                             <tr class="ci-det"><th class="info-t">Contact No:</th><th><?php echo $res['EmpPhone']; ?> </th></tr>

                             <tr class="ci-det"><th class="info-t">Citizenship:</th><th> <?php echo $res['EmpCitezen']; ?></th></tr>

                             <tr class="ci-det"><th class="info-t">Religion:</th><th><?php echo $res['EmpReligion']; ?></th></tr>

                             

                           </thead>

                         </table>

                       </div>  

                       <div class="col-lg-4"></div>

                     </div>

<br>

            









                       <div class="row">

                         <div class="col-md-12 pd-none">

                              <div class="emp-details">

                               <h4 class="title dropdwn-title " >Employment Status<i class="fa fa-angle-down toggle-btn"></i></h4>

                               <hr>

                               <div class="row tbl-hide">

                                 <div class="col-lg-8 pd-none">

                                   

                                       <table class="tbld">

                                         <thead>



                                           <tr class="ci-det"><th class="info-t">Employee ID:</th><th><?php echo $res['EmpID']; ?> </th></tr>

                                           <tr class="ci-det"><th class="info-t">HMO Number:</th><th><?php echo $res['EmpHMONumber']; ?></th></tr>

                                           <tr class="ci-det"><th class="info-t">HMO Provider:</th><th><?php echo $res['HMO_PROVIDER']; ?></th></tr>

                                           <tr class="ci-det"><th class="info-t">Status:</th><th><?php echo $res['StatusEmpDesc']; ?> </th></tr>

                                           <tr class="ci-det"><th class="info-t">Classification:</th><th><?php echo $res['EmpStatDesc']; ?></th></tr>

                                           <tr class="ci-det"><th class="info-t">Department:</th><th><?php echo $res['DepartmentDesc']; ?> </th></tr>

                                           <tr class="ci-det"><th class="info-t">Job Level:</th><th><?php echo $res['jobLevelDesc']; ?></th></tr>

                                           <tr class="ci-det"><th class="info-t">Position:</th><th><?php echo $res['PositionDesc']; ?></th></tr>

                                           <tr class="ci-det"><th class="info-t">Immediate:</th><th><?php echo $ISname;?></th></tr>

                                           <tr class="ci-det"><th class="info-t">Date Hired:</th><th><?php echo $res['EmpDateHired']; ?></th></tr>

                                           <tr class="ci-det"><th class="info-t">Date Resigned:</th><th><?php echo $res['EmpDateResigned']; ?></th></tr>

                                   

                                            <tr class="ci-det"><th class="info-t">Salary Details:</th><th>  <i onclick="fnctshow()" id="bsc-show" class="fa fa-angle-down bsc-show" aria-hidden="true"></i></th></tr>

                                            <tr class="ci-det bsc-hide" ><th class="info-t">Basic:</th><th><?php echo number_format($res['EmpBasic']); ?>.00</th></tr>

                                           <tr class="ci-det bsc-hide1"><th class="info-t">Allowance:</th><th><?php echo number_format($res['EmpAllowance']); ?>.00</th></tr>

                                           <tr class="ci-det bsc-hide2"><th class="info-t">Hourly Rate:</th><th><?php echo number_format($res['EmphRate']); ?>.00</th></tr>

                                       

                                         

                                          

                                         </thead>

                                       </table>

                                

                                 </div>

     <br>     <br>     <br>

                                 <div class="col-lg-4"></div>



                               </div>

                                              <!-- educational background -->

                      <h6 id="cinfo-title-fd" class="cinfo-title">Educational Background</h6>

                      <hr class="cinfohr">

                      <div class="row">

                      

                       <div class="col-lg-12 p-info" >

                        <table class="table table-responsive">

                           <thead>

                            <tr><th></th>

                            <th>School</th>

                            <th>Year Started</th>

                            <th>Year End</th>

                            <th>School Address</th></tr>

                           

                           </thead>

                           <tbody>

                             

                         <?php



                          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);

                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                          $statement = $pdo->prepare("select * from empeducationalbackground where Program = 'Primary' and EmpID=:id");

                          $statement->bindParam(':id' , $q);

                          $statement->execute(); 

                          $row = $statement->fetch();

                          ?>

                              <tr>

                              <td>Primary</td>

                              <td><?php echo $row['Name_of_School']; ?></td>

                              <td><?php echo $row['Year_Started']; ?></td>

                              <td><?php echo $row['Year_End']; ?></td>

                              <td><?php echo $row['School_Address']; ?></td>

                              </tr>

                           <?php

                           $statement = $pdo->prepare("select * from empeducationalbackground where Program = 'Secondary' and EmpID=:id");

                          $statement->bindParam(':id' , $q);

                          $statement->execute(); 

                          $row = $statement->fetch();

                          ?>

                              <tr>

                              <td>Secondary</td>

                              <td><?php echo $row['Name_of_School']; ?></td>

                              <td><?php echo $row['Year_Started']; ?></td>

                              <td><?php echo $row['Year_End']; ?></td>

                              <td><?php echo $row['School_Address']; ?></td>

                              </tr> 

                            <?php

                            $statement = $pdo->prepare("select * from empeducationalbackground where Program = 'Tertiary' and EmpID=:id");

                          $statement->bindParam(':id' , $q);

                          $statement->execute(); 

                          $row = $statement->fetch();

                          ?>

                              <tr>

                              <td>Tertiary</td>

                              <td><?php echo $row['Name_of_School']; ?></td>

                              <td><?php echo $row['Year_Started']; ?></td>

                              <td><?php echo $row['Year_End']; ?></td>

                              <td><?php echo $row['School_Address']; ?></td>

                              </tr>

                           </tbody>

                         </table>

                       </div> 

                       <br>

                       <br> 

                       <div class="col-lg-4"></div>

                     </div> 

                               <!-- end  -->

                                      <h6 id="cinfo-title-fd" class="cinfo-title">Family Details</h6>

                       <hr class="cinfohr">

                     <div class="row">

                      

                       <div class="col-lg-12 p-info">

                         <table class="table table-responsive">

                           <thead>

                            <tr><th>Name</th>

                            <th>Address</th>

                            <th>Relationship</th>

                            <th>ICE</th>

                            <th>Contact Number</th></tr>

                           

                           </thead>

                           <tbody>

                         <?php

                         $b=$_GET["q"];

                          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);

                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                          $statement = $pdo->prepare("select * from fdetails where FDetID = :id");

                          $statement->bindParam(':id' , $b);

                          $statement->execute(); 



                          while ($row = $statement->fetch()){

                          ?>

                        

                             <tr>

                              <td><?php echo $row['FName']; ?></td>

                              <td><?php echo $row['FAdd']; ?></td>

                              <td><?php echo $row['FRel']; ?></td>

                              <td><?php echo $row['FICE']; ?></td>

                              <td><?php echo $row['FContact']; ?></td>

                              </tr>

                              <?php }?>

                            

                           </tbody>

                         </table>

                       </div> 

                       <br>

                       <br> 

                       <div class="col-lg-4"></div>

                     </div> 



                       <h6 class="cinfo-title d-none">Work Schedules</h6>

                         <hr class="cinfohr d-none">

                         <div class="row d-none">

                          

                           <div class="col-lg-12 p-info">

                                <table class="table table-responsive">

                                   <thead>

                                    <tr><th>Monday</th>

                                    <th>Tuesday</th>

                                    <th>Wednesday</th>

                                    <th>Thusday</th>

                                    <th>Friday</th>

                                    <th>Saturday</th>

                                    <th>Sunday</th>

                                  </tr>

                                   

                                   </thead>

                                   <tbody>

                                    <tr>

                                     

                                        <?php

                                          $sqlsc="select * from workdays left join workschedule on workdays.SchedTime=workschedule.WorkSchedID where empid='$q'";

                                          $scqry=mysqli_query($con,$sqlsc);

                                         

                                          $scnrow=mysqli_num_rows($scqry);



                                while ( $scres=mysqli_fetch_array($scqry)) {

                                ?>

                                    <td>

                                      <?php echo $scres['TimeFrom'] . "-" . $scres['TimeTo']; ?>  

                                      </td>
                                      

                                <?php

                                }

                                        ?>



                                        

                              <?php  

                          



                          ?>

                                      <td>Rest Day</td>

                                    </tr>

                                   </tbody>

                                 </table>

                           </div>

                         </div>









                            <div class="emp-details e201Hide" id="e201Hide1">

                       <h4 class="title dropdwn-title" >Compliance Document Data<i class="fa fa-angle-down toggle-btn"></i></h4>

                        <hr>

                       <div class="aaaa">

                        <table class="table">

                           <thead>

                             <tr class="cdd-tr"><th class="det-f">Passport Number <i style="outline: none;" class="fa fa-question-circle" href="#" role="button" data-trigger="focus" tabindex="0" data-toggle="popover" data-html="true" title="Details"  data-content="Expiry Date: <br/> " <?php echo $res['EmpPPNo']; ?> "Issuing Authority:" <?php echo $res['EmpPPNo']; ?>></i> </th><th class="com_number"><?php echo $res['EmpPPNo']; ?></th></tr>

                             <tr class="cdd-tr"><th class="det-f">PAG-IBIG:</th><th class="com_number"><?php echo $res['EmpPINo']; ?></th></tr>

                             <tr class="cdd-tr"><th class="det-f">PHILHEALTH:</th><th class="com_number"><?php echo $res['EmpPHNo']; ?></th></tr>

                             <tr class="cdd-tr"><th class="det-f">SSS Number:</th><th class="com_number"><?php echo $res['EmpSSS']; ?></th></tr>

                             <tr class="cdd-tr"><th class="det-f">TIN:</th><th class="com_number"><?php echo $res['EmpTIN']; ?></th></tr>                                       

                             <tr class="cdd-tr"><th class="det-f">UMID:</th><th class="com_number"><?php echo $res['EmpUMIDNo']; ?></th></tr>

                

                           </thead>

                         </table>

                         </div>

                   </div>

                      <!-- end  -->

                       <div class="emp-details e201Hide">

                       <h4 class="title dropdwn-title" >Job Description<i class="fa fa-angle-down toggle-btn"></i></h4>

                        <hr>

                       <div class="aaaa">

                        <table class="table">

                           <thead id="jdview">

                              <?php

                                            $res12=mysqli_query($con,"select * from jobdescription inner join empjobdesc on jobdescription.JD_ID=empjobdesc.JID where empjobdesc.EmpID='" . $res['EmpID'] . "'");

                                            while ($row3=mysqli_fetch_array($res12)) {

                                          ?>

                                           

                                              <tr class="cdd-tr"><th class="det-f"><?php echo $row3["JDescription"]; ?></th></tr>

                                          <?php

                                            }

                                          ?>

                        

                           </thead>



                             

                         </table>

                         </div>

                   </div>



                      <!-- end  -->

                       <div id="ed-to-hide" class="emp-details ed-st e201Hide">

                       <h4 class="title dropdwn-title">Employment Details<i class="fa fa-angle-down toggle-btn"></i></h4>

                        <hr>

                         <div class="aaaa">

                        <table class="table">

                           <thead>

                             <tr class="pos"><th><i class="fa fa-briefcase"></i> <?php echo $res['PositionDesc']; ?>/<i class="current-prev">Current</i></th></tr>

                          

                            <tr class="info-t"><th><?php  echo $res['DepartmentDesc']; ?></th></tr>

                             <tr class="info-t"><th><?php echo $res['EmpDateHired']; ?></th></tr>



                           </thead>

                           <thead>

                             <tr class="pos"><th><i class="fa fa-briefcase"></i> <?php echo $res['EmpPP']; ?>/<i class="current-prev">Previous</i></th></tr>

                             <tr class="info-t"><th><?php echo $res['EmpPPPos']; ?></th></tr>

                            <tr class="info-t"><th><?php echo $res['EmpPPDept']; ?></th></tr>

                             <tr class="info-t"><th><?php echo $res['EmpPPSD']; ?></th></tr>



                           </thead>

                         </table>

                         </div>

                     </div>

                      <!-- end  -->

                           </div>

                         </div>

                      

                       </div>

                   </div>

             </div>

         </div>

     </div>



       <!-- The Modal

                   -->      <div class="modal" id="newjobd">

                          <div class="modal-dialog modal-dialog-centered">

                            <div class="modal-content">

                            

                            

                               <div class="modal-header">

                        

                                    <button type="button" class="close" data-dismiss="modal">&times;</button>

                                  </div>

                              <!-- Modal body -->

                              <div class="modal-body">

                                <form>

                                     <div class="form-group">

                                        <label for="email">Employee Job Description</label>

                                        <div class="EmpListJD" id="EmpListJD" style="height: 114px;overflow-y: scroll;">

                                            <?php

                                            $res12=mysqli_query($con,"select * from jobdescription inner join empjobdesc on jobdescription.JD_ID=empjobdesc.JID where empjobdesc.EmpID='" . $res['EmpID'] . "'");

                                            while ($row3=mysqli_fetch_array($res12)) {

                                          ?>

                                              <a  class="btn"><?php echo $row3["JDescription"]; ?> <i class="fa fa-times" id="<?php echo $row3['EJID']; ?>" aria-hidden="true"></i></a>

                                          <?php

                                            }

                                          ?>

                                         

                                          

                                        </div>



                                      <label for="email">List of Job Description</label>

                                       <input type="text" style="display: inline-block;width: 90%;" placeholder="Search Job Description" name="sjdesc" class="form-control txtsjdesc">

                                        <div class="ListJD" id="ListJD" style="height: 114px;overflow-y: scroll;">

                                          <?php

                                            $res13=mysqli_query($con,"select * from jobdescription order by JDescription asc");

                                            while ($row=mysqli_fetch_array($res13)) {

                                          ?>

                                          <a  class="btn"><?php echo $row[1]; ?> <i class="fa fa-check-circle" id="<?php echo $row[0]; ?>" aria-hidden="true"></i></a>

                                          <?php

                                            }

                                          ?>



                                          

                                        </div>



                     <?php if ($_SESSION['UserType']!=3){

                      ?>

                                        <input type="text" style="display: inline-block;width: 90%;" placeholder="New Job Description" name="newjd" class="form-control txtnewjd"><button type="button" id="addnewJD" class="btn btn-success" style="margin-top: -5px;">+</button>

                                      <?php } ?>

                                      </div>

                                    <!--   <button type="submit" class="btn btn-success">Apply Changes</button> -->

                                </form>

                              </div>

                              

                            <!-- Modal footer -->

                            <div class="modal-footer">

                             

                            </div>

                            

                          </div>

                        </div>

                      </div>



     <?php } ?>