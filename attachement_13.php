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
                if ((!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash']) && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time()))) {
                    $_SESSION['id'] = $row['EmpID'];

                    $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                    $statement->bindParam(':un', $_SESSION['id']);
                    $statement->execute();
                    $count = $statement->rowCount();
                    $row = $statement->fetch();
                    $hash = $row['EmpPW'];
                    $_SESSION['UserType'] = $row['EmpRoleID'];
                    $cid = $row['EmpCompID'];
                    $_SESSION['CompID'] = $row['EmpCompID'];
                    $_SESSION['EmpISID'] = $row['EmpISID'];
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

                } else {

                }
            }
        }
    }

}

date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>13th Month Attachment</title>
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
    <script type="text/javascript" src="assets/js/general.js"></script>

    <!-- Print the report area in landscape.
         Hooks preserved: .btnprint #btngen1Att #toprint .header_data .chk13_month .thisdate #cutOFd1 #cutOFd2 #reportview -->
    <script type="text/javascript">
        $(document).ready(function () {
            $(document).on('click', '.btnprint', function (e) {
                var css = '@page { size: landscape; }',
                    head = document.head || document.getElementsByTagName('head')[0],
                    style = document.createElement('style');

                style.type = 'text/css';
                style.media = 'print';

                if (style.styleSheet) {
                    style.styleSheet.cssText = css;
                } else {
                    style.appendChild(document.createTextNode(css));
                }
                head.appendChild(style);
                var originalContents = document.body.innerHTML;

                $(".header_data").css("font-size", "12px");
                $(".header_data").removeClass("d-none").addClass("d-block");
                $(".chk13_month").removeClass("d-block").addClass("d-none");

                $(".thisdate").html($("#cutOFd1").val() + " To : " + $("#cutOFd2").val());

                var printContents = document.getElementById('toprint').innerHTML;

                document.body.innerHTML = printContents;
                window.print();
                document.body.innerHTML = originalContents;
                $(".header_data").removeClass("d-block").addClass("d-none");
                $(".chk13_month").removeClass("d-none").addClass("d-block");
            });
        });
    </script>

    <style>
        /* Bootstrap-4 display utilities aren't in BS3 — the print handler toggles
           .d-none/.d-block on the report header, so define them here (page-scoped,
           in <head> so they survive the document.body.innerHTML swap on print). */
        .d-none { display: none !important; }
        .d-block { display: block !important; }

        .modal-backdrop { background-color: transparent; }

        /* .wd-card has no inner padding of its own; pad the body (the table body
           keeps its own edge-to-edge scroll box via .wd-tablewrap). */
        .wd-card__body { padding: 16px 20px; }
        .wd-card__body .wd-tablewrap { margin: 0 -20px -16px; }

        /* on-screen report header (logo + date range) is print-only */
        .header_data { align-items: center; gap: 16px; margin: 0 0 8px; }
        .header_data img { max-height: 54px; width: auto; }
        .header_data h3 { margin: 6px 0 0; }
        .chk13_month { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; color: var(--text-2); }
        .chk13_month label { margin: 0; font-weight: 600; }

        @media print {
            /* Tahoma for the printed report only (don't fight the theme on screen) */
            body { font-family: Tahoma !important; }
            .header_data { display: flex !important; }
            /* the on-screen table body scrolls inside .wd-tablewrap (max-height:40vh);
               un-clip it for print so ALL rows render, not just the visible window. */
            .wd-tablewrap { max-height: none !important; overflow: visible !important; border: 0 !important; }
            .wd-table td, .wd-table th { white-space: normal; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>

<body>
    <?php $wd_active = 'attachement_13'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>13<sup>th</sup> Month Attachment</h1>
            <p>Per-payroll gross and allowance breakdown used to compute 13<sup>th</sup> month pay.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Attachment report</h3>
            <form style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin:0">
                <div class="wd-field" style="margin:0">
                    <label for="cutOFd1">From</label>
                    <input type="date" class="wd-input" id="cutOFd1" name="cutOFd1" value="2023-11-20" style="width:auto;padding:7px 10px">
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="cutOFd2">To</label>
                    <input type="date" class="wd-input" id="cutOFd2" name="cutOFd2" value="2024-04-05" style="width:auto;padding:7px 10px">
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="selEmpAtt">Employee</label>
                    <select class="wd-select" id="selEmpAtt" style="min-width:230px">
                        <option value="all">All</option>
                        <?php
                        $sql = mysqli_query($con, "select * from employees where EmpStatusID='1' and EmpID<>'WeDoinc-003' order by EmpLN asc");
                        while ($res = mysqli_fetch_array($sql)) {
                        ?>
                        <option value="<?php echo htmlspecialchars($res['EmpID']); ?>"><?php echo htmlspecialchars($res['EmpLN'] . ", " . $res['EmpMN'] . " " . $res['EmpFN']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <button class="wd-btn wd-btn--primary" id="btnViewAtt" type="button"><i class="fa-solid fa-eye"></i> View</button>
                <button class="wd-btn wd-btn--ghost btnprint" id="btngen1Att" type="button"><i class="fa-solid fa-print"></i> Print</button>
            </form>
        </div>

        <div class="wd-card__body">
            <div id="result" class="alert" style="display:none"></div>

            <div id="toprint">
                <div class="header_data d-none">
                    <img src="assets/images/logo-2.png" alt="WeDo BPO">
                    <div class="subtitle">
                        <h3>WeDo BPO</h3>
                        <h5 class="thisdate"> </h5>
                    </div>
                </div>

                <div class="form-check chk13_month">
                    <input type="checkbox" class="form-check-input" id="chk13Att" name="chk13Att">
                    <label class="form-check-label" for="chk13Att">Filter for 13 Month</label>
                </div>

                <div class="wd-tablewrap">
                    <table class="wd-table" id="reportview">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Pay period</th>
                                <th>Gross</th>
                                <th>Allowance</th>
                                <th>13<sup>th</sup> Month (BASIC)</th>
                                <th>13<sup>th</sup> Month (BASIC+ALLOWANCE)</th>
                            </tr>
                        </thead>
                        <tbody id="dataAtt">

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>
</body>

</html>
