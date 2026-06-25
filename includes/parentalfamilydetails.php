
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

?>



<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Parental Family Details</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Parental Family Details</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Parental Family Details</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmparentalfam">
                 
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Name of Family:</label>
                          <input type="text" name="nameoffamily" id="nameoffamily" class="form-control" >
                        </div>
                    </div>

                      <div class="col-lg-6">
                          <div class="form-group">
                          <label >Employee Name:</label>
                          <select class="form-control" name="empname" id="empname">
                                <?php
                                        $statement = $pdo->prepare("select * from employees where EmpStatusID=1 order by EmpLN");
                                        $statement->execute();
                                        while ($row = $statement->fetch()){
                                ?>
                                        <option value="<?php echo $row['EmpID']; ?>"><?php echo $row['EmpFN'] . " " . $row['EmpLN']; ?></option>
                                <?php
                                         } 
                                ?>


                          </select>
                      </div>
                      </div>

                    </div>
                    <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Date of Birth:</label>
                          <input type="date" name="dob" id="dob" class="form-control" >
                        </div>
                      </div>
                    </div>

                           <button type="button" required="required" class="btn btn-success btn-block btnsavepfdet">Submit</button>
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
        <th class="darth col-darth">Name </th>
        <th class="darth  col-darth">Employee Name</th>
        <th class="darth  col-darth">Birthday</th>
        <th class="darth  col-darth">Action</th>
      </tr>
    </thead>

    <tbody class="maintbody" id="maintbody">
         <?php
            
            $statement = $pdo->prepare("select * from parentalrel inner join employees on parentalrel.EmpID=employees.EmpID order by EmpLN");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                                <td class="td-dar"><?php echo $row['Name']; ?> </td>
                                <td class="td-dar"><?php echo $row['EmpFN'] . " " . $row['EmpLN']; ?> </td>
                                <td class="td-dar"><?php echo $row['DateofBirth']; ?> </td>
                                <td class="td-dar"><button type="button"  title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button>
                                <button type="button" id="<?php echo $row[0];  ?>" class="btn btn-danger deleteparrel"><i class="fa fa-ban" aria-hidden="true"></i></button>
                                </td> 
                              </tr>
                              
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">Parental Update</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updateagency=<?php echo $row[0];  ?>"  class="frmagency<?php echo $row[0];  ?>">
                  <div class="row">
                      <div class="col-lg-6">
                        <div class="form-group">
                          <label >Name of Family:</label>
                          <input type="text" id="nameoffamilyparental" name="nameoffamilyparental" value="<?php echo $row['Name']; ?>"   class="form-control" >
                        </div>  

                    </div>
                    
                    <div class="col-lg-6">
                        <div class="form-group">
                          <label >Birthday:</label>
                          <input type="date" id="birthdayparental" name="birthdayparental" value="<?php echo $row['DateofBirth']; ?>" id="agencyname" class="form-control" >
                        </div>
                    </div>
                </div>
                 
                <div class="row">
                   <div class="col-lg-12">
                        <div class="form-group">
                            <label >Employee Name:</label>
                            <select class="form-control" name="empnameparental" id="empnameparental">
                                <option value="<?php echo $row['EmpID']; ?>"><?php echo $row['EmpFN'] . " " . $row['EmpLN']; ?></option>
                                
                                
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