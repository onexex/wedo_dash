<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
    include 'w_conn.php';
      date_default_timezone_set("Asia/Manila"); 
?>

<!DOCTYPE html>
<html>
<head>
  
     <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Booklet Registry"; } ?></title> 
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	  <script type="text/javascript" src="assets/js/script.js"></script>
	  <script src="assets/js/script-reports.js"></script>
	  <script type="text/javascript" src="assets/js/script-modules.js"></script>
	  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
	  <link rel="stylesheet" type="text/css" href="assets/css/jquery.dialog.css">
  <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <script type="text/javascript" src="assets/js/booklet.js"></script>
    <script type="text/javascript" src="assets/js/jquery.dialog.js"></script>

<style>



    .formlabel{
        color:red !important;
        display:none;
    }
    .overlay{
        display: none;
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: 999;
        background: rgba(255,255,255,0.8) url("assets/images/01.gif") center no-repeat;
    }
    
    /* Turn off scrollbar when body element has the loading class */
    body.loading{
        overflow: hidden;   
    }
    /* Make spinner image visible when body element has the loading class */
    body.loading .overlay{
        display: block;
    }
</style>
</head>
<body>
<?php  include 'includes/header.php'; ?>
<div class="w-container">
        <div class="row">
            <div class="col-lg-3"></div>
            <div class="col-lg-9 module-content">
       			 <h4 >Booklet Registry</h4>
                    <br>
                    <div class="row">
                        <div class="col-lg-12">                          
                            <div class="row">
                                
                                 <div class="col-lg-4" style="padding-top:10px">
                                        <label for="">Enter Bank Name:</label>
                                        <input id="bankname" type="text" class="form-control" placeholder="Enter Bank Name">
                                    </div>
          
                                    <div class="col-lg-4" style="padding-top:10px">
                                        <br>
                                        <button id="store" class="btn btn-primary"> <i class="fa fa-plus"></i></button>
                                    </div>

                                    <div class="col-lg-9" style="padding-top:10px">
                                        <br>
                                        <div  id="result"  style="display:none"></div>
                                    </div>
                                    
                            </div>
                           
                            <div class="row">
                                <div class="col-lg-6" style="padding-top:20px">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                           
                                            <th width="70%" scope="col">Bank</th>
                                            <th scope="col">Booklet #</th>
                                            <th scope="col">Action </th>
                                            </tr>
                                        </thead>
                                        <tbody id="tblbankbookle">
                                        
                                           
                                        </tbody>
                                    </table> 
                                </div>                           
                            </div>
                        </div>
                    </div>
                    <!-- Modal -->
                   

                                        
                    <!-- Modal -->
                    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                        <div class="modal-header" style="background-color:red !important">
                          
                            <button  type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span style="color:white"aria-hidden="true">&times;</span>
                            </button>
                            <h4 class="modal-title" style="color:white" id="exampleModalLongTitle">Booklet Registry</h4>
                        </div>
                        <div class="modal-body">
                            <!-- start content -->
                                       
                            <div class="row">
                                
                                 <div class="col-lg-5" style="padding-top:10px">
                                        <label for="">Start </label>
                                        <input id="start" type="number" class="form-control" placeholder="Starting check #.." >
                                    </div>
                                    <div class="col-lg-5" style="padding-top:10px">
                                        <label for="">Ending</label>
                                        <input  id="end" type="number" class="form-control" placeholder="Ending check #.." >
                                    </div>
                                    <div class="col-lg-2" style="padding-top:10px">
                                        <br>
                                        <button id="addnew" class="btn btn-primary"> <i class="fa fa-plus"></i></button>
                                    </div>

                                    <div class="col-lg-9" style="padding-top:10px">
                                        <br>
                                        <div  id="result1" style="display:none"></div>
                                    </div>
                                    
                            </div>
                           
                            <div class="row">
                                <div class="col-lg-12" style="padding-top:20px">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                           
                                            <th scope="col">Start</th>
                                            <th scope="col">Ending</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tblpayeereg">
                                           
                                           
                                        </tbody>
                                    </table> 
                                </div>                           
                            </div>
               
                            <!-- end content -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <!-- <button type="button" class="btn btn-primary">Save changes</button> -->
                        </div>
                        </div>
                    </div>
                    </div>
                    <!-- closing tag above div -->
            </div>
        </div>


        <!-- Button trigger modal -->

       
</div>

</body>
</html>