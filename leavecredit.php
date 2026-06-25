<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ 
    if(!isset($_COOKIE["WeDoID"])) {

        header ('location: login'); 
    }else{
        if(!isset($_COOKIE["WeDoID"])) {
          session_destroy();
          header ('location: login'); 
        }else{
              try{
              include 'w_conn.php';
              $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                 }
              catch(PDOException $e)
                 {
              die("ERROR: Could not connect. " . $e->getMessage());
                 }
              $statement = $pdo->prepare("select * from empdetails");
              $statement->execute();    

              while ($row=$statement->fetch()) {
                if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])){
                        $_SESSION['id']=$row['EmpID'];
                
                        $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                        $statement->bindParam(':un' , $_SESSION['id']);
                        $statement->execute(); 
                        $count=$statement->rowCount();
                        $row=$statement->fetch();
                        $hash = $row['EmpPW'];
                        $_SESSION['UserType']=$row['EmpRoleID'];
                        $cid=$row['EmpCompID'];
                        $_SESSION['CompID']=$row['EmpCompID'];
                        $_SESSION['EmpISID']=$row['EmpISID'];
                        $statement = $pdo->prepare("select * from companies where CompanyID = :pw");
                        $statement->bindParam(':pw' , $cid);
                        $statement->execute(); 
                        $comcount=$statement->rowCount();
                        $row=$statement->fetch();
                        if ($comcount>0){
                          $_SESSION['CompanyName']=$row['CompanyDesc'];
                          $_SESSION['CompanyLogo']=$row['logopath'];
                          $_SESSION['CompanyColor']=$row['comcolor'];
                        }else{
                          $_SESSION['CompanyName']="ADMIN";
                          $_SESSION['CompanyLogo']="";
                          $_SESSION['CompanyColor']="red";
                        }
                         $_SESSION['PassHash']=$hash;

                }
                else{

                }
              }
            }
    }

  }
//   if ($_SESSION['UserType']<>1){
//   	header('location: index.php');
//   }
?>
<?php
	date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html>
<head>
	<title>Leave Credit</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
      <script src="assets/js/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script><!-- 
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script> -->
      <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <!--   <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script> -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="assets/js/script.js"></script>
	<script src="assets/js/script-reports.js"></script>
	<script type="text/javascript" src="assets/js/script-modules.js"></script>
	<link rel="stylesheet" type="text/css" href="assets/css/style.css">
  	<link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
	<script>
	 function FunctionChangedate(){
              var d = new Date();
              var n = d.getFullYear();
              var x = document.getElementById("lstarts").max = n + "-12-31" ;
              var x = document.getElementById("lenddate").max = n + "-12-31" ;
              var x = document.getElementById("lstarts").min = n + "-01-01" ;
              var x = document.getElementById("lenddate").min = n + "-01-01" ;
        }
	</script>
</head>
<body style="background-image: none">
	<?php
		include 'includes/header.php';
	?>
	<div class="w-container">
    <div class="row">
        <div class="col-lg-3"></div>
        <div class="col-lg-9 module-content">
            <h2 class="fw-bold text-teal">Leave Credits</h2>
            <h4 class="text-muted mb-4">as of <?php echo date("F d, Y"); ?></h4>
            
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Employee Name</th>
                        <th>Used Credit</th>
                        <th>Current Credit Earned</th>
                        <th>Remaining Credit</th>
                        <th class="text-center">View Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        include 'w_conn.php';
                        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // OPTIMIZED: One query to rule them all
                        $sql = "SELECT b.EmpID, b.EmpLN, b.EmpFN, a.EmpDOR, c.CT, c.CTH 
                                FROM employees as b 
                                INNER JOIN empdetails as a ON a.EmpID = b.EmpID 
                                INNER JOIN credit as c ON b.EmpID = c.EmpID 
                                WHERE b.EmpStatusID = 1 
                                -- WHERE a.EmpDOR IS NOT NULL AND b.EmpStatusID = 1 
                                ORDER BY b.EmpLN ASC";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                        $currentYear = date("Y");

                        while ($row = $stmt->fetch()) {
                            $id = $row['EmpID'];
                            $cth = $row['CTH']; // Total per year (e.g. 15)
                            $ct = $row['CT'];   // Currently set credit
                            $dor = $row['EmpDOR'];
                            $cdPerDay =0;
                             $daysActive=0;
                             $specialCredit = 0;
                            
                            
                            $usedCredit = $cth - $ct;
                            $creditEarned = $ct; // Default

                            // Specialized Calculation for specific ID or general Pro-rating
                            if ($id == "WeDoinc-0145" ) {
								if(is_null($dor)){
									 $creditEarned = "Missing Regularization Date";
								}else{
                                    // 	 $hireYear = date("Y", strtotime($dor));
                                    
                                    // 	// Set calculation start: Jan 1 of current year OR Hire Date if hired this year
                                    // 	$calcStart = ($hireYear < $currentYear) 
                                    // 		? date_create("1/1/" . $currentYear) 
                                    // 		: date_create($dor);
                                        
                                    // 	$dateNow = date_create(date("Y-m-d"));
                                    // 	$daysInYear = date_diff(date_create("1/1/".$currentYear), date_create("1/1/".($currentYear+1)))->format("%a");
                                        
                                    // 	$cdPerDay = $cth / $daysInYear;
                                    // 	$daysActive = date_diff($calcStart, $dateNow)->format("%a");
                                        
                                    // 	$calculated = ($cdPerDay * $daysActive) - $usedCredit + 4; // +4 as bonus
                                    // 	$creditEarned = number_format($calculated, 4, '.', '');

                                    //                             //get here the total use credits for emergency leave this year
                                    //                             $currentYear = date('Y');
                                    //                                 $localCount = 0;
                                                                    
                                    //                             // 1. Get how many days have ALREADY been used this year before this loop
                                    $totalApprovedCount = 0;
                                    // if ($leaveType == 24) {
                                        $sqlCount = "SELECT COUNT(*) FROM hleavesbd hb 
                                                    JOIN hleaves h ON hb.FID = h.LeaveID 
                                                    WHERE h.EmpID = :empid 
                                                    AND h.LType = 24 
                                                    AND hb.LStatus = 4 
                                                    AND YEAR(hb.LStart) = :year";
                                        $stmtCount = $pdo->prepare($sqlCount);
                                        $stmtCount->execute([':empid' => $id, ':year' => $currentYear]);
                                        $totalApprovedCount = (int)$stmtCount->fetchColumn();

                                         $usedCredit += $totalApprovedCount;
                                         
                                    // }
                                    
                                    $currentYear = date("Y");
                                    $hireYear    = date("Y", strtotime($dor));
                                    $dateNow     = date_create(date("Y-m-d"));
                                    
                                    // 1. Kunin ang total minutes mula sa DB
                                    $sqlSum = "SELECT SUM(hb.LDuration) FROM hleavesbd hb 
                                               JOIN hleaves h ON hb.FID = h.LeaveID 
                                               WHERE h.EmpID = :empid 
                                                
                                               AND hb.LStatus = 4 
                                               AND YEAR(hb.LStart) = :year";
                                    $stmtSum = $pdo->prepare($sqlSum);
                                    $stmtSum->execute([':empid' => $id, ':year' => $currentYear]);
                                    $totalMinutesUsed = (float)$stmtSum->fetchColumn() ?: 0;
                                    
                                    // 2. I-convert ang minutes sa days (Base sa policy mo na 600 mins = 1 day)
                                    $totalUsedInDays = $totalMinutesUsed / 600;
                                    
                                    // 3. Set Start Date (Jan 1 o Hire Date)
                                    $calcStart = ($hireYear < $currentYear) 
                                        ? date_create("1/1/" . $currentYear) 
                                        : date_create($dor);
                                    
                                    // 4. Daily Accrual Calculation
                                    $dateJan1     = date_create("1/1/" . $currentYear);
                                    $dateNextJan1 = date_create("1/1/" . ($currentYear + 1));
                                    $daysInYear   = date_diff($dateJan1, $dateNextJan1)->format("%a");
                                    
                                    $cdPerDay   = $cth / $daysInYear;
                                    $daysActive = date_diff($calcStart, $dateNow)->format("%a");
                                    
                                    // 5. Final Math: (Earned + 4 Bonus) - Used Days
                                    // Dito natin ibabawas yung converted days (e.g., 300 mins = 0.5 days)
                                    $calculated = (($cdPerDay * $daysActive) + 4) - $totalUsedInDays;
                                    
                                    // 6. Safety Floor (Zero protection)
                                    // $finalBalance = max(0, $calculated);
                                    $finalBalance = max(0, $calculated);
                                    
                                    // 7. Formatting
                                    $creditEarned = number_format($finalBalance, 4, '.', '');

                                    
								}
                               $cth = $cth - ($cdPerDay * $daysActive);
                            } else{
                                $cth= $cth - $usedCredit;
                            }
                            
                            if (is_numeric($creditEarned)) {
                                $remaining = number_format($creditEarned, 4, '.', '');
                            } else {
                              
                                $remaining = number_format(0, 4, '.', '');
                            }
                            ?>

                            <tr>
                                <td class="fw-bold"><?php echo strtoupper($row['EmpLN']) . ", " . $row['EmpFN']; ?></td>
                                <td class="text-danger"><?php echo number_format($usedCredit, 2); ?></td>
                                <td class="text-primary"><?php echo $creditEarned;  ?></td>
								
                                <!--<td class="fw-bold text-success"><?php echo number_format($remaining, 4); ?></td>-->
                                <td class="fw-bold text-success"><?php echo number_format(($cth), 4); ?></td>

                                <td class="text-center">
                                    <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#myModal<?php echo $id; ?>">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </td>
                            </tr>

                            <div class="modal fade" id="myModal<?php echo $id; ?>" role="dialog">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header" style="background-color: <?php echo $_SESSION['CompanyColor']; ?>; color: white;">
                                            <h5 class="modal-title">Leave History: <?php echo $row['EmpFN']; ?></h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="list-group">
                                                <?php
                                                $lSql = "SELECT h.LStart, l.LeaveDesc FROM hleavesbd h 
                                                         INNER JOIN leaves l ON h.LType = l.LeaveID 
                                                         WHERE h.EmpID = :id AND YEAR(h.LStart) = :yr AND h.LStatus = 4";
                                                $lStmt = $pdo->prepare($lSql);
                                                $lStmt->execute([':id' => $id, ':yr' => $currentYear]);
                                                
                                                if($lStmt->rowCount() > 0) {
                                                    while($lRow = $lStmt->fetch()) {
                                                        echo "<div class='list-group-item d-flex justify-content-between'>
                                                                <span>".date("M d, Y", strtotime($lRow['LStart']))."</span>
                                                                <span class='badge bg-info'>".$lRow['LeaveDesc']."</span>
                                                              </div>";
                                                    }
                                                } else {
                                                    echo "<p class='text-center text-muted'>No leave logs found for this year.</p>";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php } 
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='5'>Connection Error: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>