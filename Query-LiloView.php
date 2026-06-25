<?php 

  include 'ReportController.php';
  $handle = new ReportController();
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else { header ('location: login'); }
  date_default_timezone_set("Asia/Manila"); 
  $date2=date('Y-m-d', strtotime($_GET['dto'] . '+1 days'));
  $ids=$_SESSION['id'];
  if ($_GET['eid']=="all" && $_SESSION['UserType']==1  )
        {
                            $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName,
  employees.EmpMN as MiddleName, attendancelog.wsched as WorkSchedule, attendancelog.WSFrom as DateFrom,attendancelog.Timein as Timein, 
  attendancelog.WSTO as DateTo, attendancelog.TimeOut as Timeout,attendancelog.MinsLack MinsLackIN,attendancelog.MinsLack2 as MinsLacksOut,
  attendancelog.durationtime as BillHours,attendancelog.DateTimeInput as DateTimeInputed  from employees INNER JOIN attendancelog ON attendancelog.EmpID=employees.EmpID
                                 WHERE attendancelog.Timein BETWEEN '" . $_GET['dfrom'] . "' AND '" . $date2 ."' ORDER BY LastName,attendancelog.Timein desc");
         }
    elseif ($_GET['eid']=="all" && $ids=="WeDoinc-003")
    {
        $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName,
  employees.EmpMN as MiddleName, attendancelog.wsched as WorkSchedule, attendancelog.WSFrom as DateFrom,attendancelog.Timein as Timein, 
  attendancelog.WSTO as DateTo, attendancelog.TimeOut as Timeout,attendancelog.MinsLack MinsLackIN,attendancelog.MinsLack2 as MinsLacksOut,
  attendancelog.durationtime as BillHours,attendancelog.DateTimeInput as DateTimeInputed  from employees INNER JOIN attendancelog ON attendancelog.EmpID=employees.EmpID  INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID
                                WHERE (TimeIn between '" . $_GET['dfrom'] . "' AND '" . $date2 ."') and  empdetails.EmpCompID='" . $_SESSION['CompID']  . "' ORDER BY LastName,attendancelog.Timein desc");
//   echo "im hit";
//   return;
    }
    
    elseif ($_GET['eid']=="all" && $_SESSION['UserType']==2)
    {
        $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName,
  employees.EmpMN as MiddleName, attendancelog.wsched as WorkSchedule, attendancelog.WSFrom as DateFrom,attendancelog.Timein as Timein, 
  attendancelog.WSTO as DateTo, attendancelog.TimeOut as Timeout,attendancelog.MinsLack MinsLackIN,attendancelog.MinsLack2 as MinsLacksOut,
  attendancelog.durationtime as BillHours,attendancelog.DateTimeInput as DateTimeInputed  from employees INNER JOIN attendancelog ON attendancelog.EmpID=employees.EmpID  INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID
                                 WHERE (TimeIn between '" . $_GET['dfrom'] . "' AND '" . $date2 ."') and  empdetails.EmpCompID='" . $_SESSION['CompID']  . "' and (EmpISID='" . $_SESSION['id'] . "' OR employees.EmpID= '" . $_SESSION['id'] . "') ORDER BY LastName,attendancelog.Timein desc");
    }
 
    
    
    
    else
    
            {
                            $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName,
  employees.EmpMN as MiddleName, attendancelog.wsched as WorkSchedule, attendancelog.WSFrom as DateFrom,attendancelog.Timein as Timein, 
  attendancelog.WSTO as DateTo, attendancelog.TimeOut as Timeout,attendancelog.MinsLack MinsLackIN,attendancelog.MinsLack2 as MinsLacksOut,
  attendancelog.durationtime as BillHours,attendancelog.DateTimeInput as DateTimeInputed  from employees INNER JOIN attendancelog ON attendancelog.EmpID=employees.EmpID
                                 where employees.EmpID='" . $_GET['eid'] . "' AND attendancelog.Timein BETWEEN '" . $_GET['dfrom'] . "' AND '" . $date2 ."' ORDER BY LastName,attendancelog.Timein desc");

            }
?>

            <thead>
                <tr >
                    <th width="5%" >No</th>
                    <th width="25%">Name</th>
                    <th width="25%">Timein</th>
                    <th width="25%">Timeout</th>
                    <th width="25%">Duration</th>

                </tr>
            </thead>
            <tbody>
                 <?php
              $ids=0;
            if (! empty($resultdata)) {
                foreach ($resultdata as $key => $value) {
                  $ids=$ids+1;
                    ?>
                 
                     <tr>
                    <td ><?php echo $ids; ?> </td>
                    <td ><?php echo $resultdata[$key]["LastName"]. ', '.$resultdata[$key]["FirstName"].' '.$resultdata[$key]["MiddleName"]; ?> </td>
                    <td><?php  echo date("F j, Y h:i:s A", strtotime($resultdata[$key]["Timein"]));  ?> </td> 
                    <td ><?php if ($resultdata[$key]["Timeout"]!=null){ echo date("F j, Y h:i:s A", strtotime($resultdata[$key]["Timeout"]));  } ?> </td>
                    <td ><?php  echo $resultdata[$key]["BillHours"] ?></td> 
              
                </tr>
             <?php
                }
            }
            else{
              echo "Empty !";
            }
            ?>
      </tbody>