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
   	$EmpD=$_REQUEST['data'];
	$sql = "SELECT * FROM employees  WHERE EmpID=:id Order by EmpLN LIMIT 15 ";
	 $stmt = $pdo->prepare($sql);
	 $stmt->bindParam(':id', $EmpD);
	 $stmt->execute();
	 $row=$stmt->fetch();

	   //$pass=ucfirst($row['EmpLN']);
	   //$epass=password_hash($pass, PASSWORD_DEFAULT);
	   
	   $pass="wedoinc2023";
	   $epass=password_hash($pass, PASSWORD_DEFAULT);
	   
		$sql = "UPDATE empdetails SET EmpPW='$epass'  WHERE EmpID='$EmpD'";
   		$stmt = $pdo->prepare($sql);
   		$stmt->execute(); 
 