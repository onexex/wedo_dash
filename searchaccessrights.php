<?php
/* ==========================================================================
   searchaccessrights.php  —  AJAX partial for accessrights.php
   Returns the access sheet for one employee (?q=<EmpID>): one card per group
   (Modules, Reports, ...) holding a switch per access column, injected into
   #accr. Each switch keeps id = the access column, because accessrights.php
   sends that id to updateaccess.php as `term`.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
include 'w_conn.php';
require_once __DIR__ . '/includes/msgaudit-gate.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

$id = isset($_GET['q']) ? $_GET['q'] : '';
if ($id === '') { return; }

$statement = $pdo->prepare("select * from accessrights where EmpID = :id");
$statement->bindParam(':id', $id);
$statement->execute();
$row = $statement->fetch();
if (!$row) {
    echo '<div class="ar-empty"><i class="fa-solid fa-circle-exclamation"></i>'
       . '<b>No access-rights record for this employee.</b></div>';
    return;
}

$emp = $pdo->prepare("select EmpLN, EmpFN, EmpMN from employees where EmpID = :id");
$emp->execute([':id' => $id]);
$empRow  = $emp->fetch(PDO::FETCH_ASSOC) ?: [];
$empName = trim(preg_replace('/\s+/', ' ', ($empRow['EmpFN'] ?? '') . ' ' . ($empRow['EmpMN'] ?? '') . ' ' . ($empRow['EmpLN'] ?? '')));

function ar_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

/* rights that can do real damage when handed out: never bulk-switched, and
   accessrights.php asks before changing them one at a time */
$sensitive = ['arights', 'dbbackup', 'msgaudit', 'aftersep'];

/* [icon, label, column, hint] per group; columns added by later migrations are
   only listed once they exist (a missing column would read as ON) */
$groups = [];

$groups['Modules'] = ['fa-cubes', [
    ['fa-folder',              'Electronic 201 File',                'e201'],
    ['fa-calendar-check',      'Leave Credit Overview',              'lcreaditview'],
    ['fa-calendar-plus',       'Leave Credit Management',            'lcreditedit'],
    ['fa-calendar-check',      'Automated Leave Application System', 'alas'],
    ['fa-file',                'Check Register',                     'checkregister'],
    ['fa-file-lines',          'Memo Generator',                     'memo'],
    ['fa-clock',               'Payroll Management System',          'payroll'],
    ['fa-money-bill-wave',     'Payslip',                            'payslipt'],
    ['fa-file-invoice-dollar', 'Debit Advise Module',                'debitadvise'],
    ['fa-box-archive',         'Archived Management System',         'ams'],
    ['fa-calendar-days',       'Employee Scheduling',                'EF'],
    ['fa-clock',               'Overtime OB System',                 'otob'],
    ['fa-briefcase',           'Official Business Trip Tracker',     'ob'],
    ['fa-plane',               'Send to OB Trip',                    'sob'],
    ['fa-business-time',       'Overtime Filing System',             'ot'],
    ['fa-calendar-minus',      'Early Out System',                   'eo'],
    ['fa-clipboard',           'New Blog',                           'nwblogs'],
]];

$groups['Reports'] = ['fa-chart-column', [
    ['fa-chart-column',    'ALAS Viewer',                   'alasv'],
    ['fa-chart-column',    'Attendance Viewer',             'lilov'],
    ['fa-chart-column',    'Compliance Document Data',      'cddv'],
    ['fa-chart-column',    'Daily Activity Viewer',         'darv'],
    ['fa-chart-column',    'Early Out Viewer',              'eov'],
    ['fa-chart-column',    'Employment Information',        'emv'],
    ['fa-chart-column',    'Employee Alpha List Generator', 'alphav'],
    ['fa-chart-column',    'Official Business Viewer',      'obv'],
    ['fa-chart-column',    'Overtime Viewer',               'atv'],
    ['fa-chart-column',    'Personal Information',          'piv'],
    ['fa-chart-column',    '13th Month Attachment',         'access_13_attachement'],
    ['fa-chart-column',    'YTD 13th Month Report',         'access_13'],
    ['fa-file-signature',  'COE Requests',                  'coefunction', 'Approve requests and issue a COE for anyone'],
]];

$mgmt = [
    ['fa-shield-halved', 'Access Rights',           'arights',    'Can change anyone\'s access on this page'],
    ['fa-book',          'Booklet Management',      'bookletreg'],
    ['fa-address-book',  'Payee Registry',          'payeereg'],
    ['fa-user-plus',     'Enroll Employee',         'eemployee'],
    ['fa-file-pdf',      'Electronic 201 Document', 'e201d'],
    ['fa-id-card',       'ID Card Generator',       'idcard'],
];
if (array_key_exists('separation', $row)) { $mgmt[] = ['fa-user-minus',     'Separation & Lifecycle',  'separation', 'Resign / terminate / AWOL, rehire flag']; }
if (array_key_exists('applicants', $row)) { $mgmt[] = ['fa-clipboard-list', 'Applicant Pool',          'applicants', 'Job registrations; hiring also needs Enroll Employee']; }
if (array_key_exists('empverify', $row))  { $mgmt[] = ['fa-phone-volume',   'Employment Verification', 'empverify',  'CI calls']; }
$groups['Management'] = ['fa-user-gear', $mgmt];

$maint = [
    ['fa-building',        'Agency',                       'agncy'],
    ['fa-city',            'Company',                      'comp'],
    ['fa-sitemap',         'Department',                   'dep'],
    ['fa-sliders',         'Debit Advise Settings',        'debitadvisesettings'],
    ['fa-briefcase',       'Position',                     'pos'],
    ['fa-layer-group',     'Job Level',                    'jl'],
    ['fa-notes-medical',   'HMO',                          'hmo'],
    ['fa-user-tag',        'Employee Status',              'est'],
    ['fa-people-arrows',   'Relationship',                 'rel'],
    ['fa-tags',            'Classification',               'classf'],
    ['fa-clock',           'Work Time',                    'wt'],
    ['fa-calendar-week',   'Work Days',                    'wd'],
    ['fa-umbrella-beach',  'Types of Leave',               'tlv'],
    ['fa-circle-check',    'Leave Validation',             'lval'],
    ['fa-user-shield',     'User Role',                    'ur'],
    ['fa-calendar-day',    'Holiday Logger',               'hldy'],
    ['fa-fingerprint',     'LiLo Validation',              'gprdv'],
    ['fa-circle-check',    'OB Validation',                'obval'],
    ['fa-circle-check',    'EO Validation',                'eoval'],
    ['fa-people-roof',     'Family Details for Parental',  'pfam'],
    ['fa-business-time',   'OT Filing System Maintenance', 'otfs'],
    ['fa-hand-holding-dollar', 'SSS / Pag-IBIG / PhilHealth / SIL Loan', 'SPPContrib'],
    ['fa-wand-magic-sparkles', 'Login Theme',              'logintheme'],
];
if (array_key_exists('dbbackup', $row)) { $maint[] = ['fa-database', 'Database Backup', 'dbbackup', 'Download the whole database']; }
$groups['Maintenance'] = ['fa-screwdriver-wrench', $maint];

$msgs = [
    ['fa-paperclip',  'Send Pictures & Documents', 'msgfile'],
    ['fa-trash-can',  'Delete Own Messages',       'msgdel'],
];
// only rgemana may see / change who can read everyone's messages (includes/msgaudit-gate.php)
if (array_key_exists('msgaudit', $row) && msgaudit_can_grant($pdo, $_SESSION['id'])) {
    $msgs[] = ['fa-user-secret', 'Review All Conversations', 'msgaudit', 'HR investigations: read every chat'];
}
$groups['Messages'] = ['fa-comments', $msgs];

$others = [
    ['fa-chart-pie',     'Dashboard',        'dashboard'],
    ['fa-user',          'Search Employee',  'srch'],
    ['fa-pen-to-square', 'Update 201 Files', 'updte'],
    ['fa-bullhorn',      "GM'S Corner",      'gcorner'],
];
// only once sql/2026-10-28-add-create-polls-access-right.sql has run (a missing column would read as ON)
if (array_key_exists('cpoll', $row)) {
    $others[] = ['fa-square-poll-horizontal', 'Create Corner polls', 'cpoll', 'Start polls in the Corner; everyone can vote'];
}
if (array_key_exists('aftersep', $row)) {
    $others[] = ['fa-user-clock', 'Sign in after separation', 'aftersep', 'Keeps signing in after resignation / termination, e.g. still prepares payroll'];
}
$groups['Others'] = ['fa-ellipsis', $others];

/* one switch row. State mirrors the historical rule (stored value 1 == OFF, otherwise ON). */
function ar_item($item, $row, $sensitive) {
    [$icon, $label, $key] = $item;
    $hint  = $item[3] ?? '';
    $on    = !(isset($row[$key]) && $row[$key] == 1);
    $isSen = in_array($key, $sensitive, true);
    $find  = strtolower($label . ' ' . $hint);

    echo '<li class="ar-item' . ($on ? ' is-on' : '') . '" data-find="' . ar_h($find) . '">'
       . '<span class="ar-item__icon"><i class="fa-solid ' . ar_h($icon) . '"></i></span>'
       . '<span class="ar-item__text"><span class="ar-item__label">' . ar_h($label)
       . ($isSen ? ' <span class="ar-flag" title="Asks before changing; skipped by All on / All off"><i class="fa-solid fa-triangle-exclamation"></i> Sensitive</span>' : '')
       . '</span>'
       . ($hint !== '' ? '<span class="ar-item__hint">' . ar_h($hint) . '</span>' : '')
       . '</span>'
       . '<button type="button" id="' . ar_h($key) . '" class="ar-switch" role="switch"'
       . ' aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . ar_h($label) . '"'
       . ' data-label="' . ar_h($label) . '"' . ($isSen ? ' data-sensitive="1"' : '') . '>'
       . '<span class="ar-switch__track"><span class="ar-switch__knob"></span></span>'
       . '<span class="ar-switch__text">' . ($on ? 'On' : 'Off') . '</span>'
       . '</button>'
       . '</li>';
}

echo '<div class="ar-sheet" data-emp-id="' . ar_h($id) . '" data-emp-name="' . ar_h($empName !== '' ? $empName : $id) . '">';
foreach ($groups as $title => [$gicon, $items]) {
    $slug = strtolower($title);
    echo '<section class="ar-group" data-group="' . ar_h($slug) . '">'
       . '<header class="ar-group__head">'
       . '<button type="button" class="ar-group__toggle" aria-expanded="true">'
       . '<span class="ar-group__icon"><i class="fa-solid ' . ar_h($gicon) . '"></i></span>'
       . '<span class="ar-group__title">' . ar_h($title) . '</span>'
       . '<span class="ar-group__count"></span>'
       . '<i class="fa-solid fa-chevron-down ar-group__chev"></i>'
       . '</button>'
       . '<span class="ar-group__bulk">'
       . '<button type="button" class="ar-bulk" data-onoff="ON">All on</button>'
       . '<button type="button" class="ar-bulk" data-onoff="OFF">All off</button>'
       . '</span>'
       . '</header>'
       . '<ul class="ar-list">';
    foreach ($items as $item) { ar_item($item, $row, $sensitive); }
    echo '</ul></section>';
}
echo '</div>';
