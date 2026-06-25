<?php
/* Attempt MySQL server connection. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
include 'w_conn.php';
 
// Check connection
if($con === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
 
if(isset($_REQUEST["term"])){
    // Prepare a select statement
    $sql = "SELECT * FROM employees WHERE EmpID='" .$_REQUEST["term"] . "'";
    
    $result=mysqli_query($con,$sql);
  //  $row=$row = mysqli_fetch_array($result);
       // Check number of rows in the result set
            if(mysqli_num_rows($result) > 0){
                // Fetch result rows as an associative array
                while($row = mysqli_fetch_array($result)){
                       // echo "<a id='" . $row["EmpID"] . "' class='btn' >" . $row["EmpLN"] . " " . $row["EmpFN"] . " " . $row["EmpMN"]; "</a>";  
                    ?>
                      <?php echo $row["EmpFN"] . " " . $row["EmpLN"]; ?>
                
                    <?php   
                }
            } else{
                echo "<a class='btn'>No matches found</a>";
            }
    
  
     
    // Close statement
    mysqli_stmt_close($stmt);
}

// close connection
mysqli_close($con);
?>
