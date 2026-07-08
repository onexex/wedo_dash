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
    <title><?php if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Memorandum Generator"; } ?></title>
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
    <script type="text/javascript" src="assets/js/memo.js"></script>

    <style type="text/css">
        /* live-search dropdown (memo.js injects <a class="btn"> rows into .dv-livesearch) */
        .memo-search { position: relative; max-width: 460px; }
        .dv-livesearch {
            position: absolute; z-index: 1000; left: 0; right: 0; top: 100%;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow-sm);
            max-height: 280px; overflow-y: auto; margin-top: 4px;
        }
        .dv-livesearch:empty { display: none; }
        .dv-livesearch a {
            display: block; width: 100%; text-align: left; white-space: normal;
            padding: 9px 13px; font-size: 13px; color: var(--text-2);
            border: 0; border-bottom: 1px solid var(--border); border-radius: 0;
            background: transparent; cursor: pointer;
        }
        .dv-livesearch a:last-child { border-bottom: 0; }
        .dv-livesearch a:hover { background: var(--surface-2); color: var(--brand); }

        /* two-column form grid for the memo header fields */
        .memo-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 20px; }
        @media (max-width: 640px){ .memo-grid { grid-template-columns: 1fr; } }
        #result { border-radius: var(--radius); }

        /* ---- print (body-swap: window.print() replaces document.body with
           #forprint innerHTML, so these rules MUST live in <head> to survive) ---- */
        @media print {
            body { font-family: Tahoma; height: 100%; }
            #forprint { width: 100%; }
            #bodyprint {
                text-align: justify;
                text-justify: inter-word;
                white-space: pre-line;
                font-size: 14px;
            }
            .p, p { font-size: 14px; }
            label { font-weight: normal !important; font-size: 14px; }
            @page { size: auto; }
        }
    </style>
</head>
<body>
    <?php $wd_active = 'memo'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Memorandum Generator</h1>
            <p>Draft, search and print company memorandums.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>New memorandum</h3>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:12.5px;font-weight:600;color:var(--text-2)">Memo ID</span>
                <span class="wd-pill wd-pill--info"><span id="memoid">20201-006</span></span>
            </div>
        </div>

        <div style="padding:20px">
            <!-- Search existing memos -->
            <div class="wd-field memo-search">
                <label for="searchWord">Search memo</label>
                <input type="text" class="wd-input" placeholder="Search memo&hellip;" id="searchWord" autocomplete="off">
                <div class="dv-livesearch"></div>
            </div>

            <hr style="border:0;border-top:1px solid var(--border);margin:4px 0 20px">

            <!-- Header fields -->
            <div class="memo-grid">
                <div class="wd-field">
                    <label for="to">To</label>
                    <input type="text" class="wd-input" id="to" placeholder="Enter recipient">
                </div>
                <div class="wd-field">
                    <label for="datememo">Memo Date</label>
                    <input type="text" disabled class="wd-input" id="datememo">
                </div>
                <div class="wd-field">
                    <label for="from">From</label>
                    <input type="text" class="wd-input" id="from" placeholder="Enter sender">
                </div>
                <div class="wd-field">
                    <label for="subject">Subject</label>
                    <input type="text" class="wd-input" id="subject" placeholder="Enter subject">
                </div>
            </div>

            <!-- Body -->
            <div class="wd-field">
                <label for="body">Body</label>
                <textarea name="body" class="wd-textarea" id="body" cols="30" rows="16"></textarea>
            </div>

            <div id="result" class="alert alert-success" style="display:none"></div>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button class="wd-btn wd-btn--primary printmemo"><i class="fa-solid fa-print"></i> Save and Print</button>
                <button class="wd-btn wd-btn--ghost reprint" style="display:none"><i class="fa-solid fa-print"></i> Re-Print</button>
            </div>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>

    <!-- Print template (kept outside the app shell so the body-swap print picks it
         up cleanly; hidden on screen via .subtitle display:none). All print IDs
         are JS hooks in memo.js — do not rename. -->
    <div id="forprint">
        <div class="col-lg-12 subtitle" style="display:none">

            <h5 class="thisdate"> </h5>

            <div class="row">
                <div class="col-lg-12">
                    <div class="row">
                        <div class="col lg-6">
                            <label>MEMORANDOM </label>
                        </div>
                        <div class="col lg-6">
                            <label for="" id="printMemoId"></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <br>
                    <div class="row">
                        <div class="col lg-6">
                            <label for="">To:</label> <label for="" style="padding-left:30px;" id="printto">All Personnel wedobpo.com</label>
                        </div>
                        <br>
                        <div class="col lg-6">
                            <label for="">Date:</label> <label for="" style="padding-left:30px;" id="printmemodate"></label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col lg-6">
                            <label for="">From:</label> <label for="" style="padding-left:15px;" id="printfrom"></label>
                        </div>
                        <div class="col lg-6">
                            <label for="">Subject:</label> <label for="" style="padding-left:15px;" id="printsubject"></label>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12"></div>
            </div>

            <hr>
            <div class="row">
                <div class="col-lg-12">
                    <div class="row">
                        <div class="col lg-12">
                            <br>
                            <br>
                            <div id="bodyprint">body here </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
