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
  
     <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Certificate"; } ?></title> 
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
    <script type="text/javascript" src="assets/js/coe.js"></script>

    <style>
      html body{
		font-family: Tahoma !important;
	}
        @media print 
        {
            @page
            {
                size:  auto;   
            } 
         .p{
             font-size:14px;
         }
         body {
            margin:0;
            padding:0;
            height:100%;
            }
            .letter-head {
                position:absolute;
                bottom:0;
                width:100%;
                
                }
         .setcolor{
             color:red !important;
             font-size:14px;
         }

            label {
                font-weight: normal !important;
            }
            .pmargin{
                margin-top:25px !important;
            }
            .pmargin1{
                margin-top:50px !important;
            }
          
        }

        #forprint label {
            font-weight: normal
        }
    </style>
    
</head>
<body>
<?php  include 'includes/header.php'; ?>
<div class="w-container">
    <div class="row">
            <div class="col-lg-3"></div>
                <div class="col-lg-9 module-content " >
                    <h4 >Certificate of Employment</h4>
                        <br>
                        <div class="row">
                            <div class="col-lg-3">
                                <label for="">Select Employee:</label>
                                <select class="form-control" id="employeelist" ></select>
                            </div>                                                 
                        </div>
                        
                        <div class="row"> 
                            
                            <div class="col-lg-3">
                            <br>
                                <button class="btn btn-primary printthis" > Print</button>
                            </div>  
                        </div>

                        <div class="row" id="forprint" style="height:100%"> 
                            <div class="col-lg-12">
                                <br>
                                <label class="p pmargin" for="">To whom it may concern;</label>
                                
                            </div>
                            <div class="col-lg-12">
                                <br>
                                <p class="p pmargin" for="" >
                                    This is to certify that <label for="" class="fullname"></label>
                                    is presently connected with WeDo BPO Inc.</p>
                                    In <label for="" class="selfintro"></label> capacity as <label for="" class="position"></label> <label for="" class="Salutation"></label> <label class="lastname" for=""></label> has been connected with this company since
                                     <label for="" class="datehired"></label>. <br> <br> <label for="" class="Salutation"> </label> <label for="" class="lastname"> </label> receives a gross monthly compensation of Pesos <label for="" class="basic"></label>, 
                                    exclusive of overtime pay, bonuses and other salary-related fringe benefits, of which <label for="" class="heshe"></label> entitled to receive.
                                </p>
                                <p class="p">Should you have clarifications, please call 02.84704131 or 63.917.7240123 anytime during office hours.</p>
                                <p class="p">This certification is issued for at the request of <label for="" class="fullname">Ramon Gemana</label> for whatever legal purpose it may serve.</p>
                                <p class="p">Issued on <label for=""><?php echo date("M-d-Y");?></label>, at Pasig City, Philippines.</p>
                                <br>
                                <p class="p">Thank you.</p>
                            </div>

                            <div class="col-lg-12 pmargin1">
                                <p>
                                <img src="assets/images/sign/ogm.png" style="width:150px" class="pmargin1"  valign="middle" vspace="6" hspace="6"/> <br> 
                             
                                </p>
                                <p class="p" >Jose Modesto A. Ferrer</p>
                                <p class="p">General Manager - WeDo BPO Inc.</p>
                            </div>

                            <div class="col-lg-12 letter-head" >
                                <p >
                                <img src="assets/images/logos/WeDo.png" style="width:100px"  valign="middle" vspace="6" hspace="6"/> <br> 
                          
                             </p>
                                <p class="setcolor" >1901 Antel Global Corporate Center #3 J. Vargas Ave. Ortigas Business District, Pasig City 1605 </p>
          
                            </div>
                           

                        </div>
                    
            </div>
    </div>
</div>

</body>
</html>