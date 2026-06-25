

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                   <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Work Time</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Work Time</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Work Time</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmworktime">
              
                  <div class="row">
                      <div class="col-lg-4">
                        <div class="form-group">
                          <label >Time From:</label>
                          <input type="time" name="timefrom" id="timefrom" class="form-control" >
                        </div>
                    </div>
                      <div class="col-lg-4">
                        <div class="form-group">
                          <label >Time To:</label>
                          <input type="time" name="timeto" id="timeto" class="form-control" >
                        </div>
                    </div>
                   <div class="col-lg-4">
                          <div class="form-group">
                            <label >Time Cross:</label>
                           <select  class="form-control" id="tcross" name="tcross">
                            <option></option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                         </select>
                          
                          </div>
                      </div>

                    </div>
                           <button type="button" required="required" class="btn btn-success  btnworktime">Submit</button>
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
        <th class="darth col-darth">No.</th>
        <th class="darth col-darth">Time From</th>
        <th class="darth col-darth">Time To</th>
        <th class="darth col-darth">Time Cross</th>
        <th class="darth col-darth">Action</th>
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
            $statement = $pdo->prepare("select * from workschedule ");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row[0]; ?></td>
                                <td class="td-dar"><?php echo $row[1]; ?></td>
                                <td class="td-dar"><?php echo $row[2]; ?></td>
                                <td class="td-dar"><?php if($row[3]==1){echo "Yes";}else{echo "No";}  ?></td>
                                <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                               
                              </tr>
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