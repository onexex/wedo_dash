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
  
     <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Payee Registry"; } ?></title> 
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script> 
	  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
	  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	  <script type="text/javascript" src="assets/js/script.js"></script>
	  <script src="assets/js/script-reports.js"></script>
	  <script type="text/javascript" src="assets/js/script-modules.js"></script>
	  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
  <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <script type="text/javascript" src="assets/js/payeeregistry.js"></script>

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
       			 <h4 >Payee Registry</h4>
                    <br>
                    <div class="row">
                        <div class="col-lg-12">                          
                            <div class="row">
                                
                                 <div class="col-lg-4" style="padding-top:10px">
                                        <label for="">Enter Payee:</label>
                                        <input id="payee" type="text" class="form-control" placeholder="Enter Payee">
                                    </div>
                                    <div class="col-lg-4" style="padding-top:10px">
                                        <label for=""> Enter Account number:</label>
                                        <input  id="can" type="text" class="form-control" placeholder="Account Number">
                                    </div>
                                    <div class="col-lg-4" style="padding-top:10px">
                                        <br>
                                        <button id="store" class="btn btn-primary"> <i class="fa fa-plus"></i></button>
                                    </div>

                                    <div class="col-lg-9" style="padding-top:10px">
                                        <br>
                                        <div  id="result" class="alert alert-success" style="display:none"></div>
                                    </div>
                                    
                            </div>
                           
                            <div class="row">
                                <div class="col-lg-9" style="padding-top:20px">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                           
                                            <th scope="col">Payee</th>
                                            <th scope="col">CAN</th>
                                            <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tblpayeereg">
                                           
                                           
                                        </tbody>
                                    </table> 
                                </div>                           
                            </div>
                        </div>
                    </div>

                    <!-- closing tag above div -->
            </div>
        </div>
</div>

</body>
</html>