<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {

    } else {
        if (!isset($_COOKIE["WeDoID"])) {

            header('location: login');
        } else {
            if (!isset($_COOKIE["WeDoID"])) {
                session_destroy();
                header('location: login');
            } else {
                try {
                    include 'w_conn.php';
                    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch (PDOException $e) {
                    die("ERROR: Could not connect. " . $e->getMessage());
                }
                $statement = $pdo->prepare("select * from empdetails");
                $statement->execute();

                while ($row = $statement->fetch()) {
                    if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])) {
                        $_SESSION['id'] = $row['EmpID'];

                        $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                        $statement->bindParam(':un', $_SESSION['id']);
                        $statement->execute();
                        $count                = $statement->rowCount();
                        $row                  = $statement->fetch();
                        $hash                 = $row['EmpPW'];
                        $_SESSION['UserType'] = $row['EmpRoleID'];
                        $cid                  = $row['EmpCompID'];
                        $_SESSION['CompID']   = $row['EmpCompID'];
                        $_SESSION['EmpISID']  = $row['EmpISID'];
                        $gstmt = $pdo->prepare("SELECT EmpGender FROM empprofiles WHERE EmpID = :id");
                        $gstmt->execute([':id' => $_SESSION['id']]);
                        $gender = trim((string) $gstmt->fetchColumn());
                        $_SESSION['gender'] = $gender;
                        $statement            = $pdo->prepare("select * from companies where CompanyID = :pw");
                        $statement->bindParam(':pw', $cid);
                        $statement->execute();
                        $comcount = $statement->rowCount();
                        $row      = $statement->fetch();
                        if ($comcount > 0) {
                            $_SESSION['CompanyName']  = $row['CompanyDesc'];
                            $_SESSION['CompanyLogo']  = $row['logopath'];
                            $_SESSION['CompanyColor'] = $row['comcolor'];
                        } else {
                            $_SESSION['CompanyName']  = "ADMIN";
                            $_SESSION['CompanyLogo']  = "";
                            $_SESSION['CompanyColor'] = "red";
                        }
                        $_SESSION['PassHash'] = $hash;

                    } else {

                    }
                }
            }
        }

    }
?>
<?php
    date_default_timezone_set('Asia/Manila');

    /* Map a status description to a themed pill colour. */
    function wd_status_pill($desc) {
        $d = strtolower((string) $desc);
        if (strpos($d, 'approve') !== false)                                     { $c = 'ok'; }
        elseif (strpos($d, 'reject') !== false || strpos($d, 'cancel') !== false
             || strpos($d, 'disapprove') !== false || strpos($d, 'deny') !== false
             || strpos($d, 'decline') !== false)                                 { $c = 'danger'; }
        elseif (strpos($d, 'pending') !== false || strpos($d, 'file') !== false
             || strpos($d, 'process') !== false || strpos($d, 'review') !== false){ $c = 'warn'; }
        else                                                                     { $c = 'info'; }
        return '<span class="wd-pill wd-pill--' . $c . '">' . htmlspecialchars($desc) . '</span>';
    }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Automated Leave Application System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script src="assets/js/script-reports.js"></script>
    <script type="text/javascript" src="assets/js/script-modules.js"></script>
    <script type="text/javascript" src="assets/js/administrative.js"></script>

    <script>
        function FunctionChangedate() {
            var d = new Date();
            var n = d.getFullYear();
            var x = document.getElementById("lstarts").max = n + "-12-31";
            var x = document.getElementById("lenddate").max = n + "-12-31";
            var x = document.getElementById("lstarts").min = n + "-01-01";
            var x = document.getElementById("lenddate").min = n + "-01-01";
        }

        function checkSubCategory(selectElement) {
            const subCategoryArea = document.getElementById('sub_category_area');
            const subCategorySelect = document.getElementById('leave_subcategory');

            // Get the text of the selected option
            const selectedText = selectElement.options[selectElement.selectedIndex].text.toUpperCase();

            // If the selection contains the word "VACATION", show the second tier
            if (selectedText.includes("SIL VACATION") || selectedText.includes("VACATION")) {
                subCategoryArea.style.display = 'block';
                subCategorySelect.setAttribute('required', 'required');
            } else {
                subCategoryArea.style.display = 'none';
                subCategorySelect.removeAttribute('required');
                subCategorySelect.value = ""; // Clear selection if hidden
            }
        }
    </script>
</head>

<body>
    <?php $wd_active = 'alas'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Automated Leave Application</h1>
            <p>File a leave request and review your recent leave history.</p>
        </div>
        <button type="button" id="eventListener" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#newform"><i class="fa-solid fa-plus"></i> Leave Application Form</button>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Leave history</h3>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <input type="date" class="wd-input" id="dtp1" style="width:auto;padding:7px 10px"
                    value="<?php echo date('Y-m-d', strtotime(date("Y-m-d") . ' - 15 days')); ?>">
                <span style="color:var(--text-3);font-size:12px">to</span>
                <input type="date" class="wd-input" id="dtp2" style="width:auto;padding:7px 10px"
                    value="<?php echo date("Y-m-d"); ?>">
                <button class="wd-btn wd-btn--ghost" id="alashistory" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            </div>
        </div>
        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Filing Date</th>
                        <th>Date From</th>
                        <th>Date To</th>
                        <th>Duration</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody id="tbalas">
                    <?php
                        try {
                            include 'w_conn.php';
                            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        } catch (PDOException $e) {
                            die("ERROR: Could not connect. " . $e->getMessage());
                        }
                        $id        = $_SESSION['id'];
                        $dt1       = date('Y-m-d', strtotime(date("Y-m-d") . ' - 15 days'));
                        $dt2       = date("Y-m-d", strtotime(date("Y-m-d") . ' + 1 days'));
                        $statement = $pdo->prepare("SELECT a.LStatus as LST,a.FID as IDL,LeaveDesc,a.LFDate as FD,a.LStart as LS,a.LEnd as LE,a.LDuration as dur,a.LPurpose as LP,StatusDesc from hleavesbd as a
				    			                            INNER JOIN hleaves on a.FID=hleaves.LeaveID
															  	INNER JOIN status as b on a.LStatus=b.StatusID
															  	INNER JOIN leaves_validation c ON c.sid=a.LType
															  	INNER JOIN leaves as d on c.lid=d.LeaveID
															  	where a.EmpID=:id and hleaves.LStatus<>7 and a.LInputDate BETWEEN :dt1 AND :dt2 order by a.LStart asc");
                        $statement->bindParam(':id', $id);
                        $statement->bindParam(':dt1', $dt1);
                        $statement->bindParam(':dt2', $dt2);
                        $statement->execute();
                        while ($row21 = $statement->fetch()) {
                        ?>

                    <tr>
                        <td><b><?php echo htmlspecialchars($row21['LeaveDesc']); ?></b></td>
                        <td><?php echo date("F j, Y", strtotime($row21['FD'])); ?></td>
                        <td><?php echo date("F j, Y", strtotime($row21['LS'])); ?></td>
                        <td><?php echo date("F j, Y", strtotime($row21['LE'])); ?></td>
                        <td><?php echo htmlspecialchars($row21['dur']) . " min"; ?></td>
                        <td><?php echo htmlspecialchars($row21['LP']); ?></td>
                        <td><?php echo wd_status_pill($row21['StatusDesc']); ?></td>
                        <?php
                            if ($row21['LST'] == 1) {
                                ?>
                        <td><button type="button" class="wd-iconbtn" style="width:32px;height:32px;font-size:14px;color:var(--danger-text);border-color:var(--danger-bg)"
                                data-toggle="modal" data-target="#myModalob<?php echo $row21['IDL']; ?>" title="Cancel request"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></td>
                        <?php
                            } else {
                                ?>
                        <td><span style="color:var(--text-3)">&mdash;</span></td>
                        <?php
                                }
                            ?>
                    </tr>

                    <!-- Cancel-confirmation modal -->
                    <div class="modal ob-viewdel" id="myModalob<?php echo $row21['IDL']; ?>">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header" style="background-color:#f93627;color:#fff">
                                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                                    <h4 class="modal-title">Cancel this leave request?</h4>
                                </div>
                                <div class="modal-body">
                                    <p style="color:var(--text-2)">This action removes the pending request. Continue?</p>
                                    <button type="button" id="<?php echo $row21['IDL']; ?>" class="wd-btn wd-btn--primary ys_leave">Yes, cancel it</button>
                                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">No</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                        }
                    ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>

    <!-- ===== Leave Application Form modal (hooks preserved for module JS) ===== -->
    <div class="modal" id="newform">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header" style="background-color:#f93627;color:#fff">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                    <h4 class="modal-title">Leave Application Form</h4>
                </div>
                <?php
                    include 'w_conn.php';
                    try {
                        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    } catch (PDOException $e) {
                        die("ERROR: Could not connect. " . $e->getMessage());
                    }

                    $id        = $_SESSION['id'];
                    $isid      = $_SESSION['EmpISID'];
                    $statement = $pdo->prepare("SELECT *
                    FROM employees
                    INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID
                    INNER JOIN companies ON empdetails.EmpCompID=companies.CompanyID
                    INNER JOIN departments ON empdetails.EmpdepID=departments.DepartmentID
                    INNER JOIN positions ON positions.PSID=employees.PosID where employees.EmpID=:id order by employees.EmpLN ASC ");
                    $statement->bindParam(':id', $id);
                    $statement->execute();
                    $row = $statement->fetch();

                    // Direct reports of the logged-in user (for filing leave on their behalf)
                    $reportsStmt = $pdo->prepare("SELECT em.EmpID, em.EmpLN, em.EmpFN, em.EmpMN
                        FROM empdetails e
                        INNER JOIN employees em ON e.EmpID = em.EmpID
                        WHERE e.EmpISID = :sid AND e.EmpCompID = :cid
                        ORDER BY em.EmpLN ASC");
                    $reportsStmt->execute([':sid' => $id, ':cid' => $_SESSION['CompID']]);
                    $reports = $reportsStmt->fetchAll();
                ?>

                <!-- Modal body -->
                <div class="modal-body">
                    <form id="alas_data" action="">
                        <div class="row">
                            <div class="col-lg-6">
                                <input type="hidden" id="alas_owner_id" value="<?php echo $_SESSION['id']; ?>">
                                <div class="form-group">
                                    <label>Personnel Name:</label>
                                    <?php if (count($reports) > 0): ?>
                                        <select class="form-control" id="target_empid" name="target_empid">
                                            <option value="<?php echo $_SESSION['id']; ?>"><?php echo $row['EmpLN'] . ' ' . $row['EmpFN'] . ' ' . $row['EmpMN']; ?> (Myself)</option>
                                            <?php foreach ($reports as $rep): ?>
                                                <option value="<?php echo htmlspecialchars($rep['EmpID']); ?>"><?php echo htmlspecialchars($rep['EmpLN'] . ' ' . $rep['EmpFN'] . ' ' . $rep['EmpMN']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Select a team member to file a leave on their behalf.</small>
                                    <?php else: ?>
                                        <input type="text" disabled class="form-control"
                                            value="<?php echo $row['EmpLN'] . ' ' . $row['EmpFN'] . ' ' . $row['EmpMN'] ?>">
                                        <input type="hidden" name="target_empid" value="<?php echo $_SESSION['id']; ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Company Name:</label>
                                    <input type="text" id="f_company" disabled class="form-control"
                                        value="<?php echo $row['CompanyDesc'] ?>">
                                </div>
                                <div class="form-group">
                                    <label>Department:</label>
                                    <input type="text" id="f_dept" disabled class="form-control"
                                        value="<?php echo $row['DepartmentDesc'] ?>">
                                </div>
                                <div class="form-group">
                                    <label>Designation:</label>
                                    <input type="text" id="f_designation" disabled class="form-control"
                                        value="<?php echo $row['PositionDesc'] ?>">
                                </div>
                                <div class="form-group" style="display:none">
                                    <label>Leave Kind:</label>
                                    <select class="form-control" id="leavepay" required="required"
                                        name="leavepay">
                                        <option value="1">Paid</option>
                                        <!--<option value="0">Unpaid</option>-->
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Leave Type:</label>
                                    <select class="form-control" id="leavetype" required="required"
                                        name="leavetype" onchange="checkSubCategory(this);">

                                        <?php
                                            include 'w_conn.php';
                                            #2024 new script

                                            $id = $_SESSION['id'];
                                            $sql25  = "select * from credit where EmpID = :id";
                                            $stmt25 = $pdo->prepare($sql25);
                                            $stmt25->bindParam(':id', $id);
                                            $stmt25->execute();
                                            $crdetail25 = $stmt25->fetch();
                                            $crcnt25    = $stmt25->rowCount();

                                            // Resolve gender so the list is filtered correctly: males don't see
                                            // Maternity, females don't see Paternity. In the normal session flow
                                            // $gender is otherwise unset, falling through to the "show all" branch.
                                            if (empty($gender)) {
                                                $gstmt = $pdo->prepare("SELECT EmpGender FROM empprofiles WHERE EmpID = :id");
                                                $gstmt->execute([':id' => $id]);
                                                $gender = trim((string) $gstmt->fetchColumn());
                                            }

                                            if ($crcnt25 > 0) {


                                                if ( $gender == "Male") {
                                                            $sql = mysqli_query($con, "select * from  leaves where LeaveID IN('29','33','30','22') order by LeaveDesc asc");

                                                } else if ( $gender == "Female") {
                                                    $sql = mysqli_query($con, "select * from  leaves where LeaveID IN('27','33','30','22') order by LeaveDesc asc");

                                                } else {
                                                    $sql = mysqli_query($con, "select * from  leaves where LeaveID IN('29','27','36','37','33','30','22') order by LeaveDesc asc");
                                                }

                                                while ($res = mysqli_fetch_array($sql)) {
                                                    if ($_SESSION['id'] != "WeDoinc-003" and $res['LeaveID'] == 34) {
                                                    } else {
                                                    ?>
                                                        <option value="<?php echo $res['LeaveID']; ?>">
                                                            <?php echo $res['LeaveDesc']; ?> </option>
                                                        <?php
                                                            }
                                                }

                                            } else {
                                                ?>
                                                <option value="33">Unpaid</option>
                                                <?php

                                                }
                                            ?>
                                    </select>

                                </div>

                                <div class="form-group" id="sub_category_area" style="display:none; background-color: #f9f9f9; padding: 10px; border-radius: 5px; border: 1px solid #ddd;">
                                    <label>Select Vacation Detail: </label>
                                    <select class="form-control" id="leave_subcategory" name="leave_subcategory">
                                        <option value="22">VACATION </option>
                                        <option value="38">FORCE MAJEURE (Inclement weather, nature, etc.)</option>
                                        <option value="24">EMERGENCY (Emergencies)</option>

                                    </select>
                                    <small class="text-muted">Note: Force Majeure and Emergency can be filed upon reporting back for work.</small>
                                </div>
                                <div class="form-group">
                                    <label>Explanation/ Purpose of Leave:</label>
                                    <textarea class="form-control" rows="4" id="purposeofleave"
                                        name="reason"></textarea>
                                </div>

                            </div>
                            <div class="col-lg-6">

                                <?php
                                    include 'w_conn.php';
                                    //get date hired as regular
                                    try {
                                        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
                                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                    } catch (PDOException $e) {
                                        die("ERROR: Could not connect. " . $e->getMessage());
                                    }
                                    $id = $_SESSION['id'];
                                    //parameter this value
                                    $sql  = "select * from empdetails where EmpID = :id";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->bindParam(':id', $id);
                                    $stmt->execute();
                                    $details      = $stmt->fetch();
                                    $detailscnt   = $stmt->rowCount();
                                    $cdPerMonth   = 0;
                                    $cdPerDay     = 0;
                                    $creditEarned = 0;
                                    $emergencyRemaining = null; // only set for the special on-the-spot employee
                                    if ($detailscnt == 1) {
                                        if ($details['EmpStatID'] != 1) {
                                            $creditEarned = "Non Regular employee don't have credits";
                                        } else {

                                            if (is_null($details['EmpDOR'])) {
                                                $creditEarned = "Missing Regularization Date";
                                            } else {


                                                    $dth = $details['EmpDOR'];
                                                    $yr  = date("Y", strtotime($dth));
                                                    $cyr = date("Y"); // Ito rin ang ating $currentYear

                                                    // Siguraduhin natin na parehong may hawak na ID ang dalawang variable para walang conflict sa query mo
                                                    $EmplID = $id;
                                                    $currentYear = $cyr;

                                                    ######################
                                                    # 2024 script for credit
                                                    # get the details to validate 15 and 10 credits
                                                    $sql24  = "SELECT * FROM credit WHERE EmpID = :id";
                                                    $stmt24 = $pdo->prepare($sql24);
                                                    $stmt24->bindParam(':id', $id);
                                                    $stmt24->execute();
                                                    $crdetail24 = $stmt24->fetch();
                                                    $crcnt24    = $stmt24->rowCount();

                                                    // 2026 set default credit earned
                                                    $creditEarned = $crdetail24['CT'] ?? 0;

                                                    // Suriin kung ang empleyado ay ang partikular na account para sa earning mode
                                                    if ($id == "WeDoinc-0145") {

                                                        // Kunin ang kasalukuyang kabuuang credit logs (Isang query na lang sa itaas ng math logic)
                                                        $sql  = "SELECT * FROM credit WHERE EmpID = :id";
                                                        $stmt = $pdo->prepare($sql);
                                                        $stmt->bindParam(':id', $id);
                                                        $stmt->execute();
                                                        $crdetail = $stmt->fetch();
                                                        $crcnt    = $stmt->rowCount();

                                                        if ($crcnt > 0) {
                                                            $crh  = $crdetail['CTH']; // Total Credits Allowable (e.g., 15)
                                                            $crth = $crdetail['CT'];  // Current Credit Balance na natitira sa DB

                                                            // 1. Kunin ang kabuuang minuto ng aprubadong leaves mula sa DB (Kabilang ang mga half-day o buong araw)
                                                            $sqlCount = "SELECT SUM(hb.LDuration) FROM hleavesbd hb
                                                                        JOIN hleaves h ON hb.FID = h.LeaveID
                                                                        WHERE h.EmpID = :empid
                                                                        AND h.LType = 24
                                                                        AND hb.LStatus = 4
                                                                        AND YEAR(hb.LStart) = :year";
                                                            $stmtCount = $pdo->prepare($sqlCount);
                                                            $stmtCount->execute([':empid' => $EmplID, ':year' => $currentYear]);

                                                            // Kung walang record, gawin nating 0 ang minutes
                                                            $totalMinutesApproved = (float)$stmtCount->fetchColumn() ?: 0;

                                                            // 2. I-convert ang total minutes sa eksaktong bilang ng araw (Halimbawa: 300 mins / 600 = 0.5 araw)
                                                            $hrApprovedLeaves = $totalMinutesApproved / 600;

                                                            $tdy   = date("Y");
                                                            $date1 = date_create($tdy . "-01-01");
                                                            $date2 = date_create(($tdy + 1) . "-01-01");
                                                            $diff  = date_diff($date1, $date2);
                                                            $daysInYear = (int)$diff->format("%a");

                                                            // Credit rate kada isang araw
                                                            $cdPerDay = $crh / $daysInYear;

                                                            // Tukuyin ang panimulang petsa base sa Year comparison
                                                            if ($yr < $cyr) {
                                                                $gnOfdJan = date_create($tdy . "-01-01");
                                                            } else {
                                                                $gnOfdJan = date_create($dth);
                                                            }

                                                            // Kalkulahin ang bilang ng araw mula panimula hanggang ngayon
                                                            $gnOfdCur    = date_create(date("Y-m-d"));
                                                            $diff2       = date_diff($gnOfdJan, $gnOfdCur);
                                                            $gnOfdJanCur = (int)$diff2->format("%r%a");

                                                            // Earning credits already used (deducted) so far. Emergency (LType 24)
                                                            // never deducts from credit.CT, so CTH - CT reflects only non-emergency use.
                                                            $useCredit = $crh - $crth;

                                                            // EARNING balance = exactly what HR uses to pay a non-emergency leave:
                                                            // accrued-to-date minus used. This mirrors $varCT in
                                                            // query/Query-UpdateApp.php so the form never shows more payable days
                                                            // than HR will actually approve.
                                                            $onTheSpotCredit = 4;
                                                            $creditEarned = ($cdPerDay * $gnOfdJanCur) - $useCredit;
                                                            if ($creditEarned < 0) {
                                                                $creditEarned = 0;
                                                            }

                                                            // The 4 flat Emergency (on-the-spot) credits are a SEPARATE pool, usable
                                                            // only for Emergency leave and never deducted from the earning balance.
                                                            // Show how many of the 4 remain (emergency days already taken this year).
                                                            $emergencyRemaining = $onTheSpotCredit - $hrApprovedLeaves;
                                                            if ($emergencyRemaining < 0) {
                                                                $emergencyRemaining = 0;
                                                            }

                                                        } else {
                                                            $creditEarned = "Missing credit logs";
                                                        }
                                                    }



                                                }

                                        }
                                    } else {
                                        //return;
                                }?>
                                <div class="form-group"
                                    style="border:1px solid var(--brand);background:var(--brand-tint);padding:12px;border-radius:10px;color:var(--text)">
                                    <label style="color:var(--brand-700)">Leave Credits<?php echo ($emergencyRemaining !== null) ? ' (earned)' : ''; ?>:</label>

                                    <?php

                                        // Determine the display value
                                        if (is_numeric($creditEarned)) {
                                            $displayValue = number_format((float) $creditEarned, 2, '.', '');
                                        } else {
                                            // It's already a string message, so keep it as is
                                            $displayValue = $creditEarned;
                                        }
                                    ?>
                                    <input name="lcredit" type="text" id="lcredit"
                                        style="color:var(--brand-700);font-weight:600" readonly="readonly"
                                        value="<?php echo htmlspecialchars($displayValue); ?>" class="form-control">

                                    <?php if ($emergencyRemaining !== null): ?>
                                        <label style="margin-top:8px;color:var(--brand-700)">Emergency (on-the-spot):</label>
                                        <input type="text" readonly="readonly" style="color:var(--brand-700);font-weight:600"
                                            value="<?php echo htmlspecialchars(number_format((float) $emergencyRemaining, 2, '.', '') . ' / 4'); ?>"
                                            class="form-control">
                                    <?php endif; ?>

                                </div>
                                <div class="form-group">
                                    <label>Filing Date:</label>
                                    <input name="lfdates" type="text" readonly="readonly"
                                        value="<?php echo date('F d, Y'); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Leave Start Date:</label>
                                    <input name="lstarts" max="<?php echo date('Y-12-31'); ?>"
                                        min="<?php echo date('Y-01-01'); ?>" type="date"
                                        id="lstarts" value="<?php echo date('Y-m-d'); ?>"
                                        class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Leave End Date:</label>
                                    <input name="lenddate" min="<?php echo date('Y-01-01'); ?>"
                                        type="date" id="lenddate"
                                        value="<?php echo date('Y-m-d'); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <div class="ihd-dis" style="float: right;">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" style="position: relative;" id="exampleCheck1" name="is_half_day">
                                            <label class="form-check-label" for="exampleCheck1">If Half Day?</label>
                                        </div>

                                        <div id="halfDayOptions" style="display: none; margin-top: 10px; padding-left: 20px;">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="half_day_type" id="typeAM" value="half_day_am" checked>
                                                <label class="form-check-label" for="typeAM">AM (Morning)</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="half_day_type" id="typePM" value="half_day_pm">
                                                <label class="form-check-label" for="typePM">PM (Afternoon)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <label class="dur-text">Duration Days:</label>
                                    <input type="text" readonly="readonly" value="1" id="leavedur"
                                        name="leavedur" class="form-control dura">
                                </div>
                                <button type="button" id="alassave"
                                    class="wd-btn wd-btn--primary" style="width:100%;justify-content:center">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- Modal footer -->
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- The Modal -->
    <div class="modal" id="modalSuccess">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header" style="padding: 7px 8px;">
                    <h1 style="font-size: 25px; padding-left: 10px;color:green;"><i class="fa fa-check"
                            aria-hidden="true"></i></h1>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <!-- Modal body -->
                <div class="modal-body">
                    <div class="alert alert-success">
                    </div>
                </div>
                <!-- Modal footer -->
            </div>
        </div>
    </div>
    <!-- modal end -->

    <!-- The Modal -->
    <div class="modal" id="modalWarning">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header" style="padding: 7px 8px;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <!-- Modal body -->
                <div class="modal-body">
                    <div class="alert alert-danger">
                    </div>
                </div>
                <!-- Modal footer -->
            </div>
        </div>
    </div>

</body>

</html>
