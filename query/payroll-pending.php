<?php
/* ==========================================================================
   payroll-pending.php  —  who is blocking payroll generation.
   POST dtfr, dtto (the cut-off dates), returns JSON.

   Query-payroll.php refuses to generate while an EO / OB / Leave / OT filing in
   the cut-off is still pending (status 1 = waiting for the immediate superior,
   2 = waiting for HR) and only answers with a code (10000–10003). This lists
   those filings so HR knows whom to chase. The WHERE clauses below must stay
   the same as the checks in Query-payroll.php, or the list won't match the
   warning. Also lists unapproved payroll dates behind the 10005 warning.

   Gated by the `payroll` access right (==2), like the Payroll menu item.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');

function pp_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    pp_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pp_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    pp_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

try {
    $st = $pdo->prepare("SELECT payroll FROM accessrights WHERE EmpID = :id");
    $st->execute([':id' => $_SESSION['id']]);
    $allowed = (int) $st->fetchColumn() === 2;
} catch (Throwable $e) {
    $allowed = false;
}
if (!$allowed) {
    pp_out(403, ['status' => 'error', 'msg' => 'You do not have access to payroll.']);
}

$dtfr = (string) ($_POST['dtfr'] ?? '');
$dtto = (string) ($_POST['dtto'] ?? '');
foreach ([$dtfr, $dtto] as $d) {
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        pp_out(422, ['status' => 'error', 'msg' => 'Pick the cut-off dates first.']);
    }
}
// Query-payroll.php checks EO / OB / OT up to the day after the cut-off end
$newDDTO = date('Y-m-d', strtotime($dtto . ' + 1 days'));

$who = "e.EmpLN AS ln, e.EmpFN AS fn, s.EmpFN AS sfn, s.EmpLN AS sln";
$hm  = function ($t) { return $t ? date('h:i A', strtotime($t)) : ''; };

$checks = [
    'eo' => ['label' => 'Early out', 'to' => $newDDTO, 'sql' => "
        SELECT x.EMPID AS empid, $who, x.Status AS status, x.DFile AS dfrom, x.DFile AS dto, x.EOfrom, x.Purpose AS purpose
          FROM earlyout x
          LEFT JOIN employees e ON e.EmpID = x.EMPID
          LEFT JOIN employees s ON s.EmpID = x.EmpISID
         WHERE (x.DFile BETWEEN :dateFrom AND :dateTo) AND x.Status IN ('1','2')",
        'detail' => function ($r) { return 'Out at ' . $r['EOfrom']; }],
    'ob' => ['label' => 'OB', 'to' => $newDDTO, 'sql' => "
        SELECT x.EmpID AS empid, $who, x.OBStatus AS status, x.OBDateFrom AS dfrom, x.OBDateTo AS dto,
               x.OBTimeFrom, x.OBTimeTo, x.OBIFrom, x.OBITo, x.OBPurpose AS purpose
          FROM obshbd x
          LEFT JOIN employees e ON e.EmpID = x.EmpID
          LEFT JOIN employees s ON s.EmpID = x.EmpSID
         WHERE (x.OBDateFrom BETWEEN :dateFrom AND :dateTo) AND x.OBStatus IN ('1','2')",
        'detail' => function ($r) { return trim($r['OBTimeFrom'] . '–' . $r['OBTimeTo'] . ' · ' . $r['OBIFrom'] . ' → ' . $r['OBITo']); }],
    'leave' => ['label' => 'Leave', 'to' => $dtto, 'sql' => "
        SELECT x.EmpID AS empid, $who, x.LStatus AS status, x.LStart AS dfrom, x.LEnd AS dto, l.LeaveDesc, x.LPurpose AS purpose
          FROM hleavesbd x
          LEFT JOIN employees e ON e.EmpID = x.EmpID
          LEFT JOIN employees s ON s.EmpID = x.EmpSID
          LEFT JOIN leaves l ON l.LeaveID = x.LType
         WHERE (x.LStart BETWEEN :dateFrom AND :dateTo) AND x.LStatus IN ('1','2') AND x.LType <> 9",
        'detail' => function ($r) { return $r['LeaveDesc'] ?: 'Leave'; }],
    'ot' => ['label' => 'OT', 'to' => $newDDTO, 'sql' => "
        SELECT x.EmpID AS empid, $who, x.Status AS status, DATE(x.TimeIn) AS dfrom, DATE(x.TimeOut) AS dto,
               x.TimeIn, x.TimeOut, x.Duration, x.Purpose AS purpose
          FROM otattendancelog x
          LEFT JOIN employees e ON e.EmpID = x.EmpID
          LEFT JOIN employees s ON s.EmpID = x.EmpISID
         WHERE (x.TimeIn BETWEEN :dateFrom AND :dateTo) AND x.Status IN ('1','2')",
        'detail' => function ($r) use ($hm) { return $hm($r['TimeIn']) . '–' . $hm($r['TimeOut']) . ' (' . $r['Duration'] . ' hr)'; }],
];

$groups = [];
$total  = 0;
try {
    foreach ($checks as $key => $c) {
        $st = $pdo->prepare($c['sql'] . " ORDER BY e.EmpLN, e.EmpFN, dfrom");
        $st->execute([':dateFrom' => $dtfr, ':dateTo' => $c['to']]);
        $items = [];
        foreach ($st->fetchAll() as $r) {
            $waitingHr = (string) $r['status'] === '2';
            $name = trim(trim((string) $r['ln']) . ', ' . trim((string) $r['fn']), ', ');
            $items[] = [
                'empid'    => (string) $r['empid'],
                'name'     => $name !== '' ? $name : (string) $r['empid'],
                'date'     => ($r['dto'] === null || $r['dfrom'] === $r['dto']) ? (string) $r['dfrom'] : $r['dfrom'] . ' to ' . $r['dto'],
                'detail'   => (string) $c['detail']($r),
                'purpose'  => (string) $r['purpose'],
                'waiting'  => $waitingHr ? 'HR' : 'Immediate superior',
                'approver' => $waitingHr ? '' : trim($r['sfn'] . ' ' . $r['sln']),
            ];
        }
        $total += count($items);
        $groups[] = ['key' => $key, 'label' => $c['label'], 'items' => $items];
    }

    // 10005: another payroll date generated but not yet approved
    $st = $pdo->prepare("SELECT DISTINCT PYDate FROM payrol WHERE hrApproved = 0 ORDER BY PYDate");
    $st->execute();
    $unapproved = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    pp_out(500, ['status' => 'error', 'msg' => 'Could not read the pending filings.']);
}

pp_out(200, ['status' => 'ok', 'total' => $total, 'groups' => $groups, 'unapproved_payroll' => $unapproved]);
