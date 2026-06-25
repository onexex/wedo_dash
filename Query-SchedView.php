<?php 

    include 'ReportController.php';
    $handle = new ReportController();
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else { header ('location: login'); }
    date_default_timezone_set("Asia/Manila");
    $ids=$_SESSION['id'];
    $empid = $_POST['emp'];
    $dt = $_POST['date1'];
    $dt2 = $_POST['date2'];

    if ($_POST['emp']=="all" && $_SESSION['UserType']==1  )
    {
        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto)
        ");
        
    }
    else if($_POST['emp']!="all" && $_SESSION['UserType']==1  )
    {
        try{
            $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
                INNER JOIN workdays AS b ON b.empid=a.EmpID
                INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
                INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
                WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto) AND a.EmpID = '$empid'
            ");
        }
        catch(PDOException $e){
            die("ERROR: " . $e->getMessage());
        }
    }
    else if ($_POST['emp']=="all" && $_SESSION['UserType']==2)
    {

        echo $empid;
        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            INNER JOIN empdetails AS e ON e.EmpID=a.EmpID
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto) AND e.EmpISID = '$ids'
        ");
    }
    else if ($_POST['emp']!="all" && $_SESSION['UserType']==2)
    {

        echo $empid;
        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            INNER JOIN empdetails AS e ON e.EmpID=a.EmpID
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto) AND e.EmpISID = '$ids' AND a.EmpID = '$empid'
        ");
    }
    else if ($_POST['emp']=="all" && $_SESSION['UserType']==3)
    {

        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto)
        ");
    }
    else if ($_POST['emp']!="all" && $_SESSION['UserType']==3)
    {

        $resultdata = $handle->runQuery("SELECT a.EmpID as EmpID, a.EmpLN as lastName, a.EmpFN as firstName, a.EmpMN as midName, b.Day_s as araw, d.TimeFrom as tf, d.TimeTo as tt, c.dfrom as startDate, c.dto as endDate FROM employees AS a
            INNER JOIN workdays AS b ON b.empid=a.EmpID
            INNER JOIN schedeffectivity AS c ON c.efids=b.EFID
            INNER JOIN workschedule as d ON d.WorkSchedID =b.SchedTime
            WHERE ('$dt' BETWEEN c.dfrom AND c.dto) AND ('$dt2' BETWEEN c.dfrom AND c.dto) AND a.EmpID = '$empid'
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