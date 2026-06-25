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
                                $statement = $pdo->prepare("select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID inner join companies on  empdetails.EmpCompID=companies.CompanyID inner join departments on empdetails.EmpDepID=departments.DepartmentID inner join positions on employees.PosID=positions.PSID where employees.EmpID='" . $_GET['id'] . "'");
                                $statement->execute();
                               $row2 = $statement->fetch();
                              ?>
                                 <div class="form-group">
                          <label >Company Name:</label>
                          <input type="text" disabled class="form-control" value="<?php echo $row2['CompanyDesc']; ?>" id="empcomp">
                        </div>
                         <div class="form-group">
                          <label >Department:</label>
                          <input type="text" disabled class="form-control" value="<?php echo $row2['DepartmentDesc']; ?>" id="empdep">
                        </div>
                         <div class="form-group">
                          <label >Designation:</label>
                          <input type="text" disabled class="form-control" value="<?php echo $row2['PositionDesc']; ?>" id="empdesig">
                        </div>
                                
     