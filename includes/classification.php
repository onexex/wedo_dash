

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                   <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Classifications</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Classification</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Classification</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmclassifi">
              
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Classification Code:</label>
                          <input type="text" name="classcode" id="classcode" class="form-control" >
                        </div>
                    </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Classification Description:</label>
                          <input type="text" name="classdesc" id="classdesc" class="form-control" >
                        </div>
                    </div>
                 

                    </div>
                           <button type="button" required="required" class="btn btn-success btn-block btnclassifi">Submit</button>
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
        <th class="darth col-darth">Classification Code</th>
        <th class="darth col-darth">Classification Description</th>
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
            $statement = $pdo->prepare("select * from empstatus ");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row[0]; ?></td>
                                <td class="td-dar"><?php echo $row[1]; ?></td>
                                <td class="td-dar"><?php echo $row[2]; ?></td>
                               <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                               
                              </tr>


                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Classification</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updateclass=<?php echo $row[0]; ?>"  class="frmclassifis">
              
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Classification Code:</label>
                          <input type="text" name="classcode" value="<?php echo $row[1]; ?>" id="classcode" class="form-control" >
                        </div>
                    </div>
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Classification Description:</label>
                          <input type="text" name="classdesc" value="<?php echo $row[2]; ?>" id="classdesc" class="form-control" >
                        </div>
                    </div>
                 

                    </div>
                           <button type="Submit" required="required" class="btn btn-success btn-block btnclassifis">Update</button>
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