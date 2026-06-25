<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){

  }
  else{ header ('location: login.php'); }

                include 'w_conn.php';
?>


<?php
if (isset($_GET['otmaint'])){
    include 'w_conn.php';
        try{
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $e)
        {
            die("ERROR: Could not connect. " . $e->getMessage());
        }

           $statement = $pdo->prepare("select * from otfsmaintenance where compid=:id");
                          $statement->bindParam(':id', $_GET['otmaint']);
                          $statement->execute();
                          $nrow = $statement->rowCount();
                          $row = $statement->fetch();
                          if ($nrow>0){
                          ?>
                          <tr>
                            <td><?php if ($row['IsBefore']==0) { echo "Yes";  }else{ echo "No"; } ?></td>
                            <td><?php echo $row[3] ?></td>
                            <td><?php if ($row[4]==0) { echo "Yes";  }else{ echo "No"; } ?></td>
                            <td><?php echo $row[5] ?></td>
                            <td><?php if ($row[6]==0) { echo "Yes";  }else{ echo "No"; } ?></td>
                            <td><?php if ($row[7]==0) { echo "Yes";  }else{ echo "No"; } ?></td>
                            <td><?php echo $row[8] ?></td>
                             <td><button class="btn btn-info" data-toggle="modal" data-target="#newform"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></button></td> 
                          </tr>
                             <div class="modal" id="newform">
                                    <div class="modal-dialog" style="max-width: 570px;">
                                      <div class="modal-content">

                                        <!-- Modal Header -->
                                        <div class="modal-header">
                                          <h4 class="modal-title">OT Maintenance</h4>
                                          <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>

                                        <!-- Modal body -->
                                        <div class="modal-body ob-body">
                                            <form class="frmot">
                                              <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">File Before:</label>
                                                      <select class="form-control" id="fbf" name="fbefore">
                                                        <?php if ($row['IsBefore']==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                       
                                                      </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">No of Days Before:</label>
                                                      <input type="text" value="<?php echo $row[3] ?>" id="inputextbefore" class="form-control" name="noofdaysbef">
                                                    </div>
                                                </div>
                                              </div>
                                              <div class="row">
                                                <div class="col-lg-6">
                                                  <div class="form-group">
                                                    <label for="sel1">File After:</label>
                                                    <select class="form-control" id="faf" name="fafter">
                                                        <?php if ($row[4]==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                    </select>
                                                  </div>
                                                </div>

                                                <div class="col-lg-6">
                                                  <div class="form-group">
                                                    <label for="sel1">No of Days After:</label>
                                                    <input type="text" id="inputextafter" value="<?php echo $row[5] ?>" class="form-control" name="noofdaysaft">
                                                  </div>
                                                </div>
                                              </div>
                                              <div class="row">
                                                  <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">Is Holiday:</label>
                                                      <select class="form-control" id="sel1" name="isholiday">
                                                          <?php if ($row[6]==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                      </select>
                                                    </div>
                                                  </div>
                                                     <div class="col-lg-6">
                                                          <div class="form-group">
                                                        <label for="sel1">Is Tardy:</label>
                                                        <select class="form-control" name="istardy">
                                                           <?php if ($row[7]==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                        </select>
                                                      </div>
                                                  </div>
                                              </div>
                                                <div class="row">
                                                  <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">Days Not Allowed:</label>
                                                        <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" id="Mondays" value="Monday">Monday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Tuesday">Tuesday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Wednesday">Wednesday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Thursday">Thursday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Friday">Friday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Saturday">Saturday
                                                          </label>
                                                        </div>
                                                         <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Sunday">Sunday
                                                          </label>
                                                        </div>
                                                    </div>
                                                  </div>
                                                  <div class="col-lg-6">
                                                   <textarea id="notallowed" style="display: none;" name="dnotallowed"></textarea>
                                                  </div>
                                              </div>
                                             
                                            
                                              <button type="button" class="btn btn-success btn-block btnsaveotm">Save</button>

                                            </form>
                                        </div>

                                        <!-- Modal footer -->
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-danger"  data-dismiss="modal">Close</button>
                                        </div>

                                      </div>
                                    </div>
                                  </div>
                                  <?php
                                }
                                else{
                        ?>
                              <tr>
                                <td>Yes</td>
                                <td>0</td>
                                <td>Yes</td>
                                <td>0</td>
                                <td>Yes</td>
                                <td>Yes</td>
                                <td>None</td>
                              <!--     <td><button class="btn btn-info" data-toggle="modal" data-target="#newform"><i class="fa fa-pencil-square-o" aria-hidden="true"></i></button></td> -->
                              </tr>
                                    <div class="modal" id="newform">
                                    <div class="modal-dialog" style="max-width: 570px;">
                                      <div class="modal-content">

                                        <!-- Modal Header -->
                                        <div class="modal-header">
                                          <h4 class="modal-title">OT Maintenance</h4>
                                          <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>

                                        <!-- Modal body -->
                                        <div class="modal-body ob-body">
                                            <form class="frmot" id="otfrmser">
                                              <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">File Before:</label>
                                                      <select class="form-control" id="fbf" name="fbefore">
                                                        <?php if ($row['IsBefore']==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                       
                                                      </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">No of Days Before:</label>
                                                      <input type="text" value="<?php echo $row[3] ?>" id="inputextbefore" class="form-control" name="noofdaysbef">
                                                    </div>
                                                </div>
                                              </div>
                                              <div class="row">
                                                <div class="col-lg-6">
                                                  <div class="form-group">
                                                    <label for="sel1">File After:</label>
                                                    <select class="form-control" id="faf" name="fafter">
                                                        <?php if ($row[4]==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                    </select>
                                                  </div>
                                                </div>

                                                <div class="col-lg-6">
                                                  <div class="form-group">
                                                    <label for="sel1">No of Days After:</label>
                                                    <input type="text" id="inputextafter" value="<?php echo $row[5] ?>" class="form-control" name="noofdaysaft">
                                                  </div>
                                                </div>
                                              </div>
                                              <div class="row">
                                                  <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">Is Holiday:</label>
                                                      <select class="form-control" id="sel1" name="isholiday">
                                                          <?php if ($row[6]==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                      </select>
                                                    </div>
                                                  </div>
                                                     <div class="col-lg-6">
                                                          <div class="form-group">
                                                        <label for="sel1">Is Tardy:</label>
                                                        <select class="form-control" name="istardy">
                                                           <?php if ($row[7]==0) { 
                                                      ?>
                                                        <option value="0">Yes</option>
                                                        <option value="1">No</option>
                                                      <?php
                                                        }else{ 
                                                       ?>
                                                        <option value="1">No</option>
                                                        <option value="0">Yes</option>
                                                       <?php
                                                        } ?>
                                                        </select>
                                                      </div>
                                                  </div>
                                              </div>
                                                <div class="row">
                                                  <div class="col-lg-6">
                                                    <div class="form-group">
                                                      <label for="sel1">Days Not Allowed:</label>
                                                        <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" id="Mondays" value="Monday">Monday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Tuesday">Tuesday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Wednesday">Wednesday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Thursday">Thursday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Friday">Friday
                                                          </label>
                                                        </div>
                                                          <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Saturday">Saturday
                                                          </label>
                                                        </div>
                                                         <div class="form-check">
                                                          <label class="form-check-label">
                                                            <input type="checkbox" class="form-check-input" value="Sunday">Sunday
                                                          </label>
                                                        </div>
                                                    </div>
                                                  </div>
                                                  <div class="col-lg-6">
                                                   <textarea id="notallowed" style="display: none;" name="dnotallowed"></textarea>
                                                  </div>
                                              </div>
                                             
                                            
                                              <button type="button" class="btn btn-success btn-block btnsaveotm">Save</button>

                                            </form>
                                        </div>

                                        <!-- Modal footer -->
                                        <div class="modal-footer">
                                          <button type="button" class="btn btn-danger"  data-dismiss="modal">Close</button>
                                        </div>

                                      </div>
                                    </div>
                                  </div>
                        <?php
                                }
  }
	if (isset($_GET['agency'])){
		 try{
 
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from agency ");
            $statement->execute(array(':name' => ""));
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['AgencyID']; ?></td>
                                <td class="td-dar"><?php echo $row['AgencyName']; ?></td>
                                <td class="td-dar"><?php 
                                if  ($row['IsActive']=="1"){
                                  echo "Yes";
                                }else{
                                  echo "No";
                                } ?></td>
                                <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
            <?php

            }
	}
    if (isset($_GET['company'])){
     try{
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from companies");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['CompanyID']; ?></td>
                                <td class="td-dar"><?php echo $row['CompanyDesc']; ?></td>
                                <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myview<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
            <?php

            }
  }

    if (isset($_GET['lvviewer'])){
         try{
        
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
                $stm = $pdo->prepare("select * from companies  where CompanyID=:id");
                          $stm->bindParam(':id', $_SESSION['CompID']);
                          $stm->execute(); 
                          $row2=$stm->fetch();

               $cid=$_GET['lvviewer'];
              $statement = $pdo->prepare("select * from leaves_validation left join leaves on leaves_validation.lid=leaves.LeaveID where leaves_validation.compid=:lcr");
              $statement->bindParam(':lcr', $cid);
              $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                                <td class="td-dar"><?php echo $_GET['nm']; ?></td>
                                <td class="td-dar"><?php echo $row['LeaveDesc']; ?></td>  
                                <td class="td-dar"><?php echo $row['leave_credits']; ?></td>  
                                <td class="td-dar"><?php echo $row['leave_min']; ?></td>  
                                <td class="td-dar"><?php echo $row['leave_short']; ?></td>  
                                <td class="td-dar"><?php echo $row['leave_long']; ?></td>  
                                <td class="td-dar"><?php if ($row['leave_before']==1){ echo "YES"; }else{ echo "NO"; } ?></td>  
                                <td class="td-dar"><?php if ($row['leave_file_after']==1){ echo "YES"; }else{ echo "NO"; } ?></td>  
                                <td class="td-dar"><?php echo $row['filing_after_duration']; ?></td>  
                                <td class="td-dar"><?php   if ($row['IsHalfDay']==1){ echo "YES"; }else{ echo "NO"; } ?></td>  
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                                   <!-- The Modal -->
      <!-- Modal -->
                                              <div class="modal " id="myModal<?php echo $row[0];  ?>" role="dialog">
                                                <div class="modal-dialog">
                                                
                                                  <!-- Modal content-->
                                                  <div class="modal-content">
                                                    <div class="modal-header">
                                                      <button type="button" class="close" data-dismiss="modal">&times;</button>
                                               
                                                    </div>
                                                    <div class="modal-body">
                                                         <form  method="post" action="?leavevalupdate" class="frmleaveval">
                                                          <input type="hidden" name="lvalid" value="<?php echo $row[0];  ?>">
                                                            <div class="row">
                                                              <div class="col-lg-6">
                                                                <div class="form-group">
                                                                  <label >Company Name:</label>
                                                                  <select class="form-control" name="compid" id="compid">
                                                                    <option <?php echo $row2['CompanyID']; ?>><?php echo $row2['CompanyDesc']; ?></option>
                                                                  </select>
                                                                </div>

                                                                 <div class="form-group">
                                                                    <label >Leave Type:</label>
                                                                    <select class="form-control" name="ltype" id="acompid">
                                                                      <option value="<?php echo $row['lid']; ?>"><?php echo $row['LeaveDesc']; ?></option>
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
                                                                     
                                                                              $st = $pdo->prepare("select * from leaves where LeaveID<>:lcr");
                                                                             $st->bindParam(':lcr', $row['lid']);   
                                                                            $st->execute();
                                                                        while ($row3 = $st->fetch()){
                                                                        ?>
                                                                           <option value="<?php echo $row3['LeaveID']; ?>"><?php echo $row3['LeaveDesc']; ?></option> 
                                                                                     
                                                                        <?php
                                                                      }
                                                                        
                                                                  ?>
                                                                    </select>
                                                                  </div>

                                                                    <div class="form-group">
                                                                      <label >No of Day Before Leave:</label>
                                                                      <input type="number" name="ndbf" id="mleave" value="<?php echo $row['leave_short']; ?>" class="form-control" >
                                                                    </div>

                                                                     <div class="form-group">
                                                                      <label >Can File Before:</label>
                                                                      <select class="form-control" name="filebfore" id="fb">
                                                                        <?php if ($row['leave_before']==1){ ?>
                                                                            <option value="1">Yes</option>
                                                                             <option value="0">No</option>
                                                                          <?php }else{ ?>
                                                                             <option value="0">No</option>
                                                                               <option value="1">Yes</option>
                                                                          <?php } ?>
                                                                     
                                                                      </select>
                                                                    </div>
                                                              </div>
                                                              <div class="col-lg-6">
                                                                  <div class="form-group">
                                                                    <label >Credits:</label>
                                                                    <input type="number" name="credits" value="<?php echo $row['leave_credits']; ?>" id="credits" class="form-control" >
                                                                  </div>

                                                                    <div class="form-group">
                                                                    <label >Minimum Leave:</label>
                                                                    <input type="number" name="minleave" value="<?php echo $row['leave_min']; ?>" id="credits" class="form-control" >
                                                                  </div>

                                                                   <div class="form-group">
                                                                    <label >No of Day After Leave:</label>
                                                                    <input type="number" name="ndal" value="<?php echo $row['leave_long']; ?>"  id="mleave" class="form-control" >
                                                                  </div>

                                                                   <label >Can File After:</label>
                                                                    <select class="form-control" name="fileafter" id="fb">
                                                                      <?php if ($row['leave_file_after']==1) { ?> 

                                                                      <option value="1">Yes</option>
                                                                      <option value="0">No</option>
                                                                        <?php }else{ ?>
                                                                      <option value="0">No</option>
                                                                            <option value="1">Yes</option>
                                                                        <?php } ?>
                                                                    </select>
                                                              </div>

                                                            </div>

                                                            <button type="submit" class="btn btn-success btn-block btnleaveupdate">UPDATE</button>
                                                         </form>
                                                    </div>
                                                    <div class="modal-footer">
                                                      <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                    </div>
                                                  </div>
                                                  
                                                </div>
                                              </div>
                                              <!-- end of modal -->
                    <!-- end of modal         -->
                              </tr>
            <?php

            }
    }
   if (isset($_GET['department'])){
  
             try{
        
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from departments left join companies where departments.CompID=companies.CompanyID");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['DepartmentID']; ?></td>
                                <td class="td-dar"><?php echo $row['DepartmentDesc']; ?></td>
                                <td class="td-dar"><?php echo $row['CompanyDesc']; ?></td>
                                <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
            <?php

            }
          }
        
   if (isset($_GET['hmo'])){
    try{
  
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from hmo");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                  <td class="td-dar"><?php echo $row['HMO_ID']; ?></td>
                  <td class="td-dar"><?php echo $row['HMO_PROVIDER']; ?></td>
              </tr>
            <?php

            }
   }
    if (isset($_GET['employeestatus'])){
    try{
         
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from estatus");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
               <tr>
                             
                   <td class="td-dar"><?php echo $row[0]; ?></td>
                  <td class="td-dar"><?php echo $row[1]; ?></td>
                               
               </tr>
            <?php

            }
   }
	  if (isset($_GET['joblevel'])){
    try{
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
             $statement = $pdo->prepare("select * from joblevel");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['jobLevelID']; ?></td>
                                <td class="td-dar"><?php echo $row['jobLevelDesc']; ?></td>
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                                               <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Job Level</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updatejoblevel=<?php echo $row[0]; ?>" class="frmjoblevel<?php echo $row[0]; ?>">
             
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Job Level ID:</label>
                          <input type="text" name="jid" readonly="readonly" value="<?php echo $row['jobLevelID']; ?>" id="jid" class="form-control" >
                        </div>
                    </div>

                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Job Level:</label>
                          <input type="text" name="joblevel" required="required" value="<?php echo $row['jobLevelDesc']; ?>" class="form-control" >
                      </div>
                      </div>

                    </div>
                           <button type="submit" class="btn btn-success btn-block btnjoblevela">Update</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
                              </tr>
            <?php
          }
   }
    if (isset($_GET['position'])){
 
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $statement = $pdo->prepare("select * from positions inner join departments on positions.DepartmentID=departments.DepartmentID");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
             <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['PositionDesc']; ?></td>
                                <td class="td-dar"><?php echo $row['DepartmentDesc']; ?></td>
                                <td class="td-dar"><?php
                                  $statement1 = $pdo->prepare("select * from joblevel where jobLevelID='" . $row['EmpJobLevelID'] . "'");
                                    $statement1->execute();
                                 $row1 = $statement1->fetch();
                                 echo $row1['jobLevelDesc']; 
                                 ?></td>
                               
                              </tr>
            <?php
          }
   }

     if (isset($_GET['relation'])){
 
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $statement = $pdo->prepare("select * from frelationship");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
             <tr>
                  <td class="td-dar"><?php echo $row[0]; ?></td>
                  <td class="td-dar"><?php echo $row[1]; ?></td>
               </tr>
            <?php
          }
   }
        if (isset($_GET['classification'])){
 
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $statement = $pdo->prepare("select * from empstatus");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
             <tr>
                        <td class="td-dar"><?php echo $row[0]; ?></td>
                        <td class="td-dar"><?php echo $row[1]; ?></td>
                        <td class="td-dar"><?php echo $row[2]; ?></td>
               </tr>
            <?php
          }
   }

        if (isset($_GET['worktime'])){
 
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $statement = $pdo->prepare("select * from workschedule");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
             <tr>
                        <td class="td-dar"><?php echo $row[0]; ?></td>
                        <td class="td-dar"><?php echo $row[1]; ?></td>
                        <td class="td-dar"><?php echo $row[2]; ?></td>
                        <td class="td-dar"><?php if($row[3]==1){echo "Yes";}else{echo "No";}  ?></td>
                        <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                                                     <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Work Time</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">

                        <form method="post" action="?updatewtime=<?php echo $row[0]; ?>"  class="frmworktimes">
              
                  <div class="row">
                      <div class="col-lg-4">
                        <div class="form-group">
                          <label >Time From:</label>
                          <input type="time" value="<?php echo date("H:i", strtotime("$row[1]")); ?>" name="timefrom" id="timefrom" class="form-control">
                        </div>
                    </div>
                      <div class="col-lg-4">
                        <div class="form-group">
                          <label >Time To:</label>
                          <input type="time" value="<?php echo date("H:i", strtotime("$row[2]")); ?>" name="timeto" id="timeto" class="form-control" >
                        </div>
                    </div>
                      <div class="col-lg-4">
                          <div class="form-group">
                            <label >Time Cross:</label>
                           <select  class="form-control" id="tcross" name="tcross">
                            <option value="<?php echo $row[3]; ?>"> <?php if($row[3]==1){echo "Yes";}else{echo "No";}  ?></option>
                            <?php if($row[3]==1)
                            {
                              ?>
                              <option value="0">No</option>
                              <?php 
                              }
                            else
                              {
                                 ?>
                                <option value="1">Yes</option>
                                <?php
                                } ?>

                            
                            
                         </select>
                          
                          </div>
                      </div>
                 

                    </div>
                           <button type="submit" required="required" class="btn btn-success  l">Submit</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
          </div>
               </tr>
            <?php
          }
   }
      if (isset($_GET['workday'])){
 
           
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $statement = $pdo->prepare("select * from workdays");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
             <tr>
                        <td class="td-dar"><?php echo $row[0]; ?></td>
                        <td class="td-dar"><?php echo $row[1]; ?></td>
               </tr>
            <?php
          }
   }
 


    if (isset($_GET['typeofl'])){

                try{
                      $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                         }
                      catch(PDOException $e)
                         {
                      die("ERROR: Could not connect. " . $e->getMessage());
                         }
                          $statement = $pdo->prepare("select * from  leaves");
                          $statement->execute();
                           while ($row = $statement->fetch()){
            ?>
                        <tr>
                          <td class="td-dar"><?php echo $row[0]; ?></td>
                          <td class="td-dar"><?php echo $row[1]; ?></td>
                           <td><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                        </tr>

                                                        <!-- The Modal -->
  <div class="modal" id="myModal<?php echo $row[0];  ?>">
    <div class="modal-dialog">
      <div class="modal-content">
      
        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title"></h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <!-- Modal body -->
        <div class="modal-body">
          <form action="?updattol=<?php echo $row[0]; ?>" method="post" class="frmup">
             <div class="col-lg-12">
                        <div class="form-group">
                          <label class="wrn-l">Leave Name:</label>
                          <input type="text" name="lname" id="ln<?php echo $row[0]; ?>" value="<?php echo $row[1]; ?>" class="form-control" >
                        </div>
                    </div>
        
              <button type="submit" id="<?php echo $row[0]; ?>" class="btn btn-success btn-block">Update</button>
          
          </form>
        </div>
        
        <!-- Modal footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
        </div>
        
      </div>
    </div>
  </div>
  <!-- end of modal -->
                <?php
                        }
             
    }
//for SIL LOAN 
if (isset($_GET['silloan'])){

                                include 'w_conn.php';
            try{
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
          $statement = $pdo->prepare("select * from silloan inner join employees on silloan.loanEmpID=employees.EmpID order by loanAmount asc");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                                <td class="td-dar"><?php echo $row['EmpFN'] . " " . $row['EmpLN']; ?></td>
                                <td class="td-dar"><?php echo $row['loanAmount']; ?></td>
                                <td class="td-dar"><?php echo $row['LoanType']; ?></td>
                                <td class="td-dar"><?php echo $row['loanStatus']; ?></td>
                               
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">SIL LOAN</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updatesilloan=<?php echo $row[0];  ?>"  class="updatesilloan<?php echo $row[0];  ?>">
                     
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >Loan Amount:</label>
                            <input type="number" name="ee" value="<?php echo $row['loanAmount'];  ?>" id="ee" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >Loan Type:</label>
                               <select name="er" id="er" class="form-control">
                                  <option value="<?php echo $row['LoanType']; ?>"><?php echo $row['LoanType']; ?></option>
                                    <option value="SSS">SSS</option>
                                    <option value="PI">PI</option>
                                    <option value="SIL">SIL</option>
                                </select>
                            </div>
                        </div>
                         <div class="col-lg-6">
                            <div class="form-group">
                            <label >Loan Status:</label>
                             <select name="er" id="er" class="form-control">
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="INACTIVE">INACTIVE</option>
                                </select>
                            </div>
                        </div>
                    </div>

                           <button type="submit" required="required" class="btn btn-success btn-block">Update</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
          </div>
         


<?php
    }
}
//family parental 
if (isset($_GET['familyparental'])){
  try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                 }
              catch(PDOException $e)
                 {
              die("ERROR: Could not connect. " . $e->getMessage());
                 }
    $statement = $pdo->prepare("select * from parentalrel inner join employees on parentalrel.EmpID=employees.EmpID order by EmpLN");
    $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['Name']; ?></td>
                                <td class="td-dar"><?php echo $row['EmpFN'] . " " . $row['EmpLN']; ?></td>
                                <td class="td-dar"><?php echo $row['DateofBirth']; ?></td>
                              
                               <!--   <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td> -->
                              </tr>

<?php
    }
}
?>
  

<?php
//sss 
if (isset($_GET['sss'])){
?>
   <?php
                                include 'w_conn.php';
            try{
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from sss order by SalaryFrom asc");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                                <td class="td-dar"><?php echo $row['sssc']; ?></td>
                                <td class="td-dar"><?php echo $row['SalaryFrom']; ?></td>
                                <td class="td-dar"><?php echo $row['SalaryTo']; ?></td>
                                <td class="td-dar"><?php echo $row['SSER']; ?></td>
                                <td class="td-dar"><?php echo $row['SSEE']; ?></td>
                                <td class="td-dar"><?php echo $row['SSEC']; ?></td>
                               
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">SSS</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updatesss=<?php echo $row[0];  ?>"  class="frmagency<?php echo $row[0];  ?>">
                        <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >SSSC:</label>
                            <input type="number" name="SSSC" value="<?php echo $row['sssc'];  ?>" id="SSSC" class="form-control" >
                            </div>  

                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >Salary From:</label>
                            <input type="number" name="salaryfrom" value="<?php echo $row['SalaryFrom'];  ?>" id="salaryfrom" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >Salary To:</label>
                                <input type="number" name="salaryto" value="<?php echo $row['SalaryTo'];  ?>" id="salaryto" class="form-control" >
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >SSER:</label>
                            <input type="number" name="SSER"  value="<?php echo $row['SSER'];  ?>"  id="SSER" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >SSEE:</label>
                                <input type="number" name="SSEE" value="<?php echo $row['SSEE'];  ?>" id="SSEE" class="form-control" >
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >SSEC:</label>
                            <input type="number" name="SSEC" value="<?php echo $row['SSEC'];  ?>" id="SSEC" class="form-control" >
                            </div>
                        </div>
                    </div>
                           <button type="submit" required="required" class="btn btn-success btn-block btnagencys">Update</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
          </div>
            <?php

            }
            ?>

<?php
}

// end of sss/

// pag ibig
if (isset($_GET['pagibig'])){
  ?>
 <?php
                                include 'w_conn.php';
            try{
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from pagibig order by EE asc");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                                <td class="td-dar"><?php echo $row['EE']; ?></td>
                                <td class="td-dar"><?php echo $row['ER']; ?></td>
                               
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">PAG IBIG</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updatepagibig=<?php echo $row[0];  ?>"  class="updatepagibig<?php echo $row[0];  ?>">
                     
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >EE:</label>
                            <input type="number" name="er" value="<?php echo $row['EE'];  ?>" id="ee" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >ER:</label>
                                <input type="number" name="er" value="<?php echo $row['ER'];  ?>" id="er" class="form-control" >
                            </div>
                        </div>

                    </div>

                           <button type="submit" required="required" class="btn btn-success btn-block btnagencys">Update</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
          </div>
            <?php

            }
            ?>
  <?php
}
// end of pagibig
if (isset($_GET['philhealth'])){
  ?>
     <?php
                                include 'w_conn.php';
            try{
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
               }
            catch(PDOException $e)
               {
            die("ERROR: Could not connect. " . $e->getMessage());
               }
            $statement = $pdo->prepare("select * from philhealth order by PHSB asc");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                                <td class="td-dar"><?php echo $row['PHSB']; ?></td>
                                <td class="td-dar"><?php echo $row['SalaryFrom']; ?></td>
                                <td class="td-dar"><?php echo $row['SalaryTo']; ?></td>
                                <td class="td-dar"><?php echo $row['PHEE']; ?></td>
                                <td class="td-dar"><?php echo $row['PHER']; ?></td>
                               
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
                                  <!-- The Modal -->
                            <div class="modal" id="myModal<?php echo $row[0];  ?>">
                                <div class="modal-dialog">
                                <div class="modal-content">

                                    <!-- Modal Header -->
                                    <div class="modal-header">
                                    <h4 class="modal-title">PhilHealth</h4>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>

                                    <!-- Modal body -->
                                    <div class="modal-body ob-body">
                                            <form method="post" action="?updatepagibig=<?php echo $row[0];  ?>"  class="updatepagibig<?php echo $row[0];  ?>">
                                        
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="form-group">
                                                        <label >PHSB:</label>
                                                        <input type="number" name="PHSB" id="PHSB" value="<?php echo $row['PHSB']; ?>" class="form-control" >
                                                    </div>
                                                </div>

                                            

                                        </div>
                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label >SalaryFrom:</label>
                                                    <input type="number" name="SalaryFrom" value="<?php echo $row['SalaryFrom']; ?>" id="SalaryFrom" class="form-control" >
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label >SalaryTo:</label>
                                                    <input type="number" name="SalaryTo" value="<?php echo $row['SalaryTo']; ?>" id="SalaryTo" class="form-control" >
                                                </div>
                                            </div>

                                        </div>

                                        <div class="row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label >PHEE:</label>
                                                    <input type="number" name="PHEE" value="<?php echo $row['PHEE']; ?>" id="PHEE" class="form-control" >
                                                </div>
                                            </div>

                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <label >PHER:</label>
                                                    <input type="number" name="PHER" value="<?php echo $row['PHER']; ?>" id="PHER" class="form-control" >
                                                </div>
                                            </div>

                                        </div>

                           <button type="submit" required="required" class="btn btn-success btn-block">Update</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
          </div>
            <?php

            }
            ?>
 <?php
}
?>