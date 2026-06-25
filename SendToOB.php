<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
   if (isset($_SESSION['id']) && $_SESSION['id']!="0"){

     }
  else{ 
    if(!isset($_COOKIE["WeDoID"])) {

        header ('location: login'); 
    }else{
        if(!isset($_COOKIE["WeDoID"])) {
          session_destroy();
          header ('location: login'); 
        }else{
              try{
              include 'w_conn.php';
              $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                 }
              catch(PDOException $e)
                 {
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
                else{

                }
              }
            }
    }

  }
?>
<?php
    include 'w_conn.php';
      date_default_timezone_set("Asia/Manila"); 
?>

<!DOCTYPE html>
<html>
<head>

    <title>Send to Official Business Trip</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
 <!--  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous"> -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
  <script type="text/javascript" src="assets/js/script.js"></script>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="assets/js/script-reports.js"></script>
  <script type="text/javascript" src="assets/js/script-modules.js"></script>
    <script type="text/javascript" src="assets/js/administrative.js"></script>

  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
  <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
<style>  html body{
		font-family: Tahoma !important;
	}</style>
</head>
<body style="background-image: none">

  <?php 
    include 'includes/header.php';

  ?>
  <div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
               <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Send to Official Business Trip</h4>
                      <button type="button" class="btn btn-primary" id="eventListener" data-toggle="modal" data-target="#newform">+ Send to OB Trip Form</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog" style="max-width: 1000px !important;">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Send to OB Trip Form</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" class="frmsendtoob" id="frmsendtoob">
                  <div class="row">
                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Personnel Name:</label>
                          <select class="form-control" name="empidob" id="empname">
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
                                   if ($_SESSION['UserType']==1){
                                     $statement = $pdo->prepare("select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpStatusID='1' AND empdetails.EmpID<>'admin' order by EmpLN");
                                     $statement->execute();
                                   }else if ($_SESSION['UserType']==2){
                                       $statement = $pdo->prepare("select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpStatusID='1' AND EmpISID='$_SESSION[id]' or employees.EmpID='$_SESSION[id]' order by EmpLN");
                                       $statement->execute();
                                   }
                                   
                              
                                while ($row2 = $statement->fetch()){
                                    if ($row2['EmpID']=="WeDoinc-016" || $row2['EmpID']=="WeDoinc-017" || $row2['EmpID']=="WeDoinc-018" || $row2['EmpID']=="WeDoinc-019" || $row2['EmpID']=="WeDoinc-014"){

                                  }else{
                              ?>
                                <option value="<?php echo $row2['EmpID']; ?>"><?php echo $row2['EmpLN'] . ", " . $row2['EmpFN'] ; ?></option>
                              <?php    
                                }   
                                }
                              ?>
    
                          </select>
                        </div>
                        <div id="empinfo">
                         <div class="form-group">
                          <label >Company Name:</label>
                          <input type="text" disabled class="form-control" id="empcomp">
                        </div>
                         <div class="form-group">
                          <label >Department:</label>
                          <input type="text" disabled class="form-control" id="empdep">
                        </div>
                         <div class="form-group">
                          <label >Designation:</label>
                          <input type="text" disabled class="form-control" id="empdesig">
                        </div>
                      </div>

                      </div>
                      <div class="col-lg-6">
                       
                           <div class="form-group">
                          <label >Filing Date:</label>
                          <input type="text" disabled value="<?php echo date('F d , Y'); ?>" class="form-control" >
                      </div>
                       <div class="form-group">
                          <label >OB Date From:</label>
                          <input type="date" value="<?php echo date("Y-m-d");?>"  class="form-control" name="obdf" id="obdf">
                      </div>
                       <div class="form-group">
                          <label >OB Date To:</label>
                          <input type="date" value="<?php echo date("Y-m-d");?>"  class="form-control" name="obdt" id="obdt">
                      </div>
                      
                     
                      </div>

                    </div>
                    
              
              
                    <div class="row ">
                        <div class="col-lg-4"><h6>ITINERARY</h6>
                          <div class="row">
                              <div class="col-lg-6 a">
                                <div class="form-group">
                                    <label >From:</label>
                                    <input type="text" readonly  value="Tektite"  class="form-control" name="itfrom" id="itfrom">
                                </div>
                            </div>
                            <div class="col-lg-6 a">
                                <div class="form-group">
                                    <label >To:</label>
                                    <input type="text"  class="form-control" name="itto" id="itto">
                                </div>
                            </div>
                           
                          </div>
                        </div>                      
                        <div class="col-lg-4"><h6>PURPOSE</h6>
                                <div class="form-group">
                            <textarea class="form-control" rows="2" name="emppurpose" id="emppurpose"></textarea>
                          </div>
                             <div class="form-group">
                                    <label >Cash Advance Amount:</label>
                                    <input type="number" name="ca" value="0.00" placeholder="0.00"  class="form-control" >
                                </div>
                        </div>                      
                        <div class="col-lg-4"><h6>INCLUSIVE TIME</h6>
                              <div class="row">
                            <div class="col-lg-6 a">
                                <div class="form-group">
                                    <label >Departure:</label>
                                    <input type="time" name="depart" value="08:00" id="depart"  class="form-control" >
                                </div>
                            </div>
                             <div class="col-lg-6 a">
                                <div class="form-group">
                                    <label >Return:</label>
                                    <input type="time" name="return" value="19:00" id="return"  class="form-control" >
                                </div>
                            </div>
                            <div class="col-lg-12">
                              <div class="form-group">
                                    <label >Purpose:</label>
                                    <input type="text"  class="form-control" name="capurpose">
                                </div>
                            </div>
                          </div>

                        </div>                      
                                       
                    </div>
                           <button type="button" class="btn btn-success btn-block btnsendtoob">Submit</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
          </div>
  <br>
  <br>
 <div class="dtpar">
      <h5>Date Parameters:</h5>
      <label>From:</label>
      <input type="date" class="form-control" id="dtp1"   value="<?php echo date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));?>" >
      <label>To:</label>
      <input type="date" class="form-control"  id="dtp2"  value="<?php echo date("Y-m-d");?>">
      <button class="btn " id="sob" type="button"><img src="assets/images/refreshicon.png" data-toggle="tooltip" data-placement="right" title="Refresh" width="25px"></button>
      </div>
  <div class="container-formatss">
  <h5 class="title">Send to OB History</h5>


</div>

  <div class="container-format">       
  <table class="table table-striped">
     <thead>
      <tr >
        <th >Filing Date</th>
        <th >Name</th>
        <th >Date From</th>
        <th >Date To</th>
        <th >Itinerary To</th>
        <th >Purpose</th>
        <th >Cash Advance</th>
        <th >Status</th>
      </tr>
    </thead>
      <tbody id="tbsob">
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
                         $dt1= date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));
                      $dt2=date("Y-m-d");
                      if ($_SESSION['UserType']==1){
                         $statement = $pdo->prepare("SELECT * from employees inner join  obs as a on employees.EmpID=a.EmpID  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where  OBDateFrom between :dt1 and :dt2 and OBStatus=4 order by OBDateFrom desc");
                      }else{
                         $statement = $pdo->prepare("SELECT * from employees INNER JOIN empdetails on employees.EmpID=empdetails.EmpID inner join  obs as a on empdetails.EmpID=a.EmpID 
                                  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where (empdetails.EmpID=:id or empdetails.EmpISID=:id) and OBDateFrom between :dt1 and :dt2 and (a.OBType<>7 or a.OBType<>5 or a.OBType<>3) order by OBDateFrom desc");
                  $statement->bindParam(':id' , $id);
                      }
                 

                  $statement->bindParam(':dt1' , $dt1);
                  $statement->bindParam(':dt2' , $dt2);
                  $statement->execute();
              
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php echo date("F j, Y", strtotime($row21['OBFD'])); ?></td>  
                   <td><?php echo $row21['EmpLN']; ?></td>
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateFrom'])); ?></td>
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateTo'])); ?></td>
                   <td><?php echo $row21['OBITo']; ?></td>
                   <td><?php echo $row21['OBPurpose']; ?></td>
                   <td><?php echo $row21['OBCAAmt']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                
                  </tr> 
              <?php 
              }

              ?>


    </tbody>
  </table>
</div>
<br>
<br>
<br>

         

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
                    <h1 style="font-size: 25px; padding-left: 10px;color:red;"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></h1>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>
              
              <!-- Modal body -->
              <div class="modal-body">
                
                <div class="alert alert-danger">
                    <h5></h5>
                </div>
              </div>
              
              <!-- Modal footer -->
           
              
            </div>
          </div>
        </div>
        <!-- modal end --> 
        
        
         <!-- The Modal -->
        <div class="modal" id="modalSuccess">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                    <h1 style="font-size: 25px; padding-left: 10px;color:green;"><i class="fa fa-check" aria-hidden="true"></i></h1>
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
        
          <!-- The Modal -->
        <div class="modal" id="modalSuccess">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                    <h1 style="font-size: 25px; padding-left: 10px;color:green;"><i class="fa fa-check" aria-hidden="true"></i></h1>
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
