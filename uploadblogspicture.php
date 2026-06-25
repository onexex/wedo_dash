<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }

  include 'w_conn.php';
try
   {
      $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }

catch(PDOException $e)
   {
      die("ERROR: Could not connect. " . $e->getMessage());
   }
 

 $filename = $_FILES['file']['name'];
 if(isset($_GET['q']))
  	{
    $id=$_GET['q'];
    }

/* Location */
$result1 = mysqli_query($con, "SELECT MAX(POST_ID) AS Max_Id FROM blog_post");
$rowd = mysqli_fetch_array($result1); 
$cnt1= mysqli_num_rows ($result1);

if($id == '10')
 {
  $pid=$_GET['pid'];
  $maxID= $pid;
 }
else
  {
    $maxID= $rowd['Max_Id'];  
  }

print $id;
$location = "assets/images/blogpost/post_id". $maxID . ".jpg";
$uploadOk = 1;
$imageFileType = pathinfo($location,PATHINFO_EXTENSION);

/* Valid extensions */
$valid_extensions = array("jpg","jpeg","png");

/* Check file extension */
    if(!in_array(strtolower($imageFileType), $valid_extensions)) {
       $uploadOk = 0;
    }

    if($uploadOk == 0){
       echo 0;
    }else{
       /* Upload file */
      move_uploaded_file($_FILES['file']['tmp_name'],$location);
         echo $location;
      
      
       }

?>