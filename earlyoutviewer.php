<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login'); }
  include 'w_conn.php';
  include 'ReportController.php';
  $dt1=date('Y-m-d');
  $dt2=date('Y-m-1');
  $dt3=date('Y-m-16');
  $ddt=date('Y-m-d', strtotime(date('Y-m-1')));
  $ddt2=date('Y-m-d', strtotime(date('Y-m-1')));
  $dt2=date('Y-m-d', strtotime('+1 days'));

  $handle = new ReportController();
  $resultdata = $handle->runQuery("SELECT employees.EmpID as Employees_ID,
    employees.EmpFN as FirstName,
    employees.EmpMN as MiddleName,
    employees.EmpLN as LastName,
    earlyout.FDate as Filing_Date,
    earlyout.Purpose as Purpose,
    earlyout.IS_remark as IS_Remark,
    earlyout.IS_updated as IS_Update,
    earlyout.HR_remark as HR_Remark,
    earlyout.DateTimeUpdated as DateTimeUpdated,
    earlyout.HR_updated as HR_Update,
    earlyout.DateTimeInputed as DateInput,
    status.StatusDesc as Status ,
    status.StatusID as statidd
    
    FROM employees
    INNER JOIN earlyout ON employees.EmpID=earlyout.EMPID 
    INNER JOIN status ON earlyout.Status=status.StatusID
    INNER JOIN empdetails on employees.EmpID=empdetails.EmpID   
    WHERE 1<0 and(DateTimeUpdated between '$dt1' and '$dt2') 
    order by LastName,earlyout.FDate desc 

    "); 
      if ($_SESSION['UserType']==1){

      }else{

      }
    
   
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
 date_default_timezone_set("Asia/Manila");
  $id=$_SESSION['id'];
  $isid=$_SESSION['EmpISID'];  
  $statement = $pdo->prepare("select * from accessrights where EmpID=:id");
  $statement->bindParam(':id' , $id);
  $statement->execute();
  $r = $statement->fetch();
 if ($r['eov']==1)
  	{
    header('location: 404?');	    
  	}
  else{
  }
  
  
  

  ?>
<!DOCTYPE html>
<html>
<head>

    <title>Early Out Reports</title>
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
  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
  
  <link rel="stylesheet" type="text/css" href="assets/css/style-reports.css">
  <script type="text/javascript" src="assets/js/script.js"></script>
  <script type="text/javascript" src="assets/js/script-reports.js"></script>
  <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.3.1.js"></script>
  <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/1.5.6/js/dataTables.buttons.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/1.5.6/js/buttons.html5.min.js"></script>




    <style
    
    .buttons-html5{
          background-image: none !important;
    background-color: green !important;
    color: #fff !important;
    border-radius: 5px !important;
    }
</style>
 
  <style>
    html body{
		font-family: Tahoma !important;
	}
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
  
$(document).on('click', '#btnprint', function(e) {

  var originalContents = document.body.innerHTML;
  $("#bodyData td").css("padding", "10px");
  $("#tab").css({
  overflow: 'hidden',
  height: '100%'
  });

  $("#tab").css({
  overflow: 'auto',
  height: 'auto'
  });


  var printContents = document.getElementById('tblprint').innerHTML;
  
  document.body.innerHTML = printContents;
  window.print();
  document.body.innerHTML = originalContents;

  });
});
</script>
<script type="text/javascript">
function exportToExcel(tableID, filename = ''){

    
      var downloadurl;
    var dataFileType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById("tab");
    var tableHTMLData = tableSelect.outerHTML.replace(/ /g, '%20');
    filename = "EOReports_" + document.getElementById("dtp1").value + "_" + document.getElementById("dtp2").value + "_" + document.getElementById("empcompid").options[document.getElementById("empcompid").selectedIndex].text;
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
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-3"></div>
        <!-- website content -->
        <div class="col-lg-9 wd-login">
              <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">EarlyOut Viewer </h4>
          
            <div class="row">
              <div class="col-lg-4">
                  <div class="form-group ">
                    <label for="sel1">Choose Employee:</label>
                    <select class="form-control" id="empcompid" name="empcompany">
                      <option value="all">All</option>
                      <?php 
                          if ($_SESSION['UserType']==1){
                              $sql=mysqli_query($con, "select * from employees where EmpStatusID=1 order by EmpLN asc");
                          }else{
                            // $sql=mysqli_query($con, "select * from employees where EmpStatusID=1 order by EmpLN asc");

                                $sql=mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID 
                                where employees.EmpID<>'admin' and (EmpISID='" . $_SESSION['id'] . "' or employees.EmpID='" . $_SESSION['id'] . "') 
                                and employees.EmpStatusID=1  order by EmpLN asc");
                          }
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
                    <input type="date" id="dtp1" value="<?php echo date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));?>"class="form-control">
                      <label>To:</label>
                    <input type="date" id="dtp2" value="<?php echo date("Y-m-d");?>" class="form-control">
                    <button class="btn eorefresh"  type="button"><img src="assets/images/refreshicon.png" data-toggle="tooltip" data-placement="right" title="Refresh" width="25px"></button>
                  </div>
              </div>
            </div>
            <br>
            
            <div class="btn">
              <form action="" method="post">
                  <!-- <button type="submit" name='export1' value="Export to Excel"class="btn btn-info">Export to Excel</button> -->
                        <button class="btn btn-info  btnprint" type="button" id="btnprint"><i class="fa fa-print" aria-hidden="true"></i></button>
                          <button class="btn btn-success" onclick="exportToExcel('tab', 'user-data')"><i class="fa fa-file-excel-o" aria-hidden="true"></i> Export Table Data To Excel File</button>
              </form>
            </div>
                            
            <div id="tblprint">
                <table id="tab" class="table table-responsive">
                    <thead>
                      <tr>
                        <th >No</th>
                        <th width="25%">Name</th>
                        <th width="25%">Filing Date</th>
                        <th width="25%">Purpose</th>
                        <th width="25%">Status</th>       

                      </tr>
                    </thead>
                  <tbody id="eovw">
      
                        <?php
                          $ids=0;
                        if (! empty($resultdata)) {
                            foreach ($resultdata as $key => $value) {
                              $ids=$ids+1;
                                ?>
                            
                                <tr>
                                <td><?php echo $ids; ?></td>
                                <td><?php echo $resultdata[$key]["LastName"] . ',  '. $resultdata[$key]["FirstName"] ; ?></td>
                                <td><?php echo date("F j, Y h:i:s A", strtotime($resultdata[$key]["Filing_Date"])); ?></td>
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
        </div>
    </div>
  </div>
      

</body>
</html>