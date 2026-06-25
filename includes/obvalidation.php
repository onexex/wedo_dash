

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">OB Validation</h4>
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
            $statement = $pdo->prepare("select * from obvalidation  where compid=:id");
                    $statement->bindParam(':id', $_SESSION['CompID']);
            $statement->execute();
            $rc=$statement->rowCount();
            if ($rc>0){

            }else{
            ?>
              <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ OB Validation</button>
            <?php
            }
            ?>
                               
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">OB Validation</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmobval">
                
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Can FIle Before?:</label>
                           <select class="form-control" name="fbefore" id="fbefore">
                            <option value="2">Yes</option>
                            <option value="1">No</option>
                          </select>
                        </div>
                    </div>
                     <div class="col-lg-6">
                        <div class="form-group">
                          <label >Days Before?:</label>
                           <input type="number" name="dysbefore" class="form-control">
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                          <label >Can FIle After?:</label>
                           <select class="form-control" name="fafter" id="fafter">
                            <option value="2">Yes</option>
                            <option value="1">No</option>
                          </select>
                        </div>
                    </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Days After?:</label>
                           <input type="number" name="dysafter" class="form-control">
                        </div>
                    </div>
                    </div>
                           <button type="button" required="required" class="btn btn-success btn-block btnobval">Submit</button>
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
<BR>  
  
  <div class="container-format">       
  <table class="table table-bordered">

    <thead>
      <tr>
        <th class="darth col-darth">Can File Before</th>
        <th class="darth col-darth">Days Before</th>
        <th class="darth col-darth">Can File After</th>
        <th class="darth col-darth">Days After</th>
        <th class="darth  col-darth">Update</th>
      </tr>
    </thead>

    <tbody class="maintbody" id="maintbody">
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
            $statement = $pdo->prepare("select * from obvalidation  where compid=:id");
                    $statement->bindParam(':id', $_SESSION['CompID']);
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php 
                               if ($row['IsBefore']==2){ echo "Yes"; } else{ echo "No"; }; 
                                ?></td>
                                  <td class="td-dar"><?php 
                              echo $row['DaysBefore']; ?></td>
                                 <td class="td-dar"><?php 
                                   if ($row['IsAfter']==2){ echo "Yes"; } else{ echo "No"; }; 
                                
                                ?></td>
                                 <td class="td-dar"><?php 
                              echo $row['DaysAfter']; ?></td>
                          
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">OB Validation</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updateobval=<?php echo $row[0];  ?>"  class="frmagency<?php echo $row[0];  ?>">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Can FIle Before?:</label>
                           <select class="form-control" name="fbefore" id="fbefore">
                            <?php 
                               if ($row['IsBefore']==2){ 
                                ?>
                                    <option value="2">Yes</option>
                                  <option value="1">No</option>
                                <?php

                              }  else{
                              ?>
                                  <option value="1">No</option>
                                    <option value="2">Yes</option>
                                <?php
                              }
                                ?>
                          
                          </select>
                        </div>
                    </div>
                     <div class="col-lg-6">
                          <div class="form-group">
                          <label >Days After?:</label>
                              <input type="number" value="<?php echo $row['DaysBefore']; ?>" name="dysbefore" class="form-control">
                          </div>
                     </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                          <label >Can FIle After?:</label>
                           <select class="form-control"  name="fafter" id="fafter">
                            <?php 
                               if ($row['IsAfter']==2){ 
                                ?>
                                    <option value="2">Yes</option>
                                  <option value="1">No</option>
                                <?php

                              } else{  ?>
                                  <option value="1">No</option>
                                    <option value="2">Yes</option>
                                <?php
                              }
                                ?>
                          </select>
                        </div>
                    </div>
                       <div class="col-lg-6">
                          <div class="form-group">
                          <label >Days After?:</label>
                              <input type="number" value="<?php echo $row['DaysAfter']; ?>" name="dysafter" class="form-control">
                          </div>
                     </div>
                </div>
                
                           <button type="submit" required="required" class="btn btn-success btn-block btnvalidation">Update</button>
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

    </tbody>
  </table>
</div>
<br>
<br>
<br>
<br>
  
         </div>
    </div>
</div>
