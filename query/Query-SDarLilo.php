<?php
	if (session_status() === PHP_SESSION_NONE) { session_start(); }
 	if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  	else{ header ('location: login'); }
	if (isset($_GET['dar'])){

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

			$dpt=$_GET['dtt'];
			$dpf=$_GET['dtf'];
			$d2= date('Y-m-d', strtotime($dpt . ' + 1 days'));
			$d1= date('Y-m-d', strtotime($dpf));

			$statement = $pdo->prepare("select * from dars where EmpID = :name and 	DarDateTime BETWEEN :dfr AND :dto order by DarDateTime desc");
			$statement->bindParam(':name' , $id);
			$statement->bindParam(':dfr' , $d1);
			$statement->bindParam(':dto' , $d2);
			$statement->execute();
			if ($statement->rowCount()<1){
					echo "Empty";
			}

			while ($row = $statement->fetch()){
			?>
			  	<tr>
			        <td class="td-dar" width="40%"><?php echo date("F j, Y", strtotime($row['DarDateTime'])); ?></td>
			        <td class="td-dar res-day" width="40%"><?php echo date("l", strtotime($row['DarDateTime'])); ?></td>
			        <td class="td-dar" width="40%"><?php echo date("h:i:s A", strtotime($row['DarDateTime'])); ?></td>            
			        <td class="td-act" width="50%"><?php echo $row['EmpActivity']; ?></td>
			    </tr>
			<?php


		}

	}
		if (isset($_GET['lilo'])){
		      date_default_timezone_set("Asia/Manila"); 
		    	$dpt=$_GET['dtt'];
				$dpf=$_GET['dtf'];
				$dt2= date('Y-m-d', strtotime($dpt));
				$dt1= date('Y-m-d', strtotime($dpf));
		         include '../includes/home-attendancelog.php';	
		}
		
	?>