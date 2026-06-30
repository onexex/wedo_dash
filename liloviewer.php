<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
        // authorised
    } else {
        header('location: login');
        exit;
    }

    date_default_timezone_set('Asia/Manila');

    /* Default range: 1st of the current month through today. */
    $wdFrom = date('Y-m-01');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Attendance Viewer</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (Bootstrap modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/script-reports.js"></script>

    <!-- Print the report area in landscape. Hooks preserved: #thisprints #tblprint1 #tab #empcompid #dtp1 #dtp2 .captionText -->
    <script type="text/javascript">
        $(document).ready(function () {
            $(document).on('click', '#thisprints', function (e) {
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
                $("#tab td").css({ "padding": "9px", "text-align": "center", "font-size": "10px" });
                $("#tab th").css("font-size", "10px");
                $(".captionText").removeClass("d-none").addClass("d-block");
                $("#captionText").html($("#empcompid option:selected").text());
                $("#dateRange").html($("#dtp1").val() + " To : " + $("#dtp2").val());

                var printContents = document.getElementById('tblprint1').innerHTML;
                document.body.innerHTML = printContents;
                window.print();
                document.body.innerHTML = originalContents;
                $(".captionText").removeClass("d-block").addClass("d-none");
            });

            /* Auto-load the table on open (reuses the #viewlilo refresh path in
               script-reports.js → Query-LiloView), so logs show without a click. */
            $("#viewlilo").trigger("click");
        });

        function exportToExcel(tableID, filename = '') {
            var downloadurl;
            var dataFileType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById("tab");
            var tableHTMLData = tableSelect.outerHTML.replace(/ /g, '%20');
            filename = "Attendance_" + document.getElementById("dtp1").value + "_" + document.getElementById("dtp2").value + "_" + document.getElementById("empcompid").options[document.getElementById("empcompid").selectedIndex].text;
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
        .wd-table tbody tr td[colspan] { text-align: center; color: var(--text-3); padding: 26px 20px; }
        @media print {
            .captionText { display: block !important; }
            /* Un-clip the scrolling table body so ALL rows print, not just the visible window. */
            .wd-tablewrap { max-height: none !important; overflow: visible !important; border: 0 !important; }
            .wd-table td, .wd-table th { white-space: normal; }
        }
    </style>
</head>

<body>
    <?php $wd_active = 'liloviewer'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Attendance Viewer</h1>
            <p>Review employee time-in / time-out logs across date ranges.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Attendance logs</h3>
            <form id="vlilodata" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin:0">
                <div class="wd-field" style="margin:0">
                    <label for="empcompid">Choose Employee</label>
                    <select class="wd-select" id="empcompid" name="empcompany" style="min-width:230px">
                        <option value="all">All</option>
                        <?php
                            if ($_SESSION['UserType'] == 1) {
                                // super user: every company
                                $sql = mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpID<>'admin' and EmpStatusID=1 order by EmpLN asc");
                            } else {
                                // anyone else with module access: all employees in their company
                                $sql = mysqli_query($con, "select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpID<>'admin' and empdetails.EmpCompID='" . $_SESSION['CompID'] . "' and EmpStatusID=1 order by EmpLN asc");
                            }
                            while ($res = mysqli_fetch_array($sql)) {
                        ?>
                        <option value="<?php echo $res['EmpID']; ?>"><?php echo $res['EmpLN'] . ", " . $res['EmpMN'] . " " . $res['EmpFN']; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="dtp1">From</label>
                    <input type="date" class="wd-input" id="dtp1" style="width:auto;padding:7px 10px"
                        value="<?php echo $wdFrom; ?>">
                </div>
                <div class="wd-field" style="margin:0">
                    <label for="dtp2">To</label>
                    <input type="date" class="wd-input" id="dtp2" style="width:auto;padding:7px 10px"
                        value="<?php echo date("Y-m-d"); ?>">
                </div>
                <button class="wd-btn wd-btn--ghost" id="viewlilo" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            </form>
        </div>

        <div id="tblprint1">
            <!-- print-only captions (shown by #thisprints handler) -->
            <label class="captionText d-none" id="captionTextMain">Attendance Report</label>
            <label class="captionText d-none" id="captionText">All Employees</label>
            <label class="captionText d-none" id="dateRange"></label>

            <div class="wd-tablewrap">
                <!-- #viewlilo replaces this whole table's contents with Query-LiloView output
                     (thead+tbody, 5 cols). Keep the header here in sync with that file. -->
                <table class="wd-table" id="tab">
                    <thead id="tabth">
                        <tr>
                            <th>No</th>
                            <th>Name</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody id="darviewer">
                        <tr>
                            <td colspan="5">Choose an employee and date range, then click <i class="fa-solid fa-rotate"></i> Refresh to load attendance.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wd-card__foot">
            <button class="wd-btn wd-btn--ghost" id="thisprints" type="button"><i class="fa-solid fa-print"></i> Print</button>
            <button class="wd-btn wd-btn--primary" type="button" onclick="exportToExcel('tab', 'user-data')"><i class="fa-solid fa-file-excel"></i> Export to Excel</button>
        </div>
    </section>

    <!-- Loading modal (kept: script-reports.js #viewlilo handler references #thislilomodal) -->
    <div class="modal" id="thislilomodal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body" style="text-align:center">
                    <img width="120px" src="assets/images/load.gif" alt="Loading">
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/wd-footer.php'; ?>
</body>

</html>
