

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Lilo Validation</h4>
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
                                $statement = $pdo->prepare("select * from lilovalidation where EmpCompID=:id");
                                $statement->bindParam(':id', $_SESSION['CompID']);
                                $statement->execute();
                                $rcount=$statement->rowCount();
                           
                                if ($rcount>0){

                                }else{
                                  ?>
                                 <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Lilo Validation</button>
                                  <?php
                                }
            ?>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Lilo Validation</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmllilo">
                
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Grace Period:</label>
                          <input type="number" name="gperiod" id="gperiod" class="form-control" >
                        </div>
                    </div>

                     

                    </div>
                           <button type="button" required="required" class="btn btn-success btn-block btngraceper">Submit</button>
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
        <th class="darth col-darth">Grace Period</th>
        <th class="darth  col-darth">Updated Time</th>
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
            $statement = $pdo->prepare("select * from lilovalidation  where EmpCompID=:id");
                    $statement->bindParam(':id', $_SESSION['CompID']);
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php 
                                echo $row['EmpGP']; 
                                ?></td>
                                 <td class="td-dar"><?php 
                                echo $row['DTInputed']; 
                                ?></td>
                              
                          
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">LILO Validation</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updateliloval=<?php echo $row[0];  ?>"  class="frmagency<?php echo $row[0];  ?>">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                        <label >Grace Period:</label>
                          <input type="number" name="gperiod" value="<?php echo $row['EmpGP']; ?>" id="gperiod" class="form-control" >
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
