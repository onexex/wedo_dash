

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                     <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">HMO</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ HMO</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">HMO</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" class="frmhmo">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >ID:</label>
                          <input type="text" name="hmoid" id="hmoid" class="form-control" >
                        </div>               

                      </div>
                      <div class="col-lg-6">
                           <div class="form-group">
                          <label >Name:</label>
                          <input type="text" name="hmoname" id="hmoname" class="form-control" >
                      </div>
                      </div>

                    </div>
                           <button type="button" class="btn btn-success btn-block btnhmo">Submit</button>
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
        <th class="darth col-darth">ID No</th>
        <th class="darth col-darth">Name</th>
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
            $statement = $pdo->prepare("select * from hmo");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                              <td class="td-dar"><?php echo $row['HMO_ID']; ?></td>
                              <td class="td-dar"><?php echo $row['HMO_PROVIDER']; ?></td>
                              <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
              </tr>

                    <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title"><?php echo $row['HMO_PROVIDER']; ?></h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updatehmo=<?php echo $row[0]; ?>" class="frmhmo<?php echo $row[0]; ?>">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >ID:</label>
                          <input type="text" value="<?php echo $row['HMO_ID']; ?>" readonly="readonly" name="hmoid" id="hmoid" class="form-control" >
                        </div>               

                      </div>
                      <div class="col-lg-6">
                           <div class="form-group">
                          <label >Name:</label>
                          <input type="text" name="hmoname" value="<?php echo $row['HMO_PROVIDER']; ?>" id="hmoname" class="form-control" >
                      </div>
                      </div>

                    </div>
                           <button type="submit" class="btn btn-success btn-block btnhmoa">Update</button>
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