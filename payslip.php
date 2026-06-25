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
  
     <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Payslip Management System"; } ?></title> 
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
    <script type="text/javascript" src="assets/js/payslip.js"></script>

    <script>
         $('document').ready(function(){
            var myStyle = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />';
        $('#btnprint').click(function(){

         var pdates=$("#pdates").val();  
          if(pdates==="") {
              alert("Please specify Pay date.");
              return false;
          }

          $("#toprint1").clone().appendTo(".printme");   
          $("#toprint1").hide();  
          $("body").addClass("printing"); 
          w=window.open(null, 'Print_Page', 'scrollbars=no');
         
     
         // $(label).css("font-size", "5px");
         $("label").css("font-size" , "8px");
         $("p").css("font-size" , "8px");
         $("h5").css("font-size" , "8px");
          //preparation part
          $(".inforight").css("float", "left");
          //$(".inforight").css("width", "300px");
          $(".inforight").css("padding-left", "45px");
         $(".inforight").css("width", "45%");
          //payee part
          $(".infoleft").css("padding-left", "25px");
          $(".infoleft").css("float", "left");
         $(".infoleft").css("width", "55%");
          //particulars part
          $(".particularammount").css("float", "left");
          $(".particularammount").css("width", "50%");
   
          //particular border
        //  $(".border").css("vertical-align", "center");
          $(".border").css("border-right", "solid");
          $(".border").css("border-width", "0.1px");

            //payment part
            $(".amountcol1").css("float", "left");
            $(".amountcol1").css("width", "50%");
            $(".amountcol1").css("padding-top", "25px");
            $(".amountcol1").css("padding-left", "25px");
            $(".amountcol").css("padding-top", "45px");

            // journal part
            $(".journal").css("float", "left");
            $(".journal").css("padding-left", "10px");
            $(".journal").css("width", "15%");
            $(".journal1").css("float", "left");
            $(".journal1").css("width", "35%");
            $(".journal2").css("float", "left");
            $(".journal2").css("width", "25%");
            $(".journal3").css("float", "left");
            $(".journal3").css("width", "25%");
            //journal list
            
            $(".journallast").css("float", "left");
            $(".journallast").css("width", "100%");

            $(".borderleft").css("border-left", "solid");
          $(".borderleft").css("border-width", "0.1px");


          w.document.write(myStyle + jQuery(".printme").html());
          w.document.close();
          w.print();

            $("body").removeClass("printing");
            //Clear up the div.
            $(".printme").empty();
            $(".printme").hide();

            $("#toprint1").show();
       
          
        });

        function pop_print(){
          w=window.open(null, 'Print_Page', 'scrollbars=no');        
          var myStyle = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />';
          window.document.write(myStyle + jQuery('#toprint1').html());
          window.document.close();
          window.print();
      }

     
      
    });
    </script>
<style>
     @media screen {
         p.bodyText {font-family:verdana, arial, sans-serif;}
      }

      @media print {
        body {font-family:tahoma;
        margin:5%;
        }
        
      }
     

    p.paycutoff{
        font-size: 10pt;
        line-height: 10pt;
        padding-left:100px;

    }
    .amountcol{
        text-align:right;
        padding:0px;
        
    }
  html body{
		font-family: Tahoma !important;
	}
    #table1>tbody>tr>td, #table1>tbody>tr>th, #table1>tfoot>tr>td, #table1>tfoot>tr>th, #table1>thead>tr>td, #table1>thead>tr>th {
        padding:5px;
        font-size:10px;
         border: 1px solid !important;
      

    }
    .table{
        margin-bottom:0px !important;
    }
</style>
</head>
<body>
<?php  include 'includes/header.php'; ?>
<div class="w-container">
    <div class="row">
            <div class="col-lg-3"></div>
            <div class="col-lg-9" >
       			 <h4 >Payslip Module</h4>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-lg-4">
                                    <label for="">Select Employee:</label>
                                    <select class="form-control" id="employeelist" >
                                    </select>                                    
                                    <br>
                                </div>
                           </div>
                           
                           <div class="row">
                                <div class="col-lg-4">
                                    <label for="">Select Paydate:</label>
                                    <select class="form-control" id="pdates" >
                                    </select>
                                    <br>
                                    <button id="btnviewpaydata" type="button" class="btn btn-info">  <i class="fa fa-eye" ></i> View IC</button>     
                                    <button id="btnviewbe" type="button" class="btn btn-info">  <i class="fa fa-eye" ></i> View BE</button>     
                                    
                                </div>                               
                           </div>

                           <br>
                           <div  id="result1" class="alert" style="display:none"></div>
                           <hr>
                           <br>

                            <div style="display:none" id="ic"> 
                                    <button id="btnprint" type="button" class="btn btn-success"> <i class="fa fa-print" ></i> Print</button>                                 
                                    
                                    <div id="toprint1"  >
                                    <br> 
                                    
                                        <div class="col">
                                            <p style="font-size: 10pt; line-height: 10pt;" for="">WeDo BPO Inc.</p>
                                            <p style="font-size: 10pt; line-height: 10pt;" for="">Unit 1901 Antel Global Corporate Center</p>
                                            <p style="font-size: 10pt; line-height: 10pt;" for=""># 3 Julia Vargas Ave, Ortigas Business District</p>
                                            <p style="font-size: 10pt; line-height: 10pt;" for="">Pasig City 1605</p>
                                        </div>
                                    
                                        <div class="row" style="margin-top:30px; padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; padding-left:0px;">
                                                <h5 style="text-align: center; ">PAY VOUCHER</h5>
                                            </div>
                                        </div>

                                        <div class="row" style="margin-top:5px; padding-right:15px; padding-left:15px;">
                                            <div class="col-lg-12 dd" style="border-style: solid;border-width: 0.2px; padding-left:0px; ">
                                            <div class="row">
                                                <div class="col-lg-6 infoleft" style="margin-top:10px; padding-left:0px;">
                                                    
                                                            <div class="col-lg-12">
                                                                <label class="labelnameadd" style="margin-right:25px;width:45px;font-weight: normal;"  for="">Payee:</label>
                                                                <label id="name" style="font-weight: normal;"for=""> </label>
                                                            </div>

                                                            <div class="col-lg-12"  >
                                                                <label class="labelnameadd" style="margin-right:25px;width:45px;font-weight: normal;" for="">Address:</label>
                                                                <label id="address" style="font-weight: normal;"for=""> </label>
                                                            </div>                                             
                                                </div>

                                                <div class="col-lg-6 inforight" style="margin-top:10px; padding-left:0px; ">
                                                    
                                                    <div class="col-lg-12">
                                                        <label  style="margin-right:25px;width:110px;font-weight: normal;"  for="">Preparation Date:</label>
                                                        <label id="debitdate" style="font-weight: normal;"for=""> </label>
                                                    </div>

                                                    <div class="col-lg-12">
                                                        <label style="margin-right:25px;width:110px;font-weight: normal;" for="">Approval Date:</label>
                                                        <label id="releasedata" style="font-weight: normal;"for=""> </label>
                                                    </div>                                             
                                                    </div>

                                            </div>
                                            </div>
                                        </div>

                                        <div class="row" style="margin-top:5px; padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; padding-left:0px;padding-right:0px">
                                            <div class="row">

                                                <div class="col-lg-6 "  style="border-right: solid;border-width: 0.2px;"  >                                           
                                                        <div class="col-lg-12 particularammount border" >
                                                            <h5 style="text-align: center; ">PARTICULARS</h5>
                                                        </div>                                                                  
                                                </div>

                                                <div class="col-lg-6 " >                                               
                                                    <div class="col-lg-12 particularammount">
                                                            <h5 style="text-align: center; ">AMOUNT</h5>
                                                    </div>                                                                                     
                                                    </div>

                                            </div>
                                            </div>
                                        </div>

                                        
                                        <div class="row"  style="padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid; margin-bottom:0px;border-width: 0.2px;border-top:none; padding-left:0px;padding-right:0px;">
                                            <div class="row amountcolrow" >
                                                <div class="col-lg-6" style="border-right: solid;border-width: 0.2px;" >
                                                    
                                                        <div class="col-lg-12 amountcol1 border" style="padding-left:0px;">
                                                        <label  for=""> IN PAYMENT FOR:</label>
                                                        <p  class="paycutoff" id="payfor" for="">BACK-END OFFICE PAYROLL</p>
                                                        <p  class="paycutoff" id="cutoffdates" for=""> </p>
                                                        <p class="amountcol2" id="totalword" style="float:right"   for="">TOTAL</p>
                                                        </div>
                                
                                                </div>

                                                <div class="col-lg-6 " style="float:right">
                                                    <div class="col-lg-12 amountcol amountcol1"  >
                                                        <label  for="">  </label>
                                                        <p  class="paycutoff"  id="moneypayfor" for=""> -</p>
                                                        <p  class="paycutoff" id="payfor" for=""> - </p>
                                                        <p id="moneytotal" for="">-</p>

                                                    </div>                                                                                     
                                                    </div>

                                            </div>
                                            </div>
                                        </div>

                                        <div class="row" style="padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                                            <div class="row">

                                                <div class="col-lg-6 "  style="border-right: solid;border-width: 0.2px;"  >                                           
                                                        <div class="col-lg-12 particularammount border" >
                                                            <h5 style="text-align: center; ">JOURNAL ENTRY</h5>
                                                        </div>                                                                  
                                                </div>

                                                <div class="col-lg-6 " >                                               
                                                    <div class="col-lg-12 particularammount">
                                                            <h5 style="text-align: center; "></h5>
                                                    </div>                                                                                     
                                                    </div>

                                            </div>
                                            </div>
                                        </div>

                                        <div class="row" style="padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                                            <div class="row">

                                                <div class="col-lg-2 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal  border"  >
                                                            <p style="font-size: 10pt; line-height: 10pt; padding-top:10px;  text-align:left">Code</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-4 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal1 border" >
                                                            <p style="font-size: 10pt; line-height: 10pt; padding-top:10px;text-align:left">Code</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-3 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal2 border" >
                                                            <p style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:center ">Debit</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-3 "   >                                           
                                                        <div class="col-lg-12 journal3 " >
                                                            <p style="font-size: 10pt; line-height: 10pt; padding-top:10px;  text-align:center">Credit</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                
                                            </div>
                                            </div>
                                        </div>

                                        <div class="row" style="padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                                            <div class="row">

                                                <div class="col-lg-2 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal border"  >
                                                        <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">- </p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left"> -</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">- </p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">- </p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-4 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal1 border  " >
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">Service</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">Allowance</p>
                                                            <p  style="font-size: 10pt; line-height: 10pt;text-align:left">Overtime</p>
                                                            <p  style="font-size: 10pt; line-height: 10pt;text-align:left">Adjustment 1</p>
                                                            <p  style="font-size: 10pt; line-height: 10pt;text-align:left">Adjustment 2</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">Absences/Tardiness</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">SSS Payable</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">Philhealth Payable</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">Pag-Ibig Payable</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">SSS Loan Payable</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">Pag-Ibig Loans Payable</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">W/Taxes Payable</p>
                                                            <p style="font-size: 10pt; line-height: 10pt;text-align:left">CASH IN BANK</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-3 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal2 border" >
                                                            <p id="debitservice" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitallowance" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitovertime" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitadjustment1" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitadjustment2" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debittardiness" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitsss" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitphilhealth" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitpagibig" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitsssloan"  style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitpagibigloan" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debittax" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p id="debitcash" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-3 "   >                                           
                                                        <div class="col-lg-12 journal3 " >
                                                            <p  id="creditservice" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditallowance" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditovertime" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditadjustment1" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditadjustment2" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="credittardiness" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditsss" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditphilhealth" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditpagibig" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditsssloan" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditpagibigloan"style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="credittax" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                            <p  id="creditcash" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                
                                            </div>
                                            </div>
                                        </div>


                                        <div class="row" style="padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                                            <div class="row">

                                                <div class="col-lg-2 "    >                                           
                                                        <div class="col-lg-12 journal  "  >
                                                            <p style="font-size: 10pt; line-height: 10pt; padding-top:10px;  text-align:left"></p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-4 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal1 " >
                                                            <p style="font-size: 10pt; line-height: 10pt; padding-top:10px;text-align:left"></p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-3 "  style="border-right: solid;border-width: 0.2px;  "  >                                           
                                                        <div class="col-lg-12 journal2 borderleft border" >
                                                            <p id="debittotal" style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:right ">-</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                <div class="col-lg-3 "   >                                           
                                                        <div class="col-lg-12 journal3 " >
                                                            <p id="credittotal" style="font-size: 10pt; line-height: 10pt; padding-top:10px;  text-align:right">-</p>
                                                        </div>              
                                                                                                            
                                                </div>
                                                
                                            </div>
                                            </div>
                                        </div>  
                                        
                                        <div class="row" style="padding-right:15px; padding-left:15px">
                                            <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                                            <div class="row">
                                                <div class="col-lg-12 "    > 
                                                                                                
                                                        <div class="col-lg-12 journallast "  >
                                                            <label   style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Amount in Words:</label>      <label id="totaltowords"  style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left :50px;" for="">-</label> <br>
                                                            <label  style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Bank:</label>                 <label  id="bankinfo" style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left :50px;" for="">-</label> <br>
                                                            <label  style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Check Number:</label>         <label  id="checknumber"  style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left :50px;" for="">DA/CASH</label> <br>
                                                            <label  id="" style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Expense Desciption:</label>   <label id="description"  style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left: 50px;" for="">-</label> <br>
                                                        </div>              
                                                                                                            
                                                </div>
                                                                                            
                                            </div>
                                            </div>
                                            
                                        </div>


                                        <div class="row" style="padding-top:15px">
                                            <div class="col-lg-12"  >
                                            <div class="row">
                                                <div class="col-lg-12 "    > 
                                                                                                
                                                        <div class="col-lg-12 journallast "  >
                                                            <label  style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left ">System-generated payslip</label>       <br>
                                                            <label  style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left ">Approved by the Office of the General Manager</label>                 <br>
                                                            
                                                        </div>              
                                                                                                            
                                                </div>
                                                                                            
                                            </div>
                                            </div>
                                            
                                        </div>
                                    
                                        <br><br><br><br>
                                    </div>
                            </div>

                            <div style="display:none"   id="be" >
                                <button id="btnprintbe" type="button" class="btn btn-success"> <i class="fa fa-print" ></i> Print</button>                                 
                               
                                <div class="" id="printbe">
                                <br> 
                                    <div >
                                    <img src="assets/images/logos/WeDo.png" style="width:100px"  valign="middle" vspace="6" hspace="6"/> <br>
                                    </div>
                                    <div class="col" >
                                        <p style="font-size: 10pt; line-height: 10pt;" for="">WeDo BPO Inc.</p>
                                        <p style="font-size: 10pt; line-height: 10pt;" for="">Unit 1901 Antel Global Corporate Center ,# 3 Julia Vargas Ave, Ortigas Business District Pasig City 1605</p>
                                        <!-- <p style="font-size: 10pt; line-height: 10pt;" for=""># 3 Julia Vargas Ave, Ortigas Business District</p>
                                        <p style="font-size: 10pt; line-height: 10pt;" for="">Pasig City 1605</p> -->
                                    </div>
                                     <br>
                                     <br>
                                     <div class="col" >
                                        <p id="namebe" style="font-size: 10pt; line-height: 10pt;" for="">Ramon Jr. Bagasbas Gemana</p>
                                        <p id="addressbe" style="font-size: 10pt; line-height: 10pt;" for="">Block 15 Lot 4 Lessandra Homes Brgy. Kaypian CSJDM</p>
                                        
                                    </div>
                                   
                                     


                                    <div class="row">
                                        <div class="col-lg-12">
                                        <table class="table" width="100%" id="table1">
                                        <thead>
                                            <tr >
                                                <th style="border:none !important" width="20%"></th>
                                                <th style="border:none !important "width="30%"></th>
                                                <th style="border:none !important " width="10%"></th>
                                                <th style="border:none !important " width="20%"></th>
                                                <th style="border:none !important " width="20%"></th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr > 
                                                <td scope="row" width="20%">Designation</td>
                                                <td id="designation" colspan="2" id="designation"></td>
                                                <td >Pay Begin Date:</td>
                                                <td id="begindate"></td>
                                            </tr>
                                            <tr > 
                                                <td scope="row" width="20%">Position:</td>
                                                <td id="position" colspan="2" id="designation"></td>
                                                <td >Pay End Date: </td>
                                                <td id="enddate"></td>
                                            </tr>
                                            <tr style="border-bottom:solid 1px"> 
                                                <td style="border-bottom:solid 1px" scope="row" width="20%">Department:</td>
                                                <td  id="department" style="border-bottom:solid 1px" colspan="2" id="designation"></td>
                                                <td style="border-bottom:solid 1px" id="designation">Advice Date:</td>
                                                <td  id="advicedate" style="border-bottom:solid 1px" id="designation"></td>
                                            </tr>   
                                          
                                           
                                        </tbody>
                                        </table>
                                        <!-- new table -->
                                        <br>
                                        <table class="table" width="100%" id="table1">
                                        <thead>
                                            <tr >
                                                <th style="border:none !important " width="20%"></th>
                                                <th style="border:none !important " width="10%"></th>
                                                <th style="border:none !important " width="15%">EARNINGS</th>
                                                <th style="border:none !important " width="15%"></th>
                                                <th style="border:none !important " width="20%"></th>
                                                <th style="border:none !important " width="10%">TAXES</th>
                                                <th style="border:none !important " width="10%"></th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr> 
                                                <th  style="text-align:center" scope="row">Description</th>
                                                <th style="text-align:center" scope="row">Hrs</th>
                                                <th style="text-align:center" scope="row">Current</th>
                                                <th style="text-align:center" scope="row">YTD</th>
                                                <th style="text-align:center" scope="row">Description</th>
                                                <th style="text-align:center" scope="row">Current</th>
                                                <th style="text-align:center" scope="row">YTD</th>
                                            </tr>
                                            <tr > 
                                                <td scope="row">Basic Pay</td>
                                                <td scope="row"></td>
                                                <td  style="text-align: right" id="basiccurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="basicytd" scope="row">0.00</td>
                                                <td scope="row">Withholding:</td>
                                                <td style="text-align: right" id="witholdingcurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="witholdingytd" scope="row">0.00</td>
                                            </tr>
                                            <tr> 
                                                <td scope="row">Allowance</td>
                                                <td scope="row"></td>
                                                <td style="text-align: right" id="allowancecurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="allowanceytd" scope="row">0.00</td>
                                                <th scope="row"></th>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>
                                            <tr> 
                                                <td scope="row">OT/Holiday Pay</td>
                                                <td style="text-align: right" id="othrs" scope="row">0</td>
                                                <td style="text-align: right" id="otcurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="otytd" scope="row">0.00</td>
                                                <th scope="row"></th>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>
                                            <tr> 
                                                <td >Accumulated 13 Month Pay</td>
                                                <td scope="row"></td>
                                                <td style="text-align: right" id="monthpaycurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="monthpayytd" scope="row">0.00</td>
                                                <th scope="row"></th>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>
                              
                                            <t > 
                                                <th scope="row">Total</th>
                                                <td scope="row"></td>
                                                <td style="text-align: right" id="earningstotalcurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="earningstotalytd" scope="row">0.00</td>
                                                <th scope="row">Total</th>
                                                <td style="text-align: right" id="taxestotalcurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="taxestotalytd"  scope="row">0.00</td>
                                            </t>
                                          
                                        </tbody>
                                        </table>
                                         <!-- new table -->
                                         <table class="table" width="100%" id="table1" style="margin-top:0px">
                                        <thead style="display:none" >
                                            <tr >
                                                <th style="border:none !important " width="12%"></th> 
                                                <th style="border:none !important " width="7%"></th>
                                                <th style="border:none !important " width="7%"></th>
                                                <th style="border:none !important " width="10%"></th>
                                                <th style="border:none !important " width="12%"></th>
                                                <th style="border:none !important " width="7%"></th>
                                                <th style="border:none !important " width="10%"></th>
                                                <th style="border:none !important " width="12%"></th>
                                                <th style="border:none !important " width="7%"></th>
                                                <th style="border:none !important " width="10%"></th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <br>
                                            <tr > 
                                                <th style="border:none !important; text-align:center" colspan="4" scope="row">BEFORE-TAX DEDUCTIONS</th>
                                                <th style="border:none !important; text-align:center" colspan="3" scope="row">AFTER-TAX DEDUCTIONS</th>
                                                <th style="border:none !important; text-align:center" colspan="3" scope="row">EMPLOYER PAID BENEFITS</th>
                                                
                                            </tr>
                                            
                                            <tr > 
                                                <th style="text-align:center" scope="row">Description</th>
                                                <th style="text-align:center" scope="row">Hrs</th>
                                                <th style="text-align:center" scope="row">Current</th>
                                                <th style="text-align:center" scope="row">YTD</th>
                                                <th style="text-align:center" scope="row">Description</th>
                                                <th style="text-align:center" scope="row">Current</th>
                                                <th style="text-align:center" scope="row">YTD</th>
                                                <th style="text-align:center" scope="row">Description</th>
                                                <th style="text-align:center" scope="row">Current</th>
                                                <th style="text-align:center" scope="row">YTD</th>
                                            </tr>
                                            <tr  > 
                                                <td scope="row">Absences (Basic)</td>
                                                <td style="text-align: right" id="absenceshrs" scope="row">0.00</td>
                                                <td style="text-align: right" id="absencescurrent"scope="row">0.00</td>
                                                <td style="text-align: right" id="absencesytd"scope="row">0.00</td>
                                                <td scope="row">SSS Contri</td>
                                                <td style="text-align: right" id="ssscurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="sssytd" scope="row">0.00</td>
                                                <td scope="row">SSS-ER</td>
                                                <td style="text-align: right" id="sssercurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="ssserytd" scope="row">0.00</td>
                                            </tr>
                                            <tr > 
                                                <td scope="row">Management Fee</td>
                                                <td scope="row"></td>
                                                <td style="text-align: right" id="managementcurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="managementytd" scope="row">0.00</td>
                                                <td scope="row">Philhealth Contri</td>
                                                <td style="text-align: right" id="philcurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="philytd" scope="row">0.00</td>
                                                <td scope="row">Philhealth ER</td>
                                                <td style="text-align: right" id="philercurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="philerytd" scope="row">0.00</td>
                                            </tr>
                                            <tr  > 
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row">Pagibig Contri</td>
                                                <td style="text-align: right" id="picurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="piytd" scope="row">0.00</td>
                                                <td scope="row">Pagibig ER</td>
                                                <td style="text-align: right" id="piercurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="pierytd"scope="row">0.00</td>
                                            </tr>
                                            <tr  > 
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row">SSS Loan </td>
                                                <td style="text-align: right" id="sssloancurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="sssloanytd" scope="row">0.00</td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>
                                            <tr> 
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td  scope="row"></td>
                                                <td scope="row">Pagibig Loan </td>
                                                <td style="text-align: right" id="piloancurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="piloanytd" scope="row">0.00</td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>
                                            <tr  > 
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td   scope="row"></td>
                                                <td scope="row">Absences(Allowance)</td>
                                                <td style="text-align: right" id="absencesallowcurrent" scope="row">0.00</td>
                                                <td style="text-align: right" id="absencesallowytd" scope="row">0.00</td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>
                                            
                                            <tr  > 
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td   scope="row"></td>
                                                <td scope="row">Advances/Adjustment 1</td>
                                                <td style="text-align: right" id="adj1current" scope="row">0.00</td>
                                                <td style="text-align: right" id="adj1ytd" scope="row">0.00</td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>

                                            <tr  > 
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td   scope="row"></td>
                                                <td scope="row">Advances/Adjustment 2</td>
                                                <td style="text-align: right" id="adj2current" scope="row">0.00</td>
                                                <td style="text-align: right" id="adj2ytd" scope="row">0.00</td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                                <td scope="row"></td>
                                            </tr>

                                            <tr  > 
                                                <th style="border:none !important " scope="row">Total</th>
                                                <td style="border:none !important " scope="row"></td>
                                                <td  id="bftaxcurrent" style="border:none !important;text-align: right " scope="row">0.00</td>
                                                <td  id="btaxytd" style="border:none !important ;text-align: right" scope="row">0.00</td>
                                                <th style="border:none !important " scope="row">Total</th>
                                                <td  id="aftaxcurrent" style="border:none !important;text-align: right " scope="row">0.00</td>
                                                <td  id="aftaxafter" style="border:none !important ;text-align: right"  scope="row">0.00</td>
                                                <th style="border:none !important " scope="row">Total</th>
                                                <td  id="epbcurrent"  style="border:none !important ;text-align: right" scope="row">0.00</td>
                                                <td  id="epbytd"  style="border:none !important ;text-align: right" scope="row">0.00</td>
                                            </tr>
                                        </tbody>
                                        </table>
                                        <!-- new table -->
                                        <br>
                                        <table class="table" width="100%" id="table1">
                                        <thead>
                                            <tr >
                                                <th  width="20%"></th> 
                                                <th  style="text-align:center" width="20%">TOTAL GROSS</th>
                                                <th  style="text-align:center" width="20%">TOTAL TAXES</th>
                                                <th  style="text-align:center" width="20%">TOTAL DEDUCTIONS</th>
                                                <th  style="text-align:center" width="20%">PAY RECEIVABLE</th>
    
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr style="border: 1px solid"> 
                                                    <th scope="row">Current</th>
                                                    <td style="text-align: right" id="totalgrosscurrent" scope="row">0.00</td>
                                                    <td style="text-align: right" id="totaltaxescurrent" scope="row">0.00</td>
                                                    <td style="text-align: right" id="totaldeductioncurrent" scope="row">0.00</td>
                                                    <td style="text-align: right" id="recievablecurrent" scope="row">0.00</td>
                                                    
                                                </tr>
                                                <tr style="border: 1px solid"> 
                                                    <th id="" scope="row">YTD</th>
                                                    <td style="text-align: right" id="totalgrossytd" scope="row">0.00</td>
                                                    <td style="text-align: right" id="totaltaxesytd" scope="row">0.00</td>
                                                    <td style="text-align: right" id="totaldeductionytd" scope="row">0.00</td>
                                                    <td style="text-align: right" id="recievableytd" scope="row">0.00</td>
                                                    
                                                </tr>
                                               
                                                <tr > 
                                                    <th style="border:none !important"  scope="row"></th>
                                                    <td style="border:none !important"  scope="row"></td>
                                                    <td style="border:none !important"  scope="row"></td>
                                                    <td style="border:none !important"  scope="row"></td>
                                                    <td style="border:none !important"   scope="row"></td>
                                                </tr>

                                                <tr > 
                                                    <th style="border:none !important"  scope="row"></th>
                                                    <td style="border:none  !important"  scope="row"></td>
                                                    <td style="border:none  !important"  scope="row"></td>
                                                    <td style="border:none  !important"  scope="row"></td>
                                                    <td style="border:none  !important"  scope="row"></td>
                                                </tr>

                                                <tr > 
                                                   <th style="border:none  !important"  colspan="3" scope="row">Received by: <label id="namerecieved" for=""></label></th>
                                                   
                                                   <td style="border:none  !important" scope="row"></td>
                                                   <th style="border:none  !important" scope="row">Date: <label id="datenow" for=""></label></td>
                                                   
                                               </tr>
                                                
                                        </tbody>
                                        </table>    
                                        </div>
                                    </div>
                                
                                
                                </div>
                            </div>

                            <div class="printme">
                                

                            </div>
                        </div>   
                        
                        
                    </div>
            </div>
    </div>
</div>

</body>
</html>