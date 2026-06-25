<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
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
  date_default_timezone_set("Asia/Manila"); 
?>
<!DOCTYPE html>
<html>
<head>
  
    <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Payroll"; } ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- <link rel="stylesheet" href="assets/css/font-awesome.min.css"> -->
    <!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/script-home.js"></script>
    <script type="text/javascript" src="assets/js/scheduler.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    
</head>
<style>
  html body{
		font-family: Tahoma !important;
	}
    .formlabel{
        color:red !important;
        display:none;
    }
    .formlabelu{
        color:red !important;
        display:none;
    }
</style>
<body>
    <?php  include 'includes/header.php'; ?>
    <div class="w-container">
        <div class="row">       
            <div class="col-lg-3"></div>
                <div class="col-lg-9 module-content">

                <h4 >Schedulling Module</h4>
                    <div class="row">
                    
                        <div class="col-lg-12">
                                <button type="button" id="newschedreg" class="btn btn-primary" data-toggle="modal" data-target="#scheduleradd">+Employee Schedule</button>
                            </div> 
                            
                        <div class="col-lg-6">
                            <div class="form-group">
                            <br>
                                <label for="exampleInputEmail1">Employee Name</label>
                                <form action="" autocomplete="off">
                                <input type="text" class="form-control" id="name" aria-describedby="name" placeholder="Employee Name">
                                </form>
                                
                                <!-- <small id="nameHelp" class="form-text text-muted">We'll never share your email with anyone else.</small> -->
                            </div>
                        </div>                   
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <table class="table">
                                <thead class="thead-light">
                                    <tr>
                                
                                    <th scope="col">Name</th>
                                    <th scope="col">From</th>
                                    <th scope="col">To</th>
                                    <th scope="col">Action</th>
                                    
                                    </tr>
                                </thead>
                                <tbody id="scheduler">
                                    
                                </tbody>
                                </table>
                        </div>                   
                    </div>
                </div>
        </div>          
            
            <div class="modal" id="scheduleradd" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Employee Schedulling</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <form name="entersched">
                                <div class="col-lg-12">
                                    <div class="row">
                                        <div class="form-group">
                                            <label for="Employees">Employees: <a style="color:red">*</a></label>
                                            <select id="employee" class="form-control">
                                            </select>
                                            <small id="lblemployee" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                           
                                    </div>
                                </div>
                            </div>

                            <div class="row"> 
                        
                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Employees">Effectivity From: <a style="color:red">*</a></label>
                                            <input type="date" class="form-control" id="dfrom" >
                                            <small id="lbldfrom" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Employees">Effectivity To: <a style="color:red">*</a></label>
                                            <input type="date" class="form-control" id="dto" >
                                            <small id="lbldto" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                            </div>

                            <small id="lblpos" class="form-text text-muted formlabel">Schedule!</small>
                            <div class="row"> 
                        
                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Monday">Monday: <a style="color:red">*</a></label>
                                            <select id="Monday" class="form-control">
                                            </select>
                                            <small id="lblmonday" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Tuesday">Tuesday: <a style="color:red">*</a></label>
                                            <select id="Tuesday" class="form-control">
                                            </select>
                                            <small id="lbltuesday" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Wednesday">Wednesday: <a style="color:red">*</a></label>
                                            <select id="Wednesday" class="form-control">
                                            </select>
                                            <small id="lblwednesday" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Thursday">Thursday: <a style="color:red">*</a></label>
                                            <select id="Thursday" class="form-control">
                                            </select>
                                            <small id="lblthursday" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Friday">Friday: <a style="color:red">*</a></label>
                                            <select id="Friday" class="form-control">
                                            </select>
                                            <small id="lblfriday" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Saturday">Saturday: <a style="color:red">*</a></label>
                                            <select id="Saturday" class="form-control">
                                            </select>
                                            <small id="lblsaturday" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Sunday">Sunday: <a style="color:red">*</a></label>
                                            <select id="Sunday" class="form-control">
                                            </select>
                                            <small id="lblsunday" class="form-text text-muted formlabel">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>
                            </div>
                            </form>    
                        
                        
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="save">Submit</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div> 
                    </div>
                </div>
            </div>
           
            <div class="modal" id="viewsched" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Employee Schedule</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-lg-12">
                                    <table class="table">
                                        <thead class="thead-light">
                                            <tr>                                       
                                            <th scope="col">Day</th>
                                            <th scope="col">Time</th>  
                                            <th scope="col">Update</th>                                        
                                            </tr>
                                        </thead>
                                        <tbody id="daytime">
                                            
                                        </tbody>
                                    </table>
                                </div>               
                            </div>   
                            <div class="modal-footer">
                                <!-- <button type="button" class="btn btn-primary" id="save">Submit</button> -->
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal" id="updatesched" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Employee Schedule</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <form name="entersched">
                                <div class="col-lg-12">
                                    <div class="row">
                                        <div class="form-group">
                                            <label for="Employees">Employees: <a style="color:red">*</a></label>
                                            <select id="employeeu" class="form-control">
                                            </select>
                                            <small id="lblemployeeu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                           
                                    </div>
                                </div>
                            </div>

                            <div class="row"> 
                        
                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Employees">Effectivity From: <a style="color:red">*</a></label>
                                            <input type="date" class="form-control" id="dfromu" >
                                            <small id="lbldfromu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Employees">Effectivity To: <a style="color:red">*</a></label>
                                            <input type="date" class="form-control" id="dtou" >
                                            <small id="lbldtou" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                            </div>

                            <small id="lblpos" class="form-text text-muted formlabel">Schedule!</small>
                            <div class="row"> 
                        
                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Monday">Monday: <a style="color:red">*</a></label>
                                            <select id="Mondayu" class="form-control">
                                            </select>
                                            <small id="lblmondayu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Tuesday">Tuesday: <a style="color:red">*</a></label>
                                            <select id="Tuesdayu" class="form-control">
                                            </select>
                                            <small id="lbltuesdayu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Wednesday">Wednesday: <a style="color:red">*</a></label>
                                            <select id="Wednesdayu" class="form-control">
                                            </select>
                                            <small id="lblwednesdayu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Thursday">Thursday: <a style="color:red">*</a></label>
                                            <select id="Thursdayu" class="form-control">
                                            </select>
                                            <small id="lblthursdayu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Friday">Friday: <a style="color:red">*</a></label>
                                            <select id="Fridayu" class="form-control">
                                            </select>
                                            <small id="lblfridayu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                              
                                        <div class="form-group">
                                            <label for="Saturday">Saturday: <a style="color:red">*</a></label>
                                            <select id="Saturdayu" class="form-control">
                                            </select>
                                            <small id="lblsaturdayu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>

                                <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Sunday">Sunday: <a style="color:red">*</a></label>
                                            <select id="Sundayu" class="form-control">
                                            </select>
                                            <small id="lblsundayu" class="form-text text-muted formlabelu">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>
                            </div>
                            </form>    
                                               
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="save">Submit</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div> 
                    </div>
                </div>
            </div>

               
            <div class="modal" id="updatetime" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Update Time</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                            <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="Friday">Time <a style="color:red">*</a></label>
                                            <select id="timedata" class="form-control">
                                            </select>
                                            <small id="lbltimeupdate" class="form-text text-muted formlabelupdate">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>               
                            </div>   
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="updatetimesched">Submit</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal" id="updateeffective" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Update Effectivity</h4>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                            <div class="col-lg-6">                               
                                    <div class="row">                                                                          
                                        <div class="form-group">
                                            <label for="dfrom">Date From:<a style="color:red">*</a></label>
                                            <input type="date" class="form-control" id="efdfrom" >
                                            <small id="lblefdfrom" class="form-text text-muted formlabelupdate">This is a required Field!</small>
                                        </div> 

                                        <div class="form-group">
                                            <label for="dto">Date To:<a style="color:red">*</a></label>
                                            <input type="date" class="form-control" id="efdto" >
                                            <small id="lblefdto" class="form-text text-muted formlabelupdate">This is a required Field!</small>
                                        </div>                                                                             
                                    </div>                                
                                </div>               
                            </div>   
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="updateEffecDate1">Submit</button>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

    </div>        
</body>
</html>
