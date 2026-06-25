<?php
	if (session_status() === PHP_SESSION_NONE) { session_start(); }
	if (isset($_GET['empidchanged'])){
				include 'w_conn.php';
		  		$sql=mysqli_query($con, "select * from empdetails where EmpCompID='".  $_POST['compid'] ."'");
                              $nrows=mysqli_num_rows($sql);

                              $sql2=mysqli_query($con, "select * from companies where CompanyID='".  $_POST['compid'] ."'");
                              $res1=mysqli_fetch_array($sql2);
                              $cnt="";
                              if ($nrows<10){
                                $cnt="00";
                              }elseif($nrows<100){
                                $cnt="0";
                              }
                              $em=$res1['compcode']  . '-' . $cnt . ($nrows+1);
                              echo $em;
	}
	if (isset($_GET['addeoval'])){
		include 'w_conn.php';
		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		    $sql = "INSERT INTO  eovalidation (compid,IsBefore,IsTardy,IsAfter) VALUES (:cid,:bef,:tar,:aft)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':cid', $_SESSION['CompID']);
		   $stmt->bindParam(':bef', $_POST['fbefore']);
		   $stmt->bindParam(':tar', $_POST['ftardy']);
		   $stmt->bindParam(':aft', $_POST['fafter']);
		    $stmt->execute();
	}
	if (isset($_GET['addobval'])){
		include 'w_conn.php';
		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		    $sql = "INSERT INTO  obvalidation (compid,IsBefore,IsAfter,DaysBefore,DaysAfter) VALUES (:cid,:bef,:aft,:dysbef,:dysaft)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':cid', $_SESSION['CompID']);
		   $stmt->bindParam(':bef', $_POST['fbefore']);
		   $stmt->bindParam(':aft', $_POST['fafter']);
		   $stmt->bindParam(':dysbef', $_POST['dysbefore']);
		   $stmt->bindParam(':dysaft', $_POST['dysafter']);
		    $stmt->execute();
	}
	if (isset($_GET['addgraceperiod'])){

		include 'w_conn.php';
		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		    $sql = "INSERT INTO  lilovalidation (EmpCompID,EmpGP) VALUES (:cid,:gper)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':cid', $_SESSION['CompID']);
		   $stmt->bindParam(':gper', $_POST['gperiod']);
		    $stmt->execute();
	}
	if (isset($_GET['otfsm'])){
		if (session_status() === PHP_SESSION_NONE) { session_start(); }
		include 'w_conn.php';
		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }

		    $statement = $pdo->prepare("select * from otfsmaintenance where   compid=:id");
            $statement->bindParam(':id', $_SESSION['CompID']);
            $statement->execute();
		   	$nrow = $statement->rowCount();
		   	if ($nrow>0){
			   	 $sql = "UPDATE otfsmaintenance SET IsBefore=:fbe,NoDaysBefore=:ndfbe,IsAfter=:aft,NoDaysAfter=:ndfaf,IsHoliday=:ish,IsTardy=:ista,IsDay=:dnt WHERE compid=:cid";
			   $stmt = $pdo->prepare($sql);
			   $stmt->bindParam(':cid', $_SESSION['CompID']);
			   $stmt->bindParam(':fbe', $_POST['fbefore']);
			   $stmt->bindParam(':ndfbe', $_POST['noofdaysbef']);
			   $stmt->bindParam(':aft', $_POST['fafter']);
			   $stmt->bindParam(':ndfaf', $_POST['noofdaysaft']);
			   $stmt->bindParam(':ish', $_POST['isholiday']);
			   $stmt->bindParam(':ista', $_POST['istardy']);
			   $stmt->bindParam(':dnt', $_POST['dnotallowed']);
			   $stmt->execute();

		   }else{
		   $sql = "INSERT INTO  otfsmaintenance (compid,IsBefore,NoDaysBefore,IsAfter,NoDaysAfter,IsHoliday,IsTardy,IsDay) VALUES (:cid,:fbe,:ndfbe,:aft,:ndfaf,:ish,:ista,:dnt)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':cid', $_SESSION['CompID']);
		   $stmt->bindParam(':fbe', $_POST['fbefore']);
		   $stmt->bindParam(':ndfbe', $_POST['noofdaysbef']);
		   $stmt->bindParam(':aft', $_POST['fafter']);
		   $stmt->bindParam(':ndfaf', $_POST['noofdaysaft']);
		   $stmt->bindParam(':ish', $_POST['isholiday']);
		   $stmt->bindParam(':ista', $_POST['istardy']);
		   $stmt->bindParam(':dnt', $_POST['dnotallowed']);
		   $stmt->execute(); 
		}
	}
	if (isset($_GET['addholidaylogger'])){
		include 'w_conn.php';
		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }

		    $sql = "INSERT INTO  holidays (Hdate,Htype,Hdescription,HCompID,HOffsetEmpID) VALUES (:hld,:hlt,:dsc,:cid,:id)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':hld', $_POST['hldate']);
		   $stmt->bindParam(':hlt', $_POST['hldtype']);
		   $stmt->bindParam(':dsc', $_POST['desc']);
		   $stmt->bindParam(':cid', $_SESSION['CompID']);
		   $stmt->bindParam(':id', $_SESSION['id']);
		   $stmt->execute(); 
	}
	if (isset($_GET['typeofleave'])){
		include 'w_conn.php';
		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		   $sql = "INSERT INTO leaves (LeaveDesc) VALUES (:ln)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':ln', $_POST['lname']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Type of Leaves";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['agency'])){
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
		$sql = "INSERT INTO agency (AgencyID,AgencyName,IsActive) VALUES (:id,:agename,:agestat)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id', $_POST['agencyno']);
		   $stmt->bindParam(':agename', $_POST['agencyname']);
		   $stmt->bindParam(':agestat', $_POST['agencystatus']);
		   $stmt->execute(); 

		      $id=$_SESSION['id'];
		     $ch="Maintenance : Added Agency";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['company'])){
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
		   $ex = $_GET['company'];
		   $lgpath="assets/images/logos/" . $_POST['compno'] . "." . $ex;
			$sql = "INSERT INTO companies (CompanyID,CompanyDesc,logopath,comcolor,compcode) VALUES (:id,:agename,:pth,:comcol,:compcode)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id', $_POST['compno']);
		   $stmt->bindParam(':agename', $_POST['compname']);
		   $stmt->bindParam(':pth', $lgpath);
		   $stmt->bindParam(':comcol', $_POST['comcolor']);
		   $stmt->bindParam(':compcode', $_POST['comcode']);
		   $stmt->execute(); 

		   $sql = "INSERT INTO  otfsmaintenance (compid) VALUES (:cid)";
		   $stmt2 = $pdo->prepare($sql);
		   $stmt2->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Company";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['department'])){
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
		$sql = "INSERT INTO departments (DepartmentDesc,CompID) VALUES (:dep,:comid)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':dep', $_POST['depname']);
		   $stmt->bindParam(':comid', $_POST['compid']);
		   $stmt->execute(); 
		  

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Department";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['hmo'])){
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
		$sql = "INSERT INTO hmo (HMO_ID,HMO_PROVIDER) VALUES (:hid, :hname)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':hid', $_POST['hmoid']);
		   $stmt->bindParam(':hname', $_POST['hmoname']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added HMO";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['employeestatus'])){
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
		$sql = "INSERT INTO estatus (StatusEmpDesc) VALUES (:stat)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':stat', $_POST['empstat']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Employee Status";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['joblevel'])){
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
		      $statement = $pdo->prepare("select * from joblevel where jobLevelID=:id and CompanyID=:cid");
                          $statement->bindParam(':id', $_POST['jid']);
                          $statement->bindParam(':cid', $_POST['empcompany']);
                          $statement->execute();
                          $nrow = $statement->rowCount();
		   if ($nrow>0){
		   		echo 1;
		   }else{
		   			$sql = "INSERT INTO joblevel (jobLevelID,jobLevelDesc,CompanyID) VALUES (:code, :lvl, :empcid)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':code', $_POST['jid']);
		   $stmt->bindParam(':lvl', $_POST['joblevel']);
		   $stmt->bindParam(':empcid', $_POST['empcompany']);
		   $stmt->execute();
				echo 2;

				 $id=$_SESSION['id'];
		     $ch="Maintenance : Added Job Level";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
		   }

	
	}
	if (isset($_GET['position'])){
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
		$sql = "INSERT INTO positions (positionDesc,DepartmentID,EmpJobLevelID) VALUES (:pos1, :dep1,:jbl)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':pos1', $_POST['pos']);
		   $stmt->bindParam(':dep1', $_POST['dep']);
		   $stmt->bindParam(':jbl', $_POST['joblevel']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Position";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	// SIL LOAN
	if (isset($_GET['silloan'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
			 date_default_timezone_set("Asia/Manila"); 
		    $todaysil =date("Y-m-d");
		// insert into dars
		$sql = "INSERT INTO silloan (loanEmpID,loanAmount,LoanType,loanStatus,Date) VALUES (:lid,:lam,:lty,:lsta,:dt)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':lid', $_POST['empid']);
		   $stmt->bindParam(':lam', $_POST['loanam']);
		   $stmt->bindParam(':lty', $_POST['ltype']);
		   $stmt->bindParam(':lsta', $_POST['lsta']);
		   $stmt->bindParam(':dt', $todaysil);
		   $stmt->execute(); 
		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added SIL LOAN";
			 $today2 =date("Y-m-d H:i:s");
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->bindParam(':tdy', $today2);
		   $stmt->execute(); 
	}
	// family parental familyparental
	if (isset($_GET['familyparental'])){
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
		$sql = "INSERT INTO parentalrel (EmpID,DateofBirth,Name) VALUES (:eid,:dob,:nm)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':eid', $_POST['empname']);
		   $stmt->bindParam(':dob', $_POST['dob']);
		   $stmt->bindParam(':nm', $_POST['nameoffamily']);
		   $stmt->execute(); 
		   $id=$_SESSION['id'];
		  // $ch="Maintenance : Added Family Details for Parental";
		   date_default_timezone_set("Asia/Manila");
		   $today2 =date("Y-m-d H:i:s");
			 
		// insert into dars
		   $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->bindParam(':tdy', $today2);
		   $stmt->execute(); 
	}
		// end 
	//sss
	if (isset($_GET['sss'])){
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
		$sql = "INSERT INTO sss (sssc,SalaryFrom,SalaryTo,SSER,SSEE,SSEC) VALUES (:txtssc,:sfrom,:sto,:ser,:see,:sec)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':txtssc', $_POST['SSSC']);
		   $stmt->bindParam(':sfrom', $_POST['salaryfrom']);
		   $stmt->bindParam(':sto', $_POST['salaryto']);
		   $stmt->bindParam(':ser', $_POST['SSER']);
		   $stmt->bindParam(':see', $_POST['SSEE']);
		   $stmt->bindParam(':sec', $_POST['SSEC']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added SSS";
			 date_default_timezone_set("Asia/Manila"); 
			 $today2 =date("Y-m-d H:i:s");
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->bindParam(':tdy', $today2);
		   $stmt->execute(); 
	}

	// end sss

	// pag ibig
	if (isset($_GET['pagibig'])){
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
		$sql = "INSERT INTO pagibig (EE,ER) VALUES (:ee,:er)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':ee', $_POST['ee']);
		   $stmt->bindParam(':er', $_POST['er']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Pagibig ";
			 date_default_timezone_set("Asia/Manila"); 
			 $today2 =date("Y-m-d H:i:s");
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->bindParam(':tdy', $today2);
		   $stmt->execute(); 
	}

	// end pagibig
	// philhealth
	if (isset($_GET['philhealth'])){
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
		$sql = "INSERT INTO philhealth (PHSB,SalaryFrom,SalaryTo,PHEE,PHER) VALUES (:PHSB,:SalaryFrom,:SalaryTo,:PHEE,:PHER)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':PHSB', $_POST['PHSB']);
		   $stmt->bindParam(':SalaryFrom', $_POST['SalaryFrom']);
		   $stmt->bindParam(':SalaryTo', $_POST['SalaryTo']);
		   $stmt->bindParam(':PHEE', $_POST['PHEE']);
		   $stmt->bindParam(':PHER', $_POST['PHER']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added philhealth ";
			 date_default_timezone_set("Asia/Manila"); 
			 $today2 =date("Y-m-d H:i:s");
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:tdy)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->bindParam(':tdy', $today2);
		   $stmt->execute(); 
	}
	// end of philhealth
	if (isset($_GET['relation'])){
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
		$sql = "INSERT INTO frelationship (FRelDesc) VALUES (:rell)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':rell', $_POST['relname']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Family Relationship";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['classification'])){
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
		$sql = "INSERT INTO empstatus (EmpCODE,EmpStatDesc) VALUES (:clcode,:cldesc)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':clcode', $_POST['classcode']);
		   $stmt->bindParam(':cldesc', $_POST['classdesc']);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added classification";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['worktime'])){
		include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		// insert sched
		   $date = $_POST['timefrom']; 
			$tfr = date('h:i A', strtotime($date));
			 $date = $_POST['timeto']; 
			$tto = date('h:i A', strtotime($date));
			$tc = $_POST['tcross']; 
		$sql = "INSERT INTO workschedule (TimeFrom,Timeto,TimeCross) VALUES (:tfrom,:tto,:tc)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':tfrom', $tfr);
		   $stmt->bindParam(':tto', $tto);
		    $stmt->bindParam(':tc', $tc);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Work Shift";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
	if (isset($_GET['workday'])){
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
		$sql = "INSERT INTO workdays (WDesc) VALUES (:dtfrt)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':dtfrt', $tfr);
		   $stmt->execute(); 

		    $id=$_SESSION['id'];
		     $ch="Maintenance : Added Work Day";
		// insert into dars
		    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
	}
		if (isset($_GET['userrole'])){
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
		$rid=$_REQUEST['usrole'];
		$q=$_REQUEST['empid'];
		$sql = "UPDATE empdetails SET EmpRoleID='$rid' WHERE EmpID='$q'";
		   $stmt = $pdo->prepare($sql);
		   $stmt->execute(); 

		   //changing access rights

		   $id=$_SESSION['id'];
		   $ch="Maintenance : Added User ROle";
		// insert into dars
		   $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
		}
		if (isset($_GET['addtypeofleave'])){
      	include 'w_conn.php';

		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
		    $c=$_REQUEST['compid'];
		 	$li=$_REQUEST['ltype'];
		 	$lc=$_REQUEST['credits'];
		  	$sql = "SELECT * FROM leaves_validation where  compid=:cid and lid=:lid";
		   	$stmt = $pdo->prepare($sql);
		   	$stmt->bindParam(':cid', $c);
		   	$stmt->bindParam(':lid', $li);
		   	$stmt->execute(); 
		   	$rcnt=$stmt->rowCount();
		 
		   	if ($rcnt>0){
		   		echo 1;
		   	}else{
		   			// insert into typeofleave
	
			$sql = "INSERT INTO leaves_validation (compid,lid,leave_credits) VALUES(:cid,:lid,:lcr)";
		   	$stmt = $pdo->prepare($sql);
		   	$stmt->bindParam(':cid', $c);
		   	$stmt->bindParam(':lid', $li);
		   	$stmt->bindParam(':lcr', $lc);
		   	$stmt->execute(); 

		   	$id=$_SESSION['id'];
		    $ch="Maintenance : Added Leaves Validation";
			// insert into dars
		   $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
		   $stmt = $pdo->prepare($sql);
		   $stmt->bindParam(':id' , $id);
		   $stmt->bindParam(':empact', $ch);
		   $stmt->execute(); 
		   	}

	
   		
   		}

   		if (isset($_GET['citizenship'])){
   			    	include 'w_conn.php';
   			 		$sql2=mysqli_query($con, "select DISTINCT EmpCitezen from empprofiles WHERE EmpCitezen like '" . $_REQUEST['data'] . "%'");
                            while ($res1=mysqli_fetch_array($sql2)) {
                        ?>
                         <a class="ctzn-a"><?php echo $res1['EmpCitezen']; ?></a>
                        <?php
                            }
   		}


   		if (isset($_GET['religion'])){
   			    	include 'w_conn.php';
   			 		$sql2=mysqli_query($con, "select DISTINCT EmpReligion from empprofiles WHERE EmpReligion like '" . $_REQUEST['data'] . "%'");
                            while ($res1=mysqli_fetch_array($sql2)) {
                        ?>
                         <a class="rel-a"><?php echo $res1['EmpReligion']; ?></a>
                        <?php
                            }
   		}
   		if (isset($_GET['newempPosition'])){
   				include 'w_conn.php';
   			
				try{
				$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				   }
				catch(PDOException $e)
				   {
				die("ERROR: Could not connect. " . $e->getMessage());
				   }

				$sql = "select * from positions where PositionDesc=:pst and DepartmentID=:dpi and EmpJobLevelID=:jbl";
			   	$stmt = $pdo->prepare($sql);
			   
			   	$stmt->bindParam(':pst', $_REQUEST['pos']);
			   	$stmt->bindParam(':dpi', $_REQUEST['dep']);
			   	$stmt->bindParam(':jbl', $_REQUEST['joblevel']);
			   	$stmt->execute(); 
			    $nrow = $stmt->rowCount();
			    if ($nrow>0){
			    	echo  1;
			    }
			    else{
			    		$sql = "INSERT INTO positions (PositionDesc,DepartmentID,EmpJobLevelID) VALUES(:pst,:dpi,:jbl)";
					   	$stmt = $pdo->prepare($sql);
					   	$stmt->bindParam(':pst', $_REQUEST['pos']);
					   	$stmt->bindParam(':dpi', $_REQUEST['dep']);
					   	$stmt->bindParam(':jbl', $_REQUEST['joblevel']);
					   	$stmt->execute(); 

					    $id=$_SESSION['id'];
					     $ch="Maintenance : Added Position";
					// insert into dars
					    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
					   $stmt = $pdo->prepare($sql);
					   $stmt->bindParam(':id' , $id);
					   $stmt->bindParam(':empact', $ch);
					   $stmt->execute(); 

					   	$sql = "select * from positions where PositionDesc=:pst and DepartmentID=:dpi and EmpJobLevelID=:jbl";
					   	$stmt2 = $pdo->prepare($sql);
					   
					   	$stmt2->bindParam(':pst', $_REQUEST['pos']);
					   	$stmt2->bindParam(':dpi', $_REQUEST['dep']);
					   	$stmt2->bindParam(':jbl', $_REQUEST['joblevel']);
					   	$stmt2->execute(); 
					   	$row2 = $stmt2->fetch();

					   	$sql = "select * from positions where PSID<>:dpi";
					   	$stmt = $pdo->prepare($sql);
					   	$stmt->bindParam(':dpi', $row2['PSID']);
					   	$stmt->execute(); 

					   	?>
					   		<option class="pos<?php echo $_REQUEST['dep']; ?>" value="<?php echo $row2['PSID']; ?>"><?php echo $_REQUEST['pos']; ?>  </option>
					   	<?php
					   	while($row = $stmt->fetch()){
					   		?>
					   			<option class="pos<?php echo $row['DepartmentID']; ?>" value="<?php echo $row['PSID']; ?>"><?php echo $row['PositionDesc']; ?>  </option>
					   		<?php
					   	}

			    }
   		}
   		if (isset($_GET['newempDepartment'])){

   				include 'w_conn.php';
   			
				try{
				$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				   }
				catch(PDOException $e)
				   {
				die("ERROR: Could not connect. " . $e->getMessage());
				   }

				     	$sql = "select * from departments where DepartmentDesc=:dep and CompID=:comid";
					$stmt = $pdo->prepare($sql);
					$stmt->bindParam(':dep', $_POST['depname']);
					$stmt->bindParam(':comid', $_POST['compid']);
					$stmt->execute(); 
					 $nrow = $stmt->rowCount();
					 if ($nrow>0){
					 		echo 1;
					 }else{
					 		$sql = "INSERT INTO departments (DepartmentDesc,CompID) VALUES (:dep,:comid)";
							$stmt = $pdo->prepare($sql);
							$stmt->bindParam(':dep', $_POST['depname']);
							$stmt->bindParam(':comid', $_POST['compid']);
							$stmt->execute(); 
							 $id=$_SESSION['id'];
						     $ch="Maintenance : Added Department";
						// insert into dars
						    $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
						   $stmt = $pdo->prepare($sql);
						   $stmt->bindParam(':id' , $id);
						   $stmt->bindParam(':empact', $ch);
						   $stmt->execute(); 

                                       try{
                                include 'w_conn.php';
                                      $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                                      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                         }
                                      catch(PDOException $e)
                                         {
                                      die("ERROR: Could not connect. " . $e->getMessage());
                                         }
                                           if ($_SESSION['UserType']==1){
                                            $statement = $pdo->prepare("select * from departments inner join companies where departments.CompID=companies.CompanyID");
                                            echo "<option></option>";
                                          }else if($_SESSION['UserType']==2){
                                              $statement = $pdo->prepare("select * from departments inner join companies where departments.CompID=companies.CompanyID");
                                          }
                                      $statement->execute();
                                      while ($row = $statement->fetch()){
                                      ?>
                                        <option class="dep<?php echo $row['CompanyID']; ?>" value="<?php echo $row['DepartmentID']; ?>"><?php echo $row['DepartmentDesc']; ?></option>
                                      <?php
                                      }
                                    
					 }

				


   		}
?>