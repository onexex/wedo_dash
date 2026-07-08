<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    /* --- auth: session, or re-auth from the "remember me" cookie ------------ */
    if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
        // already signed in
    } else {
        if (!isset($_COOKIE["WeDoID"])) {
            header('location: login'); exit;
        }
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
            if ((!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash']) && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time()))) {
                $_SESSION['id'] = $row['EmpID'];
                $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                $statement->bindParam(':un', $_SESSION['id']);
                $statement->execute();
                $row  = $statement->fetch();
                $hash = $row['EmpPW'];
                $_SESSION['UserType'] = $row['EmpRoleID'];
                $cid = $row['EmpCompID'];
                $_SESSION['CompID']   = $row['EmpCompID'];
                $_SESSION['EmpISID']  = $row['EmpISID'];
                $statement = $pdo->prepare("select * from companies where CompanyID = :pw");
                $statement->bindParam(':pw', $cid);
                $statement->execute();
                $comcount = $statement->rowCount();
                $row = $statement->fetch();
                if ($comcount > 0) {
                    $_SESSION['CompanyName'] = $row['CompanyDesc'];
                    $_SESSION['CompanyLogo'] = $row['logopath'];
                    $_SESSION['CompanyColor'] = $row['comcolor'];
                } else {
                    $_SESSION['CompanyName'] = "ADMIN";
                    $_SESSION['CompanyLogo'] = "";
                    $_SESSION['CompanyColor'] = "red";
                }
                $_SESSION['PassHash'] = $hash;
            }
        }
    }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login'); exit; }
    date_default_timezone_set("Asia/Manila");

    /* the seven weekdays, rendered once for the create modal and once as the
       column list — kept in one place so create/quick-fill stay in sync */
    $wd_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : "Scheduler"; ?></title>
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (Bootstrap modals + jQuery + FontAwesome 6) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/scheduler.js"></script>

    <style type="text/css">
        /* required-field hint toggled by scheduler.js (.show()/.hide()) */
        .formlabel { display: none; color: var(--danger-text); font-size: 12px; margin-top: 4px; }

        /* two-up rows inside the create modal; collapse on phones */
        .sch-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        @media (max-width: 560px) { .sch-grid { grid-template-columns: 1fr; } }

        /* the seven day selects laid out in a responsive grid */
        .sch-daygrid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        @media (max-width: 560px) { .sch-daygrid { grid-template-columns: 1fr; } }

        /* multi-employee picker */
        .sch-emppick { border: 1px solid var(--border-2); border-radius: var(--radius); overflow: hidden; }
        .sch-emppick__bar { display: flex; align-items: center; gap: 10px; padding: 8px 10px;
            background: var(--surface-2); border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .sch-emppick__bar .wd-input { flex: 1; min-width: 140px; padding: 7px 11px; }
        .sch-checkall { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px;
            font-weight: 600; color: var(--text-2); white-space: nowrap; cursor: pointer; margin: 0; }
        .sch-emplist { max-height: 190px; overflow-y: auto; padding: 4px; }
        .sch-empitem { display: flex; align-items: center; gap: 9px; padding: 7px 9px; border-radius: 7px;
            font-size: 13px; color: var(--text); cursor: pointer; margin: 0; }
        .sch-empitem:hover { background: var(--surface-2); }
        .sch-empitem input { width: 15px; height: 15px; accent-color: var(--brand); cursor: pointer; }
        .sch-empitem.is-hidden { display: none; }
        .sch-empempty { padding: 14px; text-align: center; color: var(--text-3); font-size: 13px; }
        .sch-empcount { padding: 8px 12px; border-top: 1px solid var(--border); background: var(--surface-2);
            font-size: 12.5px; color: var(--text-2); }
        .sch-empcount b { color: var(--brand); }

        /* a subtle section heading for "Weekly schedule" */
        .sch-subhead { display: block; font-family: var(--font-head); font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .05em; color: var(--text-3);
            margin: 6px 0 12px; padding-top: 14px; border-top: 1px solid var(--border); }

        /* action buttons injected into rows by scheduler.js keep wd-btn styling */
        #scheduler .wd-btn, #daytime .wd-btn { margin: 2px 4px 2px 0; }

        /* "Show N entries" selector in the card head */
        .sch-show { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-2); }
        .sch-show .wd-select { width: auto; padding: 7px 30px 7px 11px; }

        /* filter toolbar */
        .sch-filters { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; padding: 16px 20px; border-bottom: 1px solid var(--border); }
        .sch-filters .wd-field { margin: 0; flex: 1 1 160px; min-width: 150px; }
        .sch-filters .wd-btn { flex: 0 0 auto; }

        /* result count strip */
        .sch-meta { padding: 9px 20px; font-size: 12.5px; color: var(--text-3); background: var(--surface-2); border-bottom: 1px solid var(--border); }
        .sch-meta b { color: var(--text-2); }

        @media print {
            .wd-sidebar, .wd-topbar { display: none !important; }
        }
    </style>
</head>
<body>
    <?php
        $wd_active = 'scheduler';
        include 'includes/wd-header.php';
    ?>

    <div class="wd-pagehead">
        <div>
            <h1>Employee Scheduler</h1>
            <p>Assign weekly work schedules and effectivity periods &mdash; to one employee or many at once.</p>
        </div>
        <button type="button" id="newschedreg" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#scheduleradd">
            <i class="fa-solid fa-plus"></i> New Schedule
        </button>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Registered schedules</h3>
            <div class="sch-show">
                <span>Show</span>
                <select id="limit" class="wd-select">
                    <option value="10">10</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="500">500</option>
                </select>
                <span>entries</span>
            </div>
        </div>

        <div class="sch-filters">
            <div class="wd-field">
                <label for="name">Employee last name</label>
                <input type="text" class="wd-input" id="name" placeholder="e.g. Alatiit" autocomplete="off">
            </div>
            <div class="wd-field">
                <label for="fdatefrom">Effectivity from</label>
                <input type="date" class="wd-input" id="fdatefrom">
            </div>
            <div class="wd-field">
                <label for="fdateto">Effectivity to</label>
                <input type="date" class="wd-input" id="fdateto">
            </div>
            <button type="button" id="applyfilter" class="wd-btn wd-btn--primary"><i class="fa-solid fa-filter"></i> Filter</button>
            <button type="button" id="clearfilter" class="wd-btn wd-btn--ghost"><i class="fa-solid fa-xmark"></i> Clear</button>
        </div>

        <div class="sch-meta">Showing <b id="schedcount">0</b> schedule(s)</div>

        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Effectivity From</th>
                        <th scope="col">Effectivity To</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody id="scheduler">
                    <tr><td colspan="4" style="text-align:center;color:var(--text-3)">Loading schedules&hellip;</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===================== Create schedule modal ===================== -->
    <div class="modal" id="scheduleradd" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Create Employee Schedule</h4>
                </div>
                <div class="modal-body">
                    <form id="entersched" name="entersched" autocomplete="off">

                        <div class="wd-field">
                            <label>Employees <a style="color:var(--brand)">*</a> <span class="wd-muted" style="font-weight:400">— pick one or more to give them the same schedule</span></label>
                            <div class="sch-emppick">
                                <div class="sch-emppick__bar">
                                    <input type="text" class="wd-input" id="empfilter" placeholder="Filter employees&hellip;">
                                    <label class="sch-checkall"><input type="checkbox" id="empselectall"> Select all</label>
                                </div>
                                <div class="sch-emplist" id="employee">
                                    <div class="sch-empempty">Loading employees&hellip;</div>
                                </div>
                                <div class="sch-empcount"><b id="empselcount">0</b> employee(s) selected</div>
                            </div>
                            <small id="lblemployee" class="formlabel">Please select at least one employee!</small>
                        </div>

                        <div class="sch-grid">
                            <div class="wd-field">
                                <label for="dfrom">Effectivity From <a style="color:var(--brand)">*</a></label>
                                <input type="date" class="wd-input" id="dfrom">
                                <small id="lbldfrom" class="formlabel">This is a required field!</small>
                            </div>
                            <div class="wd-field">
                                <label for="dto">Effectivity To <a style="color:var(--brand)">*</a></label>
                                <input type="date" class="wd-input" id="dto">
                                <small id="lbldto" class="formlabel">This is a required field!</small>
                            </div>
                        </div>

                        <label class="sch-subhead">Weekly schedule</label>

                        <div class="wd-field">
                            <label for="setalldays"><i class="fa-solid fa-wand-magic-sparkles"></i> Quick fill &mdash; set every day to</label>
                            <select id="setalldays" class="wd-select"></select>
                        </div>

                        <div class="sch-daygrid">
                            <?php foreach ($wd_days as $d): $lc = strtolower($d); ?>
                            <div class="wd-field">
                                <label for="<?php echo $d; ?>"><?php echo $d; ?> <a style="color:var(--brand)">*</a></label>
                                <select id="<?php echo $d; ?>" class="wd-select sch-dayselect"></select>
                                <small id="lbl<?php echo $lc; ?>" class="formlabel">This is a required field!</small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                    <div id="schedresult" class="alert alert-success" style="display:none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--primary" id="save"><i class="fa-solid fa-floppy-disk"></i> Save schedule</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== View schedule modal ===================== -->
    <div class="modal" id="viewsched" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Employee Schedule</h4>
                </div>
                <div class="modal-body">
                    <div class="wd-tablewrap" style="max-height:60vh">
                        <table class="wd-table">
                            <thead>
                                <tr>
                                    <th scope="col">Day</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Update</th>
                                </tr>
                            </thead>
                            <tbody id="daytime"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== Update time modal ===================== -->
    <div class="modal" id="updatetime" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Update Time</h4>
                </div>
                <div class="modal-body">
                    <div class="wd-field" style="margin:0">
                        <label for="timedata">Time <a style="color:var(--brand)">*</a></label>
                        <select id="timedata" class="wd-select"></select>
                        <small id="lbltimeupdate" class="formlabel">This is a required field!</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--primary" id="updatetimesched"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== Update effectivity modal ===================== -->
    <div class="modal" id="updateeffective" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Update Effectivity</h4>
                </div>
                <div class="modal-body">
                    <div class="wd-field">
                        <label for="efdfrom">Date From <a style="color:var(--brand)">*</a></label>
                        <input type="date" class="wd-input" id="efdfrom">
                        <small id="lblefdfrom" class="formlabel">This is a required field!</small>
                    </div>
                    <div class="wd-field" style="margin:0">
                        <label for="efdto">Date To <a style="color:var(--brand)">*</a></label>
                        <input type="date" class="wd-input" id="efdto">
                        <small id="lblefdto" class="formlabel">This is a required field!</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--primary" id="updateEffecDate1"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
