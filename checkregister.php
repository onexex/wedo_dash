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
    <title><?php if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Check Register"; } ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">
    <link rel="stylesheet" type="text/css" href="assets/css/jquery.dialog.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/jquery.dialog.js"></script>
    <script type="text/javascript" src="assets/js/check.js"></script>

    <style>
        /* ---- Print rules MUST live in <head>: the print handlers replace
           document.body.innerHTML, which would wipe any <body> <style>. ---- */
        @media print {
            @page { size: auto; }
            body  { height:100%; font-family:Tahoma; }
            label { font-weight: normal !important; }
            /* physical-cheque line alignment (unscoped: .chk-preview wrapper is
               not part of the printed #forprint innerHTML) */
            #forprint { width:100%; }
            .l1  { text-align:right; padding-right:25px; }
            .l2b { text-align:right; padding-right:25px; }
            .l2a, .l3 { text-align:left; padding-left:25px; line-height:2.5; }
            /* history table print: un-clip the scroll body, keep pill colours */
            .wd-tablewrap { max-height:none !important; overflow:visible !important; border:none !important; }
            .chk-toolbar, .wd-card__head { display:none !important; }
            * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        }

        /* ---- Payee live-search dropdown ---- */
        .chk-payee{position:relative}
        .dv-livesearch{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:30;
            background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
            box-shadow:var(--shadow);overflow:hidden}
        .dv-livesearch:empty{display:none}
        .dv-livesearch a{display:block;padding:9px 13px;font-size:13px;color:var(--text);
            cursor:pointer;border:none;border-radius:0;background:none;text-align:left;white-space:normal}
        .dv-livesearch a:hover{background:var(--surface-2);color:var(--brand)}

        /* ---- Cheque preview ---- */
        .chk-preview-label{display:block;font-size:12.5px;font-weight:600;color:var(--text-2);margin-bottom:6px}
        .chk-preview{min-height:280px;background:var(--surface-2);border:1px dashed var(--border-2);
            border-radius:var(--radius);padding:24px 26px;font-family:'Courier New',Courier,monospace;color:var(--text)}
        .chk-preview .col-lg-12::after{content:"";display:table;clear:both}
        .chk-preview .l1{text-align:right;margin:0 0 10px;font-weight:600}
        .chk-preview .l2a{font-weight:600}
        .chk-preview .l2b{font-weight:700}
        .chk-preview .l3{display:block;border-bottom:1px solid var(--border-2);padding-bottom:6px;min-height:24px;margin:0}
        .chk-preview .l4{color:var(--text-2);font-style:italic;margin:0}
        .chk-preview .float-right{float:right}

        /* ---- History filter toolbar ---- */
        .chk-toolbar{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;
            padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface)}
        .chk-tb-field{display:flex;flex-direction:column;gap:6px}
        .chk-tb-field>label{font-size:12px;font-weight:600;color:var(--text-2);margin:0}
        .chk-tb-field .wd-input,.chk-tb-field .wd-select{width:auto;min-width:150px;padding:9px 12px}

        /* history print header */
        .subtitle{padding:16px 20px}
        .subtitle .thisdate{color:var(--text-2);font-size:13px;margin:4px 0 0}

        /* result alert spacing inside the writer card */
        #result{margin-top:14px}
    </style>
</head>
<body>
<?php $wd_active = 'checkregister'; include 'includes/wd-header.php'; ?>

<div class="wd-pagehead">
    <div>
        <h1>Check Register</h1>
        <p>Write and print cheques, then review your cheque register history.</p>
    </div>
</div>

<!-- ===================== Check Writer ===================== -->
<section class="wd-card">
    <div class="wd-card__head">
        <h3>Check Writer</h3>
    </div>
    <div style="padding:20px">
        <div class="row">
            <!-- form -->
            <div class="col-lg-5">
                <div class="wd-field">
                    <label>Bank info</label>
                    <select id="bankinfo" class="wd-select"></select>
                </div>
                <div class="wd-field">
                    <label>Check number</label>
                    <input type="number" class="wd-input" id="checknumber" readonly placeholder="Auto-generated">
                </div>
                <div class="wd-field chk-payee">
                    <label>Payee</label>
                    <input type="text" class="wd-input" id="payee" placeholder="Payee name" autocomplete="off">
                    <div class="dv-livesearch"></div>
                </div>
                <div class="wd-field">
                    <label>Date of check</label>
                    <input type="date" class="wd-input" id="checkdate">
                </div>
                <div class="wd-field">
                    <label>Check amount</label>
                    <input type="number" class="wd-input" id="checkamount" placeholder="0.00">
                </div>
                <div class="wd-field">
                    <label>Remarks</label>
                    <textarea class="wd-textarea" id="remark" rows="2" placeholder="Optional remarks"></textarea>
                </div>
                <button type="button" class="wd-btn wd-btn--primary save" style="width:100%;justify-content:center">
                    <i class="fa-solid fa-floppy-disk"></i> Save and confirm
                </button>
                <div id="result" class="alert alert-success" style="display:none"></div>
            </div>

            <!-- cheque preview -->
            <div class="col-lg-7">
                <label class="chk-preview-label">Cheque preview (for print)</label>
                <div class="chk-preview" id="forprint">
                    <div class="col-lg-12">
                        <p class="l1"></p>
                        <br>
                        <label class="l2a"></label>
                        <label class="l2b float-right"></label>
                        <br>
                        <p class="l3"></p>
                        <br><br><br>
                        <p class="l4"></p>
                    </div>
                </div>
                <button type="button" class="wd-btn wd-btn--ghost printthis" style="margin-top:12px">
                    <i class="fa-solid fa-print"></i> Print cheque
                </button>
            </div>
        </div>
    </div>
</section>

<!-- ===================== Check Register History ===================== -->
<section class="wd-card">
    <div class="wd-card__head">
        <h3>Check Register History</h3>
        <button type="button" class="wd-btn wd-btn--ghost" id="print">
            <i class="fa-solid fa-print"></i> Print history
        </button>
    </div>

    <div class="chk-toolbar">
        <div class="chk-tb-field">
            <label for="dfrom">From</label>
            <input type="date" class="wd-input" id="dfrom" aria-describedby="from">
        </div>
        <div class="chk-tb-field">
            <label for="dto">To</label>
            <input type="date" class="wd-input" id="dto" aria-describedby="to">
        </div>
        <div class="chk-tb-field">
            <label for="payeefilter">Payee filter</label>
            <select class="wd-select" id="payeefilter"></select>
        </div>
        <div class="chk-tb-field">
            <label for="bankfilter">Bank filter</label>
            <select class="wd-select" id="bankfilter"></select>
        </div>
        <div class="chk-tb-field">
            <label for="orderfilter">Order by</label>
            <select class="wd-select" id="orderfilter">
                <option value="bankinfo">Bank Name</option>
                <option value="checkamount">Check Amount</option>
                <option value="checkno">Check Number</option>
                <option value="checkdate">Check Date</option>
                <option value="payee">Payee</option>
            </select>
        </div>
        <div class="chk-tb-field">
            <label for="sortby">Sort by</label>
            <select class="wd-select" id="sortby">
                <option value="asc">ASC</option>
                <option value="desc">DESC</option>
            </select>
        </div>
        <div class="chk-tb-field">
            <label>&nbsp;</label>
            <button type="button" class="wd-btn wd-btn--primary" id="refresh" aria-describedby="refresh">
                <i class="fa-solid fa-rotate"></i> Refresh
            </button>
        </div>
    </div>

    <div id="forprinting">
        <div class="subtitle" style="display:none">
            <h4 class="pyrlfilt">Check History</h4>
            <h5 class="thisdate"></h5>
        </div>
        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Payee</th>
                        <th>Bank</th>
                        <th>Check Number</th>
                        <th>Check Amount</th>
                        <th>Check Date</th>
                        <th>Status</th>
                        <th class="actions">Action</th>
                    </tr>
                </thead>
                <tbody id="historydata"></tbody>
            </table>
        </div>
    </div>
</section>

<?php include 'includes/wd-footer.php'; ?>

</body>
</html>
