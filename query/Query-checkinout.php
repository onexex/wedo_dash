<?php 
  include 'w_conn.php';
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
   
  $id=$_SESSION['id'];

 $has_log=0;

//check if has login
// $if_have_login= $pdo->prepare("Select * from attendancelog where EmpID='$id' order by LogID DESC");
// $if_have_login->execute();
// $if_have=$if_have_login->fetch();
// $numberofrows=$if_have->num_rows;

$result = mysqli_query($con, "Select * from attendancelog where EmpID='$id' order by TimeIn DESC");
$res = mysqli_fetch_array($result); 
$cnt= mysqli_num_rows ($result);


if ($cnt==0)
  {
    $has_log=1;
  }

else
  {
      if(($cnt>0) && ($res['TimeOut']==NULL))
        {
          $has_log=2;
        }
      else
        {
          $has_log=1;
        }
  }    
      //new code  
       if ($has_log==1)
            { 
              print 1;
            }
        elseif ($has_log==2) 
            {
              print 2;
            }
 ?>