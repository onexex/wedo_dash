<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else { header ('location: login'); }

  include 'ReportController.php';
  $handle = new ReportController();
  $resultdata = $handle->runQuery(" SELECT b.EmpID as Employees_ID,
    b.EmpFN as FirstName,
    b.EmpMN as MiddleName,
    b.EmpLN as LastName,
    a.DateFiling as DateFiling,
    a.TimeFiling as TimeFiling,
    a.TimeIn as Time_IN,
    a.TimeOut as Time_OUT,
    a.Duration as Duration,
    a.Purpose as Purpose,
    a.ISReason as IS_Reason,
    a.HRReason as HR_Reason,
    a.ISUpdate as IS_Update,
    a.HRUpdate as HR_Update,
    a.DateTimeUpdate as Date_TimeUpdate,
    a.DateTimeInputed as DateTimeInputed,
    c.StatusDesc as Status

    FROM otattendancelog as a 
    INNER JOIN employees as b ON a.EmpID=b.EmpID
    INNER JOIN status as c ON a.Status=c.StatusID WHERE StatusID=1 or StatusID=2 or StatusID=4");

 
  ?>
<!DOCTYPE html>
<html>
<head>

    <title>Overtime Viewer</title>
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
  <style>
body {
    font-size: 0.95em;
    font-family: arial;
    color: #212121;
}
  html body{
		font-family: Tahoma !important;
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

    <style>
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
    filename = "OTReports_" + document.getElementById("dtp1").value + "_" + document.getElementById("dtp2").value + "_" + document.getElementById("empcompid").options[document.getElementById("empcompid").selectedIndex].text;
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

      <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9 wd-login">
           <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Overtime Viewer </h4>
              <div id="table-container">
              <div class="row">
                <div class="col-lg-4">
                    <div class="form-group ">
                      <label for="sel1">Choose Employee:</label>
                        <select class="form-control" id="empcompid" name="empcompany">
                            <option value="all">All</option>
                            <?php
                              $sql=mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpID<>'admin' and employees.EmpStatusID = 1  and EmpCompID='WeDoInc-01' order by EmpLN asc");
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
            <input type="date" class="form-control" id="dtp2" value="<?php echo date("Y-m-d");?>">
            <button class="btn btnovb"  type="button"><img src="assets/images/refreshicon.png" data-toggle="tooltip" data-placement="right" title="Refresh" width="25px"></button>
        </div>
                </div>
               
              </div>
    <div id="tblprint">
        <table id="tab">
            <thead id="tabth">
                <tr>
                    <th width="5%">No</th>
                    <th width="10%">Name</th>
                    <th width="15%">DateTimeFiling</th>
                    <th width="45%">TimeIN</th>
                    <th width="45%">TimeOUT</th>
                    <th width="45%">Duration</th>
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
                    <td><?php echo $ids; ?></td>
                    <td><?php echo $resultdata[$key]["LastName"] . ',  '. $resultdata[$key]["FirstName"] ; ?></td>
                    <td><?php echo date("F j, Y", strtotime($resultdata[$key]["DateFiling"])) . ' '. date("h:i:s A", strtotime($resultdata[$key]["TimeFiling"])); ?></td>
                    <td><?php echo date("F j, Y h:i:s A", strtotime($resultdata[$key]["Time_IN"])); ?></td>
                    <td><?php echo date("F j, Y h:i:s A", strtotime($resultdata[$key]["Time_OUT"])); ?></td>
                    <td><?php echo $resultdata[$key]["Duration"]; ?></td>
                    <td><?php echo $resultdata[$key]["Purpose"]; ?></td>
                    <td><?php echo $resultdata[$key]["Status"]; ?></td>
              
                </tr>
             <?php
                }
            }
            ?>
      </tbody>
        </table>
      </div>
        <br>
        
        <div class="btn">
            <form action="" method="post">
                <!-- <button type="submit" name='export1' value="Export to Excel"class="btn btn-info">Export to Excel</button> -->
                  <button class="btn btn-info  btnprint" id="btnprint" onclick='printDiv();'><i class="fa fa-print" aria-hidden="true"></i></button>
                  <button class="btn btn-success" onclick="exportToExcel('tab', 'user-data')"><i class="fa fa-file-excel-o" aria-hidden="true"></i> Export Table Data To Excel File</button>
            </form>
        </div>
    </div>
         </div>
       </div>
     </div>

</body>
</html>