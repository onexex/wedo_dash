

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Holiday Logger</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Holiday</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Holiday Logger</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmholiday">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Date:</label>
                          <input type="date" name="hldate" id="hldate" class="form-control" >
                        </div>  

                  </div>
                </div>
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Desciption:</label>
                          <input type="text" name="desc" id="desc" class="form-control" >
                        </div>
                    </div>

                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Type:</label>
                          <select class="form-control" name="hldtype" id="hldtype">
                            <option value="1">Regular Holiday</option>
                            <option value="2">Special Holiday</option>
                          </select>
                      </div>
                      </div>

                    </div>
                           <button type="button" required="required" class="btn btn-success btn-block btnholiday">Submit</button>
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
        <th class="darth col-darth">Date</th>
        <th class="darth  col-darth">Type</th>
        <th class="darth  col-darth">Description</th>
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
            $statement = $pdo->prepare("select * from holidays order by sid desc");
            // $statement->execute(array(':name' => ""));
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>     
                  <td class="td-dar"><?php 
                  echo date("F j, Y", strtotime($row['Hdate']));
                  ?></td>
                  <td class="td-dar"><?php 
                  if ($row['Htype']==1){
                    echo "Regular Holiday";
                  } else{
                    echo "Special Holiday";
                  }

                  ?></td>
                  <td class="td-dar"><?php echo $row['Hdescription']; ?></td>
                  <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                </tr>
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Holiday Logger</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updateagency=<?php echo $row[0];  ?>"  class="frmagency<?php echo $row[0];  ?>">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >No.:</label>
                          <input type="text" name="agencyno" value="<?php echo $row['SID']; ?>" id="holidayID" class="form-control" >
                        </div>  

                      </div>
                  </div>
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Holiday Desc:</label>
                          <input type="text" name="agencyname" value="<?php echo $row['Hdescription']; ?>" id="agencyname" class="form-control" >
                        </div>
                    </div>

                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Status:</label>
                          <select class="form-control" name="agencystatus" id="agencystatus">
                            <?php 
                                if  ($row['IsActive']=="1"){
                             ?>
                               <option value="1">Active</option>
                            <option value="2">Not Active</option>
                             <?php
                                }else{
                             ?>
                            <option value="2">Not Active</option>
                               <option value="1">Active</option>
                             <?php
                                } ?></td>
                          
                          </select>
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
