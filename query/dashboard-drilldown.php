<?php
/* ==========================================================================
   dashboard-drilldown.php  —  detail behind the dashboard's
   "Tardiness & absenteeism by department" panel.

   Given a department + reporting period, returns (as an HTML partial for the
   drill-down modal) the individual LATE time-ins (who / date / minutes late)
   and the individual ABSENCES (who / date) for that department.

   Mirrors dashboard.php exactly: scope from session role, employed-during-window
   resignation filter, and the schedule-aware absence accounting (any ALAS/OB with
   status <> 7, i.e. not cancelled, excuses the day — same as the login gate).
   Scope + access are derived from the
   SESSION, never from the client, so a team lead can't drill outside their team.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    http_response_code(403); echo '<div class="dd-none">Your session has expired &mdash; please sign in again.</div>'; exit;
}
include 'w_conn.php';
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500); echo '<div class="dd-none">Database unavailable.</div>'; exit;
}

/* access gate: the `dashboard` right must be ON (==2), same as dashboard.php */
try {
    $g = $pdo->prepare("SELECT dashboard FROM accessrights WHERE EmpID = :id");
    $g->execute([':id' => $_SESSION['id']]);
    if ((int) $g->fetchColumn() !== 2) { http_response_code(403); echo '<div class="dd-none">Not authorized.</div>'; exit; }
} catch (Exception $e) { http_response_code(500); echo '<div class="dd-none">Error.</div>'; exit; }

$uid   = $_SESSION['id'];
$utype = (int) ($_SESSION['UserType'] ?? 3);
$scope = ($utype === 2) ? 'team' : 'org';   // mirrors dashboard.php

$dept = isset($_GET['dept']) ? (string) $_GET['dept'] : '';
$vd = function ($s, $def) { $t = strtotime((string) $s); return $t ? date('Y-m-d', $t) : $def; };
$pt = $vd($_GET['pt'] ?? '', date('Y-m-d'));
$pf = $vd($_GET['pf'] ?? '', date('Y-01-01', strtotime($pt)));
if ($pf > $pt) { $t = $pf; $pf = $pt; $pt = $t; }
if (strtotime($pt) - strtotime($pf) > 366 * 86400) { $pf = date('Y-m-d', strtotime($pt . ' -366 days')); }
/* ==========================================================================
   KPI / tile drill-downs (?kind=...) — the people behind each dashboard number.
   Same session-derived scope + access gate as the department drill-down above.
   `ad` = the attendance date the dashboard displayed (today, or the latest
   attendance day when today has no clock-ins); validated, never trusted for scope.
   ========================================================================== */
$kind = isset($_GET['kind']) ? preg_replace('/[^a-z]/', '', (string) $_GET['kind']) : '';
if ($kind !== '') {
    $hh  = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES); };
    $fmt = function ($d) { return $d ? date('M j', strtotime($d)) : ''; };
    $nm  = function ($ln, $fn) { return trim($ln . ', ' . $fn, ', '); };
    $ad  = $vd($_GET['ad'] ?? '', date('Y-m-d'));
    $scopeWhere = "e.EmpStatusID = 1" . ($scope === 'team' ? " AND d.EmpISID = :uid" : "");   // mirrors $scopeJoinWhere
    $attScope   = ($scope === 'team') ? "d.EmpISID = :uid" : "1=1";                          // mirrors $attScope
    $bind = function (array $p) use ($scope, $uid) { if ($scope === 'team') { $p[':uid'] = $uid; } return $p; };

    /* employees SCHEDULED on $ad — identical to dashboard.php's $scheduledEmp query */
    $scheduled = function () use ($pdo, $ad, $attScope, $bind) {
        $st = $pdo->prepare(
            "SELECT DISTINCT e.EmpID, e.EmpLN, e.EmpFN, p.PositionDesc, COALESCE(dp.DepartmentDesc,'Unassigned') dept
             FROM employees e
             JOIN empdetails d ON e.EmpID=d.EmpID
             JOIN workdays wd ON wd.empid=e.EmpID AND wd.Day_s=DAYNAME(:ad1)
             JOIN workschedule ws ON wd.SchedTime=ws.WorkSchedID AND ws.WorkSchedID<>0
             JOIN schedeffectivity se ON wd.EFID=se.efids AND :ad2 BETWEEN se.dfrom AND se.dto
             LEFT JOIN positions p ON e.PosID=p.PSID
             LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
             LEFT JOIN holidays h ON h.Hdate=:ad3 AND h.HCompID=d.EmpCompID
             WHERE h.SID IS NULL AND d.EmpRoleID <> 1 AND COALESCE(p.PositionDesc,'') NOT LIKE '%Manager%' AND $attScope
             ORDER BY e.EmpLN, e.EmpFN");
        $st->execute($bind([':ad1'=>$ad, ':ad2'=>$ad, ':ad3'=>$ad]));
        $out = []; while ($r = $st->fetch(PDO::FETCH_ASSOC)) { $out[$r['EmpID']] = $r; } return $out;
    };
    $col = function ($sql, array $p = []) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($p); return array_flip($st->fetchAll(PDO::FETCH_COLUMN)); };

    /* render helpers — a column name starting with '#' is right-aligned */
    $head  = function ($title, $sub) use ($hh) { return '<div class="dd-head">' . $hh($title) . ' <span class="dd-period">&middot; ' . $hh($sub) . '</span></div>'; };
    $table = function (array $cols, array $rows, $empty) use ($hh) {
        if (!$rows) { return '<div class="dd-none">' . $hh($empty) . '</div>'; }
        $h = '<table class="dd-tbl"><thead><tr>';
        foreach ($cols as $c) { $h .= '<th' . (substr($c, 0, 1) === '#' ? ' class="num"' : '') . '>' . $hh(ltrim($c, '#')) . '</th>'; }
        $h .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr>';
            foreach ($r as $i => $cell) {   // cells are pre-escaped by the caller
                $num = substr($cols[$i] ?? '', 0, 1) === '#';
                $h .= '<td class="' . ($i === 0 ? 'dd-emp' : '') . ($num ? ' num' : '') . '">' . $cell . '</td>';
            }
            $h .= '</tr>';
        }
        return $h . '</tbody></table>';
    };
    $pill = function ($cls, $txt) use ($hh) { return '<span class="wd-pill wd-pill--' . $cls . '">' . $hh($txt) . '</span>'; };
    $adLbl = date('M j, Y', strtotime($ad));

    try {
        switch ($kind) {

        /* ---- Active employees / Direct reports ---- */
        case 'active':
            $st = $pdo->prepare(
                "SELECT e.EmpLN, e.EmpFN, p.PositionDesc, COALESCE(dp.DepartmentDesc,'Unassigned') dept, d.EmpDateHired
                 FROM employees e JOIN empdetails d ON e.EmpID=d.EmpID
                 LEFT JOIN positions p ON e.PosID=p.PSID LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
                 WHERE $scopeWhere ORDER BY dept, e.EmpLN, e.EmpFN");
            $st->execute($bind([]));
            $rows = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [$hh($nm($r['EmpLN'], $r['EmpFN'])), $hh($r['PositionDesc'] ?: '—'), $hh($r['dept']),
                           $hh($r['EmpDateHired'] && $r['EmpDateHired'] !== '0000-00-00' ? date('M j, Y', strtotime($r['EmpDateHired'])) : '—')];
            }
            echo $head($scope === 'team' ? 'Direct reports' : 'Active employees', count($rows) . ' people');
            echo $table(['Employee', 'Position', 'Department', 'Date hired'], $rows, 'No active employees in scope.');
            break;

        /* ---- Present on $ad (scheduled + clocked in), with time-in and lateness ---- */
        case 'present':
            $sch = $scheduled();
            $st = $pdo->prepare("SELECT EmpID, MIN(TimeIn) ti, MAX(MinsLack) ml FROM attendancelog WHERE WSFrom=:ad GROUP BY EmpID");
            $st->execute([':ad' => $ad]);
            $att = []; while ($r = $st->fetch(PDO::FETCH_ASSOC)) { $att[$r['EmpID']] = $r; }
            $rows = []; $late = 0;
            foreach ($sch as $eid => $r) {
                if (!isset($att[$eid])) { continue; }
                $ml = (int) round($att[$eid]['ml']); if ($ml > 0) { $late++; }
                $rows[] = [$hh($nm($r['EmpLN'], $r['EmpFN'])), $hh($r['PositionDesc'] ?: $r['dept']),
                           $hh($att[$eid]['ti'] ? date('g:i A', strtotime($att[$eid]['ti'])) : '—'),
                           $ml > 0 ? $pill('warn', $ml . ' min late') : $pill('ok', 'On time')];
            }
            echo $head('Present', $adLbl . ' · ' . count($rows) . ' of ' . count($sch) . ' scheduled' . ($late ? " · $late late" : ''));
            echo $table(['Employee', 'Position', '#Time in', '#Status'], $rows, 'No one scheduled has clocked in on ' . $adLbl . '.');
            break;

        /* ---- On leave / OB on $ad (scheduled, not clocked in, filing on file) ---- */
        case 'leave':
            $sch = $scheduled();
            $present = $col("SELECT DISTINCT EmpID FROM attendancelog WHERE WSFrom=:ad", [':ad' => $ad]);
            $st = $pdo->prepare(
                "SELECT 'Leave' typ, lv.EmpID, COALESCE(l.LeaveDesc,'Leave') what, lv.LStart f, lv.LEnd t, lv.LStatus stt
                 FROM hleavesbd lv LEFT JOIN leaves l ON lv.LType=l.LeaveID
                 WHERE :ad1 BETWEEN lv.LStart AND lv.LEnd AND lv.LStatus <> 7
                 UNION ALL
                 SELECT 'OB', ob.EmpID, COALESCE(ob.OBPurpose,'Official business'), ob.OBDateFrom, ob.OBDateTo, ob.OBStatus
                 FROM obshbd ob WHERE :ad2 BETWEEN ob.OBDateFrom AND ob.OBDateTo AND ob.OBStatus <> 7");
            $st->execute([':ad1' => $ad, ':ad2' => $ad]);
            // leave filings take precedence over OB for the same person (dashboard partition
            // order: present → leave → OB); ?mod=leave restricts to leave only (KPI tile).
            $onlyLeave = (($_GET['mod'] ?? '') === 'leave');
            $fil = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                if ($onlyLeave && $r['typ'] !== 'Leave') { continue; }
                $fil[$r['EmpID']] = $fil[$r['EmpID']] ?? [];
                if ($r['typ'] === 'Leave') { array_unshift($fil[$r['EmpID']], $r); } else { $fil[$r['EmpID']][] = $r; }
            }
            $stMap = [1=>'Pending', 2=>'Approved by IS', 3=>'Disapproved by IS', 4=>'Approved by HR', 5=>'Disapproved by HR', 6=>'Disapproved', 8=>'Approved w/o pay', 9=>'Processed', 10=>'Released'];
            $rows = [];
            foreach ($sch as $eid => $r) {
                if (isset($present[$eid]) || empty($fil[$eid])) { continue; }
                $f = $fil[$eid][0];   // leave first (UNION order), then OB — matches the dashboard partition
                $sc = (int) $f['stt']; $cls = in_array($sc, [3,5,6]) ? 'danger' : ($sc === 1 ? 'warn' : ($sc === 2 ? 'info' : 'ok'));
                $rows[] = [$hh($nm($r['EmpLN'], $r['EmpFN'])), $hh($f['typ']) . ' <span class="dpat-sub">· ' . $hh(mb_strimwidth($f['what'], 0, 40, '…')) . '</span>',
                           $hh($fmt($f['f']) . ($f['t'] !== $f['f'] ? ' – ' . $fmt($f['t']) : '')), $pill($cls, $stMap[$sc] ?? ('Status ' . $sc))];
            }
            echo $head($onlyLeave ? 'On leave' : 'On leave / OB', $adLbl . ' · ' . count($rows) . ' people');
            echo $table(['Employee', 'Filing', 'Dates', '#Status'], $rows, 'No one scheduled is on ' . ($onlyLeave ? 'leave' : 'leave or OB') . ' on ' . $adLbl . '.');
            break;

        /* ---- Scheduled but not clocked in and no filing on $ad ---- */
        case 'notin':
            $sch = $scheduled();
            $present = $col("SELECT DISTINCT EmpID FROM attendancelog WHERE WSFrom=:ad", [':ad' => $ad]);
            $lv = $col("SELECT DISTINCT EmpID FROM hleavesbd WHERE :ad BETWEEN LStart AND LEnd AND LStatus <> 7", [':ad' => $ad]);
            $ob = $col("SELECT DISTINCT EmpID FROM obshbd WHERE :ad BETWEEN OBDateFrom AND OBDateTo AND OBStatus <> 7", [':ad' => $ad]);
            $rows = [];
            foreach ($sch as $eid => $r) {
                if (isset($present[$eid]) || isset($lv[$eid]) || isset($ob[$eid])) { continue; }
                $rows[] = [$hh($nm($r['EmpLN'], $r['EmpFN'])), $hh($r['PositionDesc'] ?: '—'), $hh($r['dept']), $pill('danger', 'No time-in')];
            }
            echo $head('Not clocked in', $adLbl . ' · ' . count($rows) . ' of ' . count($sch) . ' scheduled');
            echo $table(['Employee', 'Position', 'Department', '#Status'], $rows, 'Everyone scheduled on ' . $adLbl . ' is present or accounted for.');
            break;

        /* ---- Pending approvals (all four modules, rolling 14-day window, no LIMIT) ---- */
        case 'pending':
            $ptbl = [
              'hl' => ['t'=>'hleaves',         'st'=>'LStatus',  'sup'=>'EmpSID',  'fd'=>'LFDate',          'label'=>'Leave',     'what'=>"COALESCE(l.LeaveDesc,'Leave')", 'xj'=>' LEFT JOIN leaves l ON a.LType=l.LeaveID', 'from'=>'a.LStart',     'to'=>'a.LEnd'],
              'ob' => ['t'=>'obs',             'st'=>'OBStatus', 'sup'=>'EmpSID',  'fd'=>'OBInputDate',     'label'=>'OB',        'what'=>"COALESCE(a.OBPurpose,'')",       'xj'=>'', 'from'=>'a.OBDateFrom', 'to'=>'a.OBDateTo'],
              'eo' => ['t'=>'earlyout',        'st'=>'Status',   'sup'=>'EmpISID', 'fd'=>'DateTimeInputed', 'label'=>'Early-out', 'what'=>"COALESCE(a.Purpose,'')",         'xj'=>'', 'from'=>'a.DFile',      'to'=>'a.DFile'],
              'ot' => ['t'=>'otattendancelog', 'st'=>'Status',   'sup'=>'EmpISID', 'fd'=>'DateFiling',      'label'=>'Overtime',  'what'=>"COALESCE(a.Purpose,'')",         'xj'=>'', 'from'=>'DATE(a.TimeIn)', 'to'=>'DATE(a.TimeOut)'],
            ];
            $mod = isset($_GET['mod']) && isset($ptbl[$_GET['mod']]) ? $_GET['mod'] : '';
            $cutoff = date('Y-m-d', strtotime('-14 days'));
            $parts = []; $pp = [];
            foreach ($ptbl as $k => $m) {
                if ($mod !== '' && $k !== $mod) { continue; }
                if ($scope === 'team') { $w = "a.{$m['sup']}=:u_$k AND a.{$m['st']}=1"; $pp[":u_$k"] = $uid; }
                else                   { $w = "a.{$m['st']} IN (1,2)"; }
                $w .= " AND DATE(a.{$m['fd']}) >= :c_$k"; $pp[":c_$k"] = $cutoff;
                $parts[] = "SELECT '{$m['label']}' typ, CONCAT(b.EmpLN,', ',b.EmpFN) emp, {$m['what']} what, {$m['from']} f, {$m['to']} t, a.{$m['fd']} filed, a.{$m['st']} stt
                            FROM {$m['t']} a JOIN employees b ON a.EmpID=b.EmpID{$m['xj']} WHERE $w";
            }
            $rows = [];
            if ($parts) {
                $st = $pdo->prepare(implode(' UNION ALL ', $parts) . ' ORDER BY filed ASC');
                $st->execute($pp);
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $stt = (int) $r['stt'];
                    $rows[] = [$hh(trim($r['emp'], ', ')),
                               $hh($r['typ']) . ($r['what'] !== '' ? ' <span class="dpat-sub">· ' . $hh(mb_strimwidth($r['what'], 0, 40, '…')) . '</span>' : ''),
                               $hh($r['f'] ? $fmt($r['f']) . ($r['t'] && $r['t'] !== $r['f'] ? ' – ' . $fmt($r['t']) : '') : '—'),
                               $hh($r['filed'] ? date('M j', strtotime($r['filed'])) : '—'),
                               $stt === 1 ? $pill('warn', 'Awaiting superior') : $pill('info', 'Awaiting HR')];
                }
            }
            $ttl = $mod !== '' ? 'Pending ' . strtolower($ptbl[$mod]['label']) . ' approvals' : 'Pending approvals';
            echo $head($ttl, 'filed in the last 14 days · ' . count($rows) . ' pending');
            echo $table(['Employee', 'Request', 'For', 'Filed', '#Status'], $rows, 'Nothing awaiting action.');
            break;

        /* ---- Workforce: everyone on file (org only) ---- */
        case 'onfile':
            if ($scope !== 'org') { echo '<div class="dd-none">Not available for team scope.</div>'; break; }
            $st = $pdo->query(
                "SELECT e.EmpLN, e.EmpFN, e.EmpStatusID, p.PositionDesc, COALESCE(dp.DepartmentDesc,'Unassigned') dept
                 FROM employees e LEFT JOIN positions p ON e.PosID=p.PSID LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
                 ORDER BY e.EmpStatusID, e.EmpLN, e.EmpFN");
            $rows = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [$hh($nm($r['EmpLN'], $r['EmpFN'])), $hh($r['PositionDesc'] ?: '—'), $hh($r['dept']),
                           (int) $r['EmpStatusID'] === 1 ? $pill('ok', 'Active') : $pill('danger', 'Resigned/Inactive')];
            }
            echo $head('Employees on file', count($rows) . ' records');
            echo $table(['Employee', 'Position', 'Department', '#Status'], $rows, 'No employee records.');
            break;

        /* ---- Workforce: resigned / inactive (org only) ---- */
        case 'inactive':
            if ($scope !== 'org') { echo '<div class="dd-none">Not available for team scope.</div>'; break; }
            $st = $pdo->query(
                "SELECT e.EmpLN, e.EmpFN, p.PositionDesc, COALESCE(dp.DepartmentDesc,'Unassigned') dept, d.EmpDateResigned
                 FROM employees e LEFT JOIN empdetails d ON e.EmpID=d.EmpID
                 LEFT JOIN positions p ON e.PosID=p.PSID LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
                 WHERE e.EmpStatusID <> 1 ORDER BY d.EmpDateResigned DESC, e.EmpLN, e.EmpFN");
            $rows = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $rd = ($r['EmpDateResigned'] && $r['EmpDateResigned'] !== '0000-00-00') ? date('M j, Y', strtotime($r['EmpDateResigned'])) : '—';
                $rows[] = [$hh($nm($r['EmpLN'], $r['EmpFN'])), $hh($r['PositionDesc'] ?: '—'), $hh($r['dept']), $hh($rd)];
            }
            echo $head('Resigned / inactive', count($rows) . ' people');
            echo $table(['Employee', 'Position', 'Department', 'Resigned'], $rows, 'No resigned or inactive employees.');
            break;

        /* ---- Workforce: left within the reporting period ---- */
        case 'left':
            $st = $pdo->prepare(
                "SELECT e.EmpLN, e.EmpFN, p.PositionDesc, COALESCE(dp.DepartmentDesc,'Unassigned') dept, d.EmpDateResigned
                 FROM employees e JOIN empdetails d ON e.EmpID=d.EmpID
                 LEFT JOIN positions p ON e.PosID=p.PSID LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
                 WHERE d.EmpDateResigned IS NOT NULL AND d.EmpDateResigned <> '' AND d.EmpDateResigned <> '0000-00-00'
                   AND d.EmpDateResigned BETWEEN :rd AND :rt" . ($scope === 'team' ? " AND d.EmpISID=:uid" : "") . "
                 ORDER BY d.EmpDateResigned DESC, e.EmpLN, e.EmpFN");
            $st->execute($bind([':rd' => $pf, ':rt' => $pt]));
            $rows = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [$hh($nm($r['EmpLN'], $r['EmpFN'])), $hh($r['PositionDesc'] ?: '—'), $hh($r['dept']), $hh(date('M j, Y', strtotime($r['EmpDateResigned'])))];
            }
            echo $head('Left in period', $fmt($pf) . ' – ' . date('M j, Y', strtotime($pt)) . ' · ' . count($rows) . ' people');
            echo $table(['Employee', 'Position', 'Department', 'Resigned'], $rows, 'No resignations in this period.');
            break;

        default:
            echo '<div class="dd-none">Unknown detail.</div>';
        }
    } catch (Exception $e) {
        echo '<div class="dd-none">Could not load details.</div>';
    }
    exit;
}

if ($dept === '') { echo '<div class="dd-none">No department specified.</div>'; exit; }

$scopeAnd  = ($scope === 'team') ? " AND d.EmpISID = :uid" : "";
// Match dashboard.php: current workforce only — not resigned (active flag
// e.EmpStatusID=1, not the resignation date) and not OJT (EmpStatID 4).
// Also exempt from tardiness/absence: HR/admin (EmpRoleID=1) and every manager
// (any "*Manager" title — General/Center/Project/Admin and Finance Manager,
// matched by NOT LIKE '%Manager%'), who must not add to the department totals.
$resignAnd = " AND e.EmpStatusID = 1 AND d.EmpStatID <> 4 AND d.EmpRoleID <> 1"
           . " AND COALESCE(p.PositionDesc,'') NOT LIKE '%Manager%'";

// Gemana (WeDoinc-0010) works a fixed 7 AM–7 PM schedule but is TARDY ONLY from
// 8 AM onward. His tardiness is recomputed from an 08:00 baseline off the actual
// TimeIn (see the dedicated block below), independent of the stored MinsLack —
// which for older rows reflects a previous 7 AM schedule. Rule is Gemana-only.
$FLEXI = 'WeDoinc-0010';

/* ---- late time-ins: one row per tardy clock-in ---- */
$tard = [];
try {
    $st = $pdo->prepare(
        "SELECT e.EmpLN, e.EmpFN, a.WSFrom d, a.MinsLack m
         FROM attendancelog a
         JOIN employees e ON a.EmpID = e.EmpID
         JOIN empdetails d ON e.EmpID = d.EmpID
         LEFT JOIN positions p ON e.PosID = p.PSID
         LEFT JOIN departments dp ON p.DepartmentID = dp.DepartmentID
         WHERE a.WSFrom BETWEEN :pf AND :pt AND a.MinsLack > 0
           AND a.EmpID <> :flexi
           AND COALESCE(dp.DepartmentDesc,'Unassigned') = :dept$scopeAnd$resignAnd
         ORDER BY e.EmpLN, e.EmpFN, a.WSFrom");
    $pr = [':pf'=>$pf, ':pt'=>$pt, ':dept'=>$dept, ':flexi'=>$FLEXI]; if ($scope==='team') { $pr[':uid']=$uid; }
    $st->execute($pr);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $nm = trim($r['EmpLN'] . ', ' . $r['EmpFN'], ', ');
        $tard[$nm][] = ['d' => $r['d'], 'm' => (int) round($r['m'])];
    }
} catch (Exception $e) {}

/* ---- Gemana (WeDoinc-0010) flexi tardiness — SPECIAL CASE ----
   Schedule is 7 AM–7 PM but he is tardy only from 8 AM onward. Recompute his
   late rows from an 08:00 baseline off the actual TimeIn (ignores stored
   MinsLack). Same $tard[name][] shape as above; excluded from the query above
   so he is never double-counted. */
try {
    $gst = $pdo->prepare(
        "SELECT e.EmpLN, e.EmpFN, a.WSFrom d,
                TIMESTAMPDIFF(SECOND, CONCAT(a.WSFrom,' 08:00:00'), a.TimeIn)/60 m
         FROM attendancelog a
         JOIN employees e ON a.EmpID = e.EmpID
         JOIN empdetails d ON e.EmpID = d.EmpID
         LEFT JOIN positions p ON e.PosID = p.PSID
         LEFT JOIN departments dp ON p.DepartmentID = dp.DepartmentID
         WHERE a.EmpID = :flexi AND a.WSFrom BETWEEN :pf AND :pt
           AND TIMESTAMPDIFF(SECOND, CONCAT(a.WSFrom,' 08:00:00'), a.TimeIn) > 0
           AND COALESCE(dp.DepartmentDesc,'Unassigned') = :dept$scopeAnd$resignAnd
         ORDER BY a.WSFrom");
    $pr = [':pf'=>$pf, ':pt'=>$pt, ':dept'=>$dept, ':flexi'=>$FLEXI]; if ($scope==='team') { $pr[':uid']=$uid; }
    $gst->execute($pr);
    while ($r = $gst->fetch(PDO::FETCH_ASSOC)) {
        $nm = trim($r['EmpLN'] . ', ' . $r['EmpFN'], ', ');
        $tard[$nm][] = ['d' => $r['d'], 'm' => (int) round($r['m'])];
    }
} catch (Exception $e) {}

/* ---- absences: one row per scheduled day with no attendance/leave/OB ----
   Same shape as dashboard.php: per-employee (weekday, effectivity range) rows
   first, pruned to effectivities overlapping the window, then STRAIGHT_JOIN the
   date series in and test attendance/leave/OB with NOT EXISTS. ---- */
$abs = [];
try {
    $st = $pdo->prepare(
        "WITH RECURSIVE dts AS (
             SELECT DATE(:pf1) dt, DAYNAME(DATE(:pf3)) dn
             UNION ALL SELECT dt + INTERVAL 1 DAY, DAYNAME(dt + INTERVAL 1 DAY) FROM dts WHERE dt < :pt1
         ),
         att AS (SELECT DISTINCT EmpID FROM attendancelog WHERE WSFrom BETWEEN :pf2 AND :pt2),
         rng AS (
             SELECT DISTINCT e.EmpID, e.EmpLN, e.EmpFN, d.EmpCompID, wd.Day_s, se.dfrom, se.dto
             FROM att
             JOIN employees e ON e.EmpID = att.EmpID
             JOIN empdetails d ON e.EmpID = d.EmpID
             LEFT JOIN positions p ON e.PosID = p.PSID
             LEFT JOIN departments dp ON p.DepartmentID = dp.DepartmentID
             JOIN workdays wd ON wd.empid = e.EmpID AND wd.SchedTime <> 0
             JOIN schedeffectivity se ON se.efids = CAST(wd.EFID AS UNSIGNED)
                                     AND se.dto >= :pf4 AND se.dfrom <= :pt4
             WHERE COALESCE(dp.DepartmentDesc,'Unassigned') = :dept$scopeAnd$resignAnd
         ),
         sched AS (
             SELECT DISTINCT r.EmpID, r.EmpLN, r.EmpFN, dts.dt
             FROM rng r STRAIGHT_JOIN dts ON dts.dn = r.Day_s AND dts.dt BETWEEN r.dfrom AND r.dto
             WHERE NOT EXISTS (SELECT 1 FROM holidays h WHERE h.Hdate = dts.dt AND h.HCompID = r.EmpCompID)
         )
         SELECT s.EmpLN, s.EmpFN, s.dt d
         FROM sched s
         WHERE NOT EXISTS (SELECT 1 FROM attendancelog a WHERE a.EmpID = s.EmpID AND a.WSFrom = s.dt)
           AND NOT EXISTS (SELECT 1 FROM hleavesbd lv WHERE lv.EmpID = s.EmpID AND s.dt BETWEEN lv.LStart AND lv.LEnd AND lv.LStatus <> 7)
           AND NOT EXISTS (SELECT 1 FROM obshbd ob WHERE ob.EmpID = s.EmpID AND s.dt BETWEEN ob.OBDateFrom AND ob.OBDateTo AND ob.OBStatus <> 7)
         ORDER BY s.EmpLN, s.EmpFN, s.dt");
    $pr = [':pf1'=>$pf, ':pt1'=>$pt, ':pf2'=>$pf, ':pt2'=>$pt, ':pf3'=>$pf, ':pf4'=>$pf, ':pt4'=>$pt, ':dept'=>$dept];
    if ($scope==='team') { $pr[':uid']=$uid; }
    $st->execute($pr);
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $nm = trim($r['EmpLN'] . ', ' . $r['EmpFN'], ', ');
        $abs[$nm][] = $r['d'];
    }
} catch (Exception $e) {}

$fmt = function ($d) { return date('M j', strtotime($d)); };
$hh  = function ($s) { return htmlspecialchars($s, ENT_QUOTES); };
$tardTotal = array_sum(array_map('count', $tard));
$absTotal  = array_sum(array_map('count', $abs));
?>
<div class="dd-head"><?php echo $hh($dept); ?> <span class="dd-period">&middot; <?php echo $fmt($pf) . ' &ndash; ' . date('M j, Y', strtotime($pt)); ?></span></div>

<div class="dd-sec">
  <div class="dd-sec__h"><i class="fa-solid fa-clock"></i> Late time-ins <span class="dd-count"><?php echo (int)$tardTotal; ?></span></div>
  <?php if ($tard): ?>
    <table class="dd-tbl">
      <thead><tr><th>Employee</th><th class="num">Late</th><th class="num">Avg</th><th>Dates &middot; minutes late</th></tr></thead>
      <tbody>
        <?php foreach ($tard as $nm => $rows): $cnt = count($rows); $avg = $cnt ? round(array_sum(array_column($rows, 'm')) / $cnt) : 0; ?>
          <tr>
            <td class="dd-emp"><?php echo $hh($nm); ?></td>
            <td class="num"><?php echo (int)$cnt; ?></td>
            <td class="num"><?php echo (int)$avg; ?>m</td>
            <td><div class="dd-chips"><?php foreach ($rows as $x): ?><span class="dd-chip"><?php echo $fmt($x['d']); ?> <b><?php echo (int)$x['m']; ?>m</b></span><?php endforeach; ?></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?><div class="dd-none">No late time-ins in this period.</div><?php endif; ?>
</div>

<div class="dd-sec">
  <div class="dd-sec__h"><i class="fa-solid fa-user-xmark"></i> Unaccounted absences <span class="dd-count"><?php echo (int)$absTotal; ?></span></div>
  <?php if ($abs): ?>
    <table class="dd-tbl">
      <thead><tr><th>Employee</th><th class="num">Absences</th><th>Dates</th></tr></thead>
      <tbody>
        <?php foreach ($abs as $nm => $dates): ?>
          <tr>
            <td class="dd-emp"><?php echo $hh($nm); ?></td>
            <td class="num"><?php echo count($dates); ?></td>
            <td><div class="dd-chips"><?php foreach ($dates as $d): ?><span class="dd-chip dd-chip--abs"><?php echo $fmt($d); ?></span><?php endforeach; ?></div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?><div class="dd-none">No unaccounted absences in this period.</div><?php endif; ?>
</div>
