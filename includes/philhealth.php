

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">PhilHealth</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ PhilHealth</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">PhilHealth</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmphilhealthssave">
                  
                    <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label >PHSB:</label>
                                    <input type="number" name="PHSB" id="PHSB" class="form-control" >
                                </div>
                            </div>

                           

                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >SalaryFrom:</label>
                                <input type="number" name="SalaryFrom" id="SalaryFrom" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >SalaryTo:</label>
                                <input type="number" name="SalaryTo" id="SalaryTo" class="form-control" >
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >PHEE:</label>
                                <input type="number" name="PHEE" id="PHEE" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >PHER:</label>
                                <input type="number" name="PHER" id="PHER" class="form-control" >
                            </div>
                        </div>

                    </div>

                    <button type="button" required="required" class="btn btn-success btn-block btnphilhealth">Submit</button>
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
        <th class="darth col-darth">PHSB</th>
        <th class="darth col-darth">SalaryFrom</th>
        <th class="darth col-darth">SalaryTo</th>
        <th class="darth col-darth">PHEE</th>
        <th class="darth col-darth">PHER</th>
        <th class="darth col-darth">UPDATE</th>
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
                                            <form method="post" action="?updatephilhealth=<?php echo $row[0];  ?>"  class="updatephilhealth<?php echo $row[0];  ?>">
                                        
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