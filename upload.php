<?php
$filename = $_FILES['file']['name'];
 if(isset($_GET['q']))
  	{
   $id=$_GET['q'];
   }

/* Location */
$location = "assets/images/". $id . ".jpg";
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
   if(move_uploaded_file($_FILES['file']['tmp_name'],$location)){
     echo $location;
   }else{
     echo 0;
   }
  
   }

?>