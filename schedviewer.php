<?php

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else { header ('location: login'); }

    include 'ReportController.php';
    $handle = new ReportController();

    $dt=date('Y-m-d');
    $dt2=date('Y-m-3');
    $dt3=date('Y-m-16');
    $ddt=date('Y-m-d', strtotime(date('Y-m-1')));
    $ddt2=date('Y-m-d', strtotime(date('Y-m-1')));
    if ($dt>$dt2 && $dt<$dt3){
        $dt1=$ddt2;
    }else{ 
        $dt1=$ddt;
    }
    $dt2=date('Y-m-d', strtotime('+1 days'));

    if ($_SESSION['UserType']==1){
        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto)
        ");
    }
    else if ($_SESSION['UserType']==2){

        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            INNER JOIN empdetails AS e ON e.EmpID=a.EmpID
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto) OR e.EmpID ='" . $_SESSION['id'] . "' AND e.EmpISID='" . $_SESSION['id'] . "'
        ");
    }
    else
    {
        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto)
        ");
    }

?>

<!DOCTYPE html>
<html lang="en">

<head>
    
    <title>Schedule Viewer</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon"> 
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
    <script src="assets/js/schedViewScript.js"></script>
    <script type="text/javascript" src="assets/js/script-reports.js"></script>
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

</head>

<body>
    <?php 
        include 'includes/header.php';
    ?>

    <div class="lg-hide-logo" id="lg-hide-logo">
        <img width="150px" src="<?php echo $_SESSION['CompanyLogo']; ?>">
        <h4><?php echo $_SESSION['CompanyName']; ?></h4>
    </div>
    <div class="w-container">
        <div class="row">
            <div class="col-lg-3"></div>
            <!-- website content -->
            <div class="col-lg-9 wd-login">
                <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Schedule Viewer
                </h4>
                <div id="container" style="padding: 40px; height: 100%;">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group ">
                                <form id="vlilodata">
                                    <label for="sel1">Choose Employee:</label>
                                    <select class="form-control" id="empcompid" name="empcompany">
                                        <option value="all">All</option>

                                        <?php
                                            if ($_SESSION['UserType']==1){
                                                $sql=mysqli_query($con, "SELECT * FROM employees INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID WHERE employees.EmpStatusID = 1 AND employees.EmpID<>'admin' ORDER BY EmpLN ASC");
                                            }
                                            else if ($_SESSION['UserType']==2){
                                                $sql=mysqli_query($con, "SELECT * FROM employees INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID WHERE employees.EmpStatusID = 1 AND employees.EmpID<>'admin' AND EmpCompID='" . $_SESSION['CompID']  . "' AND EmpISID='" . $_SESSION['id'] . "' ORDER BY EmpLN ASC");
                                            }
                                            else {
                                                $sql=mysqli_query($con, "SELECT * FROM employees INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID WHERE employees.EmpStatusID = 1 AND employees.EmpID<>'admin' ORDER BY EmpLN ASC");
                                            }
                                            while($res=mysqli_fetch_array($sql)){
                                        ?>
                                        <option value="<?php echo $res['EmpID']; ?>">
                                            <?php echo $res['EmpLN'] . ", " . $res['EmpFN'] . " " . $res['EmpMN']; ?>
                                        </option>
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
                                <input type="date" class="form-control" id="dtp1" value=<?php $dt=date('Y-m-d'); $dt2=date('Y-m-1'); $dt3=date('Y-m-16'); $ddt=date('Y-m-d', strtotime(date('Y-m-1'))); $ddt2=date('Y-m-d', strtotime(date('Y-m-1'))); if ($dt>$dt2 && $dt<$dt3){ echo $ddt2; }else{ echo $ddt; }?>>
                                <!-- <input type="text" class="form-control" id="date1"> -->
                                <label>To:</label>
                                <input type="date" class="form-control" id="dtp2" value="<?php echo date("Y-m-d");?>">
                                <button class="btn" id="viewsched" type="button"><img src="assets/images/refreshicon.png" data-toggle="tooltip" data-placement="right" title="Refresh" width="25px"></button>
                            </div>
                        </div>
                        </form>
                    </div>
                    <!--   <p>Export to:</p> -->
                    <div id="tblprint1">
                        <table id="tab">
                            <thead>
                                <tr>
                                    <th width="5%" class="text-center" >No</th>
                                    <th class="text-center">EmpID</th>
                                    <th width="25%" class="text-center"  align="center">Name</th>
                                    <th width="25%" class="text-center"  align="center">Effectivity Date</th>
                                    <th width="25%" class="text-center"  align="center">Day</th>
                                    <th width="25%" class="text-center"  align="center">Schedule</th>
                                </tr>
                            </thead>
                            <tbody id="darviewer">

                                <?php
                                    $ids=0;

                                    if(!empty($resultdata)){
                                        foreach($resultdata as $key => $value){
                                            $ids=$ids+1;

                                            $startDay = $resultdata[$key]["startDate"];
                                            $endDay = $resultdata[$key]["endDate"];
                                            $date;

                                            // for ( $date = $startDay; $date <= $endDay; $date++){
                                            // }
                                            // return;


                                ?>
                                <tr>
                                    <td width="5%" class="text-center"> <?php echo $ids; ?> </td>
                                    <td class=text-center" ><?php echo $resultdata[$key]["EmpID"]; ?> </td>
                                    <td width="25%" class="text-center" ><?php echo $resultdata[$key]["firstName"] . " " . $resultdata[$key]["midName"] . " " . $resultdata[$key]["lastName"]; ?> </td>
                                    <td class="text-center"> From <?php echo $resultdata[$key]["startDate"] . " To ". $resultdata[$key]["endDate"]?></td>
                                    <td width="25%" class="text-center" ><?php echo $resultdata[$key]["araw"]?></td>
                                    <td width="25%" class="text-center" ><?php echo $resultdata[$key]["tf"] . " to " . $resultdata[$key]["tt"]?></td>
                                </tr>

                                <?php
                                        }
                                    }
                                    else{
                                        ?>
                                            <td width="5%" class="text-center"> EMPTY !</td>
                                        <?php

                                    }
                                ?>
                                
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>


    <!-- The Modal -->
    <div class="modal" id="thislilomodal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header" style="padding: 7px 8px;">
                </div>

                <!-- Modal body -->
                <div class="modal-body">
                    <div class="alert " style="text-align: center;">

                        <img width="150px" src="assets/images/load.gif">
                    </div>
                </div>

                <!-- Modal footer -->


            </div>
        </div>
    </div>
    <!-- modal end -->

</body>

</html>