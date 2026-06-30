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
<html lang="en">

<head>
	<title>Leave Credit Viewer</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<!-- Functional libs (Bootstrap modals + existing module JS) -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

	<!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
	<link rel="stylesheet" href="assets/css/wedo-theme.css">

	<script type="text/javascript" src="assets/js/script.js"></script>

	<!-- Print + Excel export (table only; hooks: #lcprint-btn #lcprint #lctab .captionText) -->
	<script type="text/javascript">
		$(document).ready(function () {
			$(document).on('click', '#lcprint-btn', function () {
				var css = '@page { size: landscape; }',
					head = document.head || document.getElementsByTagName('head')[0],
					style = document.createElement('style');
				style.type = 'text/css';
				style.media = 'print';
				if (style.styleSheet) { style.styleSheet.cssText = css; }
				else { style.appendChild(document.createTextNode(css)); }
				head.appendChild(style);

				var originalContents = document.body.innerHTML;
				$(".captionText").removeClass("d-none").addClass("d-block");
				var printContents = document.getElementById('lcprint').innerHTML;
				document.body.innerHTML = printContents;
				window.print();
				document.body.innerHTML = originalContents;
				$(".captionText").removeClass("d-block").addClass("d-none");
			});
		});

		function exportLeaveCredit() {
			var dataFileType = 'application/vnd.ms-excel';
			var table = document.getElementById('lctab').cloneNode(true);
			// drop the "View Details" action column from the spreadsheet
			table.querySelectorAll('.lc-actioncol').forEach(function (el) { el.parentNode.removeChild(el); });
			var tableHTMLData = table.outerHTML.replace(/ /g, '%20');
			var filename = 'LeaveCredits_<?php echo date("Y-m-d"); ?>.xls';

			var a = document.createElement("a");
			document.body.appendChild(a);
			if (navigator.msSaveOrOpenBlob) {
				var blob = new Blob(['﻿', tableHTMLData], { type: dataFileType });
				navigator.msSaveOrOpenBlob(blob, filename);
			} else {
				a.href = 'data:' + dataFileType + ', ' + tableHTMLData;
				a.download = filename;
				a.click();
			}
		}
	</script>

	<!-- Styles kept in <head> so the @media print rules survive the print
	     handler's document.body.innerHTML swap (a body <style> would be wiped). -->
	<style>
		/* Leave-history list inside the per-employee modal */
		.wd-historylist { display: flex; flex-direction: column; gap: 8px; }
		.wd-historyrow {
			display: flex; align-items: center; justify-content: space-between; gap: 10px;
			padding: 9px 12px; border: 1px solid var(--border); border-radius: var(--radius);
			background: var(--surface-2); color: var(--text-2); font-size: 13px;
		}

		/* Print caption helpers (BS4 d-none/d-block aren't in BS3) */
		.d-none { display: none !important; }
		.d-block { display: block !important; }
		.captionText { font-weight: 600; color: var(--text); margin: 2px 0; }
		.wd-card__foot { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--border); flex-wrap: wrap; }

		@media print {
			.captionText { display: block !important; }
			/* the on-screen body scrolls inside .wd-tablewrap (max-height:40vh) —
			   un-clip it for print so ALL rows render, not just the visible window */
			.wd-tablewrap { max-height: none !important; overflow: visible !important; border: 0 !important; }
			.wd-table td, .wd-table th { white-space: normal; }
			.lc-actioncol { display: none !important; }
			/* force pill backgrounds/colours to actually print */
			* { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
		}
	</style>
</head>

<body>
	<?php $wd_active = 'leavecredit'; include 'includes/wd-header.php'; ?>

	<div class="wd-pagehead">
		<div>
			<h1>Leave Credits</h1>
			<p>Earned, used and remaining leave credits &mdash; as of <?php echo date("F d, Y"); ?>.</p>
		</div>
	</div>

	<section class="wd-card">
		<div class="wd-card__head">
			<h3>Employee leave credits</h3>
		</div>
		<div id="lcprint">
			<label class="captionText d-none" id="lcCaptionMain">Leave Credits</label>
			<label class="captionText d-none" id="lcCaptionDate">As of <?php echo date("F d, Y"); ?></label>
			<div class="wd-tablewrap">
			<table class="wd-table" id="lctab">
				<thead>
					<tr>
						<th>Employee Name</th>
						<th>Used Credit</th>
						<th>Current Credit Earned</th>
						<th>Remaining Credit</th>
						<th class="lc-actioncol" style="text-align:center">View Details</th>
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
								<td><b><?php echo htmlspecialchars(strtoupper($row['EmpLN']) . ", " . $row['EmpFN']); ?></b></td>
								<td><span style="color:var(--danger-text);font-weight:600"><?php echo number_format($usedCredit, 2); ?></span></td>
								<td><span style="color:var(--brand-700);font-weight:600"><?php echo htmlspecialchars($creditEarned); ?></span></td>
								<td><span class="wd-pill wd-pill--ok"><?php echo number_format(($cth), 4); ?></span></td>
								<td class="lc-actioncol" style="text-align:center">
									<button type="button" class="wd-iconbtn" style="width:32px;height:32px;font-size:14px" data-toggle="modal" data-target="#myModal<?php echo $id; ?>" title="View leave history">
										<i class="fa-solid fa-eye"></i>
									</button>
								</td>
							</tr>

							<div class="modal" id="myModal<?php echo $id; ?>" role="dialog">
								<div class="modal-dialog">
									<div class="modal-content">
										<div class="modal-header" style="background-color:#f93627;color:#fff">
											<button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
											<h4 class="modal-title">Leave History: <?php echo htmlspecialchars($row['EmpFN']); ?></h4>
										</div>
										<div class="modal-body">
											<div class="wd-historylist">
												<?php
												$lSql = "SELECT h.LStart, l.LeaveDesc FROM hleavesbd h
														 INNER JOIN leaves l ON h.LType = l.LeaveID
														 WHERE h.EmpID = :id AND YEAR(h.LStart) = :yr AND h.LStatus = 4";
												$lStmt = $pdo->prepare($lSql);
												$lStmt->execute([':id' => $id, ':yr' => $currentYear]);

												if($lStmt->rowCount() > 0) {
													while($lRow = $lStmt->fetch()) {
														echo "<div class='wd-historyrow'>
																<span>".date("M d, Y", strtotime($lRow['LStart']))."</span>
																<span class='wd-pill wd-pill--info'>".htmlspecialchars($lRow['LeaveDesc'])."</span>
															  </div>";
													}
												} else {
													echo "<p style='text-align:center;color:var(--text-3)'>No leave logs found for this year.</p>";
												}
												?>
											</div>
										</div>
										<div class="modal-footer">
											<button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
										</div>
									</div>
								</div>
							</div>

						<?php }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='5'>Connection Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                    }
                    ?>
				</tbody>
			</table>
			</div>
		</div>
		<div class="wd-card__foot">
			<button class="wd-btn wd-btn--ghost" id="lcprint-btn" type="button"><i class="fa-solid fa-print"></i> Print</button>
			<button class="wd-btn wd-btn--primary" type="button" onclick="exportLeaveCredit()"><i class="fa-solid fa-file-excel"></i> Export to Excel</button>
		</div>
	</section>

	<?php include 'includes/wd-footer.php'; ?>
</body>

</html>
