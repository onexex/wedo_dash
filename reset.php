<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
	date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html>
<head>
	<title>Automated Leave Application System</title>
	  <meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script> 
	  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
	  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	  <script type="text/javascript" src="assets/js/script.js"></script>
	      <script src="assets/js/script-reports.js"></script>
	  <script type="text/javascript" src="assets/js/script-modules.js"></script>
	  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
  <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
</head>

<style type="text/css">
	.modal-backdrop{
		background-color: transparent;
	}
	.ihd-dis{
		display:  none;
	}
</style>
<body style="background-image: none">
	<?php
		include 'includes/header.php';
	?>
	<div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9 module-content">
       			<h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">RESET DATA</h4>
        			<table  class="table table-striped">
        				<thead>
        					<tr><th>Company ID</th>
        						<th>Company Name</th>
        						<th>Action</th>
        					</tr>
        				</thead>
        				<tbody id="tblres">
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
							    $statement = $pdo->prepare("select * from companies");
							    $statement->execute();
							    while ($row = $statement->fetch()){
               				?>
	               				<tr>
	               					<td class="td-dar"><?php echo $row['CompanyID']; ?></td>
	                                <td class="td-dar"><?php echo $row['CompanyDesc']; ?></td>
	               					<td><button class="btn btn-danger" data-toggle="modal" data-target="#myModal<?php echo $row[0]; ?>">Clear All Data</button></td>
	               				</tr>

	               				 <div class="modal" id="myModal<?php echo $row[0]; ?>">
								    <div class="modal-dialog">
								      <div class="modal-content">
								      
								        <!-- Modal Header -->
								        <div class="modal-header">
								          <h4 class="modal-title">Are you Sure you want to Clear data of <?php echo $row['CompanyDesc']; ?></h4>
								          <button type="button" class="close" data-dismiss="modal">&times;</button>
								        </div>
								        
								        <!-- Modal body -->
								        <div class="modal-body">
								             <button type="button" id="<?php echo $row[0]; ?>" class="btn btn-success clrdata">Yes</button>
								             <button type="button" class="btn btn-danger" data-dismiss="modal">NO</button>
								        </div>
								        
								        <!-- Modal footer -->
								        <div class="modal-footer">
								    
								        </div>
								        
								      </div>
								    </div>
								  </div>
								  <!-- end of modal -->
               				<?php
               					}
               				?>
        				</tbody>
        			</table>
        	

         <!-- end of website content -->
         </div>
     	</div>
 	</div>
</body>
</html>