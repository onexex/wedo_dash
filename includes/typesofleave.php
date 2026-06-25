
<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                                  <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Types of Leave</h4>
          		 <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Types of Leave</button>
          		    <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog" style="max-width: 500px;">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Types of Leave</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form  method="post" class="frmtleave">
                  
                  <div class="row">
                  
                      <div class="col-lg-12">
                        <div class="form-group">
                          <label class="wrn-l">Leave Name:</label>
                          <input type="text" name="lname" id="lname" class="form-control" >
                        </div>
                    </div>

                    </div>
                           <button type="button" class="btn btn-success btn-block btnleavet">Save</button>
                          </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                  <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                </div>

              </div>
            </div>
          </div>
          <br>
           <table class="table table-bordered">

            <thead>
              <tr>
                <th class="darth col-darth">Leave ID</th>
                <th class="darth col-darth">Leave Type</th>
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
                          $statement = $pdo->prepare("select * from  leaves");
                          $statement->execute();
                           while ($row = $statement->fetch()){
            ?>
                        <tr>
                          <td class="td-dar"><?php echo $row[0]; ?></td>
                          <td class="td-dar"><?php echo $row[1]; ?></td>
                           <td><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                        </tr>

                                                        <!-- The Modal -->
  <div class="modal" id="myModal<?php echo $row[0];  ?>">
    <div class="modal-dialog">
      <div class="modal-content">
      
        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title"></h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <!-- Modal body -->
        <div class="modal-body">
          <form action="?updattol=<?php echo $row[0]; ?>" method="post" class="frmup" enctype="multipart/form-data">
             <div class="col-lg-12">
                        <div class="form-group">
                          <label class="wrn-l">Leave Name:</label>
                          <input type="text" name="lname" id="lname" value="<?php echo $row[1]; ?>" class="form-control" >
                        </div>
                    </div>
        
              <button type="submit" class="btn btn-success btn-block btnleaveupt">Update</button>
          
          </form>
        </div>
        
        <!-- Modal footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
        </div>
        
      </div>
    </div>
  </div>
  <!-- end of modal -->
                <?php
                        }
               ?>

            </tbody>
          </table>


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