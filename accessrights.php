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
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">

    <script type="text/javascript" src="assets/js/script.js"></script>

    <style type="text/css">
        /* ---- layout: employee panel left, access sheet right ---- */
        .ar-layout { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 20px; align-items: start; }
        .ar-side { position: sticky; top: 0; }
        .ar-layout [hidden], .ar-toast [hidden] { display: none !important; }
        @media (max-width: 1100px) { .ar-layout { grid-template-columns: minmax(0, 1fr); } .ar-side { position: static; } }

        /* ---- employee search (the card must not clip the dropdown; theme sets overflow:hidden) ---- */
        .ar-picker { overflow: visible; }
        .ar-picker__body { padding: 16px 20px 18px; }
        .ar-search { position: relative; margin: 0; }
        .ar-search__icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-3); font-size: 13px; pointer-events: none; }
        .ar-search .wd-input { padding-left: 36px; }
        .ar-search__wrap { position: relative; }
        #empdetails { position: absolute; left: 0; right: 0; top: 100%; z-index: 30; margin-top: 6px;
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            box-shadow: 0 10px 30px rgba(16,24,40,.14); max-height: 340px; overflow: auto; }
        #empdetails:empty { display: none; }
        #empdetails a { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%;
            text-align: left; white-space: normal; padding: 10px 14px; margin: 0; border: 0; border-bottom: 1px solid var(--border);
            border-radius: 0; background: none; color: var(--text); font-family: var(--font-body);
            font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; }
        #empdetails a:last-child { border-bottom: 0; }
        #empdetails a small { color: var(--text-3); font-size: 11.5px; flex: 0 0 auto; }
        #empdetails a:hover, #empdetails a.is-active { background: var(--brand-tint); color: var(--text); }
        #empdetails a.is-active { box-shadow: inset 3px 0 0 var(--brand); }
        #empdetails a.ar-noresult { color: var(--text-3); cursor: default; background: none; box-shadow: none; }
        .ar-tip { margin: 10px 0 0; color: var(--text-3); font-size: 12px; }
        .ar-tip kbd { font-family: inherit; font-size: 11px; background: var(--surface-2); border: 1px solid var(--border-2);
            border-bottom-width: 2px; border-radius: 5px; padding: 0 5px; color: var(--text-2); box-shadow: none; }

        /* ---- selected employee summary ---- */
        .ar-emp { border-top: 1px solid var(--border); padding: 18px 20px 20px; }
        .ar-emp__who { display: flex; align-items: center; gap: 12px; }
        .ar-emp__avatar { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 50%; background: var(--navy); color: #fff;
            display: flex; align-items: center; justify-content: center; font-family: var(--font-head); font-weight: 700; font-size: 15px; }
        .ar-emp__name { font-family: var(--font-head); font-weight: 700; font-size: 15px; color: var(--text); line-height: 1.25; word-break: break-word; }
        .ar-emp__id { font-size: 12px; color: var(--text-3); margin-top: 2px; }
        .ar-emp__clear { margin-left: auto; flex: 0 0 auto; border: 0; background: none; color: var(--text-3); width: 30px; height: 30px;
            border-radius: 50%; cursor: pointer; }
        .ar-emp__clear:hover { background: var(--surface-2); color: var(--text); }
        .ar-meter { margin-top: 16px; }
        .ar-meter__row { display: flex; justify-content: space-between; font-size: 12.5px; color: var(--text-2); margin-bottom: 7px; }
        .ar-meter__row b { color: var(--text); font-family: var(--font-head); }
        .ar-meter__bar { height: 8px; background: var(--surface-2); border-radius: var(--radius-pill); overflow: hidden; }
        .ar-meter__fill { height: 100%; width: 0; background: var(--ok-text); border-radius: inherit; transition: width .3s; }
        .ar-groupnav { list-style: none; margin: 14px 0 0; padding: 0; }
        .ar-groupnav a { display: flex; align-items: center; gap: 9px; padding: 7px 8px; margin: 0 -8px; border-radius: 8px;
            color: var(--text-2); font-size: 12.5px; text-decoration: none; }
        .ar-groupnav a:hover { background: var(--surface-2); color: var(--text); }
        .ar-groupnav i { width: 16px; text-align: center; color: var(--text-3); }
        .ar-groupnav span:last-child { margin-left: auto; font-weight: 600; color: var(--text-3); font-size: 12px; }

        /* ---- toolbar ---- */
        .ar-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 14px; }
        .ar-tools .ar-search { flex: 1 1 240px; }
        .ar-seg { display: inline-flex; background: var(--surface-2); border-radius: var(--radius); padding: 3px; }
        .ar-seg button { border: 0; background: none; padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
            color: var(--text-2); cursor: pointer; }
        .ar-seg button[aria-pressed="true"] { background: var(--surface); color: var(--text); box-shadow: var(--shadow-sm); }

        /* ---- group cards ---- */
        .ar-group { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm); margin-bottom: 16px; scroll-margin-top: 12px; }
        .ar-group__head { display: flex; align-items: center; gap: 8px; padding: 8px 12px 8px 8px; }
        .ar-group__toggle { flex: 1 1 auto; display: flex; align-items: center; gap: 11px; border: 0; background: none;
            padding: 6px 8px; border-radius: var(--radius); cursor: pointer; text-align: left; min-width: 0; }
        .ar-group__toggle:hover { background: var(--surface-2); }
        .ar-group__icon { width: 32px; height: 32px; flex: 0 0 32px; border-radius: 9px; background: var(--brand-tint); color: var(--brand);
            display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .ar-group__title { font-family: var(--font-head); font-weight: 700; font-size: 15px; color: var(--text); }
        .ar-group__count { font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: var(--radius-pill);
            background: var(--surface-2); color: var(--text-2); white-space: nowrap; }
        .ar-group__count.is-all { background: var(--ok-bg); color: var(--ok-text); }
        .ar-group__chev { margin-left: auto; color: var(--text-3); font-size: 12px; transition: transform .2s; }
        .ar-group.is-collapsed:not(.is-searching) .ar-group__chev { transform: rotate(-90deg); }
        .ar-group__bulk { display: inline-flex; gap: 4px; flex: 0 0 auto; }
        .ar-bulk { border: 1px solid var(--border-2); background: var(--surface); color: var(--text-2); font-size: 12px; font-weight: 600;
            padding: 6px 11px; border-radius: 8px; cursor: pointer; white-space: nowrap; }
        .ar-bulk:hover { background: var(--surface-2); color: var(--text); }
        .ar-group.is-hidden, .ar-item.is-hidden { display: none; }
        .ar-group.is-collapsed:not(.is-searching) .ar-list { display: none; }

        /* ---- rights ---- */
        .ar-list { list-style: none; margin: 0; padding: 4px 8px 10px; border-top: 1px solid var(--border);
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); column-gap: 8px; }
        .ar-item { display: flex; align-items: center; gap: 12px; padding: 10px 10px; border-radius: var(--radius); cursor: pointer;
            border-bottom: 1px solid var(--border); }
        .ar-item:hover { background: var(--surface-2); }
        .ar-item__icon { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 8px; background: var(--surface-2); color: var(--text-3);
            display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .15s; }
        .ar-item.is-on .ar-item__icon { background: var(--brand-tint); color: var(--brand); }
        .ar-item__text { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
        .ar-item__label { font-size: 13px; font-weight: 600; color: var(--text-2); line-height: 1.35; }
        .ar-item.is-on .ar-item__label { color: var(--text); }
        .ar-item__hint { font-size: 11.5px; color: var(--text-3); line-height: 1.35; }
        .ar-flag { display: inline-flex; align-items: center; gap: 4px; margin-left: 4px; font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .03em; padding: 1px 7px; border-radius: var(--radius-pill);
            background: var(--warn-bg); color: var(--warn-text); vertical-align: 1px; }
        .ar-flag i { font-size: 9px; }

        /* switch — id = access column; aria-checked is the state */
        .ar-switch { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 8px; border: 0; background: none; padding: 4px;
            margin: -4px; border-radius: var(--radius-pill); cursor: pointer; }
        .ar-switch:focus { outline: none; }
        .ar-switch:focus-visible .ar-switch__track { box-shadow: 0 0 0 3px var(--brand-tint), 0 0 0 1px var(--brand); }
        .ar-switch__track { position: relative; width: 38px; height: 22px; border-radius: var(--radius-pill); background: var(--border-2); transition: background .18s; }
        .ar-switch__knob { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff;
            box-shadow: 0 1px 3px rgba(16,24,40,.25); transition: transform .18s; }
        .ar-switch[aria-checked="true"] .ar-switch__track { background: var(--ok-text); }
        .ar-switch[aria-checked="true"] .ar-switch__knob { transform: translateX(16px); }
        .ar-switch__text { width: 22px; text-align: left; font-size: 11.5px; font-weight: 700; color: var(--text-3); }
        .ar-switch[aria-checked="true"] .ar-switch__text { color: var(--ok-text); }
        .ar-switch.is-pending { cursor: progress; opacity: .55; }
        .ar-switch.is-pending .ar-switch__knob { animation: ar-pulse .8s ease-in-out infinite alternate; }
        @keyframes ar-pulse { to { opacity: .4; } }
        .ar-item.is-flash { animation: ar-flash 1s ease-out; }
        @keyframes ar-flash { from { background: var(--ok-bg); } to { background: transparent; } }

        /* ---- empty / loading states ---- */
        .ar-empty { background: var(--surface); border: 1px dashed var(--border-2); border-radius: var(--radius-lg); padding: 56px 24px;
            text-align: center; color: var(--text-2); font-size: 13px; display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .ar-empty > i { font-size: 30px; color: var(--text-3); margin-bottom: 8px; }
        .ar-empty b { color: var(--text); font-family: var(--font-head); font-size: 15px; }
        .ar-skel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px; margin-bottom: 16px; }
        .ar-skel span { display: block; height: 14px; border-radius: 6px; margin: 12px 0;
            background: linear-gradient(90deg, var(--surface-2) 25%, var(--border) 50%, var(--surface-2) 75%); background-size: 200% 100%;
            animation: ar-shimmer 1.2s linear infinite; }
        @keyframes ar-shimmer { to { background-position: -200% 0; } }

        /* ---- toast ---- */
        .ar-toast { position: fixed; left: 50%; bottom: 24px; z-index: 1100; transform: translate(-50%, 20px); opacity: 0; pointer-events: none;
            display: flex; align-items: center; gap: 14px; max-width: calc(100vw - 32px); padding: 12px 14px 12px 16px;
            background: var(--navy); color: #fff; border-radius: var(--radius); box-shadow: 0 12px 32px rgba(9,21,46,.3);
            font-size: 13px; transition: opacity .2s, transform .2s; }
        .ar-toast.is-show { opacity: 1; transform: translate(-50%, 0); pointer-events: auto; }
        .ar-toast > i { color: #5fd38f; }
        .ar-toast.is-error > i { color: #ff8a80; }
        .ar-toast__undo { border: 0; background: rgba(255,255,255,.12); color: #fff; font-weight: 700; font-size: 12.5px;
            padding: 6px 12px; border-radius: 8px; cursor: pointer; }
        .ar-toast__undo:hover { background: rgba(255,255,255,.22); }

        /* ---- confirm modal ---- */
        #acchange .modal-content { border: 0; border-radius: var(--radius-lg); box-shadow: 0 20px 50px rgba(16,24,40,.25); }
        #acchange .modal-body { padding: 26px 26px 10px; display: flex; gap: 16px; }
        #acchange .ar-confirm__icon { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 50%; background: var(--warn-bg); color: var(--warn-text);
            display: flex; align-items: center; justify-content: center; font-size: 17px; }
        #acchange h4 { margin: 2px 0 6px; font-size: 16px; color: var(--text); }
        #acchange p { margin: 0 0 6px; color: var(--text-2); font-size: 13px; }
        #acchange .modal-footer { border-top: 0; padding: 14px 26px 22px; }

        @media (max-width: 620px) {
            .ar-group__head { flex-wrap: wrap; }
            .ar-group__bulk { width: 100%; padding: 0 8px 4px; }
            .ar-list { grid-template-columns: minmax(0, 1fr); }
            .ar-tools .ar-seg { flex: 1 1 auto; }
            .ar-tools .ar-seg button { flex: 1 1 0; }
        }
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
            <p>Find an employee, then switch what they can open. Each switch saves the moment you flip it.</p>
        </div>
    </div>

    <div class="ar-layout">
        <aside class="ar-side">
            <section class="wd-card ar-picker">
                <div class="wd-card__head"><h3>Employee</h3></div>
                <div class="ar-picker__body">
                    <div class="wd-field ar-search">
                        <label for="txtemp">Search by lastname or employee ID</label>
                        <div class="ar-search__wrap">
                            <i class="fa-solid fa-magnifying-glass ar-search__icon"></i>
                            <input type="text" class="wd-input" id="txtemp" autocomplete="off" placeholder="Start typing&hellip;"
                                   role="combobox" aria-autocomplete="list" aria-controls="empdetails" aria-expanded="false">
                            <div id="empdetails" role="listbox"></div>
                        </div>
                    </div>
                    <p class="ar-tip"><kbd>&uarr;</kbd> <kbd>&darr;</kbd> to move, <kbd>Enter</kbd> to open, <kbd>/</kbd> to search</p>
                </div>

                <div class="ar-emp" id="ar-emp" hidden>
                    <div class="ar-emp__who">
                        <div class="ar-emp__avatar" id="ar-emp-initials"></div>
                        <div>
                            <div class="ar-emp__name" id="ar-emp-name"></div>
                            <div class="ar-emp__id" id="ar-emp-id"></div>
                        </div>
                        <button type="button" class="ar-emp__clear" id="ar-clear" title="Close this employee" aria-label="Close this employee">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="ar-meter">
                        <div class="ar-meter__row"><span>Rights switched on</span><b id="ar-total"></b></div>
                        <div class="ar-meter__bar"><div class="ar-meter__fill" id="ar-total-fill"></div></div>
                    </div>
                    <ul class="ar-groupnav" id="ar-groupnav"></ul>
                </div>
            </section>
        </aside>

        <div class="ar-main">
            <div class="ar-tools" id="ar-tools" hidden>
                <div class="ar-search">
                    <i class="fa-solid fa-filter ar-search__icon"></i>
                    <input type="search" class="wd-input" id="ar-filter" placeholder="Filter rights, e.g. payroll, leave, viewer" autocomplete="off" aria-label="Filter rights">
                </div>
                <div class="ar-seg" role="group" aria-label="Show">
                    <button type="button" data-show="all" aria-pressed="true">All</button>
                    <button type="button" data-show="on" aria-pressed="false">On</button>
                    <button type="button" data-show="off" aria-pressed="false">Off</button>
                </div>
                <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="ar-expand"><i class="fa-solid fa-down-left-and-up-right-to-center"></i> <span>Collapse all</span></button>
            </div>

            <div id="ar-nomatch" class="ar-empty" hidden>
                <i class="fa-solid fa-magnifying-glass"></i>
                <b>No rights match this filter.</b>
                <span>Try another word, or switch back to <em>All</em>.</span>
            </div>

            <div id="accr"></div>
        </div>
    </div>

    <!-- confirm (sensitive rights + bulk); kept outside #accr so reloading the sheet can't remove it -->
    <div class="modal fade" id="acchange" tabindex="-1" role="dialog" aria-labelledby="ar-confirm-title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="ar-confirm__icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div>
                        <h4 id="ar-confirm-title"></h4>
                        <p id="ar-confirm-msg"></p>
                        <p id="ar-confirm-note" style="color:var(--text-3);font-size:12px"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
                    <button type="button" class="wd-btn wd-btn--primary btnacyes"></button>
                </div>
            </div>
        </div>
    </div>

    <div class="ar-toast" id="ar-toast" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check"></i>
        <span class="ar-toast__msg"></span>
        <button type="button" class="ar-toast__undo" hidden>Undo</button>
    </div>

    <script type="text/javascript">
        $(function () {
            var $q = $("#txtemp"), $results = $("#empdetails"), $accr = $("#accr");
            var current = null;          // { id, name } of the employee on screen
            var searchSeq = 0, searchTimer = null, active = -1;
            var confirmRun = null, toastTimer = null, show = "all";
            var COLLAPSE_KEY = "wd.accessrights.collapsed";

            var EMPTY = '<div class="ar-empty"><i class="fa-solid fa-user-shield"></i>'
                      + '<b>Pick an employee to manage their access.</b>'
                      + '<span>Search on the left by lastname or employee ID.</span></div>';
            $accr.html(EMPTY);

            function firstName(n) { return (n || "").split(" ")[0]; }
            function initials(n) {
                var p = (n || "").split(" ").filter(Boolean);
                return ((p[0] || "?").charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : "")).toUpperCase();
            }
            function readCollapsed() {
                try { return JSON.parse(localStorage.getItem(COLLAPSE_KEY) || "[]"); } catch (e) { return []; }
            }
            function saveCollapsed() {
                var slugs = $accr.find(".ar-group.is-collapsed").map(function () { return $(this).attr("data-group"); }).get();
                try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify(slugs)); } catch (e) {}
            }

            /* ---------- employee search ---------- */
            function closeResults() { $results.empty(); active = -1; $q.attr("aria-expanded", "false"); }
            function pickable() { return $results.find("a[id]"); }
            function highlight(i) {
                var $a = pickable();
                $a.removeClass("is-active");
                if (!$a.length) { active = -1; return; }
                active = (i + $a.length) % $a.length;
                $a.eq(active).addClass("is-active")[0].scrollIntoView({ block: "nearest" });
            }

            $q.on("input", function () {
                var term = $.trim($q.val());
                clearTimeout(searchTimer);
                if (!term) { searchSeq++; closeResults(); return; }
                searchTimer = setTimeout(function () {
                    var mine = ++searchSeq;
                    $.get("query/searchemp.php", { term: term }).done(function (html) {
                        if (mine !== searchSeq) { return; }   // a newer keystroke already answered
                        $results.html(html);
                        $results.find("a").attr("role", "option").each(function () {
                            var $a = $(this);
                            if (!this.id) { $a.addClass("ar-noresult").text("No employee matches “" + term + "”"); return; }
                            $a.attr("data-name", $.trim($a.text()));
                            $a.append($("<small>").text("#" + this.id));
                        });
                        $q.attr("aria-expanded", "true");
                        active = -1;
                        if (pickable().length === 1) { highlight(0); }
                    });
                }, 180);
            });

            $q.on("keydown", function (e) {
                if (e.key === "ArrowDown")      { e.preventDefault(); highlight(active + 1); }
                else if (e.key === "ArrowUp")   { e.preventDefault(); highlight(active - 1); }
                else if (e.key === "Escape")    { closeResults(); }
                else if (e.key === "Enter") {
                    var $a = pickable();
                    if ($a.length) { e.preventDefault(); pick($a.eq(active < 0 ? 0 : active)); }
                }
            });
            $results.on("mousedown", "a[id]", function (e) { e.preventDefault(); pick($(this)); });
            $(document).on("mousedown", function (e) {
                if (!$(e.target).closest(".ar-picker .ar-search").length) { closeResults(); }
            });

            function pick($a) { load($a.attr("id"), $a.attr("data-name")); }

            /* ---------- load one employee's sheet ---------- */
            function load(empid, name) {
                current = { id: String(empid), name: name || String(empid) };
                $q.val("").blur();
                closeResults();
                $("#ar-tools").prop("hidden", true);
                $("#ar-nomatch").prop("hidden", true);
                $accr.html('<div class="ar-skel"><span style="width:30%"></span><span></span><span style="width:80%"></span><span style="width:60%"></span></div>'
                         + '<div class="ar-skel"><span style="width:25%"></span><span style="width:70%"></span><span></span></div>');
                renderSummary();
                try { history.replaceState(null, "", "?emp=" + encodeURIComponent(empid)); } catch (e) {}

                var me = current;
                $.get("searchaccessrights.php", { q: empid }).done(function (html) {
                    if (current !== me) { return; }
                    $accr.html(html || EMPTY);
                    var $sheet = $accr.find(".ar-sheet");
                    if ($sheet.length) {
                        current.name = $sheet.attr("data-emp-name") || current.name;
                        var collapsed = readCollapsed();
                        $accr.find(".ar-group").each(function () {
                            var c = collapsed.indexOf($(this).attr("data-group")) > -1;
                            $(this).toggleClass("is-collapsed", c).find(".ar-group__toggle").attr("aria-expanded", c ? "false" : "true");
                        });
                        $("#ar-tools").prop("hidden", false);
                        applyFilter();
                        syncExpandLabel();
                    }
                    renderSummary();
                }).fail(function () {
                    if (current !== me) { return; }
                    $accr.html('<div class="ar-empty"><i class="fa-solid fa-plug-circle-exclamation"></i>'
                             + '<b>Couldn’t load access rights.</b><span>Check your connection and pick the employee again.</span></div>');
                });
            }

            $("#ar-clear").on("click", function () {
                current = null;
                $accr.html(EMPTY);
                $("#ar-tools, #ar-emp").prop("hidden", true);
                $("#ar-nomatch").prop("hidden", true);
                try { history.replaceState(null, "", location.pathname); } catch (e) {}
                $q.focus();
            });

            /* ---------- counts + summary ---------- */
            function renderSummary() {
                if (!current) { $("#ar-emp").prop("hidden", true); return; }
                $("#ar-emp").prop("hidden", false);
                $("#ar-emp-name").text(current.name);
                $("#ar-emp-id").text("Employee ID " + current.id);
                $("#ar-emp-initials").text(initials(current.name));

                var on = 0, total = 0, nav = [];
                $accr.find(".ar-group").each(function () {
                    var $g = $(this), $items = $g.find(".ar-item"), gOn = $items.filter(".is-on").length;
                    on += gOn; total += $items.length;
                    $g.find(".ar-group__count").text(gOn + " of " + $items.length + " on").toggleClass("is-all", gOn === $items.length && gOn > 0);
                    nav.push($("<li>").append($("<a href='#'>").attr("data-group", $g.attr("data-group"))
                        .append($g.find(".ar-group__icon i").clone())
                        .append($("<span>").text($g.find(".ar-group__title").text()))
                        .append($("<span>").text(gOn + "/" + $items.length))));
                });
                $("#ar-total").text(total ? on + " of " + total : "—");
                $("#ar-total-fill").css("width", total ? (on / total * 100) + "%" : 0);
                $("#ar-groupnav").empty().append(nav);
            }

            $("#ar-groupnav").on("click", "a", function (e) {
                e.preventDefault();
                var $g = $accr.find('.ar-group[data-group="' + $(this).attr("data-group") + '"]');
                if ($g.hasClass("is-collapsed")) { setCollapsed($g, false); saveCollapsed(); syncExpandLabel(); }
                $g[0].scrollIntoView({ behavior: "smooth", block: "start" });
            });

            /* ---------- filter + collapse ---------- */
            function applyFilter() {
                var t = $.trim($("#ar-filter").val()).toLowerCase(), any = false;
                $accr.find(".ar-group").each(function () {
                    var $g = $(this), visible = 0;
                    $g.find(".ar-item").each(function () {
                        var on = $(this).hasClass("is-on");
                        var ok = (!t || this.getAttribute("data-find").indexOf(t) > -1) && (show === "all" || (show === "on") === on);
                        $(this).toggleClass("is-hidden", !ok);
                        if (ok) { visible++; }
                    });
                    $g.toggleClass("is-hidden", !visible).toggleClass("is-searching", !!t);
                    if (visible) { any = true; }
                });
                $("#ar-nomatch").prop("hidden", any || !$accr.find(".ar-item").length);
            }
            $("#ar-filter").on("input", applyFilter);
            $(".ar-seg").on("click", "button", function () {
                show = $(this).attr("data-show");
                $(".ar-seg button").attr("aria-pressed", "false");
                $(this).attr("aria-pressed", "true");
                applyFilter();
            });

            function setCollapsed($g, c) {
                $g.toggleClass("is-collapsed", c).find(".ar-group__toggle").attr("aria-expanded", c ? "false" : "true");
            }
            function syncExpandLabel() {
                var allOpen = !$accr.find(".ar-group.is-collapsed").length;
                $("#ar-expand span").text(allOpen ? "Collapse all" : "Expand all");
                $("#ar-expand i").attr("class", "fa-solid " + (allOpen ? "fa-down-left-and-up-right-to-center" : "fa-up-right-and-down-left-from-center"));
            }
            $accr.on("click", ".ar-group__toggle", function () {
                var $g = $(this).closest(".ar-group");
                setCollapsed($g, !$g.hasClass("is-collapsed"));
                saveCollapsed(); syncExpandLabel();
            });
            $("#ar-expand").on("click", function () {
                var collapse = !$accr.find(".ar-group.is-collapsed").length;
                $accr.find(".ar-group").each(function () { setCollapsed($(this), collapse); });
                saveCollapsed(); syncExpandLabel();
            });

            /* ---------- confirm modal ---------- */
            function confirmAction(o) {
                $("#ar-confirm-title").text(o.title);
                $("#ar-confirm-msg").text(o.msg);
                $("#ar-confirm-note").text(o.note || "");
                $(".btnacyes").text(o.ok).toggleClass("wd-btn--primary", !o.danger).toggleClass("wd-btn--danger", !!o.danger);
                confirmRun = o.run;
                $("#acchange").modal("show");
            }
            $(".btnacyes").on("click", function () {
                var run = confirmRun;
                confirmRun = null;
                $("#acchange").modal("hide");
                if (run) { run(); }
            });
            $("#acchange").on("shown.bs.modal", function () { $(".btnacyes").focus(); });

            /* ---------- toast ---------- */
            function hideToast() { $("#ar-toast").removeClass("is-show"); }
            function toast(msg, isError, undo) {
                var $t = $("#ar-toast");
                $t.toggleClass("is-error", !!isError).find(".ar-toast__msg").text(msg);
                $t.find("> i").attr("class", "fa-solid " + (isError ? "fa-circle-exclamation" : "fa-circle-check"));
                $t.find(".ar-toast__undo").prop("hidden", !undo).off("click").on("click", function () { hideToast(); undo(); });
                $t.addClass("is-show");
                clearTimeout(toastTimer);
                toastTimer = setTimeout(hideToast, undo ? 7000 : 4500);
            }

            /* ---------- change rights (one request per column, in order) ---------- */
            function setState($s, on) {
                $s.attr("aria-checked", on ? "true" : "false").find(".ar-switch__text").text(on ? "On" : "Off");
                $s.closest(".ar-item").toggleClass("is-on", on).removeClass("is-flash");
                void $s[0].offsetWidth;
                $s.closest(".ar-item").addClass("is-flash");
            }

            function change(switches, turnOn, groupName) {
                var who = current, onoff = turnOn ? "ON" : "OFF", done = [], failed = 0, forbidden = false;
                $(switches).addClass("is-pending");
                (function next(i) {
                    if (i >= switches.length) { return finish(); }
                    var $s = $(switches[i]);
                    $.get("updateaccess.php", { empid: who.id, mponoff: onoff, term: $s.attr("id") })
                        .done(function () { setState($s, turnOn); done.push(switches[i]); })
                        .fail(function (x) { failed++; if (x.status === 403) { forbidden = true; } })
                        .always(function () { $s.removeClass("is-pending"); next(i + 1); });
                })(0);

                function finish() {
                    if (current === who) { renderSummary(); }
                    var undo = done.length ? function () { change(done, !turnOn, groupName); } : null;
                    if (failed) {
                        toast("Couldn’t change " + failed + (failed === 1 ? " right" : " rights")
                              + (forbidden ? ": you don’t have permission to change access rights." : ". Please try again."), true);
                    } else if (switches.length === 1) {
                        toast($(switches[0]).attr("data-label") + " is now " + (turnOn ? "on" : "off") + " for " + firstName(who.name) + ".", false, undo);
                    } else {
                        toast("Turned " + (turnOn ? "on " : "off ") + done.length + " " + groupName + " rights for " + firstName(who.name) + ".", false, undo);
                    }
                }
            }

            // flip one switch (clicking anywhere on the row works too)
            $accr.on("click", ".ar-item", function (e) {
                var $s = $(this).find(".ar-switch");
                if ($s.hasClass("is-pending") || !current) { return; }
                var turnOn = $s.attr("aria-checked") !== "true", label = $s.attr("data-label");
                if ($s.attr("data-sensitive")) {
                    confirmAction({
                        title: (turnOn ? "Turn on " : "Turn off ") + label + "?",
                        msg: turnOn ? label + " is a sensitive right. " + current.name + " will be able to use it as soon as you confirm."
                                    : current.name + " will lose " + label + " right away.",
                        ok: turnOn ? "Turn on" : "Turn off",
                        danger: !turnOn,
                        run: function () { change([$s[0]], turnOn); }
                    });
                } else {
                    change([$s[0]], turnOn);
                }
            });

            // All on / All off for the visible rights of a group (sensitive ones are skipped)
            $accr.on("click", ".ar-bulk", function () {
                if (!current) { return; }
                var $g = $(this).closest(".ar-group"), turnOn = $(this).attr("data-onoff") === "ON";
                var name = $g.find(".ar-group__title").text();
                var $vis = $g.find(".ar-item:not(.is-hidden) .ar-switch");
                var skipped = $vis.filter("[data-sensitive]").length;
                var targets = $vis.filter(":not([data-sensitive]):not(.is-pending)")
                                  .filter(function () { return ($(this).attr("aria-checked") === "true") !== turnOn; }).get();
                if (!targets.length) {
                    toast("All " + name + " rights shown are already " + (turnOn ? "on" : "off") + ".");
                    return;
                }
                confirmAction({
                    title: (turnOn ? "Turn on " : "Turn off ") + targets.length + " " + name + (targets.length === 1 ? " right?" : " rights?"),
                    msg: "This changes " + current.name + "’s access right away. You can undo it from the message that follows.",
                    note: skipped ? "Sensitive rights in this group are left as they are; change those one at a time." : "",
                    ok: turnOn ? "Turn on " + targets.length : "Turn off " + targets.length,
                    danger: !turnOn,
                    run: function () { change(targets, turnOn, name); }
                });
            });

            // "/" jumps to the filter (or the employee search when nobody is open)
            $(document).on("keydown", function (e) {
                if (e.key !== "/" || $(e.target).is("input, textarea, select, [contenteditable]") || $("#acchange").is(":visible")) { return; }
                e.preventDefault();
                ($("#ar-tools").prop("hidden") ? $q : $("#ar-filter")).focus();
            });

            // reopen the employee from the address bar (?emp=ID) after a refresh
            var fromUrl = new URLSearchParams(location.search).get("emp");
            if (fromUrl) { load(fromUrl); }
        });
    </script>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
