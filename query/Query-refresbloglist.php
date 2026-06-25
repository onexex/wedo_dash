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

   if (isset($_GET['srchblog'])){
              

              $dtf=$_POST['dtfrom'];
              $dtto=$_POST['dtto'];
//$id=$_SESSION['id'];
$statement = $pdo->prepare("select * from blog_post INNER JOIN blog_category ON blog_post.Post_Category=blog_category.cat_id 
                          INNER JOIN blog_status ON blog_post.Post_Stat=blog_status.SId 
                          INNER JOIN blog_edit ON blog_post.Post_IsEdit=blog_edit.IsID 
                          WHERE blog_post.Post_Publish_Date BETWEEN :dfr AND :dto ORDER BY Post_ID desc");

                  $statement->bindParam(':dfr' , $dtf);
                  $statement->bindParam(':dto' , $dtto);
                  $statement->execute();

while ($row = $statement->fetch()){
?>
  <tr>
           
      <td ><?php echo $row['Post_Title']; ?></td>
      <td ><?php echo $row['Post_Content']; ?></td>
      <td ><?php echo $row['cat_desc']; ?></td>
      <td >
 <!--<a id="reloaddata" href="blogupdate?pid=<?php echo $row['Post_ID']; ?>" class="btn btn-danger btnupdate" style="font-size:10px"><i class="fa fa-edit" aria-hidden="true" style="font-size:10px"></i></a>-->
 <a class="btn btn-danger btnupdate" data-toggle="modal" data-target="#Updatethis<?php echo $row['Post_ID'];  ?>" style="font-size:10px;color: #fff;"><i class="fa fa-edit" aria-hidden="true" style="font-size:10px" ></i></a>
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
          <label for="text" text="<?php echo $row['Post_ID']; ?>" id="postID">Post ID: <?php echo " ". $row['Post_ID']; ?></label>

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
        <button type="button" class="btn btn-primary" id="updateblog_save" >Update</button>  




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
}
?>