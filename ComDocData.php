<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else { header ('location: login'); }

  include 'ReportController.php';
  $handle = new ReportController();
 $resultdata = $handle->runQuery("SELECT Companies.CompanyDesc as Companies,EmpDetails.EmpID as Employees_ID, t.EmpFN as FirstName ,t.EmpLN as LastName ,t.EmpMN as MiddleName , EmpProfiles.EmpSSS as SSS, EmpProfiles.EmpPINo as Pagibig,EmpProfiles.EmpPHNo as PhilHealth,  EmpProfiles.EmpUMIDNo as UMID, EmpProfiles.EmpTIN as TIN, EmpProfiles.EmpPPNo as Passport_No,EmpProfiles.EmpPPED as Passport_Expiry_Date, EmpProfiles.EmpPPIA as Issuing_Authority

                              FROM EmpDetails 
                              LEFT JOIN EmpProfiles ON EmpProfiles.EmpID=EmpDetails.EmpID
                              LEFT JOIN Employees as t ON  EmpProfiles.EmpID=t.EmpID 
                              LEFT JOIN Companies ON EmpDetails.EmpCompID=Companies.CompanyID                                             
                              order by LastName ASC ");


 $todayF =date("Y-m-d h:i:s A"); 
if (isset($_POST["export"])) {
    $filename = "Compliance_Document_Data". $todayF .".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
                $isPrintHeader = false;
                if (! empty($resultdata)) {
                    foreach ($resultdata as $row) {
                        if (! $isPrintHeader) {
                            echo implode("\t", array_keys($row)) . "\n";
                            $isPrintHeader = true;
                        }
                        echo implode("\t", array_values($row)) . "\n";
                    }
                }
    exit();
}
  ?>
<!DOCTYPE html>
<html>
<head>

    <title>Compliance Document Data</title>
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
    $(document).ready(function() {
    $('#tab').DataTable( {
        dom: 'Bfrtip',
        buttons: [
            'excelHtml5',
            'csvHtml5'
        ]
    });
} );
  </script>
    <style>
    .buttons-html5{
          background-image: none !important;
    background-color: green !important;
    color: #fff !important;
    border-radius: 5px !important;
    }
</style>
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
            <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Compliance Document Data </h4>
              <div id="table-container">
            
              <div class="row">
                <div class="col-lg-4">
                    <div class="form-group ">
                      <form id="vlilodata" >
                      <label for="sel1">Choose Employee:</label>
                      <select class="form-control" id="empcompid" name="empcompany">
                        <option value="all">All</option>

                        <?php
                      $sql=mysqli_query($con, "select * from employees order by EmpLN asc");
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
            <input type="date" class="form-control" id="dtp2" value="<?php echo date("Y-m-d");?>">

            <button class="btn btn-success" id="comcoda" type="button"><i  class="fa fa-refresh" aria-hidden="true"></i></button>
        </div>
                </div>
               </form>
              </div>
                <p>Export to:</p>
                  <div id="tblprint">
        <table id="tab">
            <thead>
                <tr>
                    <th width="5%">No</th><!-- 
                    <th width="10%">Company</th> -->
                    <th width="5%">Employee_ID</th>
                    <th width="20%">Name</th>
                    <th width="45%">SSS</th>
                    <th width="15%">Pag-ibig</th>
                    <th width="45%">Philhealth</th>
                    <th width="5%">UMID</th>
                    <th width="10%">TIN</th>
                    <th width="15%">Passport Number</th>
                    <th width="45%">Passport Expiry Date</th>
                    <th width="45%">Issuing Authority</th>
                    <th width="45%">Driver License</th>
                    

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
                    <!-- <td><?php echo $resultdata[$key]["Companies"]; ?> </td> -->
                    <td><?php echo $resultdata[$key]["Employees_ID"]; ?> </td>
                    <td><?php echo $resultdata[$key]["LastName"]. ' '.$resultdata[$key]["FirstName"].' '.$resultdata[$key]["MiddleName"]; ?> </td>
                    <td><?php echo $resultdata[$key]["SSS"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Pagibig"]; ?> </td>
                    <td><?php echo $resultdata[$key]["PhilHealth"]; ?> </td>
                    <td><?php echo $resultdata[$key]["UMID"]; ?> </td>  
                    <td><?php echo $resultdata[$key]["TIN"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Passport_No"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Passport_Expiry_Date"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Issuing_Authority"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Issuing_Authority"]; ?> </td>
          
              
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
                <!-- <button type="submit" name='export' value="Export to Excel"class="btn btn-info">Export to Excel</button> -->
                        <button class="btn btn-info  btnprint" id="btnprint" onclick='printDiv();'><i class="fa fa-print" aria-hidden="true"></i></button>
            </form>
        </div>
    </div>
         </div>
       </div>
     </div>

</body>
</html>