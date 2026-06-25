

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">SSS (Social Security System)</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ SSS</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">SSS</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmsssssave">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >SSSC:</label>
                            <input type="number" name="SSSC" id="SSSC" class="form-control" >
                            </div>  

                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >Salary From:</label>
                            <input type="number" name="salaryfrom" id="salaryfrom" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >Salary To:</label>
                                <input type="number" name="salaryto" id="salaryto" class="form-control" >
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >SSER:</label>
                            <input type="number" name="SSER" id="SSER" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >SSEE:</label>
                                <input type="number" name="SSEE" id="SSEE" class="form-control" >
                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >SSEC:</label>
                            <input type="number" name="SSEC" id="SSEC" class="form-control" >
                            </div>
                        </div>
                    </div>
                           <button type="button" required="required" class="btn btn-success btn-block btnssss">Submit</button>
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
        <th class="darth col-darth">SSSC</th>
        <th class="darth col-darth">SalaryFrom</th>
        <th class="darth  col-darth">SalaryTo</th>
        <th class="darth  col-darth">SSER</th>
        <th class="darth  col-darth">SSEE</th>
        <th class="darth  col-darth">SSEC</th>
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
                        <form method="post" action="?updatesss=<?php echo $row[0];  ?>"  class="updatesss<?php echo $row[0];  ?>">
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