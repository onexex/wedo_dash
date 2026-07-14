<?php
/* ==========================================================================
   dashboard.php  —  Role-adaptive management overview.
   ---------------------------------------------------------------------------
   Scope is derived from the logged-in user's role:
     UserType 1 (Super User / HR) .... organization-wide
     UserType 2 (Immediate Superior) . their direct reports ("my team")
     everyone else ................... personal ("my activity")

   Panels: KPI stats, Pending approvals, Attendance snapshot,
           Workforce headcount, Leave & credits.

   Uses the themed shell (includes/wd-header.php / wd-footer.php) which exposes
   $wdpdo (PDO), $ar (access-rights row) and the session. All figures degrade
   gracefully to empty states — local seed data is sparse; this is built to be
   correct on production. Approval workflow (from notifications.php):
     status 1 = Pending (awaiting Immediate Superior, row.EmpSID/EmpISID)
     status 2 = Approved by IS (awaiting HR / Super User)
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
include 'w_conn.php';

/* --- session recovery from "remember me" cookie (mirrors index.php) --- */
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    if (isset($_COOKIE["WeDoID"])) {
        try {
            $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $statement = $pdo->prepare("SELECT * FROM empdetails");
            $statement->execute();
            while ($row = $statement->fetch()) {
                if (!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash'])
                    && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time())) {
                    $_SESSION['id']       = $row['EmpID'];
                    $_SESSION['UserType'] = $row['EmpRoleID'];
                    $_SESSION['CompID']   = $row['EmpCompID'];
                    $_SESSION['EmpISID']  = $row['EmpISID'];
                    $_SESSION['PassHash'] = $row['EmpPW'];
                    $cid = $row['EmpCompID'];
                    $stmt_comp = $pdo->prepare("SELECT * FROM companies WHERE CompanyID = :pw");
                    $stmt_comp->bindParam(':pw', $cid);
                    $stmt_comp->execute();
                    if ($stmt_comp->rowCount() > 0) {
                        $row_comp = $stmt_comp->fetch();
                        $_SESSION['CompanyName']  = $row_comp['CompanyDesc'];
                        $_SESSION['CompanyLogo']  = $row_comp['logopath'];
                        $_SESSION['CompanyColor'] = $row_comp['comcolor'];
                    } else {
                        $_SESSION['CompanyName'] = "ADMIN"; $_SESSION['CompanyLogo'] = ""; $_SESSION['CompanyColor'] = "red";
                    }
                    break;
                }
            }
        } catch (PDOException $e) { die("ERROR: Could not connect. " . $e->getMessage()); }
    }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }
}

/* --- access-rights gate: the `dashboard` right must be ON (==2). Runs BEFORE any
       output so the redirect can fire. Least-privilege: block unless explicitly
       granted. On a DB error we fall through and let the shell surface it. --- */
try {
    $gpdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $gpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $gstmt = $gpdo->prepare("SELECT dashboard FROM accessrights WHERE EmpID = :id");
    $gstmt->execute([':id' => $_SESSION['id']]);
    if ((int) $gstmt->fetchColumn() !== 2) { header('location: 404?'); exit(); }
} catch (PDOException $e) { /* DB down — the shell include below will report it */ }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : htmlspecialchars($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo ($_SESSION['CompanyLogo'] != "") ? htmlspecialchars($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <!-- Functional libs -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (after bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">
    <script type="text/javascript" src="assets/js/script.js"></script>

    <style>
      /* --- dashboard-scoped layout (built on wedo-theme tokens) --- */
      .dash-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
      @media (max-width:1080px){.dash-grid{grid-template-columns:1fr}}
      .dash-grid .wd-card{margin:0}
      .dash-cardnote{font-size:12px;color:var(--text-3)}
      .dash-body{padding:16px 20px}

      /* breakdown chips (pending approvals by type) */
      .dash-chips{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 4px}
      .dash-chip{flex:1 1 120px;min-width:120px;border:1px solid var(--border);border-radius:12px;padding:12px 14px;background:var(--surface)}
      .dash-chip__n{font-family:var(--font-head);font-weight:700;font-size:24px;line-height:1;color:var(--text)}
      .dash-chip__n.is-hot{color:var(--brand)}
      .dash-chip__l{font-size:12px;color:var(--text-2);margin-top:6px;display:flex;align-items:center;gap:6px}

      /* compact list rows shared by several panels */
      .dash-list{list-style:none;margin:14px 0 0;padding:0}
      .dash-list li{display:flex;align-items:center;gap:12px;padding:10px 0;border-top:1px solid var(--border)}
      .dash-list li:first-child{border-top:0}
      .dash-list .nm{font-weight:600;color:var(--text)}
      .dash-list .sub{font-size:12px;color:var(--text-3)}
      .dash-list .rt{margin-left:auto;text-align:right;white-space:nowrap}
      .dash-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--surface-2);color:var(--text-2);flex:none}
      .dash-empty{padding:22px 4px;text-align:center;color:var(--text-3);font-size:13px}
      .dash-empty i{display:block;font-size:22px;margin-bottom:8px;color:var(--border-2)}

      /* attendance donut-ish counters */
      .dash-att{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:6px}
      .dash-att__cell{flex:1 1 90px;text-align:center;border:1px solid var(--border);border-radius:12px;padding:12px 8px}
      .dash-att__n{font-family:var(--font-head);font-weight:700;font-size:26px;line-height:1}
      .dash-att__l{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-3);margin-top:6px}
      .n-present{color:var(--ok-text)} .n-leave{color:var(--info-text)} .n-out{color:var(--brand)}

      /* 7-day mini bar trend */
      .dash-bars{display:flex;align-items:flex-end;gap:8px;height:96px;margin:16px 0 2px}
      .dash-bars .b{flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end}
      .dash-bars .bar{width:100%;max-width:34px;background:var(--brand-tint);border-radius:6px 6px 0 0;min-height:3px;position:relative}
      .dash-bars .bar b{position:absolute;top:-18px;left:0;right:0;text-align:center;font-size:11px;color:var(--text-2);font-weight:600}
      .dash-bars .dy{font-size:10px;color:var(--text-3);white-space:nowrap}
      .dash-bars .b.is-today .bar{background:var(--brand)}

      /* department distribution bars */
      .dash-dept{margin-top:14px;display:grid;gap:9px}
      .dash-dept__row{display:grid;grid-template-columns:130px 1fr 34px;align-items:center;gap:10px;font-size:13px}
      .dash-dept__name{color:var(--text-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
      .dash-dept__track{height:8px;border-radius:6px;background:var(--surface-2);overflow:hidden}
      .dash-dept__fill{height:100%;background:var(--brand);border-radius:6px}
      .dash-dept__val{text-align:right;color:var(--text);font-weight:600}

      /* department tardiness/absence patterns */
      .dash-deptpat{margin-top:18px}
      .dash-period{display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap}
      .dash-headright{display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;justify-content:flex-end}
      .dash-period .wd-field{margin:0}
      .dash-period label{font-size:11px}
      .dpat-wrap{overflow-x:auto}
      table.dpat{width:100%;border-collapse:collapse;font-size:13px;min-width:640px}
      table.dpat th,table.dpat td{padding:11px 12px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap}
      table.dpat th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-3);font-weight:700}
      table.dpat th.num,table.dpat td.num{text-align:right}
      table.dpat tbody tr:hover{background:var(--surface-2)}
      table.dpat .dept{font-weight:600;color:var(--text);white-space:normal}
      .dpat-metric{display:flex;align-items:center;gap:10px;justify-content:flex-end}
      .dpat-metric .track{width:88px;height:8px;border-radius:6px;background:var(--surface-2);overflow:hidden;flex:none}
      .dpat-metric .fill{display:block;height:100%;border-radius:6px}
      .dpat-metric .fill.late{background:var(--warn-text)}
      .dpat-metric .fill.abs{background:var(--brand)}
      .dpat-metric .pct{min-width:38px;text-align:right;font-weight:700;color:var(--text)}
      .dpat-sub{color:var(--text-3);font-size:11px}
      /* department drill-down */
      table.dpat tbody tr{cursor:pointer}
      .dpat-drill{color:var(--text-3);font-size:10px;margin-left:6px;opacity:.45;transition:opacity .15s,transform .15s}
      table.dpat tbody tr:hover .dpat-drill{opacity:1;transform:translateX(2px);color:var(--brand)}
      .dd-modalhead{background:#f93627;color:#fff;border:0}
      .dd-modalhead .modal-title{color:#fff;font-family:var(--font-head);font-weight:700}
      .dd-modalhead .close{color:#fff;opacity:.9;text-shadow:none;font-size:26px}
      #dpatDrill .modal-body{max-height:72vh;overflow:auto;padding:18px 20px}
      .dd-loading,.dd-none{color:var(--text-3);text-align:center;padding:22px;font-size:13px}
      .dd-head{font-weight:700;color:var(--text);margin-bottom:14px;font-family:var(--font-head);font-size:16px}
      .dd-head .dd-period{font-weight:600;color:var(--text-3);font-size:12px}
      .dd-sec{margin-bottom:22px}
      .dd-sec__h{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-2);font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:8px}
      .dd-sec__h .dd-count{margin-left:auto;background:var(--surface-2);color:var(--text-2);border-radius:var(--radius-pill);padding:1px 10px;font-size:12px}
      table.dd-tbl{width:100%;border-collapse:collapse;font-size:13px}
      table.dd-tbl th,table.dd-tbl td{padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);vertical-align:top}
      table.dd-tbl th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-3);font-weight:700}
      table.dd-tbl th.num,table.dd-tbl td.num{text-align:right;white-space:nowrap}
      .dd-emp{font-weight:600;color:var(--text);white-space:nowrap}
      .dd-chips{display:flex;flex-wrap:wrap;gap:5px}
      .dd-chip{background:var(--warn-bg,#fdf0e3);color:var(--warn-text);border-radius:6px;padding:2px 7px;font-size:11px;white-space:nowrap}
      .dd-chip b{font-weight:700}
      .dd-chip--abs{background:var(--brand-tint);color:var(--brand)}

      .dash-scopebadge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--brand);background:var(--brand-tint);padding:4px 10px;border-radius:var(--radius-pill)}
      .dash-viewall{font-size:12px;font-weight:600;color:var(--brand);text-decoration:none;white-space:nowrap}
      .dash-viewall:hover{text-decoration:underline}
    </style>
  </head>

  <body>
    <?php
      /* status text -> themed pill (guarded; shared with migrated pages) */
      if (!function_exists('wd_status_pill')) {
          function wd_status_pill($desc) {
              $d = strtolower((string) $desc);
              if (strpos($d,'disapprove')!==false||strpos($d,'reject')!==false||strpos($d,'cancel')!==false||strpos($d,'deny')!==false||strpos($d,'decline')!==false){$c='danger';}
              elseif (strpos($d,'pending')!==false){$c='warn';}
              elseif (strpos($d,'approve')!==false||strpos($d,'released')!==false){$c='ok';}
              else{$c='info';}
              return '<span class="wd-pill wd-pill--'.$c.'">'.htmlspecialchars($desc).'</span>';
          }
      }
      $wd_active = 'dashboard';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName, $nrow

      /* ===================== DATA LAYER ===================== */
      $uid   = $_SESSION['id'];
      $utype = (int) ($_SESSION['UserType'] ?? 3);
      // The Dashboard is a MANAGEMENT overview (the personal "my day" view lives on
      // Home / index.php) and is gated by the `dashboard` access right. So: approvers
      // (UserType 2) see their own team; everyone else who holds the right — Super
      // Users, or anyone you deliberately grant it to — sees the whole organization.
      // There is no personal/self mode here (that would duplicate Home).
      $scope = ($utype === 2) ? 'team' : 'org';
      $scopeLabel = $scope==='org' ? 'Organization-wide' : 'My team';
      $today  = date('Y-m-d');

      /* scope predicate for any query that JOINs `employees e` + `empdetails d`
         and only wants ACTIVE (employed, not resigned) people. */
      $activeWhere = "e.EmpStatusID=1 AND (d.EmpDateResigned IS NULL OR d.EmpDateResigned='' OR d.EmpDateResigned='0000-00-00')";
      $scopeJoinWhere = $activeWhere;
      if     ($scope==='team') { $scopeJoinWhere .= " AND d.EmpISID = :uid"; }
      elseif ($scope==='self') { $scopeJoinWhere .= " AND e.EmpID = :uid"; }

      /* tiny query helper: auto-binds :uid when present in the SQL */
      $q = function($sql, $extra = []) use ($wdpdo, $uid) {
          $st = $wdpdo->prepare($sql);
          if (strpos($sql, ':uid') !== false) { $extra[':uid'] = $uid; }
          $st->execute($extra);
          return $st;
      };
      $scalar = function($sql, $extra = []) use ($q) {
          try { $v = $q($sql, $extra)->fetchColumn(); return $v === false ? 0 : $v; }
          catch (Exception $e) { return 0; }
      };

      /* ---- active roster in scope (headcount + attendance denominator) ---- */
      $roster = []; $activeCount = 0;
      try {
          $rs = $q("SELECT e.EmpID, e.EmpLN, e.EmpFN, p.PositionDesc,
                           COALESCE(dp.DepartmentDesc,'Unassigned') dept, d.EmpDateHired
                    FROM employees e
                    JOIN empdetails d ON e.EmpID=d.EmpID
                    LEFT JOIN positions p ON e.PosID=p.PSID
                    LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
                    WHERE $scopeJoinWhere
                    ORDER BY e.EmpLN, e.EmpFN");
          while ($r = $rs->fetch(PDO::FETCH_ASSOC)) { $roster[$r['EmpID']] = $r; }
          $activeCount = count($roster);
      } catch (Exception $e) {}

      /* ---- attendance snapshot — VALIDATED AGAINST SCHEDULE ----
         Only employees actually SCHEDULED to work on the day are counted/displayed
         (rostered non-rest weekday within their work-schedule effectivity, excluding
         holidays). The schedule is the validation, so a person on a rest day or with
         no schedule is never shown as "not clocked in". Not active-filtered: a live
         schedule effectivity already implies a current employee (and dodges the local
         seed's resigned-flag collapse). Same accounting as includes/loginabsencegate.php. */
      $attScope = ($scope==='team') ? "d.EmpISID = :uid" : "1=1";

      // effective date: today if anyone in scope clocked in today, else latest attendance day
      $latestAttn = $scalar("SELECT MAX(a.WSFrom) FROM attendancelog a
                             JOIN employees e ON a.EmpID=e.EmpID
                             JOIN empdetails d ON e.EmpID=d.EmpID
                             WHERE $attScope");
      $latestAttn = $latestAttn ?: null;
      $attnStale  = ($latestAttn && $latestAttn < $today);
      $attnDate   = $attnStale ? $latestAttn : $today;

      // employees SCHEDULED to work on $attnDate (with name/dept for the list)
      $scheduledEmp = [];
      try {
          $ss = $q("SELECT DISTINCT e.EmpID, e.EmpLN, e.EmpFN, p.PositionDesc,
                           COALESCE(dp.DepartmentDesc,'Unassigned') dept
                    FROM employees e
                    JOIN empdetails d ON e.EmpID=d.EmpID
                    JOIN workdays wd ON wd.empid=e.EmpID AND wd.Day_s=DAYNAME(:ad1)
                    JOIN workschedule ws ON wd.SchedTime=ws.WorkSchedID AND ws.WorkSchedID<>0
                    JOIN schedeffectivity se ON wd.EFID=se.efids AND :ad2 BETWEEN se.dfrom AND se.dto
                    LEFT JOIN positions p ON e.PosID=p.PSID
                    LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
                    LEFT JOIN holidays h ON h.Hdate=:ad3 AND h.HCompID=d.EmpCompID
                    WHERE h.SID IS NULL AND d.EmpRoleID <> 1 AND $attScope", [':ad1'=>$attnDate, ':ad2'=>$attnDate, ':ad3'=>$attnDate]);
          while ($r = $ss->fetch(PDO::FETCH_ASSOC)) { $scheduledEmp[$r['EmpID']] = $r; }
      } catch (Exception $e) {}
      $scheduledCount = count($scheduledEmp);

      // accounted-for sets on $attnDate (same statuses as the absence panel)
      $presentIds = $leaveIds = $obIds = [];
      try { $presentIds = array_flip($q("SELECT DISTINCT EmpID FROM attendancelog WHERE WSFrom=:ad", [':ad'=>$attnDate])->fetchAll(PDO::FETCH_COLUMN)); } catch (Exception $e) {}
      try { $leaveIds   = array_flip($q("SELECT DISTINCT EmpID FROM hleavesbd WHERE :ad BETWEEN LStart AND LEnd AND LStatus NOT IN (3,5,6,7)", [':ad'=>$attnDate])->fetchAll(PDO::FETCH_COLUMN)); } catch (Exception $e) {}
      try { $obIds      = array_flip($q("SELECT DISTINCT EmpID FROM obshbd WHERE :ad BETWEEN OBDateFrom AND OBDateTo AND OBStatus NOT IN (3,5,6,7)", [':ad'=>$attnDate])->fetchAll(PDO::FETCH_COLUMN)); } catch (Exception $e) {}

      // partition ONLY the scheduled employees
      $presentCount = $leaveCount = $obCount = 0; $notInIds = [];
      foreach ($scheduledEmp as $eid => $r) {
          if     (isset($presentIds[$eid])) { $presentCount++; }
          elseif (isset($leaveIds[$eid]))   { $leaveCount++; }
          elseif (isset($obIds[$eid]))      { $obCount++; }
          else                              { $notInIds[$eid] = $r; }
      }
      $notInCount = count($notInIds);
      $attnRate = $scheduledCount > 0 ? round($presentCount / $scheduledCount * 100) : 0;

      /* 7-day present trend ending $attnDate (scheduled-day clock-ins per day) */
      $trend = [];
      $d1 = date('Y-m-d', strtotime($attnDate.' -6 days'));
      try {
          $ts = $q("SELECT a.WSFrom d, COUNT(DISTINCT a.EmpID) c FROM attendancelog a
                    JOIN employees e ON a.EmpID=e.EmpID
                    JOIN empdetails d ON e.EmpID=d.EmpID
                    WHERE a.WSFrom BETWEEN :d1 AND :d2 AND $attScope
                    GROUP BY a.WSFrom", [':d1'=>$d1, ':d2'=>$attnDate]);
          $tmap = [];
          while ($t = $ts->fetch(PDO::FETCH_ASSOC)) { $tmap[$t['d']] = (int)$t['c']; }
          for ($i=6; $i>=0; $i--) { $dd = date('Y-m-d', strtotime($attnDate." -$i days")); $trend[$dd] = $tmap[$dd] ?? 0; }
      } catch (Exception $e) {}

      /* ---- dashboard reporting period — default YEAR-TO-DATE ----
         A single dashboard-wide window (Jan 1 of the effective year → effective
         date) driving every time-based panel below. User-overridable via GET
         (?pf=&pt=). Clamped to ~1 year to keep the absence recursive-CTE cheap. */
      $vd = function($s, $def) { $t = strtotime((string)$s); return $t ? date('Y-m-d', $t) : $def; };
      $patTo   = $vd($_GET['pt'] ?? '', $attnDate);
      $patFrom = $vd($_GET['pf'] ?? '', date('Y-01-01', strtotime($patTo)));   // Jan 1 of the "to" year
      if ($patFrom > $patTo) { $t = $patFrom; $patFrom = $patTo; $patTo = $t; }
      if (strtotime($patTo) - strtotime($patFrom) > 366*86400) { $patFrom = date('Y-m-d', strtotime($patTo.' -366 days')); }

      /* ---- pending approvals (scope-adaptive) ---- */
      // per-table meta: status col + immediate-superior col
      $ptbl = [
        'hl' => ['t'=>'hleaves',         'st'=>'LStatus',  'sup'=>'EmpSID',  'label'=>'Leave',     'icon'=>'fa-calendar-check'],
        'ob' => ['t'=>'obs',             'st'=>'OBStatus', 'sup'=>'EmpSID',  'label'=>'OB',        'icon'=>'fa-briefcase'],
        'eo' => ['t'=>'earlyout',        'st'=>'Status',   'sup'=>'EmpISID', 'label'=>'Early-out', 'icon'=>'fa-calendar-minus'],
        'ot' => ['t'=>'otattendancelog', 'st'=>'Status',   'sup'=>'EmpISID', 'label'=>'Overtime',  'icon'=>'fa-business-time'],
      ];
      // scope predicate (without status) for a table alias `a`
      $pendScope = function($m) use ($scope) {
          if ($scope==='team') return "a.{$m['sup']}=:uid AND a.{$m['st']}=1";      // awaiting me as superior
          if ($scope==='self') return "a.EmpID=:uid AND a.{$m['st']} IN (1,2)";     // my own pending filings
          return "a.{$m['st']} IN (1,2)";                                            // org: whole pending pipeline
      };
      $pendCount = ['hl'=>0,'ob'=>0,'eo'=>0,'ot'=>0]; $awaitIS = 0; $awaitHR = 0; $pendTotal = 0;
      foreach ($ptbl as $k => $m) {
          $w = $pendScope($m);
          try {
              $r = $q("SELECT SUM(a.{$m['st']}=1) a1, SUM(a.{$m['st']}=2) a2 FROM {$m['t']} a WHERE $w")
                   ->fetch(PDO::FETCH_ASSOC);
              $a1 = (int)($r['a1'] ?? 0); $a2 = (int)($r['a2'] ?? 0);
              $pendCount[$k] = $a1 + $a2; $awaitIS += $a1; $awaitHR += $a2; $pendTotal += $a1 + $a2;
          } catch (Exception $e) {}
      }

      /* actionable pending list (oldest first) — UNION across the four modules */
      $pendList = [];
      try {
          $parts = []; $pp = [];
          $sel = [
            'hl' => "SELECT 'Leave' typ,'fa-calendar-check' icon, CONCAT(b.EmpLN,', ',b.EmpFN) emp, a.LFDate filed, a.LStatus stt FROM hleaves a JOIN employees b ON a.EmpID=b.EmpID WHERE ",
            'ob' => "SELECT 'OB' typ,'fa-briefcase' icon, CONCAT(b.EmpLN,', ',b.EmpFN) emp, a.OBInputDate filed, a.OBStatus stt FROM obs a JOIN employees b ON a.EmpID=b.EmpID WHERE ",
            'eo' => "SELECT 'Early-out' typ,'fa-calendar-minus' icon, CONCAT(b.EmpLN,', ',b.EmpFN) emp, a.DateTimeInputed filed, a.Status stt FROM earlyout a JOIN employees b ON a.EmpID=b.EmpID WHERE ",
            'ot' => "SELECT 'Overtime' typ,'fa-business-time' icon, CONCAT(b.EmpLN,', ',b.EmpFN) emp, a.DateFiling filed, a.Status stt FROM otattendancelog a JOIN employees b ON a.EmpID=b.EmpID WHERE ",
          ];
          foreach ($ptbl as $k => $m) {
              // rebuild scope predicate with a per-subquery placeholder so :uid is unique in the UNION
              if     ($scope==='team') { $w = "a.{$m['sup']}=:u_$k AND a.{$m['st']}=1";  $pp[":u_$k"]=$uid; }
              elseif ($scope==='self') { $w = "a.EmpID=:u_$k AND a.{$m['st']} IN (1,2)"; $pp[":u_$k"]=$uid; }
              else                     { $w = "a.{$m['st']} IN (1,2)"; }
              $parts[] = $sel[$k].$w;
          }
          $ustmt = $wdpdo->prepare(implode(" UNION ALL ", $parts)." ORDER BY filed ASC LIMIT 12");
          $ustmt->execute($pp);
          $pendList = $ustmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (Exception $e) {}

      /* ---- workforce ---- */
      $totalOnFile = $scope==='org' ? (int)$scalar("SELECT COUNT(*) FROM employees") : $activeCount;
      $inactiveCount = max(0, $totalOnFile - $activeCount);
      // department distribution from the roster (already in memory)
      $deptDist = [];
      foreach ($roster as $r) { $d = $r['dept'] ?: 'Unassigned'; $deptDist[$d] = ($deptDist[$d] ?? 0) + 1; }
      arsort($deptDist);
      // hires within the reporting period (YTD) from roster
      $recentHires = [];
      foreach ($roster as $r) {
          if (!empty($r['EmpDateHired']) && $r['EmpDateHired'] >= $patFrom && $r['EmpDateHired'] <= $patTo) {
              $recentHires[] = $r;
          }
      }
      usort($recentHires, function($a,$b){ return strcmp($b['EmpDateHired'],$a['EmpDateHired']); });
      // resignations within the reporting period (YTD), scope-limited
      $recentResigned = (int)$scalar("SELECT COUNT(*) FROM employees e JOIN empdetails d ON e.EmpID=d.EmpID
          WHERE d.EmpDateResigned IS NOT NULL AND d.EmpDateResigned <> '' AND d.EmpDateResigned <> '0000-00-00'
                AND d.EmpDateResigned BETWEEN :rd AND :rt"
          . ($scope==='team' ? " AND d.EmpISID=:uid" : ($scope==='self' ? " AND e.EmpID=:uid" : "")),
          [':rd'=>$patFrom, ':rt'=>$patTo]);

      /* ---- leave & credits ---- */
      $onLeave = []; $upcoming = []; $lowCredit = [];
      try {
          $onLeave = $q("SELECT CONCAT(e.EmpLN,', ',e.EmpFN) nm, COALESCE(l.LeaveDesc,'Leave') lt, h.LStart, h.LEnd
                         FROM hleaves h
                         JOIN employees e ON h.EmpID=e.EmpID
                         JOIN empdetails d ON e.EmpID=d.EmpID
                         LEFT JOIN leaves l ON h.LType=l.LeaveID
                         WHERE h.LStatus IN (2,4,8,9,10) AND :ad BETWEEN h.LStart AND h.LEnd AND $scopeJoinWhere
                         ORDER BY h.LEnd ASC LIMIT 12", [':ad'=>$today])->fetchAll(PDO::FETCH_ASSOC);
      } catch (Exception $e) {}
      try {
          $upcoming = $q("SELECT CONCAT(e.EmpLN,', ',e.EmpFN) nm, COALESCE(l.LeaveDesc,'Leave') lt, h.LStart, h.LEnd
                          FROM hleaves h
                          JOIN employees e ON h.EmpID=e.EmpID
                          JOIN empdetails d ON e.EmpID=d.EmpID
                          LEFT JOIN leaves l ON h.LType=l.LeaveID
                          WHERE h.LStatus IN (2,4,8,9,10) AND h.LStart > :ad AND h.LStart <= :fut AND $scopeJoinWhere
                          ORDER BY h.LStart ASC LIMIT 8",
                          [':ad'=>$today, ':fut'=>date('Y-m-d', strtotime('+30 days'))])->fetchAll(PDO::FETCH_ASSOC);
      } catch (Exception $e) {}
      try {
          $lowCredit = $q("SELECT CONCAT(e.EmpLN,', ',e.EmpFN) nm, c.CT
                           FROM credit c
                           JOIN employees e ON c.EmpID=e.EmpID
                           JOIN empdetails d ON e.EmpID=d.EmpID
                           WHERE $scopeJoinWhere
                           ORDER BY c.CT ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
      } catch (Exception $e) {}

      /* ---- tardiness & absenteeism patterns, by department ----
         Uses the dashboard-wide reporting period ($patFrom..$patTo, default
         year-to-date). Includes everyone who was EMPLOYED
         DURING the window (resigned date empty or on/after the window start) and
         actually worked in it — NOT filtered on CURRENT active status, which would
         drop people who were employed during a past window but have since left (and
         would collapse to ~1 person on the local seed). Scope still applies
         (team = direct reports). Hidden for self scope. */
      $showDept = ($scope !== 'self');
      $scopeAnd = ($scope==='team') ? " AND d.EmpISID = :uid" : "";
      // YTD panels show CURRENT workforce only: not resigned and not OJT. "Not
      // resigned" is the active flag e.EmpStatusID=1 (1=active, 2=resigned) — NOT
      // the resignation DATE, which the local seed leaves populated on active staff
      // (21/22 actives have one), so a date test would hide almost everyone. OJT is
      // empdetails.EmpStatID=4 ("On-the Job Training", see includes/loginabsencegate.php).
      // EmpRoleID=1 (Super User / HR / admin) is also excluded: they don't clock in,
      // so counting them as scheduled would inflate absences with people who never log.
      $resignAnd = " AND e.EmpStatusID = 1 AND d.EmpStatID <> 4 AND d.EmpRoleID <> 1";
      $deptPat = [];
      $patT = ['logs'=>0,'late'=>0,'expected'=>0,'absences'=>0];
      if ($showDept) {
          // tardiness: MinsLack = minutes late on time-in (see query/Query-insertlilo.php).
          // Gemana (WeDoinc-0010) is flexi — tardy only from 8 AM — so his lateness is
          // recomputed from an 08:00 baseline off TimeIn via the CASE below (not the stored
          // MinsLack, which for older rows reflects a previous 7 AM schedule). Gemana-only;
          // everyone else keeps using MinsLack. Matches query/dashboard-drilldown.php.
          try {
              $st = $wdpdo->prepare(
                  "SELECT COALESCE(dp.DepartmentDesc,'Unassigned') dept, COUNT(*) logs,
                          SUM(CASE WHEN a.EmpID=:flx1
                                   THEN TIMESTAMPDIFF(SECOND, CONCAT(a.WSFrom,' 08:00:00'), a.TimeIn) > 0
                                   ELSE a.MinsLack > 0 END) late,
                          ROUND(AVG(NULLIF(CASE WHEN a.EmpID=:flx2
                                   THEN GREATEST(TIMESTAMPDIFF(SECOND, CONCAT(a.WSFrom,' 08:00:00'), a.TimeIn)/60, 0)
                                   ELSE a.MinsLack END, 0)),0) avg_min
                   FROM attendancelog a
                   JOIN employees e ON a.EmpID=e.EmpID
                   JOIN empdetails d ON e.EmpID=d.EmpID
                   LEFT JOIN positions p ON e.PosID=p.PSID
                   LEFT JOIN departments dp ON p.DepartmentID=dp.DepartmentID
                   WHERE a.WSFrom BETWEEN :pf AND :pt$scopeAnd$resignAnd
                   GROUP BY dept");
              $pr = [':pf'=>$patFrom, ':pt'=>$patTo, ':flx1'=>'WeDoinc-0010', ':flx2'=>'WeDoinc-0010']; if ($scope==='team') { $pr[':uid']=$uid; }
              $st->execute($pr);
              while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                  $dn = $r['dept'] ?: 'Unassigned';
                  $deptPat[$dn] = ['logs'=>(int)$r['logs'], 'late'=>(int)$r['late'], 'avg'=>(int)$r['avg_min'], 'expected'=>0, 'absences'=>0];
                  $patT['logs'] += (int)$r['logs']; $patT['late'] += (int)$r['late'];
              }
          } catch (Exception $e) {}
          // absenteeism: scheduled work days with NO attendance and NO ALAS leave / OB
          // on file, among employees who worked in the window. A day covered by any
          // ALAS (hleavesbd) or OB (obshbd) filing that isn't disapproved/cancelled
          // (status NOT IN 3,5,6,7) is excused and never counted absent. Set-based
          // mirror of includes/loginabsencegate.php — "scheduled" honors each employee's
          // work-schedule effectivity (workdays + workschedule + schedeffectivity,
          // non-rest, date within se.dfrom..se.dto) and excludes company holidays.
          try {
              $st = $wdpdo->prepare(
                  "WITH RECURSIVE dts AS (
                       SELECT DATE(:pf1) dt UNION ALL SELECT dt + INTERVAL 1 DAY FROM dts WHERE dt < :pt1
                   ),
                   att AS (SELECT DISTINCT EmpID FROM attendancelog WHERE WSFrom BETWEEN :pf2 AND :pt2)
                   SELECT COALESCE(dp.DepartmentDesc,'Unassigned') dept,
                          COUNT(DISTINCT CASE WHEN h.SID IS NULL THEN CONCAT(e.EmpID,'|',dts.dt) END) expected,
                          COUNT(DISTINCT CASE WHEN h.SID IS NULL AND a.LogID IS NULL AND lv.EmpID IS NULL AND ob.EmpID IS NULL
                                              THEN CONCAT(e.EmpID,'|',dts.dt) END) absences
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
                   WHERE 1=1$scopeAnd$resignAnd
                   GROUP BY dept");
              $pr = [':pf1'=>$patFrom, ':pt1'=>$patTo, ':pf2'=>$patFrom, ':pt2'=>$patTo];
              if ($scope==='team') { $pr[':uid']=$uid; }
              $st->execute($pr);
              while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                  $dn = $r['dept'] ?: 'Unassigned';
                  if (!isset($deptPat[$dn])) { $deptPat[$dn] = ['logs'=>0,'late'=>0,'avg'=>0,'expected'=>0,'absences'=>0]; }
                  $deptPat[$dn]['expected'] = (int)$r['expected'];
                  $deptPat[$dn]['absences'] = (int)$r['absences'];
                  $patT['expected'] += (int)$r['expected']; $patT['absences'] += (int)$r['absences'];
              }
          } catch (Exception $e) {}
          // derive rates + sort worst-first (by late %, then absence %)
          foreach ($deptPat as $dn => &$dv) {
              $dv['latePct'] = $dv['logs']     ? round($dv['late']     / $dv['logs']     * 100) : 0;
              $dv['absPct']  = $dv['expected'] ? round($dv['absences'] / $dv['expected'] * 100) : 0;
          }
          unset($dv);
          uasort($deptPat, function($a,$b){ return ($b['latePct']+$b['absPct']) <=> ($a['latePct']+$a['absPct']); });
      }

      /* helpers for rendering */
      function dash_name($n){ return htmlspecialchars(trim($n, ', ')); }
      function dash_age($filed){
          $t = strtotime($filed); if (!$t) return '';
          $d = floor((time() - $t) / 86400);
          if ($d <= 0) return 'today';
          return $d.'d ago';
      }
      $canNotif = !(isset($ar['notif']) && $ar['notif']==1); // notif==1 means blocked (per notifications.php)
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>Dashboard</h1>
          <p>Welcome back, <?php echo htmlspecialchars(trim(explode(',', $wdName)[1] ?? $wdName)); ?> &mdash; <?php echo date('l, F j Y'); ?></p>
        </div>
        <div class="dash-headright">
          <form method="get" action="dashboard" class="dash-period" title="Reporting period — defaults to year-to-date">
            <div class="wd-field"><label for="pf">From</label><input type="date" id="pf" name="pf" class="wd-input" style="width:auto;padding:7px 10px" value="<?php echo htmlspecialchars($patFrom); ?>"></div>
            <div class="wd-field"><label for="pt">To</label><input type="date" id="pt" name="pt" class="wd-input" style="width:auto;padding:7px 10px" value="<?php echo htmlspecialchars($patTo); ?>"></div>
            <button type="submit" class="wd-btn wd-btn--primary"><i class="fa-solid fa-filter"></i> Apply</button>
          </form>
          <span class="dash-scopebadge"><i class="fa-solid <?php echo $scope==='org'?'fa-building':($scope==='team'?'fa-users':'fa-user'); ?>"></i> <?php echo $scopeLabel; ?></span>
        </div>
      </div>

      <!-- ===== KPI STATS ===== -->
      <section class="wd-stats">
        <div class="wd-stat">
          <div class="wd-stat__label"><i class="fa-solid fa-users"></i> <?php echo $scope==='team'?'Direct reports':'Active employees'; ?></div>
          <div class="wd-stat__value"><?php echo (int)$activeCount; ?></div>
        </div>
        <div class="wd-stat">
          <div class="wd-stat__label"><i class="fa-solid fa-user-check"></i> Present <?php echo $attnStale?'(latest)':'today'; ?></div>
          <div class="wd-stat__value"><?php echo (int)$presentCount; ?> <span style="font-size:14px;color:var(--text-3);font-weight:600"><?php echo $scheduledCount?('· '.$attnRate.'%'):''; ?></span></div>
        </div>
        <div class="wd-stat">
          <div class="wd-stat__label"><i class="fa-solid fa-plane-departure"></i> On leave today</div>
          <div class="wd-stat__value"><?php echo (int)$leaveCount; ?></div>
        </div>
        <div class="wd-stat">
          <div class="wd-stat__label"><i class="fa-solid fa-clipboard-check"></i> <?php echo $scope==='self'?'My pending requests':'Pending approvals'; ?></div>
          <div class="wd-stat__value"><?php echo (int)$pendTotal; ?></div>
        </div>
      </section>

      <?php if ($showDept):
        $patLatePct = $patT['logs']     ? round($patT['late']     / $patT['logs']     * 100) : 0;
        $patAbsPct  = $patT['expected'] ? round($patT['absences'] / $patT['expected'] * 100) : 0;
      ?>
      <section class="wd-card dash-deptpat">
        <div class="wd-card__head">
          <h3>Tardiness &amp; absenteeism by department</h3>
          <span class="dash-cardnote"><i class="fa-regular fa-calendar"></i> <?php echo date('M j', strtotime($patFrom)); ?> &ndash; <?php echo date('M j, Y', strtotime($patTo)); ?></span>
        </div>
        <div class="dash-body">
          <p class="dash-cardnote" style="margin:0 0 4px">
            <?php echo date('M j', strtotime($patFrom)); ?> &ndash; <?php echo date('M j, Y', strtotime($patTo)); ?>
            &middot; <?php echo (int)$patT['late']; ?> late time-ins (<?php echo $patLatePct; ?>% of <?php echo (int)$patT['logs']; ?>)
            &middot; <?php echo (int)$patT['absences']; ?> unaccounted absences (<?php echo $patAbsPct; ?>% of <?php echo (int)$patT['expected']; ?> scheduled days)
          </p>
          <?php if ($deptPat): ?>
            <div class="dpat-wrap">
              <table class="dpat" data-pf="<?php echo htmlspecialchars($patFrom); ?>" data-pt="<?php echo htmlspecialchars($patTo); ?>">
                <thead>
                  <tr>
                    <th>Department</th>
                    <th class="num">Late time-ins</th>
                    <th class="num">Tardiness rate</th>
                    <th class="num">Avg&nbsp;late</th>
                    <th class="num">Absences</th>
                    <th class="num">Absence rate</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($deptPat as $dn => $dv): ?>
                    <tr data-dept="<?php echo htmlspecialchars($dn, ENT_QUOTES); ?>" title="Click to see who &amp; which dates">
                      <td class="dept"><?php echo htmlspecialchars($dn); ?> <i class="fa-solid fa-chevron-right dpat-drill"></i></td>
                      <td class="num"><?php echo (int)$dv['late']; ?> <span class="dpat-sub">/ <?php echo (int)$dv['logs']; ?></span></td>
                      <td class="num">
                        <span class="dpat-metric"><span class="track"><span class="fill late" style="width:<?php echo min(100,(int)$dv['latePct']); ?>%"></span></span><span class="pct"><?php echo (int)$dv['latePct']; ?>%</span></span>
                      </td>
                      <td class="num"><?php echo $dv['late']>0 ? ((int)$dv['avg'].' min') : '<span class="dpat-sub">&mdash;</span>'; ?></td>
                      <td class="num"><?php echo (int)$dv['absences']; ?> <span class="dpat-sub">/ <?php echo (int)$dv['expected']; ?></span></td>
                      <td class="num">
                        <span class="dpat-metric"><span class="track"><span class="fill abs" style="width:<?php echo min(100,(int)$dv['absPct']); ?>%"></span></span><span class="pct"><?php echo (int)$dv['absPct']; ?>%</span></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <p class="dash-cardnote" style="margin:12px 0 0"><i class="fa-solid fa-circle-info"></i> Tardiness = time-ins with minutes late &gt; 0. Absence = scheduled work days (per each employee's work-schedule effectivity, excluding holidays) with no attendance and no ALAS leave or OB on file &mdash; a day covered by any ALAS/OB filing that isn't disapproved or cancelled is never counted absent. Counted only for employees who were employed during the period (not resigned before it) and worked at least once in the window.</p>
          <?php else: ?>
            <div class="dash-empty"><i class="fa-solid fa-chart-simple"></i>No attendance for employees in this period. Adjust the period at the top of the dashboard.</div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>

      <div class="dash-grid">

        <!-- ===== PENDING APPROVALS ===== -->
        <section class="wd-card">
          <div class="wd-card__head">
            <h3><?php echo $scope==='self'?'My pending requests':'Pending approvals'; ?></h3>
            <?php if ($canNotif): ?><a class="dash-viewall" href="notifications">Open notifications <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
          </div>
          <div class="dash-body">
            <div class="dash-chips">
              <?php foreach ($ptbl as $k => $m): ?>
                <div class="dash-chip">
                  <div class="dash-chip__n <?php echo $pendCount[$k]>0?'is-hot':''; ?>"><?php echo (int)$pendCount[$k]; ?></div>
                  <div class="dash-chip__l"><i class="fa-solid <?php echo $m['icon']; ?>"></i> <?php echo $m['label']; ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($scope==='org' && $pendTotal>0): ?>
              <p class="dash-cardnote" style="margin:12px 0 0"><i class="fa-solid fa-circle-info"></i> <?php echo (int)$awaitHR; ?> awaiting HR &middot; <?php echo (int)$awaitIS; ?> awaiting immediate superiors</p>
            <?php endif; ?>

            <?php if ($pendList): ?>
              <ul class="dash-list">
                <?php foreach ($pendList as $p): $stt=(int)$p['stt']; ?>
                  <li>
                    <span class="dash-ico"><i class="fa-solid <?php echo htmlspecialchars($p['icon']); ?>"></i></span>
                    <div>
                      <div class="nm"><?php echo dash_name($p['emp']); ?></div>
                      <div class="sub"><?php echo htmlspecialchars($p['typ']); ?> &middot; filed <?php echo dash_age($p['filed']); ?></div>
                    </div>
                    <div class="rt">
                      <?php echo $stt===1 ? '<span class="wd-pill wd-pill--warn">Awaiting superior</span>' : '<span class="wd-pill wd-pill--info">Awaiting HR</span>'; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="dash-empty"><i class="fa-solid fa-circle-check"></i>Nothing awaiting action. You're all caught up.</div>
            <?php endif; ?>
          </div>
        </section>

        <!-- ===== ATTENDANCE SNAPSHOT ===== -->
        <section class="wd-card">
          <div class="wd-card__head">
            <h3>Attendance snapshot</h3>
            <span class="dash-cardnote"><?php echo ($attnStale ? 'Latest data: ' : '').date('M j, Y', strtotime($attnDate)); ?> &middot; <?php echo (int)$scheduledCount; ?> scheduled</span>
          </div>
          <div class="dash-body">
            <?php if ($scheduledCount === 0): ?>
              <div class="dash-empty"><i class="fa-solid fa-calendar-xmark"></i>No employees are scheduled to work on <?php echo date('M j, Y', strtotime($attnDate)); ?>.</div>
            <?php else: ?>
            <div class="dash-att">
              <div class="dash-att__cell"><div class="dash-att__n n-present"><?php echo (int)$presentCount; ?></div><div class="dash-att__l">Present</div></div>
              <div class="dash-att__cell"><div class="dash-att__n n-leave"><?php echo (int)($leaveCount + $obCount); ?></div><div class="dash-att__l">Leave / OB</div></div>
              <div class="dash-att__cell"><div class="dash-att__n n-out"><?php echo (int)$notInCount; ?></div><div class="dash-att__l">Not clocked in</div></div>
            </div>

            <?php
              $maxT = 0; foreach ($trend as $c) { $maxT = max($maxT, $c); }
              if ($maxT > 0):
            ?>
            <div class="dash-bars">
              <?php foreach ($trend as $dd => $c): $h = $maxT ? max(3, round($c / $maxT * 78)) : 3; ?>
                <div class="b <?php echo $dd===$attnDate?'is-today':''; ?>">
                  <div class="bar" style="height:<?php echo $h; ?>px"><b><?php echo $c>0?$c:''; ?></b></div>
                  <span class="dy"><?php echo date('D', strtotime($dd)); ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <p class="dash-cardnote" style="text-align:center;margin:2px 0 0">Present per day &mdash; last 7 days</p>
            <?php endif; ?>

            <?php if ($notInIds): ?>
              <ul class="dash-list">
                <?php $shown=0; foreach ($notInIds as $r): if ($shown++>=6) break; ?>
                  <li>
                    <span class="dash-ico"><i class="fa-solid fa-user-clock"></i></span>
                    <div>
                      <div class="nm"><?php echo dash_name($r['EmpLN'].', '.$r['EmpFN']); ?></div>
                      <div class="sub"><?php echo htmlspecialchars($r['PositionDesc'] ?: $r['dept']); ?></div>
                    </div>
                    <div class="rt"><span class="wd-pill wd-pill--danger">No time-in</span></div>
                  </li>
                <?php endforeach; ?>
                <?php if ($notInCount > 6): ?><li><span class="sub" style="margin:0 auto">+ <?php echo $notInCount-6; ?> more scheduled, not clocked in</span></li><?php endif; ?>
              </ul>
            <?php else: ?>
              <div class="dash-empty" style="padding-top:14px"><i class="fa-solid fa-user-check"></i>Everyone scheduled is present or accounted for.</div>
            <?php endif; ?>
            <?php endif; /* scheduledCount > 0 */ ?>
          </div>
        </section>

        <!-- ===== WORKFORCE ===== -->
        <?php if ($scope!=='self'): ?>
        <section class="wd-card">
          <div class="wd-card__head">
            <h3>Workforce</h3>
            <span class="dash-cardnote"><?php echo $scope==='team'?'Your team':'Company'; ?></span>
          </div>
          <div class="dash-body">
            <div class="dash-att">
              <div class="dash-att__cell"><div class="dash-att__n" style="color:var(--text)"><?php echo (int)$activeCount; ?></div><div class="dash-att__l">Active</div></div>
              <?php if ($scope==='org'): ?>
              <div class="dash-att__cell"><div class="dash-att__n" style="color:var(--text-2)"><?php echo (int)$totalOnFile; ?></div><div class="dash-att__l">On file</div></div>
              <div class="dash-att__cell"><div class="dash-att__n" style="color:var(--text-3)"><?php echo (int)$inactiveCount; ?></div><div class="dash-att__l">Resigned/Inactive</div></div>
              <?php endif; ?>
              <div class="dash-att__cell"><div class="dash-att__n n-out"><?php echo (int)$recentResigned; ?></div><div class="dash-att__l">Left (YTD)</div></div>
            </div>

            <?php if ($deptDist): $maxD = max($deptDist); ?>
            <div class="dash-dept">
              <?php $dn=0; foreach ($deptDist as $dname => $dc): if ($dn++>=6) break; ?>
                <div class="dash-dept__row">
                  <span class="dash-dept__name" title="<?php echo htmlspecialchars($dname); ?>"><?php echo htmlspecialchars($dname); ?></span>
                  <span class="dash-dept__track"><span class="dash-dept__fill" style="width:<?php echo $maxD?round($dc/$maxD*100):0; ?>%"></span></span>
                  <span class="dash-dept__val"><?php echo (int)$dc; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($recentHires): ?>
              <ul class="dash-list">
                <?php $hn=0; foreach ($recentHires as $r): if ($hn++>=3) break; ?>
                  <li>
                    <span class="dash-ico" style="background:var(--ok-bg);color:var(--ok-text)"><i class="fa-solid fa-user-plus"></i></span>
                    <div>
                      <div class="nm"><?php echo dash_name($r['EmpLN'].', '.$r['EmpFN']); ?></div>
                      <div class="sub">New hire &middot; <?php echo htmlspecialchars($r['PositionDesc'] ?: $r['dept']); ?></div>
                    </div>
                    <div class="rt sub"><?php echo date('M j', strtotime($r['EmpDateHired'])); ?></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>

        <!-- ===== LEAVE & CREDITS ===== -->
        <section class="wd-card">
          <div class="wd-card__head">
            <h3>Leave &amp; credits</h3>
            <?php if (wd_can($ar,'lcreaditview')): ?><a class="dash-viewall" href="leavecredit">Leave credits <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
          </div>
          <div class="dash-body">
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-3);margin-bottom:2px">On leave today</div>
            <?php if ($onLeave): ?>
              <ul class="dash-list">
                <?php foreach ($onLeave as $l): ?>
                  <li>
                    <span class="dash-ico" style="background:var(--info-bg);color:var(--info-text)"><i class="fa-solid fa-plane-departure"></i></span>
                    <div>
                      <div class="nm"><?php echo dash_name($l['nm']); ?></div>
                      <div class="sub"><?php echo htmlspecialchars($l['lt']); ?> &middot; until <?php echo date('M j', strtotime($l['LEnd'])); ?></div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="dash-empty" style="padding:14px 4px"><i class="fa-solid fa-user-check"></i>No one is on approved leave today.</div>
            <?php endif; ?>

            <?php if ($upcoming): ?>
              <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-3);margin:18px 0 2px">Upcoming (next 30 days)</div>
              <ul class="dash-list">
                <?php foreach ($upcoming as $l): ?>
                  <li>
                    <span class="dash-ico"><i class="fa-solid fa-calendar-day"></i></span>
                    <div>
                      <div class="nm"><?php echo dash_name($l['nm']); ?></div>
                      <div class="sub"><?php echo htmlspecialchars($l['lt']); ?></div>
                    </div>
                    <div class="rt sub"><?php echo date('M j', strtotime($l['LStart'])); ?></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if ($lowCredit): ?>
              <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-3);margin:18px 0 2px">Lowest leave-credit balances</div>
              <ul class="dash-list">
                <?php foreach ($lowCredit as $c): $ct=(float)$c['CT']; ?>
                  <li>
                    <span class="dash-ico" style="background:var(--brand-tint);color:var(--brand)"><i class="fa-solid fa-wallet"></i></span>
                    <div><div class="nm"><?php echo dash_name($c['nm']); ?></div></div>
                    <div class="rt"><span class="wd-pill <?php echo $ct<=0?'wd-pill--danger':($ct<3?'wd-pill--warn':'wd-pill--ok'); ?>"><?php echo number_format($ct,1); ?> days</span></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>

      </div><!-- /.dash-grid -->

    <?php include 'includes/wd-footer.php'; ?>

    <!-- department drill-down: who was late / absent, and on which dates -->
    <div class="modal" id="dpatDrill" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header dd-modalhead">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            <h4 class="modal-title"><i class="fa-solid fa-magnifying-glass-chart"></i> <span id="ddTitle">Department detail</span></h4>
          </div>
          <div class="modal-body" id="ddBody"></div>
        </div>
      </div>
    </div>
    <script>
      (function(){
        var tbl = document.querySelector('table.dpat');
        if (!tbl || !window.jQuery) return;
        var pf = tbl.getAttribute('data-pf'), pt = tbl.getAttribute('data-pt');
        tbl.querySelectorAll('tbody tr').forEach(function(tr){
          tr.addEventListener('click', function(){
            var dept = tr.getAttribute('data-dept'); if (!dept) return;
            document.getElementById('ddTitle').textContent = dept;
            document.getElementById('ddBody').innerHTML = '<div class="dd-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading&hellip;</div>';
            jQuery('#dpatDrill').modal('show');
            jQuery.get('query/dashboard-drilldown.php', { dept: dept, pf: pf, pt: pt })
              .done(function(html){ document.getElementById('ddBody').innerHTML = html; })
              .fail(function(){ document.getElementById('ddBody').innerHTML = '<div class="dd-none">Could not load details. Please try again.</div>'; });
          });
        });
      })();
    </script>
  </body>
</html>
