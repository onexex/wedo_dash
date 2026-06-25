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
  
     <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Check Register"; } ?></title> 
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
    <script type="text/javascript" src="assets/js/check.js"></script>
    <script type="text/javascript" src="assets/js/booklet.js"></script>
    <script type="text/javascript" src="assets/js/jquery.dialog.js"></script>
    
	  <link rel="stylesheet" type="text/css" href="assets/css/jquery.dialog.css">

    <style>
        @media print 
        {
            @page
            {
                size:  auto;   
            } 
         .p{
             font-size:14px;
            
             
         }
         #forprint{
             width:100%;
             
         }
         .l1{
             text-align:right;
             padding-right:25px;
         }
         .l2b{
             text-align:right;
             padding-right:25px;
         }
         .l2a, .l3{
             text-align:left;
             padding-left:25px;
             line-height: 2.5;
         }
         body {
           
            height:100%;
            font-family: Tahoma; 
            }

            label {
                font-weight: normal !important;
            }
           
          
        }
        body { 
	font-family: Tahoma; 
}
       
    </style>
    
</head>
<body>
<?php  include 'includes/header.php'; ?>
<div class="w-container">
    <div class="row">
            <div class="col-lg-3"></div>
                <div class="col-lg-9 module-content " >
                    <h4 >Check Writer</h4>
                    <br>
                        <br>
                    <div class="row">                       
                            <div class="col-lg-12 sd">
                                
                                    <div class="col-lg-4">
                                      <!-- <button id="test" class="btn btn-primary ">test</button> -->
                                          <label for=""> Bank Info:</label>
                                        <!-- <input type="text" class="form-control" id="bankinfo" placeholder="Bankinfo"/> -->
                                        <select name="" id="bankinfo" class="form-control">

                                        </select>
                                        <br>
                                        <label for=""> Check Number:</label>
                                        
                                        
                                        <input type="number" class="form-control" id="checknumber" readonly placeholder="Check Number"/>
                                        <br>
                                        <label for=""> Payee:</label>
                                        <input type="text" class="form-control" id="payee" placeholder="Payee"/>
                                        <div class="dv-livesearch" style="position: absolute;     width: 100%;padding-right: 40px;">
                                          
                                        </div>
                                        <br>
                                        <label for=""> Date of Check:</label>

                                        <input type="date" class="form-control" id="checkdate"/>
                                       
                                        <br>
                                        <label for=""> Check Amount:</label>

                                        <input type="number" class="form-control" id="checkamount" placeholder="Check Amount"/>
                                        <br>
                                        <label for="">Remarks:</label>

                                        <textarea type="text" class="form-control" id="remark" placeholder="Remarks"></textarea>
                                        <br>
                                        <button class="btn btn-primary save"><i class="fa fa-save">  </i> Save and Confirm</button>
                                        <br>
                                        <br>
                                        <div  id="result" class="alert alert-success" style="display:none"></div>
                                    </div>
                                                                                      
                                    <div class="col-lg-8">
                                                                        
                                        <label for=""> For Print:</label>
                                        <br>

                                        <div class="row bg-light h-500" id="forprint" >
                                           
                                            <div class="col-lg-12" > 
                                                <p class="l1"></p>
                                            <br>
                                                <label class=" l2a"></label>
                                               
                                            
                                                <label class="l2b float-right"></label>
                                               
                                            <br>
                                                <p class="l3"></p>
                                                <br>
                                                <br>
                                                <br>
                                                <p class="l4"></p>
                                               
                                            </div>
                                                                                        
                                        </div>
                                        <button class="btn btn-primary printthis"><i class="fa fa-print">  </i> Print</button>
                                    </div>
                                    
                               
                                
                            </div>
                            
                    </div>   
                    <hr>
                   

                        <div class="col-lg-12">
                            <br>                  
                            <caption class="font-weight-bold">Check Register History</caption>
                            <br>
                            <br>
                                <div class="row">
                                    <div class="form-group" style="margin-left:10px;">
                                        <label for="datefrom">From:</label>
                                        <input type="date" class="form-control" id="dfrom" aria-describedby="from" >
                                    
                                    </div>
                                    <div class="form-group" style="margin-left:10px;">
                                        <label for="dateto">To:</label>
                                        <input type="date" class="form-control" id="dto" aria-describedby="to" >
                
                                    </div>
                                    <div class="form-group" style="margin-left:10px;margin-top:27px;">
                        
                                        <button type="button" class="btn btn-primary btn-sm" id="refresh" aria-describedby="refresh"><i class="fa fa-refresh"></i></button>
                
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group col-lg-3 col-md-3 "  >
                                        <label for="payee">Payee Filter:</label>
                                        <select name="payeefilter" class="form-control" id="payeefilter"></select>
                                    
                                    </div>
                                    <div class="form-group col-lg-3 col-md-3" >
                                        <label for="dateto">Bank Filter:</label>
                                        <select name="bankfilter" class="form-control" id="bankfilter"></select>
                
                                    </div>
                                    <div class="form-group col-lg-2 col-md-2" >
                                        <label for="dateto">Order By:</label>
                                        <select name="orderfilter" class="form-control" id="orderfilter">
                                            <option value="bankinfo">       Bank Name</option>
                                            <option value="checkamount">    Check Amount</option>
                                            <option value="checkno">        Check Number</option>
                                            <option value="checkdate">      Check Date</option>
                                            <option value="payee">          Payee</option>
                                            
                                        </select>
                
                                    </div>

                                    <div class="form-group col-lg-2 col-md-2" >
                                        <label for="dateto">Sort By:</label>
                                        <select name="sortby" class="form-control" id="sortby">
                                            <option value="asc">       ASC</option>
                                            <option value="desc">      DESC</option>
                                           
                                        </select>
                
                                    </div>
                                
                                </div>
                            
                        </div>
                        <div id="forprinting">
                        <div class="col-md-6 subtitle" style="display:none">
                            <h4 class="pyrlfilt">Check History</h4>
                            <h5 class="thisdate"> </h5>
                           
                        </div>
                                <table class="table">
                            
                            
                                    <thead class="thead-light">
                                        <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Payee</th>
                                        <th scope="col">Bank</th>
                                        <th scope="col">Check Number</th>
                                        <th scope="col">Check Amount</th>
                                        <th scope="col">Check Date</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="actions">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historydata">

                                    
                                    </tbody>
                                </table>
                        </div>
                     
                    

                    <button class="btn btn-primary" id="print"> <i class="fa fa-print"></i> Print</button>
                    <br>
                    <br>
                    
            </div>
    </div>
</div>

</body>
</html>