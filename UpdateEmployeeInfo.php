<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
    include 'w_conn.php';

    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userType = $_SESSION['UserType'];
    $compID   = isset($_SESSION['CompID']) ? $_SESSION['CompID'] : '';

    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

    /* <option>; $selected marks the employee's current value */
    function opt($value, $label, $selected = false) {
        return '<option value="' . h($value) . '"' . ($selected ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    require_once __DIR__ . '/includes/profile-change.php';

    /* Two modes:
       - "Update 201 Files" users edit anyone directly (Save changes);
       - everyone else may only open THEIR OWN record, and their edits become a
         change request that HR approves (includes/profile-change.php). */
    $me          = (string)$_SESSION['id'];
    $canUpdate   = pcr_can_update($pdo, $me);
    $requestMode = !$canUpdate;
    $q           = (isset($_GET['sid']) && trim($_GET['sid']) !== '') ? trim($_GET['sid']) : $me;
    $forbidden   = $requestMode && $q !== $me;
    $qe = mysqli_real_escape_string($con, $q);

    $result = mysqli_query($con, "SELECT empdetails.Seq_ID,hmo.HMO_ID,hmo.HMO_PROVIDER,agency.AgencyID,agency.AgencyName,empdetails.EmpID,empdetails.EmpDateHired,empdetails.EmpDateResigned,empdetails.EmpDOR,empstatus.EmpStatDesc,empstatus.EmpStatID,workschedule.WorkSchedID,
      workschedule.TimeFrom,workschedule.TimeTo,workdays.WID,workdays.WDesc,companies.CompanyID,companies.CompanyDesc,empdetails2.EmpBasic,
      empdetails2.EmpHRate,empdetails2.EmpAllowance,employees.EmpID as id,employees.EmpSuffix as sf,employees.EmpFN AS fn,employees.EmpLN as ln,employees.EmpMN as mn,employees.EmpSuffix,
      empprofiles.EmpAddress1,empprofiles.EmpDOB,empprofiles.EmpHMONumber,empprofiles.EmpGender,empprofiles.EmpEmail,empprofiles.EmpMobile,
      empprofiles.EmpPPNo,empprofiles.EmpPINo,empprofiles.EmpPHNo,empprofiles.EmpSSS,empprofiles.EmpTIN,
      empprofiles.EmpUMIDNo,empprofiles.EmpCitezen,empprofiles.EmpReligion,empprofiles.EmpPhone,empprofiles.EmpPPIA,
      empprofiles.EmpPP,empprofiles.EmpPPSD,empprofiles.EmpPPDept,empprofiles.EmpPPPos,empprofiles.EmpCS,empprofiles.EmpPPED,
      empprofiles.EmpAddDis,empprofiles.EmpAddCity,empprofiles.EmpAddProv,empprofiles.EmpAddZip,empprofiles.EmpAddCountry,joblevel.jobLevelID,joblevel.jobLevelDesc,
      empprofiles.EmpPPAth,estatus.StatusEmpDesc,estatus.ID,t.EmpSuffix,t.EmployeeIDNumber,t.EmpFN,t.EmpLN,t.EmpMN,positions.PositionDesc,positions.PSID,departments.DepartmentDesc,departments.DepartmentID,joblevel.JobLevelDesc,departments.DepartmentID,empdetails.EmpISID
    FROM empdetails
    LEFT JOIN empstatus ON empdetails.EmpStatID=empstatus.EmpStatID
    LEFT JOIN hmo ON hmo.HMO_ID=empdetails.HMO_ID
    LEFT JOIN agency ON agency.AgencyID=empdetails.AgencyID
    LEFT JOIN workschedule ON empdetails.EmpWSID=workschedule.WorkSchedID
    LEFT JOIN workdays ON empdetails.EmpRDID=workdays.WID
    LEFT JOIN companies ON empdetails.EmpCompID=companies.CompanyID
    LEFT JOIN empdetails2 ON empdetails2.EmpID= empdetails.EmpID
    LEFT JOIN employees ON empdetails.EmpISID=employees.EmpID
    LEFT JOIN empprofiles ON empprofiles.EmpID=empdetails.EmpID
    LEFT JOIN employees as t ON empprofiles.EmpID=t.EmpID
    LEFT JOIN estatus ON estatus.ID=t.EmpStatusID
    LEFT JOIN positions ON positions.PSID=t.PosID
    LEFT JOIN joblevel ON joblevel.JobLevelID=positions.EmpJobLevelID
    LEFT JOIN departments ON positions.DepartmentID=departments.DepartmentID
    WHERE empdetails.EmpID = '$qe'");
    $res = (!$forbidden && $result) ? mysqli_fetch_array($result) : null;   // never load someone else's record

    $fullName = $res ? trim(preg_replace('/\s+/', ' ', ucfirst($res['EmpFN']) . ' ' . ucfirst($res['EmpMN']) . ' ' . ucfirst($res['EmpLN']) . ' ' . ucfirst($res['EmpSuffix']))) : '';
    $ISname   = $res ? trim($res['fn'] . ' ' . $res['ln'] . ' ' . $res['mn'] . ' ' . ucfirst($res['sf'])) : '';

    $photo    = $res ? trim((string)$res['EmpPPAth']) : '';
    $hasPhoto = $photo !== '' && is_file(__DIR__ . '/' . $photo);

    /* request mode: show the employee their still-pending values, so submitting
       again amends that request instead of silently dropping it */
    $latest = ($res && $requestMode) ? pcr_latest($pdo, $me) : null;
    $pending = ($latest && $latest['Status'] === 'pending') ? $latest : null;
    $eduOverlay = [];
    $familyOverlay = null;
    if ($pending) {
        $defs = pcr_fields();
        foreach ($pending['Changes']['fields'] ?? [] as $c) {
            if (!isset($defs[$c['field']])) { continue; }
            [, $table, $col] = $defs[$c['field']];
            if (strpos($table, 'edu:') === 0) { $eduOverlay[substr($table, 4)][$col] = $c['new']; }
            else { $res[$col] = $c['new']; }
        }
        if (isset($pending['Changes']['family']['new'])) { $familyOverlay = $pending['Changes']['family']['new']; }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $fullName !== '' ? h($fullName) . ' · Edit profile' : 'Edit profile'; ?></title>
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (Bootstrap modals + jQuery + FontAwesome 6 + SweetAlert) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/employee-form.css?v=<?php echo filemtime(__DIR__ . '/assets/css/employee-form.css'); ?>">

    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-updateemployee.js?v=<?php echo filemtime(__DIR__ . '/assets/js/script-updateemployee.js'); ?>"></script>
</head>
<body>
    <?php
        $wd_active = 'e201';
        include 'includes/wd-header.php';
    ?>

<?php if ($forbidden) { ?>
    <div class="wd-pagehead"><div><h1>Update my information</h1></div></div>
    <div class="ne-card ue-missing">
        <i class="fa-solid fa-lock"></i>
        <h3>You can only update your own information</h3>
        <p>Changes to other employees' records are made by HR.</p>
        <a class="wd-btn wd-btn--ghost" href="UpdateEmployeeInfo"><i class="fa-solid fa-user-pen"></i> Update my information</a>
    </div>
<?php } elseif (!$res) { ?>
    <div class="wd-pagehead"><div><h1>Edit employee profile</h1></div></div>
    <div class="ne-card ue-missing">
        <i class="fa-solid fa-user-slash"></i>
        <h3>Employee not found</h3>
        <p>There is no employee with ID <b><?php echo h($q); ?></b>.</p>
        <a class="wd-btn wd-btn--ghost" href="e201"><i class="fa-solid fa-arrow-left"></i> Back to 201 File</a>
    </div>
<?php } else { ?>

    <div class="wd-pagehead">
        <div class="ue-ident">
            <?php if ($hasPhoto) { ?>
                <img class="ue-ident__avatar" src="<?php echo h($photo); ?>" alt="">
            <?php } else { ?>
                <span class="ue-ident__avatar ue-ident__avatar--initials"><?php echo h(strtoupper(substr(trim($res['EmpFN']), 0, 1) . substr(trim($res['EmpLN']), 0, 1))); ?></span>
            <?php } ?>
            <div>
                <h1><?php echo $requestMode ? 'Update my information' : 'Edit employee profile'; ?></h1>
                <p><b><?php echo h($fullName); ?></b> &middot; <?php echo h($res['EmpID']); ?><?php if (trim((string)$res['PositionDesc']) !== '') { echo ' &middot; ' . h($res['PositionDesc']); } ?></p>
            </div>
        </div>
        <div class="ne-actions">
            <a class="wd-btn wd-btn--ghost" href="e201"><i class="fa-solid fa-arrow-left"></i> Back</a>
            <?php if ($requestMode) { ?>
                <button type="button" class="wd-btn wd-btn--primary" id="submit-form"><i class="fa-solid fa-paper-plane"></i> Submit for approval</button>
            <?php } else { ?>
                <button type="button" class="wd-btn wd-btn--primary" id="submit-form"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
            <?php } ?>
        </div>
    </div>

    <?php if ($requestMode) {
        $labels = [];
        if ($pending) {
            foreach ($pending['Changes']['fields'] ?? [] as $c) { $labels[] = $c['label']; }
            if (!empty($pending['Changes']['family'])) { $labels[] = 'Family details'; }
        }
        $reviewedRecently = $latest && $latest['ReviewedAt'] && strtotime($latest['ReviewedAt']) > strtotime('-14 days');
    ?>
        <?php if ($pending) { ?>
            <div class="ue-notice ue-notice--info">
                <i class="fa-solid fa-hourglass-half"></i>
                <div>
                    <b>Waiting for HR approval</b> &mdash; you submitted changes on <?php echo h(date('M j, Y g:i A', strtotime($pending['CreatedAt']))); ?>.
                    The form shows your requested values; submitting again replaces that request.
                    <span class="ue-notice__fields"><?php echo h(implode(' · ', $labels)); ?></span>
                </div>
            </div>
        <?php } elseif ($latest && $latest['Status'] === 'rejected' && $reviewedRecently) { ?>
            <div class="ue-notice ue-notice--danger">
                <i class="fa-solid fa-circle-xmark"></i>
                <div><b>Your last request was not approved</b> (<?php echo h(date('M j, Y', strtotime($latest['ReviewedAt']))); ?>):
                    <?php echo h($latest['Remarks']); ?></div>
            </div>
        <?php } elseif ($latest && $latest['Status'] === 'approved' && $reviewedRecently) { ?>
            <div class="ue-notice ue-notice--ok">
                <i class="fa-solid fa-circle-check"></i>
                <div><b>Your last request was approved</b> on <?php echo h(date('M j, Y', strtotime($latest['ReviewedAt']))); ?>.</div>
            </div>
        <?php } else { ?>
            <div class="ue-notice">
                <i class="fa-solid fa-circle-info"></i>
                <div>Changes you submit are reviewed by HR before they appear on your 201 file. Employment details, salary and your photo are updated by HR.</div>
            </div>
        <?php } ?>
    <?php } ?>

    <!-- step navigation -->
    <div class="ne-steps">
        <button type="button" class="ne-step is-active" id="btn-ci"><span class="ne-step__t">General Information</span><span class="ne-badge"></span></button>
        <button type="button" class="ne-step" id="btn-eb"><span class="ne-step__t">Educational Background</span><span class="ne-badge"></span></button>
        <?php if (!$requestMode) { ?>
        <button type="button" class="ne-step" id="btn-es"><span class="ne-step__t">Employment Information</span><span class="ne-badge"></span></button>
        <?php } ?>
        <button type="button" class="ne-step" id="btn-cdd"><span class="ne-step__t">Compliance Document Data</span><span class="ne-badge"></span></button>
        <button type="button" class="ne-step" id="btn-fd"><span class="ne-step__t">Family Details</span><span class="ne-badge"></span></button>
        <?php if (!$requestMode) { ?>
        <button type="button" class="ne-step" id="btn-prf"><span class="ne-step__t">Profile</span><span class="ne-badge"></span></button>
        <?php } ?>
    </div>

    <form id="fdata" method="post" enctype="multipart/form-data" data-mode="<?php echo $requestMode ? 'request' : 'direct'; ?>">

        <!-- ============ 1) General Information ============ -->
        <div class="ne-panel frm-ci is-shown">
            <div class="ne-card">
                <h3 class="ne-card__title">General Information</h3>
                <p class="ne-card__sub">Personal details and complete mailing address.</p>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label for="empfname">First Name: <i>*</i></label>
                            <input type="text" class="form-control" required name="empfn" id="empfname" placeholder="First Name" value="<?php echo h($res['EmpFN']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="empmname">Middle Name:</label>
                            <input type="text" class="form-control" name="empmn" id="empmname" placeholder="Middle Name" value="<?php echo h($res['EmpMN']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="emplname">Last Name: <i>*</i></label>
                            <input type="text" class="form-control" required name="empln" id="emplname" placeholder="Last Name" value="<?php echo h($res['EmpLN']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="empsuff">Suffix:</label>
                            <input type="text" class="form-control" name="empsuff" id="empsuff" placeholder="Suffix" value="<?php echo h($res['EmpSuffix']); ?>">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label for="empgender">Gender: <i>*</i></label>
                            <select class="form-control" required name="pempgender" id="empgender">
                                <?php if (!in_array($res['EmpGender'], ['Female', 'Male'], true)) { echo opt($res['EmpGender'], $res['EmpGender'], true); } ?>
                                <?php echo opt('Female', 'Female', $res['EmpGender'] === 'Female'); ?>
                                <?php echo opt('Male', 'Male', $res['EmpGender'] === 'Male'); ?>
                            </select>
                        </div>
                        <div class="form-group ne-ac">
                            <label for="empcitizen">Citizenship:</label>
                            <input type="text" class="form-control" id="empcitizen" name="empcitizen" autocomplete="off" placeholder="Citizenship" value="<?php echo h($res['EmpCitezen']); ?>">
                            <div class="cl-citizen">
                                <?php
                                    $sql2 = mysqli_query($con, "select DISTINCT EmpCitezen from empprofiles WHERE EmpCitezen IS NOT NULL");
                                    while ($r2 = mysqli_fetch_array($sql2)) { echo '<a class="ctzn-a">' . h($r2['EmpCitezen']) . '</a>'; }
                                ?>
                            </div>
                        </div>
                        <div class="form-group ne-ac">
                            <label for="empreligion">Religion:</label>
                            <input type="text" class="form-control" id="empreligion" name="empreligion" autocomplete="off" placeholder="Religion" value="<?php echo h($res['EmpReligion']); ?>">
                            <div class="cl-reli">
                                <?php
                                    $sql2 = mysqli_query($con, "select DISTINCT EmpReligion from empprofiles WHERE EmpReligion IS NOT NULL");
                                    while ($r2 = mysqli_fetch_array($sql2)) { echo '<a class="rel-a">' . h($r2['EmpReligion']) . '</a>'; }
                                ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="empphome">Home Phone Number:</label>
                            <input type="text" class="form-control" id="empphome" name="emphomenumber" placeholder="Home Phone Number" value="<?php echo h($res['EmpMobile']); ?>">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label for="empdob">Date of Birth: <i>*</i></label>
                            <input type="date" class="form-control" id="empdob" name="pempdob" value="<?php echo h($res['EmpDOB']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="empcs">Civil Status: <i>*</i></label>
                            <select class="form-control" required id="empcs" name="pempcs">
                                <?php
                                    $cs = ['Single', 'Married', 'Divorced', 'Widowed'];
                                    if (!in_array($res['EmpCS'], $cs, true)) { echo opt($res['EmpCS'], $res['EmpCS'], true); }
                                    foreach ($cs as $c) { echo opt($c, $c, $res['EmpCS'] === $c); }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="phoneno">Mobile Number:</label>
                            <input type="text" class="form-control" id="phoneno" name="pempn0" placeholder="Mobile Number" value="<?php echo h($res['EmpPhone']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="empeadd">Email Address:</label>
                            <input type="email" class="form-control" id="empeadd" name="pempeadd" placeholder="Email Address" value="<?php echo h($res['EmpEmail']); ?>">
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <label>Complete Mailing Address: <i>*</i></label>
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <input type="text" id="pempstreetno" name="pempstreetno" class="form-control" required placeholder="Street No / Street Name / Subdivision" value="<?php echo h($res['EmpAddress1']); ?>">
                                </div>
                                <div class="form-group">
                                    <input type="text" id="pempdistrict" name="pempdistrict" class="form-control" required placeholder="Barangay" value="<?php echo h($res['EmpAddDis']); ?>">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <input type="text" id="pempcity" name="pempcity" class="form-control" required placeholder="City" value="<?php echo h($res['EmpAddCity']); ?>">
                                </div>
                                <div class="form-group">
                                    <input type="text" id="pempprovince" name="pempprovince" class="form-control" required placeholder="Province" value="<?php echo h($res['EmpAddProv']); ?>">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <input type="text" id="pempzipcode" name="pempzipcode" class="form-control" required placeholder="Zip Code" value="<?php echo h($res['EmpAddZip']); ?>">
                                </div>
                                <div class="form-group">
                                    <input type="text" id="pempcountry" name="pempcountry" class="form-control" required placeholder="Country" value="<?php echo h($res['EmpAddCountry']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ 2) Educational Background ============ -->
        <div class="ne-panel frm-eb">
            <div class="ne-card">
                <h3 class="ne-card__title">Educational Background</h3>
                <p class="ne-card__sub">Schools attended from primary to tertiary.</p>
                <table class="table tbl-new">
                        <thead>
                            <tr>
                                <td></td>
                                <td>Name of School</td>
                                <td>Year Started</td>
                                <td>Year Graduated</td>
                                <td>School Address</td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $edu = $pdo->prepare("SELECT * FROM empeducationalbackground WHERE Program = :p AND EmpID = :id");
                                foreach (['p' => 'Primary', 's' => 'Secondary', 't' => 'Tertiary'] as $k => $prog) {
                                    $edu->execute([':p' => $prog, ':id' => $res['EmpID']]);
                                    $e = array_merge($edu->fetch() ?: [], $eduOverlay[$prog] ?? []);
                            ?>
                            <tr>
                                <td><?php echo $prog; ?></td>
                                <td><input type="text" value="<?php echo h($e['Name_of_School'] ?? ''); ?>" name="<?php echo $k; ?>_nameofschool" class="form-control"></td>
                                <td><input type="number" value="<?php echo h($e['Year_Started'] ?? ''); ?>" name="<?php echo $k; ?>_yearstarted" class="form-control"></td>
                                <td><input type="number" value="<?php echo h($e['Year_End'] ?? ''); ?>" name="<?php echo $k; ?>_yeargraduated" class="form-control"></td>
                                <td><input type="text" value="<?php echo h($e['School_Address'] ?? ''); ?>" name="<?php echo $k; ?>_schooladdress" class="form-control"></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                </table>
            </div>
        </div>

        <?php if (!$requestMode) { /* employment & salary: HR only — not even rendered for employees */ ?>
        <!-- ============ 3) Employment Information ============ -->
        <div class="ne-panel frm-es">
            <div class="ne-card">
                <h3 class="ne-card__title">Employment Information</h3>
                <p class="ne-card__sub">Company, role, compensation and employment dates.</p>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empcid">Employee ID:</label>
                                    <?php require_once __DIR__ . '/includes/idcard-lib.php'; $idcNumber = idc_issued_number($pdo, $res['EmpID']); ?>
                                    <input type="text" class="form-control" required name="empcid" id="empcid" placeholder="Employee ID" value="<?php echo h($idcNumber ?: $res['EmployeeIDNumber']); ?>"<?php if ($idcNumber): ?> readonly title="Set by the issued company ID card (ID Card Generator)"<?php endif; ?>>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empID">Employee Number: <i>*</i></label>
                                    <input type="text" class="form-control" required readonly name="empidn" id="empID" placeholder="Employee Number" value="<?php echo h($res['EmpID']); ?>">
                                    <input type="hidden" name="empidhidden" value="<?php echo h($res['EmpID']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="empcompid">Company:</label>
                            <select class="form-control" id="empcompid" name="empcompany">
                                <?php echo opt($res['CompanyID'], $res['CompanyDesc'], true); ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Department: <i>*</i> <i class="fa-solid fa-plus ne-addpl" data-toggle="modal" data-target="#modaladddep" id="depselect" aria-hidden="true"></i></label>
                            <select class="form-control DepEmp" id="empdepartment1" required name="empdep">
                                <?php
                                    $found = false; $html = '';
                                    $sql = mysqli_query($con, "SELECT * FROM departments WHERE CompID='" . mysqli_real_escape_string($con, $res['CompanyID']) . "'");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $sel = $row['DepartmentID'] == $res['DepartmentID'];
                                        $found = $found || $sel;
                                        $html .= '<option class="dep' . h($row['CompID']) . '" value="' . h($row['DepartmentID']) . '"' . ($sel ? ' selected' : '') . '>' . h($row['DepartmentDesc']) . '</option>';
                                    }
                                    if (!$found) { echo opt($res['DepartmentID'], $res['DepartmentDesc'], true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Position: <i>*</i> <i class="fa-solid fa-plus ne-addpl Pos_Sel" data-toggle="modal" data-target="#modaladdpos" aria-hidden="true"></i></label>
                            <select class="form-control" id="idempposition" required name="empposition">
                                <?php
                                    $found = false; $html = '';
                                    $sql = mysqli_query($con, "SELECT * FROM positions");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $sel = $row['PSID'] == $res['PSID'];
                                        $found = $found || $sel;
                                        $html .= '<option class="pos' . h($row['DepartmentID']) . '" value="' . h($row['PSID']) . '"' . ($sel ? ' selected' : '') . '>' . h($row['PositionDesc']) . '</option>';
                                    }
                                    if (!$found) { echo opt($res['PSID'], $res['PositionDesc'], true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="empdesccode">Classification: <i>*</i></label>
                            <select class="form-control" id="empdesccode" required name="empclassification">
                                <?php
                                    $found = false; $html = '';
                                    $sql = mysqli_query($con, "SELECT * FROM empstatus");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $sel = $row['EmpStatID'] == $res['EmpStatID'];
                                        $found = $found || $sel;
                                        $html .= opt($row['EmpStatID'], $row['EmpStatDesc'], $sel);
                                    }
                                    if (!$found) { echo opt($res['EmpStatID'], $res['EmpStatDesc'], true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <?php
                            // pre-fill so a save doesn't wipe it (the endpoint writes this field every time)
                            $dor = (string)$res['EmpDOR'];
                            if (strpos($dor, '0000') === 0) { $dor = ''; }
                            $dorShown = $res['EmpStatID'] == 1;
                        ?>
                        <div class="form-group" id="dorGroup"<?php echo $dorShown ? '' : ' hidden'; ?>>
                            <label for="dorInput">Date of Regularization: <i>*</i></label>
                            <input class="form-control" type="date" id="dorInput" name="dorInput" value="<?php echo h($dor); ?>"<?php echo $dorShown ? ' required' : ''; ?>>
                        </div>

                        <div class="form-group">
                            <label for="empis">Immediate Superior: <i>*</i></label>
                            <select class="form-control" id="empis" required name="empis">
                                <?php
                                    $curIS = (string)$res['EmpISID'];
                                    echo $curIS === 'N/A' || $curIS === '' ? '<option selected>N/A</option>' : '<option>N/A</option>';
                                    $found = ($curIS === 'N/A' || $curIS === ''); $html = '';
                                    $sql = mysqli_query($con, "SELECT * FROM employees a INNER JOIN empdetails b ON a.empid=b.EmpID WHERE a.empid<>'" . mysqli_real_escape_string($con, $res['EmpID']) . "'");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $sel = $row['EmpID'] == $curIS;
                                        $found = $found || $sel;
                                        $html .= '<option class="is' . h($row['EmpdepID']) . '" value="' . h($row['EmpID']) . '"' . ($sel ? ' selected' : '') . '>' . h($row['EmpFN'] . ', ' . $row['EmpLN']) . '</option>';
                                    }
                                    if (!$found) { echo opt($curIS, $ISname, true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="empstatus">Status: <i>*</i></label>
                            <select class="form-control" required id="empstatus" name="empst">
                                <?php
                                    $found = false; $html = '';
                                    $sql = mysqli_query($con, "SELECT * FROM estatus");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $sel = $row['ID'] == $res['ID'];
                                        $found = $found || $sel;
                                        $html .= opt($row['ID'], $row['StatusEmpDesc'], $sel);
                                    }
                                    if (!$found) { echo opt($res['ID'], $res['StatusEmpDesc'], true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="emppp">Previous Position:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpPP']); ?>" name="pemppp" id="emppp" placeholder="Previous Position">
                        </div>
                        <div class="form-group">
                            <label for="emppsd">Start Date:</label>
                            <input type="date" class="form-control" value="<?php echo h($res['EmpPPSD']); ?>" name="pemppsd" id="emppsd">
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="idempjoblevel">Job Level:</label>
                            <select class="form-control" id="idempjoblevel" required name="empjoblevel">
                                <?php
                                    $found = false; $html = '';
                                    $st = $pdo->query("SELECT * FROM joblevel");
                                    while ($row = $st->fetch()) {
                                        $sel = $row['jobLevelID'] == $res['jobLevelID'];
                                        $found = $found || $sel;
                                        $html .= opt($row['jobLevelID'], $row['jobLevelDesc'], $sel);
                                    }
                                    if (!$found) { echo opt($res['jobLevelID'], $res['jobLevelDesc'], true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="idempagency">Agency:</label>
                            <select class="form-control" id="idempagency" required name="empagency">
                                <?php
                                    $found = false; $html = '';
                                    $sql = mysqli_query($con, "SELECT * FROM agency");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $sel = $row['AgencyID'] == $res['AgencyID'];
                                        $found = $found || $sel;
                                        $html .= opt($row['AgencyID'], $row['AgencyName'], $sel);
                                    }
                                    if (!$found) { echo opt($res['AgencyID'], $res['AgencyName'], true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="idemphmo">HMO Number:</label>
                            <input type="text" id="idemphmo" value="<?php echo h($res['EmpHMONumber']); ?>" class="form-control" name="emphmono">
                        </div>
                        <div class="form-group">
                            <label for="idhmoprovider">HMO Provider:</label>
                            <select class="form-control" id="idhmoprovider" required name="emphmo">
                                <?php
                                    $found = false; $html = '';
                                    $sql = mysqli_query($con, "SELECT * FROM hmo");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        $sel = $row['HMO_ID'] == $res['HMO_ID'];
                                        $found = $found || $sel;
                                        $html .= opt($row['HMO_ID'], $row['HMO_PROVIDER'], $sel);
                                    }
                                    if (!$found) { echo opt($res['HMO_ID'], $res['HMO_PROVIDER'], true); }
                                    echo $html;
                                ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empdth">Date Hired: <i>*</i></label>
                                    <input type="date" class="form-control" id="empdth" required name="empdatehired" value="<?php echo h($res['EmpDateHired']); ?>">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empdtr">Date Resigned:</label>
                                    <input type="date" class="form-control" name="empdateresigned" id="empdtr" value="<?php echo h($res['EmpDateResigned']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empbasic">Basic: <i>*</i></label>
                                    <input type="text" class="form-control" value="<?php echo h($res['EmpBasic']); ?>.00" required id="empbasic" name="empbasic" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empallowance">Allowance:</label>
                                    <input type="text" class="form-control" value="<?php echo h($res['EmpAllowance']); ?>.00" id="empallowance" name="empallowance" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="emphourlyrate">Hourly Rate:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpHRate']); ?>.00" id="emphourlyrate" name="emphourlyrate" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="emppdept">Previous Department:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpPPDept']); ?>" name="pemppdept" id="emppdept" placeholder="Department">
                        </div>
                        <div class="form-group">
                            <label for="empppd">Previous Designation:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpPPPos']); ?>" name="pempppd" id="empppd" placeholder="Designation">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php } ?>

        <!-- ============ 4) Compliance Document Data ============ -->
        <div class="ne-panel frm-cdd">
            <div class="ne-card">
                <h3 class="ne-card__title">Compliance Document Data</h3>
                <p class="ne-card__sub">Government IDs and passport details.</p>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="empppno">Passport Number:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpPPNo']); ?>" name="pempppno" id="empppno" placeholder="Passport Number">
                        </div>
                        <div class="form-group">
                            <label for="emppped">Passport Expiry Date:</label>
                            <input type="date" class="form-control" value="<?php echo h($res['EmpPPED']); ?>" name="pemppped" id="emppped">
                        </div>
                        <div class="form-group">
                            <label for="empppia">Issuing Authority:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpPPIA']); ?>" name="pempppia" id="empppia" placeholder="Issuing Authority">
                        </div>
                        <div class="form-group">
                            <label for="emppagibig">PAG-IBIG:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpPINo']); ?>" name="pemppagibig" id="emppagibig" placeholder="PAG-IBIG">
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="empphno">PhilHealth:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpPHNo']); ?>" name="pempphno" id="empphno" placeholder="PhilHealth">
                        </div>
                        <div class="form-group">
                            <label for="empsss">SSS Number:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpSSS']); ?>" name="pempsss" id="empsss" placeholder="SSS Number">
                        </div>
                        <div class="form-group">
                            <label for="emptin">TIN:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpTIN']); ?>" name="pemptin" id="emptin" placeholder="TIN">
                        </div>
                        <div class="form-group">
                            <label for="empumid">UMID:</label>
                            <input type="text" class="form-control" value="<?php echo h($res['EmpUMIDNo']); ?>" name="pempumid" id="empumid" placeholder="UMID">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ 5) Family Details ============ -->
        <div class="ne-panel frm-fd">
            <div class="ne-card">
                <div class="ue-cardhead">
                    <div>
                        <h3 class="ne-card__title">Family Details</h3>
                        <p class="ne-card__sub">Contacts and emergency (ICE) details.</p>
                    </div>
                    <button type="button" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#Newmodalform"><i class="fa-solid fa-plus"></i> Add</button>
                </div>
                <table class="table tbl-relationship">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Relationship</th>
                                <th>Contact Number</th>
                                <th>ICE</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                if ($familyOverlay !== null) {
                                    $famRows = array_map(fn($r) => ['FName' => $r['name'], 'FAdd' => $r['address'], 'FRel' => $r['relationship'],
                                                                    'FContact' => $r['contact'], 'FICE' => $r['ice']], $familyOverlay);
                                } else {
                                    $fam = $pdo->prepare("SELECT * FROM fdetails WHERE FDetID = :id");
                                    $fam->execute([':id' => $q]);
                                    $famRows = $fam->fetchAll();
                                }
                                $id = 0;
                                foreach ($famRows as $row) {
                                    $id++;
                            ?>
                            <tr id="<?php echo $id; ?>">
                                <td class="name<?php echo $id; ?>"><?php echo h($row['FName']); ?></td>
                                <td class="add<?php echo $id; ?>"><?php echo h($row['FAdd']); ?></td>
                                <td class="rel<?php echo $id; ?>"><?php echo h($row['FRel']); ?></td>
                                <td class="con<?php echo $id; ?>"><?php echo h($row['FContact']); ?></td>
                                <td class="ice<?php echo $id; ?>"><?php echo h($row['FICE']); ?></td>
                                <td><button type="button" class="btn btn-link" onclick="myremovetr(this)">Remove</button></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                </table>
                <input type="hidden" name="non" id="rnum" value="<?php echo $id; ?>">
            </div>
        </div>

        <?php if (!$requestMode) { ?>
        <!-- ============ 6) Profile ============ -->
        <div class="ne-panel frm-prf">
            <div class="ne-card">
                <h3 class="ne-card__title">Profile Picture</h3>
                <p class="ne-card__sub">Choose a new photo, then click <b>Save changes</b>.</p>
                <div class="ne-prof">
                    <div class="prof-pic<?php echo $hasPhoto ? ' has-photo' : ''; ?>" id="profPreview"<?php if ($hasPhoto) { ?> style="background-image:url('<?php echo h($photo); ?>')"<?php } ?>></div>
                    <input type="file" name="file" id="file" accept="image/*">
                </div>
            </div>
        </div>

        <?php } ?>

    </form>

    <!-- ===================== Family Details modal ===================== -->
    <div class="modal ne-modal" id="Newmodalform">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Family Details</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="famname">Name</label>
                        <input type="text" placeholder="Firstname Middlename Lastname" required id="famname" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="famadd">Address</label>
                        <input type="text" placeholder="Address" id="famadd" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="rel">Relationship</label>
                        <select class="form-control" id="rel" required name="Frel">
                            <option></option>
                            <?php
                                $sql = mysqli_query($con, "SELECT * FROM frelationship");
                                while ($rrow = mysqli_fetch_array($sql)) { echo opt($rrow[0], $rrow[1]); }
                            ?>
                        </select>
                        <input type="text" style="display:none;margin-top:8px" id="relinput" placeholder="Relationship" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="famnumber">Contact Number</label>
                        <input type="text" id="famnumber" placeholder="Contact Number" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-check-label" style="display:inline-flex;align-items:center;gap:7px;font-weight:600">
                            <input type="checkbox" class="relice" value=""> Tag as ICE?
                        </label>
                    </div>
                    <button type="button" class="wd-btn wd-btn--primary btn-rel" style="width:100%;justify-content:center">Add to list</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$requestMode) { ?>
    <!-- ===================== Add Department modal ===================== -->
    <div class="modal ne-modal" id="modaladddep">
        <div class="modal-dialog modal-dialog-centered" style="max-width:750px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Add Department</h4>
                </div>
                <div class="modal-body">
                    <form method="post" class="frmdep">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Department Name:</label>
                                    <input type="text" name="depname" id="depname" class="form-control">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Company:</label>
                                    <select class="form-control" name="compid" id="compid">
                                        <?php
                                            if ($userType == 1) {
                                                $statement = $pdo->prepare("select * from companies");
                                            } else {
                                                $statement = $pdo->prepare("select * from companies where CompanyID=:cid");
                                            }
                                            $userType == 1 ? $statement->execute() : $statement->execute([':cid' => $compID]);
                                            while ($row = $statement->fetch()) {
                                                echo '<option value="' . htmlspecialchars($row['CompanyID']) . '">' . htmlspecialchars($row['CompanyDesc']) . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="wd-btn wd-btn--primary btndepartment" style="width:100%;justify-content:center">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== Add Position modal ===================== -->
    <div class="modal ne-modal" id="modaladdpos">
        <div class="modal-dialog modal-dialog-centered" style="max-width:750px">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Add Position</h4>
                </div>
                <div class="modal-body">
                    <form method="post" class="frmpos">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Company:</label>
                                    <select id="comchange" class="form-control">
                                        <?php
                                            if ($userType == 1) {
                                                $statement = $pdo->prepare("select * from companies");
                                                $statement->execute();
                                                echo "<option></option>";
                                            } else {
                                                $statement = $pdo->prepare("select * from companies where CompanyID=:cid");
                                                $statement->execute([':cid' => $compID]);
                                            }
                                            while ($row = $statement->fetch()) {
                                                echo '<option value="' . htmlspecialchars($row['CompanyID']) . '">' . htmlspecialchars($row['CompanyDesc']) . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Department:</label>
                                    <select id="dep" name="dep" required class="form-control">
                                        <?php
                                            $statement = $pdo->prepare("select * from departments inner join companies where departments.CompID=companies.CompanyID");
                                            $statement->execute();
                                            if ($userType == 1) { echo "<option></option>"; }
                                            while ($row = $statement->fetch()) {
                                                echo '<option id="' . htmlspecialchars($row['CompanyID']) . '" value="' . htmlspecialchars($row['DepartmentID']) . '">' . htmlspecialchars($row['DepartmentDesc']) . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Position:</label>
                                    <input type="text" name="pos" id="pos" required class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Job Level:</label>
                                    <select name="joblevel" id="joblevel" required class="form-control">
                                        <option></option>
                                        <?php
                                            $statement = $pdo->prepare("select * from joblevel");
                                            $statement->execute();
                                            while ($row = $statement->fetch()) {
                                                echo '<option value="' . htmlspecialchars($row['jobLevelID']) . '">' . htmlspecialchars($row['jobLevelDesc']) . '</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="wd-btn wd-btn--primary btnposition" style="width:100%;justify-content:center">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php } ?>

    <!-- used by script.js (.btn-rel) for "Please fill up empty fields" -->
    <div class="modal" id="modalWarning">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border:none">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body"><div class="alert alert-danger" style="margin:0"></div></div>
            </div>
        </div>
    </div>

    <script>
        /* save happens over AJAX (script-updateemployee.js); never submit natively —
           e.g. a stray Enter key or a non-typed button would reload the page */
        $('#fdata').on('submit', function (e) { e.preventDefault(); });

        /* step switcher */
        (function () {
            var PANELS = {
                'btn-ci': '.frm-ci', 'btn-eb': '.frm-eb', 'btn-es': '.frm-es',
                'btn-cdd': '.frm-cdd', 'btn-fd': '.frm-fd', 'btn-prf': '.frm-prf'
            };
            window.ueShowTab = function (id) {
                if (!PANELS[id]) { return; }
                $('#fdata > .ne-panel').removeClass('is-shown');
                $('#fdata > ' + PANELS[id]).addClass('is-shown');
                $('.ne-step').removeClass('is-active');
                $('#' + id).addClass('is-active');
            };
            $(document).on('click', '.ne-step', function () { window.ueShowTab(this.id); });
            $(document).on('change', '#file', function () { $('#profPreview').addClass('has-photo'); });
        })();

        /* required-field validation + per-tab badges (same rules as Enroll Employee) */
        (function () {
            // the same fields the old save gate checked (so existing records stay savable)
            var REQUIRED = {
                'btn-ci':  ['#empfname', '#emplname', '#empgender', '#empcs', '#empdob',
                            '#pempdistrict', '#pempcity', '#pempprovince', '#pempzipcode', '#pempcountry'],
                'btn-es':  ['#empID', '#idempposition', '#empdesccode', '#empis', '#empstatus', '#empdth', '#empbasic']
            };
            function isEmpty($el) {
                if (!$el.length || $el.closest('[hidden]').length) { return false; }
                var v = $el.val();
                return v == null || $.trim(String(v)) === '';
            }
            function tabEmpty(tabId, mark) {
                var count = 0;
                $.each(REQUIRED[tabId], function (i, sel) {
                    var $el = $(sel), empty = isEmpty($el);
                    if (empty) { count++; if (mark) { $el.addClass('is-invalid'); } }
                    else { $el.removeClass('is-invalid'); }
                });
                return count;
            }
            function refresh(mark) {
                var total = 0;
                $.each(REQUIRED, function (tabId) {
                    var c = tabEmpty(tabId, mark);
                    total += c;
                    var $b = $('#' + tabId + ' .ne-badge');
                    if (c > 0) { $b.attr('class', 'ne-badge ne-badge--warn').text(c); }
                    else { $b.attr('class', 'ne-badge ne-badge--ok').html('<i class="fa-solid fa-check"></i>'); }
                });
                return total;
            }
            window.ueValidateAll = function (mark) { return refresh(mark); };
            window.ueFirstIncompleteTab = function () {
                var first = null;
                $.each(REQUIRED, function (tabId) { if (!first && tabEmpty(tabId, false) > 0) { first = tabId; } });
                return first;
            };
            $('#fdata').on('input change', 'input, select, textarea', function () {
                if (!isEmpty($(this))) { $(this).removeClass('is-invalid'); }
                refresh(false);
            });
            refresh(false);
        })();

        /* Classification = Regular (1) asks for the regularization date */
        $('#empdesccode').on('change', function () {
            var reg = $(this).val() == 1;
            $('#dorGroup').prop('hidden', !reg);
            $('#dorInput').prop('required', reg);
        });
    </script>

<?php } ?>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
