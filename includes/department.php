

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                   <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Departments</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Department</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Department</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form  method="post" class="frmdep">
                  
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Department Name:</label>
                          <input type="text" name="depname" id="depname" class="form-control" >
                        </div>
                    </div>
                       <div class="col-lg-6">
                        <div class="form-group">
                          <label >Company:</label>
                          <select class="form-control" name="compid" id="compid">
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

                                  $statement = $pdo->prepare("select * from companies");
                                 }else{
                                    $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");

                                 }
                              $statement->execute(array(':name' => ""));
                              while ($row = $statement->fetch()){
                              ?>
                                 <option value="<?php echo $row['CompanyID']; ?>"><?php echo $row['CompanyDesc']; ?></option> 
                                           
                              <?php
                            }
                              
                        ?>
                          </select>
                        </div>
                    </div>
                      

                    </div>
                           <button type="submit" class="btn btn-success btn-block btndepartment">Submit</button>
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
        <th class="darth col-darth">Department ID</th>
        <th class="darth col-darth">Department Name</th>
        <th class="darth col-darth">Company</th>
        <th class="darth col-darth">Action</th>
      </tr>
    </thead>

    <tbody  class="maintbody" id="maintbody">
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
               if ($_SESSION["UserType"]==1){
                    $statement = $pdo->prepare("select * from departments inner join companies on departments.CompID=companies.CompanyID");
               }
               else{
                 $statement = $pdo->prepare("select * from departments left join companies on departments.CompID=companies.CompanyID where companies.CompanyID='$_SESSION[CompID]'");
               }
           
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['DepartmentID']; ?></td>
                                <td class="td-dar"><?php echo $row['DepartmentDesc']; ?></td>
                                <td class="td-dar"><?php echo $row['CompanyDesc']; ?></td>
                                           <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
     <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Department</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form action="?updtdep=<?php echo $row[0];  ?>" method="post" class="frmdep<?php echo $row[0];  ?>">
                  
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Department Name:</label>
                          <input type="text" name="depname" id="depname" value="<?php echo $row['DepartmentDesc']; ?>" class="form-control" >
                        </div>
                    </div>
                       <div class="col-lg-6">
                        <div class="form-group">
                          <label >Company:</label>
                          <select class="form-control" name="compid" id="compid">
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

                                  $st = $pdo->prepare("select * from companies");
                                 }else{
                                    $st = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");

                                 }
                              $st->execute(array(':name' => ""));
                              while ($row1 = $st->fetch()){
                              ?>
                                 <option value="<?php echo $row1['CompanyID']; ?>"><?php echo $row1['CompanyDesc']; ?></option> 
                                           
                              <?php
                            }
                              
                        ?>
                          </select>
                        </div>
                    </div>
                      

                    </div>
                           <button type="submit" class="btn btn-success btn-block btnupdatedep">Update</button>
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