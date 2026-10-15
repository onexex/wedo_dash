<?php
/* ==========================================================================
   apply.php  —  PUBLIC job registration page (no sign-in, no applicant login).
   A 6-step application: Position -> Personal -> Contact & address ->
   Background (IDs, education, last job) -> Family & emergency -> Resume &
   review. Collects a full 201 profile so that, when HR hires the applicant,
   includes/applicants-lib.php apl_hire() copies it into the employee tables.
   Steps are client-side only (assets/js/apply.js); the server validates the
   whole form again (apl_validate). Submits to query/apply-action.php.
   Security: strict CSP (no inline script), no framing, spam guards and
   Turnstile (when configured) — see includes/applicants-lib.php.
   Shared as ".../apply" (.htaccess sends /apply.php there); it deliberately
   has no link to the employee sign-in page.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
include 'w_conn.php';
require_once __DIR__ . '/includes/applicants-lib.php';

$ts = apl_turnstile();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'" . ($ts ? ' https://challenges.cloudflare.com' : '')
     . "; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com"
     . "; font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:"
     . "; connect-src 'self'; frame-src " . ($ts ? 'https://challenges.cloudflare.com' : "'none'")
     . "; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");

$ready = false;
$openings = [];
$rels = [];
try {
    $aplpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $aplpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $ready = apl_ready($aplpdo);
    if ($ready) { $openings = apl_openings($aplpdo, true); $rels = apl_relationships($aplpdo); }
} catch (Throwable $e) { $ready = false; }
$token = apl_public_token();
$pick  = (int) ($_GET['job'] ?? 0);
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };

/* one labelled input; $hint shows under it, errors are added by apply.js */
$field = function ($name, $label, $attrs = '', $type = 'text', $hint = '', $wide = false) use ($e) {
    return '<div class="apl-f' . ($wide ? ' apl-f--wide' : '') . '"><label for="apl_' . $name . '">' . $label . '</label>'
         . '<input type="' . $type . '" class="wd-input" id="apl_' . $name . '" name="' . $name . '" ' . $attrs . '>'
         . ($hint !== '' ? '<small class="apl-hint">' . $hint . '</small>' : '') . '</div>';
};
$select = function ($name, $label, array $opts, $required = false) use ($e) {
    $o = '<option value="">Select…</option>';
    foreach ($opts as $x) { $o .= '<option>' . $e($x) . '</option>'; }
    return '<div class="apl-f"><label for="apl_' . $name . '">' . $label . '</label><select class="wd-select" id="apl_' . $name
         . '" name="' . $name . '"' . ($required ? ' required' : '') . '>' . $o . '</select></div>';
};
$famRow = function ($i) use ($e, $rels) {
    $opts = '<option value="">Select…</option>';
    foreach ($rels as $r) { $opts .= '<option>' . $e($r) . '</option>'; }
    return '<fieldset class="apl-person" data-row="' . $i . '"><legend>Person <span class="apl-person__n"></span></legend>'
         . '<button type="button" class="apl-person__del" aria-label="Remove this person"><i class="fa-solid fa-xmark"></i></button>'
         . '<div class="apl-grid">'
         . '<div class="apl-f"><label>Full name</label><input class="wd-input" name="fam[' . $i . '][name]" maxlength="150" autocomplete="off" placeholder="First and last name"></div>'
         . '<div class="apl-f"><label>Relationship</label><select class="wd-select" name="fam[' . $i . '][relationship]">' . $opts . '</select></div>'
         . '<div class="apl-f"><label>Mobile number</label><input type="tel" class="wd-input" name="fam[' . $i . '][contact]" maxlength="16" autocomplete="off" inputmode="numeric" placeholder="09xx xxx xxxx" data-mobile></div>'
         . '<div class="apl-f"><label>Address</label><input class="wd-input" name="fam[' . $i . '][address]" maxlength="250" autocomplete="off"></div>'
         . '</div>'
         . '<label class="apl-toggle"><input type="checkbox" name="fam[' . $i . '][emergency]" value="1"' . ($i === 0 ? ' checked' : '') . '>'
         . '<span class="apl-toggle__ui"></span> Contact this person in case of emergency</label>'
         . '</fieldset>';
};
$steps = ['Position', 'Personal', 'Contact', 'Background', 'Family', 'Review'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Careers | WeDo BPO Inc.</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Build your career with WeDo BPO Inc. — see our open positions and apply online.">
    <meta name="theme-color" content="#09152e">
    <link rel="icon" type="image/png" href="assets/images/logos/wedo-favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <link rel="stylesheet" href="assets/css/applicants.css?v=<?php echo $v('assets/css/applicants.css'); ?>">
<?php if ($ready && $ts): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
<?php endif; ?>
</head>
<body class="apl-public">
    <header class="apl-hero">
        <div class="apl-wrap">
            <nav class="apl-top">
                <img src="assets/images/logos/wedo-logo.png" alt="WeDo BPO Inc." class="apl-logo">
            </nav>
            <div class="apl-hero__body">
                <span class="apl-eyebrow"><i class="fa-solid fa-briefcase"></i> Careers at WeDo BPO</span>
                <h1>Build your career with us</h1>
                <p>Apply once, online. HR reviews every application and contacts you by email or phone.</p>
                <ul class="apl-perks">
                    <li><i class="fa-regular fa-clock"></i> About 10 minutes</li>
                    <li><i class="fa-solid fa-mobile-screen"></i> Works on your phone</li>
                    <li><i class="fa-solid fa-shield-halved"></i> Protected under the Data Privacy Act</li>
                </ul>
<?php if ($ready): ?>
                <a href="#aplApp" class="wd-btn wd-btn--primary apl-cta">Start your application <i class="fa-solid fa-arrow-down"></i></a>
<?php endif; ?>
            </div>
        </div>
    </header>

    <main class="apl-wrap apl-main">
<?php if (!$ready): ?>
        <section class="apl-panel"><div class="lcy-empty">We are not accepting applications online right now. Please check back soon.</div></section>
<?php else: ?>
        <?php if ($openings): ?>
        <section class="apl-jobs" aria-labelledby="aplJobsTitle">
            <div class="apl-sechead">
                <h2 id="aplJobsTitle">Open positions</h2>
                <span class="apl-count"><?php echo count($openings); ?> open</span>
            </div>
            <div class="apl-jobgrid">
            <?php foreach ($openings as $o): ?>
                <article class="apl-job" data-job-card="<?php echo (int) $o['id']; ?>">
                    <div class="apl-job__icon"><i class="fa-solid fa-user-tie"></i></div>
                    <h3><?php echo $e($o['title']); ?></h3>
                    <?php if ($o['location'] !== ''): ?><p class="apl-job__loc"><i class="fa-solid fa-location-dot"></i> <?php echo $e($o['location']); ?></p><?php endif; ?>
                    <?php if ($o['description'] !== ''): ?>
                        <p class="apl-job__desc"><?php echo nl2br($e($o['description'])); ?></p>
                        <button type="button" class="apl-job__more" aria-expanded="false">Read more</button>
                    <?php endif; ?>
                    <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm apl-job__apply" data-job="<?php echo (int) $o['id']; ?>">
                        <span class="apl-job__apply-t">Apply for this role</span><span class="apl-job__picked"><i class="fa-solid fa-check"></i> Selected</span>
                    </button>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="apl-panel" id="aplApp" aria-labelledby="aplAppTitle">
            <div class="apl-panel__head">
                <h2 id="aplAppTitle">Your application</h2>
                <p class="apl-stepnow" id="aplStepNow" aria-live="polite">Step 1 of <?php echo count($steps); ?> · Position</p>
                <div class="apl-progress" aria-hidden="true"><span id="aplBar"></span></div>
                <ol class="apl-stepper" id="aplStepper">
                    <?php foreach ($steps as $i => $s): ?>
                    <li data-go="<?php echo $i; ?>"<?php echo $i === 0 ? ' class="is-on"' : ''; ?>><span class="apl-dot"><?php echo $i + 1; ?></span><span class="apl-lbl"><?php echo $s; ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <form id="aplForm" class="apl-form" enctype="multipart/form-data" novalidate autocomplete="on">
                <input type="hidden" name="token" value="<?php echo $e($token); ?>">
                <!-- people never see this field; automated spam fills it in -->
                <div class="apl-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <!-- 1 · Position -->
                <div class="apl-step is-on" data-step="0">
                    <h3 class="apl-step__title">Which position are you applying for?</h3>
                    <div class="apl-choices" role="radiogroup" aria-label="Position">
                        <label class="apl-choice"><input type="radio" name="opening" value="0"<?php echo $pick ? '' : ' checked'; ?>>
                            <span><b>Any open position</b><small>Let HR match you to a role</small></span></label>
                        <?php foreach ($openings as $o): ?>
                        <label class="apl-choice"><input type="radio" name="opening" value="<?php echo (int) $o['id']; ?>"<?php echo $pick === $o['id'] ? ' checked' : ''; ?>>
                            <span><b><?php echo $e($o['title']); ?></b><?php if ($o['location'] !== ''): ?><small><?php echo $e($o['location']); ?></small><?php endif; ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <?php echo $select('source', 'How did you hear about us?', ['Facebook', 'JobStreet', 'Indeed', 'LinkedIn', 'Referral', 'Walk-in', 'Other']); ?>
                </div>

                <!-- 2 · Personal -->
                <div class="apl-step" data-step="1" hidden>
                    <h3 class="apl-step__title">Tell us about yourself</h3>
                    <p class="apl-step__sub">Use your name exactly as it appears on your government ID.</p>
                    <div class="apl-grid">
                        <?php echo $field('firstName', 'First name *', 'maxlength="80" required autocomplete="given-name"');
                              echo $field('lastName', 'Last name *', 'maxlength="80" required autocomplete="family-name"');
                              echo $field('middleName', 'Middle name', 'maxlength="80" autocomplete="additional-name"');
                              echo $field('suffix', 'Suffix', 'maxlength="10" placeholder="Jr, III"');
                              echo $field('birthDate', 'Date of birth *', 'required autocomplete="bday"', 'date');
                              echo $select('gender', 'Gender *', APL_GENDERS, true);
                              echo $select('civilStatus', 'Civil status *', APL_CIVIL, true);
                              echo $field('citizenship', 'Citizenship', 'maxlength="60" value="Filipino"');
                              echo $field('religion', 'Religion', 'maxlength="60"'); ?>
                    </div>
                </div>

                <!-- 3 · Contact & address -->
                <div class="apl-step" data-step="2" hidden>
                    <h3 class="apl-step__title">How can we reach you?</h3>
                    <div class="apl-grid">
                        <?php echo $field('email', 'Email *', 'maxlength="150" required autocomplete="email" placeholder="you@example.com"', 'email');
                              echo $field('mobile', 'Mobile number *', 'maxlength="16" required autocomplete="tel" inputmode="numeric" placeholder="09xx xxx xxxx" data-mobile', 'tel');
                              echo $field('homePhone', 'Home phone', 'maxlength="30" inputmode="tel"', 'tel');
                              echo $field('street', 'House no. / street / subdivision *', 'maxlength="150" required autocomplete="address-line1"', 'text', '', true);
                              echo $field('barangay', 'Barangay *', 'maxlength="100" required');
                              echo $field('city', 'City / municipality *', 'maxlength="100" required autocomplete="address-level2"');
                              echo $field('province', 'Province *', 'maxlength="100" required autocomplete="address-level1"');
                              echo $field('zip', 'ZIP code', 'maxlength="10" inputmode="numeric" autocomplete="postal-code"');
                              echo $field('country', 'Country *', 'maxlength="60" required value="Philippines" autocomplete="country-name"'); ?>
                    </div>
                </div>

                <!-- 4 · Background -->
                <div class="apl-step" data-step="3" hidden>
                    <h3 class="apl-step__title">Education</h3>
                    <p class="apl-step__sub">At least one school. Leave the rest blank if they don't apply.</p>
                    <?php foreach (APL_PROGRAMS as $prog): ?>
                    <div class="apl-school">
                        <span class="apl-school__lvl"><?php echo $prog === 'Tertiary' ? 'College' : ($prog === 'Secondary' ? 'High school' : 'Elementary'); ?></span>
                        <div class="apl-grid apl-grid--school">
                            <div class="apl-f apl-f--wide"><label>School</label><input class="wd-input" name="edu[<?php echo $prog; ?>][school]" maxlength="100"></div>
                            <div class="apl-f"><label>From</label><input class="wd-input" name="edu[<?php echo $prog; ?>][from]" inputmode="numeric" maxlength="4" placeholder="YYYY"></div>
                            <div class="apl-f"><label>To</label><input class="wd-input" name="edu[<?php echo $prog; ?>][to]" inputmode="numeric" maxlength="4" placeholder="YYYY"></div>
                            <div class="apl-f apl-f--wide"><label>School address</label><input class="wd-input" name="edu[<?php echo $prog; ?>][address]" maxlength="100"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <details class="apl-more">
                        <summary><i class="fa-solid fa-id-card"></i> Government IDs <small>(optional — add them if you have them)</small></summary>
                        <div class="apl-grid">
                            <?php echo $field('sss', 'SSS number', 'maxlength="30"');
                                  echo $field('tin', 'TIN', 'maxlength="30"');
                                  echo $field('philhealth', 'PhilHealth number', 'maxlength="30"');
                                  echo $field('pagibig', 'Pag-IBIG number', 'maxlength="30"');
                                  echo $field('umid', 'UMID', 'maxlength="30"');
                                  echo $field('passportNo', 'Passport number', 'maxlength="30"');
                                  echo $field('passportExpiry', 'Passport expiry', '', 'date');
                                  echo $field('passportIssuer', 'Passport issued by', 'maxlength="80" placeholder="e.g. DFA Manila"'); ?>
                        </div>
                    </details>
                    <details class="apl-more">
                        <summary><i class="fa-solid fa-building"></i> Most recent job <small>(optional)</small></summary>
                        <div class="apl-grid">
                            <?php echo $field('prevCompany', 'Company', 'maxlength="150"');
                                  echo $field('prevPosition', 'Position', 'maxlength="100"');
                                  echo $field('prevDept', 'Department', 'maxlength="100"');
                                  echo $field('prevStart', 'Start date', '', 'date'); ?>
                        </div>
                    </details>
                </div>

                <!-- 5 · Family -->
                <div class="apl-step" data-step="4" hidden>
                    <h3 class="apl-step__title">Family &amp; emergency contacts</h3>
                    <p class="apl-step__sub">Add at least one person we can call in an emergency.</p>
                    <div id="aplFamily"><?php echo $famRow(0); ?></div>
                    <button type="button" class="apl-add" id="aplAddFam" data-max="<?php echo APL_MAX_FAMILY; ?>"><i class="fa-solid fa-plus"></i> Add another person</button>
                    <template id="aplFamTpl"><?php echo $famRow('__i__'); ?></template>
                </div>

                <!-- 6 · Resume & review -->
                <div class="apl-step" data-step="5" hidden>
                    <h3 class="apl-step__title">Resume</h3>
                    <label class="apl-drop" id="aplDrop" for="aplResume">
                        <input type="file" id="aplResume" name="resume" required
                               accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        <span class="apl-drop__empty"><i class="fa-solid fa-cloud-arrow-up"></i><b>Drop your resume here</b> or <u>browse</u><small>PDF or Word · up to 5 MB</small></span>
                        <span class="apl-drop__file"><i class="fa-solid fa-file-lines"></i><span><b id="aplFileName"></b><small id="aplFileSize"></small></span>
                            <button type="button" class="apl-drop__del" id="aplFileDel" aria-label="Remove the file"><i class="fa-solid fa-xmark"></i></button></span>
                    </label>

                    <h3 class="apl-step__title" style="margin-top:26px">Check your details</h3>
                    <div class="apl-review" id="aplReview"></div>

                    <div class="apl-consent">
                        <label class="apl-toggle apl-toggle--top"><input type="checkbox" name="consent" value="1" required><span class="apl-toggle__ui"></span>
                            <span>I certify that the information above is true, and I agree that WeDo BPO Inc. may collect and process my personal
                            information for recruitment and, if I am hired, for my employment records, in line with the Data Privacy Act of 2012
                            (RA 10173). If I am not hired, my application may be deleted.</span></label>
                    </div>
<?php if ($ts): ?>
                    <div id="aplTurnstile" class="apl-turnstile" data-sitekey="<?php echo $e($ts['site']); ?>"></div>
<?php endif; ?>
                </div>

                <p class="apl-err" id="aplErr" role="alert"></p>
                <div class="apl-nav">
                    <button type="button" class="wd-btn wd-btn--ghost" id="aplBack" hidden><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <span class="apl-nav__sp"></span>
                    <button type="button" class="wd-btn wd-btn--primary" id="aplNext">Continue <i class="fa-solid fa-arrow-right"></i></button>
                    <button type="submit" class="wd-btn wd-btn--primary" id="aplSend" hidden><i class="fa-solid fa-paper-plane"></i> Submit application</button>
                </div>
            </form>

            <div class="apl-done" id="aplDone" hidden>
                <div class="apl-done__badge"><i class="fa-solid fa-check"></i></div>
                <h3>Application received!</h3>
                <p id="aplDoneMsg"></p>
                <p class="apl-ref" id="aplRef" hidden>Reference no. <b id="aplRefNo"></b></p>
                <ol class="apl-next">
                    <li><b>HR reviews</b> your application — usually within a few working days.</li>
                    <li><b>We contact you</b> by email or phone for an interview or assessment.</li>
                    <li><b>If you are hired</b>, your details here become your employee record — no need to fill them in again.</li>
                </ol>
            </div>
        </section>
<?php endif; ?>
        <footer class="apl-foot">
            <span>© <?php echo date('Y'); ?> WeDo BPO Inc.</span>
            <span>Your information is protected under the Data Privacy Act of 2012.</span>
        </footer>
    </main>
<?php if ($ready): ?>
    <script src="assets/js/apply.js?v=<?php echo $v('assets/js/apply.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
