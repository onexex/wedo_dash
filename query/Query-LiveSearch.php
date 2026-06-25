<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
/* Attempt MySQL server connection. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
else{ header ('location: login.php'); }
include 'w_conn.php';
 
// Check connection
if($con === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

    $param_term=$_GET['term'];
    if ($_SESSION['UserType']==1){
    $sql = "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where EmpLN like '%" .  $param_term . "%' or  empdetails.EmpID like '" .  $param_term . "' order by EmpLN limit 15 ";
    }else{
        $sql = "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where (EmpLN like '" .  $param_term . "' or  empdetails.EmpID like '" .  $param_term . "') and EmpCompID='" . $_SESSION['CompID'] . "' and employees.EmpID<>'" . $_SESSION['id'] ."'  order by EmpLN limit 15 ";
    }
    $res=mysqli_query($con, $sql);
     // Check number of rows in the result set
            if(mysqli_num_rows($res) > 0){
                // Fetch result rows as an associative array
                while($row = mysqli_fetch_array($res)){
                 echo "<a id='" . $row["EmpID"] . "' class='btn' >" . $row["EmpLN"] . " " . $row["EmpFN"] . " " . $row["EmpMN"];
                }
            } else{
                echo "<a class='btn'>No matches found</a>";
            }


//     if($stmt = mysqli_prepare($con, $sql)){
//         // // Bind variables to the prepared statement as parameters
//         // mysqli_stmt_bind_param($stmt, "s", $param_term);
        
//         // // Set parameters
//         // $param_term = $_REQUEST["term"] . '%';
        
//         // Attempt to execute the prepared statement
//         if(mysqli_stmt_execute($stmt)){
//             $result = mysqli_stmt_get_result($stmt);
            
//             // Check number of rows in the result set
//             if(mysqli_num_rows($result) > 0){
//                 // Fetch result rows as an associative array
//                 while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
//                  echo "<a id='" . $row["EmpID"] . "' class='btn' >" . $row["EmpLN"] . " " . $row["EmpFN"] . " " . $row["EmpMN"];
//                 }
//             } else{
//                 echo "<a class='btn'>No matches found</a>";
//             }
//         } else{
//             echo "ERROR: Could not able to execute $sql. " . mysqli_error($con);
//         }
//     }
     
//     // Close statement
//     mysqli_stmt_close($stmt);
 

// // close connection
// mysqli_close($con);
?>
