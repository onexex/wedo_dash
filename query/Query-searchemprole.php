<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
include 'w_conn.php';
?>


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



// Prefix search on last name (the live-search box labelled "Search Employee
// Lastname"). Was a broken exact match (EmpLN = :term) so partial input matched
// nothing — restored to the intended LIKE prefix search.
$term = ($_REQUEST["term"] ?? "") . "%";
$statement = $pdo->prepare("SELECT * FROM employees WHERE EmpLN LIKE :term ORDER BY EmpLN LIMIT 15");
$statement->bindParam(':term', $term);
$statement->execute();

$found = false;
while ($row = $statement->fetch()){
    $found = true;
    echo "<a class='btn btn-block btn-name' id='" . htmlspecialchars($row["EmpID"]) . "'>"
       . htmlspecialchars($row["EmpLN"] . " " . $row["EmpFN"] . " " . $row["EmpMN"]) . "</a>";
}
if (!$found) {
    echo "<a class='btn btn-name'>No matches found</a>";
}

 
// // Check connection
// if($con === false){
//     die("ERROR: Could not connect. " . mysqli_connect_error());
// }
 
// if(isset($_REQUEST["term"])){
//     // Prepare a select statement
//     $sql = "SELECT * FROM Employees WHERE EmpLN LIKE ? Order by EmpLN LIMIT 15 ";
    
    
    
    
//     if($stmt = mysqli_prepare($con, $sql)){
//         // Bind variables to the prepared statement as parameters
//         mysqli_stmt_bind_param($stmt, "s", $param_term);
        
//         // Set parameters
//         $param_term = $_REQUEST["term"] . '%';
        
//         // Attempt to execute the prepared statement
//         if(mysqli_stmt_execute($stmt)){
//             $result = mysqli_stmt_get_result($stmt);
            
//             // Check number of rows in the result set
//             if(mysqli_num_rows($result) > 0){
//                 // Fetch result rows as an associative array
//                 while($row = mysqli_fetch_array($result)){
//                     echo "<a class='btn btn-block btn-name' id='" . $row["EmpID"] . "' class='btn' >" . $row["EmpLN"] . " " . $row["EmpFN"] . " " . $row["EmpMN"]; "</a>";   
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
// }

// close connection
// mysqli_close($con);

	?>
