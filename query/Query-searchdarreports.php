<?php
		if (session_status() === PHP_SESSION_NONE) { session_start(); }

          try{
          include 'w_conn.php';
          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
             }
          catch(PDOException $e)
             {
          die("ERROR: Could not connect. " . $e->getMessage());
             }

          if (isset($_GET['fdetails'])){
              $id=$_POST['Eid'];
             if ($id=="All"){
                  $resultdata = (" SELECT * FROM employees INNER JOIN fdetails ON employees.EmpID=fdetails.FDetID ");
                                $statement = $pdo->prepare($resultdata);
                
                  $statement->execute();
             }
             else{
                      $resultdata = (" SELECT * FROM employees INNER JOIN fdetails ON employees.EmpID=fdetails.FDetID where employees.EmpID=:idn");
                      
                                $statement = $pdo->prepare($resultdata);
                      $statement->bindParam(':idn' , $id);
                  $statement->execute();
             }


                  $ids=1;
                      while ($row2 = $statement->fetch()){

                  ?>
                     <tr>
                            <td><?php echo $ids; ?></td>
                            <td><?php echo $row2["EmpFN"] . " " . $row2["EmpLN"]; ?></td>
                            <td><?php echo $row2["FName"]; ?></td>
                            <td><?php echo $row2["FRel"]; ?></td>
                      
                        </tr>

                        <?php
                        $ids=$ids+1;
            }
          }
          if (isset($_GET['srchdar'])){
              
              $id=$_POST['Eid'];
           
              $dtf = $_POST['dtfrom'];
              $dtt= date('Y-m-d', strtotime($_POST['dtto'] . ' +1 day'));
              if ($id=="All"){
                  if ($_SESSION['UserType']==1){
                     $resultdata = (" SELECT employees.EmpID as Employees_ID,employees.EmpFN as FirstName,employees.EmpMN as MiddleName,
                    
                    employees.EmpLN as LastName,dars.DarDateTime as Date_Time,dars.EmpActivity as Activity 
                    
                    FROM dars INNER JOIN employees ON dars.EMPID=employees.EmpID
                    
                    WHERE dars.DarDateTime BETWEEN :dfr AND :dto ORDER BY LastName,DarDateTime desc");
                                                           $statement = $pdo->prepare($resultdata);
                  $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtt);
                  $statement->execute();
                }else{
                      $resultdata = (" SELECT employees.EmpID as Employees_ID,employees.EmpFN as FirstName,employees.EmpMN as MiddleName,
                    
                    employees.EmpLN as LastName,dars.DarDateTime as Date_Time,dars.EmpActivity as Activity 
                    
                    FROM dars INNER JOIN employees ON dars.EMPID=employees.EmpID  INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID
                    
                    WHERE empdetails.EmpISID=:isid AND dars.DarDateTime BETWEEN :dfr AND :dto ORDER BY LastName,DarDateTime desc");
                                      $statement = $pdo->prepare($resultdata);
                  $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtt);
                  $statement->bindParam(':isid' , $_SESSION['id']);
                  $statement->execute();
                }

              }else{
                  $resultdata = (" SELECT employees.EmpID as Employees_ID,employees.EmpFN as FirstName,employees.EmpMN as MiddleName
                  
                  ,employees.EmpLN as LastName,dars.DarDateTime as Date_Time,dars.EmpActivity as Activity 
                  
                  FROM dars INNER JOIN employees ON dars.EMPID=employees.EmpID 
                  
                  WHERE employees.EmpID= :idn AND dars.DarDateTime BETWEEN :dfr AND :dto ORDER BY LastName,DarDateTime desc");
                  
                  $statement = $pdo->prepare($resultdata);
                  $statement->bindParam(':idn' , $id);
                  $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtt);
                  $statement->execute();
              }
           
                  $ids=1;
                      while ($row2 = $statement->fetch()){

                  ?>
                     <tr>
                       <?php if ($id=="all"){
                      ?>
                        <td><?php echo $row2["Employees_ID"]; ?></td>
                      <?php
                    }else{
                      ?>
                          <td><?php echo $id; ?></td>
                    <?php
                    } ?>
                            <td style="text-align: left;"><?php echo $ids; ?></td>
                            <td style="text-align: left;"><?php echo $row2["LastName"] . ',  '. $row2["FirstName"] ; ?></td>
                            <td style="text-align: left;"><?php  echo date("F j, Y h:i:s A", strtotime($row2['Date_Time'])); ?></td>
                            <td style="text-align: left;"><?php echo $row2["Activity"]; ?></td>
                      
                        </tr>

                        <?php
                        $ids=$ids+1;
            }
          }   
          if (isset($_GET['srcheo'])){
              $id=$_POST['Eid'];
              $dtf=$_POST['dtfrom'];
              $dtt=date('Y-m-d', strtotime($_POST['dtto'] . ' +1 day'));
              
              if ($id=="all"){
                  $resultdata = ("SELECT employees.EmpID as Employees_ID,
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
                  status.StatusDesc as Status 

                  FROM employees
                  INNER JOIN earlyout ON employees.EmpID=earlyout.EMPID 
                  INNER JOIN status ON earlyout.Status=status.StatusID where earlyout.FDate BETWEEN :dfr AND :dto order by LastName,earlyout.FDate  desc"); 
                   $statement = $pdo->prepare($resultdata);
                  $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtt);
                  $statement->execute();
                 // INNER JOIN status ON earlyout.Status=status.StatusID where earlyout.Status=4 and earlyout.FDate BETWEEN :dfr AND :dto order by LastName,earlyout.FDate  desc"); 
              }
              else{
                  $resultdata = ("SELECT employees.EmpID as Employees_ID,
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
                  status.StatusDesc as Status 

                  FROM employees
                  INNER JOIN earlyout ON employees.EmpID=earlyout.EMPID 
                  INNER JOIN status ON earlyout.Status=status.StatusID WHERE  employees.EmpID=:idn AND earlyout.FDate BETWEEN :dfr AND :dto order by LastName,earlyout.DateTimeUpdated  desc"); 
                   $statement = $pdo->prepare($resultdata);
                  $statement->bindParam(':idn' , $id);
                  $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtt);
                  $statement->execute();
                  
                   // INNER JOIN status ON earlyout.Status=status.StatusID WHERE earlyout.Status=4 and employees.EmpID=:idn AND earlyout.FDate BETWEEN :dfr AND :dto order by LastName,earlyout.DateTimeUpdated  desc"); 

                }
                  $ids=1;
                   while ($row2 = $statement->fetch()){
                ?>
                  <tr>
                    <?php if ($id=="all"){
                      ?>
                      <?php
                    }else{
                      ?>
                    <?php
                    } ?>
                
                    <td><?php echo $ids ; ?></td>
                    <td><?php echo $row2["LastName"] . ',  '. $row2["FirstName"] ; ?></td>
                    <td><?php  echo date("F j, Y h:i:s A", strtotime($row2["DateInput"])); ?></td>
                    <td><?php echo $row2["Purpose"]; ?></td>
                    <td style="text-align:center;"><?php echo $row2["Status"]; ?></td>

              
                </tr>
                <?php
                   $ids=$ids+1;
              }

          }
            if (isset($_GET['srchalsv'])){
              $id=$_POST['Eid'];
              $dtf=$_POST['dtfrom'];
              $dtt=date('Y-m-d', strtotime($_POST['dtto'] . ' +1 day'));
                  if ($id=="all"){
                     $resultdata = (" SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName, employees.EmpMN as MiddleName,hleavesbd.LFDate as DateFiled,hleavesbd.LStart as Start_Date,hleavesbd.LEnd as End_Date,leaves.LeaveDesc as Leave_Type,hleavesbd.LPurpose as Purpose,
                      hleavesbd.LDuration as Duration,status.StatusDesc as Status,hleavesbd.LISReason as IS_Reason,hleavesbd.LUpdate AS IS_Update_Date,hleavesbd.LHRReason as HR_Reason,
                      hleavesbd.LUpdateHR as HR_Update,hleavesbd.LInputDate as DateTimeInputed
                                     FROM hleavesbd
                                    --  INNER JOIN leaves_validation ON hleavesbd.LType=leaves_validation.sid
                                     INNER JOIN leaves ON hleavesbd.LType=leaves.LeaveID
                                     INNER JOIN employees ON hleavesbd.EmpID=employees.EmpID 
                                     INNER JOIN status ON status.StatusID=hleavesbd.LStatus   WHERE LStatus<>7 and hleavesbd.LStart BETWEEN :dfr AND :dto order by hleavesbd.LStart  desc
                                     ");
                  $statement = $pdo->prepare($resultdata);
                    $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtt);
                   $statement->execute();
              }
              else{
                     $resultdata = ("SELECT employees.EmpID as Employee_ID,employees.EmpLN as LastName,employees.EmpFN as FirstName, employees.EmpMN as MiddleName,hleavesbd.LFDate as DateFiled,hleavesbd.LStart as Start_Date,hleavesbd.LEnd as End_Date,leaves.LeaveDesc as Leave_Type,hleavesbd.LPurpose as Purpose,
                        hleavesbd.LDuration as Duration,status.StatusDesc as Status,hleavesbd.LISReason as IS_Reason,hleavesbd.LUpdate AS IS_Update_Date,hleavesbd.LHRReason as HR_Reason,
                        hleavesbd.LUpdateHR as HR_Update,hleavesbd.LInputDate as DateTimeInputed
                                     FROM hleavesbd
                                    --  INNER JOIN leaves_validation ON hleavesbd.LType=leaves_validation.sid
                                     INNER JOIN leaves ON hleavesbd.LType=leaves.LeaveID
                                     INNER JOIN employees ON hleavesbd.EmpID=employees.EmpID 
                                     INNER JOIN status ON status.StatusID=hleavesbd.LStatus where LStatus<>7 and employees.EmpID=:idn AND hleavesbd.LStart BETWEEN :dfr AND :dto order by hleavesbd.LStart  desc
                                     ");
                  $statement = $pdo->prepare($resultdata);
                  $statement->bindParam(':idn' , $id);
                     $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtt);
                   $statement->execute();

                }
            
                    $ids=1;
                   while ($row2 = $statement->fetch()){
                ?>
                  <tr>
                    <td><?php echo $row2["LastName"]. ' '.$row2["FirstName"].' '.$row2["MiddleName"]; ?> </td>
                    <td><?php echo date("F j, Y", strtotime($row2["DateFiled"])); ?> </td>
                    <td><?php echo date("F j, Y", strtotime($row2["Start_Date"])); ?> </td>
                    <td><?php echo date("F j, Y", strtotime($row2["End_Date"])); ?> </td>
                    <td><?php echo $row2["Leave_Type"]; ?> </td>  
                    <td><?php echo $row2["Purpose"]; ?> </td>
                    <td><?php echo $row2["Duration"]; ?> </td>
                    <td><?php echo $row2["Status"]; ?> </td>

              
                </tr>
                <?php
                   $ids=$ids+1;
              }
           }


             if (isset($_GET['srchcomdata'])){
                  $id=$_POST['Eid'];
                  $dtf=$_POST['dtfrom'];
                  $dtt=$_POST['dtto'];
                  if ($id=="all"){
                     $resultdata = ("SELECT Companies.CompanyDesc as Companies,EmpDetails.EmpID as Employees_ID, t.EmpFN as FirstName ,t.EmpLN as LastName ,t.EmpMN as MiddleName , EmpProfiles.EmpSSS as SSS, EmpProfiles.EmpPINo as Pagibig,EmpProfiles.EmpPHNo as PhilHealth,  EmpProfiles.EmpUMIDNo as UMID, EmpProfiles.EmpTIN as TIN, EmpProfiles.EmpPPNo as Passport_No,EmpProfiles.EmpPPED as Passport_Expiry_Date, EmpProfiles.EmpPPIA as Issuing_Authority

                              FROM EmpDetails 
                              LEFT JOIN EmpProfiles ON EmpProfiles.EmpID=EmpDetails.EmpID
                              LEFT JOIN Employees as t ON  EmpProfiles.EmpID=t.EmpID 
                              LEFT JOIN Companies ON EmpDetails.EmpCompID=Companies.CompanyID                                              
                              order by LastName ASC ");
                      $statement = $pdo->prepare($resultdata);
                   $statement->execute();
                  }
                  else{
                         $resultdata = ("SELECT Companies.CompanyDesc as Companies,EmpDetails.EmpID as Employees_ID, t.EmpFN as FirstName ,t.EmpLN as LastName ,t.EmpMN as MiddleName , EmpProfiles.EmpSSS as SSS, EmpProfiles.EmpPINo as Pagibig,EmpProfiles.EmpPHNo as PhilHealth,  EmpProfiles.EmpUMIDNo as UMID, EmpProfiles.EmpTIN as TIN, EmpProfiles.EmpPPNo as Passport_No,EmpProfiles.EmpPPED as Passport_Expiry_Date, EmpProfiles.EmpPPIA as Issuing_Authority

                              FROM EmpDetails 
                              LEFT JOIN EmpProfiles ON EmpProfiles.EmpID=EmpDetails.EmpID
                              LEFT JOIN Employees as t ON  EmpProfiles.EmpID=t.EmpID 
                              LEFT JOIN Companies ON EmpDetails.EmpCompID=Companies.CompanyID where EmpProfiles.EmpID=:idn                                            
                              order by LastName ASC ");
                        $statement = $pdo->prepare($resultdata);
                        $statement->bindParam(':idn' , $id);
                        $statement->execute();
                  }
                   $ids=1;
                   while ($row2 = $statement->fetch()){
                    ?>
                       <tr>
                    <td><?php echo $ids; ?> </td>
                    <td><?php echo $row2["Companies"]; ?> </td>
                    <td><?php echo $row2["Employees_ID"]; ?> </td>
                    <td><?php echo $row2["LastName"]. ' '.$row2["FirstName"].' '.$row2["MiddleName"]; ?> </td>
                    <td><?php echo $row2["SSS"]; ?> </td>
                    <td><?php echo $row2["Pagibig"]; ?> </td>
                    <td><?php echo $row2["PhilHealth"]; ?> </td>
                    <td><?php echo $row2["UMID"]; ?> </td>  
                    <td><?php echo $row2["TIN"]; ?> </td>
                    <td><?php echo $row2["Passport_No"]; ?> </td>
                    <td><?php echo $row2["Passport_Expiry_Date"]; ?> </td>
                    <td><?php echo $row2["Issuing_Authority"]; ?> </td>
                    <td><?php echo $row2["Issuing_Authority"]; ?> </td>
          
              
                </tr>
                    <?php        $ids=$ids+1;
                   }
             }
             if (isset($_GET['srchobret'])){
                  $id=$_POST['Eid'];
                  $dtf=$_POST['dtfrom'];
                  $dtt=date('Y-m-d', strtotime($_POST['dtto'] . ' +1 day'));
                  if ($id=="all"){
                     
                     if ($_SESSION['UserType']==1 &&  $_SESSION['id']=="WeDoInc-003"){
                        $resultdata = (" SELECT employees.EmpID as Employee_ID,
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
                          INNER JOIN status ON obs.OBStatus=status.StatusID WHERE  obs.OBDateFrom BETWEEN :dfr AND :dto order by employees.EmpLN,obs.OBDateFrom desc");
                         $statement = $pdo->prepare($resultdata);
                          $statement->bindParam(':dfr' , $dtf);
                          $statement->bindParam(':dto' , $dtt);
                      }
                       elseif ($_SESSION['UserType']==2 && $_SESSION['id']=="WeDoinc-003"){
                        $resultdata = (" SELECT employees.EmpID as Employee_ID,
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
                          INNER JOIN status ON obs.OBStatus=status.StatusID WHERE obs.OBDateFrom BETWEEN :dfr AND :dto order by employees.EmpLN,obs.OBDateFrom desc");
                         $statement = $pdo->prepare($resultdata);
                          $statement->bindParam(':dfr' , $dtf);
                          $statement->bindParam(':dto' , $dtt);
                    
                      }
                    //   else{
                    //      $resultdata = (" SELECT employees.EmpID as Employee_ID,
                    //       employees.EmpLN as LastName,
                    //       employees.EmpFN as FirstName,
                    //       employees.EmpMN as MiddleName,
                    //       obs.OBFD as Filing_Date,
                    //       obs.OBDateFrom as OBDateFrom,
                    //       obs.OBDateTo as OBDateTo,
                    //       obs.OBIFrom as Itinerary_From,
                    //       obs.OBITo as Itinerary_To,
                    //       obs.OBTimeFrom as Time_From,
                    //       obs.OBTimeTo as Time_To,
                    //       obs.OBISReason as IS_Reason,
                    //       obs.OBHRReason as HR_Reason,
                    //       obs.OBPurpose as Purpose,
                    //       obs.OBUpdated as DTUpdated,
                    //       obs.OBCAAmt as Cash_Advance,
                    //       obs.OBCAPurpose as CA_Purpose,
                    //       status.StatusDesc as Status,
                    //       obs.OBInputDate as DateTimeInputed FROM employees
                    //       INNER JOIN obs ON employees.EmpID=obs.EmpID
                    //       INNER JOIN status ON obs.OBStatus=status.StatusID
                    //       INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID
                    //      WHERE obs.OBStatus=4 and obs.OBDateFrom BETWEEN :dfr AND :dto and (employees.EmpID=:isid or empdetails.EmpISID=:isid) order by employees.EmpLN,obs.OBDateFrom desc");
                    //      $statement = $pdo->prepare($resultdata);
                    //       $statement->bindParam(':dfr' , $dtf);
                    //       $statement->bindParam(':dto' , $dtt);
                    //       $statement->bindParam(':isid' , $_SESSION['id']);
                    //   }
                    // on clicking the refresh button on admin acct 
                    else{
                        $resultdata = (" SELECT employees.EmpID as Employee_ID,
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
                          INNER JOIN status ON obs.OBStatus=status.StatusID WHERE obs.OBDateFrom BETWEEN :dfr AND :dto order by employees.EmpLN,obs.OBDateFrom desc");
                         $statement = $pdo->prepare($resultdata);
                          $statement->bindParam(':dfr' , $dtf);
                          $statement->bindParam(':dto' , $dtt);
                    }
                         
                          $statement->execute();
                            $ids=1;
                          while ($row2 = $statement->fetch()){
                            ?>
                                  <tr>
                                <td><?php echo $ids; ?> </td>
                                <td><?php echo $row2["LastName"]. ' '.$row2["FirstName"].' '.$row2["MiddleName"]; ?> </td>
                                <td><?php  echo date("F j, Y", strtotime($row2["Filing_Date"])); ?> </td>
                                <td><?php  echo date("F j, Y", strtotime($row2["OBDateFrom"])); ?> </td>
                                <td><?php  echo date("F j, Y", strtotime($row2["OBDateTo"])); ?> </td>
                                <td><?php echo $row2["Itinerary_From"]; ?> </td>  
                                <td><?php echo $row2["Itinerary_To"]; ?> </td>
                                <td><?php echo date("h:i:s A", strtotime($row2["Time_From"])); ?> </td>
                                <td><?php echo date("h:i:s A", strtotime($row2["Time_To"])); ?> </td>
                                <td><?php echo $row2["Purpose"]; ?> </td>
                                <td><?php echo number_format($row2["Cash_Advance"], 2); ?> </td>
                                <td><?php echo $row2["CA_Purpose"]; ?> </td>
                                <td><?php echo $row2["Status"]; ?> </td>
                          
                            </tr>
                            <?php        $ids=$ids+1;
                          }
                  }else{
                      $resultdata = (" SELECT employees.EmpID as Employee_ID,
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
                          INNER JOIN status ON obs.OBStatus=status.StatusID  WHERE obs.EmpID=:idn  AND obs.OBInputDate BETWEEN :dfr AND :dto");
                                                    // INNER JOIN status ON obs.OBStatus=status.StatusID  WHERE obs.OBStatus=4 and obs.EmpID=:idn  AND obs.OBInputDate BETWEEN :dfr AND :dto");
                          $statement = $pdo->prepare($resultdata);
                          $statement->bindParam(':idn' , $id);
                          $statement->bindParam(':dfr' , $dtf);
                          $statement->bindParam(':dto' , $dtt);
                          $statement->execute();
                             $ids=1;
                          while ($row2 = $statement->fetch()){
                            ?>
                                  <tr>
                                <td><?php echo $ids; ?> </td>
                                <td><?php echo $row2["LastName"]. ' '.$row2["FirstName"].' '.$row2["MiddleName"]; ?> </td>
                                <td><?php  echo date("F j, Y", strtotime($row2["Filing_Date"])); ?> </td>
                                <td><?php  echo date("F j, Y", strtotime($row2["OBDateFrom"])); ?> </td>
                                <td><?php  echo date("F j, Y", strtotime($row2["OBDateTo"])); ?> </td>
                                <td><?php echo $row2["Itinerary_From"]; ?> </td>  
                                <td><?php echo $row2["Itinerary_To"]; ?> </td>
                                <td><?php echo date("h:i:s A", strtotime($row2["Time_From"])); ?> </td>
                                <td><?php echo date("h:i:s A", strtotime($row2["Time_To"])); ?> </td>
                                <td><?php echo $row2["Purpose"]; ?> </td>
                                <td><?php echo number_format($row2["Cash_Advance"], 2); ?> </td>
                                <td><?php echo $row2["CA_Purpose"]; ?> </td>
                                <td><?php echo $row2["Status"]; ?> </td>
                          
                            </tr>
                            <?php        $ids=$ids+1;
                          }
                  }
             }
             if (isset($_GET['srchovertimt'])){
                               $id=$_POST['Eid'];
                                $dtf=$_POST['dtfrom'];
                                $dtt=date('Y-m-d', strtotime($_POST['dtto'] . ' +1 day'));
                                       if ($id=="all"){
                                          $resultdata =(" SELECT b.EmpID as Employees_ID,
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
                                          INNER JOIN status as c ON a.Status=c.StatusID  WHERE  a.TimeIn BETWEEN :dfr AND :dto and (StatusID=1 or StatusID=2 or StatusID=4  or StatusID=5)");
                                            $statement = $pdo->prepare($resultdata);
                                            $statement->bindParam(':dfr' , $dtf);
                                            $statement->bindParam(':dto' , $dtt);
                                           $statement->execute();
                                       }else{
                                           $resultdata =(" SELECT b.EmpID as Employees_ID,
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
                                          INNER JOIN status as c ON a.Status=c.StatusID  WHERE b.EmpID=:idn AND a.TimeIn BETWEEN :dfr AND :dto and (StatusID=1 or StatusID=2 or StatusID=4 or StatusID=5)");
                                            $statement = $pdo->prepare($resultdata);
                                            $statement->bindParam(':idn' , $id);
                                            $statement->bindParam(':dfr' , $dtf);
                                            $statement->bindParam(':dto' , $dtt);
                                           $statement->execute();
                                       }
                                       $ids=1;
                          while ($row2 = $statement->fetch()){
                            ?>
                                  <tr>
                    <td><?php echo $ids; ?></td>
                    <td><?php echo $row2["LastName"] . ',  '. $row2["FirstName"] ; ?></td>
                    <td><?php echo date("F j, Y", strtotime($row2["DateFiling"])) . ' '. date("h:i:s A", strtotime($row2["TimeFiling"])); ?></td>
                    <td><?php echo date("F j, Y h:i:s A", strtotime( $row2["Time_IN"])); ?></td>
                    <td><?php echo date("F j, Y h:i:s A", strtotime($row2["Time_OUT"])); ?></td>
                    <td><?php echo $row2["Duration"]; ?></td>
                    <td><?php echo $row2["Purpose"]; ?></td>
                    <td><?php echo $row2["Status"]; ?></td>
              
                </tr>
                            <?php        $ids=$ids+1;
                          }
                         }
          //new
        
            ?>