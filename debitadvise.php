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
  
    <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Debit Advise"; } ?></title>
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
    <script type="text/javascript" src="assets/js/debitadvice.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    
    <!-- include this script for payroll -->
    <script type="text/javascript">
      $('document').ready(function(){
        $('#btnprint').click(function(){

          
          $("#btnprint").hide();
           $(".toprint").show();
           $("#toprint .row").show();
            $(".subtitle").css("top" , "0px");
             $("td").css("font-size" , "14px");
             $("td").css("border-top" , "1px solid #ddd");
             $("th").css("font-size" , "14px");
             $("th").css("text-align" , "left");
             $("th").css("padding" , "8px");
             $("td").css("padding" , "8px");
             $("table").css("width" , "100%");
             $(".toprint").css("margin" , "5%");
             
            // $("table").css("text-align" , "center");
            // $("thead").css("border-bottom" , "3px solid #000");
            // $(".names").css("text-align" , "left");
            // $(".valdata").css("text-align" , "right");
            // $(".a").css("text-align" , "right");
            // $(".a").css("font-weight" , "bold");
            $(".rldate").css("display" , "none");
            pop_print();
           $(".toprint").hide();
           $("#toprint .row").hide();
             $("td").css("font-size" , "14px");
             $("th").css("font-size" , "14px");
            $(".rldate").css("display" , "block");
            $("#btnprint").show();
          
        });

        $('#btnprintdoc2').click(function(){
          var debitdate= $("#debitDate").text();
         var branch= $("#conBranch").text();
        var city= $("#conCity").text();
        var paydate= $("#pdates").val();
       
         jQuery.ajax({
             url:'query/debitPhpScript.php?savehistorydebit', 
             method: 'POST',
             data:{debitdate:debitdate,
                    branch:branch,
                    city:city,
                    paydate:paydate},
             cache: false,
             dataType: 'json',
             error: function(xhr, status, error) {
         alert(xhr.responseText);
             },
             success: function(resultData){

             }
         });
         
          $("#btnprintdoc2").hide();
          $(".toprint1").show(); 
          $(".toprint1").css("width" , "100%");
          
          $(".numString").css("width" , "50%");
          
        
          $(".std").css("width" , "100%");
          $(".sig1").css("float" , "left");
          $(".sig2").css("float" , "right");
          
          pop_print1();
          $(".toprint1").hide();
          $(".std").css("font-size" , "10pt");
          $("#btnprintdoc2").show();
  
        });

        function pop_print(){
          w=window.open(null, 'Print_Page', 'scrollbars=no');        
          var myStyle = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />';
          w.document.write(myStyle + jQuery('#toprint').html());
          w.document.close();
          w.print();
      }
      
      function pop_print1(){
          w=window.open(null, 'Print_Page', 'scrollbars=no');        
          var myStyle = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />';
          w.document.write(myStyle + jQuery('#toprint1').html());
          w.document.close();
          w.print();
      }

      });
      
    </script>

    <style type="text/css">
      html body{
		font-family: Tahoma !important;
	}
      th, td{
        white-space: nowrap;
      }
    </style>

<style>
    /* .lbdata{
       display: none;
    } *//* ISO Paper Size */
@page {
  size: A4 landscape;
}

/* Size in mm */    
@page {
  size: 100mm 200mm landscape;
}

/* Size in inches */    
@page {
  size: 4in 6in landscape;
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
                    <div class="col-lg-9 ">
                    
                        <h3>Debit Advise</h3>
                        <label  class="label label-primary" for="l1">Generate Cash Card Posting</label>
                        <label  class="label label-primary" for="l2">Generate Letter for Debit Advise!</label>
                        <br> <br> 
                        <div class="col-lg-2">
                             <label for="pdats">Select Payroll Date:</label>
                            <select name="pdates" class="form-control" id="pdates">
                            </select>
                  
                            <br>
                        </div>
                        <div class="col-lg-3">
                            
                           
                            
                        </div>

                        <div class="col-md-12">
                            <button type="button" id="doc1" class="btn btn-success"> Cash Card Posting </button>
                            <button type="button" id="doc2" class="btn btn-success" data-toggle="modal" data-target="#myModal"> Debit Advise Letter </button>
                            <hr>
                            <div  id="result" class="alert" style="display:none"></div>
                        </div>
                        
                        <div class="col-lg-9" id="toprint">
                          <div class="row rldate" style="margin-bottom:14px">
                            <div class="col-lg-3 " >
                                <label for=""> Select Bank Release Date :</label>
                                <input type="date" id="dtpReleaseDate" class="form-control">
                            </div>
                          </div>
                          
                          <div class="col-lg-4">
                          <p style="font-size:   10pt; line-height: 14pt;"> WeDo BPO Inc. <br>
                              WeDo Metro Philippines Corp. <br>
                             [Consolidated] <br><br>
                             For release : <label for="" class="release">sdsd </label> <br>
                             Salaries - Payroll
                            </p>
                          </div>

                            <br>
                            <table class="table table-hover" style="width:80%">
                            <thead>
                            <tr>
                                <th></th>
                                <th>NAME</th>
                                <th>CARD NUMBER</th>
                                <th>UCPB</th>
                                <!--<th>CBC</th>-->
                            </tr>
                            </thead>
                            <tbody id="tblPaydata">
    
                                
                            </tbody>
                            </table>

                            <button type="button" id="btnprint" class="btn btn-success">Print </button>
                        </div> 

                        <div class="col-lg-9" id="toprint1" style="margin:12px">
                        <br>
                        <div class="container">

                        <p id="debitDate" style="font-size: 10pt; line-height: 14pt;"><?php echo date("d M Y");?></p>
                            <p style="font-size:   10pt; line-height: 14pt;">
                               <label id="conSalut" style="font-weight: normal;"for=""></label><label id="conName" for="" style="font-weight: normal;"> </label><br>
                               <label id ="conPosition" for="" style="font-weight: normal;"></label><br>
                               <label id="conBranch" for="" style="font-weight: normal;"></label><br>
                               <label id="conCity" for="" style="font-weight: normal;"></label><br><br><br>
                              Dear  <label id="conSalut" style="font-weight: normal;"for=""></label> <label id="conDear" for="" style="font-weight: normal;"></label> ;<br><br>

                              This is to authorize <label id="bank" style="font-weight: normal;"></label>  Branch to debit the amount from <label id="bank1" style="font-weight: normal;"> </label> Branch  CA/SA 
                              Number <label id="ca" style="font-weight: normal;"> </label>  in the name of WeDo Metro Philippines Corp. <br> <br>

                              <div class="col-lg-5 numString">
                                
                              <label id="wordsInPr" for=""  > </label>
                              </div>
                                <br> <br>
                              <br>
                              Thank you. <br> <br>
                              Truly yours,<br>  <br>
                              <img src="assets/images/sign/ogm.png" style="width:150px"  valign="middle" vspace="5" hspace="5"/> <br>
                              <strong>JOMOD A. FERRER</strong> <br>
                              General Manager – WeDo Metro Philippines Corp. <br> <br> <br>
                              Noted by: <br> <br>
                                      <div class="std">
                                        <div class="col-lg-3 sig1">
                                          <br> 
                                        <img src="assets/images/sign/elt.png" style="width:150px"  valign="middle" vspace="6" hspace="6"/> <br> 
                                        <strong> EMELITA L. TADIQUE	</strong> <br> 
                                        <p>Signatory</p>
                                        </div>
                                
                                        <div class="col-lg-3 sig2">
                                        <img src="assets/images/sign/kll.png" style="width:150px"  valign="middle" vspace="6" hspace="6"/> <br>
                                        <strong>MARK REOLESTER L. LEDESMA</strong>
                                        <p>Signatory</p>
                                        </div>
                                      </div>
                              </p>
                        </div><br>
                        <button type="button" id="btnprintdoc2" class="btn btn-success">Print </button>
                        </div> 
                                    
                        
  <div class="container">

  <!-- Trigger the modal with a button -->
 <!-- // <button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target="#myModal">Open Modal</button> -->

  <!-- Modal -->
  <div class="modal fade" id="myModal" role="dialog">
    <div class="modal-dialog">
    
      <!-- Modal content-->
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h5 class="modal-title">Select Bank Details</h5>
        </div>
        <div class="modal-body">
        
          <div class="row">
            <div class="col-lg-6">
            <label for="comment">Select Contact:</label>
            <select name="bankifo" class="form-control" id="bankinfo">
            </select>
            
            </div>
            <div class="col-lg-6">
            <label for="comment">Select CA:</label>
            <select name="cainfo" class="form-control" id="cainfo">
            </select>
            
            </div>
          </div>


        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        </div>
      </div>
      
    </div>
  </div>
  
</div>
                    </div>

               
       </div>   
            
    </div>  
    
    
    
</body>
</html>
