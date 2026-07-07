<?php
/* ==========================================================================
   searchaccessrights.php  —  AJAX partial for accessrights.php
   Returns the module/report/management/maintenance access rows for one
   employee (?q=<EmpID>) as <tr> markup injected into #accr.
   Buttons keep their exact "ON"/"OFF" text + id (= access column) + the
   #acchange modal trigger, because accessrights.php's JS relies on them.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
include 'w_conn.php';

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
    echo '<tr><td colspan="2" style="text-align:center;color:var(--text-3)">No access-rights record for this employee.</td></tr>';
    return;
}

/* one access row: icon + label + ON/OFF toggle.
   State mirrors the historical rule (stored value 1 == OFF, otherwise ON). */
function ar_row($icon, $label, $key, $row) {
    $isOff = isset($row[$key]) && $row[$key] == 1;
    $state = $isOff ? 'off' : 'on';
    $text  = $isOff ? 'OFF' : 'ON';
    echo '<tr>'
       . '<td><i class="fa-solid ' . $icon . '"></i> <span>' . $label . '</span></td>'
       . '<td><button type="button" id="' . htmlspecialchars($key) . '"'
       . ' class="ar-toggle ar-toggle--' . $state . '"'
       . ' data-toggle="modal" data-target="#acchange">' . $text . '</button></td>'
       . '</tr>';
}

/* section divider row */
function ar_group($title) {
    echo '<tr class="ar-grouprow"><td colspan="2">' . htmlspecialchars($title) . '</td></tr>';
}

/* ---- Modules ---- */
ar_group('Modules');
ar_row('fa-folder',          'Electronic 201 File',                'e201',          $row);
ar_row('fa-calendar-check',  'Leave Credit Overview',              'lcreaditview',  $row);
ar_row('fa-calendar-check',  'Automated Leave Application System', 'alas',          $row);
ar_row('fa-file',            'Check Register',                     'checkregister', $row);
ar_row('fa-file-lines',      'Memo Generator',                     'memo',          $row);
ar_row('fa-clock',           'Payroll Management System',          'payroll',       $row);
ar_row('fa-money-bill-wave', 'Payslip',                            'payslipt',      $row);
ar_row('fa-file-invoice-dollar', 'Debit Advise Module',            'debitadvise',   $row);
ar_row('fa-box-archive',     'Archived Management System',         'ams',           $row);
ar_row('fa-calendar-days',   'Employee Scheduling',                'EF',            $row);
ar_row('fa-clock',           'Overtime OB System',                 'otob',          $row);
ar_row('fa-briefcase',       'Official Business Trip Tracker',     'ob',            $row);
ar_row('fa-plane',           'Send to OB Trip',                    'sob',           $row);
ar_row('fa-business-time',   'Overtime Filing System',             'ot',            $row);
ar_row('fa-calendar-minus',  'Early Out System',                   'eo',            $row);
ar_row('fa-clipboard',       'New Blog',                           'nwblogs',       $row);

/* ---- Reports ---- */
ar_group('Reports');
ar_row('fa-chart-column', 'ALAS Viewer',                    'alasv',                 $row);
ar_row('fa-chart-column', 'Attendance Viewer',              'lilov',                 $row);
ar_row('fa-chart-column', 'Compliance Document Data',       'cddv',                  $row);
ar_row('fa-chart-column', 'Daily Activity Viewer',          'darv',                  $row);
ar_row('fa-chart-column', 'Early Out Viewer',               'eov',                   $row);
ar_row('fa-chart-column', 'Employment Information',          'emv',                  $row);
ar_row('fa-chart-column', 'Employee Alpha List Generator',  'alphav',                $row);
ar_row('fa-chart-column', 'Official Business Viewer',       'obv',                   $row);
ar_row('fa-chart-column', 'Overtime Viewer',                'atv',                   $row);
ar_row('fa-chart-column', 'Personal Information',           'piv',                   $row);
ar_row('fa-chart-column', '13th Month Attachment',          'access_13_attachement', $row);
ar_row('fa-chart-column', 'YTD 13th Month Report',          'access_13',             $row);

/* ---- Management ---- */
ar_group('Management');
ar_row('fa-lock',      'Access Rights',            'arights',    $row);
ar_row('fa-lock',      'Booklet Management',       'bookletreg', $row);
ar_row('fa-lock',      'Payee Registry',           'payeereg',   $row);
ar_row('fa-user-plus', 'Enroll Employee',          'eemployee',  $row);
ar_row('fa-file-pdf',  'Electronic 201 Document',  'e201d',      $row);

/* ---- Maintenance ---- */
ar_group('Maintenance');
ar_row('fa-square-plus', 'Agency',                        'agncy',               $row);
ar_row('fa-square-plus', 'Company',                       'comp',                $row);
ar_row('fa-square-plus', 'Department',                    'dep',                 $row);
ar_row('fa-square-plus', 'Debit Advise Settings',         'debitadvisesettings', $row);
ar_row('fa-square-plus', 'Position',                      'pos',                 $row);
ar_row('fa-square-plus', 'Job Level',                     'jl',                  $row);
ar_row('fa-square-plus', 'HMO',                           'hmo',                 $row);
ar_row('fa-square-plus', 'Employee Status',               'est',                 $row);
ar_row('fa-square-plus', 'Relationship',                  'rel',                 $row);
ar_row('fa-square-plus', 'Classification',                'classf',              $row);
ar_row('fa-square-plus', 'Work Time',                     'wt',                  $row);
ar_row('fa-square-plus', 'Work Days',                     'wd',                  $row);
ar_row('fa-square-plus', 'Types of Leave',               'tlv',                 $row);
ar_row('fa-square-plus', 'Leave Validation',              'lval',                $row);
ar_row('fa-square-plus', 'User Role',                     'ur',                  $row);
ar_row('fa-square-plus', 'Holiday Logger',                'hldy',                $row);
ar_row('fa-square-plus', 'LiLo Validation',               'gprdv',               $row);
ar_row('fa-square-plus', 'OB Validation',                 'obval',               $row);
ar_row('fa-square-plus', 'EO Validation',                 'eoval',               $row);
ar_row('fa-square-plus', 'OT Filing System Maintenance',  'otfs',                $row);

/* ---- Others ---- */
ar_group('Others');
ar_row('fa-chart-pie',    'Dashboard',         'dashboard', $row);
ar_row('fa-user',         'Search Employee',   'srch',    $row);
ar_row('fa-pen-to-square','Update 201 Files',  'updte',   $row);
ar_row('fa-bullhorn',     "GM'S Corner",       'gcorner', $row);
