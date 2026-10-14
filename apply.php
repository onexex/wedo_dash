<?php
/* ==========================================================================
   apply.php  —  PUBLIC job registration page (no sign-in, no applicant login).
   Lists the open positions (Management > Applicants > Job openings) and takes
   a full 201 profile — personal details, address, government IDs, education,
   previous job, family / emergency contacts — plus a resume and the Data
   Privacy consent. When HR hires the applicant, this data is copied into the
   employee tables (includes/applicants-lib.php apl_hire), so nobody retypes it.
   Submits to query/apply-action.php. Linked from the sign-in page.
   Security: strict CSP (no inline script), no framing, spam guards and
   Turnstile (when configured) — see includes/applicants-lib.php.
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
$field = function ($name, $label, $attrs = '', $type = 'text') use ($e) {
    return '<div class="wd-field"><label for="apl_' . $name . '">' . $label . '</label><input type="' . $type . '" class="wd-input" id="apl_'
         . $name . '" name="' . $name . '" ' . $attrs . '></div>';
};
$famRow = function ($i) use ($e, $rels) {
    $opts = '<option value="">—</option>';
    foreach ($rels as $r) { $opts .= '<option>' . $e($r) . '</option>'; }
    return '<div class="apl-fam" data-row="' . $i . '">'
         . '<div class="wd-field"><label>Full name</label><input class="wd-input" name="fam[' . $i . '][name]" maxlength="150"></div>'
         . '<div class="wd-field"><label>Relationship</label><select class="wd-select" name="fam[' . $i . '][relationship]">' . $opts . '</select></div>'
         . '<div class="wd-field"><label>Contact number</label><input type="tel" class="wd-input" name="fam[' . $i . '][contact]" maxlength="30"></div>'
         . '<div class="wd-field apl-span2"><label>Address</label><input class="wd-input" name="fam[' . $i . '][address]" maxlength="250"></div>'
         . '<label class="lcy-check apl-ice"><input type="checkbox" name="fam[' . $i . '][emergency]" value="1"' . ($i === 0 ? ' checked' : '') . '> Contact in case of emergency</label>'
         . '</div>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Careers | WeDo BPO Inc.</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Apply for a job at WeDo BPO Inc.">
    <link rel="icon" type="image/png" href="assets/images/logos/wedo-favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
    <link rel="stylesheet" href="assets/css/applicants.css?v=<?php echo $v('assets/css/applicants.css'); ?>">
<?php if ($ready && $ts): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</head>
<body class="apl-public">
    <header class="apl-hero">
        <div class="apl-wrap">
            <a href="login.php"><img src="assets/images/logos/wedo-logo.png" alt="WeDo BPO Inc." class="apl-logo"></a>
            <h1>Join WeDo BPO</h1>
            <p>Register your application once — if you are hired, HR uses these details for your employee record, so please make sure they are correct.</p>
        </div>
    </header>

    <main class="apl-wrap apl-main">
<?php if (!$ready): ?>
        <section class="wd-card"><div class="lcy-empty">We are not accepting applications online right now. Please check back soon.</div></section>
<?php else: ?>
        <?php if ($openings): ?>
        <section class="apl-openings" aria-label="Open positions">
            <h2>Open positions</h2>
            <div class="apl-jobgrid">
            <?php foreach ($openings as $o): ?>
                <article class="apl-job">
                    <h3><?php echo $e($o['title']); ?></h3>
                    <?php if ($o['location'] !== ''): ?><p class="apl-job__loc"><i class="fa-solid fa-location-dot"></i> <?php echo $e($o['location']); ?></p><?php endif; ?>
                    <?php if ($o['description'] !== ''): ?><p class="apl-job__desc"><?php echo nl2br($e($o['description'])); ?></p><?php endif; ?>
                    <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-job="<?php echo (int) $o['id']; ?>">Apply for this role</button>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="wd-card" id="aplFormCard">
            <div class="wd-card__head"><h3>Applicant registration</h3><span class="lcy-muted">Fields marked * are required</span></div>
            <form id="aplForm" class="apl-form" enctype="multipart/form-data" novalidate autocomplete="on">
                <input type="hidden" name="token" value="<?php echo $e($token); ?>">
                <!-- people never see this field; automated spam fills it in -->
                <div class="apl-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <h4 class="apl-sec">Position</h4>
                <div class="wd-field">
                    <label for="aplOpening">Position you are applying for *</label>
                    <select class="wd-select" id="aplOpening" name="opening">
                        <option value="0">Any open position</option>
                        <?php foreach ($openings as $o): ?>
                        <option value="<?php echo (int) $o['id']; ?>"<?php echo $pick === $o['id'] ? ' selected' : ''; ?>><?php echo $e($o['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h4 class="apl-sec">Personal information</h4>
                <div class="apl-grid">
                    <?php echo $field('firstName', 'First name *', 'maxlength="80" required autocomplete="given-name"');
                          echo $field('middleName', 'Middle name', 'maxlength="80" autocomplete="additional-name"');
                          echo $field('lastName', 'Last name *', 'maxlength="80" required autocomplete="family-name"');
                          echo $field('suffix', 'Suffix', 'maxlength="10" placeholder="Jr, III"');
                          echo $field('birthDate', 'Date of birth *', 'required autocomplete="bday"', 'date'); ?>
                    <div class="wd-field"><label for="apl_gender">Gender *</label>
                        <select class="wd-select" id="apl_gender" name="gender" required><option value="">—</option><?php foreach (APL_GENDERS as $g) { echo '<option>' . $g . '</option>'; } ?></select></div>
                    <div class="wd-field"><label for="apl_civilStatus">Civil status *</label>
                        <select class="wd-select" id="apl_civilStatus" name="civilStatus" required><option value="">—</option><?php foreach (APL_CIVIL as $c) { echo '<option>' . $c . '</option>'; } ?></select></div>
                    <?php echo $field('citizenship', 'Citizenship', 'maxlength="60" value="Filipino"');
                          echo $field('religion', 'Religion', 'maxlength="60"'); ?>
                </div>

                <h4 class="apl-sec">Contact &amp; address</h4>
                <div class="apl-grid">
                    <?php echo $field('email', 'Email *', 'maxlength="150" required autocomplete="email"', 'email');
                          echo $field('mobile', 'Mobile number *', 'maxlength="30" required autocomplete="tel" placeholder="09xx xxx xxxx"', 'tel');
                          echo $field('homePhone', 'Home phone', 'maxlength="30"', 'tel'); ?>
                    <div class="wd-field apl-span2"><label for="apl_street">House no. / street / subdivision *</label><input class="wd-input" id="apl_street" name="street" maxlength="150" required autocomplete="address-line1"></div>
                    <?php echo $field('barangay', 'Barangay *', 'maxlength="100" required');
                          echo $field('city', 'City / municipality *', 'maxlength="100" required autocomplete="address-level2"');
                          echo $field('province', 'Province *', 'maxlength="100" required autocomplete="address-level1"');
                          echo $field('zip', 'ZIP code', 'maxlength="10" autocomplete="postal-code"');
                          echo $field('country', 'Country *', 'maxlength="60" required value="Philippines" autocomplete="country-name"'); ?>
                </div>

                <h4 class="apl-sec">Government IDs <span class="lcy-muted">(if you have them)</span></h4>
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

                <h4 class="apl-sec">Education <span class="lcy-muted">(at least one school)</span></h4>
                <div class="apl-edu">
                <?php foreach (APL_PROGRAMS as $prog): ?>
                    <div class="apl-edu__row">
                        <b><?php echo $prog === 'Tertiary' ? 'College / Tertiary' : ($prog === 'Secondary' ? 'High school' : 'Elementary'); ?></b>
                        <div class="wd-field"><label>School</label><input class="wd-input" name="edu[<?php echo $prog; ?>][school]" maxlength="100"></div>
                        <div class="wd-field"><label>Year started</label><input class="wd-input" name="edu[<?php echo $prog; ?>][from]" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="YYYY"></div>
                        <div class="wd-field"><label>Year ended</label><input class="wd-input" name="edu[<?php echo $prog; ?>][to]" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="YYYY"></div>
                        <div class="wd-field"><label>School address</label><input class="wd-input" name="edu[<?php echo $prog; ?>][address]" maxlength="100"></div>
                    </div>
                <?php endforeach; ?>
                </div>

                <h4 class="apl-sec">Most recent job <span class="lcy-muted">(if any)</span></h4>
                <div class="apl-grid">
                    <?php echo $field('prevCompany', 'Company', 'maxlength="150"');
                          echo $field('prevPosition', 'Position', 'maxlength="100"');
                          echo $field('prevDept', 'Department', 'maxlength="100"');
                          echo $field('prevStart', 'Start date', '', 'date'); ?>
                </div>

                <h4 class="apl-sec">Family &amp; emergency contacts * <span class="lcy-muted">(at least one emergency contact)</span></h4>
                <div id="aplFamily">
                    <?php echo $famRow(0); echo $famRow(1); ?>
                </div>
                <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="aplAddFam" data-max="<?php echo APL_MAX_FAMILY; ?>"><i class="fa-solid fa-plus"></i> Add another person</button>
                <template id="aplFamTpl"><?php echo $famRow('__i__'); ?></template>

                <h4 class="apl-sec">Resume</h4>
                <div class="apl-grid">
                    <div class="wd-field">
                        <label for="aplResume">Resume / CV * <span class="lcy-muted">(PDF or Word, up to 5 MB)</span></label>
                        <input type="file" class="wd-input" id="aplResume" name="resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                    </div>
                    <div class="wd-field"><label for="apl_source">How did you hear about us?</label>
                        <select class="wd-select" id="apl_source" name="source"><option value="">—</option><option>Facebook</option><option>JobStreet</option><option>Indeed</option><option>LinkedIn</option><option>Referral</option><option>Walk-in</option><option>Other</option></select></div>
                </div>

                <div class="apl-consent">
                    <label class="lcy-check"><input type="checkbox" name="consent" value="1" required>
                        <span>I certify that the information above is true, and I agree that WeDo BPO Inc. may collect and process my personal
                        information for recruitment and, if I am hired, for my employment records, in line with the Data Privacy Act of 2012
                        (RA 10173). If I am not hired, my application may be deleted.</span></label>
                </div>
<?php if ($ts): ?>
                <div class="cf-turnstile" data-sitekey="<?php echo $e($ts['site']); ?>"></div>
<?php endif; ?>

                <p class="lcy-modal-error" id="aplErr" role="alert"></p>
                <button type="submit" class="wd-btn wd-btn--primary apl-submit" id="aplSend"><i class="fa-solid fa-paper-plane"></i> Submit registration</button>
            </form>
            <div class="apl-done lcy-hidden" id="aplDone">
                <i class="fa-solid fa-circle-check"></i>
                <h3>Registration received</h3>
                <p id="aplDoneMsg"></p>
            </div>
        </section>
<?php endif; ?>
        <p class="apl-foot">Already part of the team? <a href="login.php">Sign in to WeDo</a></p>
    </main>
<?php if ($ready): ?>
    <script src="assets/js/apply.js?v=<?php echo $v('assets/js/apply.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
