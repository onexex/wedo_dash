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
                  $id=$_SESSION['id'];
                   $date1=date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));
                        $date2=date("Y-m-d", strtotime(' + 1 days'));
                  $statement = $pdo->prepare("SELECT * from otattendancelog as a 
                                  INNER JOIN status as b on a.Status=b.StatusID  
                                  where a.EmpID=:id and TimeIn between :dt1 and :dt2 and a.Status<>7 order by a.DateTimeInputed DESC");

                  $statement->bindParam(':id' , $id);
                  $statement->bindParam(':dt1' , $date1);
                  $statement->bindParam(':dt2' , $date2);
                  $statement->execute();
                  $cnt=0;
                while ($row21 = $statement->fetch())
                {
                  $cnt=$cnt+1;
                  ?>
                   <tr>
                   <td><?php echo $cnt ?></td>  
                   <td><?php echo date("F j, Y h:i:s A", strtotime($row21['DateTimeInputed'])); ?></td>
                   <td><?php echo date("F j, Y h:i:s A", strtotime($row21['TimeIn'])); ?></td>
                   <td><?php echo date("F j, Y h:i:s A", strtotime($row21['TimeOut'])); ?></td> 
                   <td><?php echo $row21['Purpose']; ?></td>
                   <td><?php echo $row21['Duration']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                
                    <?php
                        if($row21['Status']==1){
                    ?>
                    <td><button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModalob<?php echo $row21['OTLOGID']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button> </td>
                      <?php
                        }else{
                            
                        }
                        ?>
                  </tr> 
                  
                   <!-- The Modal -->
                    <div class="modal ob-viewdel" id="myModalob<?php echo $row21['OTLOGID']; ?>">
                      <div class="modal-dialog">
                        <div class="modal-content">
                    
                          <!-- Modal Header -->
                          <div class="modal-header">
                            <h4 class="modal-title">Are you sure you want to remove this ??</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                          </div>
                    
                          <!-- Modal body -->
                          <div class="modal-body">
                              <button type="button" id="<?php echo $row21['OTLOGID']; ?>" class="btn btn-success ys_ot">Yes</button> 
                               <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                          </div>
                    
                          <!-- Modal footer -->
                      
                    
                        </div>
                      </div>
                    </div>
              <?php 
              }

              ?>