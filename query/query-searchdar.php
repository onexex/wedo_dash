<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$q = $_SESSION['id'];
try{

include 'w_conn.php';	

$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
  date_default_timezone_set("Asia/Manila"); 

$dt1=date('Y-m-d', strtotime('-7 days'));
$dt2=date('Y-m-d', strtotime('+1 days'));
$statement = $pdo->prepare("select * from dars where EmpID = :id and DarDateTime between :dt1 and :dt2 order by DarDateTime desc");
$statement->bindParam(':id' , $q);
$statement->bindParam(':dt1' , $dt1);
$statement->bindParam(':dt2' , $dt2);
$statement->execute(); 

while ($row = $statement->fetch()){
?>
  <tr>
                    <td class="td-dar" width="40%"><?php echo date("M j, Y", strtotime($row['DarDateTime'])); ?></td>
                    <td class="td-dar res-day" width="40%"><?php echo date("l", strtotime($row['DarDateTime'])); ?></td>
                    <td class="td-dar" width="40%"><?php echo date("h:i:s A", strtotime($row['DarDateTime'])); ?></td>
             
                    <td class="td-act" width="50%"><?php echo $row['EmpActivity']; ?></td>
                  </tr>
<?php

}
?>

 


