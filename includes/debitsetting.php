<?php
/* ==========================================================================
   Debit Advise Settings  —  themed maintenance module (wedo-theme.css)
   Rendered standalone by maintenance.php?debitsetting, which handles the
   session + auth check before including this file. All element IDs below are
   JS hooks in assets/js/debitadvice.js — do not rename them.
   ========================================================================== */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php if (($_SESSION['CompanyName'] ?? '') == "") { echo "Dashboard"; } else { echo "Debit Advise Settings"; } ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (Bootstrap modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/debitadvice.js"></script>

    <style type="text/css">
        /* action buttons that debitadvice.js injects into the tables still carry
           Bootstrap .btn classes — theme them to match the wedo look */
        #tblBank .btn, #tblca .btn {
            border: none; border-radius: 8px; margin-right: 4px;
            font-family: var(--font-head); font-weight: 600; font-size: 12px;
            padding: 6px 11px; box-shadow: var(--shadow-sm);
        }
        #tblBank .btn i, #tblca .btn i { margin-right: 3px; }
        #tblBank .btn-primary, #tblca .btn-primary { background: var(--brand); color: #fff; }
        #tblBank .btn-primary:hover, #tblca .btn-primary:hover { background: var(--brand-600); }
        #tblBank .btn-danger, #tblca .btn-danger { background: var(--danger-text); color: #fff; }
        #tblBank .btn-danger:hover, #tblca .btn-danger:hover { filter: brightness(.93); }

        /* empty-state row shown until the first Refresh populates the table */
        .wd-empty td { text-align: center; color: var(--text-3); padding: 26px 20px; }

        /* CA/SA modal: keep the add-account row aligned */
        .ca-add { display: flex; gap: 10px; align-items: center; }
        .ca-add .wd-input { flex: 1; }
        #resultUpdate, #result { border-radius: var(--radius); }
    </style>
</head>
<body>
    <?php $wd_active = 'debitsetting'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Debit Advise Settings</h1>
            <p>Register bank contacts and their CA/SA account numbers for debit advise letters.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="button" id="new" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#myModal">
                <i class="fa-solid fa-plus"></i> Register Contact
            </button>
            <button type="button" id="refreshData" class="wd-btn wd-btn--ghost" title="Reload list">
                <i class="fa-solid fa-rotate-right"></i> Refresh
            </button>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Bank Contacts</h3>
        </div>
        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">Position</th>
                        <th scope="col">Branch</th>
                        <th scope="col">CA/SA Account</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody id="tblBank">
                    <tr class="wd-empty"><td colspan="6">Loading bank contacts&hellip;</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===== Register bank contact ===== -->
    <div class="modal fade" id="myModal" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Register Bank Contact Information</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Salutation</label>
                                <select id="salutation" class="form-control">
                                    <option value="Ms.">Ms.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Mr.">Mr.</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" class="form-control" id="fname">
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Initial</label>
                                <input type="text" class="form-control" id="initial">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" class="form-control" id="lname">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" class="form-control" id="position">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Branch</label>
                                <input type="text" class="form-control" id="branch">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>City Address</label>
                                <input type="text" class="form-control" id="cityaddress">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Other Branch</label>
                                <input type="text" class="form-control" id="otherbranch">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="result" class="alert" style="display:none;float:left"></div>
                    <button type="button" class="wd-btn wd-btn--primary" id="btnRegister"><i class="fa-solid fa-floppy-disk"></i> Register</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Update bank contact ===== -->
    <div class="modal fade" id="myModalUpdate" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Update Bank Contact Information</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Salutation</label>
                                <select id="salutationUpdate" class="form-control">
                                    <option value="Ms.">Ms.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Mr.">Mr.</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" class="form-control" id="fnameUpdate">
                            </div>
                        </div>
                        <div class="col-sm-2">
                            <div class="form-group">
                                <label>Initial</label>
                                <input type="text" class="form-control" id="initialUpdate">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" class="form-control" id="lnameUpdate">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Position</label>
                                <input type="text" class="form-control" id="positionUpdate">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Branch</label>
                                <input type="text" class="form-control" id="branchUpdate">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>City Address</label>
                                <input type="text" class="form-control" id="cityaddressUpdate">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Other Branch</label>
                                <input type="text" class="form-control" id="otherbranchUpdate">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="resultUpdate" class="alert" style="display:none;float:left"></div>
                    <button type="button" class="wd-btn wd-btn--primary" id="btnRegisterUpdate"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== CA/SA account numbers for a contact ===== -->
    <div class="modal fade" id="myModalCA" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title caname"></h4>
                </div>
                <div class="modal-body">
                    <div class="ca-add">
                        <input type="text" class="wd-input form-control" name="caNumber" id="caNumber" placeholder="Account Number" autocomplete="off">
                        <button type="button" class="wd-btn wd-btn--primary" id="saveca"><i class="fa-solid fa-plus"></i> Add</button>
                    </div>
                    <hr style="border:0;border-top:1px solid var(--border);margin:18px 0">
                    <div class="wd-tablewrap">
                        <table class="wd-table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">CA/SA Account</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tblca"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Populate the bank contact list on load (mirrors clicking Refresh).
        $(function () { $('#refreshData').click(); });
    </script>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
