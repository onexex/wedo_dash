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
<html lang="en">
<head>

    <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Payroll"; } ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (modals + existing JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/script-home.js"></script>
    <script type="text/javascript" src="assets/js/payroll.js"></script>

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
          // absolute base so the theme CSS resolves inside the blank popup
          var base = location.origin + location.pathname.replace(/[^\/]*$/, '');
          var head = ''
            + '<link rel="stylesheet" href="' + base + 'assets/css/wedo-theme.css">'
            + '<style>'
            +   '@page{margin:12mm}'
            +   'body{background:#fff;padding:0;color:var(--text);font-family:var(--font-body)}'
            +   '.wd-table{width:100%;border-collapse:collapse}'
            +   '.wd-table th{background:var(--surface-2);text-transform:none;color:var(--text)}'
            +   '.wd-table th,.wd-table td{border:1px solid var(--border-2)}'
            +   '.subtitle{text-align:right}'
            +   '.pyrlfilt{color:var(--brand);font-family:var(--font-head)}'
            +   '.pr-sign,.sign{display:flex;justify-content:space-between;gap:24px;margin-top:14px}'
            + '</style>';
          w = window.open(null, 'Print_Page', 'scrollbars=no');
          w.document.write('<!DOCTYPE html><html><head>' + head + '</head><body>' + jQuery('#toprint').html() + '</body></html>');
          w.document.close();
          // let the stylesheet/fonts load before printing
          setTimeout(function(){ w.print(); }, 350);
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
      .wd-table th, .wd-table td{ white-space: nowrap; }

      /* payroll control grid */
      .pr-controls{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:18px;padding:18px 20px}
      .pr-controls h5{font-family:var(--font-head);font-size:11px;font-weight:700;letter-spacing:.06em;
        text-transform:uppercase;color:var(--text-3);margin:0 0 8px}
      .pr-controls .wd-input{margin-bottom:8px}
      .pr-actions{display:flex;flex-wrap:wrap;gap:8px}
      .pr-actions .wd-btn{flex:1 1 auto;justify-content:center}
      #reportview{ width:100%; }
      .pr-sign{display:flex;justify-content:space-between;flex-wrap:wrap;gap:24px;padding:18px 20px}
      .pr-sign h5{margin:0 0 4px;font-size:13px}
    </style>

<style>
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
    <?php $wd_active = 'payroll'; include 'includes/wd-header.php'; ?>

      <div class="wd-pagehead">
        <div>
          <h1>Payroll Management System</h1>
          <p>Generate, review and approve payroll for the cut-off period</p>
        </div>
      </div>

      <!-- Controls -->
      <section class="wd-card">
        <div class="wd-card__head"><h3>Payroll controls</h3></div>
        <div class="pr-controls">
          <div>
            <h5>Payroll Date</h5>
            <input type="date" class="wd-input" id="pdate" name="pdate">
            <div class="pr-actions">
              <button class="wd-btn wd-btn--primary wd-btn--sm btngenviewerr" id="btngen"><i class="fa-solid fa-gears"></i> Generate</button>
              <button class="wd-btn wd-btn--danger wd-btn--sm" id="btnApprovedPayroll"><i class="fa-solid fa-circle-check"></i> Approve for Release</button>
            </div>
          </div>

          <div>
            <h5>Cut Off Date</h5>
            <input type="date" class="wd-input" id="cutOFd1" name="cutOFd1">
            <input type="date" class="wd-input" id="cutOFd2" name="cutOFd2">
          </div>

          <div>
            <h5>Print &amp; Display Filter</h5>
            <select class="wd-select payrolfilater">
              <option value="0">All</option>
              <option value="2">IC</option>
              <option value="1">BE</option>
            </select>
            <div class="pr-actions">
              <button class="wd-btn wd-btn--info wd-btn--sm btnprint" id="btngen1"><i class="fa-solid fa-print"></i> Print</button>
              <button class="wd-btn wd-btn--info wd-btn--sm" id="btnview"><i class="fa-solid fa-eye"></i> Payroll</button>
              <button class="wd-btn wd-btn--info wd-btn--sm btnviewrp"><i class="fa-solid fa-eye"></i> Summary</button>
            </div>
          </div>

          <div>
            <h5>Adjustment</h5>
            <button class="wd-btn wd-btn--success wd-btn--sm" data-toggle="modal" data-target="#exampleModal" id="inputAdj"><i class="fa-solid fa-plus"></i> Adjustment</button>
          </div>
        </div>
        <div class="overlay"></div>
      </section>

      <div id="result" class="alert" style="display:none"></div>

      <!-- Report -->
      <section class="wd-card">
        <div id="toprint">
          <div class="row" style="display: none;">
            <div class="col-md-6">
              <img src="assets/images/logo-2.png">
            </div>
            <div class="col-md-6 subtitle">
              <h4 class="pyrlfilt">WeDo Metro Payroll Report</h4>
              <h5 class="thisdate"> </h5>
              <h5 class="payrolldate"> </h5>
            </div>
          </div>

          <div class="wd-tablewrap" style="max-height:none">
            <table class="wd-table" id="reportview">
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
          </div>

          <hr style="border-top: 2px solid var(--border-2)">
          <div class="pr-sign sign">
            <div class="prepared1">
              <h5>Prepared By:</h5>
              <h5 class="prprdby">WeDo Payroll Management System</h5>
              <h5>Date/Time Printed: <?php echo date("F j,Y h:i:s A"); ?></h5>
            </div>
            <div class="prepared2">
              <h5>Approved for Release by:</h5>
              <br>
              <h5 class="apprvd">Jomod A. Ferrer - General Manager</h5>
            </div>
          </div>
        </div>
      </section>

    <?php include 'includes/wd-footer.php'; ?>

    <!-- Adjustment logger modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header" style="color:#fff;background-color:#f93627">
            <h3 class="modal-titles" id="exampleModalLabel">Adjustment Logger</h3>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="wd-tablewrap">
              <table class="wd-table">
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
                      <select class="wd-select" id="employeedata"></select>
                    </td>
                    <td>
                      <input type="text" class="wd-input" id="adjamount" placeholder="Amount" required>
                    </td>
                    <td>
                      <button class="wd-btn wd-btn--primary wd-btn--sm" id="addAdjusment"><i class="fa-solid fa-plus"></i></button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <div id="result1" class="alert" style="display:none"></div>
          <div class="modal-footer">
            <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

</body></html>
