

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">

 

                   <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Companies</h4>
            <?php
                if ($_SESSION['UserType']==1){
          ?>
               <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Company</button>
          <?php
            }
            else{
                $statement = $pdo->prepare("select * from companies");
            }
            ?>
            
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Company</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" class="frmcompany" enctype="multipart/form-data">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Company ID:</label>
                          <input type="text" name="compno" id="compno" class="form-control" >
                        </div>  

                  </div>
                   <div class="col-lg-6">
                        <div class="form-group">
                          <label >Company Navbar Color:</label>
                          <input type="color" name="comcolor" id="comcolor" class="form-control" >
                        </div>
                    </div>
                     
                </div>
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Company Name:</label>
                          <input type="text" name="compname" id="compname" class="form-control" >
                        </div>
                    </div>
                      

                   <div class="col-lg-6">
                        <div class="form-group">
                          <label >Company Logo:</label>
                          <input type="file" name="comlogopath" id="comlogopath" >
                        </div>  

                  </div>
                    </div>
                    <div class="row">
                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Company Code:</label>
                          <input type="text" name="comcode" id="comcode" class="form-control" >
                        </div>
                      </div>
                      <div class="col-lg-6 comcolorlogo">
                        <h6>Company Logo</h6>
                        <div class="comlogopic">
                          
                        </div>
                      </div>
                     
                    </div>
                           <button type="submit" class="btn btn-success btn-block btncompany">Submit</button>
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
        <th class="darth col-darth">Company ID</th>
        <th class="darth col-darth">Company Name</th>
        <th class="darth col-darth">Company Code</th>
        <th class="darth col-darth">Company Color</th>
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
                $statement = $pdo->prepare("select * from companies");
            }
            else if($_SESSION['UserType']==2){
                   $statement = $pdo->prepare("select * from companies where CompanyID='$_SESSION[CompID]'");
            }
            else{
     $statement = $pdo->prepare("select * from companies");
            }
         
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                             
                         
                                <td class="td-dar"><?php echo $row['CompanyID']; ?></td>
                                <td class="td-dar"><?php echo $row['CompanyDesc']; ?></td>
                                <td class="td-dar"><?php echo $row['compcode']; ?></td>
                                <td class="td-dar"><?php echo $row['comcolor']; ?></td>
                                <td class="td-dar"><button type="button" title="VIEW" class="btn btn-warning" data-toggle="modal" data-target="#myview<?php echo $row[0];  ?>"><i class="fa fa-eye" aria-hidden="true"></i></button> <button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                               
                              </tr>

                                                              <!-- The Modal -->
  <div class="modal" id="myview<?php echo $row[0];  ?>">
    <div class="modal-dialog">
      <div class="modal-content">
      
        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title"><?php echo $row['CompanyDesc']; ?></h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <!-- Modal body -->
        <div class="modal-body">
          <form action="?updtcomp" method="post" class="frmup" enctype="multipart/form-data">
            <div class="form-group">
              <label>Company No</label>
              <input type="text" readonly="readonly" class="form-control" name="ucomno" value="<?php echo $row['CompanyID']; ?>">
            </div>
             <div class="form-group">
              <label>Company Name</label>
              <input type="text" class="form-control" readonly="readonly" name="ucomname" value="<?php echo $row['CompanyDesc']; ?>">
            </div>
             <div class="form-group">
              <label>Company Code</label>
              <input type="text" class="form-control" readonly="readonly" name="ucomcode" value="<?php echo $row['compcode']; ?>">
            </div>
           
            <div class="">
              <div class="comlogod"  id="ucomplogodiv" style="<?php echo "background-image: url('" . $row['logopath'] . "')" ?>; background-color: <?php echo $row['comcolor']; ?>"></div>
            </div>
            <br>
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


                                <!-- The Modal -->
  <div class="modal" id="myModal<?php echo $row[0];  ?>">
    <div class="modal-dialog">
      <div class="modal-content">
      
        <!-- Modal Header -->
        <div class="modal-header">
          <h4 class="modal-title">Update <?php echo $row['CompanyDesc']; ?></h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <!-- Modal body -->
        <div class="modal-body">
          <form action="?updtcomp" method="post" class="frmup" enctype="multipart/form-data">
            <div class="form-group">
              <label>Company No</label>
              <input type="text" readonly="readonly" class="form-control" name="ucomno" value="<?php echo $row['CompanyID']; ?>">
            </div>
             <div class="form-group">
              <label>Company Name</label>
              <input type="text" class="form-control" name="ucomname" value="<?php echo $row['CompanyDesc']; ?>">
            </div>
             <div class="form-group">
              <label>Company Code</label>
              <input type="text" class="form-control" name="ucomcode" value="<?php echo $row['compcode']; ?>">
            </div>
            <div class="form-group">
              <label>Company Color</label>
              <input type="color" class="form-control" name="ucomcolor" value="<?php echo $row['comcolor']; ?>">
            </div>
            <div class="form-group">
              <label>Company logo</label>
              <input type="file" onchange="chng(this)" class="form-control" name="ucomplogo" id="ucomplogo">
            </div>
            <div class="">
              <div class="comlogod"  id="ucomplogodiv" style="<?php echo "background-image: url('" . $row['logopath'] . "')" ?>; background-color: <?php echo $row['comcolor']; ?>"></div>
            </div>
            <br>
            <button type="submit" id="<?php echo $row['CompanyID']; ?>" onclick="thisid(this.id)" class="btn btn-info">Update</button>
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
<br>
<br>
<br>
<br>
  
         </div>
    </div>
</div>