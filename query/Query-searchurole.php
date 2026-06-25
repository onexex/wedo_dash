<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
	include 'w_conn.php';
?>
<?php
try{
$id=$_GET['q'];
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }



$statement = $pdo->prepare("select * from empdetails where EmpID = :id");
$statement->bindParam(':id' , $id);
$statement->execute(); 
$row = $statement->fetch();

$statement = $pdo->prepare("select * from employees where EmpID = :id");
$statement->bindParam(':id' , $id);
$statement->execute(); 
$res = $statement->fetch();

?>
<tr>
	<td><?php echo $res['EmpFN'] . " " . $res['EmpLN']; ?></td>
	<td>
<?php
	if ($row['EmpRoleID']==1){
		echo "Super User";
	}
	else if($row['EmpRoleID']==2){
			echo "Admin";	
	}
	else{
		echo "User";
	}

?>		
	</td>
	<td><button  data-toggle="modal" data-target="#myview" class="btn btn-info" title="UPDATE"><i class="fa fa-pencil" aria-hidden="true"></i></button></td>

</tr>
	  <div class="modal" id="myview">
    <div class="modal-dialog">
      <div class="modal-content">
      
        <!-- Modal Header -->
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        
        <!-- Modal body -->
        <div class="modal-body">
          <form action="?updtrole=<?php echo $id; ?>" method="post" class="frmup" enctype="multipart/form-data">
      			<div class="form-group">

				  <label for="sel1">Select User role for <?php echo $res['EmpFN'] . " " . $res['EmpLN']; ?>:</label>
				  <select class="form-control" id="urole<?php echo $id; ?>" name="sel1">
				    <option value="1">Super User</option>
				    <option value="2">Admin</option>
				    <option value="3">User</option>
				  </select>
				</div>
				<button type="button" class="btn btn-success changeurole" id="<?php echo $id; ?>">Change</button>
          </form>
        </div>
        
      
        
      </div>
    </div>
  </div>
  <!-- end of modal -->