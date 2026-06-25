

<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
         <!-- website content -->
          <div class="col-lg-9 module-content">
                          <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">SIL LOAN</h4>
             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ SIL LOAN</button>
             <!-- The Modal -->
          <div class="modal" id="newform">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">SIL LOAN</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post"  class="frmsilloan">
                  
                    <div class="row">
                        <div class="col-lg-6">
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
                               $statement = $pdo->prepare("select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where EmpStatusID=1 order by EmpLN");
                             $statement->execute();
                                         ?>

                            <div class="form-group">
                            <label >Employee:</label>
                            <select  class="form-control" name="empid" id="empid">
                              <?php
                                while ($row2 = $statement->fetch()){
                                ?>
                                <option value="<?php echo $row2['EmpID']; ?>"><?php echo $row2['EmpLN'] . ", " . $row2['EmpFN'] ; ?></option>
                                <?php
                                }
                              ?>
                            </select>
                        
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >Loan Amount:</label>
                            <input type="number" name="loanam" id="loanam" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >Loan Type:</label>
                                <select name="ltype" id="ltype" class="form-control">
                                    <option value="SSS">SSS</option>
                                    <option value="PI">PI</option>
                                    <option value="SIL">SIL</option>
                                </select>
                            </div>
                        </div>
                          <div class="col-lg-6">
                            <div class="form-group">
                                <label >Loan Status:</label>
                                <select name="lsta" id="lsta" class="form-control">
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="INACTIVE">INACTIVE</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="button" required="required" class="btn btn-success btn-block btnsilloan">Submit</button>
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
        <th class="darth col-darth">Employee Name</th>
        <th class="darth col-darth">Loan Amount</th>
        <th class="darth col-darth">Loan Type</th>
        <th class="darth col-darth">Loan Status</th>
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
            $statement = $pdo->prepare("select * from silloan inner join employees on silloan.loanEmpID=employees.EmpID order by loanAmount asc");
            $statement->execute();
            while ($row = $statement->fetch()){
            ?>
              <tr>
                                <td class="td-dar"><?php echo $row['EmpFN'] . " " . $row['EmpLN']; ?></td>
                                <td class="td-dar"><?php echo $row['loanAmount']; ?></td>
                                <td class="td-dar"><?php echo $row['LoanType']; ?></td>
                                <td class="td-dar"><?php echo $row['loanStatus']; ?></td>
                               
                                 <td class="td-dar"><button type="button" title="EDIT" class="btn btn-info" data-toggle="modal" data-target="#myModal<?php echo $row[0];  ?>"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>
                              </tr>
                                  <!-- The Modal -->
          <div class="modal" id="myModal<?php echo $row[0];  ?>">
            <div class="modal-dialog">
              <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                  <h4 class="modal-title">SIL LOAN</h4>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                        <form method="post" action="?updatesilloan=<?php echo $row[0];  ?>"  class="updatesilloan<?php echo $row[0];  ?>">
                     
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                            <label >Loan Amount:</label>
                            <input type="number" name="loanam" value="<?php echo $row['loanAmount'];  ?>" id="loanam" class="form-control" >
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label >Loan Type:</label>
                               <select name="lty" id="lty" class="form-control">
                                  <option value="<?php echo $row['LoanType']; ?>"><?php echo $row['LoanType']; ?></option>
                                    <option value="SSS">SSS</option>
                                    <option value="PI">PI</option>
                                    <option value="SIL">SIL</option>
                                </select>
                            </div>
                        </div>
                         <div class="col-lg-6">
                            <div class="form-group">
                            <label >Loan Status:</label>
                             <select name="lstat" id="lstat" class="form-control">
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="INACTIVE">INACTIVE</option>
                                </select>
                            </div>
                        </div>
                    </div>

                           <button type="submit" required="required" class="btn btn-success btn-block">Update</button>
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