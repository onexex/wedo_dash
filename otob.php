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
	<title>OverTime OB System</title>
	  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script> 
	  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
	  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	  <script type="text/javascript" src="assets/js/script.js"></script>
	  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
	  <script type="text/javascript">
	  	  $(document).ready(function(){
	      	$(".btnotob").click(function(){ 
	      		alert("a");
	      	});
	      });

	  </script
	  <style>
	    html body{
		font-family: Tahoma !important;
	}</style>
</head>
<body style="background-image: none">
	<?php
		include 'includes/header.php';
	?>
	<div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9 module-content">
       
        	
        	<div class="row">
        		<div class="col-lg-12">
        			<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newform">+ Overtime OB Form</button>
        			<!-- The Modal -->
					<div class="modal" id="newform">
					  <div class="modal-dialog">
					    <div class="modal-content">

					      <!-- Modal Header -->
					      <div class="modal-header">
					        <h4 class="modal-title">Overtime OB Form</h4>
					        <button type="button" class="close" data-dismiss="modal">&times;</button>
					      </div>
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
				                   
				                  $id=$_SESSION['id'];
				                  $isid=$_SESSION['EmpISID'];
				                  $statement = $pdo->prepare("SELECT *                                          
				                              FROM Employees 
				                              INNER JOIN EmpDetails ON Employees.EmpID=EmpDetails.EmpID
				                              INNER JOIN Companies ON EmpDetails.EmpCompID=Companies.CompanyID
				                              INNER JOIN Departments ON EmpDetails.EmpdepID=Departments.DepartmentID 
				                              INNER JOIN Positions ON Positions.PSID=Employees.PosID where Employees.EmpID=:id order by Employees.EmpLN ASC ");
				                  $statement->bindParam(':id' , $id);
				                  $statement->execute();
				                  $row = $statement->fetch();
				                ?>
					      <!-- Modal body -->
					      <div class="modal-body">
					        			<form action="">
					        <div class="row">
					        		<div class="col-lg-6">
											  <div class="form-group">
											    <label >Personnel Name:</label>
											    <input type="text" disabled class="form-control" value="<?php echo $row['EmpLN']. ' ' . $row['EmpFN']. ' ' .$row['EmpMN'] ?>">
											  </div>
											   <div class="form-group">
											    <label >Company Name:</label>
											    <input type="text" disabled class="form-control" value="<?php echo $row['CompanyDesc'] ?>">
											  </div>
											   <div class="form-group">
											    <label >Department:</label>
											    <input type="text" disabled class="form-control" value="<?php echo $row['DepartmentDesc'] ?>">
											  </div>
											  <div class="form-group">
											    <label >Designation:</label>
											    <input type="text" disabled class="form-control" value="<?php echo $row['PositionDesc'] ?>" >
											  </div>
											  

					        		</div>
					        		<div class="col-lg-3">
					        			   <div class="form-group">
											    <label >Filling Date:</label>
											    <input type="text" disabled value="<?php echo date('F d, Y'); ?>" class="form-control" >
											</div>
											 <div class="form-group">
											    <label >OT Date From:</label>
											    <input type="date" name="otdf" id="otdf"  class="form-control" >
											</div>
											 <div class="form-group">
											    <label >OT Date To:</label>
											    <input type="date" name="otdt" id="otdt" class="form-control" >
											</div>
					        		</div>
					        		<div class="col-lg-3">
					        			   <div class="form-group">
											    <label >Filling Time:</label>
											    <input type="text" disabled value="<?php echo date('g:i:s A'); ?>" class="form-control" >
											</div>
											 <div class="form-group">
											    <label >OT Time From:</label>
											    <input type="time" ame="otTimefrom" id="otTimefrom"   class="form-control" >
											</div>
											 <div class="form-group">
											    <label >OT Time To:</label>
											    <input type="time" name="otTimeto" id="otTimeto"  class="form-control" >
											</div>
											<div class="form-group">
											    <label >Duration Days:</label>
											    <input type="text" disabled class="form-control" >
											</div>
											
					        		</div>
					        		<div class="col-lg-12">
					        			<div class="form-group">
											    <label >Purpose:</label>
											    <textarea class="form-control" name="otobpurpose" id="otobpurpose" rows="4"id="comment"></textarea>
											  </div>
					        		</div>
					        			<button type="button" class="btn btn-success btnotob" >Submit</button>
					        	</div>
					        	
										</form>
					      </div>

					      <!-- Modal footer -->
					      <div class="modal-footer">
					        <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
					      </div>

					    </div>
					  </div>
					</div>



        			<br>
        			<h5 class="module-title-history">Overtime OB History</h5>
        			<div class="dtpar">
        				<h5>Date Parameter:</h5>
        				<label>From:</label>
        				<input type="date" class="form-control">
        				<label>To:</label>
        				<input type="date" class="form-control">
        			</div>
        			<table class="table table-striped">
        				<thead>
        					 <tr >
						        <th >Filing Date</th>
						        <th >Date From</th>
						        <th >Date To</th>
						        <th >Time From</th>
						        <th >Time To</th>
						        <th >Itinerary From</th>
						        <th >Itinerary To</th>
						        <th >Purpose</th>
						        <th >Status</th>
						      </tr>
        				</thead>
        				   <tbody id="tbob">
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
                      $id=$_SESSION['id'];
                  $statement = $pdo->prepare("SELECT * from obs as a 
                                  INNER JOIN Status as b on a.OBStatus=b.StatusID  
                                  where a.EmpID=:id ");

                  $statement->bindParam(':id' , $id);
                  $statement->execute();
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php echo $row21['OBFD']; ?></td>  
                   <td><?php echo $row21['OBDateFrom']; ?></td>
                   <td><?php echo $row21['OBDateTo']; ?></td>
                   <td><?php echo $row21['OBTimeFrom']; ?></td> 
                   <td><?php echo $row21['OBTimeTo']; ?></td>
                   <td><?php echo $row21['OBIFrom']; ?></td>
                   <td><?php echo $row21['OBITo']; ?></td>
                   <td><?php echo $row21['OBPurpose']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                
                  </tr> 
              <?php 
              }

              ?>


    </tbody>
        			</table>
        		</div>
        	</div>
         <!-- end of website content -->
         </div>
     	</div>
 	</div>
</body>
</html>