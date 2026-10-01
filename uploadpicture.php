<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
      http_response_code(401);
      echo 0;
      exit;
  }
  // only HR may set an employee's photo: "Update 201 Files" (edit profile) or
  // "Enroll Employee" (registration). Employees can't change photos via requests.
  include 'w_conn.php';
  $__ar = mysqli_prepare($con, 'SELECT updte, eemployee FROM accessrights WHERE EmpID = ?');
  mysqli_stmt_bind_param($__ar, 's', $_SESSION['id']);
  mysqli_stmt_execute($__ar);
  $__r = mysqli_fetch_assoc(mysqli_stmt_get_result($__ar));
  if (!$__r || ((string)$__r['updte'] !== '2' && (string)$__r['eemployee'] !== '2')) {
      http_response_code(403);
      echo 0;
      exit;
  }
?>
<?php
$filename = $_FILES['file']['name'];
 if(isset($_GET['q']))
  	{
   $id=$_GET['q'];
   }
// sanitize the employee id used in the filename (no path traversal)
$id = preg_replace('/[^A-Za-z0-9._-]/', '', basename(isset($id) ? $id : ''));
if ($id === '') { echo 0; exit; }

/* Location */
$location = "assets/images/profiles/". $id . ".jpg";
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