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
  
     <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Memorandum Generator"; } ?></title> 
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
    <script type="text/javascript" src="assets/js/memo.js"></script>

    <style>
        @media print 
        {
            #bodyprint{
                text-align: justify;
                text-justify: inter-word;
                white-space: pre-line; 
            }
            @page
            {
                size:  auto;   
            } 
         .p{
             font-size:14px;
            
             
         }
         p{
             font-size:14px;
            
             
         }
         /* #printMemoId{
            margin-right:5%;
            float:right;
         } */

         #forprint{
             width:100%;
             
         }
      
         body {
           
            height:100%;
            font-family: Tahoma; 
           
            }

            label {
                font-weight: normal !important;
                font-size:14px;
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
                    <h4 >Memorandum Generator</h4>
                    <br>
                    <div class="row " >
                            <div class="col-lg-4 col-lg-6" style="z-index:1000">
                                <div class="row"  >
                                    <div class="col-lg-8">
                                    
                                        <div class="form-group">
                                            
                                            <label for="">Search memo:</label>
                                            <input type="text" class="form-control" placeholder="Search memo.." id="searchWord">
                                            <div class="dv-livesearch" style="position: absolute;     width: 100%;padding-right: 40px;">
 
                                             </div>
                                        </div>  
                                        
                                    </div>
                                </div>
                            </div>
                           
                            <div class="col-lg-12 col-lg-6">
                                <div class="row"  >
                                    <div class="col-lg-8">
                                    
                                        <div class="form-group">
                                        <hr>
                                            <label for="">Memo_ID:</label>
                                            <label id="memoid" for="">20201-006</label>
                                        </div>  
                                        
                                    </div>
                                </div>
                            </div>
                    
                       
                           <div class="col-lg-12 col-md-12" >
                               <div class="row">
                                   
                                   <div class="col-lg-4">
                                        <form>
                                            <div class="form-group">
                                                    <label for="exampleInputEmail1">To:</label>
                                                    <input type="text" class="form-control" id="to"  placeholder="Enter to">
                                                    </div>
                                                <div class="form-group">
                                                    <label for="exampleInputPassword1">From:</label>
                                                    <input type="text" class="form-control" id="from" placeholder="Enter from">
                                                </div>
                                 
                                            </form>

                                    </div>
                                    <div class="col-lg-4">
                                        <form>
                                            <div class="form-group">
                                                    <label for="exampleInputEmail1">Memo Date:</label>
                                                    <input type="text"  disabled class="form-control" id="datememo"   ></input>
                                                    </div>
                                                <div class="form-group">
                                                    <label for="exampleInputPassword1">Subject:</label>
                                                    <input type="text" class="form-control" id="subject" placeholder="Enter Subject">
                                                </div>
                                 
                                            </form>

                                    </div>
                                    
                                </div>
                           
                            </div>
                        </div> 
                            <div class="row">
                                <div class="col-lg-12" >
                                    <div class="row">
                                        <div class="col-lg-8">
                                        <hr>
                                            <div class="form-group">
                                                        <label for="exampleInputEmail1">Body:</label>
                                                        <textarea name="body" class="form-control" id="body" cols="30" rows="20"></textarea>
                                            </div>  
                                            <div  id="result" class="alert alert-success" style="display:none"></div>
                                            <button class="btn btn-primary printmemo"> <i class="fa fa-print"></i> Save and Print</button>
                                            <button class="btn btn-primary reprint" style="display:none"> <i class="fa fa-print"></i> Re-Print</button>
                                            <!-- <button  class="btn btn-primary clearform"> <i class="fa fa-close"></i> Clear Form</button> -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                                                             
                    </div>
                </div>
    </div>

    <div id="forprint">
        <div class="col-lg-12 subtitle" style="display:none">
            
            <h5 class="thisdate"> </h5>

           
                <div class="row">
                    <div class="col-lg-12">
                            <div class="row">
                                <div class="col lg-6">
                                <label>MEMORANDOM </label>
                                </div>
                                <div class="col lg-6">
                                <label  for="" id="printMemoId"></label>
                                </div>
                            </div>
                        
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12">
                    <br>
                          
                        <div class="row">
                          
                            <div class="col lg-6">
                                <label for="">To:</label> <label for="" style="padding-left:30px;" id="printto">All Personnel wedobpo.com</label>
                            </div>
                            <br>
                            <div class="col lg-6">
                            <label for="">Date:</label> <label for="" style="padding-left:30px;" id="printmemodate"></label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col lg-6">
                            <label for="">From:</label> <label for="" style="padding-left:15px;" id="printfrom"></label>
                            </div>
                            <div class="col lg-6">
                            <label for="">Subject:</label> <label for="" style="padding-left:15px;" id="printsubject"></label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12">
                       
                    </div>
                </div>

                 <hr>  
                 <div class="row">
                    <div class="col-lg-12">
                            <div class="row">
                                <div class="col lg-12">
                                    <br>
                                    <br>
                                <div id="bodyprint">body here </div>
                                </div>
                                
                            </div>
                        
                         
                    </div>
                </div>

            
        </div>
    </div>      

</div>

</body>
</html>