   <?php 
include 'w_conn.php';
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
 $postID=$_GET['pid'];
 $title=$_POST['uptitlepost'];	
 $content=$_POST['Contentup'];
 $cat=$_POST['categoryup'];
 //$cat=$_POST['upcategory'];
 $stat=$_POST['statusup'];
 $edit=$_POST['editup'];
 $path="assets/images/blogpost/post_id" . $postID . ".jpg"; 
 $path2="dashboard.wedoinc.ph/assets/images/blogpost/post_id" . $postID . ".jpg";  
print $edit;
    $sql = "UPDATE blog_post SET 	Post_Category=:cat,
    								Post_Title=:title,
    								Post_Content=:con,
    								Post_Stat=:stat,
    								Post_IsEdit=:edit,
    								Post_image=:path1,
    								Post_URL=:path2
    								WHERE Post_ID=:pid";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':pid', $postID);
   $stmt->bindParam(':title', $title);
   $stmt->bindParam(':con', $content);
   $stmt->bindParam(':cat', $cat);
   $stmt->bindParam(':stat',$stat);
   $stmt->bindParam(':edit', $edit);
   $stmt->bindParam(':path1',$path);
   $stmt->bindParam(':path2',$path2);
   $stmt->execute(); 


?>