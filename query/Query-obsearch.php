<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
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
                          
                      $id=$_SESSION['id'];
                         $dt1= date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));
                      $dt2=date("Y-m-d");
                  $statement = $pdo->prepare("SELECT * FROM obs AS a 
                                  INNER JOIN status AS b ON a.OBStatus=b.StatusID  
                                  WHERE a.OBDateFrom BETWEEN :dt1 AND :dt2 AND a.EmpID=:id  and a.OBStatus<>7  order by a.OBInputDate desc");

                  $statement->bindParam(':id' , $id);
                  $statement->bindParam(':dt1' , $dt1);
                  $statement->bindParam(':dt2' , $dt2);
                  $statement->execute();
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php echo date("F j, Y", strtotime($row21['OBFD'])); ?></td>  
                   <td><?php echo date("F j, Y", strtotime($row21['OBDateFrom'])); ?></td>
                   <td><?php echo date("F j, Y", strtotime($row21['OBDateTo'])); ?></td>
                   <td><?php echo date("h:i:s A", strtotime($row21['OBTimeFrom'])); ?></td> 
                   <td><?php echo date("h:i:s A", strtotime($row21['OBTimeTo'])); ?></td>
                   <td><?php echo $row21['OBIFrom']; ?></td>
                   <td><?php echo $row21['OBITo']; ?></td>
                   <td><?php echo $row21['OBPurpose']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                      <?php
                        if($row21['OBStatus']==1){
                    ?>
                    <td><button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModalob<?php echo $row21['OBID']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button> </td>
                      <?php
                        }else{
                            
                        }
                        ?>
                
                  </tr> 
                   
                   <!-- The Modal -->
                    <div class="modal ob-viewdel" id="myModalob<?php echo $row21['OBID']; ?>">
                      <div class="modal-dialog">
                        <div class="modal-content">
                    
                          <!-- Modal Header -->
                          <div class="modal-header">
                            <h4 class="modal-title">Are you sure you want to remove this ??</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                          </div>
                    
                          <!-- Modal body -->
                          <div class="modal-body">
                              <button type="button" id="<?php echo $row21['OBID']; ?>" class="btn btn-success ys_ob">Yes</button> 
                               <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                          </div>
                    
                          <!-- Modal footer -->
                      
                    
                        </div>
                      </div>
                    </div>
              <?php 
              }

              ?>