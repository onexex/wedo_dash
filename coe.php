<?php
/* ==========================================================================
   coe.php  —  Reports > My Documents (COE): Certificate of Employment.
   Everyone: request a COE (purpose, with or without salary), follow the
   request, print it once approved. COE approvers (`coefunction`): approve or
   reject requests and issue a COE for anyone — including people who have
   left and can no longer sign in. Separated people get the past-tense
   certificate ("was connected ... from ... to ..."). Each approval gets a
   control number (COE-2026-0001) printed on the certificate.
   Rules: includes/lifecycle-lib.php. Reads/writes: query/lifecycle-action.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/idcard-lib.php';
require_once __DIR__ . '/includes/lifecycle-lib.php';

try {
    $lcypdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $lcypdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }

$coeInfo  = idc_company_info();   // address + phone from Card back settings (ID Card Generator)
$isIssuer = lcy_can_issue_coe($lcypdo);
$boot = ['token' => lcy_csrf_token(), 'issuer' => $isIssuer, 'me' => (string) $_SESSION['id']];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Dashboard" : "Certificate of Employment"; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
</head>
<body>
    <?php $wd_active = 'coe'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Certificate of Employment</h1>
            <p><?php echo $isIssuer ? 'Approve COE requests, or issue one for anyone — including former employees.'
                                    : 'Request a certificate of employment and print it once HR approves.'; ?></p>
        </div>
<?php if ($isIssuer && lcy_ready($lcypdo)): ?>
        <button type="button" class="wd-btn wd-btn--primary" id="coeIssueBtn"><i class="fa-solid fa-file-circle-plus"></i> Issue a COE</button>
<?php endif; ?>
    </div>

<?php if (!lcy_ready($lcypdo)): ?>
    <section class="wd-card"><div class="lcy-empty">COE requests are not set up on this server yet — run <code>sql/2026-10-14-add-employee-lifecycle.sql</code>.</div></section>
<?php else: ?>

<?php if ($isIssuer): ?>
    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Requests</h3>
            <div class="lcy-tabs" id="coeTabs">
                <button type="button" class="lcy-tab is-on" data-status="pending">Waiting <span id="coePendingN"></span></button>
                <button type="button" class="lcy-tab" data-status="approved">Approved</button>
                <button type="button" class="lcy-tab" data-status="rejected">Rejected</button>
                <button type="button" class="lcy-tab" data-status="">All</button>
            </div>
        </div>
        <div id="coeQueue"><div class="lcy-empty">Loading&hellip;</div></div>
    </section>
<?php endif; ?>

    <section class="wd-card">
        <div class="wd-card__head"><h3>Request my certificate</h3></div>
        <form class="lcy-card-body lcy-row" id="coeRequestForm" autocomplete="off">
            <div class="wd-field">
                <label for="coePurpose">What is it for?</label>
                <input type="text" class="wd-input" id="coePurpose" name="purpose" maxlength="200" list="coePurposes" required placeholder="e.g. Bank loan application">
                <datalist id="coePurposes">
                    <option value="Bank loan application"><option value="Visa application"><option value="Housing / Pag-IBIG loan">
                    <option value="New employer requirement"><option value="Government requirement">
                </datalist>
            </div>
            <label class="lcy-check" style="margin-bottom:12px !important"><input type="checkbox" name="salary" value="1"> Include my monthly salary</label>
            <button type="submit" class="wd-btn wd-btn--primary"><i class="fa-solid fa-paper-plane"></i> Send request</button>
        </form>
        <p class="lcy-modal-error" id="coeRequestErr" style="padding:0 20px"></p>
        <div class="wd-card__head" style="border-top:1px solid var(--border)"><h3>My requests</h3></div>
        <div id="coeMine"><div class="lcy-empty">Loading&hellip;</div></div>
    </section>

    <section class="wd-card lcy-hidden" id="coePreview">
        <div class="wd-card__head">
            <h3>Certificate <span class="lcy-muted" id="coePreviewNo"></span></h3>
            <div class="lcy-actions">
                <button type="button" class="wd-btn wd-btn--primary" id="coePrint"><i class="fa-solid fa-print"></i> Print</button>
                <button type="button" class="wd-btn wd-btn--ghost" id="coeClose">Close</button>
            </div>
        </div>
        <div class="coe-paperwrap">
            <div id="forprint" class="coe-paper">
                <div class="coe-control" id="coeControl"></div>
                <p class="coe-greeting">To whom it may concern;</p>
                <div id="coeBody"></div>
                <p>Should you have clarifications, please call <?php echo $e($coeInfo['phone']); ?> anytime during office hours.</p>
                <p id="coeIssuedFor"></p>
                <p id="coeIssuedOn"></p>
                <p class="coe-verifynote" id="coeVerifyNote"></p>
                <p>Thank you.</p>
                <div class="coe-sign">
                    <img id="coeSign" alt="Signature"><?php /* src set per approved request (query/signature.php checks it) */ ?>
                    <p class="coe-signname">Jose Modesto A. Ferrer</p>
                    <p>General Manager - WeDo BPO Inc.</p>
                </div>
                <div class="coe-letterhead">
                    <img src="assets/images/logos/WeDo.png" alt="WeDo BPO Inc.">
                    <p class="coe-address"><?php echo $e(implode(', ', $coeInfo['address'])); ?></p>
                </div>
            </div>
        </div>
    </section>

<?php if ($isIssuer): ?>
    <!-- issue a COE for anyone (HR) -->
    <div class="modal fade" id="coeIssueModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    <h4 class="modal-title">Issue a COE</h4>
                </div>
                <form class="modal-body" id="coeIssueForm" autocomplete="off">
                    <input type="hidden" name="emp">
                    <div class="wd-field lcy-search">
                        <label for="coeIssueSearch">Employee — current or former</label>
                        <input type="search" class="wd-input" id="coeIssueSearch" placeholder="Start typing a name&hellip;">
                        <div class="lcy-results" id="coeIssueResults"></div>
                    </div>
                    <div class="wd-field">
                        <label for="coeIssuePurpose">Purpose</label>
                        <input type="text" class="wd-input" id="coeIssuePurpose" name="purpose" maxlength="200" list="coePurposes" required>
                    </div>
                    <label class="lcy-check"><input type="checkbox" name="salary" value="1"> Include monthly salary</label>
                    <p class="lcy-muted" style="margin-top:10px">Use this for requests by phone, email or walk-in — e.g. someone who has left and can no longer sign in. It is approved at once.</p>
                    <p class="lcy-modal-error" id="coeIssueErr"></p>
                </form>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
                    <button type="button" class="wd-btn wd-btn--primary" id="coeIssueSave"><i class="fa-solid fa-check"></i> Issue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- reject with a reason -->
    <div class="modal fade" id="coeRejectModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
                    <h4 class="modal-title">Reject request</h4>
                </div>
                <div class="modal-body">
                    <div class="wd-field">
                        <label for="coeRejectWhy">Reason (the employee sees this)</label>
                        <textarea class="wd-textarea" id="coeRejectWhy" rows="3" maxlength="300"></textarea>
                    </div>
                    <p class="lcy-modal-error" id="coeRejectErr"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
                    <button type="button" class="wd-btn wd-btn--danger" id="coeRejectSave">Reject</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <script>window.LCY_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="assets/js/lifecycle-common.js?v=<?php echo $v('assets/js/lifecycle-common.js'); ?>"></script>
    <script src="assets/js/coe.js?v=<?php echo $v('assets/js/coe.js'); ?>"></script>
<?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
