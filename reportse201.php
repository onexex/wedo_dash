<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login'); 
}


include 'ReportController.php';
$handle = new ReportController();
$resultdata = $handle->runQuery("SELECT Companies.CompanyDesc as Companies,EmpDetails.EmpID as Employees_ID , t.EmpFN as FirstName ,t.EmpLN as LastName ,t.EmpMN as MiddleName ,EmpProfiles.EmpGender as Gender ,EmpProfiles.EmpDOB as BirthDate, TIMESTAMPDIFF(YEAR, EmpProfiles.EmpDOB, NOW()) AS Age,EmpProfiles.EmpCS as Status,EmpProfiles.EmpMobile as ContactNo, EmpProfiles.EmpCitezen as Citizen, EmpProfiles.EmpReligion as Religion,EmpProfiles.EmpEmail as Email, CONCAT(EmpProfiles.EmpAddress1, ' ' ,EmpProfiles.EmpAddDis) as Street_No_Name,  EmpProfiles.EmpAddCity as City, EmpProfiles.EmpAddProv as Province, EmpProfiles.EmpAddZip as Zip_Code, EmpProfiles.EmpAddCountry as Country,  Departments.DepartmentDesc as Departments, Positions.PositionDesc as Positions,Joblevel.JobLevelDesc as Joblevel, EmpDetails.EmpDateHired as DateHired, EStatus.StatusEmpDesc as StatusDesc, EmpStatus.EmpStatDesc as Employment_Status, CONCAT(WorkSchedule.TimeFrom, '-',WorkSchedule.TimeTo) as WorkSchedule,Workdays.WDesc as Workdays,agency.AgencyName as Agency,CONCAT(Employees.EmpFN,' ', Employees.EmpLN,' ' ,Employees.EmpMN) as IMMEDIATE_SUPERIOR , EmpProfiles.EmpSSS as SSS, EmpProfiles.EmpPHNo as PhilHealth, EmpProfiles.EmpPINo as Pagibig, EmpProfiles.EmpUMIDNo as UMID, EmpProfiles.EmpTIN as TIN, EmpProfiles.EmpPPNo as Passport_No,EmpProfiles.EmpPPED as Passport_Expiry_Date, EmpProfiles.EmpPPIA as Issuing_Authority,  EmpDetails.EmpDateResigned as DateResigned,EmpDetails2.EmpBasic as Basic_Salary,EmpDetails2.EmpHRate as Hourly_Rate,EmpDetails2.EmpAllowance as Allowance, hmo.HMO_ID as HMO_ID,hmo.HMO_PROVIDER as HMO_Name,EmpProfiles.EmpHMONumber as HMO_No,EmpProfiles.EmpPP as Previous_Position,EmpProfiles.EmpPPSD as Previous_Position_SD,EmpProfiles.EmpPPDept as Previous_Position_Dep,EmpProfiles.EmpPPPos as Previous_Position
                                                                          
                              FROM EmpDetails 
                              LEFT JOIN EmpStatus ON EmpDetails.EmpStatID=EmpStatus.EmpStatID
                              LEFT JOIN hmo ON hmo.HMO_ID=hmo.HMO_ID
                              LEFT JOIN agency ON agency.AgencyID=EmpDetails.AgencyID
                              LEFT JOIN WorkSchedule ON EmpDetails.EmpWSID=WorkSchedule.WorkSchedID
                              LEFT JOIN Workdays ON EmpDetails.EmpRDID=Workdays.WID
                              LEFT JOIN Companies ON EmpDetails.EmpCompID=Companies.CompanyID
                              LEFT JOIN EmpDetails2 ON EmpDetails2.EmpID = EmpDetails.EmpID
                              LEFT JOIN EmpProfiles ON EmpProfiles.EmpID=EmpDetails.EmpID                       
                              INNER JOIN Employees as t ON  EmpProfiles.EmpID=t.EmpID
                              LEFT JOIN Employees  ON EmpDetails.EmpISID=Employees.EmpID
                              LEFT JOIN EStatus ON EStatus.ID=t.EmpStatusID
                              LEFT JOIN Positions ON Positions.PSID=t.PosID
                              LEFT JOIN Joblevel ON Joblevel.JobLevelID=Positions.EmpJobLevelID
                              LEFT JOin Departments ON Positions.DepartmentID=Departments.DepartmentID 
                              WHERE EmpDetails.EmpCompID='" . $_SESSION['CompID']  . "'
                              order by LastName ASC ");
 $todayF =date("Y-m-d"); 


?>
<!DOCTYPE html>
<html>
<head>

    <title>Alpha List Generator</title>
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
  <script src="assets/js/script-reports.js"></script>
  <script src="assets/js/script.js"></script>
  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
  <script src="assets/js/script-reports.js"></script>
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

      <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9 wd-login">
                   <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Alpha List Generator </h4>



        <div class="container-format">
    <div style="padding: 0px 15px 0px 15px;">
        <div class="dtpar">
            <p>Date Parameters:</p>
            <label>From:</label>
            <input type="date" class="form-control">
              <label>To:</label>
            <input type="date" class="form-control">
            <button class="btn btn-danger"  type="button">Refresh</button>
        </div>
         </div>
 
  <br>
           <p>Export to:</p>
               <div id="tblprint">
  <table  id="tab" >
    <thead>
      <tr >
        <th width="20%">NO</th>
        <th width="20%">EMPLOYEE_ID_NO</th>
        <th width="20%">LAST_NAME</th>
        <th width="20%">FIRST_NAME</th>
        <th width="20%">MIDDLE_NAME </th>
        <th width="20%">GENDER </th>
        <th width="20%">BIRTHDATE</th>
        <th width="20%">AGE</th>
        <th width="20%">STATUS </th>
        <th width="20%">Contact</th>
        <th >CITIZENSHIP</th>
        <th >EMAIL</th>
        <th >RELIGION</th>
        <th >COMPANY</th>
        <th >DEPARTMENT</th>
        <th >POSITION</th>
        <th >COMPLETE_MAILING_ADDRESS</th>
        <th >SSS</th>
        <th >PHILHEALTH</th>
        <th >PAG_IBIG </th>
        <th >UMID_NO.</th>
        <th >DATE_HIRED  </th>
        <th >EMPLOYMENT_STATUS </th>
        <th >CLASSIFICATION </th>
        <th >IMMEDIATE_SUPERIOR </th>
        <th >WORK_DAYS</th>
        <th >SCHEDULE</th>
        <th >BASIC_SALARY</th>
        <th >ALLOWANCE </th>
        <th >HOURLY_RATE </th>
        <th >DATE_RESIGNED </th>
        <th >PREVIOUS_POSITION </th>
        <th >PASSPORT_NUMBER</th>
        <th >PASSPORT_EXPIRY_DATE</th>
        <th >ISSUING_AUTHORTY</th>
        <th >JOB_LEVEL</th>
        <th >HMO_PROVIDER</th>
        <th >HMO_ID_NO</th>



      </tr>
    </thead>
    <tbody  id="adddar"> 
      <?php
        include 'w_conn.php';

$statement = $pdo->prepare("SELECT EmpDetails.Seq_ID,hmo.HMO_ID,hmo.HMO_PROVIDER,agency.AgencyID,agency.AgencyName,EmpDetails.EmpID,EmpDetails.EmpDateHired                               , EmpDetails.EmpDateResigned,EmpStatus.EmpStatDesc,EmpStatus.EmpStatID,WorkSchedule.WorkSchedID,
                                  WorkSchedule.TimeFrom,WorkSchedule.TimeTo,Workdays.WID,Workdays.WDesc,Companies.CompanyID,Companies.CompanyDesc,EmpDetails2.EmpBasic,
                                     EmpDetails2.EmpHRate,EmpDetails2.EmpAllowance,Employees.EmpID as ids, Employees.EmpFN AS fn,Employees.EmpLN as ln,Employees.EmpMN as mn,
                                     EmpProfiles.EmpAddress1,EmpProfiles.EmpDOB,EmpProfiles.EmpGender,EmpProfiles.EmpEmail,EmpProfiles.EmpMobile,
                                     EmpProfiles.EmpPPNo,EmpProfiles.EmpPINo,EmpProfiles.EmpPHNo,EmpProfiles.EmpSSS,EmpProfiles.EmpTIN,
                                     EmpProfiles.EmpUMIDNo,EmpProfiles.EmpCitezen,EmpProfiles.EmpReligion,EmpProfiles.EmpPhone,EmpProfiles.EmpPPIA ,
                                     EmpProfiles.EmpPP,EmpProfiles.EmpPPSD,EmpProfiles.EmpPPDept,EmpProfiles.EmpPPPos,EmpProfiles.EmpCS,EmpProfiles.EmpPPED,
                                     EmpProfiles.EmpHMONumber ,
                                     EmpProfiles.EmpAddDis,EmpProfiles.EmpAddCity,EmpProfiles.EmpAddProv,EmpProfiles.EmpAddZip,EmpProfiles.EmpAddCountry,
                                     EmpProfiles.EmpPPAth,EStatus.StatusEmpDesc,t.EmpFN,t.EmpLN,t.EmpMN,Positions.PositionDesc,Positions.PSID,Departments.DepartmentDesc,departments.DepartmentID,Joblevel.JobLevelDesc,Departments.DepartmentID
                                                                         
                              FROM EmpDetails 
                              LEFT JOIN EmpStatus ON EmpDetails.EmpStatID=EmpStatus.EmpStatID
                              LEFT JOIN hmo ON hmo.HMO_ID=hmo.HMO_ID
                              LEFT JOIN agency ON agency.AgencyID=EmpDetails.AgencyID
                              LEFT JOIN WorkSchedule ON EmpDetails.EmpWSID=WorkSchedule.WorkSchedID
                              LEFT JOIN Workdays ON EmpDetails.EmpRDID=Workdays.WID
                              LEFT JOIN Companies ON EmpDetails.EmpCompID=Companies.CompanyID
                              LEFT JOIN EmpDetails2 ON EmpDetails2.EmpID = EmpDetails.EmpID
                              LEFT JOIN Employees  ON EmpDetails.EmpISID=Employees.EmpID
                              LEFT JOIN EmpProfiles ON EmpProfiles.EmpID=EmpDetails.EmpID                       
                              INNER JOIN Employees as t ON  EmpProfiles.EmpID=t.EmpID
                              LEFT JOIN EStatus ON EStatus.ID=t.EmpStatusID
                              LEFT JOIN Positions ON Positions.PSID=t.PosID
                              LEFT JOIN Joblevel ON Joblevel.JobLevelID=Positions.EmpJobLevelID
                              LEFT JOin Departments ON Positions.DepartmentID=Departments.DepartmentID order by t.EmpLN ASC ");
$statement->execute();

$id=0;
while ($row = $statement->fetch()){
$resultset[] = $row;
  $id=$id+1;
?>
  <tr>              <td > <?php echo $id; ?></td>
                    <td > <?php echo $row['EmpID']; ?></td>
                    <td> <?php echo $row['EmpLN']; ?></td>
                    <td > <?php echo $row['EmpFN']; ?></td>
                    <td > <?php echo $row['EmpMN']; ?></td>
                    <td > <?php echo $row['EmpGender']; ?></td>
                    <td > <?php echo $row['EmpDOB']; ?></td>
                    <td > <?php echo date_diff(date_create($row['EmpDOB']), date_create('today'))->y, "\n"; ?></td>
                    <td > <?php echo $row['EmpCS']; ?></td>
                    <td > <?php echo $row['EmpMobile']; ?></td>
                    <td > <?php echo $row['EmpCitezen']; ?></td>
                    <td > <?php echo $row['EmpEmail']; ?></td>
                    <td > <?php echo $row['EmpReligion']; ?></td>
                    <td > <?php echo $row['CompanyDesc']; ?></td>
                    <td > <?php echo $row['DepartmentDesc']; ?></td>
                    <td > <?php echo $row['PositionDesc']; ?></td>
                    <td > <?php echo $row['EmpAddress1'] . " ". $row['EmpAddDis'] . " ". $row['EmpAddCity'] . " ". $row['EmpAddProv'] . " ". $row['EmpAddZip'] . " ". $row['EmpAddCountry'] ?></td>
                    <td > <?php echo $row['EmpSSS']; ?></td>
                    <td > <?php echo $row['EmpPHNo']; ?></td>
                    <td > <?php echo $row['EmpPINo']; ?></td>
                    <td > <?php echo $row['EmpUMIDNo']; ?></td>
                    <td > <?php echo $row['EmpDateHired']; ?></td>
                    <td > <?php echo $row['StatusEmpDesc']; ?></td>
                    <td > <?php echo $row['EmpStatDesc']; ?></td>
                    <td > <?php echo $row['fn'] . " " .  $row['mn'] . " " .  $row['ln']; ?></td>
                    <td > <?php echo $row['WDesc']; ?></td>
                    <td > <?php echo $row['TimeFrom'] . "-" . $row['TimeTo'];?></td>
                    <td > <?php echo $row['EmpBasic']; ?></td>
                    <td > <?php echo $row['EmpAllowance']; ?></td>
                    <td > <?php echo $row['EmpHRate']; ?></td>
                    <td > <?php echo $row['EmpDateResigned']; ?></td>
                    <td > <?php echo $row['EmpPPPos']; ?></td>
                    <td > <?php echo $row['EmpPPNo']; ?></td>
                    <td > <?php echo $row['EmpPPED']; ?></td>
                    <td > <?php echo $row['EmpPPIA']; ?></td>
                    <td > <?php echo $row['JobLevelDesc']; ?></td>
                    <td > <?php echo $row['HMO_PROVIDER']; ?></td>
                    <td > <?php echo $row['EmpHMONumber']; ?></td>
                  </tr>
<?php

}


?>



      
    </tbody>
  </table>
</div>
  <br>
         <div class="btn">
            <form action="" method="post">
           
                    <button class="btn btn-info  btnprint" id="btnprint" onclick='printDiv();'><i class="fa fa-print" aria-hidden="true"></i></button>
            </form>
        </div>
</div>
  </div>
       </div>
     </div>
</body>
</html>
