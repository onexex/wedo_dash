<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
    include 'w_conn.php';
    date_default_timezone_set("Asia/Manila");

    /* Single source of truth for the position dropdowns so the Register and
       Update modals always offer the exact same (de-duplicated) list. Any value
       already stored in amsarchive.pos can therefore be re-selected on edit. */
    function ams_position_options() {
        $positions = [
            'Programmer', 'IT Supervisor', 'Outbound Sales Agent', 'Outbound Specialist',
            'Inbound Sales Agent', 'Inbound Technical Support Personnel', 'CSR', 'Encoder', 'TSR',
            'Graphic Designer', 'Staff-OGM', 'Quality Auditor', 'Research Analyst', 'Sales Specialist',
            'Database Administrator', 'Team Supervisor', 'Security Personnel', 'IT Support Specialist',
            'Project Manager', 'General Manager', 'Operation Manager', 'SNS Administrator', 'Utility Personnel',
            'Team Leader', 'Company Driver', 'Admin Staff', 'Admin and Finance Manager',
            'Remote Contact Center Associates', 'Outbound Telemarketing Specialist',
        ];
        $out = '<option selected>Choose...</option>';
        foreach ($positions as $p) {
            $out .= '<option>' . htmlspecialchars($p) . '</option>';
        }
        return $out;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : "AMS"; ?></title>

    <!-- Functional libs (Bootstrap modals + jQuery + FontAwesome 6) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/ams.js"></script>

    <style type="text/css">
        /* two-up form rows inside the modals; collapse to one column on phones */
        .ams-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        @media (max-width: 560px) { .ams-grid { grid-template-columns: 1fr; } }

        .ams-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }

        /* required-field hint toggled by ams.js (.show()/.hide()) */
        .formlabel { display: none; color: var(--danger-text); font-size: 12px; margin-top: 4px; }

        /* View button injected into rows by ams.js (btn btn-primary btn-sm) — theme it */
        #amstable .btn { display: inline-flex; align-items: center; gap: 6px; border: 0;
            border-radius: var(--radius); padding: 7px 12px; font-size: 12.5px; font-weight: 600;
            background: var(--brand); color: #fff; cursor: pointer; }
        #amstable .btn:hover { background: var(--brand-600); }

        /* let the archive table print at full height without the app chrome */
        @media print {
            @page { size: portrait; }
            .wd-sidebar, .wd-topbar, .ams-actions, .ams-search, .hide_cell { display: none !important; }
            .wd-tablewrap { max-height: none; overflow: visible; }
        }
    </style>
</head>
<body>
    <?php
        $wd_active = 'ams';
        include 'includes/wd-header.php';
    ?>

    <div class="wd-pagehead">
        <div>
            <h1>Archived Management System</h1>
            <p>Register separated employees and keep their clearance, records and remarks on file.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Employee archive</h3>
        </div>

        <div style="padding:16px 20px">
            <div class="ams-actions">
                <button type="button" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#amsview"><i class="fa-solid fa-user-plus"></i> Register Employee</button>
                <button type="button" class="wd-btn wd-btn--ghost" id="viewData"><i class="fa-solid fa-list"></i> Show All</button>
                <button type="button" class="wd-btn wd-btn--ghost" id="btnprint"><i class="fa-solid fa-print"></i> Print</button>
            </div>

            <div class="wd-field ams-search" style="margin:0">
                <label for="amssearch">Search last name</label>
                <input type="text" class="wd-input" id="amssearch" placeholder="Start typing a last name&hellip;" autocomplete="off">
            </div>
        </div>

        <div id="tblprint" class="wd-tablewrap">
            <table class="wd-table" id="tab">
                <thead id="tabth">
                    <tr>
                        <th>Name</th>
                        <th>Position / Title / Level</th>
                        <th>Status</th>
                        <th>Verified By</th>
                        <th class="hide_cell">Action</th>
                    </tr>
                </thead>
                <tbody id="amstable">
                    <tr><td colspan="5" style="text-align:center;color:var(--text-3)">Click &ldquo;Show All&rdquo;, or search a last name.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===================== Register modal ===================== -->
    <div class="modal fade" id="amsview" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Registration Form</h4>
                </div>
                <div class="modal-body">
                    <form id="amsid">
                        <div class="ams-grid">
                            <div class="wd-field">
                                <label for="fname">First Name</label>
                                <input type="text" class="wd-input" id="fname" placeholder="First Name">
                                <small id="lblfname" class="formlabel">This is a required field!</small>
                            </div>
                            <div class="wd-field">
                                <label for="lname">Last Name</label>
                                <input type="text" class="wd-input" id="lname" placeholder="Last Name">
                                <small id="lbllname" class="formlabel">This is a required field!</small>
                            </div>
                        </div>

                        <div class="wd-field">
                            <label for="pos">Position</label>
                            <select id="pos" class="wd-select"><?php echo ams_position_options(); ?></select>
                            <small id="lblpos" class="formlabel">This is a required field!</small>
                        </div>

                        <label class="wd-field-heading" style="display:block;font-size:12.5px;font-weight:600;color:var(--text-2);margin-bottom:6px">Employment Date</label>
                        <div class="ams-grid">
                            <div class="wd-field">
                                <label for="dfrom">From</label>
                                <input type="date" class="wd-input" id="dfrom">
                            </div>
                            <div class="wd-field">
                                <label for="dto">To</label>
                                <input type="date" class="wd-input" id="dto">
                            </div>
                        </div>

                        <div class="ams-grid">
                            <div class="wd-field">
                                <label for="status">Employment Classification</label>
                                <select id="status" class="wd-select">
                                    <option>Back End</option>
                                    <option>IC</option>
                                    <option>TR</option>
                                    <option>Probationary</option>
                                </select>
                            </div>
                            <div class="wd-field">
                                <label for="clerance">Clearance</label>
                                <select id="clerance" class="wd-select">
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="wd-field">
                            <label for="reason">Reason for Leaving</label>
                            <input type="text" id="reason" class="wd-input">
                        </div>

                        <div class="wd-field">
                            <label for="derogatory">Derogatory Records</label>
                            <textarea id="derogatory" class="wd-textarea" rows="4"></textarea>
                        </div>

                        <div class="wd-field">
                            <label for="addrem">Additional Remarks</label>
                            <textarea id="addrem" class="wd-textarea" rows="2"></textarea>
                        </div>

                        <!-- kept for the backend contract; not user-facing -->
                        <input type="hidden" id="salary" value="0">
                        <input type="hidden" id="resignation" value="n/a">
                        <input type="hidden" id="addver" value="<?php echo (int) $_SESSION['id']; ?>">
                    </form>
                    <div id="result" class="alert alert-success" style="display:none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--primary" id="save"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== Update modal ===================== -->
    <div class="modal fade" id="amsshow" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Employee Update</h4>
                </div>
                <div class="modal-body">
                    <form id="amsidu">
                        <div class="ams-grid">
                            <div class="wd-field">
                                <label for="fname1">First Name</label>
                                <input type="text" class="wd-input" id="fname1" placeholder="First Name">
                                <small id="lblfname1" class="formlabel">This is a required field!</small>
                            </div>
                            <div class="wd-field">
                                <label for="lname1">Last Name</label>
                                <input type="text" class="wd-input" id="lname1" placeholder="Last Name">
                                <small id="lbllname1" class="formlabel">This is a required field!</small>
                            </div>
                        </div>

                        <div class="wd-field">
                            <label for="pos1">Position</label>
                            <select id="pos1" class="wd-select"><?php echo ams_position_options(); ?></select>
                            <small id="lblpos1" class="formlabel">This is a required field!</small>
                        </div>

                        <label class="wd-field-heading" style="display:block;font-size:12.5px;font-weight:600;color:var(--text-2);margin-bottom:6px">Employment Date</label>
                        <div class="ams-grid">
                            <div class="wd-field">
                                <label for="dfrom1">From</label>
                                <input type="date" class="wd-input" id="dfrom1">
                            </div>
                            <div class="wd-field">
                                <label for="dto1">To</label>
                                <input type="date" class="wd-input" id="dto1">
                            </div>
                        </div>

                        <div class="ams-grid">
                            <div class="wd-field">
                                <label for="status1">Employment Classification</label>
                                <select id="status1" class="wd-select">
                                    <option>Back End</option>
                                    <option>IC</option>
                                    <option>TR</option>
                                    <option>Probationary</option>
                                </select>
                            </div>
                            <div class="wd-field">
                                <label for="clerance1">Clearance</label>
                                <select id="clerance1" class="wd-select">
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                        </div>

                        <div class="wd-field">
                            <label for="reason1">Reason for Leaving</label>
                            <input type="text" id="reason1" class="wd-input">
                        </div>

                        <div class="wd-field">
                            <label for="derogatory1">Derogatory Records</label>
                            <textarea id="derogatory1" class="wd-textarea" rows="4"></textarea>
                        </div>

                        <div class="wd-field">
                            <label for="addrem1">Additional Remarks</label>
                            <textarea id="addrem1" class="wd-textarea" rows="2"></textarea>
                        </div>

                        <!-- kept for the backend contract; not user-facing -->
                        <input type="hidden" id="salary1" value="0">
                        <input type="hidden" id="resignation1" value="n/a">
                        <input type="hidden" id="addver1" value="<?php echo (int) $_SESSION['id']; ?>">
                    </form>
                    <div id="result1" class="alert alert-success" style="display:none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--primary" id="update"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        $(document).ready(function () {
            // Print just the archive table: swap in its markup, print, restore.
            // Delegated ams.js handlers bind on $(document) so they survive the swap.
            $(document).on('click', '#btnprint', function () {
                var original = document.body.innerHTML;
                $('.hide_cell').hide();                       // drop the Action column
                document.body.innerHTML = document.getElementById('tblprint').innerHTML;
                window.print();
                document.body.innerHTML = original;
            });
        });
    </script>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
