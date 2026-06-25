<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$q = $_SESSION['id'];
try{

include 'w_conn.php';	

$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }

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
                  $statement = $pdo->prepare("SELECT * FROM earlyout AS a INNER JOIN status AS b ON a.Status=b.StatusID WHERE a.EmpID=:id and Status<>7 ORDER BY DateTimeInputed DESC");
                  $statement->bindParam(':id' , $id);
                  $statement->execute();
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php echo $row21['Purpose']; ?></td>
                   <td><?php echo date("F j, Y", strtotime($row21['DFile'])); ?></td>  
                   <td><?php echo $row21['StatusDesc']; ?></td>
                   <td><?php echo date("F j, Y h:i:s A", strtotime($row21['DateTimeInputed'])); ?></td>
                     <?php
                        if($row21['Status']==1){
                    ?>
                         <td><button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModalw<?php echo $row21['SID']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button> </td>    
                    <?php
                        }else{
                            
                        }
                    ?>
                  </tr> 
                     <!-- The Modal -->
                    <div class="modal" id="myModalw<?php echo $row21['SID']; ?>">
                      <div class="modal-dialog">
                        <div class="modal-content">
                    
                          <!-- Modal Header -->
                          <div class="modal-header">
                            <h4 class="modal-title">Are you sure you want to remove this ??</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                          </div>
                    
                          <!-- Modal body -->
                          <div class="modal-body">
                              <button type="button" id="<?php echo $row21['SID']; ?>"  class="btn btn-success ys_eo">Yes</button> 
                               <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                          </div>
                    
                          <!-- Modal footer -->
                       
                        </div>
                      </div>
                    </div>
              <?php 
              }

              ?>



 


