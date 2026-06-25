<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>


<?php
	include_once ("w_conn.php");

  if ($_FILES['file']['name'] != '')
  {
    $filename = basename($_FILES['file']['name']);
 if(isset($_GET['q']))
    {
   $id=$_GET['q'];
   }

   $location = "assets/pdf/". $filename;
   
   $pth=$_FILES['file']['tmp_name'];
   move_uploaded_file($pth,$location);
   mysqli_query($con,"insert into empe201files (EMPID,EmpfileN,EmpProFPath) values ($_GET[q],'$filename','$pth')");  
  }
  else{

  }


/* Location */


?>