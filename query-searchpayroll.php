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


 $ltype = $_GET['dte']; 
 $rd="Restday";
 $filter= $_GET['filter'];
$be=1;
$ic=2;

if($filter==0){
   $statement = $pdo->prepare("select * from temp_payroll as a
							inner join employees as b on a.EmpID=b.EmpID 
                     inner join empdetails as c on a.EmpID=c.EmpID
                     where  TypeOff<>:rd and  Pdate=:date 
                     order by b.EmpLN,a.Date asc");
   $statement->bindParam(':date' , $ltype);
   $statement->bindParam(':rd' , $rd);
   $statement->execute(); 
 }
 elseif($filter==1){
   $statement = $pdo->prepare("select * from temp_payroll as a
   inner join employees as b on a.EmpID=b.EmpID 
   inner join empdetails as c on a.EmpID=c.EmpID
   where  TypeOff<>:rd and  Pdate=:date and c.EmpStatID=:stat
   order by b.EmpLN,a.Date asc");
   $statement->bindParam(':date' , $ltype);
   $statement->bindParam(':rd' , $rd);
   $statement->bindParam(':stat' , $be);
   $statement->execute(); 
 }
 else{
   $statement = $pdo->prepare("select * from temp_payroll as a
   inner join employees as b on a.EmpID=b.EmpID 
   inner join empdetails as c on a.EmpID=c.EmpID
   where  TypeOff<>:rd and  Pdate=:date and c.EmpStatID=:stat
   order by b.EmpLN,a.Date asc");
   $statement->bindParam(':date' , $ltype);
   $statement->bindParam(':rd' , $rd);
   $statement->bindParam(':stat' , $ic);
   $statement->execute(); 
 }

?>              
                                    <thead>
                                        <tr>
                                            <th>Employee ID </th>
                                            <th>Employee Name </th>                                      
                                            <th>Logs Type</th>
                                            <th id="adjmin">Adjustment(min)</th>
                                            <th id="adjmoney">Adjustment(money)</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
 
                         <!-- this code is for printing reports -->

<?php

while ($row = $statement->fetch()){
?>
  <tr <?php if($row['TypeOff']=="No Logs"){ ?>  <?php  } ?>  <?php if($row['TypeOff']=="Restday"){ ?>  <?php  } ?> <?php if($row['adjustment']>0){ ?> <?php  } ?>  >                                           
                    <td ><?php echo $row['EmpID']; ?></td>
                    <td ><?php echo $row['EmpLN'] . " " . $row['EmpFN']; ?></td>             
                    <td ><?php echo $row['TypeOff']; ?></td>     
                    <td><?php echo  number_format($row['adjustment'],2)."<br>";?></td>
                    <td><?php echo  number_format($row['adjmin'],2)."<br>";?></td>
                    <td ><?php echo $row['Date']; ?></td>

                  </tr>
<?php

}
?>