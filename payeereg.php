<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
    include 'w_conn.php';
    date_default_timezone_set("Asia/Manila");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : "Payee Registry"; ?></title>
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (Bootstrap modals + jQuery + FontAwesome 6) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/payeeregistry.js"></script>

    <style type="text/css">
        /* add-payee form: three fields on one line, wraps on narrow screens */
        .pr-addgrid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 14px; align-items: end; }
        @media (max-width: 640px) { .pr-addgrid { grid-template-columns: 1fr; } }
        .pr-addgrid .wd-field { margin: 0; }

        /* flash alert lives just under the form */
        #result { margin: 14px 0 0; }
        #result .close { float: right; font-size: 20px; line-height: 1; opacity: .6; cursor: pointer; }
        #result .close:hover { opacity: 1; }

        /* filter box in the list card head */
        .pr-filter { display: flex; align-items: center; gap: 8px; }
        .pr-filter .wd-input { width: 220px; max-width: 46vw; padding: 8px 11px; }

        /* delete button injected per row by payeeregistry.js */
        #tblpayeereg .wd-iconbtn { color: var(--danger-text); border-color: var(--border); }
        #tblpayeereg .wd-iconbtn:hover { background: var(--danger-bg); border-color: var(--danger-text); }

        .pr-empty td { text-align: center; color: var(--text-3); padding: 22px; }
    </style>
</head>
<body>
    <?php
        $wd_active = 'payeereg';
        include 'includes/wd-header.php';
    ?>

    <div class="wd-pagehead">
        <div>
            <h1>Payee Management System</h1>
            <p>Register payees and their customer account numbers (CAN) for disbursements.</p>
        </div>
    </div>

    <!-- Add payee -->
    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Add a payee</h3>
        </div>
        <div style="padding:18px 20px">
            <div class="pr-addgrid">
                <div class="wd-field">
                    <label for="payee">Payee <a style="color:var(--brand)">*</a></label>
                    <input id="payee" type="text" class="wd-input" placeholder="Payee name" autocomplete="off">
                </div>
                <div class="wd-field">
                    <label for="can">Customer Account Number <a style="color:var(--brand)">*</a></label>
                    <input id="can" type="text" class="wd-input" placeholder="Account number" autocomplete="off">
                </div>
                <div class="wd-field">
                    <button id="store" type="button" class="wd-btn wd-btn--primary"><i class="fa-solid fa-plus"></i> Add payee</button>
                </div>
            </div>
            <div id="result" class="alert alert-success" style="display:none"></div>
        </div>
    </section>

    <!-- Registered payees -->
    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Registered payees <span class="wd-pill wd-pill--info" style="margin-left:6px"><span id="payeecount">0</span></span></h3>
            <div class="pr-filter">
                <i class="fa-solid fa-magnifying-glass" style="color:var(--text-3)"></i>
                <input id="payeefilter" type="text" class="wd-input" placeholder="Filter payees&hellip;" autocomplete="off">
            </div>
        </div>
        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th scope="col">Payee</th>
                        <th scope="col">CAN</th>
                        <th scope="col" style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody id="tblpayeereg">
                    <tr class="pr-empty"><td colspan="3">Loading payees&hellip;</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
