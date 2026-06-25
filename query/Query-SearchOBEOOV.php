<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login'); }
?>
 <?php
       

                    try{
                  include 'w_conn.php';
                  $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                     }
                  catch(PDOException $e)
                     {
                  die("ERROR: Could not connect. " . $e->getMessage());
                     }
                  $sql="SELECT 'OB' as st,CONCAT('OB-', a.OBID) as id,CONCAT('Date From : ',a.OBDateFrom,' To : ',a.OBDateTo) as dtfromto , CONCAT('From : ',a.OBIFrom,'<br> To : ',a.OBITo,'<br> Purpose : ',a.OBPurpose) as descript,a.OBStatus as BSTat, CONCAT('OB Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.OBInputDate as TimeFiled, a.OBUpdated as dtp,c.StatusDesc as stat from 
                  obs as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.OBStatus=c.StatusID  
                    
                  UNION ALL 

                  SELECT 'EO' as st, CONCAT('EO-', a.SID) as id,CONCAT('Date : ',a.FDate) as dtfromto, CONCAT('Purpose : ',a.Purpose) as descript,a.Status as BSTat, CONCAT('Earlyout Application of ',b.EmpLN,' ',b.EmpFN) as ntype,a.DateTimeInputed as TimeFiled, a.DateTimeUpdated as dtp,c.StatusDesc as stat from 
                  earlyout as a 
                  INNER JOIN employees as b ON a.EmpID=b.EmpID 
                  INNER JOIN status as c ON a.Status=c.StatusID";

                  $statement = $pdo->prepare($sql);
                  $statement->execute();

                  while ($row = $statement->fetch()){
                   
                    ?>
                     <tr>
                    <td><?php  echo $row['ntype']; ?></td>
                    <td><?php  echo $row['dtp']; ?></td>
                    <td><?php  echo $row['stat']; ?></td>
                    <td><button class="btn btn-warning" data-toggle="modal" data-target="#myModal<?php echo  $row['id']; ?>">View</button></td>
                      </tr>


                  <!-- Modal -->
                  <div class="modal fade" id="myModal<?php echo  $row['id']; ?>" role="dialog">
                    <div class="modal-dialog">
                    
                      <!-- Modal content-->
                      <div class="modal-content">
                        <div class="modal-header">
                          <button type="button" class="close" data-dismiss="modal">&times;</button>
   
                        </div>
                        <div class="modal-body">
                          <h4><?php  echo $row['ntype']; ?></h4>
                          <?php  echo $row['descript']; ?><br>
                          <?php  echo $row['dtfromto']; ?><br>
                          Date Applied : <?php echo date("F m, Y h:i:s A", strtotime($row['TimeFiled']));   ?>
                        
                        </div>
                        <div class="modal-footer">
                          <?php
                            if ($row['BSTat']==1){
                           ?>
                                  <button type="button" class="btn btn-danger" id="<?php echo  $row['id']; ?>">Approve</button>
                          <button type="button" class="btn btn-info" >DisApprove</button><?php
                            }else{
                          ?>
                            <?php  echo $row['stat']; ?>
                          <?php
                            }
                          ?>
                   
                        </div>
                      </div>
                      
                    </div>
                  </div>
                  
                </div>

                    <?php
                  }
                ?>
