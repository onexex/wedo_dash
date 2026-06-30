<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
        // authorised
    } else {
        header('location: login');
        exit;
    }

    date_default_timezone_set('Asia/Manila');

    /* Map a status description to a themed pill colour. Guarded so it coexists
       with the same helper on other migrated pages / shared includes. */
    if (!function_exists('wd_status_pill')) {
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
    }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Overtime Viewer</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (Bootstrap modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/script-reports.js"></script>

    <!-- Print the report area in landscape (hooks preserved: #btnprint #tblprint #tab #tabth #darviewer .captionText #captionText #dateRange #empcompid #dtp1 #dtp2) -->
    <script type="text/javascript">
        $(document).ready(function () {
            $(document).on('click', '#btnprint', function (e) {
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
                $("#darviewer td").css("padding", "9px");
                $("#darviewer td").css("text-align", "center");
                $("#darviewer td").css("font-size", "10px");
                $("#tabth th").css("font-size", "10px");
                $("#tab").css({ overflow: 'hidden', height: '100%' });
                $("#tab").css({ overflow: 'auto', height: 'auto' });
                $(".captionText").css("font-size", "12px");
                $(".captionText").removeClass("d-none").addClass("d-block");
                $("#captionText").html($("#empcompid option:selected").text());
                $("#dateRange").html($("#dtp1").val() + " To : " + $("#dtp2").val());

                var printContents = document.getElementById('tblprint').innerHTML;

                document.body.innerHTML = printContents;
                window.print();
                document.body.innerHTML = originalContents;
                $(".captionText").removeClass("d-block").addClass("d-none");
            });
        });

        function exportToExcel(tableID, filename = '') {
            var downloadurl;
            var dataFileType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById("tab");
            var tableHTMLData = tableSelect.outerHTML.replace(/ /g, '%20');
            filename = "OTReports_" + document.getElementById("dtp1").value + "_" + document.getElementById("dtp2").value + "_" + document.getElementById("empcompid").options[document.getElementById("empcompid").selectedIndex].text;
            filename = filename ? filename + '.xls' : 'export_excel_data.xls';

            downloadurl = document.createElement("a");
            document.body.appendChild(downloadurl);

            if (navigator.msSaveOrOpenBlob) {
                var blob = new Blob(['﻿', tableHTMLData], { type: dataFileType });
                navigator.msSaveOrOpenBlob(blob, filename);
            } else {
                downloadurl.href = 'data:' + dataFileType + ', ' + tableHTMLData;
                downloadurl.download = filename;
                downloadurl.click();
            }
        }
    </script>

    <style>
        /* Print caption helpers (Bootstrap 4 d-none/d-block aren't in BS3) */
        .d-none { display: none !important; }
        .d-block { display: block !important; }
        .captionText { font-weight: 600; color: var(--text); margin: 2px 0; }
        /* keep the export/print bar tidy */
        .wd-card__foot { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--border); flex-wrap: wrap; }
        @media print {
            .captionText { display: block !important; }
            /* The on-screen table body scrolls inside .wd-tablewrap (max-height:40vh).
               Un-clip it for print so ALL rows render, not just the visible window. */
            .wd-tablewrap { max-height: none !important; overflow: visible !important; border: 0 !important; }
            .wd-table td, .wd-table th { white-space: normal; }
            /* force the status pill backgrounds/colours to actually print */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>

<body>
    <?php $wd_active = 'overtimeviewer'; include 'includes/wd-header.php'; ?>

    <?php
        try {
            include 'w_conn.php';
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("ERROR: Could not connect. " . $e->getMessage());
        }
    ?>

    <div class="wd-pagehead">
        <div>
            <h1>Overtime Viewer</h1>
            <p>Review filed overtime attendance across employees and date ranges.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Overtime reports</h3>
            <form id="votdata" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin:0">
                <div class="wd-field" style="margin:0">
                    <label for="empcompid">Choose Employee</label>
                    <select class="wd-select" id="empcompid" name="empcompany" style="min-width:230px">
                        <option value="all">All</option>
                        <?php
                            $sql = mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpID<>'admin' and employees.EmpStatusID = 1 and EmpCompID='WeDoInc-01' order by EmpLN asc");
                            while ($res = mysqli_fetch_array($sql)) {
                        ?>
                        <option value="<?php echo $res['EmpID']; ?>"><?php echo $res['EmpLN'] . ", " . $res['EmpFN'] . " " . $res['EmpMN']; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="dtp1">From</label>
                    <input type="date" class="wd-input" id="dtp1" style="width:auto;padding:7px 10px"
                        value="<?php echo date('Y-m-d', strtotime(date("Y-m-d") . ' - 15 days')); ?>">
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="dtp2">To</label>
                    <input type="date" class="wd-input" id="dtp2" style="width:auto;padding:7px 10px"
                        value="<?php echo date("Y-m-d"); ?>">
                </div>
                <button class="wd-btn wd-btn--ghost btnovb" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            </form>
        </div>

        <div id="tblprint">
            <!-- print-only captions (shown by #btnprint handler) -->
            <label class="captionText d-none" id="captionTextMain">Overtime Report</label>
            <label class="captionText d-none" id="captionText">All Employees</label>
            <label class="captionText d-none" id="dateRange"></label>

            <div class="wd-tablewrap">
                <table class="wd-table" id="tab">
                    <thead id="tabth">
                        <tr>
                            <th>No</th>
                            <th>Name</th>
                            <th>Date/Time Filed</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Duration</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="darviewer">
                        <?php
                            try {
                                $sql = "SELECT b.EmpID as Employees_ID, b.EmpFN as FirstName, b.EmpMN as MiddleName, b.EmpLN as LastName,
                                        a.DateFiling as DateFiling, a.TimeFiling as TimeFiling, a.TimeIn as Time_IN, a.TimeOut as Time_OUT,
                                        a.Duration as Duration, a.Purpose as Purpose, c.StatusDesc as Status
                                        FROM otattendancelog as a
                                        INNER JOIN employees as b ON a.EmpID=b.EmpID
                                        INNER JOIN status as c ON a.Status=c.StatusID
                                        WHERE StatusID=1 or StatusID=2 or StatusID=4
                                        ORDER BY b.EmpLN ASC, b.EmpFN ASC";
                                $statement = $pdo->prepare($sql);
                                $statement->execute();
                            } catch (Exception $e) {
                                echo 'Caught exception: ', $e->getMessage(), "\n";
                            }
                            $ids = 0;
                            if (!empty($statement->rowCount())) {
                                while ($row = $statement->fetch()) {
                                    $ids++;
                        ?>
                        <tr>
                            <td><?php echo $ids; ?></td>
                            <td><?php echo htmlspecialchars($row["LastName"] . ', ' . $row["FirstName"]); ?></td>
                            <td><?php echo date("F j, Y", strtotime($row["DateFiling"])) . ' ' . date("h:i:s A", strtotime($row["TimeFiling"])); ?></td>
                            <td><?php echo date("F j, Y h:i:s A", strtotime($row["Time_IN"])); ?></td>
                            <td><?php echo date("F j, Y h:i:s A", strtotime($row["Time_OUT"])); ?></td>
                            <td><?php echo htmlspecialchars($row["Duration"]); ?></td>
                            <td><?php echo htmlspecialchars($row["Purpose"]); ?></td>
                            <td><?php echo wd_status_pill($row["Status"]); ?></td>
                        </tr>
                        <?php
                                }
                            }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wd-card__foot">
            <button class="wd-btn wd-btn--ghost" id="btnprint" type="button"><i class="fa-solid fa-print"></i> Print</button>
            <button class="wd-btn wd-btn--primary" type="button" onclick="exportToExcel('tab', 'user-data')"><i class="fa-solid fa-file-excel"></i> Export to Excel</button>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>
</body>

</html>
