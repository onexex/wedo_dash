<?php 
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else { header ('location: login'); }
    include 'ReportController.php';
    $handle = new ReportController();
    $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName, employees.EmpMN as MiddleName,hleavesbd.LFDate as DateFiled,hleavesbd.LStart as Start_Date,hleavesbd.LEnd as End_Date,leaves.LeaveDesc as Leave_Type,hleavesbd.LPurpose as Purpose,
        hleavesbd.LDuration as Duration,status.StatusDesc as Status,hleavesbd.LISReason as IS_Reason,hleavesbd.LUpdate AS IS_Update_Date,hleavesbd.LHRReason as HR_Reason,
        hleavesbd.LUpdateHR as HR_Update,hleavesbd.LInputDate as DateTimeInputed
        FROM hleavesbd
        INNER JOIN leaves ON hleavesbd.LType=leaves.LeaveID
        INNER JOIN employees ON hleavesbd.EmpID=employees.EmpID 
        INNER JOIN status ON status.StatusID=hleavesbd.LStatus  
    ");
    $todayF =date("Y-m-d h:i:s A"); 
    try{
        include 'w_conn.php';
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch(PDOException $e){
        die("ERROR: Could not connect. " . $e->getMessage());
    }
?>
<!DOCTYPE html>
<html>
<head>

    <title>ALAS Reports</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <!--  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-reports.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style-reports.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.3.1.js"></script>
    <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.html5.min.js"></script>


    <script type="text/javascript"> 
        $(document).ready(function() {
        
            $(document).on('click', '#btnprint', function(e) {
              
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
            $("#darviewer td").css("padding", "9px");
            $("#darviewer td").css("text-align", "center");
            $("#darviewer td").css("font-size", "10px");
            $("#tabth th").css("font-size", "10px");
            $("#tab").css({
                overflow: 'hidden',
                height: '100%'
                });

                $("#tab").css({
                overflow: 'auto',
                height: 'auto'
                });
            $(".captionText").css("font-size","12px");
            $(".captionText").removeClass("d-none").addClass("d-block");
            $("#captionText").html($("#empcompid option:selected").text());
            $("#dateRange").html($("#dtp1").val() + " To : " + $("#dtp2").val());

            var printContents = document.getElementById('tblprint').innerHTML;
            
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            $(".captionText").removeClass("d-block").addClass("d-none");


            });
        } );
    </script>

    
    <style>
        html body{
            font-family: Tahoma !important;
        }
        .buttons-html5{
            background-image: none !important;
            background-color: green !important;
            color: #fff !important;
            border-radius: 5px !important;
        }
    </style>
    <script type="text/javascript">
        function exportToExcel(tableID, filename = ''){
            var downloadurl;
            var dataFileType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById("tab");
            var tableHTMLData = tableSelect.outerHTML.replace(/ /g, '%20');
            filename = "LeaveReports_" + document.getElementById("dtp1").value + "_" + document.getElementById("dtp2").value + "_" + document.getElementById("empcompid").options[document.getElementById("empcompid").selectedIndex].text;
            // Specify file name
            filename = filename?filename+'.xls':'export_excel_data.xls';
            
            // Create download link element
            downloadurl = document.createElement("a");
            
            document.body.appendChild(downloadurl);
            
            if(navigator.msSaveOrOpenBlob){
                var blob = new Blob(['\ufeff', tableHTMLData], {
                type: dataFileType
                });
                navigator.msSaveOrOpenBlob( blob, filename);
            }else{
                // Create a link to the file
                downloadurl.href = 'data:' + dataFileType + ', ' + tableHTMLData;
                
                // Setting the file name
                downloadurl.download = filename;
                
                //triggering the function
                downloadurl.click();
            }
        }
    </script>
</head>
<body>
    <?php 
        include 'includes/header.php';
    ?>
    <div  class="lg-hide-logo" id="lg-hide-logo">
        <img width="150px" src="<?php echo $_SESSION['CompanyLogo']; ?>">
        <h4><?php echo $_SESSION['CompanyName']; ?></h4>
    </div>
    <div class="w-container">
    <div class="row">
    <div class="col-lg-3"></div>
    <!-- website content -->
    <div class="col-lg-9 wd-login">
        <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">ALAS Viewer </h4>
        <div id="table-container">
            <div class="row">
                <div class="col-lg-4">
                    <div class="form-group ">
                        <form id="vlilodata" >
                        <label for="sel1">Choose Employee:</label>
                        <select class="form-control" id="empcompid" name="empcompany">
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
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="dtpar">
                        <p>Date Parameters:</p>
                        <label>From:</label>
                        <input type="date" class="form-control" id="dtp1"  value="<?php echo date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));?>" >
                        <label>To:</label>
                        <input type="date" class="form-control"  id="dtp2"  value="<?php echo date("Y-m-d");?>">
                        <button class="btn" id="viewalasv" type="button"><img src="assets/images/refreshicon.png" data-toggle="tooltip" data-placement="right" title="Refresh" width="25px"></button>
                    </div>
                </div>
                        </form>
            </div>
            <div id="tblprint">
                <div class="col">
                    <div class="row">
                        <label for="" class="captionText d-none" id="captionTextMain">ALAS Report</label>
                    </div>
                    <div class="row">
                        <label for="" class="captionText d-none" id="captionText">All Employees</label>
                    </div>
                    <div class="row">
                        <label for="" class="captionText d-none" id="dateRange"> 19/272023</label>
                    </div>
                </div>
                
              
                <table id="tab">
                    <thead id="tabth">
                    <tr>
                        <th width="12.5%">Name</th>
                        <th width="12.5%">Date Filed</th>
                        <th width="12.5%">Start Date</th>
                        <th width="12.5%">End Date</th>
                        <th width="12.5%">Leave Type</th>
                        <th width="12.5%">Purpose</th>
                        <th width="12.5%">Duration</th>
                        <th width="12.5%">Status</th>
                    </tr>
                    </thead>
                    <tbody id="darviewer">
                        <?php
                        try{
                            $sql= " SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName, employees.EmpMN as MiddleName,hleavesbd.LFDate as DateFiled,hleavesbd.LStart as Start_Date,hleavesbd.LEnd as End_Date,leaves.LeaveDesc as Leave_Type,hleavesbd.LPurpose as Purpose,
                                hleavesbd.LDuration as Duration,status.StatusDesc as Status,hleavesbd.LISReason as IS_Reason,hleavesbd.LUpdate AS IS_Update_Date,hleavesbd.LHRReason as HR_Reason,
                                hleavesbd.LUpdateHR as HR_Update,hleavesbd.LInputDate as DateTimeInputed
                                FROM hleavesbd
                                INNER JOIN hleaves ON hleavesbd.FID=hleaves.LeaveID
                                INNER JOIN leaves_validation ON hleavesbd.LType=leaves_validation.sid
                                INNER JOIN leaves ON leaves_validation.lid=leaves.LeaveID
                                INNER JOIN employees ON hleavesbd.EmpID=employees.EmpID 
                                INNER JOIN status ON status.StatusID=hleavesbd.LStatus  
                                WHERE hleavesbd.LStatus<>7 and employees.EmpStatusID=1";
                            $statement = $pdo->prepare($sql);
                            $statement->execute();     
                        }catch (Exception $e) {
                            echo 'Caught exception: ',  $e->getMessage(), "\n";
                        }
                        $ids=0;
                        if (! empty($statement->rowCount())) {
                            while ($row=$statement->fetch()) {
                                $ids=$ids+1;
                                ?>
                                <tr>
                                    <td><?php echo $row["LastName"]. ' '.$row["FirstName"].' '.$row["MiddleName"]; ?> </td>
                                    <td><?php echo date("F j, Y", strtotime($row["DateFiled"])); ?> </td>
                                    <td><?php echo date("F j, Y", strtotime($row["Start_Date"])); ?> </td>
                                    <td><?php echo date("F j, Y", strtotime($row["End_Date"])); ?> </td>
                                    <td><?php echo $row["Leave_Type"]; ?> </td>  
                                    <td><?php echo $row["Purpose"]; ?> </td>
                                    <td><?php echo $row["Duration"]; ?> </td>
                                    <td><?php echo $row["Status"]; ?> </td>
                                </tr>
                            <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="btn">
                <form action="" method="post">
                    <!-- <button type="submit" name='export1' value="Export to Excel"class="btn btn-info">Export to Excel</button> -->
                    <button class="btn btn-info  btnprint" id="btnprint" ><i class="fa fa-print" aria-hidden="true"></i></button>
                    <button class="btn btn-success" onclick="exportToExcel('tab', 'user-data')"><i class="fa fa-file-excel-o" aria-hidden="true"></i> Export Table Data To Excel File</button>
                </form>
            </div>
        </div>
        </div>
        </div>
    </div>
</body>
</html>