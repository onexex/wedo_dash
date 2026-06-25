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
    <script type="text/javascript" src="assets/js/payroll.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    
    <!-- include this script for payroll -->
    <script type="text/javascript">
      $('document').ready(function(){
        $('.btnprint').click(function(){
        //   var filter = ($(".payrolfilater").val());
        // if(filter=="2"){
        //   $("#adjmin").html("Marketting Document/URL1");
          
        // }
          // $(".wd-login").hide();
          // $(".s-menu").hide();
          // $(".w-navbar").hide();
          // $(".wedo-clock").hide();
           $(".toprint").show();
           $("#toprint .row").show();
            $(".thisdate").html("For the period of " + $("#cutOFd1").val() + " - " + $("#cutOFd2").val());
            $(".payrolldate").html("Payroll Date : " + $("#pdate").val());
            $(".subtitle").css("top" , "0px");
            $("td").css("font-size" , "9px");
            $("td").css("border-top" , "1px solid #ddd");
            $("th").css("font-size" , "9px");
            $("th").css("text-align" , "center");
            $("th").css("padding" , "8px");
            $("td").css("padding" , "8px");
            $("table").css("width" , "100%");
            $("table").css("text-align" , "center");
            $("thead").css("border-bottom" , "3px solid #000");
            $(".names").css("text-align" , "left");
            $(".valdata").css("text-align" , "right");
            $(".a").css("text-align" , "right");
            $(".a").css("font-weight" , "bold");
            
            
            if ($(".pyrlfilt").html()=="Payroll Attachment Summary"){

            }else{
              $(".pyrlfilt").html("WeDo " +  $(".payrolfilater option:selected").text() + " Payroll Report");
            }
        
            pop_print();
           $(".toprint").hide();
           $("#toprint .row").hide();
            $("td").css("font-size" , "14px");
            $("th").css("font-size" , "14px");


          // $(".wd-login").show();
          // $(".s-menu").show();
          // $(".w-navbar").show();
          // $(".wedo-clock").show();

          
        });

        function pop_print(){
          w=window.open(null, 'Print_Page', 'scrollbars=no');        
          var myStyle = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />';
          w.document.write(myStyle + jQuery('#toprint').html());
          w.document.close();
          w.print();
      }
      
      });
      
    </script>
    <script>
    $.ajax({
          url:'query/query-payrollscript.php?getemployee', 
          type:'GET',
          cache: false,
          data:{},
          dataType: 'json',
           success:function(dataresult){
            console.log(dataresult);
            var resultData=dataresult.data;          
            var errcode = dataresult.errcode;
           if(errcode==0){
            var emp = '';
                    emp+=("<option value=></option>");
                    $(resultData).each(function (index, item) {
                      emp+=("<option value="+item.EmpID+">" +item.EmpLN + " "+item.EmpFN+ "</option>");
                   })              
              $("#employeedata").empty();
              $("#employeedata").append(emp);
              
           }
          }
        });      
        
      $("#updateAdjustment").click(function(){
        alert("Update");
      });

      $("#deleteAdjusment").click(function(){
        alert("Delete");
      });

        </script>
    <!-- end srcipt -->
    <style type="text/css">
      th, td{
        white-space: nowrap;
      }
    </style>

<style>
  html body{
		font-family: Tahoma !important;
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
            <!-- website content -->
                <div class="col-lg-9 ">
                <!-- wd-login -->
                    <div class="in-con">
                      <br>
                        <div class="row" style="background-color: floralwhite;margin:1px">
                                               
                            <div class="col-md-3 pagti">
                                <h5>Payroll Date</h5>
                                <input type="date" class="form-control" id="pdate" name="pdate" style="margin-bottom:5px">
                                <button class="btn btn-success btn-sm btngenviewerr" id="btngen">Generate</button>
                                <button class="btn btn-danger btn-sm" id="btnApprovedPayroll">Approve for Release</button>
                                

                            </div>
                            <div class="col-md-2 pagti">
                                <h5>Cut Off Date</h5>
                                <input type="date" class="form-control" id="cutOFd1"  name="cutOFd1" style="margin-bottom: 5px">
                               
                                <input type="date" class="form-control" id="cutOFd2"  name="cutOFd2" style="margin-bottom: 5px">
                                
                                
                                            </div>
                            <div class="col-md-3">
                                <h5>Print & Display Filter</h5>
                                <select class="form-control form-select-lg col-md-3 payrolfilater" style="margin-bottom:5px" >
                                  <option value="0">All</option>
                                  <option value="2">IC</option>
                                  <option value="1">BE</option>
                                </select>
                              
                                <button class="btn-sm btn btn-info  btnprint" id="btngen1"> <i class="fa fa-print"></i> Print</button>
                                <button class=" btn-sm btn btn-info " id="btnview" ><i class="fa fa-eye"></i> Payroll</button>
   
                                 <button class="btn-sm btn btn-info   btnviewrp"><i class="fa fa-eye"></i> Summary</button>
                            </div>

                            <div class="col-md-2">
                                <h5>Adjustment</h5>
                                <button class="btn-sm btn btn-success "  data-toggle="modal" data-target="#exampleModal" id="inputAdj"> <i class="fa fa-plus"></i> Adjustment</button>

                
                            </div>
                            <div class="overlay"></div>
                        </div>
                        <br>
                        <div  id="result" class="alert" style="display:none"></div>
                
                <div id="toprint" style="overflow: scroll;">
                  <div class="row" style="display: none;">
                    <div class="col-md-6">
                      <img src="assets/images/logo-2.png">
                      
                      <!--<h3>WeDo BPO</h3>-->
                      <!--<h3>WeDo Metro Inc</h3>-->
                    </div>
                    <div class="col-md-6 subtitle">
                      <!--<h4 class="pyrlfilt">WeDo BPO/Metro Payroll Report</h4>-->
                         <h4 class="pyrlfilt">WeDo Metro Payroll Report</h4>
                      <h5 class="thisdate"> </h5>
                      <h5 class="payrolldate"> </h5>
                    </div>
                  </div>

                      <table class="table" id="reportview" style="overflow: auto;">
                        <thead>            
                          <tr>
                            <th>Employee Name</th>
                            <th>Basic</th>
                            <th>AB/TRD</th>
                            <th>APCO</th>
                                                      
                            <th>OT</th>
                            <th>Gross Pay</th>
                            <th>SSS</th>
                            <th>SSS Loan</th>
                            <th>PH</th>
                            <th>PI</th>
                            <th>PI Loan</th>
                            <th>Taxable Income</th>
                            <th>Tax</th>
                            <th>Netpay</th>
                            <th>Allowance</th>
                            <th>Adjustments</th>
                            <th>Adjustment 2</th>
                            <th>Pay Receivable</th>
                          </tr>
                        </thead>
                        <tbody>

                        
                        </tbody>
                      </table>
                      <hr style="border-top: 2px solid;">
                      <div class="sign" style="width: 100%;">
                          <div class="prepared1" style="display: inline-block;" >
                              <h5>Prepared By:</h5>
                              <h5 class="prprdby">WeDo Payroll Management System</h5>
                              <h5>Date/Time Printed: <?php echo date("F j,Y h:i:s A"); ?></h5>
                          </div>
                          <div class="prepared2" style="display: inline-block;float: right;">
                              <h5>Approved for Release by:</h5>
                              <br>
                              <h5 class="apprvd">Jomod A. Ferrer - General Manager</h5>
                          </div>
                      </div>
                </div>
                <!-- end copy this code -->
               

                    </div>
                </div>

        </div>

        <!-- Modal -->
      <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h3 class="modal-title" id="exampleModalLabel">Adjusment Logger</h3>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
        
              <table class="table table-hover">
              
                  <thead>
                  <tr>
                      <th>No</th>
                      <th>Employee</th>
                      <th>Amount</th>
                      <th>Date</th>
                      <th>Actions</th>
                      </tr>
                  </thead>
          
                  <tbody id="adjustment2">
                
              </tbody>
            
                  <tfoot>
                      <tr>
                          <td>
                          <select class="form-control" id="employeedata" >
                          
                        </select>
                          </td>
                          <td>
                          <p>
                              <input type="text" class="form-control" id="adjamount" placeholder="Amount" required>
                              </p>
                          </td>

                          <td>
                              <button  class="btn btn-primary btn-sm " id="addAdjusment" ><i class="fa fa-plus"></i></button>
                              <!-- <button  class="btn btn-primary btn-sm" id="updateAdjustment"><i class="fa fa-pencil"></i></button>
                              <button  class="btn btn-danger btn-sm" id="deleteAdjusment" ><i class="fa fa-ban"></i></button> -->
                              
                          </td>
                          
                      </tr>
                      
                  </tfoot>
              </table>
            </div>
              <div  id="result1" class="alert" style="display:none"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              <!-- <button type="button" class="btn btn-primary">Save changes</button> -->
            </div>
          </div>
        </div>
      </div>
           
    </div>        
</body></html>
