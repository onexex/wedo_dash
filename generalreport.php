<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {

} else {
    if (!isset($_COOKIE["WeDoID"])) {

        header('location: login');
    } else {
        if (!isset($_COOKIE["WeDoID"])) {
            session_destroy();
            header('location: login');
        } else {
            try {
                include 'w_conn.php';
                $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("ERROR: Could not connect. " . $e->getMessage());
            }
            $statement = $pdo->prepare("select * from empdetails");
            $statement->execute();

            while ($row = $statement->fetch()) {
                if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])) {
                    $_SESSION['id'] = $row['EmpID'];

                    $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                    $statement->bindParam(':un', $_SESSION['id']);
                    $statement->execute();
                    $count = $statement->rowCount();
                    $row = $statement->fetch();
                    $hash = $row['EmpPW'];
                    $_SESSION['UserType'] = $row['EmpRoleID'];
                    $cid = $row['EmpCompID'];
                    $_SESSION['CompID'] = $row['EmpCompID'];
                    $_SESSION['EmpISID'] = $row['EmpISID'];
                    $statement = $pdo->prepare("select * from companies where CompanyID = :pw");
                    $statement->bindParam(':pw', $cid);
                    $statement->execute();
                    $comcount = $statement->rowCount();
                    $row = $statement->fetch();
                    if ($comcount > 0) {
                        $_SESSION['CompanyName'] = $row['CompanyDesc'];
                        $_SESSION['CompanyLogo'] = $row['logopath'];
                        $_SESSION['CompanyColor'] = $row['comcolor'];
                    } else {
                        $_SESSION['CompanyName'] = "ADMIN";
                        $_SESSION['CompanyLogo'] = "";
                        $_SESSION['CompanyColor'] = "red";
                    }
                    $_SESSION['PassHash'] = $hash;

                } else {

                }
            }
        }
    }

}
?>
<?php
    date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html>

<head>
    <title>!3th Month Calculator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"
        integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous">
    </script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"
        integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous">
    </script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/general.js"></script>

    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <script>
        function FunctionChangedate() {
            var d = new Date();
            var n = d.getFullYear();
            var x = document.getElementById("lstarts").max = n + "-12-31";
            var x = document.getElementById("lenddate").max = n + "-12-31";
            var x = document.getElementById("lstarts").min = n + "-01-01";
            var x = document.getElementById("lenddate").min = n + "-01-01";
        }
    </script>

    
<script type="text/javascript"> 
        $(document).ready(function() {
        
            // $(".buttons-html5").remove("span");
            // $('#tab').DataTable( {
            //     dom: 'Bfrtip',
            //     buttons: [
            //         'excelHtml5',
            //         'csvHtml5'
            //     ]
            // } );

        $(document).on('click', '.btnprint', function(e) {
            var css = '@page { size: landscape; }',
            head = document.head || document.getElementsByTagName('head')[0],
            style = document.createElement('style');

            style.type = 'text/css';
            style.media = 'print';

            if (style.styleSheet){
                style.styleSheet.cssText = css;
            } else {
                style.appendChild(document.createTextNode(css));
            }
            head.appendChild(style);    
            var originalContents = document.body.innerHTML;
           
            $(".header_data").css("font-size","12px");
            $(".header_data").removeClass("d-none").addClass("d-block");
            $(".chk13_month").removeClass("d-block").addClass("d-none");

            $(".thisdate").html($("#cutOFd1").val() + " To : " + $("#cutOFd2").val());

            var printContents = document.getElementById('toprint').innerHTML;
            
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            $(".header_data").removeClass("d-block").addClass("d-none");
            $(".chk13_month").removeClass("d-none").addClass("d-block");
            });
        } );
    </script>
</head>

<style type="text/css">
    html body {
        font-family: Tahoma !important;
    }

    .modal-backdrop {
        background-color: transparent;
    }

    .ihd-dis {
        /*display:  none;*/
    }
</style>

<body style="background-image: none">
    <?php include 'includes/header.php'; ?>
    <div class="w-container">
        <div class="row">
            <div class="col-lg-3"></div>
            <div class="col-lg-9 ">
                <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">YTD 13<Sup>th</Sup>Month Report</h4>
                <div class="in-con">
                    <br>
                    <div class="row" style="background-color: floralwhite;margin:1px">
                        <div class="col-md-2 pagti">
                            <h5>Dates:</h5>
                            <input type="date" class="form-control" id="cutOFd1" value="2023-11-20"  name="cutOFd1" style="margin-bottom: 5px">
                            <input type="date" class="form-control" id="cutOFd2"  name="cutOFd2" style="margin-bottom: 5px">
                        </div>

                        <div class="col-lg-4">
                            <h5>Filter</h5>
                            <select class="form-control form-select-lg col-md-3 " id="selEmp" style="margin-bottom:5px" >
                                <option value="all">All</option>
                                    <?php
                                    $sql=mysqli_query($con, "select * from employees where EmpStatusID='1' order by EmpLN asc");
                                    while($res=mysqli_fetch_array($sql)){
                                    ?>
                                    <option  value="<?php echo $res['EmpID']; ?>"><?php echo $res['EmpLN'] . ", " . $res['EmpMN'] . " " . $res['EmpFN']; ?>  </option>
                                    <?php   
                                    }
                                ?>
               
                            </select>
                                <button class="btn-sm btn btn-info mx-3 " id="btnView"> <i class="fa fa-print"></i> View</button>
                                <button class="btn-sm btn btn-info  btnprint" id="btngen1"> <i class="fa fa-print"></i> Print</button>
                        </div>

                        <div class="overlay"></div>
                    </div>

                    <br>
                    <div  id="result" class="alert" style="display:none"></div>
            
                    <div id="toprint" style="overflow: scroll;">
                        <div class="row header_data" style="display: none;">
                            <div class="col-md-6">
                                <img src="assets/images/logo-2.png">
                                <h3>WeDo BPO</h3>
                            </div>
                            <div class="col-md-6 subtitle">
                           
                            <h5 class="thisdate"> </h5>
                            <!-- <h5 class="payrolldate"> </h5> -->
                            </div>
                            
                        </div>
                        <div class="form-group form-check chk13_month">
                            <input type="checkbox" class="form-check-input" id="chk13" name="chk13">
                            <label class="form-check-label pl-4" for="exampleCheck1">Filter for 13 Month</label>
                        </div>
                        
                        <table class="table" id="reportview" style="overflow: auto;">
                     
                            <thead>            
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Basic</th>
                                    <th>AB/TRD</th>
                                    <th class="not_13month_data">OT</th>
                                    <th>Gross Pay</th>
                                    <th class="not_13month_data">SSS</th>
                                    <th class="not_13month_data">SSS Loan</th>
                                    <th class="not_13month_data">PH</th>
                                    <th class="not_13month_data">PI</th>
                                    <th class="not_13month_data">PI Loan</th>
                                    <th class="not_13month_data">Taxable Income</th>
                                    <th class="not_13month_data">Tax</th>
                                    <th class="not_13month_data">Netpay</th>
                                    <th>Allowance</th>
                                    <th class="not_13month_data">Adjustments</th>
                                    <th class="not_13month_data">Adjustments Allow</th>
                                    <th class="not_13month_data">Pay Receivable</th>
                                    <th>13<sup>th</sup>Month (BASIC)</th>
                                    <th>13<sup>th</sup> Month (BASIC+ALLOWANCE)</th>
                                </tr>
                            </thead>
                            <tbody id="data">

                            
                            </tbody>
                        </table>
                        <hr style="border-top: 2px solid;"> 
                    </div>
                </div>
            </div>
        </div>
    </div> 
</body>

</html>