<?php
	if (isset($_GET['updateeoval'])){
				include 'w_conn.php';

			try{
			$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			   }
			catch(PDOException $e)
			   {
			die("ERROR: Could not connect. " . $e->getMessage());
			   }
			   	$sql = "UPDATE eovalidation SET IsBefore=:bef,IsTardy=:tar,IsAfter=:aft WHERE  compid=:id";
		   		$stmt = $pdo->prepare($sql);
		   		$stmt->bindParam(':bef', $_POST['fbefore']);
		   		$stmt->bindParam(':tar', $_POST['ftardy']);
		   		$stmt->bindParam(':aft', $_POST['fafter']);
		   		$stmt->bindParam(':id', $_SESSION['CompID']);
		   		 $stmt->execute(); 
		   		    header ('location: maintenance.php?eovalidation');
	}
	if (isset($_GET['updateobval'])){
				include 'w_conn.php';

			try{
			$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			   }
			catch(PDOException $e)
			   {
			die("ERROR: Could not connect. " . $e->getMessage());
			   }
			   	$sql = "UPDATE obvalidation SET IsBefore=:bef,IsAfter=:aft,DaysBefore=:dysbef,DaysAfter=:dysaft WHERE  compid=:id";
		   		$stmt = $pdo->prepare($sql);
		   		$stmt->bindParam(':bef', $_POST['fbefore']);
		   		$stmt->bindParam(':aft', $_POST['fafter']);
		   		$stmt->bindParam(':dysbef', $_POST['dysbefore']);
		   		$stmt->bindParam(':dysaft', $_POST['dysafter']);
		   		$stmt->bindParam(':id', $_SESSION['CompID']);
		   		$stmt->execute(); 
		   		header ('location: maintenance.php?obvalidation');
	}

	if (isset($_GET['updateliloval'])){
				include 'w_conn.php';

			try{
			$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			   }
			catch(PDOException $e)
			   {
			die("ERROR: Could not connect. " . $e->getMessage());
			   }
			   	$sql = "UPDATE lilovalidation SET EmpGP=:gp WHERE  EmpCompID=:id";
		   		$stmt = $pdo->prepare($sql);
		   		$stmt->bindParam(':gp', $_POST['gperiod']);
		   		$stmt->bindParam(':id', $_SESSION['CompID']);
		   		 $stmt->execute(); 
		   		    header ('location: maintenance.php?lilovalidation');
	}
	if (isset($_GET['updtcomp'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		// insert into dars
	
		$sql = "UPDATE companies SET CompanyDesc=:agename,compcode=:ccode,comcolor=:ccolor WHERE  CompanyID=:id";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id', $_POST['ucomno']);
		   $stmt->bindParam(':agename', $_POST['ucomname']);
		   $stmt->bindParam(':ccode', $_POST['ucomcode']);
		   $stmt->bindParam(':ccolor', $_POST['ucomcolor']);
		   $stmt->execute(); 

		   if ($_FILES['ucomplogo']['name']!=""){
			   $filename = $_FILES['ucomplogo']['name'];
				/* Location */
				$location = "assets/images/logos/". $_POST['ucomno'] . ".jpg";
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
			   move_uploaded_file($_FILES['ucomplogo']['tmp_name'],$location);
			     echo $location;
			  
			  
			   }

		   }

		   	$_SESSION['CompanyColor']=$_POST['ucomcolor'];

		   	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Company";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

		   header ('location: maintenance.php?company');
	}
	//sss SIL LOAN
	if (isset($_GET['updatesilloan'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		
		   $id=$_GET['updatesilloan'];
		   $sql = "Update silloan set loanAmount=:lm,LoanType=:lty,loanStatus=:lst where lid=:ids";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':lm', $_POST['loanam']);
		   $stmt->bindParam(':lty', $_POST['lty']);
		   $stmt->bindParam(':lst', $_POST['lstat']);
		   $stmt->bindParam(':ids', $id);
		   $stmt->execute(); 


		   date_default_timezone_set("Asia/Manila"); 
		   $today2 =date("Y-m-d H:i:s");
			$id=$_SESSION['id'];
			$ch="Maintenance : Updated SIL LOAN";
			// insert into dars
			$sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(':id' , $id);
			$stmt->bindParam(':empact', $ch);
			$stmt->bindParam(':tdy', $today2);
			$stmt->execute(); 
		  			 header ('location: maintenance.php?silloan');
	}

	// end SIL LOAN
	//sss update
	if (isset($_GET['updatesss'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		
		   $id=$_GET['updatesss'];
		   $sql = "Update sss set sssc=:txtssc,SalaryFrom=:sfrom,SalaryTo=:sto,SSER=:ser,SSEE=:see,SSEC=:sec where ID=:ids";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':txtssc', $_POST['SSSC']);
		   $stmt->bindParam(':sfrom', $_POST['salaryfrom']);
		   $stmt->bindParam(':sto', $_POST['salaryto']);
		   $stmt->bindParam(':ser', $_POST['SSER']);
		   $stmt->bindParam(':see', $_POST['SSEE']);
		   $stmt->bindParam(':sec', $_POST['SSEC']);
		   $stmt->bindParam(':ids', $_GET['updatesss']);
		   $stmt->execute(); 


		   date_default_timezone_set("Asia/Manila"); 
		   $today2 =date("Y-m-d H:i:s");
			$id=$_SESSION['id'];
			$ch="Maintenance : Updated SSS";
			// insert into dars
			$sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(':id' , $id);
			$stmt->bindParam(':empact', $ch);
			$stmt->bindParam(':tdy', $today2);
			$stmt->execute(); 
		  			 header ('location: maintenance.php?sss');
	}

	// end sss
	// pagibig
	if (isset($_GET['updatepagibig'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		
		   $id=$_GET['updatepagibig'];
		   $sql = "Update pagibig set EE=:ee,ER=:er where PIID=:ids";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':ee', $_POST['ee']);
		   $stmt->bindParam(':er', $_POST['er']);
		   $stmt->bindParam(':ids', $id);
		   $stmt->execute(); 


		   date_default_timezone_set("Asia/Manila"); 
		   $today2 =date("Y-m-d H:i:s");
			$id=$_SESSION['id'];
			$ch="Maintenance : Updated Pagibig";
			// insert into dars
			$sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(':id' , $id);
			$stmt->bindParam(':empact', $ch);
			$stmt->bindParam(':tdy', $today2);
			$stmt->execute(); 
		  	header ('location: maintenance.php?pagibig');
	}

	// end of pagibig
	// philhealth
	if (isset($_GET['updatephilhealth'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		
		   $id=$_GET['updatephilhealth'];
		   $sql = "Update philhealth set PHSB=:PHSB,SalaryFrom=:SalaryFrom,SalaryTo=:SalaryTo,PHEE=:PHEE,PHER=:PHER where ID=:ids";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':PHSB', $_POST['PHSB']);
		   $stmt->bindParam(':SalaryFrom', $_POST['SalaryFrom']);
		   $stmt->bindParam(':SalaryTo', $_POST['SalaryTo']);
		   $stmt->bindParam(':PHEE', $_POST['PHEE']);
		   $stmt->bindParam(':PHER', $_POST['PHER']);
		   $stmt->bindParam(':ids', $id);
		   $stmt->execute(); 


		   date_default_timezone_set("Asia/Manila"); 
		   $today2 =date("Y-m-d H:i:s");
			$id=$_SESSION['id'];
			$ch="Maintenance : Updated Pagibig";
			// insert into dars
			$sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(':id' , $id);
			$stmt->bindParam(':empact', $ch);
			$stmt->bindParam(':tdy', $today2);
			$stmt->execute(); 
		  	header ('location: maintenance.php?philhealth');
	}
	
	// end of philhealth

	
	if (isset($_GET['updtdep'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		
		   $id=$_GET['updtdep'];
			$sql = "UPDATE departments SET DepartmentDesc='$_POST[depname]' WHERE DepartmentID='$id'";
		  
		   $stmt = $pdo->prepare($sql);
		    $stmt->execute(); 
		     	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Department";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 
		   header ('location: maintenance.php?department');
	}

	if (isset($_GET['updatepos'])){
			include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		   $sql = "UPDATE positions SET positionDesc=:pos1,DepartmentID=:dep1,EmpJobLevelID=:jbl WHERE PSID=:id";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':pos1', $_POST['pos']);
		   $stmt->bindParam(':dep1', $_POST['dep']);
		   $stmt->bindParam(':jbl', $_POST['joblevel']);
		   $stmt->bindParam(':id', $_GET['updatepos']);
		   $stmt->execute(); 

		    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Position";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 
		    header ('location: maintenance.php?position');
	}
	if (isset($_GET['updatejoblevel'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		// insert into dars
		$sql = "UPDATE joblevel SET jobLevelDesc=:lvl WHERE jobLevelID=:code";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':code', $_POST['jid']);
		   $stmt->bindParam(':lvl', $_POST['joblevel']);
		   $stmt->execute(); 

		    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Job Level";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

		   header ('location: maintenance.php?joblevel');
	}
	if (isset($_GET['updatehmo'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		// insert into dars
		$sql = "UPDATE hmo set HMO_PROVIDER=:hname WHERE HMO_ID=:hid";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':hid', $_POST['hmoid']);
		   $stmt->bindParam(':hname', $_POST['hmoname']);
		   $stmt->execute(); 

		    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated HMO";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

		    header ('location: maintenance.php?hmo');
	}
	if (isset($_GET['updateempstat'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		// insert into dars
		$sql = "UPDATE estatus SET StatusEmpDesc=:stat  WHERE ID=:id";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':stat', $_POST['empstat']);
		   $stmt->bindParam(':id', $_GET['updateempstat']);
		   $stmt->execute(); 
		    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Employee Status";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

		 header ('location: maintenance.php?employeestatus');
	}
	if (isset($_GET['updaterel'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		// insert into dars
		$sql = "UPDATE frelationship SET FRelDesc=:rell WHERE FRelID=:id";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id', $_GET['updaterel']);
		   $stmt->bindParam(':rell', $_POST['relname']);
		   $stmt->execute(); 
		   		 header ('location: maintenance.php?relationship');
		   		 	 	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Family Relationship";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 
	}
		

		if (isset($_GET['updateclass'])){
			include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		// insert into dars
		$sql = "UPDATE empstatus SET EmpCODE=:clcode,EmpStatDesc=:cldesc WHERE EmpStatID=:id";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':clcode', $_POST['classcode']);
		   $stmt->bindParam(':cldesc', $_POST['classdesc']);
		   $stmt->bindParam(':id', $_GET['updateclass']);
		   $stmt->execute(); 

		    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Classification";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 
		   header ('location: maintenance.php?classification');
		}
		if (isset($_GET['updatewtime'])){
					include 'w_conn.php';

					try{
					$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
					$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					   }
					catch(PDOException $e)
					   {
					die("ERROR: Could not connect. " . $e->getMessage());
					   }
					// insert into dars
					   $date = $_POST['timefrom']; 
						$tfr = date('h:i A', strtotime($date));
						 $date = $_POST['timeto']; 
						$tto = date('h:i A', strtotime($date));
					$sql = "UPDATE workschedule SET TimeFrom=:tfrom,Timeto=:tto WHERE WorkSchedID=:id";
					   $stmt = $pdo->prepare($sql);
					   $stmt->bindParam(':tfrom', $tfr);
					   $stmt->bindParam(':tto', $tto);
					   $stmt->bindParam(':id', $_GET['updatewtime']);
					   $stmt->execute(); 

					    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Work Shift";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

					      header ('location: maintenance.php?worktime');
			}
			if (isset($_GET['updateworkdays'])){
				include 'w_conn.php';

					try{
					$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
					$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					   }
					catch(PDOException $e)
					   {
					die("ERROR: Could not connect. " . $e->getMessage());
					   }
					// insert into dars
					   $tfr=$_POST['dayfrom'] . " - " . $_POST['dayto'];
					$sql = "UPDATE workdays SET WDesc=:dtfrt WHERE WID=:id";
					   $stmt = $pdo->prepare($sql);
					   $stmt->bindParam(':dtfrt', $tfr);
					   $stmt->bindParam(':id', $_GET['updateworkdays']);
					   $stmt->execute(); 
					    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Work Days";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

					    header ('location: maintenance.php?workdays');
			}
			if (isset($_GET['updateagency'])){
				include 'w_conn.php';

				try{
				$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				   }
				catch(PDOException $e)
				   {
				die("ERROR: Could not connect. " . $e->getMessage());
				   }
				// insert into dars
				$sql = "UPDATE agency SET AgencyID=:id,AgencyName=:agename,IsActive=:agestat WHERE ASID=:ageid";
				   $stmt = $pdo->prepare($sql);
				   $stmt->bindParam(':id', $_POST['agencyno']);
				   $stmt->bindParam(':ageid', $_GET['updateagency']);
				   $stmt->bindParam(':agename', $_POST['agencyname']);
				   $stmt->bindParam(':agestat', $_POST['agencystatus']);
				   $stmt->execute(); 
				    	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Agency";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

				       header ('location: maintenance.php?agency');
			} if (isset($_GET['updattol'])){
				include 'w_conn.php';

				try{
				$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				   }
				catch(PDOException $e)
				   {
				die("ERROR: Could not connect. " . $e->getMessage());
				   }
				    $sql = "UPDATE leaves SET LeaveDesc=:ln WHERE LeaveID=:lid";
					$stmt = $pdo->prepare($sql);
					$stmt->bindParam(':ln', $_POST['lname']);
					$stmt->bindParam(':lid', $_GET['updattol']);
					$stmt->execute(); 

					 	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Leave";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

				    header ('location: maintenance.php?typesofleave');
			}
			if (isset($_GET['leavevalupdate'])){
				include 'w_conn.php';

				try{
				$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				   }
				catch(PDOException $e)
				   {
				die("ERROR: Could not connect. " . $e->getMessage());
				   }
				    $sql = "UPDATE leaves_validation SET leave_credits=:lcr,leave_min=:mleave,lid=:ltype,leave_before=:cfb,leave_file_after=:cfa,filing_after_duration=:fad,	filing_before_duration=:ndb WHERE sid=:id";

					$stmt = $pdo->prepare($sql);
					$stmt->bindParam(':id', $_POST['lvalid']);
					$stmt->bindParam(':lcr', $_POST['credits']);
					$stmt->bindParam(':mleave', $_POST['minleave']);
					$stmt->bindParam(':ltype', $_POST['ltype']);
					$stmt->bindParam(':cfb', $_POST['filebfore']);
					$stmt->bindParam(':cfa', $_POST['fileafter']);
					$stmt->bindParam(':fad', $_POST['ndal']);
					$stmt->bindParam(':ndb', $_POST['ndbf']);
					$stmt->execute(); 

					 	 $id=$_SESSION['id'];
						     $ch="Maintenance : Updated Leave Validation";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

				    header ('location: maintenance.php?leavevalidation');
			}
?>