
<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                                  <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">OT Filing Maintenance </h4>
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

                    $statement = $pdo->prepare("select * from companies");
                    $statement->execute(); 

                 
                    if ($_SESSION['CompID']!=0){
                          $stm = $pdo->prepare("select * from companies  where CompanyID=:id");
                          $stm->bindParam(':id', $_SESSION['CompID']);
                          $stm->execute(); 
                          $row=$stm->fetch();

                    }
                    else{

              ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-danger dropdown-toggle" data-toggle="dropdown">
                          Select Company
                        </button>
                        <div class="dropdown-menu">
                          <?php 
                          while ($row=$statement->fetch()) {
                         ?>

                          <a class="dropdown-item" id="<?php echo $row[1]; ?>"><?php echo $row['CompanyDesc']; ?></a>
                         <?php
                          }
                          ?>
                        </div>
                      </div>
              <?php
                    }
              ?>


              <br>
            <h4 class="company-name" style="color: red; border-bottom: 1px solid red; padding-bottom: 10px;"><?php if (isset($row)){ echo $row['CompanyDesc']; }  ?></h4>
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>File Before</th>
                  <th>No of Days Before</th>
                  <th>File After</th>
                  <th>No of Days After</th>
                  <th>Is Holiday</th>
                  <th>Is Tardy</th>
                  <th>Days not Allowed</th>
                  <th>Update</th>
                </tr>
              </thead>
              <tbody id="maintbody">
                <?php
                    if ($_SESSION['CompID']!=0){
                      try{
                          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                      }
                      catch(PDOException $e)
                      {
                          die("ERROR: Could not connect. " . $e->getMessage());
                      }

                          $statement = $pdo->prepare("select * from otfsmaintenance where compid=:id");
                          $statement->bindParam(':id', $_SESSION['CompID']);
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
                            <td><?php if ($row[7]==0) { echo "Yes";  }else{ echo "No"; } ?>dfdf</td>
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
                ?>
                        
                  
                <?php
                    }else{
                ?>
                <tr>
                    <td>Empty</td>
                    <td>Empty</td>
                    <td>Empty</td>
                    <td>Empty</td>
                    <td>Empty</td>
                    <td>Empty</td>
                    <td>Empty</td>
                    <td>Empty</td>
                </tr> 
                <?php
                    }
                ?>
               
              </tbody>
            </table>
         </div>
    </div>
</div>