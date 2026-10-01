<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  // must stop here — without exit the delete/insert below still ran for logged-out requests
  if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
      http_response_code(401);
      echo "Your session has expired. Please sign in again.";
      exit;
  }
?>
<?php
include 'w_conn.php';
// $conn = new mysqli($servername, $username, $password, $db);
// $name=$_POST['name'];

// $sql="INSERT INTO `test` (`nm`) VALUES ('$name')";
// if ($conn->query($sql) === TRUE) {
//     echo "data inserted";
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }

// Direct edits need "Update 201 Files"; everyone else goes through a change
// request (query/Query-requestProfileChange.php) that HR approves.
$__ar = $pdo->prepare('SELECT updte FROM accessrights WHERE EmpID = ?');
$__ar->execute([$_SESSION['id']]);
if ((string)$__ar->fetchColumn() !== '2') {
    http_response_code(403);
    echo "You don't have permission to update employee records directly.";
    exit;
}
$nameArr = json_decode($_POST["name"]);
$addArr = json_decode($_POST["a"]);
$rellArr = json_decode($_POST["rel"]);
$connoArr = json_decode($_POST["conno"]);
$iceArr = json_decode($_POST["ice"]);


// $con=mysqli_connect("localhost","root","","wedomain");

// if (mysqli_connect_errno())
// {
// echo "Failed to connect to MySQL: " . mysqli_connect_error();
// }
$id=$_POST['empID'] ;
$sql="DELETE FROM fdetails WHERE FDetID='$id'";
$stmt = $pdo->prepare($sql);
$stmt->execute(); 
for ($i = 0; $i < count($nameArr); $i++) {
if(($nameArr[$i] != "")){ /*not allowing empty values and the row which has been removed.*/



$sql="INSERT INTO fdetails (FDetID,FName,FAdd,FRel,FContact,FICE)
VALUES (:id, :name , :add , :relation , :contact , :ice)";

   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':id', $id);
   $stmt->bindParam(':name', $nameArr[$i]);
   $stmt->bindParam(':add', $addArr[$i]);
   $stmt->bindParam(':relation',$rellArr[$i]);
   $stmt->bindParam(':contact', $connoArr[$i]);
   $stmt->bindParam(':ice', $iceArr[$i]);
   $stmt->execute(); 



//('$nameArr[$i]','$addArr[$i]','$rellArr[$i]','$connoArr[$i]','$iceArr[$i]')";

// if (!mysqli_query($con,$sql))
// {
// die('Error: ' . mysqli_error($con));
// }
// }
// }
// Print "Data added Successfully !";
// mysqli_close($con);s
}
}
?>
