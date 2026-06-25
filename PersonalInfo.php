<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login'); 
}

include 'ReportController.php';
$handle = new ReportController();
$resultdata = $handle->runQuery("SELECT Companies.CompanyDesc as Companies,EmpDetails.EmpID as Employees_ID , t.EmpFN as FirstName ,t.EmpLN as LastName ,t.EmpMN as MiddleName,EmpProfiles.EmpGender as Gender, EmpProfiles.EmpDOB as Birthdate,TIMESTAMPDIFF(YEAR, EmpProfiles.EmpDOB, NOW()) AS Age, EmpProfiles.EmpCS as Status,hmo.HMO_PROVIDER as HMO_Name, EmpProfiles.EmpHMONumber as Employee_HM0, EmpProfiles.EmpMobile as MobileNo,CONCAT(EmpProfiles.EmpAddress1, ' ' ,EmpProfiles.EmpAddDis) as Street_No_Name,  EmpProfiles.EmpAddCity as City, EmpProfiles.EmpAddProv as Province, EmpProfiles.EmpAddZip as Zip_Code, EmpProfiles.EmpAddCountry as Country, EmpProfiles.EmpCitezen as Citizen, EmpProfiles.EmpEmail as Email, EmpProfiles.EmpReligion as Religion
                                                                          
                              FROM EmpDetails 
                              LEFT JOIN hmo ON hmo.HMO_ID=EmpDetails.HMO_ID
                              LEFT JOIN Companies ON EmpDetails.EmpCompID=Companies.CompanyID
                              LEFT JOIN EmpProfiles ON EmpProfiles.EmpID=EmpDetails.EmpID                       
                              INNER JOIN Employees as t ON  EmpProfiles.EmpID=t.EmpID
                              LEFT JOIN EStatus ON EStatus.ID=t.EmpStatusID WHERE EmpDetails.EmpCompID='" . $_SESSION['CompID']  . "'
                             order by LastName ASC ");
 $todayF =date("Y-m-d"); 



?>
<!DOCTYPE html>
<html>
<head>

    <title>WeDo Dashboard</title>
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

  <link rel="stylesheet" type="text/css" href="assets/css/style-reports.css">
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


<script type="text/javascript"> 
    $(document).ready(function() {
    $(".buttons-html5").remove("span");
    $('#tab').DataTable( {
        dom: 'Bfrtip',
        buttons: [
            'excelHtml5',
            'csvHtml5'
        ]
    } );
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
  
    <styLe>
 

   .table-bordered .darth{
    color: black;
    text-align: center;
    padding: 10px;
    background-color: #faf5f5;
    }
    .table-bordered .col-darth{    
      width: 157px;
    }
    .td-dar{
    text-align: center;
    padding: 4px;
  }
    .table-bordered .td-dar{
        text-align: center;
        padding: 6px;
}
.btn-pos-right{
/* padding-left: 0px; *//* position: absolute; */righ: 0px;position: absolute;right: 28px;top: 4p;/* padding: 12px; */
padding-top: 6px;
padding-bottom: 18px;

}
.btn-pos-right-dtp{



}
.btn-pos-right2{
/* padding-left: 0px; *//* position: absolute; */righ: 0px;position: absolute;right: 28px;top: 4p;/* padding: 12px; */
padding-top: 10px;
padding-bottom: 18px;

}
.dtpar label {
  display: inline-block;
  padding: 0px 5px 0px 5px;
}
.dtpar input {
  display: inline-block;
  width: auto;
}
.title1{
    margin-left: 14px;
}
.home-title{
  display: inline-block;

}
.dtpar{
  display: inline-block;
  float: right;
}
.dtpar p{
  display: inline-block;
  font-size: 13px;
}
.dtpar label{
  font-size: 13px;;
}
.dtpar input{
  font-size: 13px;
  width:149px;
}
.table-fixed{
  width: 100%;
}
/*.table-scroll{
  /*width:100%; */
/*  display: block;
  empty-cells: show;
  overflow-y: scroll;*/
  /* Decoration */
/*  border-spacing: 0;
}*/

.table-scroll thead{
  background-color: #f1f1f1;  
  position:relative;
  display: block;
  width:100%;
}
table{
  display: block;
    empty-cells: show;
  overflow-y: scroll;
  overflow-x: scroll;
      overflow-x: scroll;
    height: 500px;
}
.table-scroll tbody{
  /* Position */
  /*display: block; position:relative;
  width:100%;*/
  /* Decoration */
}

/*.table-scroll tr{
  width: 100%;
  display:flex;
}*/

/*.table-scroll td,.table-scroll th{
  flex-basis:100%;
  flex-grow:2;
  display: block;
  padding: 10px;
  text-align:center;
}*/
.table-scroll .td-act{
 flex-basis:200%;
 text-align: left;
}
/* Other options */

/*.table-scroll.small-first-col td:first-child,
.table-scroll.small-first-col th:first-child{
  flex-basis:60%;
  flex-grow:1;
}*/

/*.table-scroll tbody tr:nth-child(2n){
  border-bottom: 1px solid #ddd;
  border-top: 1px solid #ddd;
}
*/
.body-half-screen{
  max-height: 40vh;
}
.body-half-screen tr td:last-child{
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
/*.small-col{flex-basis:10%;}*/
 body {
    font-size: 0.95em;
    font-family: arial;
    color: #212121;
}
tr:hover{
    background: #ff000038;
}
th {
    background: #E6E6E6;
    border-bottom: 1px solid #000000;
}

#table-container {
    width: 850px;
    margin: 50px auto;
}

table#tab {
    border-collapse: collapse;
    width: 100%;
}

table#tab th, table#tab td {
    border-bottom: 1px solid #E0E0E0;
    padding: 8px 15px;
    text-align: left;
    font-size: 0.95em;
        white-space: nowrap;
}


#btnExport {
    padding: 10px 40px;
    background: #499a49;
    border: #499249 1px solid;
    color: #ffffff;
    font-size: 0.9em;
    cursor: pointer;
}
  </style>
</head>
<body style="background-image: none">
   <?php 
    include 'includes/header.php';
  ?>
  <br>
   <div  class="lg-hide-logo" id="lg-hide-logo">
        <img width="150px" src="<?php echo $_SESSION['CompanyLogo']; ?>">
        <h4><?php echo $_SESSION['CompanyName']; ?></h4>
    </div>
  <br>

      <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9 wd-login">
            



        <div class="container-format">
    <div style="padding: 0px 15px 0px 15px;">
        <p class="home-title">Alpha List</p> 
        <div class="dtpar">
            <p>Date Parameters:</p>
            <label>From:</label>
            <input type="date" class="form-control">
              <label>To:</label>
            <input type="date" class="form-control">
            <button class="btn btn-success"  type="button"><i  class="fa fa-refresh" aria-hidden="true"></i></button>
        </div>
         </div>
 
  <br>
      <p>Export to:</p>
     <div id="tblprint">
  <table  id="tab" >
    <thead>
      <tr >
        <th >NO</th>
        <!-- <th width="20%">Company</th> -->
        <th >EMPLOYEE_ID_NO</th>
        <th >Name </th>
        <th >Gender</th>
        <th>Birthdate</th>
        <th >Age </th>
        <th >Status</th>
        <th >HMO Provider</th>
        <th >HMO ID. No.</th>
        <th >Contact No.</th>
        <th >Complete Mailing Address</th>
        <th >Citizenship</th>
        <th >Email</th>
        <th >Religion</th>
      
      </tr>
    </thead>
    <tbody  id="adddar"> 
      <?php
              $ids=0;
            if (! empty($resultdata)) {
                foreach ($resultdata as $key => $value) {
                  $ids=$ids+1;
                    ?>
                     <tr>
                    <td><?php echo $ids; ?> </td><!-- 
                    <td><?php echo $resultdata[$key]["Companies"]; ?> </td> -->
                    <td><?php echo $resultdata[$key]["Employees_ID"]; ?> </td>
                    <td><?php echo $resultdata[$key]["LastName"]. ' '.$resultdata[$key]["FirstName"].' '.$resultdata[$key]["MiddleName"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Gender"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Birthdate"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Age"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Status"]; ?> </td>
                    <td><?php echo $resultdata[$key]["HMO_Name"]; ?> </td>  
                    <td><?php echo $resultdata[$key]["Employee_HM0"]; ?> </td>
                    <td><?php echo $resultdata[$key]["MobileNo"]; ?> </td>
                    <td class="address-info"><?php echo $resultdata[$key]["Street_No_Name"] . ' ' . $resultdata[$key]["City"] . ' ' . $resultdata[$key]["Province"] . ' ' .$resultdata[$key]["Zip_Code"] . ' ' . $resultdata[$key]["Country"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Citizen"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Email"]; ?> </td>
                    <td><?php echo $resultdata[$key]["Religion"]; ?> </td>
                    
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
         <!--        <button type="submit" 
                    name='export' value="Export to Excel"
                    class="btn btn-info">Export to Excel</button> -->
                        <button class="btn btn-info  btnprint" id="btnprint" onclick='printDiv();'><i class="fa fa-print" aria-hidden="true"></i></button>
            </form>
        </div>
</div>
  </div>
       </div>
     </div>
</body>
</html>
