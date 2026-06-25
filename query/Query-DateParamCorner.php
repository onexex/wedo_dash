 <?php
 if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login'); }
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
                      $date1=date('Y-m-d', strtotime($_POST['dateto']  . ' + 1 days'));
                        $statement = $pdo->prepare("select * from announcements inner join employees on announcements.EmpID=employees.EmpID inner join positions on employees.PosID=positions.PSID where  announcements.ADate between :df and :dt  order by ADate desc");
                        $statement->bindParam(':df' , $_POST['datefrom']);
                        $statement->bindParam(':dt' , $date1);
                        $statement->execute();


                      


                        while ($row2 = $statement->fetch()){



                        $st = $pdo->prepare("select * from empprofiles where EmpID=:id");
                        $st->bindParam(':id' , $row2['EmpID']);
                        $st->execute(); 

                        $count=$st->rowCount();
                        $row=$st->fetch();
                        if ($count<1){
                             $path="assets/images/profiles/default.png";
                            $url = "'assets/images/profiles/default.png'";
                        }else{
                             $path="assets/images/profiles/default.png";
                          try{
                            if ($row['EmpPPath']==""){
                              $path="assets/images/profiles/default.png";
                               $url= 'assets/images/profiles/default.png';

                            }else{
                              $path=$row['EmpPPath'];
                               $url=  $row['EmpPPath'];
                            }
                          }
                          catch(Exception $e) {
                            $path="assets/images/profiles/default.png";
                             $url = "'assets/images/profiles/default.png'";
                          }
                        }
                          $stmt=$pdo->prepare("Select * from annseen where aid='$row2[aid]'");
                          $stmt->execute();
                          $nrow=$stmt->rowCount();
                          $snby="";
                          while($rrow=$stmt->fetch()){
                                $state=$pdo->prepare("select * from employees where EmpID='$rrow[EmpID]'");
                                $state->execute();
                                $roow2=$state->fetch();
                                $snby=$snby . $roow2['EmpFN'] . " " . $roow2['EmpLN'] . "<br/>";
                          }
                        
                    ?>
                          <!-- announcemetn -->
                            <div class="cn" id="cn">
                                <div class="cnt">
                                 <div class="row">
                                  <div class="col-lg-2">
                                    <div class="pci-mid" style="background-image: url('<?php echo $url; ?>');">
                                    
                                    </div>
                                  
                                  </div>
                                  <div class="col-lg-10">
                                    <h5> <i class="fa fa-bullhorn" aria-hidden="true" style="font-size: 23px; <?php echo "color: " . $_SESSION['CompanyColor']; ?>"></i> 

                                     <?php echo $row2['Title']; ?> <!-- <?php  if ($_SESSION['UserType']==1){ ?> 
                                      <i class="fa fa-pencil-square" style="font-size: 23px; " data-toggle="modal" data-target="#myModal<?php echo $row2[0]; ?>" aria-hidden="true"></i><?php } ?> -->
                                     
                                     <!--  <i href="#" title="Seen by" role="button" cursor: pointer; data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="<?php echo $snby; ?>" class="fa-seen" style="margin-left: 10px;">Seen by <?php echo $nrow; ?></i> --><i class="fa fa-check" aria-hidden="true"></i>
                                    </h5>
                                    <h6><?php echo $row2['EmpFN'] . " " . $row2['EmpLN']; ?> </h6>

                                                              <!-- The Modal -->
                                    <div class="modal" id="myModal<?php echo $row2[0]; ?>">
                                      <div class="modal-dialog">
                                        <div class="modal-content">

                                          <!-- Modal Header -->
                                          <div class="modal-header">
                                            <h4 class="modal-title">Announcements</h4>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                          </div>

                                          <!-- Modal body -->
                                          <div class="modal-body">
                                            <form method="post" action="?updateann=<?php echo $row2[0]; ?>" class="frmannouncement">
                                              <div class="form-group">
                                                <label for="usr">Title:</label>
                                                <input type="text" class="form-control" value="<?php echo $row2['Title']; ?>" required="required" name="titles">
                                              </div>
                                              <div class="form-group">
                                                <label for="usr">Description:</label>
                                                 <textarea class="form-control" required="required" rows="5" name="announ" id="desc"><?php echo $row2['ADesc']; ?></textarea>
                                              </div>
                                              <button class="btn btn-success btn-block">Update</button>
                                            </form>
                                          </div>

                                          <!-- Modal footer -->
                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                          </div>

                                        </div>
                                      </div>
                                    </div>
                                    <!-- end of modal   -->
                                 
                                
                                    <p class="rdv" id="rdv<?php echo $row2[0]; ?>">
                                      <h2><?php echo $row2['ADesc']; ?></h2>
                                      <p><?php  $newDate = date("F d, Y h:i:s A", strtotime($row2['ADate']));
                                     echo $newDate; ?></p>
                                    <?php
                                      // echo substr($row2['ADesc'],0,25) . "...."; ?>
                                    </p>
                                    <p class="rdmore" id="rdmore<?php echo $row2[0]; ?>">
                                      <?php
                                       // echo $row2['ADesc']; ?>
                                    </p>
                                     <p class="rdmore" id="dtmore<?php echo $row2[0]; ?>">
                                     <?php 

                                     // $newDate = date("F d, Y h:i:s A", strtotime($row2['ADate']));
                                     // echo $newDate;
                                      ?>
                                    </p>
                                   <!--  <a  class="btn btn-link btnrdmore cmore" id="<?php echo $row2[0]; ?>">Read More..</a> -->

                                  </div>
                                </div>
                                
                              </div>
                            </div>
                            <!-- end of content -->
                    <?php     
                        }
                    ?>

                  
                   
