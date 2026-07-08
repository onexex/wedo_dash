<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Rights</title>

    <!-- Functional libs (Bootstrap modals + jQuery + FontAwesome 6) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>

    <style type="text/css">
        /* the search card must not clip the absolute dropdown (theme sets .wd-card overflow:hidden) */
        .ar-search-card { overflow: visible; }
        /* live employee-search dropdown injected by query/searchemp.php */
        .ar-search { position: relative; max-width: 460px; }
        #empdetails { position: absolute; left: 0; right: 0; top: 100%; z-index: 30; margin-top: 6px;
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            box-shadow: var(--shadow); max-height: 320px; overflow: auto; }
        #empdetails:empty { display: none; }
        #empdetails a { display: block; width: 100%; text-align: left; white-space: normal;
            padding: 10px 14px; margin: 0; border: 0; border-bottom: 1px solid var(--border);
            border-radius: 0; background: none; color: var(--text); font-family: var(--font-body);
            font-size: 13px; font-weight: 400; cursor: pointer; }
        #empdetails a:last-child { border-bottom: 0; }
        #empdetails a:hover { background: var(--brand); color: #fff; }

        /* access table: labels + toggles */
        #accr td i { color: var(--brand); width: 18px; text-align: center; margin-right: 8px; }
        #accr td span { color: var(--text); }
        #accr .ar-grouprow td { background: var(--surface-2); color: var(--brand);
            font-family: var(--font-head); font-weight: 700; font-size: 11px; text-transform: uppercase;
            letter-spacing: .06em; padding: 9px 20px; }
        .ar-tablewrap { max-height: 64vh; }

        /* ON/OFF toggle — text stays exactly "ON"/"OFF" (JS reads it); dot is a pseudo-element */
        .ar-toggle { appearance: none; -webkit-appearance: none; cursor: pointer; border: 1px solid transparent;
            border-radius: var(--radius-pill); padding: 5px 15px; min-width: 66px; font-family: var(--font-head);
            font-weight: 700; font-size: 11.5px; letter-spacing: .04em; display: inline-flex; align-items: center;
            justify-content: center; gap: 7px; transition: .15s; }
        .ar-toggle::before { content: ""; width: 7px; height: 7px; border-radius: 50%; }
        .ar-toggle--on  { background: var(--ok-bg); color: var(--ok-text); }
        .ar-toggle--on::before  { background: var(--ok-text); }
        .ar-toggle--off { background: var(--danger-bg); color: var(--danger-text); }
        .ar-toggle--off::before { background: var(--danger-text); }
        .ar-toggle:hover { filter: brightness(.96); }

        /* confirm modal title */
        #acchange .modal-title { font-size: 15px; font-weight: 600; color: var(--text); margin: 0; }
    </style>
</head>
<body>
    <?php
        $wd_active = 'accessrights';
        include 'includes/wd-header.php';
    ?>
    <div class="wd-pagehead">
        <div>
            <h1>Access Rights</h1>
            <p>Search an employee, then switch each module, report and maintenance screen on or off.</p>
        </div>
    </div>

    <section class="wd-card ar-search-card">
        <div class="wd-card__head">
            <h3>Find employee</h3>
        </div>
        <div style="padding:16px 20px">
            <div class="wd-field ar-search" style="margin:0">
                <label for="txtemp">Search employee (lastname or ID)</label>
                <input type="text" class="wd-input" id="txtemp" autocomplete="off" placeholder="Start typing a lastname&hellip;">
                <div id="empdetails"></div>
            </div>
        </div>
    </section>

    <input type="hidden" id="empidar" name="empidar">
    <input type="hidden" id="emponoff" name="emponoff">

    <section class="wd-card">
        <div class="wd-card__head">
            <h3 class="empaccessr">Select an employee to manage access</h3>
        </div>
        <div class="wd-tablewrap ar-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Access</th>
                    </tr>
                </thead>
                <tbody id="accr">
                    <tr><td colspan="2" style="text-align:center;color:var(--text-3)">No employee selected yet.</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Confirm access change (Bootstrap modal, kept at top level so #accr.empty() can't remove it) -->
    <div class="modal fade" id="acchange">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="color:#fff;background-color:#f93627">
                    <h4 class="modal-titles" style="margin:0">Confirm access change</h4>
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.9">&times;</button>
                </div>
                <div class="modal-body">
                    <h6 class="modal-title">Are you sure you want to change this access?</h6>
                </div>
                <div class="modal-footer">
                    <button type="button" id="" class="wd-btn wd-btn--primary btnacyes"><i class="fa-solid fa-check"></i> Yes, apply</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        $(document).ready(function () {
            var $results = $("#empdetails");

            // live employee search
            $("#txtemp").on("keyup input", function () {
                var term = $(this).val();
                if (term.length) {
                    $.get("query/searchemp.php", { term: term }).done(function (data) {
                        $results.html(data);
                    });
                } else {
                    $results.empty();
                }
            });

            // pick an employee -> load their access rights
            $(document).on("click", "#empdetails a", function () {
                var empid = $(this).attr("id");
                $results.empty();
                $("#txtemp").val("");
                $(".empaccessr").text("Access Rights of " + $(this).text().trim());
                $("#empidar").val(empid);
                $.get("searchaccessrights.php", { q: empid }).done(function (html) {
                    $("#accr").html(html);
                });
            });

            // click a module toggle -> stage the confirm modal
            $(document).on("click", "#accr .ar-toggle", function () {
                if ($(this).text().trim() === "ON") {
                    $(".modal-title").text("Turn OFF this module for the employee?");
                    $("#emponoff").val("OFF");
                } else {
                    $(".modal-title").text("Turn ON this module for the employee?");
                    $("#emponoff").val("ON");
                }
                $(".btnacyes").attr("id", $(this).attr("id"));
            });

            // confirm -> update, then refresh the table
            $(document).on("click", ".btnacyes", function () {
                var term  = $(this).attr("id");
                var empid = $("#empidar").val();
                var onoff = $("#emponoff").val();
                if (!term || !empid) { $("#acchange").modal("hide"); return; }
                $.get("updateaccess.php", { empid: empid, mponoff: onoff, term: term }).done(function () {
                    $.get("searchaccessrights.php", { q: empid }).done(function (html) {
                        $("#accr").html(html);
                    });
                    $("#acchange").modal("hide");
                });
            });
        });
    </script>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
