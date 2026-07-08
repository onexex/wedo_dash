<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
        // authorised
    } else {
        header('location: login');
        exit;
    }

    include 'ReportController.php';
    $handle = new ReportController();
    $dt  = date('Y-m-d');
    $dt2 = date('Y-m-1');
    $dt3 = date('Y-m-16');
    $ddt  = date('Y-m-d', strtotime(date('Y-m-1')));
    $ddt2 = date('Y-m-d', strtotime(date('Y-m-1')));
    if ($dt > $dt2 && $dt < $dt3) { $dt1 = $ddt2; } else { $dt1 = $ddt; }
    $dt2 = date('Y-m-d', strtotime('+1 days'));
    if ($_SESSION['UserType'] == 1) {
        $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employees_ID,employees.EmpFN as FirstName,
            employees.EmpMN as MiddleName,employees.EmpLN as LastName,dars.DarDateTime as Date_Time,dars.EmpActivity as Activity
            FROM dars INNER JOIN employees on dars.EmpID=employees.EmpID
            INNER JOIN empdetails on employees.EmpID=empdetails.EmpID
            WHERE (DarDateTime between '$dt1' and '$dt2') and empdetails.EmpCompID='" . $_SESSION['CompID'] . "' order by LastName,dars.DarDateTime");
    } else {
        $resultdata = $handle->runQuery(" SELECT employees.EmpID as Employees_ID,employees.EmpFN as FirstName,
            employees.EmpMN as MiddleName,employees.EmpLN as LastName,dars.DarDateTime as Date_Time,dars.EmpActivity as Activity
            FROM dars INNER JOIN employees on dars.EmpID=employees.EmpID
            INNER JOIN empdetails on employees.EmpID=empdetails.EmpID
            WHERE (DarDateTime between '$dt1' and '$dt2') and empdetails.EmpCompID='" . $_SESSION['CompID'] . "' and (EmpISID='" . $_SESSION['id'] . "' or  employees.EmpID='" . $_SESSION['id'] . "') order by LastName,dars.DarDateTime desc ");
    }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Daily Activity Report Viewer</title>
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
    <script type="text/javascript" src="assets/js/script-reports.js"></script>

    <!-- Loading spinner during initial page load (hook preserved: #modalWarning) -->
    <script type="text/javascript">
        document.onreadystatechange = function () {
            var m = document.getElementById("modalWarning");
            if (!m) { return; }
            m.style.display = (document.readyState !== "complete") ? "block" : "none";
        };
    </script>

    <!-- Print the report area in landscape (hooks preserved: #btnprint #tblprint #tab1 #darviewer .captionText #captionText #dateRange #empcompid #datefrom #dateto) -->
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
                $("#darviewer td").css({ padding: '9px', 'text-align': 'left', 'font-size': '10px' });
                $("#tabth th").css("font-size", "10px");
                $(".captionText").css("font-size", "12px").removeClass("d-none").addClass("d-block");
                $("#captionText").html($("#empcompid option:selected").text());
                $("#dateRange").html($("#datefrom").val() + " To : " + $("#dateto").val());

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
            var tableSelect = document.getElementById("tab1");
            var tableHTMLData = tableSelect.outerHTML.replace(/ /g, '%20');
            filename = "DAR_" + document.getElementById("datefrom").value + "_" + document.getElementById("dateto").value + "_" + document.getElementById("empcompid").options[document.getElementById("empcompid").selectedIndex].text;
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

        .wd-card__foot { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--border); flex-wrap: wrap; }

        /* Loading overlay — NOT a Bootstrap .modal on purpose: wd-footer blanket-adds
           .fade to every .modal and the theme keeps .modal.fade at opacity:0 until
           .in is added, so a $.toggle()-shown BS modal would render invisible. */
        #modalWarning {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1080;
            background: rgba(9, 21, 46, .35);
        }
        #modalWarning .wd-loadbox {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--surface, #fff);
            border-radius: var(--radius-lg, 14px);
            padding: 26px 34px;
            box-shadow: 0 24px 60px rgba(16, 24, 40, .28);
            text-align: center;
        }

        @media print {
            .captionText { display: block !important; }
            /* The on-screen table body scrolls inside .wd-tablewrap (max-height:40vh).
               Un-clip it for print so ALL rows render, not just the visible window. */
            .wd-tablewrap { max-height: none !important; overflow: visible !important; border: 0 !important; }
            .wd-table td, .wd-table th { white-space: normal; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>

<body>
    <?php $wd_active = 'dar'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Daily Activity Report Viewer</h1>
            <p>Review Daily Activity Report (DAR) entries across employees and date ranges.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Activity reports</h3>
            <form id="dardata" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin:0">
                <div class="wd-field" style="margin:0">
                    <label for="empcompid">Choose Employee</label>
                    <select class="wd-select" id="empcompid" name="empcompany" style="min-width:230px">
                        <option value="All">All</option>
                        <?php
                            if ($_SESSION['UserType'] == 1) {
                                $sql = mysqli_query($con, "select * from employees where EmpStatusID=1 order by EmpLN asc");
                            } else {
                                $sql = mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID
                                    where employees.EmpID<>'admin' and (EmpISID='" . $_SESSION['id'] . "' or employees.EmpID='" . $_SESSION['id'] . "')
                                    and employees.EmpStatusID=1 order by EmpLN asc");
                            }
                            while ($res = mysqli_fetch_array($sql)) {
                        ?>
                        <option value="<?php echo $res['EmpID']; ?>"><?php echo $res['EmpLN'] . ", " . $res['EmpFN'] . " " . $res['EmpMN']; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="datefrom">From</label>
                    <input type="date" class="wd-input" id="datefrom" name="ddfrom" style="width:auto;padding:7px 10px" value="<?php echo $dt1; ?>">
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="dateto">To</label>
                    <input type="date" class="wd-input" id="dateto" name="ddto" style="width:auto;padding:7px 10px" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <button class="wd-btn wd-btn--ghost refreshdar" id="cmon" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            </form>
        </div>

        <div id="tblprint">
            <!-- print-only captions (shown by #btnprint handler) -->
            <label class="captionText d-none" id="captionTextMain">Daily Activity Report</label>
            <label class="captionText d-none" id="captionText">All Employees</label>
            <label class="captionText d-none" id="dateRange"></label>

            <div class="wd-tablewrap">
                <table class="wd-table" id="tab1">
                    <thead id="tabth">
                        <tr>
                            <th>EmpID</th>
                            <th>No</th>
                            <th>Name</th>
                            <th>Date &amp; Time</th>
                            <th>Activity</th>
                        </tr>
                    </thead>
                    <tbody id="darviewer">
                        <?php
                            $ids = 0;
                            if (!empty($resultdata)) {
                                foreach ($resultdata as $key => $value) {
                                    $ids = $ids + 1;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($resultdata[$key]["Employees_ID"]); ?></td>
                            <td><?php echo $ids; ?></td>
                            <td><?php echo htmlspecialchars($resultdata[$key]["LastName"] . ',  ' . $resultdata[$key]["FirstName"]); ?></td>
                            <td><?php echo date("F j, Y h:i:s A", strtotime($resultdata[$key]["Date_Time"])); ?></td>
                            <td style="text-align:left;"><?php echo htmlspecialchars($resultdata[$key]["Activity"]); ?></td>
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
            <button class="wd-btn wd-btn--ghost" id="btnprint" type="button"><i class="fa-solid fa-print"></i> Print this Data Table</button>
            <button class="wd-btn wd-btn--primary" type="button" onclick="exportToExcel('tab1', 'user-data')"><i class="fa-solid fa-file-excel"></i> Export to Excel</button>
        </div>
    </section>

    <!-- Loading overlay (toggled by script-reports.js .refreshdar and the onreadystatechange script) -->
    <div id="modalWarning">
        <div class="wd-loadbox">
            <img width="150px" src="assets/images/load.gif" alt="Loading">
        </div>
    </div>

    <?php include 'includes/wd-footer.php'; ?>
</body>

</html>
