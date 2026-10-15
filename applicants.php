<?php
/* ==========================================================================
   applicants.php  —  Management > Applicants.
   Registrations from the public Apply page (apply.php): filter by position
   and stage, open one to see the full profile, resume, the rehire check
   against former employees and the stage history, move it along (Screening
   -> Interview -> Exam -> Job offer), then "Hire": a short dialog (company,
   department, position, superior, pay) and WeDo creates the employee from
   the registration, as Trainee — the temporary password is shown once.
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
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!apl_can_manage($aplpdo)) { header('location: 404?'); exit(); }

$ready = apl_ready($aplpdo);
$boot = [
    'token'     => apl_csrf_token(),
    'stages'    => APL_STAGES,
    'hireStage' => APL_HIRE_STAGE,
    'canMail'   => wd_mail_enabled(),          // Brevo set up: offer emails to the applicant
    'mailStages'=> APL_MAIL_STAGES,
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
        <button type="button" class="wd-btn wd-btn--ghost" id="aplOpeningsBtn"><i class="fa-solid fa-briefcase"></i> Job openings</button>
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
                <section class="apl-card">
                  <h5 class="apl-card__title"><i class="fa-solid fa-clock-rotate-left"></i> Activity</h5>
                  <ul class="apl-feed" id="aplHistory"></ul>
                </section>
              </aside>
            </div>
          </div>
        </div>
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
                <div class="apl-grid">
                  <div class="wd-field"><label for="aplHClass">Classification</label><select class="wd-select" id="aplHClass" name="classification"></select></div>
                  <div class="wd-field"><label for="aplHDate">Hire date *</label><input type="date" class="wd-input" id="aplHDate" name="hired" required></div>
                  <div class="wd-field"><label for="aplHAgency">Agency *</label><select class="wd-select" id="aplHAgency" name="agency" required></select></div>
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
                <h5><span>4</span> HMO</h5>
                <div class="apl-grid">
                  <div class="wd-field"><label for="aplHHmo">HMO provider *</label><select class="wd-select" id="aplHHmo" name="hmo" required></select></div>
                  <div class="wd-field"><label for="aplHHmoNo">HMO number</label><input class="wd-input" id="aplHHmoNo" name="hmoNumber" maxlength="100" placeholder="Optional"></div>
                </div>
              </section>

              <label class="apl-toggle apl-hire__welcome lcy-hidden" id="aplHireWelcomeRow"><input type="checkbox" id="aplHireWelcome" checked><span class="apl-toggle__ui"></span>
                <span><i class="fa-regular fa-envelope"></i> Email a welcome message <small>— employee no. and username, never the password</small></span></label>
              <p class="lcy-modal-error" id="aplHireErr" role="alert"></p>
            </form>

            <div class="apl-hire__done lcy-hidden" id="aplHireDone" role="status"></div>

            <footer class="apl-hire__foot">
              <span class="apl-hire__foot-note" id="aplHireFootNote">Creates a WeDo account — they start as the classification above.</span>
              <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal" id="aplHireCancel">Cancel</button>
              <button type="button" class="wd-btn wd-btn--success" id="aplHireSave"><i class="fa-solid fa-user-plus"></i> Create employee</button>
            </footer>
          </div>
        </div>
      </div>

      <!-- job openings -->
      <div class="modal fade" id="aplOpeningsModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title">Job openings</h4>
            </div>
            <div class="modal-body">
              <div id="aplOpeningsList"></div>
              <form id="aplOpeningForm" autocomplete="off" style="margin-top:16px;border-top:1px solid var(--border);padding-top:14px">
                <input type="hidden" name="id" value="0">
                <h4 id="aplOpeningFormTitle" style="font-size:14px;margin-bottom:10px">Add an opening</h4>
                <div class="wd-field"><label for="aplOTitle">Job title</label><input class="wd-input" id="aplOTitle" name="title" maxlength="120" required></div>
                <div class="wd-field"><label for="aplOLoc">Location</label><input class="wd-input" id="aplOLoc" name="location" maxlength="120" placeholder="e.g. Pasig City, on-site"></div>
                <div class="wd-field"><label for="aplODesc">Description</label><textarea class="wd-textarea" id="aplODesc" name="description" rows="4" maxlength="2000" placeholder="What the job is, requirements, schedule"></textarea></div>
                <label class="lcy-check"><input type="checkbox" name="open" value="1" checked> Shown on the Apply page</label>
                <p class="lcy-modal-error" id="aplOpeningErr"></p>
                <div class="lcy-actions">
                  <button type="submit" class="wd-btn wd-btn--primary"><i class="fa-solid fa-check"></i> Save opening</button>
                  <button type="button" class="wd-btn wd-btn--ghost" id="aplOpeningNew">New</button>
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
