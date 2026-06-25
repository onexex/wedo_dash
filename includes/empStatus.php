

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                   <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Employee Status</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Employee Status</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Employee Status</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" class="frmstat">
                
                  <div class="row">
                  

                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Employee Status:</label>
                          <input type="text" name="empstat" id="empstat" class="form-control" >
                      </div>
                      </div>

                    </div>
                           <button type="submit" class="btn btn-success btn-block btnestat">Submit</button>
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
        <th class="darth col-darth">Employee Status No.</th>
        <th class="darth  col-darth">Employee Status</th>
        <th class="darth  col-darth">Action</th>
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
            $statement = $pdo->prepare("select * from estatus");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['ID']; ?></td>
                                <td class="td-dar"><?php echo $row['StatusEmpDesc']; ?></td>
                                <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>

                                <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title"><?php echo $row['StatusEmpDesc']; ?></h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updateempstat=<?php echo $row[0];  ?>" class="frmstast">
                
                  <div class="row">
                     
                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Employee Status:</label>
                          <input type="text" name="empstat" value="<?php echo $row['StatusEmpDesc']; ?>" id="empstat" class="form-control" >
                      </div>
                      </div>

                    </div>
                           <button type="submit" class="btn btn-success btn-block btnestats">Submit</button>
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