<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<!DOCTYPE html>
  <html>
  <head>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon"> 
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <!--  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-e201.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <script src="assets/js/getlocationcoors.js" deffer></script>
    <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo $_SESSION['CompanyName']; } ?></title>
    <script type="text/javascript"> 
      $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip(); 
      });
      $(document).ready(function(){
        $('[data-toggle="popover"]').popover();   
      });
    </script>
    <style type="text/css">
      html body{
        font-family: Tahoma !important;
      }
      .EmpListJD a{
        display: block;
        text-align: left;
        cursor: auto;
      }
      .EmpListJD a i{
        float: right;
        color: red;
        cursor: pointer;
      }
      .ListJD a i{
        float: right;
        color: green;
        cursor: pointer;
      }
      .ListJD a{
        display: block;
        text-align: left;
        cursor: auto;
      }
      .wd-search{
        width: 200px;
        -webkit-transition: width 1s; /* For Safari 3.1 to 6.0 */
        transition: width 1s;
      }
      .wd-search:hover{
        width: 400px;
      }
      .wd-search:active{
        width: 400px;
      }
      .wd-search:focus{
        width: 400px;
      }
      .wd-search:visited{
        width: 400px;
      }
      .search-box{
        border-radius: 4px !important;
        width: 100% !important;
        -webkit-transition: width 1s; /* For Safari 3.1 to 6.0 */
        transition: width 1s;
      }
      /*  .search-box:hover{
      width: 400px !important;
      }*/
      .dv-livesearch{
        -webkit-transition: width 1s; /* For Safari 3.1 to 6.0 */
        transition: width 1s;
      }

      .e201Hide{
        display: none;
      }
      @media screen and (max-width: 479px) {
        .wd-search{
          right: 225px;
          top: 12px;
        }
        .dv-livesearch{
          width: 100%;
        }
        .wd-search:hover{
          width: 300px;
        }
        .wd-search:active{
          width: 300px;
        }
        .wd-search:focus{
          width: 300px;
        }
        .wd-search:visited{
          width: 300px;
        }
      }
    </style>
  </head>

  <body style="background-image: none">

  <?php 
    include 'includes/header.php';

    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    
    $statement = $pdo->prepare("select * from accessrights where EmpID = :id");
    $statement->bindParam(':id' , $_SESSION['id']);
    $statement->execute(); 
    $rowbtn=$statement->fetch();

    if ($rowbtn['srch']==2) { ?>
      <div class="wd-search">
        <form action="">  
          <div class="input-group mb-3">
            <input  type="text"  class="form-control search-box" placeholder="Search">
          </div>
        </form>

        <div class="dv-livesearch">
        </div>
      </div>
    <?php } ?>

  <div id="e201" class="w-container" >
    <?php include_once ("w_conn.php");

    $q = $_SESSION['id'];
    $sql = "SELECT empdetails.Seq_ID,hmo.HMO_PROVIDER,agency.AgencyName,empdetails.EmpID,empdetails.EmpDateHired,empdetails.EmpDateResigned,empstatus.EmpStatDesc,
      workschedule.TimeFrom,workschedule.TimeTo,workdays.WDesc,companies.CompanyDesc,empdetails2.EmpBasic,
      empdetails2.EmpHRate,empdetails2.EmpAllowance,employees.EmpFN AS fn,employees.EmpLN AS ln,employees.EmpMN AS mn,
      empprofiles.EmpAddress1,empprofiles.EmpDOB,empprofiles.EmpGender,empprofiles.EmpEmail,empprofiles.EmpMobile,
      empprofiles.EmpPPNo,empprofiles.EmpPINo,empprofiles.EmpPHNo,empprofiles.EmpSSS,empprofiles.EmpTIN,
      empprofiles.EmpUMIDNo,empprofiles.EmpCitezen,empprofiles.EmpReligion,empprofiles.EmpPhone,
      empprofiles.EmpPP,empprofiles.EmpPPSD,empprofiles.EmpPPDept,empprofiles.EmpPPPos,empprofiles.EmpCS,
      empprofiles.EmpHMONumber,empdetails.EmpUN,
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

    try{
      $result=mysqli_query($con,$sql);
    }catch(Exception $e) {
      echo 'Message: ';
    }
    $res=mysqli_fetch_array($result); 
    $cnt=mysqli_num_rows($result);
    if($cnt>0){
      // $ISID = $res['EmpISID'];
      // $_SESSION['rid']=$ISID;
      $eid=$res['EmpID'];
      $ISname= $res['fn'] . " " .  $res['mn'] . " " . $res['ln'];
      // $isid=$res['id'];
      $profile= ucfirst($res['EmpFN']) . " " .  ucfirst($res['EmpMN']) . " " . ucfirst($res['EmpLN']) . " " . ucfirst($res['EmpSuffix']);
      $dob= $res['EmpDOB'];
    }
    $wschedule=$res['TimeFrom'] . " " . $res['TimeTo'];
    if ($cnt == 0){   ?>
    <?php }
    else{ ?>
    <div class="row">
        <div class="col-lg-3"></div>
        <!-- website content -->
        
        <div class="col-lg-9" id="divtop">
            <div class="row">
                <!--<button class="btn btn-danger bb">test</button>-->
                <?php
                $srch="select * from messageheader where SenderID='$_SESSION[id]' or RecieverID='$_SESSION[id]' order by dateMessage desc";
                $qry=mysqli_query($con, $srch);
                $mnum=0;
                $cnt= mysqli_num_rows ($qry);
                while ($rw=mysqli_fetch_array($qry)){
                    if ($rw['SenderID']==$_SESSION['id']){
                        $sndid=$rw['RecieverID'];
                        $sql = "SELECT DISTINCT MHID FROM messages WHERE MHID='$rw[MHID]' AND SenderID='$sndid' AND Status=1";
                        $empq=mysqli_query($con, $sql);
                        $rw2=mysqli_fetch_array($empq);
                        $n2=mysqli_num_rows($empq);
                        $mnum= $mnum+$n2;
                    }else{
                        $sndid=$rw['SenderID'];
                        $sql = "SELECT DISTINCT MHID FROM messages WHERE MHID='$rw[MHID]' AND SenderID='$sndid' AND Status=1";
                        $empq=mysqli_query($con, $sql);
                        $rw2=mysqli_fetch_array($empq);
                        $n2=mysqli_num_rows($empq);
                        $mnum= $mnum+$n2;
                    }
                }
        ?>
                <div class="msg" id="msg"><a href="messages">Messages <i><?php echo $mnum; ?></i></a></div>
                    <div class="col-lg-4" id="cdc-hide">
                        <div class="prof-img" style="background-image: 
                            <?php 
                            if(file_exists($res['EmpPPAth'])){
                                echo  "url('" .$res['EmpPPAth']. "')";
                            }else{
                                if($res['EmpGender']=="Male"){
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
                                    <tr class="cdd-tr"><th class="det-f">Passport Number <i style="outline: none;" class="fa fa-question-circle" href="#" role="button" data-trigger="focus" tabindex="0" data-toggle="popover" data-html="true" title="Details"  data-content="Expiry Date:  <?php echo $res['EmpPPED'];  echo " <br/> Issuing Authority : " . $res['EmpPPIA']; ?>  " ></i> </th><th class="com_number"><?php echo $res['EmpPPNo']; ?></th></tr>
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
                    <?php
                    }
                    ?>
                  </thead>
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
                          <div class="col-lg-2">
                            <a href="#" id="pdf-filename" data-toggle="modal" data-target="#myModaldd<?php echo $r[0]; ?>"><i class="fa fa-file-pdf-o "></i><?php $rest = substr( $r['EmpfileN'], 0, +5); echo $rest; ?></a>
                          </div>

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
                      <br><br>

                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-8">
  <?php 
  if(file_exists($res['EmpPPAth'])){
  $fp= $res['EmpPPAth'];
  }else{
  if ($res['EmpGender']=="Male"){
  $fp= "assets/images/profiles/man_d.jpg";
  }else{
  $fp= "assets/images/profiles/woman_d.jpg";
  }
  }?>
  <img id="prof-img" style="display: none;" src="<?php echo $fp; ?>">
  <h3 class="emp-name" id="emp-name"> <?php echo $profile; ?>  <a style="color: red;" id="sndmessage" ><i data-toggle="tooltip" data-placement="right" title="Send Message to Immediate" style="outline: none; font-size: 15px; vertical-align: middle; cursor: pointer;"  class="fa fa-comment-o" href="#"></i></a>  
  <?php if ($rowbtn['updte']==2){ ?>
  <button class="btn btn-info   btnprint" id="btnprint" onclick='printDiv();'><i class="fa fa-print" aria-hidden="true"></i></button>
  <?php } ?>
  <?php if ($rowbtn['updte']==2){ ?>
  <a id="Updateinfo" href="UpdateEmployeeInfo?sid=<?php echo $res['EmpID']; ?>" class="btn btn-danger btnupdate" title="Update Contact Information" data-dismiss="modal"><i class="fa fa-pencil-square" aria-hidden="true"></i></a>
  <?php }
  if ($_SESSION['UserType']!=3){ ?>
  <a  href="#" id="changepasskey" data-toggle="modal" data-target="#myModaldd<?php echo $res['EmpID']; ?>" style="color:white;" class="btn btn-success btnresetpass" title="Reset Password" data-dismiss="modal"><i class="fa fa-key" aria-hidden="true"></i></a>
  <?php  } ?>


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
  Reset Password of <?php echo $profile; ?>   to Default.               
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
  <h6 class="cinfo-title" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Contact Information</h6>
  <hr class="cinfohr">

  <div class="row">
  <div class="col-lg-8 p-info" id="p-info">
  <table class="tbld">
  <thead>
  <tr class="ci-det"><th class="info-t">Address:</th><th> <?php echo $res['EmpAddress1'] . " " . $res['EmpAddDis'] . " " . $res['EmpAddCity']. " " . $res['EmpAddProv']. " " . $res['EmpAddZip']. " " . $res['EmpAddCountry'] ?></th></tr>
  <tr class="ci-det"><th class="info-t">Date of Birth:</th><th><?php echo $res['EmpDOB']; ?></th></tr>
  <tr class="ci-det"><th class="info-t">Age:</th><th><?php echo date_diff(date_create($dob), date_create('today'))->y, "\n". " Years Old"; ?></th></tr>
  <tr class="ci-det"><th class="info-t">Gender:</th><th><?php echo $res['EmpGender']; ?></th></tr>
  <tr class="ci-det"><th class="info-t">Civil Status:</th><th> <?php echo $res['EmpCS']; ?></th></tr>
  <tr class="ci-det"><th class="info-t">Email:</th><th><?php echo $res['EmpEmail']; ?></th></tr>
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
  <div class="col-md-12 pd-none" id="pd-none">
  <div class="emp-details" id="pd-none2">
  <h4 class="title dropdwn-title " style="color:<?php echo  $_SESSION['CompanyColor']; ?>;" >Employment Status<i class="fa fa-angle-down toggle-btn"></i></h4>
  <hr>
  <div class="row tbl-hide">
  <div class="col-lg-8 pd-none">
  <table class="tbld">
  <thead>
  <tr class="ci-det"><th class="info-t">Employee Username:</th><th class="empidjd"><?php echo $res['EmpUN']; ?> </th></tr>
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
  <tr class="ci-det"><th class="info-t">Salary Details:</th><th> <i class="fa fa-angle-down bsc-show" aria-hidden="true"></i></th></tr>
  <tr class="ci-det bsc-hide" ><th class="info-t">Basic:</th><th><?php echo number_format($res['EmpBasic']); ?>.00</th></tr>
  <tr class="ci-det bsc-hide"><th class="info-t">Allowance:</th><th><?php echo number_format($res['EmpAllowance']); ?>.00</th></tr>
  <tr class="ci-det bsc-hide"><th class="info-t">Hourly Rate:</th><th><?php echo number_format($res['EmphRate']); ?>.00</th></tr>
  </thead>
  </table>
  </div>
  <br>     
  <br>     
  <br>
  <div class="col-lg-4"></div>
  </div>
  <!-- educational background -->
  <h6 id="cinfo-title-fd" class="cinfo-title" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Educational Background</h6>
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
  <h6 id="cinfo-title-fd" class="cinfo-title" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">ICE</h6>
  <hr class="cinfohr">
  <div class="row">

  <div class="col-lg-12 p-info" >
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
  $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $statement = $pdo->prepare("select * from fdetails where FDetID = :id");
  $statement->bindParam(':id' , $q);
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

  <h6 class="cinfo-title d-none" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Work Schedules</h6>
  <hr class="cinfohr">
  <div class="row d-none">

  <div class="col-lg-12 p-info">
  <table class="table table-responsive">
  <thead>
  <tr>
  <th>Monday</th>
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
  $sqlsc="select * from workdays left join workschedule on workdays.SchedTime=workschedule.WorkSchedID where  empid='$q'";
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

  </tr>
  </tbody>
  </table>
  </div>
  </div>


  <div class="emp-details e201Hide" id="e201Hide1">
  <h4 class="title dropdwn-title" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Compliance Document Data<i class="fa fa-angle-down toggle-btn"></i></h4>
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
  <h4 class="title dropdwn-title" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Job Description<i class="fa fa-angle-down toggle-btn"></i></h4>
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
  <h4 class="title dropdwn-title" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Employment Details<i class="fa fa-angle-down toggle-btn"></i></h4>
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


  <?php } ?>


  </div>
  <!-- sending message to immediate or hr  -->
  <div class="com-container">
  <div class="com-header" style="<?php echo "background-color: " . $_SESSION['CompanyColor']; ?>">
  <h5><?php echo $ISname;?></h5>
  <i class="fa fa-window-close" id="sndm" aria-hidden="true"></i>
  </div>
  <div class="com-messages" id="com-messages">

  <?php

  $result = mysqli_query($con,"select * from messageheader where SenderID='$_SESSION[id]' and RecieverID='$ISID'");
  $res = mysqli_fetch_array($result); 
  $cnt= mysqli_num_rows ($result);
  if ($cnt>0){

  $result1 = mysqli_query($con,"select * from messages where MHID='$res[1]' order by DateSent asc");
  $sql="UPDATE messages SET Status=2 where MHID='$res[1]'";
  $sqlq=mysqli_query($con,$sql);
  while($rs=mysqli_fetch_array($result1)){
  if ($rs['SenderID']==$_SESSION['id']){

  $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
  $rw3=mysqli_fetch_array($rs3);
  ?>

  <div class="s-message" >
  <i><?php 
  $dd = date("FdY", strtotime($rs['DateSent']));  
  $td = date("FdY");
  if ($dd==$td){
  $newDate = date("h:i:s A", strtotime($rs['DateSent']));
  $newDate = "Today " . $newDate;
  }else{
  $newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
  }
  echo $newDate;
  ?></i><p><?php echo $rs['Message']; ?></p>


  </div>
  <?php 
  }
  else{
  $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
  $rw3=mysqli_fetch_array($rs3);
  ?>
  <div class="r-message">


  <p><?php echo $rs['Message']; ?></p><i><?php 

  $dd = date("FdY", strtotime($rs['DateSent'])); 
  $td = date("FdY");
  if ($dd==$td){
  $newDate = date("h:i:s A", strtotime($rs['DateSent']));
  $newDate = "Today " . $newDate;
  }else{
  $newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
  }
  echo $newDate;
  ?></i> 

  </div>
  <?php   
  }

  } 
  }else{
  $result = mysqli_query($con,"select * from messageheader where RecieverID='$_SESSION[id]' and SenderID='$ISID'");
  $res = mysqli_fetch_array($result); 
  $cnt= mysqli_num_rows ($result);
  if ($cnt>0){
  $result1 = mysqli_query($con,"select * from messages where MHID='$res[1]' order by DateSent asc");

  $sql="UPDATE messages SET Status=2 where MHID='$res[1]'";
  $sqlq=mysqli_query($con,$sql);


  while($rs=mysqli_fetch_array($result1)){
  if ($rs['SenderID']==$_SESSION['id']){
  $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
  $rw3=mysqli_fetch_array($rs3);
  ?>

  <div class="s-message">
  <i><?php 

  $dd = date("FdY", strtotime($rs['DateSent'])); 
  $td = date("FdY");
  if ($dd==$td){
  $newDate = date("h:i:s A", strtotime($rs['DateSent']));
  $newDate = "Today " . $newDate;
  }else{
  $newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
  }
  echo $newDate;
  ?></i><p><?php echo $rs['Message']; ?></p>

  </div>
  <?php 
  }
  else{
  $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
  $rw3=mysqli_fetch_array($rs3);
  ?>
  <div class="r-message">

  <p><?php echo $rs['Message']; ?></p><i><?php 

  $dd = date("FdY", strtotime($rs['DateSent'])); 
  $td = date("FdY");
  if ($dd==$td){
  $newDate = date("h:i:s A", strtotime($rs['DateSent']));
  $newDate = "Today " . $newDate;
  }else{
  $newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
  }
  echo $newDate;
  ?></i> 
  </div>
  <?php   
  }
  }
  }else{

  echo "Message First";
  }
  }
  ?>

  </div>
  <div class="com-send-m">
  <input type="text" name="mssg" id="mssg" class="form-control">
  <button class="btn" id="btnsend"><i class="fa fa-paper-plane" aria-hidden="true"></i></button>
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
  <label for="email" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Employee Job Description</label>
  <div class="EmpListJD" id="EmpListJD" style="height: 114px;overflow-y: scroll;">
  <?php
  $res12=mysqli_query($con,"select * from jobdescription inner join empjobdesc on jobdescription.JD_ID=empjobdesc.JID where empjobdesc.EmpID='" . $eid . "'");
  while ($row3=mysqli_fetch_array($res12)) {
  ?>
  <a  class="btn"><?php echo $row3["JDescription"]; ?> <i class="fa fa-times" id="<?php echo $row3['EJID']; ?>" aria-hidden="true"></i></a>
  <?php
  }
  ?>


  </div>

  <label for="email" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">List of Job Description</label>
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

  </body>
</html>