<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
    date_default_timezone_set("Asia/Manila");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Dashboard" : "Booklet Registry"; ?></title>

    <!-- Functional libs (jQuery + Bootstrap 3 modals + FontAwesome 6) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <!-- Page behaviour: change-password modal + jquery.dialog + booklet AJAX -->
    <script type="text/javascript" src="assets/js/script.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/jquery.dialog.css">
    <script type="text/javascript" src="assets/js/jquery.dialog.js"></script>
    <script type="text/javascript" src="assets/js/booklet.js"></script>

    <style type="text/css">
        /* add-bank inline form in the card head */
        .bk-addbank { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .bk-addbank .wd-input { width:240px; }

        /* status/result alerts sit full-width under the card head */
        .bk-alert { margin:14px 20px 0; }
        #result1.bk-alert { margin:14px 0 0; }

        /* compact action buttons inside the tables (built by booklet.js) */
        .wd-iconbtn--sm { width:32px; height:32px; font-size:14px; border-radius:8px; }
        .bk-actions { display:flex; gap:8px; }
        .bk-edit:hover { color:var(--info-text); border-color:var(--info-text); }
        .bk-del:hover  { color:var(--danger-text); border-color:var(--danger-text); }

        /* Active / Inactive series toggle (built by booklet.js) — pill-styled button */
        .bk-status { appearance:none; -webkit-appearance:none; cursor:pointer; border:1px solid transparent;
            border-radius:var(--radius-pill); padding:4px 13px; font-family:var(--font-head); font-weight:700;
            font-size:11.5px; letter-spacing:.03em; display:inline-flex; align-items:center; gap:6px; transition:.15s; }
        .bk-status::before { content:""; width:7px; height:7px; border-radius:50%; }
        .bk-status--on  { background:var(--ok-bg); color:var(--ok-text); }
        .bk-status--on::before  { background:var(--ok-text); }
        .bk-status--off { background:var(--surface-2); color:var(--text-3); }
        .bk-status--off::before { background:var(--text-3); }
        .bk-status:hover { filter:brightness(.96); }

        /* booklet-number modal: brand header */
        #exampleModalCenter .modal-header { background:var(--brand); }
        #exampleModalCenter .modal-title,
        #exampleModalCenter .modal-header .close { color:#fff; }
        .bk-serieshead { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:4px; }
        .bk-serieshead .wd-field { margin-bottom:0; flex:1; min-width:120px; }
    </style>
</head>
<body>
    <?php
        $wd_active = 'bookletregistry';
        include 'includes/wd-header.php';
    ?>

    <div class="wd-pagehead">
        <div>
            <h1>Booklet Registry</h1>
            <p>Register banks and their cheque booklet number series.</p>
        </div>
    </div>

    <div class="wd-card bk-addbank-card">
        <div class="wd-card__head">
            <h3>Registered Banks</h3>
            <form class="bk-addbank" onsubmit="return false;">
                <input id="bankname" type="text" class="wd-input" placeholder="Enter bank name&hellip;">
                <button id="store" type="button" class="wd-btn wd-btn--primary"><i class="fa-solid fa-plus"></i> Add Bank</button>
            </form>
        </div>

        <div id="result" class="bk-alert" style="display:none"></div>

        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>Bank</th>
                        <th style="width:1%">Action</th>
                    </tr>
                </thead>
                <tbody id="tblbankbookle"></tbody>
            </table>
        </div>
    </div>

    <!-- Booklet number series modal -->
    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalLongTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="exampleModalLongTitle">Booklet Number Series</h4>
                </div>
                <div class="modal-body">
                    <div class="bk-serieshead">
                        <div class="wd-field">
                            <label for="start">Start</label>
                            <input id="start" type="number" class="wd-input" placeholder="Starting no.&hellip;">
                        </div>
                        <div class="wd-field">
                            <label for="end">Ending</label>
                            <input id="end" type="number" class="wd-input" placeholder="Ending no.&hellip;">
                        </div>
                        <button id="addnew" type="button" class="wd-btn wd-btn--primary"><i class="fa-solid fa-plus"></i></button>
                    </div>

                    <div id="result1" class="bk-alert" style="display:none"></div>

                    <div class="wd-tablewrap" style="margin-top:16px">
                        <table class="wd-table">
                            <thead>
                                <tr>
                                    <th>Start</th>
                                    <th>Ending</th>
                                    <th>Status</th>
                                    <th style="width:1%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tblpayeereg"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
