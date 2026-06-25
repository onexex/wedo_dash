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



$statement = $pdo->prepare("select * from accessrights where EmpID = :id");
$statement->bindParam(':id' , $id);
$statement->execute(); 

while ($row = $statement->fetch()){
	?>

    <style type="text/css">
	  html, body{
	  font-family: Tahoma !important;
	}
	</style>
	
	<tr>
		<td><i style="outline: none;" class="fa fa-folder"></i> <label> Electronic 201 File </label></td>
	<?php if ($row['e201']==1){
	?>
		<td><button id="e201" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="e201" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<tr>
		<td><i class="fa fa-calendar-check-o"> </i> <label> Leave Credit Overview</label> </td>
		<?php if ($row['lcreaditview']==1){
	?>
		<td><button id="lcreaditview" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="lcreaditview" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	
	
	</tr>

    <tr>
		<td><i style="outline: none;" class="fa fa-calendar-check-o"></i> <label> Automated Leave Application System</label> </td>
		<?php if ($row['alas']==1){
	?>
		<td><button id="alas" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="alas" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	
	
	</tr>
	 <tr>
		<td><i class="fa fa-file-o"></i> <label> Check Register</label> </td>
		<?php if ($row['checkregister']==1){
	?>
		<td><button id="checkregister" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="checkregister" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	
	
	</tr>
		 <tr>
		<td><i class="fa fa-file-o"></i> <label> Memo Generator</label></td>
		<?php if ($row['memo']==1){
	?>
		<td><button id="memo" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="memo" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	
	
	</tr>
	
	<tr>
		<td><i class="fa fa-clock-o"></i> <label> Payroll Management System</label> </td>
		<?php if ($row['payroll']==1){
	?>
		<td><button id="payroll" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="payroll" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>

	<tr>
		<td><i class="fa fa-clock-o"></i> <label> Payslip</label>  </td>
		<?php if ($row['payslipt']==1){
	?>
		<td><button id="payslipt" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="payslipt" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<tr>
		<td><i class="fa fa-clock-o"></i> <label> Debit Advise Module</label> </td>
		<?php if ($row['debitadvise']==1){
	?>
		<td><button id="debitadvise" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="debitadvise" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>	
	<tr>
		<td><i class="fa fa-clock-o"> </i> <label> Achived Management System</label> </td>
		<?php if ($row['ams']==1){
	?>
		<td><button id="ams" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="ams" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
		<tr>
		<td><i class="fa fa-calendar"></i> <label> Employee Scheduling</label> </td>
		<?php if ($row['EF']==1){
	?>
		<td><button id="EF" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="EF" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<tr>
		<td><i class="fa fa-clock-o"></i> <label> Overtime OB System</label> </td>
		<?php if ($row['otob']==1){
	?>
		<td><button id="otob" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="otob" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<tr>
		<td><i class="fa fa-briefcase"></i> <label> Official Business Trip Tracker</label> </td>
		<?php if ($row['ob']==1){
	?>
		<td><button id="ob" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="ob" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<tr>
		<td><i class="fa fa-plane"></i> <label> Send to OB Trip</label> </td>
		<?php if ($row['sob']==1){
	?>
		<td><button id="sob" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="sob" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<tr>
		<td><i class="fa fa-calendar-plus-o"></i> <label> Overtime Filing System</label> </td>
		<?php if ($row['ot']==1){
	?>
		<td><button id="ot" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="ot" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<tr>
		<td><i class="fa fa-calendar-minus-o"></i> <label> Early Out System</label> </td>
		<?php if ($row['eo']==1){
	?>
		<td><button id="eo" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="eo" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
		<tr>
		<td><i class="fa fa-clipboard"></i> <label> New Blog</label> </td>
		<?php if ($row['nwblogs']==1){
	?>
		<td><button id="nwblogs" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td>
	<?php
	}else{
	?>
		<td><button id="nwblogs" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td>
	<?php
	} ?>
	</tr>
	<br>
	<tr>
		<th>Reports</th>
		<th>Access</th>
	</tr>

	<!-- reports -->
	<tr><td><i class="fa fa-bar-chart"></i> <label> ALAS Viewer</label> </td>
		<?php if ($row['alasv']==1){ ?> <td><button id="alasv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="alasv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
	<tr><td><i class="fa fa-bar-chart"> </i> <label> Attendance Viewer</label> </td>
		<?php if ($row['lilov']==1){ ?> <td><button id="lilov" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="lilov" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

		<tr><td><i class="fa fa-bar-chart"> </i> <label> Compliance Document Data</label> </td>
		<?php if ($row['cddv']==1){ ?> <td><button id="cddv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="cddv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
    
		<tr><td><i class="fa fa-bar-chart"> </i> <label> Daily Activity Viewer</label> </td>
		<?php if ($row['darv']==1){ ?> <td><button id="darv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="darv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
		<tr><td><i class="fa fa-bar-chart"> </i> <label> Early Out Viewer</label> </td>
		<?php if ($row['eov']==1){ ?> <td><button id="eov" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="eov" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
		
		<tr><td><i class="fa fa-bar-chart"> </i> <label> Employment Information</label> </td>
		<?php if ($row['emv']==1){ ?> <td><button id="emv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="emv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

		<tr><td><i class="fa fa-bar-chart"> </i> <label> Employee Alpha List Generator</label> </td>
		<?php if ($row['alphav']==1){ ?> <td><button id="alphav" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="alphav" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
		<tr><td><i class="fa fa-bar-chart"> </i> <label> Official Business Viewer</label> </td>
		<?php if ($row['obv']==1){ ?> <td><button id="obv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="obv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
			<tr><td><i class="fa fa-bar-chart"> </i> <label> Overtime Viewer</label> </td>
		<?php if ($row['atv']==1){ ?> <td><button id="atv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="atv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
		<tr><td><i class="fa fa-bar-chart"> </i> <label> Personal Information</label> </td>
		<?php if ($row['piv']==1){ ?> <td><button id="piv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="piv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
		<!-- 13 month start -->
		<tr><td><i class="fa fa-bar-chart"> </i> <label> 13th Month Attachement</label> </td>
			<?php if ($row['access_13_attachement']==1){ ?> <td><button id="access_13_attachement" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
		}else{ ?> <td><button id="access_13_attachement" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

		<tr><td><i class="fa fa-bar-chart"> </i> <label> YTD 13thMonth Report</label> </td>
			<?php if ($row['access_13']==1){ ?> <td><button id="access_13" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
		}else{ ?> <td><button id="access_13" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	<!-- 13 month end -->

	<br>
		<tr>
		<th>Management</th>
		<th>Access</th>
	</tr>
				<tr><td><i class="fa fa-lock"> </i> <label> Access Rights</label> </td>
		<?php if ($row['arights']==1){ ?> <td><button id="arights" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="arights" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
					<tr><td><i class="fa fa-lock"> </i> <label> Booklet Management </label> </td>
		<?php if ($row['bookletreg']==1){ ?> <td><button id="bookletreg" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="bookletreg" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
					<tr><td><i class="fa fa-lock"> </i> <label> Payee Registry</label> </td>
		<?php if ($row['payeereg']==1){ ?> <td><button id="payeereg" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="payeereg" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

			<tr><td><i class="fa fa-user-plus"> </i> <label> Enroll Employee</label> </td>
		<?php if ($row['eemployee']==1){ ?> <td><button id="eemployee" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="eemployee" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

				<tr><td><i class="fa fa-file-pdf-o"> </i> <label> Electronic 201 Document</label> </td>
		<?php if ($row['e201d']==1){ ?> <td><button id="e201d" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="e201d" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
		<br>
		<tr>
		<th>Maintenance</th>
		<th>Access</th>
	</tr>
					<tr><td><i class="fa fa-plus-square-o"> </i> <label> Agency</label> </td>
		<?php if ($row['agncy']==1){ ?> <td><button id="agncy" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="agncy" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

					<tr><td><i class="fa fa-plus-square-o"> </i> <label> Company</label> </td>
		<?php if ($row['comp']==1){ ?> <td><button id="comp" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="comp" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>


					<tr><td><i class="fa fa-plus-square-o" > </i> <label> Department</label> </td>
		<?php if ($row['dep']==1){ ?> <td><button id="dep" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="dep" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

    <tr><td><i class="fa fa-plus-square-o"> </i> <label> Debit Advise Settings</label> </td>
		<?php if ($row['debitadvisesettings']==1){ ?> <td><button id="debitadvisesettings" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="debitadvisesettings" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>


					<tr><td><i class="fa fa-plus-square-o"> </i> <label> Position</label> </td>
		<?php if ($row['pos']==1){ ?> <td><button id="pos" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="pos" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

					<tr><td><i class="fa fa-plus-square-o"> </i> <label> Job Level</label> </td>
		<?php if ($row['jl']==1){ ?> <td><button id="jl" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="jl" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

				<tr><td><i class="fa fa-plus-square-o"> </i> <label> HMO</label> </td>
		<?php if ($row['hmo']==1){ ?> <td><button id="hmo" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="hmo" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

				<tr><td><i class="fa fa-plus-square-o"> </i> <label> Employee Status</label> </td>
		<?php if ($row['est']==1){ ?> <td><button id="est" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="est" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

			<tr><td><i class="fa fa-plus-square-o"> </i> <label> Relationship</label> </td>
		<?php if ($row['rel']==1){ ?> <td><button id="rel" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="rel" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

			<tr><td><i class="fa fa-plus-square-o"> </i> <label> Classification</label> </td>
		<?php if ($row['classf']==1){ ?> <td><button id="classf" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="classf" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

			<tr><td><i class="fa fa-plus-square-o"> </i> <label> Work TIme</label> </td>
		<?php if ($row['wt']==1){ ?> <td><button id="wt" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="wt" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

		<tr><td><i class="fa fa-plus-square-o"> </i> <label> Work Days</label> </td>
		<?php if ($row['wd']==1){ ?> <td><button id="wd" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="wd" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

		<tr><td><i class="fa fa-plus-square-o"> </i> <label> Types of Leave</label> </td>
		<?php if ($row['tlv']==1){ ?> <td><button id="tlv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="tlv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

		<tr><td><i class="fa fa-plus-square-o"> </i> <label> Leave  Validation</label> </td>
		<?php if ($row['lval']==1){ ?> <td><button id="lval" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="lval" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

	<tr><td><i class="fa fa-plus-square-o"> </i> <label> User Role</label> </td>
		<?php if ($row['ur']==1){ ?> <td><button id="ur" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="ur" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>
	
	<tr><td><i class="fa fa-plus-square-o"> </i> <label> Holiday Logger</label> </td>
		<?php if ($row['hldy']==1){ ?> <td><button id="hldy" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="hldy" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

	<tr><td><i class="fa fa-plus-square-o"> </i> <label> LiLo Validation</label> </td>
		<?php if ($row['gprdv']==1){ ?> <td><button id="gprdv" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="gprdv" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

	<tr><td><i class="fa fa-plus-square-o"> </i> <label> OB Validation</label> </td>
		<?php if ($row['obval']==1){ ?> <td><button id="obval" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="obval" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

	<tr><td><i class="fa fa-plus-square-o"> </i> <label> EO Validation</label> </td>
		<?php if ($row['eoval']==1){ ?> <td><button id="eoval" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="eoval" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

	<tr><td><i class="fa fa-plus-square-o"> </i> <label> OT Filing System Maintenance</label> </td>
		<?php if ($row['otfs']==1){ ?> <td><button id="otfs" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="otfs" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>


	<br>
		<tr>
		<th>Others</th>
		<th>Access</th>
	</tr>
			<tr><td><i class="fa fa-user-circle-o">  </i> <label> Search Employee</label>  </td>
		<?php if ($row['srch']==1){ ?> <td><button id="srch" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="srch" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>


		<tr><td><i class="fa fa-pencil-square">  </i> <label> Update 201 Files</label>  </td>
		<?php if ($row['updte']==1){ ?> <td><button id="updte" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="updte" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>

		<tr><td><i class="fa fa-bullhorn">  </i> <label> GM'S CORNER</label>  </td>
		<?php if ($row['gcorner']==1){ ?> <td><button id="gcorner" class="btn btn-danger" data-toggle="modal" data-target="#acchange">OFF</button></td><?php
	}else{ ?> <td><button id="gcorner" class="btn btn-success" data-toggle="modal" data-target="#acchange">ON</button></td> <?php } ?> </tr>


	<?php
}
?>
