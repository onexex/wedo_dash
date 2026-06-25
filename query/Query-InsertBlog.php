
<?php 

  include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
   
    date_default_timezone_set("Asia/Manila");


$result1 = mysqli_query($con, "SELECT MAX(POST_ID) AS Max_Id FROM blog_post");
$rowd = mysqli_fetch_array($result1); 
$cnt1= mysqli_num_rows ($result1);

$maxID= $rowd['Max_Id'] + 1;

    $today = date("Y-m-d H:i:s"); 
    $path="assets/images/blogpost/post_id" . $maxID . ".jpg"; 
    $path2="dashboard.wedoinc.ph/assets/images/blogpost/post_id" . $maxID . ".jpg"; 
     $id=$_SESSION['id'];
    $purl="url here";
// insert into dars
$sql = "INSERT INTO blog_post (Post_Category,Post_Title,Post_Publish_Date,Post_Content,Post_image,Author_ID,Post_URL,Post_Stat,Post_IsEdit) VALUES
                             (:pcat, :ptitle, :ppub, :pcont, :pimageurl, :paid, :purl, :pstat, :pedit)";

   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':pcat', $_POST['category1']);
   $stmt->bindParam(':ptitle', $_POST['titlepost']);
   $stmt->bindParam(':ppub', $today);
   $stmt->bindParam(':pcont', $_POST['Content1']);
   //$stmt->bindParam(':pimageurl', $_POST['Imageurl']);
   $stmt->bindParam(':pimageurl', $path);
   $stmt->bindParam(':paid', $id);
   $stmt->bindParam(':purl',  $path2);
   $stmt->bindParam(':pstat', $_POST['status']);
   $stmt->bindParam(':pedit', $_POST['edit']);

   

if ($stmt->execute()) { 
  print 1;
} else {
   print 2;
}

 ?>