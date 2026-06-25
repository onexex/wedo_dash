
<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                                  <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Leave Valdiations</h4>
          		 <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Leave Validation</button>
          		    <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Leave</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form  method="post" class="addingfrmleaveval">
                  
                  <div class="row">
                  	<div class="col-lg-6">
                             <div class="form-group">
                          <label >Company:</label>
                          <select class="form-control" name="compid" id="compid">
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
                                 if ($_SESSION['UserType']==1){

                                  $statement = $pdo->prepare("select * from companies");
                                 }else{
                                    $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");

                                 }
                              $statement->execute(array(':name' => ""));
                              while ($row = $statement->fetch()){
                              ?>
                                 <option value="<?php echo $row['CompanyID']; ?>"><?php echo $row['CompanyDesc']; ?></option> 
                                           
                              <?php
                            }
                              
                        ?>
                          </select>
                        </div>
                    </div>
                    <div class="col-lg-6">
                            <div class="form-group">
                          <label >Leave Type:</label>
                          <select class="form-control" name="ltype" id="compid">
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
                           
                                    $statement = $pdo->prepare("select * from leaves");
                                 
                              $statement->execute();
                              while ($row = $statement->fetch()){
                              ?>
                                 <option value="<?php echo $row['LeaveID']; ?>"><?php echo $row['LeaveDesc']; ?></option> 
                                           
                              <?php
                            }
                              
                        ?>
                          </select>
                        </div>
                    </div>
                    </div>

                     <div class="col-lg-6">
                        <div class="form-group">
                          <label >Credits:</label>
                          <input type="number" name="credits" id="credits" class="form-control" >
                        </div>
                    </div>
              <!--         <div class="col-lg-6">
                        <div class="form-group">
                          <label >Minimum Leave:</label>
                          <input type="number" name="mleave" id="mleave" class="form-control" >
                        </div>
                    </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >No of Day Before Leave:</label>
                          <input type="number" name="ndbf" id="mleave" class="form-control" >
                        </div>
                    </div>
                     <div class="col-lg-6">
                        <div class="form-group">
                          <label >No of Day After Leave:</label>
                          <input type="number" name="ndal" id="mleave" class="form-control" >
                        </div>
                    </div>
                       <div class="col-lg-6">
                        <div class="form-group">
                          <label >File Before:</label>
                          <select class="form-control" name="filebfore" id="fb">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                          </select>
                        </div>
                    </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >File After:</label>
                          <select class="form-control" name="filebfore" id="fb">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                          </select>
                        </div>
                    </div>
                        <div class="col-lg-6">
                        <div class="form-group">
                          <label >No of Day Filing After Leave:</label>
                          <select class="form-control" name="filebfore" id="fb">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                          </select>
                        </div>
                    </div>
 -->


                           <button type="button" class="btn btn-success btn-block btnleave">Save</button>
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
                 include 'w_conn.php';
                    try{
                      $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                       }
                    catch(PDOException $e)
                       {
                    die("ERROR: Could not connect. " . $e->getMessage());
                       }
                       if ($_SESSION['UserType']==1){
                              $statement = $pdo->prepare("select * from companies");
                            $statement->execute(); 
                       }else{
                                $statement = $pdo->prepare("select * from companies where CompanyID=:lcr");
                                  $statement->bindParam(':lcr', $_SESSION['CompID']);
                            $statement->execute(); 
                       }
              

                 
                    if ((isset($_SESSION['CompID'])) || ($_SESSION['CompID']!=0)){
                          $stm = $pdo->prepare("select * from companies  where CompanyID=:id");
                          $stm->bindParam(':id', $_SESSION['CompID']);
                          $stm->execute(); 
                          $row=$stm->fetch();

                    }
                    else{

              ?>
                    <div style="margin-top:10px;" class="dropdown">
                        <button type="button" class="btn btn-danger dropdown-toggle" data-toggle="dropdown">
                          Select Company
                        </button>
                        <div class="dropdown-menu ths">
                          <?php 
                          while ($row=$statement->fetch()) {
                         ?>

                          <a class="dropdown-item btnchangelval" id="<?php echo $row[1]; ?>"><?php echo $row['CompanyDesc']; ?></a>
                         <?php
                          }
                          ?>
                        </div>
                      </div>
              <?php
                    }
              ?>

          <div class="w-container">
            <br>
              <table class="table table-bordered">
                <thead>
                    <tr>
                      <th class="darth col-darth">Company Name</th>
                      <th class="darth col-darth">Leave</th>
                      <th class="darth col-darth">Credits</th>
                      <th class="darth  col-darth">Minimum Leave</th>
                      <th class="darth  col-darth">No. of Day filling Before Leave</th>
                      <th class="darth  col-darth">File Before</th>
                      <th class="darth  col-darth">File After</th>
                      <th class="darth  col-darth">No. of Day filing After Leave</th>
                      <th class="darth  col-darth">File HalfDay</th>
                      <th class="darth  col-darth">Update</th>
                    </tr>
                  </thead>  
                  <tbody id="lvview">
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
                
                 
                    if ((isset($_SESSION['CompID'])) || ($_SESSION['CompID']!=0)){
                   
                          $stm = $pdo->prepare("select * from companies  where CompanyID=:id");
                          $stm->bindParam(':id', $_SESSION['CompID']);
                          $stm->execute(); 
                          $row2=$stm->fetch();

                          $statement = $pdo->prepare("select * from leaves_validation left join leaves on leaves_validation.lid=leaves.LeaveID where leaves_validation.compid=:lcr or 1=1");
                          $statement->bindParam(':lcr', $_SESSION['CompID']);
                          $statement->execute();
                          while ($row = $statement->fetch()){
                        ?>
                          <tr>
                                            <td class="td-dar"><?php echo $row2['CompanyDesc']; ?></td>
                                            <td class="td-dar"><?php echo $row['LeaveDesc']; ?></td>  
                                            <td class="td-dar"><?php echo $row['leave_credits']; ?></td>  
                                            <td class="td-dar"><?php echo $row['leave_min']; ?></td>  
                                            <td class="td-dar"><?php echo $row['filing_before_duration']; ?></td> 
                                            <td class="td-dar"><?php if ($row['leave_before']==1){ echo "YES"; }else{ echo "NO"; } ?></td>  
                                            <td class="td-dar"><?php if ($row['leave_file_after']==1){ echo "YES"; }else{ echo "NO"; } ?></td>  
                                
                                            <td class="td-dar"><?php echo $row['filing_after_duration']; ?></td>  
                                            <td class="td-dar"><?php  if ($row['IsHalfDay']==1){ echo "YES"; }else{ echo "NO"; } ?></td>  
                                           <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                                          
                                      
                                      

                                          </tr>

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
                                                                    <select class="form-control" name="ltype" id="compid">
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
                                                                      <input type="number" name="ndbf" id="mleave" value="<?php echo $row['filing_before_duration']; ?>" class="form-control" >
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
                                                                      <?php if ($row['filing_after_duration']==1) { ?> 

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
                        <?php

                        }
          

                    }
                  

              ?>
                  
                  </tbody>
                </table>

          </div>

          </div>
      </div>
</div>  
  <!-- The Modal -->
        <div class="modal" id="modalWarning">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>
              
              <!-- Modal body -->
              <div class="modal-body">
                <div class="alert alert-success">
            
            </div>
              </div>
              
              <!-- Modal footer -->
           
              
            </div>
          </div>
        </div>
        <!-- modal end -->  