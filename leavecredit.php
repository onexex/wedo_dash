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
                if ((!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash']) && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time()))){
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
	date_default_timezone_set('Asia/Manila');
	if (empty($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login'); exit(); }

	/* access gate BEFORE any output so the redirect can fire */
	include 'w_conn.php';
	require_once __DIR__ . '/includes/leave-credit-lib.php';
	try {
		$lcpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
		$lcpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	} catch (PDOException $e) { die("ERROR: Could not connect."); }
	if (!lc_can_view($lcpdo)) { header('location: 404?'); exit(); }

	$lcManage  = lc_can_manage($lcpdo);
	$lcYear    = (int) date('Y');
	$lcStarted = lc_year_record($lcpdo, $lcYear);
	$lcMissing = $lcManage ? lc_missing($lcpdo) : [];
	$lcRows    = (!$lcStarted && $lcManage) ? lc_rows($lcpdo) : [];
	$lcPending = (!$lcStarted && $lcManage) ? lc_pending_before($lcpdo, $lcYear) : 0;
	$lcE = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	$lcN = function ($f) { return rtrim(rtrim(number_format((float) $f, 4, '.', ''), '0'), '.'); };
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

		/* New leave year + edit credit */
		.lc-headactions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
		.lc-headactions .wd-pill { margin-left: 4px; }
		.lc-notice {
			display: flex; gap: 12px; align-items: flex-start; margin: 0 0 16px;
			padding: 12px 16px; border: 1px solid var(--border); border-radius: var(--radius);
			background: var(--surface-2); color: var(--text-2); font-size: 13.5px; line-height: 1.5;
		}
		.lc-notice > i { margin-top: 3px; color: var(--text-3); }
		.lc-notice--warn { border-color: var(--warn); background: var(--warn-bg); }
		.lc-notice--warn > i { color: var(--warn-text); }
		.lc-yearnote { margin: 0 0 16px; color: var(--text-3); font-size: 13px; }
		.lc-yearnote i { color: var(--brand-700); margin-right: 4px; }
		.lc-help { color: var(--text-3); font-size: 13px; margin: 0 0 14px; }
		.lc-error { color: var(--danger-text); font-size: 13px; margin: 10px 0 0; white-space: pre-line; }
		.lc-yeartable { max-height: 55vh; }
		.lc-yeartable .wd-input { padding: 7px 10px; }

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
		<?php if ($lcManage): ?>
		<div class="lc-headactions">
			<?php if ($lcMissing): ?>
			<button type="button" class="wd-btn wd-btn--ghost" id="lcAddBtn"><i class="fa-solid fa-user-plus"></i> Add employee <span class="wd-pill wd-pill--warn"><?php echo count($lcMissing); ?></span></button>
			<?php endif; ?>
			<?php if (!$lcStarted): ?>
			<button type="button" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#lcYearModal"><i class="fa-solid fa-calendar-plus"></i> Start <?php echo $lcYear; ?> leave year</button>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>

	<?php if ($lcManage && !$lcStarted): ?>
	<div class="lc-notice lc-notice--warn">
		<i class="fa-solid fa-triangle-exclamation"></i>
		<div>
			<b>The <?php echo $lcYear; ?> leave year hasn't started.</b>
			Everyone's remaining credit is still what was left from <?php echo $lcYear - 1; ?>.
			Click <b>Start <?php echo $lcYear; ?> leave year</b> to enter each employee's <?php echo $lcYear; ?> leave credit.
		</div>
	</div>
	<?php elseif ($lcManage && $lcStarted): ?>
	<p class="lc-yearnote">
		<i class="fa-solid fa-circle-check"></i>
		<?php if ($lcStarted['started_by'] === 'manual'): ?>
			<?php echo $lcYear; ?> leave credits were set directly in the database. From January <?php echo $lcYear + 1; ?> you can start each year here.
		<?php else: ?>
			The <?php echo $lcYear; ?> leave year was started on <?php echo $lcE(date('F j, Y', strtotime($lcStarted['started_at']))); ?> by <?php echo $lcE($lcStarted['started_name'] ?: $lcStarted['started_by']); ?>.
		<?php endif; ?>
	</p>
	<?php endif; ?>

	<?php if ($lcMissing): ?>
	<div class="lc-notice">
		<i class="fa-solid fa-circle-info"></i>
		<div>
			<b><?php echo count($lcMissing); ?> active <?php echo count($lcMissing) === 1 ? 'employee has' : 'employees have'; ?> no leave credits</b>
			(<?php echo $lcE(implode(', ', array_map(function ($m) { return $m['EmpFN'] . ' ' . $m['EmpLN']; }, $lcMissing))); ?>),
			so their paid leave is approved without pay. Use <b>Add employee</b> to give them a leave credit.
		</div>
	</div>
	<?php endif; ?>

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
						<th class="lc-actioncol" style="text-align:center"><?php echo $lcManage ? 'Actions' : 'View Details'; ?></th>
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
							$rawCT = $row['CT'];
							$rawCTH = $row['CTH'];
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
									<?php if ($lcManage): ?>
									<button type="button" class="wd-iconbtn lc-edit" style="width:32px;height:32px;font-size:14px" title="Edit leave credit"
										data-emp="<?php echo $lcE($id); ?>" data-name="<?php echo $lcE($row['EmpFN'] . ' ' . $row['EmpLN']); ?>"
										data-ct="<?php echo $lcN($rawCT); ?>" data-cth="<?php echo $lcN($rawCTH); ?>">
										<i class="fa-solid fa-pen"></i>
									</button>
									<?php endif; ?>
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

	<?php if ($lcManage): ?>
	<?php if (!$lcStarted): ?>
	<!-- Start the new leave year: HR enters each employee's leave credit for the year -->
	<div class="modal fade" id="lcYearModal" role="dialog">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<h4 class="modal-title">Start <?php echo $lcYear; ?> leave year</h4>
				</div>
				<form id="lcYearForm" autocomplete="off">
				<div class="modal-body">
					<p class="lc-help">
						Enter each employee's <b><?php echo $lcYear; ?> leave credit</b>. Their remaining credit is reset to that number.
						Unused <?php echo $lcYear - 1; ?> credit is <b>not carried over</b>. This can only be done once for <?php echo $lcYear; ?>.
					</p>
					<?php if ($lcPending > 0): ?>
					<div class="lc-notice lc-notice--warn">
						<i class="fa-solid fa-triangle-exclamation"></i>
						<div><b><?php echo $lcPending; ?> leave <?php echo $lcPending === 1 ? 'filing' : 'filings'; ?> dated <?php echo $lcYear - 1; ?> or earlier <?php echo $lcPending === 1 ? 'is' : 'are'; ?> still awaiting approval.</b>
						If <?php echo $lcPending === 1 ? 'it is' : 'they are'; ?> approved after you start <?php echo $lcYear; ?>, the days come out of the <?php echo $lcYear; ?> credit. Approve or decline <?php echo $lcPending === 1 ? 'it' : 'them'; ?> first.</div>
					</div>
					<?php endif; ?>
					<input type="hidden" name="action" value="start_year">
					<input type="hidden" name="year" value="<?php echo $lcYear; ?>">
					<input type="hidden" name="token" value="<?php echo $lcE(lc_csrf_token()); ?>">
					<div class="wd-tablewrap lc-yeartable">
					<table class="wd-table">
						<thead>
							<tr>
								<th>Employee</th>
								<th><?php echo $lcYear - 1; ?> leave credit</th>
								<th>Unused (dropped)</th>
								<th style="width:150px"><?php echo $lcYear; ?> leave credit</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($lcRows as $r): ?>
							<tr>
								<td><b><?php echo $lcE(strtoupper($r['EmpLN']) . ', ' . $r['EmpFN']); ?></b></td>
								<td><?php echo $lcN($r['CTH']); ?></td>
								<td class="wd-muted"><?php echo $lcN($r['CT']); ?></td>
								<td><input type="text" inputmode="decimal" class="wd-input lc-amt" required
									name="credit[<?php echo $lcE($r['EmpID']); ?>]" value="<?php echo $lcN($r['CTH']); ?>"></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					</div>
					<p class="lc-error" id="lcYearErr" hidden></p>
				</div>
				<div class="modal-footer">
					<button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
					<button type="submit" class="wd-btn wd-btn--primary" id="lcYearSave"><i class="fa-solid fa-calendar-plus"></i> Start <?php echo $lcYear; ?></button>
				</div>
				</form>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Edit one employee's leave credit, or add an employee who has none -->
	<div class="modal fade" id="lcEditModal" role="dialog">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<h4 class="modal-title" id="lcEditTitle">Edit leave credit</h4>
				</div>
				<form id="lcEditForm" autocomplete="off">
				<div class="modal-body">
					<input type="hidden" name="action" value="save">
					<input type="hidden" name="token" value="<?php echo $lcE(lc_csrf_token()); ?>">
					<input type="hidden" name="emp" id="lcEmp">
					<div class="wd-field" id="lcPickWrap">
						<label for="lcPick">Employee</label>
						<select class="wd-input" id="lcPick">
							<?php foreach ($lcMissing as $m): ?>
							<option value="<?php echo $lcE($m['EmpID']); ?>"><?php echo $lcE(strtoupper($m['EmpLN']) . ', ' . $m['EmpFN']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wd-field">
						<label for="lcCth"><?php echo $lcYear; ?> leave credit</label>
						<input type="text" inputmode="decimal" class="wd-input" name="cth" id="lcCth" required>
					</div>
					<div class="wd-field">
						<label for="lcCt">Remaining credit</label>
						<input type="text" inputmode="decimal" class="wd-input" name="ct" id="lcCt" required>
						<p class="lc-help" style="margin:6px 0 0">Leave credit minus the days already used this year.</p>
					</div>
					<p class="lc-error" id="lcEditErr" hidden></p>
				</div>
				<div class="modal-footer">
					<button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
					<button type="submit" class="wd-btn wd-btn--primary" id="lcEditSave"><i class="fa-solid fa-floppy-disk"></i> Save</button>
				</div>
				</form>
			</div>
		</div>
	</div>

	<script>
	$(function () {
		function post(form, btn, err) {
			$(err).prop('hidden', true).text('');
			$(btn).prop('disabled', true);
			$.post('query/leavecredit-action.php', $(form).serialize(), null, 'json')
				.done(function () { location.reload(); })
				.fail(function (x) {
					var msg = (x.responseJSON && x.responseJSON.msg) || 'Something went wrong — please try again.';
					$(err).text(msg).prop('hidden', false);
					$(btn).prop('disabled', false);
				});
		}

		$('#lcYearForm').on('submit', function (e) {
			e.preventDefault();
			if (!confirm('Start the <?php echo $lcYear; ?> leave year? Unused <?php echo $lcYear - 1; ?> credits will be dropped. This cannot be undone from the screen.')) { return; }
			post(this, '#lcYearSave', '#lcYearErr');
		});

		// edit an existing row
		$(document).on('click', '.lc-edit', function () {
			var b = $(this);
			$('#lcEditTitle').text('Edit leave credit — ' + b.data('name'));
			$('#lcPickWrap').hide();
			$('#lcEmp').val(b.data('emp'));
			$('#lcCth').val(b.data('cth'));
			$('#lcCt').val(b.data('ct'));
			$('#lcEditErr').prop('hidden', true);
			$('#lcEditModal').modal('show');
		});

		// add an active employee who has no credit row
		$('#lcAddBtn').on('click', function () {
			$('#lcEditTitle').text('Add employee to leave credits');
			$('#lcPickWrap').show();
			$('#lcEmp').val($('#lcPick').val());
			$('#lcCth, #lcCt').val('');
			$('#lcEditErr').prop('hidden', true);
			$('#lcEditModal').modal('show');
		});
		$('#lcPick').on('change', function () { $('#lcEmp').val(this.value); });

		// adding: remaining follows the leave credit until HR types its own value
		$('#lcCth').on('input', function () {
			if ($('#lcPickWrap').is(':visible')) { $('#lcCt').val(this.value); }
		});

		$('#lcEditForm').on('submit', function (e) {
			e.preventDefault();
			post(this, '#lcEditSave', '#lcEditErr');
		});
	});
	</script>
	<?php endif; ?>

	<?php include 'includes/wd-footer.php'; ?>
</body>

</html>
