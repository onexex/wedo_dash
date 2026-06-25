<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* Attempt MySQL server connection. Assuming you are running MySQL

server with default setting (user 'root' with no password) */

include 'w_conn.php';

 

// Check connection

if($con === false){

    die("ERROR: Could not connect. " . mysqli_connect_error());

}

  date_default_timezone_set("Asia/Manila");

  $param_term = $_REQUEST["term"];

if(isset($_REQUEST["term"])){

    // Prepare a select statement

      if ($_SESSION['UserType']==1){

        $sql = "SELECT * FROM employees INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID WHERE EmpLN='" .  $param_term . "' OR  empdetails.EmpID='" .  $param_term . "' ORDER BY EmpLN LIMIT 15 ";

    }else{

        $sql = "SELECT * FROM employees INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID WHERE (EmpLN='" .  $param_term . "' OR  empdetails.EmpID='" .  $param_term . "') AND EmpCompID='" . $_SESSION['CompID'] . "' AND employees.EmpID<>'" . $_SESSION['id'] ."'  ORDER BY EmpLN LIMIT 15 ";

    }

    

        $result=mysqli_query($con,$sql);

        // Bind variables to the prepared statement as parameters

        // mysqli_stmt_bind_param($stmt, "s", $param_term);

        

        // Set parameters

        // $param_term = $_REQUEST["term"] . '%';

        

        // Attempt to execute the prepared statement

            

            // Check number of rows in the result set

            if(mysqli_num_rows($result) > 0){

                // Fetch result rows as an associative array

                while($row = mysqli_fetch_array($result)){

                       // echo "<a id='" . $row["EmpID"] . "' class='btn' >" . $row["EmpLN"] . " " . $row["EmpFN"] . " " . $row["EmpMN"]; "</a>"; 

                         $sql2 = "SELECT * FROM empprofiles WHERE EmpID='$row[EmpID]'";

                            $empq=mysqli_query($con, $sql2);

                            $rw2=mysqli_fetch_array($empq); 

                             $prfpath="background-image: url('" . $rw2['EmpPPath'] ."')";

                    ?>

                      <a href="#" id="<?php echo $row["EmpID"]; ?>" class="emp-ms row">

                

                    <div class="img-s col-lg-4"> 

                            <div style="<?php if (file_exists($prfpath)){  

                                        echo $prfpath; 

                                      }else{  

                                                                                if ($rw2['EmpGender']=="Male"){

                                                    echo "background-image: url('assets/images/profiles/man_d.jpg')";

                                            }else{

                                                    echo "background-image: url('assets/images/profiles/woman_d.jpg')";

                                            }

                                      } 

                                        ?>"class="img-s">

                          

                        </div>

                    </div>

                    <div class="s-name col-lg-8">

                      <p><?php echo $row["EmpFN"] . " " . $row["EmpLN"]; ?></p>

                  

                    </div>

                  

                  </a>

                    <?php   

                }

            } else{

                echo "<a class='btn'>No matches found</a>";

            }


    }

     

    // Close statement






// close connection


?>

