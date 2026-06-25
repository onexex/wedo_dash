
<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
               <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Job Levels</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Job Level</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Job Level</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form class="frmjoblevel">
             
                  <div class="row">

                      <div class="col-lg-6">
                              <div class="form-group">
                      <label for="sel1">Company:</label>
                      <select class="form-control" id="empcompid" name="empcompany">
                      

                        <?php
                        if ($_SESSION['UserType']==1){

                      $sql=mysqli_query($con, "select * from companies");
                      echo "  <option></option>";
                        }else{
                      $sql=mysqli_query($con, "select * from companies where CompanyID='" . $_SESSION['CompID'] ."'");

                        }
                                  while($res=mysqli_fetch_array($sql)){
                              ?>
                        <option  value="<?php echo $res['CompanyID']; ?>"><?php echo $res['CompanyDesc']; ?>  </option>
                              <?php   
                                  }
                                  ?>
                      </select>
                    </div> 

                        <div class="form-group">
                          <label >Job Level ID:</label>
                          <input type="text" name="jid" id="jid" class="form-control" >
                        </div>
                    </div>

                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Job Level:</label>
                          <input type="text" name="joblevel" class="form-control" >
                      </div>
                      </div>

                    </div>
                           <button type="button" class="btn btn-success btn-block btnjoblevel">Submit</button>
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
        <th class="darth col-darth">Job Level ID</th>
        <th class="darth col-darth">Job Level</th>
        <th class="darth col-darth">Action</th>
      </tr>
    </thead>

    <tbody class="maintbody" id="maintbody">
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

                       $statement = $pdo->prepare("select * from joblevel");
            $statement->execute();
                        }else{
                            $statement = $pdo->prepare("select * from joblevel where CompanyID=:empcid");
                             $statement->bindParam(':empcid', $_SESSION['CompID']);
            $statement->execute();

                        }
          
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['jobLevelID']; ?></td>
                                <td class="td-dar"><?php echo $row['jobLevelDesc']; ?></td>
                                    <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
              </tr>
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