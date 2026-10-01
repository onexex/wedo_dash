<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
    include 'w_conn.php';
    date_default_timezone_set("Asia/Manila");

    /* one PDO handle for the whole page (the modals used to each open their own) */
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("ERROR: Could not connect. " . $e->getMessage());
    }
    $userType = $_SESSION['UserType'];
    $compID   = isset($_SESSION['CompID']) ? $_SESSION['CompID'] : '';

    /* next auto employee number for the current company (non-admin users) */
    $nrows = (int) mysqli_num_rows(mysqli_query($con, "select * from empdetails where EmpCompID='" . $compID . "'"));
    $res1  = mysqli_fetch_array(mysqli_query($con, "select * from companies where CompanyID='" . $compID . "'"));
    $cnt   = ($nrows < 10) ? "000" : (($nrows < 100) ? "00" : "0");
    $em    = $res1 ? ($res1['compcode'] . '-' . $cnt . ($nrows + 1)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : "Enroll Employee"; ?></title>
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (Bootstrap modals + jQuery + FontAwesome 6 + SweetAlert) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-newemployee.js"></script>

    <link rel="stylesheet" href="assets/css/employee-form.css?v=<?php echo filemtime(__DIR__ . '/assets/css/employee-form.css'); ?>">
</head>
<body>
    <?php
        $wd_active = 'newemployee';
        include 'includes/wd-header.php';
    ?>

    <div class="wd-pagehead">
        <div>
            <h1>Enroll New Employee</h1>
            <p>Capture a new hire's profile across the sections below, then save. Fields marked <i style="color:var(--brand)">*</i> are required.</p>
        </div>
        <div class="ne-actions">
            <button type="button" class="wd-btn wd-btn--primary" id="submit-form"><i class="fa-solid fa-floppy-disk"></i> Save All</button>
        </div>
    </div>

    <!-- step navigation -->
    <div class="ne-steps">
        <button type="button" class="ne-step is-active" id="btn-ci"><span class="ne-step__t">General Information</span><span class="ne-badge"></span></button>
        <button type="button" class="ne-step" id="btn-eb"><span class="ne-step__t">Educational Background</span><span class="ne-badge"></span></button>
        <button type="button" class="ne-step" id="btn-es"><span class="ne-step__t">Employment Information</span><span class="ne-badge"></span></button>
        <button type="button" class="ne-step" id="btn-cdd"><span class="ne-step__t">Compliance Document Data</span><span class="ne-badge"></span></button>
        <button type="button" class="ne-step" id="btn-fd"><span class="ne-step__t">Family Details</span><span class="ne-badge"></span></button>
        <button type="button" class="ne-step" id="btn-prf"><span class="ne-step__t">Profile</span><span class="ne-badge"></span></button>
    </div>

    <form id="fdata" method="post" enctype="multipart/form-data">

        <!-- ============ 1) General Information ============ -->
        <div class="ne-panel frm-ci is-shown">
            <div class="ne-card">
                <h3 class="ne-card__title frm-title">General Information</h3>
                <p class="ne-card__sub">Personal details and complete mailing address.</p>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label for="uname">Create Username: <i>*</i></label>
                            <input type="text" class="form-control" required name="uname" id="uname" placeholder="Auto-filled from name — editable">
                            <small id="unameHint" class="ne-unamehint">Generated from first initial + last name. You can override it.</small>
                        </div>
                        <div class="form-group">
                            <label for="empfname">First Name: <i>*</i></label>
                            <input type="text" class="form-control" required name="empfn" id="empfname" placeholder="First Name">
                        </div>
                        <div class="form-group">
                            <label for="empmname">Middle Name:</label>
                            <input type="text" class="form-control" name="empmn" id="empmname" placeholder="Middle Name">
                        </div>
                        <div class="form-group">
                            <label for="emplname">Last Name: <i>*</i></label>
                            <input type="text" class="form-control" required name="empln" id="emplname" placeholder="Last Name">
                        </div>
                        <div class="form-group">
                            <label for="empsuff">Suffix:</label>
                            <input type="text" class="form-control" name="empsuff" id="empsuff" placeholder="Suffix">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label for="empgender">Gender: <i>*</i></label>
                            <select class="form-control" required name="pempgender" id="empgender">
                                <option value="Female">Female</option>
                                <option value="Male">Male</option>
                            </select>
                        </div>
                        <div class="form-group ne-ac">
                            <label for="empcitizen">Citizenship:</label>
                            <input type="text" class="form-control" id="empcitizen" name="empcitizen" autocomplete="off" placeholder="Citizenship">
                            <div class="cl-citizen">
                                <?php
                                    $sql2 = mysqli_query($con, "select DISTINCT EmpCitezen from empprofiles WHERE EmpCitezen IS NOT NULL");
                                    while ($res1c = mysqli_fetch_array($sql2)) {
                                        echo '<a class="ctzn-a">' . htmlspecialchars($res1c['EmpCitezen']) . '</a>';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="form-group ne-ac">
                            <label for="empreligion">Religion:</label>
                            <input type="text" class="form-control" id="empreligion" name="empreligion" autocomplete="off" placeholder="Religion">
                            <div class="cl-reli">
                                <?php
                                    $sql2 = mysqli_query($con, "select DISTINCT EmpReligion from empprofiles WHERE EmpReligion IS NOT NULL");
                                    while ($res1r = mysqli_fetch_array($sql2)) {
                                        echo '<a class="rel-a">' . htmlspecialchars($res1r['EmpReligion']) . '</a>';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="empphome">Home Phone Number:</label>
                            <input type="text" class="form-control" id="empphome" name="emphomenumber" placeholder="Home Phone Number">
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="form-group">
                            <label for="empdob">Date of Birth: <i>*</i></label>
                            <input type="date" class="form-control" id="empdob" name="pempdob" placeholder="Date of Birth">
                        </div>
                        <div class="form-group">
                            <label for="empcs">Civil Status: <i>*</i></label>
                            <select class="form-control" required id="empcs" name="pempcs">
                                <option></option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="phoneno">Mobile Number:</label>
                            <input type="text" class="form-control" id="phoneno" name="pempn0" placeholder="Mobile Number">
                        </div>
                        <div class="form-group">
                            <label for="empeadd">Email Address:</label>
                            <input type="email" class="form-control" autocomplete="off" id="empeadd" name="pempeadd" placeholder="Email Address">
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <label>Complete Mailing Address: <i>*</i></label>
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <input type="text" id="pempstreetno" name="pempstreetno" autocomplete="off" class="form-control" required placeholder="Street No / Street Name / Subdivision">
                                </div>
                                <div class="form-group">
                                    <input type="text" id="pempdistrict" name="pempdistrict" autocomplete="off" class="form-control" required placeholder="Barangay">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <input type="text" id="pempcity" name="pempcity" autocomplete="off" class="form-control" required placeholder="City">
                                </div>
                                <div class="form-group">
                                    <input type="text" id="pempprovince" name="pempprovince" autocomplete="off" class="form-control" required placeholder="Province">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <input type="text" id="pempzipcode" name="pempzipcode" autocomplete="off" class="form-control" required placeholder="Zip Code">
                                </div>
                                <div class="form-group">
                                    <input type="text" id="pempcountry" name="pempcountry" class="form-control" value="Philippines" required placeholder="Country">
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
                <h3 class="ne-card__title frm-title">Educational Background</h3>
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
                        <tr>
                            <td>Primary</td>
                            <td><input type="text" name="p_nameofschool" class="form-control"></td>
                            <td><input type="number" name="p_yearstarted" class="form-control"></td>
                            <td><input type="number" name="p_yeargraduated" class="form-control"></td>
                            <td><input type="text" name="p_schooladdress" class="form-control"></td>
                        </tr>
                        <tr>
                            <td>Secondary</td>
                            <td><input type="text" name="s_nameofschool" class="form-control"></td>
                            <td><input type="number" name="s_yearstarted" class="form-control"></td>
                            <td><input type="number" name="s_yeargraduated" class="form-control"></td>
                            <td><input type="text" name="s_schooladdress" class="form-control"></td>
                        </tr>
                        <tr>
                            <td>Tertiary</td>
                            <td><input type="text" name="t_nameofschool" class="form-control"></td>
                            <td><input type="number" name="t_yearstarted" class="form-control"></td>
                            <td><input type="number" name="t_yeargraduated" class="form-control"></td>
                            <td><input type="text" name="t_schooladdress" class="form-control"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============ 3) Employment Information ============ -->
        <div class="ne-panel frm-es">
            <div class="ne-card">
                <h3 class="ne-card__title frm-title">Employment Information</h3>
                <p class="ne-card__sub">Company, role, compensation and employment dates.</p>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="EmployeeIDNumber">Employee ID:</label>
                                    <input type="text" class="form-control" name="EmployeeIDNumber" id="EmployeeIDNumber" placeholder="Employee ID">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empID">Employee Number: <i>*</i></label>
                                    <?php if ($userType == 1): ?>
                                        <input type="text" readonly class="form-control" required name="empidn" id="empID" placeholder="Employee Number">
                                    <?php else: ?>
                                        <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($em); ?>" required name="empidn" id="empID" placeholder="Employee Number">
                                    <?php endif; ?>
                                    <input type="hidden" name="empcheck" id="empcheck">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="empcompid">Company:</label>
                            <select class="form-control" id="empcompid" name="empcompany">
                                <?php
                                    if ($userType == 1) {
                                        $sql = mysqli_query($con, "select * from companies");
                                        echo '<option></option>';
                                    } else {
                                        $sql = mysqli_query($con, "select * from companies where CompanyID='" . $compID . "'");
                                    }
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option value="' . htmlspecialchars($res['CompanyID']) . '">' . htmlspecialchars($res['CompanyDesc']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Department: <i>*</i> <i class="fa-solid fa-plus ne-addpl" data-toggle="modal" data-target="#modaladddep" id="depselect" aria-hidden="true"></i></label>
                            <?php
                                if ($userType == 1) {
                                    $sql = mysqli_query($con, "select * from departments");
                                    echo '<select class="form-control DepEmp" id="empdepartment" required name="empdep"><option></option>';
                                } else {
                                    $sql = mysqli_query($con, "select * from departments where CompID='" . $compID . "'");
                                    echo '<select class="form-control DepEmp" id="empdepartment1" required name="empdep"><option></option>';
                                }
                                while ($res = mysqli_fetch_array($sql)) {
                                    echo '<option class="dep' . htmlspecialchars($res['CompID']) . '" value="' . htmlspecialchars($res[0]) . '">' . htmlspecialchars($res[1]) . '</option>';
                                }
                                echo '</select>';
                            ?>
                        </div>

                        <div class="form-group">
                            <label>Position: <i>*</i> <i class="fa-solid fa-plus ne-addpl Pos_Sel" data-toggle="modal" data-target="#modaladdpos" aria-hidden="true"></i></label>
                            <select class="form-control" id="idempposition" required name="empposition">
                                <option></option>
                                <?php
                                    $sql = mysqli_query($con, "select * from positions");
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option class="pos' . htmlspecialchars($res['DepartmentID']) . '" value="' . htmlspecialchars($res['PSID']) . '">' . htmlspecialchars($res['PositionDesc']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="empdesccode">Classification: <i>*</i></label>
                            <select class="form-control" id="empdesccode" required name="empclassification">
                                <option></option>
                                <?php
                                    $sql = mysqli_query($con, "select * from empstatus");
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option value="' . htmlspecialchars($res['EmpStatID']) . '">' . htmlspecialchars($res['EmpStatDesc']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="empis">Immediate Superior: <i>*</i></label>
                            <select class="form-control" id="empis" required name="empis">
                                <option>N/A</option>
                                <?php
                                    $sql = mysqli_query($con, "select * from employees as a inner join empdetails as b on a.EmpID=b.EmpID where a.EmpStatusID='1' and EmpRoleID!='3'");
                                    while ($row = mysqli_fetch_array($sql)) {
                                        echo '<option class="is' . htmlspecialchars($row['EmpdepID']) . '" value="' . htmlspecialchars($row['EmpID']) . '">' . htmlspecialchars($row['EmpFN'] . " " . $row['EmpLN']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="empstatus">Status: <i>*</i></label>
                            <select class="form-control" required id="empstatus" name="empst">
                                <option></option>
                                <?php
                                    $sql = mysqli_query($con, "select * from estatus");
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option value="' . htmlspecialchars($res['ID']) . '">' . htmlspecialchars($res['StatusEmpDesc']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="emppp">Previous Position:</label>
                            <input type="text" class="form-control" name="pemppp" id="emppp" placeholder="Previous Position">
                        </div>
                        <div class="form-group">
                            <label for="emppsd">Start Date:</label>
                            <input type="date" class="form-control" name="pemppsd" id="emppsd" placeholder="Start Date">
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="idempjoblevel">Job Level:</label>
                            <select class="form-control" id="idempjoblevel" required name="empjoblevel">
                                <option></option>
                                <?php
                                    $sql = mysqli_query($con, "select * from joblevel");
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option value="' . htmlspecialchars($res['jobLevelID']) . '">' . htmlspecialchars($res['jobLevelDesc']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="idempagency">Agency:</label>
                            <select class="form-control" id="idempagency" required name="empagency">
                                <option></option>
                                <?php
                                    $sql = mysqli_query($con, "select * from agency");
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option value="' . htmlspecialchars($res['AgencyID']) . '">' . htmlspecialchars($res['AgencyName']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="idemphmo">HMO Number:</label>
                            <input type="text" id="idemphmo" class="form-control" name="emphmo">
                        </div>
                        <div class="form-group">
                            <label for="idhmoprovider">HMO Provider:</label>
                            <select class="form-control" id="idhmoprovider" required name="emphmoprovider">
                                <option></option>
                                <?php
                                    $sql = mysqli_query($con, "select * from hmo");
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option value="' . htmlspecialchars($res['HMO_ID']) . '">' . htmlspecialchars($res['HMO_PROVIDER']) . '</option>';
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empdth">Date Hired: <i>*</i></label>
                                    <input type="date" class="form-control" id="empdth" value="<?php echo date('Y-m-d'); ?>" required name="empdatehired">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empdtr">Date Resigned:</label>
                                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" name="empdateresigned" id="empdtr">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empbasic">Basic: <i>*</i></label>
                                    <input type="text" class="form-control" required value="1.00" id="empbasic" name="empbasic" placeholder="1">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="empallowance">Allowance:</label>
                                    <input type="text" class="form-control" id="empallowance" value="1.00" name="empallowance" placeholder="1">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="emphourlyrate">Hourly Rate:</label>
                            <input type="text" class="form-control" id="emphourlyrate" value="1.00" name="emphourlyrate" placeholder="1">
                        </div>
                        <div class="form-group">
                            <label for="emppdept">Previous Department:</label>
                            <input type="text" class="form-control" name="pemppdept" id="emppdept" placeholder="Department">
                        </div>
                        <div class="form-group">
                            <label for="empppd">Previous Designation:</label>
                            <input type="text" class="form-control" name="pempppd" id="empppd" placeholder="Designation">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ 4) Compliance Document Data ============ -->
        <div class="ne-panel frm-cdd">
            <div class="ne-card">
                <h3 class="ne-card__title frm-title">Compliance Document Data</h3>
                <p class="ne-card__sub">Government IDs and passport details.</p>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="empppno">Passport Number:</label>
                            <input type="text" class="form-control" name="pempppno" id="empppno" placeholder="Passport Number">
                        </div>
                        <div class="form-group">
                            <label for="emppped">Passport Expiry Date:</label>
                            <input type="date" class="form-control" name="pemppped" id="emppped" placeholder="Expiry Date">
                        </div>
                        <div class="form-group">
                            <label for="empppia">Issuing Authority:</label>
                            <input type="text" class="form-control" name="pempppia" id="empppia" placeholder="Issuing Authority">
                        </div>
                        <div class="form-group">
                            <label for="emppagibig">PAG-IBIG:</label>
                            <input type="text" class="form-control" name="pemppagibig" id="emppagibig" placeholder="PAG-IBIG">
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="empphno">PhilHealth:</label>
                            <input type="text" class="form-control" name="pempphno" id="empphno" placeholder="PhilHealth">
                        </div>
                        <div class="form-group">
                            <label for="empsss">SSS Number:</label>
                            <input type="text" class="form-control" name="pempsss" id="empsss" placeholder="SSS Number">
                        </div>
                        <div class="form-group">
                            <label for="emptin">TIN:</label>
                            <input type="text" class="form-control" name="pemptin" id="emptin" placeholder="TIN">
                        </div>
                        <div class="form-group">
                            <label for="empumid">UMID:</label>
                            <input type="text" class="form-control" name="pempumid" id="empumid" placeholder="UMID">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ 5) Family Details ============ -->
        <div class="ne-panel frm-fd">
            <div class="ne-card">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px">
                    <div>
                        <h3 class="ne-card__title frm-title">Family Details</h3>
                        <p class="ne-card__sub" style="margin:0">Contacts and emergency (ICE) details.</p>
                    </div>
                    <button type="button" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#myModalrel"><i class="fa-solid fa-plus"></i> Add</button>
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
                        <input type="hidden" name="non" id="rnum" value="0">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============ 6) Profile ============ -->
        <div class="ne-panel frm-prf">
            <div class="ne-card">
                <h3 class="ne-card__title frm-title">Profile Picture</h3>
                <p class="ne-card__sub">Upload a photo, then click <b>Save All</b> to enroll the employee.</p>
                <div class="ne-prof">
                    <div class="prof-pic" id="profPreview"></div>
                    <input type="file" name="file" id="file" accept="image/*">
                </div>
            </div>
        </div>

    </form>

    <!-- ===================== Family Details modal ===================== -->
    <div class="modal ne-modal" id="myModalrel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Family Details</h4>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" placeholder="Firstname Middlename Lastname" required id="famname" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" placeholder="Address" id="famadd" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Relationship</label>
                            <select class="form-control" id="rel" required name="Frel">
                                <option></option>
                                <option value="16">Sister</option>
                                <option value="16">Brother</option>
                                <option value="16">Mother</option>
                                <option value="16">Father</option>
                                <option value="16">Cousin</option>
                                <option value="16">Grandmother</option>
                                <option value="16">Grandfather</option>
                                <option value="16">Spouse</option>
                                <?php
                                    $sql = mysqli_query($con, "select * from fdetails");
                                    while ($res = mysqli_fetch_array($sql)) {
                                        echo '<option value="' . htmlspecialchars($res[0]) . '">' . htmlspecialchars($res[1]) . '</option>';
                                    }
                                ?>
                            </select>
                            <input type="text" style="display:none;margin-top:8px" id="relinput" placeholder="Relationship" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" id="famnumber" placeholder="Contact Number" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-check-label" style="display:inline-flex;align-items:center;gap:7px;font-weight:600">
                                <input type="checkbox" class="relice" value=""> Tag as ICE?
                            </label>
                        </div>
                        <button type="button" class="wd-btn wd-btn--primary btn-rel" style="width:100%;justify-content:center">Add to list</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

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

    <!-- ===================== Warning modals ===================== -->
    <div class="modal ne-modal" id="myModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Warning</h4>
                </div>
                <div class="modal-body">Under Construction......</div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

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

    <div class="modal" id="modalWarning2">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border:none">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body"><div class="alert alert-success" style="margin:0"></div></div>
            </div>
        </div>
    </div>

    <script>
        /* Self-contained step switcher (replaces the legacy .wd-login-coupled
           handler in script.js, which collided with the theme's login styles). */
        (function () {
            var PANELS = {
                'btn-ci': '.frm-ci', 'btn-eb': '.frm-eb', 'btn-es': '.frm-es',
                'btn-cdd': '.frm-cdd', 'btn-fd': '.frm-fd', 'btn-prf': '.frm-prf'
            };
            $(document).on('click', '.ne-step', function () {
                var id = this.id;
                if (!PANELS[id]) { return; }
                $('#fdata > .ne-panel').removeClass('is-shown');
                $('#fdata > ' + PANELS[id]).addClass('is-shown');
                $('.ne-step').removeClass('is-active');
                $(this).addClass('is-active');
            });
            /* hide the placeholder icon once a photo is chosen (script.js sets the bg image) */
            $(document).on('change', '#file', function () { $('#profPreview').addClass('has-photo'); });
        })();

        /* ------------------------------------------------------------------ *
         *  Required-field validation + live per-tab count badges
         *  Required fields = the ones marked with * in each section's labels.
         * ------------------------------------------------------------------ */
        (function () {
            var REQUIRED = {
                'btn-ci':  ['#uname', '#empfname', '#emplname', '#empgender', '#empcs', '#empdob',
                            '#pempstreetno', '#pempdistrict', '#pempcity', '#pempprovince', '#pempzipcode', '#pempcountry'],
                'btn-eb':  [],
                'btn-es':  ['#empID', '.DepEmp', '#idempposition', '#empdesccode', '#empis', '#empstatus', '#empdth', '#empbasic'],
                'btn-cdd': [],
                'btn-fd':  [],
                'btn-prf': []
            };

            function isEmpty($el) {
                if (!$el.length) { return false; }          // variant not on page (admin/non-admin) → skip
                var v = $el.val();
                return v == null || $.trim(String(v)) === '';
            }

            function tabEmpty(tabId, mark) {
                var count = 0;
                $.each(REQUIRED[tabId] || [], function (i, sel) {
                    var $el = $(sel), empty = isEmpty($el);
                    if (empty) { count++; if (mark) { $el.addClass('is-invalid'); } }
                    else { $el.removeClass('is-invalid'); }   // a filled field never stays red
                });
                return count;
            }

            function refresh(mark) {
                var total = 0;
                $.each(REQUIRED, function (tabId, list) {
                    var c = tabEmpty(tabId, mark);
                    total += c;
                    var $b = $('#' + tabId + ' .ne-badge');
                    if (!(list || []).length) { $b.attr('class', 'ne-badge').empty(); return; }   // no required fields → no badge
                    if (c > 0) { $b.attr('class', 'ne-badge ne-badge--warn').text(c); }
                    else { $b.attr('class', 'ne-badge ne-badge--ok').html('<i class="fa-solid fa-check"></i>'); }
                });
                return total;
            }

            /* exposed for the submit handler in script-newemployee.js */
            window.neValidateAll = function (mark) { return refresh(mark); };
            window.neFirstIncompleteTab = function () {
                var first = null;
                $.each(REQUIRED, function (tabId, list) {
                    if (!first && (list || []).length && tabEmpty(tabId, false) > 0) { first = tabId; }
                });
                return first;
            };

            /* live recount as the user types / selects; clear a field's red mark once filled */
            $('#fdata').on('input change', 'input, select, textarea', function () {
                if (!isEmpty($(this))) { $(this).removeClass('is-invalid'); }
                refresh(false);
            });
            /* reset (after a successful save) → recount on the next tick */
            $('#fdata').on('reset', function () { setTimeout(function () { refresh(false); }, 0); });

            refresh(false);   // initial badges
        })();

        /* ------------------------------------------------------------------ *
         *  Auto-generate a UNIQUE login username = first initial + last name.
         *  The server resolves collisions (Ramon Gemana → RGemana, Rio Gemana
         *  → RiGemana). Prefills as the name is typed, but stops the moment the
         *  admin edits the username themselves (resumes if they clear it).
         * ------------------------------------------------------------------ */
        (function () {
            var touched = false, timer = null;
            var $hint = $('#unameHint');
            function hint(txt, cls) { $hint.attr('class', 'ne-unamehint ' + (cls || '')).text(txt); }

            function autoGen() {
                var fn = $.trim($('#empfname').val());
                var ln = $.trim($('#emplname').val());
                if (!fn || !ln) { return; }
                $.post('query/Query-checkusername.php', { fn: fn, ln: ln }, function (res) {
                    if (touched || !res || !res.username) { return; }   // admin took over meanwhile
                    $('#uname').val(res.username);
                    if (window.neValidateAll) { window.neValidateAll(false); }
                    hint('Auto-generated · unique username', 'is-ok');
                }, 'json');
            }

            function checkTyped() {
                var u = $.trim($('#uname').val());
                if (!u) { hint('', ''); return; }
                $.post('query/Query-checkusername.php', { u: u }, function (res) {
                    if (!res) { return; }
                    if (res.taken) { hint('“' + u + '” is already taken — try “' + res.username + '”', 'is-bad'); }
                    else { hint('Available', 'is-ok'); }
                }, 'json');
            }

            $('#empfname, #emplname').on('input', function () {
                if (touched) { return; }
                clearTimeout(timer); timer = setTimeout(autoGen, 350);   // debounce
            });
            $('#uname').on('input', function () {
                touched = true;                                          // manual edit wins
                clearTimeout(timer); timer = setTimeout(checkTyped, 350);
            });
            $('#uname').on('blur', function () {                          // cleared → resume auto-gen
                if ($.trim(this.value) === '') { touched = false; hint('Generated from first initial + last name. You can override it.', ''); }
            });
        })();
    </script>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
