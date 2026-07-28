<?php
/* ==========================================================================
   dashboard-drilldown.php  —  detail behind the dashboard's
   "Tardiness & absenteeism by department" panel.

   Given a department + reporting period, returns (as an HTML partial for the
   drill-down modal) the individual LATE time-ins (who / date / minutes late)
   and the individual ABSENCES (who / date) for that department.

   Mirrors dashboard.php exactly: scope from session role, employed-during-window
   resignation filter, and the schedule-aware absence accounting (ALAS/OB with a
   status NOT IN (3,5,6,7) excuse the day). Scope + access are derived from the
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

/* ---- absences: one row per scheduled day with no attendance/leave/OB ---- */
$abs = [];
try {
    $st = $pdo->prepare(
        "WITH RECURSIVE dts AS (
             SELECT DATE(:pf1) dt UNION ALL SELECT dt + INTERVAL 1 DAY FROM dts WHERE dt < :pt1
         ),
         att AS (SELECT DISTINCT EmpID FROM attendancelog WHERE WSFrom BETWEEN :pf2 AND :pt2)
         SELECT e.EmpLN, e.EmpFN, dts.dt d
         FROM dts
         JOIN att ON 1=1
         JOIN employees e ON e.EmpID = att.EmpID
         JOIN empdetails d ON e.EmpID = d.EmpID
         JOIN workdays wd ON wd.empid = e.EmpID AND wd.Day_s = DAYNAME(dts.dt)
         JOIN workschedule ws ON wd.SchedTime = ws.WorkSchedID AND ws.WorkSchedID <> 0
         JOIN schedeffectivity se ON wd.EFID = se.efids AND dts.dt BETWEEN se.dfrom AND se.dto
         LEFT JOIN positions p ON e.PosID = p.PSID
         LEFT JOIN departments dp ON p.DepartmentID = dp.DepartmentID
         LEFT JOIN holidays h ON h.Hdate = dts.dt AND h.HCompID = d.EmpCompID
         LEFT JOIN attendancelog a ON a.EmpID = e.EmpID AND a.WSFrom = dts.dt
         LEFT JOIN hleavesbd lv ON lv.EmpID = e.EmpID AND dts.dt BETWEEN lv.LStart AND lv.LEnd AND lv.LStatus NOT IN (3,5,6,7)
         LEFT JOIN obshbd ob ON ob.EmpID = e.EmpID AND dts.dt BETWEEN ob.OBDateFrom AND ob.OBDateTo AND ob.OBStatus NOT IN (3,5,6,7)
         WHERE COALESCE(dp.DepartmentDesc,'Unassigned') = :dept
           AND h.SID IS NULL AND a.LogID IS NULL AND lv.EmpID IS NULL AND ob.EmpID IS NULL$scopeAnd$resignAnd
         GROUP BY e.EmpID, e.EmpLN, e.EmpFN, dts.dt
         ORDER BY e.EmpLN, e.EmpFN, dts.dt");
    $pr = [':pf1'=>$pf, ':pt1'=>$pt, ':pf2'=>$pf, ':pt2'=>$pt, ':dept'=>$dept];
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
