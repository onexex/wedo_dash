<?php
	if (session_status() === PHP_SESSION_NONE) { session_start(); }

try{
include 'w_conn.php';	
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }

$statement = $pdo->prepare("select * from employees where EmpID = :id ");
$statement->bindParam(':id' , $_POST['empidn']);
$statement->execute(); 
$rows=$statement->fetch();

if($rows>0){print 1;}
else
{print 0;}


	
?>