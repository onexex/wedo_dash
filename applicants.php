<?php
/* ==========================================================================
   applicants.php  —  Management > Applicants.
   Registrations from the public Apply page (apply.php): filter by position
   and stage, open one to see the full profile, resume, the rehire check
   against former employees and the stage history, move it along (Screening
   -> Exam -> Initial interview -> Final interview -> Job offer), then
   "Hire": a short dialog (company, department, position, superior, Trainee
   or Independent Contractor, pay) and WeDo creates the employee from the
   registration and can email the matching contract — the temporary
   password is shown once.
   Closed applications can be deleted. Also manages the job openings.
   Rules: includes/applicants-lib.php. Reads/writes: query/applicants-action.php.
   Gated by the `applicants` access right.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/applicants-lib.php';
require_once __DIR__ . '/includes/applicants-mail.php';

try {
    $aplpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $aplpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    apl_db_clock($aplpdo);   // applicant times are Manila time (the server MySQL runs on UTC)
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!apl_can_manage($aplpdo)) { header('location: 404?'); exit(); }

$ready = apl_ready($aplpdo);
$myEmail = '';   // prefilled as the "Email setup" test address
try {
    $st = $aplpdo->prepare("SELECT EmpEmail FROM empprofiles WHERE EmpID = :id");
    $st->execute([':id' => $_SESSION['id']]);
    $myEmail = (string) $st->fetchColumn();
} catch (Throwable $e) { /* optional */ }
$boot = [
    'token'     => apl_csrf_token(),
    'stages'    => APL_STAGES,
    'pipeline'  => APL_PIPELINE,
    'hireStage' => APL_HIRE_STAGE,
    'canMail'   => wd_mail_enabled(),          // Brevo set up: offer emails to the applicant
    'mailStages'=> APL_MAIL_STAGES,
    'myEmail'   => filter_var(trim($myEmail), FILTER_VALIDATE_EMAIL) ? trim($myEmail) : '',
    'canHire'   => apl_can_hire($aplpdo),
    'canLife'   => lcy_can_separate($aplpdo),
    'openings'  => $ready ? apl_openings($aplpdo, false) : [],
    'id'        => (int) ($_GET['id'] ?? 0),
];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Applicants" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <link rel="stylesheet" href="assets/css/applicants.css?v=<?php echo $v('assets/css/applicants.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
  </head>
  <body>
    <?php
      $wd_active = 'applicants';
      include 'includes/wd-header.php';
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>Applicant Pool</h1>
          <p>Everyone who registered on the public Apply page. Move each one through the stages, then hire — WeDo pulls their registration into the employee records, as Trainee.</p>
        </div>
<?php if ($ready): ?>
        <div class="lcy-actions">
          <button type="button" class="wd-btn wd-btn--ghost" id="aplMailBtn"><i class="fa-regular fa-envelope"></i> Email setup</button>
          <button type="button" class="wd-btn wd-btn--ghost" id="aplOpeningsBtn"><i class="fa-solid fa-briefcase"></i> Job openings</button>
        </div>
<?php endif; ?>
      </div>

<?php if (!$ready): ?>
      <section class="wd-card"><div class="lcy-empty">Applicants is not set up on this server yet — run <code>sql/2026-10-14-add-applicants.sql</code>.</div></section>
<?php else: ?>
      <section class="wd-card">
        <div class="wd-card__head">
          <h3>Applications</h3>
          <div class="apl-share">Public link: <code id="aplLink"></code>
            <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="aplCopy"><i class="fa-regular fa-copy"></i> Copy</button></div>
        </div>
        <div class="apl-chips lcy-tabs" id="aplChips"></div>
        <form class="apl-filters" id="aplFilters" autocomplete="off">
          <div class="wd-field" style="flex:1"><label for="aplQ">Search</label><input type="search" class="wd-input" id="aplQ" placeholder="Name or email"></div>
          <div class="wd-field"><label for="aplFOpening">Position</label><select class="wd-select" id="aplFOpening"></select></div>
        </form>
        <div id="aplList"><div class="lcy-empty">Loading&hellip;</div></div>
      </section>

      <!-- one application -->
      <div class="modal fade apl-pro" id="aplModal" tabindex="-1" aria-labelledby="aplTitle">
        <div class="modal-dialog">
          <div class="modal-content">
            <header class="apl-pro__head">
              <button type="button" class="apl-pro__close" data-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
              <div class="apl-pro__id">
                <div class="apl-pro__avatar" id="aplAvatar" aria-hidden="true"></div>
                <div class="apl-pro__who">
                  <h4 id="aplTitle">Applicant</h4>
                  <p class="apl-pro__sub" id="aplSub"></p>
                  <div class="apl-pro__contact" id="aplContact"></div>
                </div>
              </div>
              <div class="apl-pro__actions" id="aplActions"></div>
            </header>
            <div class="apl-pro__track" id="aplTrack"></div>
            <div class="apl-pro__layout">
              <div class="apl-pro__main" id="aplBody"></div>
              <aside class="apl-pro__side">
                <form class="apl-card" id="aplStageForm" autocomplete="off">
                  <h5 class="apl-card__title"><i class="fa-solid fa-arrow-right-arrow-left"></i> Move to stage</h5>
                  <div class="wd-field"><label for="aplStage">Stage</label><select class="wd-select" id="aplStage" name="stage"></select><small class="apl-stage-hint" id="aplStageHint"></small></div>
                  <div class="wd-field"><label for="aplNotes">Internal note <span class="lcy-muted">(only HR sees it)</span></label><textarea class="wd-textarea" id="aplNotes" name="notes" rows="2" maxlength="1000" placeholder="e.g. Passed the initial interview"></textarea></div>
                  <div class="apl-mailopt lcy-hidden" id="aplMailOpt">
                    <label class="apl-toggle"><input type="checkbox" id="aplMailOn"><span class="apl-toggle__ui"></span> <span><i class="fa-regular fa-envelope"></i> Email the applicant</span></label>
                    <div class="wd-field lcy-hidden" id="aplMailMsgBox"><label for="aplMailMsg">Message to include <span class="lcy-muted">(optional)</span></label>
                      <textarea class="wd-textarea" id="aplMailMsg" rows="3" maxlength="2000"></textarea></div>
                  </div>
                  <p class="lcy-modal-error" id="aplStageErr"></p>
                  <button type="submit" class="wd-btn wd-btn--primary apl-card__btn"><i class="fa-solid fa-check"></i> Save</button>
                  <p class="apl-mailres" id="aplMailRes" role="status"></p>
                </form>
                <section class="apl-card" id="aplActivity">
                  <h5 class="apl-card__title"><i class="fa-solid fa-clock-rotate-left"></i> Activity
                    <button type="button" class="apl-feed__expand" id="aplFeedExpand" aria-expanded="false" title="Show all activity and notes in full"><i class="fa-solid fa-up-right-and-down-left-from-center"></i> <span>Expand</span></button></h5>
                  <ul class="apl-feed" id="aplHistory"></ul>
                </section>
              </aside>
            </div>
          </div>
        </div>
      </div>

      <!-- exam result picture, full size -->
      <div class="apl-viewer" id="aplViewer" role="dialog" aria-label="Exam result picture" hidden>
        <button type="button" class="apl-viewer__btn apl-viewer__close" id="aplViewerClose" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        <button type="button" class="apl-viewer__btn apl-viewer__prev" id="aplViewerPrev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
        <img id="aplViewerImg" alt="">
        <button type="button" class="apl-viewer__btn apl-viewer__next" id="aplViewerNext" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="apl-viewer__bar"><span id="aplViewerCap"></span>
          <a class="wd-btn wd-btn--ghost wd-btn--sm" id="aplViewerOpen" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i> Open full size</a></div>
      </div>

      <!-- hire: create the employee from the registration -->
      <div class="modal fade apl-hire" id="aplHireModal" tabindex="-1" aria-labelledby="aplHireTitle">
        <div class="modal-dialog">
          <div class="modal-content">
            <header class="apl-hire__head">
              <button type="button" class="apl-pro__close" data-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
              <span class="apl-hire__av" id="aplHireAv" aria-hidden="true"></span>
              <div class="apl-hire__who">
                <span class="apl-hire__eyebrow"><i class="fa-solid fa-user-plus"></i> Hire as employee</span>
                <h4 id="aplHireTitle">Hire</h4>
                <p id="aplHireSub"></p>
              </div>
            </header>

            <form class="apl-hire__body" id="aplHireForm" autocomplete="off" novalidate>
              <p class="apl-hire__note"><i class="fa-solid fa-circle-info"></i> Name, address, IDs, education, family<span id="aplHireNotePhoto"> and photo</span> come from the registration. Fill in where they will work and their pay.</p>

              <section class="apl-hire__sec">
                <h5><span>1</span> Placement</h5>
                <div class="apl-grid">
                  <div class="wd-field"><label for="aplHCompany">Company *</label><select class="wd-select" id="aplHCompany" name="company" required></select></div>
                  <div class="wd-field"><label for="aplHDept">Department *</label><select class="wd-select" id="aplHDept" name="department" required></select></div>
                  <div class="wd-field"><label for="aplHPos">Position *</label><select class="wd-select" id="aplHPos" name="position" required></select></div>
                  <div class="wd-field"><label for="aplHSup">Immediate superior</label><select class="wd-select" id="aplHSup" name="superior"></select></div>
                </div>
              </section>

              <section class="apl-hire__sec">
                <h5><span>2</span> Employment</h5>
                <div class="apl-grid apl-grid--3">
                  <div class="wd-field"><label for="aplHClass">Hire as *</label><select class="wd-select" id="aplHClass" name="classification" required></select></div>
                  <div class="wd-field"><label for="aplHDate">Hire date *</label><input type="date" class="wd-input" id="aplHDate" name="hired" required></div>
                  <div class="wd-field"><label for="aplHIdNo">Employee ID (card)</label><input class="wd-input" id="aplHIdNo" name="idNumber" maxlength="100" placeholder="Optional"></div>
                </div>
              </section>

              <section class="apl-hire__sec">
                <h5><span>3</span> Pay</h5>
                <div class="apl-grid apl-grid--3">
                  <div class="wd-field"><label for="aplHBasic">Monthly basic *</label><div class="apl-money"><span>₱</span><input class="wd-input" id="aplHBasic" name="basic" inputmode="decimal" placeholder="0.00" required></div></div>
                  <div class="wd-field"><label for="aplHAllow">Allowance *</label><div class="apl-money"><span>₱</span><input class="wd-input" id="aplHAllow" name="allowance" inputmode="decimal" value="0" required></div></div>
                  <div class="wd-field"><label for="aplHRate">Hourly rate *</label><div class="apl-money"><span>₱</span><input class="wd-input" id="aplHRate" name="hourly" inputmode="decimal" placeholder="0.00" required></div></div>
                </div>
              </section>

              <section class="apl-hire__sec">
                <h5><span>4</span> Contract</h5>
                <div class="apl-contract">
                  <span class="apl-contract__icon"><i class="fa-solid fa-file-signature"></i></span>
                  <div class="apl-contract__body">
                    <b id="aplHContractTitle">Contract</b>
                    <small>Filled in with the placement, hire date and pay above.</small>
                    <small class="apl-contract__draft lcy-hidden" id="aplHContractDraft"><i class="fa-solid fa-triangle-exclamation"></i> Sample wording until the final contract is in — check it before emailing.</small>
                  </div>
                  <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="aplHContractPreview"><i class="fa-regular fa-eye"></i> Preview</button>
                </div>
                <label class="apl-toggle apl-hire__welcome lcy-hidden" id="aplHireContractRow"><input type="checkbox" id="aplHireContract"><span class="apl-toggle__ui"></span>
                  <span><i class="fa-regular fa-envelope"></i> Email the contract <small>— in the email and as a printable attachment</small></span></label>
              </section>

              <label class="apl-toggle apl-hire__welcome lcy-hidden" id="aplHireWelcomeRow"><input type="checkbox" id="aplHireWelcome" checked><span class="apl-toggle__ui"></span>
                <span><i class="fa-regular fa-envelope"></i> Email a welcome message <small>— employee no. and username, never the password</small></span></label>
              <p class="lcy-modal-error" id="aplHireErr" role="alert"></p>
            </form>

            <div class="apl-hire__preview lcy-hidden" id="aplHirePreview">
              <div class="apl-hire__previewbar">
                <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="aplHirePreviewBack"><i class="fa-solid fa-arrow-left"></i> Back to the form</button>
                <b id="aplHirePreviewTitle"></b>
              </div>
              <iframe class="apl-hire__previewframe" id="aplHirePreviewFrame" title="Contract preview" sandbox=""></iframe>
            </div>

            <div class="apl-hire__done lcy-hidden" id="aplHireDone" role="status"></div>

            <footer class="apl-hire__foot">
              <span class="apl-hire__foot-note" id="aplHireFootNote">Creates a WeDo account — they start as the classification above.</span>
              <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal" id="aplHireCancel">Cancel</button>
              <button type="button" class="wd-btn wd-btn--success" id="aplHireSave"><i class="fa-solid fa-user-plus"></i> Create employee</button>
            </footer>
          </div>
        </div>
      </div>

      <!-- email setup: is Brevo working here? + a test email -->
      <div class="modal fade" id="aplMailModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title"><i class="fa-regular fa-envelope"></i> Email setup (Brevo)</h4>
            </div>
            <div class="modal-body">
              <p class="lcy-muted" style="margin-top:0">Emails to applicants need a Brevo API key and a verified sender in this server's <code>config.local.php</code>. Each step is checked below.</p>
              <ul class="apl-checks" id="aplMailChecks"><li class="is-wait"><i class="fa-solid fa-spinner fa-spin"></i> Checking…</li></ul>
              <form class="apl-mailtest" id="aplMailTestForm" autocomplete="off">
                <div class="wd-field"><label for="aplMailTo">Send a test email to</label><input type="email" class="wd-input" id="aplMailTo" maxlength="150" required placeholder="you@example.com"></div>
                <button type="submit" class="wd-btn wd-btn--primary" id="aplMailTestBtn"><i class="fa-solid fa-paper-plane"></i> Send test</button>
              </form>
              <p class="apl-mailres" id="aplMailTestRes" role="status"></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="wd-btn wd-btn--ghost" id="aplMailRecheck"><i class="fa-solid fa-rotate"></i> Check again</button>
              <button type="button" class="wd-btn wd-btn--primary" data-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>

      <!-- job openings -->
      <div class="modal fade apl-op" id="aplOpeningsModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="apl-op__head">
              <button type="button" class="apl-pro__close" data-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
              <span class="apl-op__icon"><i class="fa-solid fa-briefcase"></i></span>
              <div>
                <h4>Job openings</h4>
                <p id="aplOpeningsSum">The positions people can pick on the Apply page.</p>
              </div>
            </div>
            <div class="apl-op__body">
              <div class="apl-op__bar">
                <b>Positions</b>
                <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="aplOpeningNew"><i class="fa-solid fa-plus"></i> New opening</button>
              </div>
              <div id="aplOpeningsList" class="apl-op__list"></div>

              <form id="aplOpeningForm" class="apl-op__form" autocomplete="off">
                <input type="hidden" name="id" value="0">
                <div class="apl-op__formhead">
                  <span class="apl-op__formicon"><i class="fa-solid fa-plus" id="aplOpeningFormIcon"></i></span>
                  <div>
                    <h5 id="aplOpeningFormTitle">Add an opening</h5>
                    <small id="aplOpeningFormSub">Fill this in and it appears on the Apply page right away.</small>
                  </div>
                </div>
                <div class="apl-grid">
                  <div class="wd-field"><label for="aplOTitle">Job title <span class="apl-op__req">*</span></label>
                    <input class="wd-input" id="aplOTitle" name="title" maxlength="120" required placeholder="e.g. Bookkeeper"></div>
                  <div class="wd-field"><label for="aplOLoc">Location</label>
                    <div class="apl-op__ico"><i class="fa-solid fa-location-dot"></i><input class="wd-input" id="aplOLoc" name="location" maxlength="120" placeholder="e.g. Pasig City, on-site"></div></div>
                </div>
                <div class="wd-field">
                  <label for="aplODesc">Description <span class="apl-op__opt">optional</span></label>
                  <textarea class="wd-textarea" id="aplODesc" name="description" rows="4" maxlength="2000" placeholder="What the job is, requirements, schedule"></textarea>
                  <span class="apl-op__count" id="aplODescCount">0 / 2000</span>
                </div>
                <label class="apl-toggle apl-op__toggle">
                  <input type="checkbox" name="open" value="1" checked>
                  <span class="apl-toggle__ui"></span>
                  <span><b>Accepting applications</b><small>Shown on the Apply page. Turn off to hide it without deleting.</small></span>
                </label>
                <p class="lcy-modal-error" id="aplOpeningErr"></p>
                <div class="apl-op__foot">
                  <button type="button" class="wd-btn wd-btn--ghost" id="aplOpeningCancel" hidden>Cancel edit</button>
                  <button type="submit" class="wd-btn wd-btn--primary" id="aplOpeningSave"><i class="fa-solid fa-check"></i> <span>Add opening</span></button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <script>window.LCY_BOOT = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
      <script src="assets/js/lifecycle-common.js?v=<?php echo $v('assets/js/lifecycle-common.js'); ?>"></script>
      <script src="assets/js/applicants.js?v=<?php echo $v('assets/js/applicants.js'); ?>"></script>
<?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>
  </body>
</html>
