<?php 
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else { header ('location: login'); }
    
    include 'ReportController.php';
    $handle = new ReportController();
    if ($_SESSION['UserType']==1) {
        $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employee_ID,
        employees.EmpLN as LastName,
        employees.EmpFN as FirstName, 
        employees.EmpMN as MiddleName,
        obs.OBFD as Filing_Date,
        obs.OBDateFrom as OBDateFrom, 
        obs.OBDateTo as OBDateTo,
        obs.OBIFrom as Itinerary_From,
        obs.OBITo as Itinerary_To,
        obs.OBTimeFrom as Time_From,
        obs.OBTimeTo as Time_To,
        obs.OBISReason as IS_Reason,
        obs.OBHRReason as HR_Reason,
        obs.OBPurpose as Purpose,
        obs.OBUpdated as DTUpdated,
        obs.OBCAAmt as Cash_Advance,
        obs.OBCAPurpose as CA_Purpose,
        status.StatusDesc as Status,
        obs.OBInputDate as DateTimeInputed FROM employees
        INNER JOIN obs ON employees.EmpID=obs.EmpID
        INNER JOIN status ON obs.OBStatus=status.StatusID where obs.OBStatus=4 order by obs.OBTimeFrom desc");
    }
    
    else{
        $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employee_ID,
        employees.EmpLN as LastName,
        employees.EmpFN as FirstName, 
        employees.EmpMN as MiddleName,
        obs.OBFD as Filing_Date,
        obs.OBDateFrom as OBDateFrom, 
        obs.OBDateTo as OBDateTo,
        obs.OBIFrom as Itinerary_From,
        obs.OBITo as Itinerary_To,
        obs.OBTimeFrom as Time_From,
        obs.OBTimeTo as Time_To,
        obs.OBISReason as IS_Reason,
        obs.OBHRReason as HR_Reason,
        obs.OBPurpose as Purpose,
        obs.OBUpdated as DTUpdated,
        obs.OBCAAmt as Cash_Advance,
        obs.OBCAPurpose as CA_Purpose,
        status.StatusDesc as Status,
        obs.OBInputDate as DateTimeInputed FROM employees
        INNER JOIN obs ON employees.EmpID=obs.EmpID
        INNER JOIN status ON obs.OBStatus=status.StatusID where obs.OBStatus=4 order by obs.OBTimeFrom desc");
    }
?>
<!DOCTYPE html>
<html>
<head>

    <title>Official Business Trip Viewer</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- bootstrap  -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.min.js" integrity="sha384-ODmDIVzN+pFdexxHEHFBQH3/9/vQ9uori45z4JjnFsRydbmQbmL5t1tQ0culUzyK" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous"></script>
    
    <!-- popper  -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.6/umd/popper.min.js" integrity="sha512-6UofPqm0QupIL0kzS/UIzekR73/luZdC6i/kXDbWnLOJoqwklBK6519iUnShaYceJ0y4FaiPtX/hRnV/X/xlUQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <!-- jquery  -->
    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>
    
    <!-- font awesome  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.9.0/css/all.min.css" integrity="sha512-q3eWabyZPc1XTCmF+8/LuE1ozpg5xxn7iO89yfSOd5/oKvyqLngoNGsx8jq92Y8eXJ/IRxQbEC+FGSYxtk2oiw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- customized assets  -->
    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-reports.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style-reports.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    
    <!-- datatables  -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.html5.min.js"></script>
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
    
    <style>
        body {
            font-size: 0.95em;
            font-family: arial;
            color: #212121;
        }
        
        th {
            background: #E6E6E6;
            border-bottom: 1px solid #000000;
        }
        
        #table-container {
            width: 1066px;
            margin: 50px auto;
        }
        
        table#tab {
            border-collapse: collapse;
            width: 100%;
            white-space: nowrap;
            display: block;
            height: 450px;
            overflow-y: scroll;
        }
        table#tab tr th{text-align: center;}
        
        table#tab th, table#tab td {
            border-bottom: 1px solid #E0E0E0;
            padding: 8px 15px;
            text-align: left;
            font-size: 0.95em;
        }
        
        
        #btnExport {
            padding: 10px 40px;
            background: #499a49;
            border: #499249 1px solid;
            color: #ffffff;
            font-size: 0.9em;
            cursor: pointer;
        }
        .dtpar{
            display: inline-block;
            float: right;
        }
        .dtpar p{
            display: block;
            font-size: 13px;
        }
        .dtpar label{
            font-size: 13px;;
        }
        .dtpar input{
            font-size: 13px;
            width:140px;
        }
        .dtpar label {
            display: inline-block;
            padding: 0px 5px 0px 5px;
        }
        .dtpar input {
            display: inline-block;
        }
    </style>
    
    <script type="text/javascript">
        function exportToExcel(tableID, filename = ''){
            var downloadurl;
            var dataFileType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById("tab");
            var tableHTMLData = tableSelect.outerHTML.replace(/ /g, '%20');
            filename = "OBReports_" + document.getElementById("dtp1").value + "_" + document.getElementById("dtp2").value + "_" + document.getElementById("empcompid").options[document.getElementById("empcompid").selectedIndex].text;
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
            <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Official Business Trip Viewer </h4>
            <div id="table-container">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group ">
                            <form id="vlilodata" >
                                <label for="sel1">Choose Employee:</label>
                                <select class="form-control" id="empcompid" name="empcompany">
                                    <option value="all">All</option>
                                    <?php
                                    // if ($_SESSION['UserType']==1){
                                        $sql=mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpID<>'admin' and employees.EmpStatusID = 1  and EmpCompID='WeDoInc-01' order by EmpLN asc");
                                        // $sql=mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpID<>'admin' and employees.EmpStatusID = 1  and EmpCompID='" . $_SESSION['CompID']  . "' order by EmpLN asc");
                                    // }
                                    // else{
                                    //     $sql=mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpID<>'admin' and employees.EmpStatusID = 1 and EmpCompID='" . $_SESSION['CompID']  . "'  or employees.EmpID='" . $_SESSION['id'] . "'  order by EmpLN asc");
                                    // }
                                    while($res=mysqli_fetch_array($sql)){
                                      ?>
                                    <option  value="<?php echo $res['EmpID']; ?>"><?php echo $res['EmpLN'] . ", " . $res['EmpFN']  . " "  . $res['EmpMN']; ?>  </option>
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
                        <input type="date" class="form-control"  id="dtp2"   value="<?php echo date("Y-m-d");?>">
                        
                        <button class="btn" id="obvi" type="button"><img src="assets/images/refreshicon.png" data-toggle="tooltip" data-placement="right" title="Refresh" width="25px"></button>
                        </div>
                    </div>
                </form>
            </div>
            <div id="tblprint">
                <table id="tab">
                    <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="10%">Name</th>
                        <th width="45%">Filing Date</th>
                        <th width="15%">Date From</th>
                        <th width="45%">Day To</th>
                        <th width="5%">Itinerary From</th>
                        <th width="10%">Itinerary To</th>
                        <th width="15%">Time From</th>
                        <th width="45%">Time To</th>
                        <th width="45%">Purpose</th>
                        <th width="45%">Cash Advance</th>
                        <th width="45%">Purpose</th>
                        <th width="45%">Status</th>
                    </tr>
                    </thead>
                    <tbody id="darviewer">
                        <?php
                            $ids=0;
                            if (! empty($resultdata)) {
                                foreach ($resultdata as $key => $value) {
                                    $ids=$ids+1;
                                    ?>
                                    <tr>
                                        <td><?php echo $ids; ?> </td>
                                        <td><?php echo $resultdata[$key]["LastName"]. ' '.$resultdata[$key]["FirstName"].' '.$resultdata[$key]["MiddleName"]; ?> </td>
                                        <td><?php echo date("F j, Y", strtotime($resultdata[$key]["Filing_Date"])); ?> </td>
                                        <td><?php echo date("F j, Y", strtotime($resultdata[$key]["OBDateFrom"])); ?> </td>
                                        <td><?php echo date("F j, Y", strtotime($resultdata[$key]["OBDateTo"])); ?> </td>
                                        <td><?php echo $resultdata[$key]["Itinerary_From"]; ?> </td>  
                                        <td><?php echo $resultdata[$key]["Itinerary_To"]; ?> </td>
                                        <td><?php echo date("h:i:s A", strtotime($resultdata[$key]["Time_From"])); ?> </td>
                                        <td><?php echo date("h:i:s A", strtotime($resultdata[$key]["Time_To"])); ?> </td>
                                        <td><?php echo $resultdata[$key]["Purpose"]; ?> </td>
                                        <td><?php echo number_format($resultdata[$key]["Cash_Advance"], 2); ?> </td>
                                        <td><?php echo $resultdata[$key]["CA_Purpose"]; ?> </td>
                                        <td><?php echo $resultdata[$key]["Status"]; ?> </td>
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
                    <button class="btn btn-info  btnprint" id="btnprint" onclick='printDiv();'><i class="fa fa-print"></i></button>
                    <button class="btn btn-success" onclick="exportToExcel('tab', 'user-data')"><i class="fa fa-file-excel-o" aria-hidden="true"></i> Export Table Data To Excel File</button> 
                </form>
            </div>
        </div>
    </div>
    </div>
    </div>

</body>
</html>