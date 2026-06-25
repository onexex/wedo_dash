
<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
               <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Positions</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Position</button>
             <!-- The Modal -->
            <div class="modal" id="newform">
              <div class="modal-dialog">
                <div class="modal-content">

                  <!-- Modal Header -->
                  <div class="modal-header">
                    <h4 class="modal-title">Position</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                  </div>

                  <!-- Modal body -->
                  <div class="modal-body ob-body">
                    <form method="post" class="frmpos">
                      <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >Company:</label>
                                <select id="comchange" class="form-control">
                                  <option></option>
                                  <?php
                                    try{
                                          include 'w_conn.php';
                                          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                                          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                            } catch(PDOException $e)
                                            {
                                                die("ERROR: Could not connect. " . $e->getMessage());
                                            }
                                            if ($_SESSION['UserType']==1){
                                                $statement = $pdo->prepare("select * from companies");
                                                echo " ";
                                              }else if ($_SESSION['UserType']==2) {
                                                $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");
                                              }else{
                                                $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");
                                              }                              
                                              $statement->execute();
                                          while ($row = $statement->fetch()){?>
                                            <option value="<?php echo $row['CompanyID']; ?>"><?php echo $row['CompanyDesc']; ?></option>
                                          <?php } ?>
                                </select>
                              </div>

                            <div class="form-group">
                              <label >Department:</label>
                              <select id="dep" name="dep" required="required" class="form-control">
                                <option></option>
                                    <?php
                                try{
                                    include 'w_conn.php';
                                    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                  }catch(PDOException $e){
                                      die("ERROR: Could not connect. " . $e->getMessage());
                                  }
                                    $statement = $pdo->prepare("select * from departments inner join companies where departments.CompID=companies.CompanyID");
                                    $statement->execute();
                                    while ($row = $statement->fetch()){ ?>
                                      <option id="<?php echo $row['CompanyID']; ?>" value="<?php echo $row['DepartmentID']; ?>"><?php echo $row['DepartmentDesc']; ?></option>
                                    <?php }?>
                              </select>
                            </div>
                        </div>
                        <div class="col-lg-6">
                          <div class="form-group">
                            <label >Position:</label>
                            <input type="text" name="pos" id="pos" required="required" class="form-control" >
                          </div>

                          <div class="form-group">
                            <label >job Level:</label>
                            <select name="joblevel" id="joblevel" required="required" class="form-control">
                                <option></option>
                                <?php
                                  try{
                                      include 'w_conn.php';
                                      $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    } catch(PDOException $e){
                                      die("ERROR: Could not connect. " . $e->getMessage());
                                    }
                                  $statement = $pdo->prepare("select * from joblevel");
                                  $statement->execute();
                                  while ($row = $statement->fetch()){?>
                                  <option value="<?php echo $row['jobLevelID']; ?>"><?php echo $row['jobLevelDesc']; ?></option>
                                  <?php }?>
                            </select>
                          </div>
                        </div>
                      </div>
                      <button type="submit" class="btn btn-success btn-block btnposition">Submit</button>
                    </form>
                  </div>

                  <!-- Modal footer -->
                  <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                  </div>

                </div>
              </div>
            </div>

              <div class="w-container">
                  <div class="container-format">       
                  <table class="table table-bordered">

                    <thead>
                      <tr>
                        <th class="darth col-darth">Position</th>
                        <th class="darth  col-darth">Department</th>
                        <th class="darth  col-darth">Job Level</th>
                        <th class="darth  col-darth">Action</th>
                      </tr>
                    </thead>

                    <tbody class="maintbody" id="maintbody">
                      <?php
                        try{
                            include 'w_conn.php';
                            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                              }catch(PDOException $e){
                                die("ERROR: Could not connect. " . $e->getMessage());
                              }
                                if($_SESSION['UserType']==1){
                                  $statement = $pdo->prepare("select * from positions inner join departments on positions.DepartmentID=departments.DepartmentID");
                                }
                                else if($_SESSION['UserType']==2){
                                  $statement = $pdo->prepare("select * from positions inner join departments on positions.DepartmentID=departments.DepartmentID where CompID='$_SESSION[CompID]'");
                                }
                                else{
                                  $statement = $pdo->prepare("select * from positions inner join departments on positions.DepartmentID=departments.DepartmentID where CompID='$_SESSION[CompID]'");
                                }
                          
                            $statement->execute();
                            while ($row = $statement->fetch()){?>
                              <tr>      
                                <td class="td-dar"><?php echo $row['PositionDesc']; ?></td>
                                <td class="td-dar"><?php echo $row['DepartmentDesc']; ?></td>
                                <td class="td-dar">
                                  <?php $statement1 = $pdo->prepare("select * from joblevel where jobLevelID='" . $row['EmpJobLevelID'] . "'");
                                  $statement1->execute();
                                  $row1 = $statement1->fetch();
                                  echo $row1['jobLevelDesc'];
                                  ?>
                                  </td>
                                <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myview<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                                
                              </tr>
                          <!-- The Modal -->
                          <div class="modal" id="myview<?php echo $row[0];  ?>">
                            <div class="modal-dialog">
                              <div class="modal-content">
                                <!-- Modal Header -->
                                <div class="modal-header">
                                  <h4 class="modal-title"><?php echo $row['PositionDesc']; ?></h4>
                                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>

                                <!-- Modal body -->
                                <div class="modal-body ob-body">
                                  <form method="post" action="?updatepos=<?php echo $row[0]; ?>" class="frmpos<?php echo $row[0]; ?>">
                              
                                    <div class="row">
                                      <div class="col-lg-6">
                                          <div class="form-group">
                                            <label >Company:</label>
                                              <select id="comchange" class="form-control cmchange">
                              
                                                <?php
                                                  try{
                                                        include 'w_conn.php';
                                                        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                                                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                                            }catch(PDOException $e){
                                                            die("ERROR: Could not connect. " . $e->getMessage());
                                                            }
                                                              $statement11 = $pdo->prepare("select * from companies where CompanyID='$row[CompID]'");
                                                              $statement11->execute();
                                                              $row11 = $statement11->fetch();

                                                              ?>
                                                              <option value="<?php echo $row11['CompanyID']; ?>"><?php echo $row11['CompanyDesc']; ?></option>
                                                              <?php

                                                            if ($_SESSION['UserType']==1){
                                                              $statement1 = $pdo->prepare("select * from companies");
                                                              echo " ";
                                                            }  
                                                            else if ($_SESSION['UserType']==2) {
                                                              $statement1 = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");
                                                            }    
                                                            else{
                                                              $statement1 = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");
                                                            }                              

                                                          $statement1->execute();
                                                        while ($row1 = $statement1->fetch()){
                                                        ?>
                                                          <option value="<?php echo $row1['CompanyID']; ?>"><?php echo $row1['CompanyDesc']; ?></option>
                                                        <?php
                                                      }
                                                        
                                                  ?>
                                            </select>
                                          </div>
                                          <div class="form-group">
                                            <label >Department:</label>
                                            <select id="dep" name="dep" required="required" class="form-control">
                                              <?php
                                              try{
                                                    include 'w_conn.php';
                                                    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                                  }catch(PDOException $e){
                                                    die("ERROR: Could not connect. " . $e->getMessage());
                                                  }
                                              $statement2 = $pdo->prepare("select * from departments inner join companies where departments.CompID=companies.CompanyID");
                                              $statement2->execute();
                                              while ($row2 = $statement2->fetch()){?>
                                                <option id="d<?php echo $row2['CompanyID']; ?>" value="<?php echo $row2['DepartmentID']; ?>"><?php echo $row2['DepartmentDesc']; ?></option>
                                              <?php } ?>
                                            </select>
                                          </div>
                                      </div>

                                      <div class="col-lg-6"> 
                                        <div class="form-group">
                                          <label >Position:</label>
                                          <input type="text" name="pos" value="<?php echo $row['PositionDesc']; ?>" id="pos" required="required" class="form-control" >
                                        </div>
                                          <div class="form-group">
                                            <label >job Level:</label>
                                            <select name="joblevel"  id="joblevel" required="required" class="form-control">
                                              <?php
                                              try{
                                                    include 'w_conn.php';
                                                    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                                  }catch(PDOException $e){
                                                    die("ERROR: Could not connect. " . $e->getMessage());
                                                  }
                                                  $statement3 = $pdo->prepare("select * from joblevel");
                                                  $statement3->execute();
                                                  while ($row3 = $statement3->fetch()){?>
                                                    <option value="<?php echo $row3['jobLevelID']; ?>"><?php echo $row3['jobLevelDesc']; ?></option>             
                                                  <?php } ?>
                                            </select>
                                          </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success btn-block btnup">UPDATE</button>
                                  </form>
                                </div>

                                <!-- Modal footer -->
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                </div>

                              </div>
                            </div>
                          </div>

                            <?php } ?>

                    </tbody>
                  </table>
                  </div>
              </div>
          </div>
    </div>
  </div>