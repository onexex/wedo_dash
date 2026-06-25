<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
 if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
 else{ header ('location: login.php'); }
?>
<?php
   include 'w_conn.php';
?>


<!DOCTYPE html>
<html>
<head>
	<title>New Blog</title>
	<meta charset="utf-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1">
  	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
  	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
  	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
  	<script type="text/javascript" src="assets/js/scriptblogs.js"></script>
  	 
  <style>
    html body{
		font-family: Tahoma !important;
	}
  .post_content_st{
      white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 750px;
    margin: 0px;
  }
    .error{
        outline: 1px solid red;
    }
    .w-navbar{
    	background-color: #ff0000;
    top: 0;
    position: fixed;
    width: 100%;
    padding: 5px 20px;
    }  
 .w-navbar .lg-dv {
    display: inline-block;
}
.w-navbar .lg-dv img {
    height: 42px;
}

img {
    vertical-align: middle;
}
img {
    border: 0;
}
.title-p{
    display: inline;
    color: white;
    padding: 15px;
    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";
}
</style>
</head>
<body>
	<br>
	<div class="w-navbar" style="background-color: #ff0000 ">
	    
	   <div class="lg-dv">
       <img src="assets/images/logos/WeDoInc-01.png">
       
      
    </div>
     <h4 class="title-p">Blog Panel</h4>
	</div>	
	
	<br>	
	<br>	
	<div class="container">
		
	<!-- Button to Open the Modal -->
	<button type="button" class="btn btn-success" data-toggle="modal" data-target="#myModal">
	  Add New Blog
	</button>

	<!-- The Modal -->
	<div class="modal" id="myModal">
	  <div class="modal-dialog">
	    <div class="modal-content">

	      <!-- Modal Header -->
	      <div class="modal-header">
	        <h4 class="modal-title">Adding New Blog</h4>
	        <button type="button" class="close" data-dismiss="modal">&times;</button>
	      </div>

	      <!-- Modal body -->
	      <div class="modal-body">
	        <form method="post" enctype="multipart/form-data" id="newblog">
			  	<div class="form-group">
				    <label for="text">Title:</label>
				    <input type="text" class="form-control" placeholder="Post Title" name="titlepost" id="titlepost" required>
			  	</div>
			    <label for="text">URL:</label>

				<div class="form-group">
					<label for="comment">Content:</label>
				  	<textarea class="form-control" rows="7" id="comment" name="Content1" required></textarea>
				</div>

				<div class="form-group">
					<label for="category">Category:</label>
				  	<select class="form-control" id="category" name="category1">
					  <option value="0">-</option>
					   <?php
                            $sql=mysqli_query($con, "select * from blog_category");
                                while($res=mysqli_fetch_array($sql)){
                            ?>
                                <option  value="<?php echo $res['cat_id']; ?>"><?php echo $res['cat_desc']; ?>  </option>
                            <?php   
                                }
                                ?>
					</select>
				</div>
					

				<div class="form-group">
				    <label for="text">Image Post:</label>
				    <input type="file" class="form-control" placeholder="Post Title" name="Imageurl" id="file"  accept="image/*">
			  	</div>	  
				

				<div class="form-group">
					<label for="comment">Status:</label>
				  	<select class="form-control" id="status" name="status">
					   <option value="0">-</option>
					   <?php
                            $sql=mysqli_query($con, "select * from blog_status");
                                while($res=mysqli_fetch_array($sql)){
                            ?>
                                <option  value="<?php echo $res['SId']; ?>"><?php echo $res['Description']; ?>  </option>
                            <?php   
                                }
                                ?>
					  
					</select>
				</div>

				<div class="form-group">
					<label for="comment">Edit:</label>
				  	<select class="form-control" id="edit" name="edit">
					  <option value="0">-</option>
					   <?php
                            $sql=mysqli_query($con, "select * from blog_edit");
                                while($res=mysqli_fetch_array($sql)){
                            ?>
                                <option  value="<?php echo $res['IsID']; ?>"><?php echo $res['IsDes']; ?>  </option>
                            <?php   
                                }
                                ?>

					</select>
				</div>
				<button type="button" class="btn btn-primary" id="bsave">Save</button>  


			</form>
			<br>
            		 <div class="alert alert-success" id="messageshow" style="display: none;">
    				<strong>Success!</strong> Blog Successfully saved!
    				<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
    			<!-- 	//<span class="closebtn" onclick="this.parentElement.style.display='none';">&times; -->
  					</div>
	      </div>

	      <!-- Modal footer -->	
	      <div class="modal-footer">
	        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
	      </div>

	    </div>
	  </div>
	</div>

	<!-- table -->
	<h2>List of Blogs </h2>
	<br>
	 <div class="container" >
	 	<label> Filter Date:</label>
		 	<div class="form-group" >
			 		From:
			 		<input type="date" id="datefrom" name="dfrom" value="<?php echo date('Y-m-d', strtotime('-7 days'));?>">
			 		To:
			 		<input type="date" id="dateto" name="dtto" value="<?php echo date('Y-m-d');?>">
			 		<button type="button" class=" fa fa-repeat btn btn-success " name="reloaddata" id="reloaddata"> </button>  
		 	</div>
	 	
	 </div>        
	<table class="table table-striped" >
	    <thead>
	      <tr>
	        <th>Title</th>
	        <th>Content</th>
	        <th>Category</th>
	        <th>Action</th>
	      </tr>
	    </thead>
	   <tbody id="blogdata"> 
      <?php
try{
include 'w_conn.php';	
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
//$id=$_SESSION['id'];
$statement = $pdo->prepare("SELECT * FROM blog_post 
	                        INNER JOIN blog_category ON blog_post.Post_Category=blog_category.cat_id 
	                        INNER JOIN blog_status ON blog_post.Post_Stat=blog_status.SId 
	                        INNER JOIN blog_edit ON blog_post.Post_IsEdit=blog_edit.IsID ORDER BY Post_ID desc");
$statement->execute();

while ($row = $statement->fetch()){
?>
  <tr>
           
      <td><?php echo $row['Post_Title']; ?></td>
      <td ><p class="post_content_st"><?php echo $row['Post_Content']; ?></p></td>
      <td ><?php echo $row['cat_desc']; ?></td>
      <td >

<a class="btn btn-danger btnupdate" data-toggle="modal" data-target="#Updatethis<?php echo $row['Post_ID'];  ?>" style="font-size:10px;color: #fff;"><i class="fa fa-edit" aria-hidden="true" style="font-size:10px" ></i></a>
<a class="btn btn-success" target="_blank" href="http://wedo101millennials.wedoinc.ph/singlepost.php?id=<?php echo $row['Post_ID'];  ?>" style="font-size:10px;color: #fff;"><i class="fa fa-eye" aria-hidden="true" style="font-size:10px" ></i></a>
      </td>
  </tr>

    <!-- The Modal -->
    <div class="modal" id="Updatethis<?php echo $row['Post_ID'];  ?>">
      <div class="modal-dialog">
        <div class="modal-content">
    
          <!-- Modal Header -->
          <div class="modal-header">
            <h4 class="modal-title">Updating <?php echo $row['Post_Title']; ?></h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
    
          <!-- Form Content Update -->
          <div class="modal-body">
                    <form method="post" enctype="multipart/form-data" id="updateblog1">
			  	<div class="form-group">
				    <label for="text">Title:</label>
				    <input type="text" class="form-control" placeholder="Post Title" name="uptitlepost" value="<?php echo $row['Post_Title']; ?>" id="uptitlepost" required>
			  	</div>
			    <label for="text" text="<?php echo $row['Post_ID']; ?>" id="postID">Post ID: <?php echo $row['Post_ID']; ?></label>

				<div class="form-group">
					<label for="comment">Content:</label>
				  	<textarea class="form-control" rows="7" id="upcomment" name="Contentup" required><?php echo $row['Post_Content']; ?></textarea>
				</div>

				<div class="form-group">
					<label for="category">Category:</label>
				  	<select class="form-control" id="upcategory" name="categoryup">
					<option value="<?php echo $row['cat_id']; ?>"><?php echo $row['cat_desc']; ?></option>
					   <?php
                            $sql=mysqli_query($con, "select * from blog_category");
                                while($res=mysqli_fetch_array($sql)){
                                	if($row['cat_desc']==$res['cat_desc'])
                                	{

                                	}
                                	else{
                            ?>
                                <option  value="<?php echo $res['cat_id']; ?>"><?php echo $res['cat_desc']; ?>  </option>
                            <?php   }
                                }
                                ?>
                                	  
					</select>
				</div>
					

				<div class="form-group">
				    <label for="text">Image Post:</label>
				    <img class="img-fluid" src="<?php echo $row['Post_image']; ?>">
				    <input type="file" class="form-control" placeholder="Post Title" name="upImageurl" id="upfile"  accept="image/*">
			  	</div>	  
				

				<div class="form-group">
					<label for="comment">Status:</label>
				  	<select class="form-control" id="upstatus" name="statusup">
					<option  value="<?php echo $row['SId']; ?>"><?php echo $row['Description']; ?>  </option>
					  <?php
                            $sql=mysqli_query($con, "select * from blog_status");
                                while($res=mysqli_fetch_array($sql)){
                                	if($row['Description']==$res['Description'])
                                	{


                                	}
                                	else
                                	{
                            ?>
                                <option  value="<?php echo $res['SId']; ?>"><?php echo $res['Description']; ?>  </option>
                            <?php  } 
                                }
                                ?>

					  
					</select>
				</div>

				<div class="form-group">
					<label for="comment">Edit:</label>
				  	<select class="form-control" id="upedit" name="editup">
					  <option  value="<?php echo $row['IsID']; ?>"><?php echo $row['IsDes']; ?>  </option>
					  <?php
                            $sql=mysqli_query($con, "select * from blog_edit");
                                while($res=mysqli_fetch_array($sql)){
                                	if($row['IsDes']==$res['IsDes'])
                                	{

                                	}
                                	else
                                	{
                            ?>
                                <option  value="<?php echo $res['IsID']; ?>"><?php echo $res['IsDes']; ?>  </option>
                            <?php  } 
                                }
                                ?>
					</select>
				</div>
				<button type="button" class="btn btn-primary updatingblog_save" id="updateblog_save" >Update</button>  


			</form>
          </div>
    
          <!-- Modal footer -->
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
          </div>
    
        </div>
      </div>
    </div>
<?php

}
?>
	</table>

	</div>
</body>
</html>