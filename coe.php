<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
    include 'w_conn.php';
      date_default_timezone_set("Asia/Manila");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Certificate of Employment"; } ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (Bootstrap modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/coe.js"></script>

    <style type="text/css">
        /* toolbar: employee picker + print button */
        .coe-toolbar { display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; padding:20px; }
        .coe-toolbar .wd-field { margin-bottom:0; min-width:260px; flex:1; }

        /* document preview shown as a paper sheet */
        .coe-paperwrap { padding:24px; background:var(--surface-2); display:flex; justify-content:center; }
        .coe-paper {
            width:100%; max-width:820px; min-height:760px; position:relative;
            background:#fff; border:1px solid var(--border); border-radius:var(--radius);
            box-shadow:var(--shadow); padding:56px 60px 130px;
            font-family:Tahoma, Geneva, sans-serif; color:#1a1a1a; line-height:1.75;
        }
        .coe-paper p { margin:0 0 14px; font-size:14.5px; text-align:justify; }
        .coe-greeting { margin-bottom:26px !important; }

        /* dynamic values (JS fills the .fullname/.position/... hooks) */
        .coe-val { font-weight:700; color:var(--text); }
        .coe-val:empty { display:inline-block; min-width:120px; border-bottom:1px dotted #aaa; }

        .coe-sign { margin-top:54px; }
        .coe-sign img { display:block; width:150px; margin-bottom:2px; }
        .coe-sign .coe-signname { font-weight:700; }

        .coe-letterhead { position:absolute; left:60px; right:60px; bottom:34px;
            border-top:1px solid var(--border); padding-top:14px; }
        .coe-letterhead img { width:100px; }
        .coe-address { color:#c0392b; font-size:12.5px; margin:8px 0 0; }

        /* ---- print (body-swap: coe.js replaces document.body with #forprint
           innerHTML, so these rules MUST live in <head> to survive) ---- */
        @media print {
            @page { size:auto; margin:20mm; }
            body { margin:0; padding:0; height:100%; font-family:Tahoma, sans-serif; color:#000; }
            p { font-size:14px; text-align:justify; }
            label { font-weight:normal !important; }
            .coe-val { font-weight:700; }
            .coe-val:empty { border:0; min-width:0; }
            .coe-sign { margin-top:60px; }
            .coe-sign img { width:150px; }
            .coe-letterhead { position:fixed; left:0; right:0; bottom:0; border:0; }
            .coe-address { color:#c0392b; font-size:13px; }
        }
    </style>
</head>
<body>
    <?php $wd_active = 'coe'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Certificate of Employment</h1>
            <p>Select an employee and print an official certificate of employment.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Generate certificate</h3>
        </div>

        <div class="coe-toolbar">
            <div class="wd-field">
                <label for="employeelist">Select employee</label>
                <select class="wd-select" id="employeelist"></select>
            </div>
            <button class="wd-btn wd-btn--primary printthis"><i class="fa-solid fa-print"></i> Print</button>
        </div>
    </section>

    <section class="wd-card" style="margin-top:20px">
        <div class="wd-card__head">
            <h3>Preview</h3>
            <span class="wd-pill wd-pill--info">Issued <?php echo date("M d, Y"); ?></span>
        </div>

        <div class="coe-paperwrap">
            <!-- #forprint is the print source (coe.js body-swaps this innerHTML). All
                 .coe-val hook classes (fullname/position/Salutation/lastname/datehired/
                 basic/heshe/selfintro) are filled by coe.js — do not rename them. -->
            <div id="forprint" class="coe-paper">
                <p class="coe-greeting">To whom it may concern;</p>

                <p>
                    This is to certify that <span class="coe-val fullname"></span>
                    is presently connected with WeDo BPO Inc. In
                    <span class="coe-val selfintro"></span> capacity as
                    <span class="coe-val position"></span>
                    <span class="coe-val Salutation"></span><span class="coe-val lastname"></span>
                    has been connected with this company since
                    <span class="coe-val datehired"></span>.
                </p>

                <p>
                    <span class="coe-val Salutation"></span><span class="coe-val lastname"></span>
                    receives a gross monthly compensation of Pesos
                    <span class="coe-val basic"></span>, exclusive of overtime pay, bonuses and
                    other salary-related fringe benefits, of which
                    <span class="coe-val heshe"></span> entitled to receive.
                </p>

                <p>Should you have clarifications, please call 02.84704131 or 63.917.7240123 anytime during office hours.</p>

                <p>This certification is issued at the request of <span class="coe-val fullname"></span> for whatever legal purpose it may serve.</p>

                <p>Issued on <?php echo date("M-d-Y"); ?>, at Pasig City, Philippines.</p>

                <p>Thank you.</p>

                <div class="coe-sign">
                    <img src="assets/images/sign/ogm.png" alt="Signature">
                    <p class="coe-signname">Jose Modesto A. Ferrer</p>
                    <p>General Manager - WeDo BPO Inc.</p>
                </div>

                <div class="coe-letterhead">
                    <img src="assets/images/logos/WeDo.png" alt="WeDo BPO Inc.">
                    <p class="coe-address">1901 Antel Global Corporate Center #3 J. Vargas Ave. Ortigas Business District, Pasig City 1605</p>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
