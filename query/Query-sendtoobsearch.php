<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
       date_default_timezone_set("Asia/Manila"); 

                     try{
                        include 'w_conn.php';
                        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                           }
                        catch(PDOException $e)
                           {
                        die("ERROR: Could not connect. " . $e->getMessage());
                          }
                      $id=$_SESSION['id'];
                         $dt1= date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));
                      $dt2=date("Y-m-d");
                   if ($_SESSION['UserType']==1){
                         $statement = $pdo->prepare("SELECT * from employees inner join  obs as a on employees.EmpID=a.EmpID  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where  OBDateFrom between :dt1 and :dt2 and a.OBType=2 order by OBDateFrom desc");
                      }else{
                         $statement = $pdo->prepare("SELECT * from employees INNER JOIN empdetails on employees.EmpID=empdetails.EmpID inner join  obs as a on empdetails.EmpID=a.EmpID 
                                  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where (empdetails.EmpID=:id or empdetails.EmpISID=:id) and OBDateFrom between :dt1 and :dt2 and (a.OBType<>7 or a.OBType<>5 or a.OBType<>3) order by OBDateFrom desc");
                  $statement->bindParam(':id' , $id);
                      }
                  $statement->bindParam(':dt1' , $dt1);
                  $statement->bindParam(':dt2' , $dt2);
                  $statement->execute();
              
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                       <td><?php echo $row21['EmpFN'] . " " . $row21['EmpLN']; ?></td>
                   <td><?php echo date("F j, Y", strtotime($row21['OBFD'])); ?></td>  
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateFrom'])); ?></td>
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateTo'])); ?></td>
                   <td><?php echo date("h:i:s A", strtotime($row21['OBTimeFrom'])); ?></td> 
                   <td><?php echo date("h:i:s A", strtotime($row21['OBTimeTo'])); ?></td>
                   <td><?php echo $row21['OBIFrom']; ?></td>
                   <td><?php echo $row21['OBITo']; ?></td>
                   <td><?php echo $row21['OBPurpose']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                
                  </tr> 
              <?php 
              }

              ?>