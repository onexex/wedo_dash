  <?php
    // --- 1. SESSION & TIMEZONE INITIALIZATION ---
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    date_default_timezone_set("Asia/Manila");
    include 'w_conn.php';

    // If session is NOT set, try to recover from Cookie
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {

        if (isset($_COOKIE["WeDoID"])) {
            try {
                $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Validate Cookie against Employee Records
                $statement = $pdo->prepare("SELECT * FROM empdetails");
                $statement->execute();

                while ($row = $statement->fetch()) {
                    if ((!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash']) && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time()))) {

                        // Set Core Session Variables
                        $_SESSION['id'] = $row['EmpID'];
                        $_SESSION['UserType'] = $row['EmpRoleID'];
                        $_SESSION['CompID'] = $row['EmpCompID'];
                        $_SESSION['EmpISID'] = $row['EmpISID'];
                        $_SESSION['PassHash'] = $row['EmpPW'];
                        $cid = $row['EmpCompID'];

                        // Fetch Company Specific Details
                        $stmt_comp = $pdo->prepare("SELECT * FROM companies WHERE CompanyID = :pw");
                        $stmt_comp->bindParam(':pw', $cid);
                        $stmt_comp->execute();

                        if ($stmt_comp->rowCount() > 0) {
                            $row_comp = $stmt_comp->fetch();
                            $_SESSION['CompanyName'] = $row_comp['CompanyDesc'];
                            $_SESSION['CompanyLogo'] = $row_comp['logopath'];
                            $_SESSION['CompanyColor'] = $row_comp['comcolor'];
                        } else {
                            $_SESSION['CompanyName'] = "ADMIN";
                            $_SESSION['CompanyLogo'] = "";
                            $_SESSION['CompanyColor'] = "red";
                        }

                        // Break the loop once match is found
                        break;
                    }
                }
            } catch(PDOException $e) {
                die("ERROR: Could not connect. " . $e->getMessage());
            }
        }

        // Re-check session: if still not set after cookie check, redirect
        if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
            header('location: login.php');
            exit();
        }
    }

    //function load data here
    function displayShiftMonitor($empID, $daysBack = 7) {
      include 'w_conn.php';

      try {
          $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

          $dt1 = date('Y-m-d', strtotime("-$daysBack days"));
          $dt2 = date('Y-m-d', strtotime('+1 days'));

          $statement = $pdo->prepare("SELECT * FROM dars WHERE EmpID = :name AND DarDateTime BETWEEN :dt1 AND :dt2 ORDER BY DarDateTime DESC");
          $statement->execute([
              ':name' => $empID,
              ':dt1' => $dt1,
              ':dt2' => $dt2
          ]);

          if ($statement->rowCount() > 0) {
              while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                  $ts = strtotime($row['DarDateTime']);
                  ?>
                  <tr>
                      <td><b><?php echo date("F j, Y", $ts); ?></b></td>
                      <td><?php echo date("l", $ts); ?></td>
                      <td><?php echo date("h:i:s A", $ts); ?></td>
                      <td><?php echo htmlspecialchars($row['EmpActivity']); ?></td>
                  </tr>
                  <?php
              }
          } else {
              echo "<tr><td colspan='4' style='text-align:center;color:var(--text-3)'>No activities recorded for the last $daysBack days.</td></tr>";
          }

      } catch(PDOException $e) {
          echo "<tr><td colspan='4'>Error: " . $e->getMessage() . "</td></tr>";
      }
    }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName']=="") ? "Dashboard" : $_SESSION['CompanyName']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo ($_SESSION['CompanyLogo']!="") ? $_SESSION['CompanyLogo'] : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <!-- Functional libs (modals + existing JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/script-home.js"></script>

    <style>
      .lg-buttons{display:flex;gap:10px;padding:0 16px 18px}
      .lg-buttons a{flex:1;cursor:pointer;border-radius:8px;color:#fff !important;font-size:18px;padding:10px;text-align:center}
      .lg-question{text-align:center;padding:18px 16px 8px}
      .loadingarea{text-align:center;padding:10px}
      .loadingarea img{width:60px}
      .loadingarea h2{display:inline-block}
      .flash.bg-danger{background:#fdebe9 !important;color:#b22a1d !important}

      /* My year so far */
      .ys-range{font-family:var(--font-body,inherit);font-weight:400;font-size:12.5px;color:var(--text-3);margin-left:6px}
      .ys-nav{display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px;color:var(--text-2)}
      .ys-body{padding:16px 20px}
      /* collapsible card: the title is the toggle; body animates open/closed */
      .ys-card .wd-card__head{gap:12px}
      .ys-card .wd-card__head h3{flex:1;min-width:0;margin:0}
      .ys-toggle{display:flex;align-items:center;gap:10px;width:100%;padding:0;border:0;background:none;text-align:left;cursor:pointer;
        font:inherit;color:inherit;border-radius:8px}
      .ys-toggle:focus{outline:none}
      .ys-toggle:focus-visible{box-shadow:0 0 0 3px var(--brand-tint)}
      .ys-toggle__chev{flex:0 0 26px;width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;
        background:var(--surface-2);color:var(--text-2);font-size:11px;transition:transform .25s ease,background .15s,color .15s}
      .ys-toggle:hover .ys-toggle__chev{background:var(--brand-tint);color:var(--brand)}
      .ys-card.is-collapsed .ys-toggle__chev{transform:rotate(-90deg)}
      .ys-toggle__txt{min-width:0;display:flex;align-items:baseline;flex-wrap:wrap;column-gap:6px}
      .ys-toggle__txt .ys-range{margin-left:0}
      /* one-line summary, only while collapsed */
      .ys-mini{display:none;font-family:var(--font-body,inherit);font-size:12.5px;font-weight:400;color:var(--text-2)}
      .ys-mini b{color:var(--text)}
      .ys-mini .wd-pill{font-size:11px;padding:1px 8px;vertical-align:1px}
      .ys-card.is-collapsed .ys-mini{display:inline}
      .ys-card.is-collapsed .ys-range{display:none}
      .ys-card.is-collapsed .wd-card__head{border-bottom-color:transparent}
      .ys-collapse{display:grid;grid-template-rows:1fr;transition:grid-template-rows .25s ease,visibility 0s}
      .ys-collapse > div{min-height:0;overflow:hidden}
      .ys-card.is-collapsed .ys-collapse{grid-template-rows:0fr;visibility:hidden;transition:grid-template-rows .25s ease,visibility 0s linear .25s}
      @media (max-width:600px){ .ys-card .wd-card__head{flex-wrap:wrap} .ys-card .ys-nav{margin-left:36px} }
      @media (prefers-reduced-motion:reduce){ .ys-collapse,.ys-toggle__chev{transition:none !important} }
      .ys-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
      .ys-cell{border:1px solid var(--border);border-radius:12px;padding:12px 14px}
      .ys-cell__l{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text-2)}
      .ys-cell__l i{color:var(--brand)}
      .ys-cell__n{font-family:var(--font-head);font-weight:700;font-size:26px;line-height:1;margin-top:10px;color:var(--text)}
      .ys-cell__s{font-size:11.5px;color:var(--text-3);margin-top:6px}
      .ys-credit{color:var(--text-2)}
      .ys-credit i{color:var(--brand);margin-right:3px}
      .ys-credit b{color:var(--text)}
      .ys-foot{margin-top:16px}
      .ys-foot__txt{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:13px;color:var(--text-2)}
      .ys-foot__txt b{color:var(--text)}
      .ys-track{height:8px;border-radius:6px;background:var(--surface-2);overflow:hidden;margin-top:8px}
      .ys-fill{height:100%;background:var(--brand);border-radius:6px}
      /* drill-down: clickable tiles/badges + modal (mirrors dashboard.php's dash-drill / dd-*) */
      .ys-drill{cursor:pointer}
      .ys-cell.ys-drill{position:relative;transition:border-color .15s,box-shadow .15s}
      .ys-cell.ys-drill:hover,.ys-cell.ys-drill:focus-visible{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-tint);outline:none}
      .ys-cell.ys-drill::after{content:"\f054";font-family:"Font Awesome 6 Free";font-weight:900;position:absolute;right:10px;top:10px;font-size:10px;color:var(--text-3);opacity:.45;transition:opacity .15s,transform .15s,color .15s}
      .ys-cell.ys-drill:hover::after,.ys-cell.ys-drill:focus-visible::after{opacity:1;transform:translateX(2px);color:var(--brand)}
      .wd-pill.ys-drill:hover,.wd-pill.ys-drill:focus-visible{filter:brightness(.95);box-shadow:0 0 0 2px currentColor;outline:none}
      .ys-modalhead{background:#f93627;color:#fff;border:0}
      .ys-modalhead .modal-title{color:#fff;font-family:var(--font-head);font-weight:700}
      .ys-modalhead .close{color:#fff;opacity:.9;text-shadow:none;font-size:26px}
      .ys-dd-period{font-size:12px;font-weight:600;color:var(--text-3);margin-bottom:12px}
      .ys-dd-sec{margin-bottom:22px}
      .ys-dd-sec__h{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-2);font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:8px}
      .ys-dd-count{margin-left:auto;background:var(--surface-2);color:var(--text-2);border-radius:var(--radius-pill);padding:1px 10px;font-size:12px}
      .ys-dd-none{color:var(--text-3);text-align:center;padding:18px;font-size:13px}
      table.ys-dd-tbl{width:100%;border-collapse:collapse;font-size:13px}
      table.ys-dd-tbl th,table.ys-dd-tbl td{padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);vertical-align:top}
      table.ys-dd-tbl th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-3);font-weight:700;white-space:nowrap}
      .ys-dd-date{font-weight:600;color:var(--text);white-space:nowrap}
    </style>
    <script type="text/javascript">
      document.onreadystatechange = function() {
        var el = document.getElementById("LoadingIndexViewer");
        if (!el) return;
        el.style.display = (document.readyState !== "complete") ? "block" : "none";
      };
    </script>
  </head>

  <body>
    <?php $wd_active = 'index'; include 'includes/wd-header.php'; ?>

      <div class="wd-pagehead">
        <div>
          <h1>Shift monitor</h1>
          <p>Welcome back, <?php echo htmlspecialchars(trim(explode(',', $wdName)[1] ?? $wdName)); ?> &mdash; <?php echo date('l, F j Y'); ?></p>
        </div>
        <button type="button" id="v_session" data-toggle="modal" data-target="#newformd" class="wd-btn wd-btn--primary"><i class="fa-solid fa-plus"></i> Update DAR</button>
      </div>

      <?php
        // Leave credits (balance + yearly cap) — shown on the leave tile below.
        $wdCredit = null;
        try {
          $cstmt = $wdpdo->prepare("SELECT CT, CTH FROM credit WHERE EmpID = :id");
          $cstmt->execute([':id' => $_SESSION['id']]);
          $cv = $cstmt->fetch(PDO::FETCH_ASSOC);
          if ($cv) { $wdCredit = $cv; }
        } catch (Exception $e) {}
      ?>

      <?php
        include_once 'includes/home-yearsummary.php';
        $ysNow  = (int)date('Y');
        $ysYear = isset($_GET['yr']) ? (int)$_GET['yr'] : $ysNow;
        if ($ysYear < 2019 || $ysYear > $ysNow) { $ysYear = $ysNow; }
        $ysFrom = $ysYear . '-01-01';
        $ysTo   = ($ysYear === $ysNow) ? date('Y-m-d') : $ysYear . '-12-31';
        $ys     = getYearSummary($wdpdo, $_SESSION['id'], $_SESSION['CompID'] ?? '', $ysFrom, $ysTo);
        $ysNum  = function ($v) { return ($v == floor($v)) ? number_format($v) : number_format($v, 1); };
        $ysPct  = ($ys && $ys['scheduledYear'] > 0) ? round($ys['accounted'] / $ys['scheduledYear'] * 100) : 0;
        $ysTiles = array(
          array('fa-right-to-bracket', 'Days timed in',     'timedin', ''),
          array('fa-briefcase',        'Official business', 'ob',      'Approved'),
          array('fa-flag',             'Regular holidays',  'regular', 'On scheduled days'),
          array('fa-star',             'Special holidays',  'special', 'On scheduled days'),
          array('fa-umbrella-beach',   'Leave (days)',      'leave',   'Approved'),
        );
      ?>
      <section class="wd-card ys-card" id="ysCard">
        <div class="wd-card__head">
          <h3>
            <button type="button" class="ys-toggle" id="ysToggle" aria-expanded="true" aria-controls="ysCollapse" title="Show or hide My year so far">
              <span class="ys-toggle__chev" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
              <span class="ys-toggle__txt">
                <span>My year so far</span>
                <span class="ys-range"><?php echo date('M j', strtotime($ysFrom)) . ' &ndash; ' . date('M j, Y', strtotime($ysYear . '-12-31')); ?></span>
                <?php if ($ys && $ys['scheduled'] > 0): ?>
                  <span class="ys-mini"><b><?php echo number_format($ys['accounted']); ?></b> of <?php echo number_format($ys['scheduledYear']); ?> days accounted (<?php echo $ysPct; ?>%)<?php
                    if ($ys['unaccounted'] > 0) { echo ' &middot; <span class="wd-pill wd-pill--warn">' . number_format($ys['unaccounted']) . ' unaccounted</span>'; }
                    if ($ys['conflicts']) { echo ' <span class="wd-pill wd-pill--danger">' . count($ys['conflicts']) . ' conflict' . (count($ys['conflicts']) > 1 ? 's' : '') . '</span>'; }
                  ?></span>
                <?php endif; ?>
              </span>
            </button>
          </h3>
          <div class="ys-nav">
            <?php if ($ysYear > 2019): ?><a class="wd-btn wd-btn--ghost wd-btn--sm" href="?yr=<?php echo $ysYear - 1; ?>" title="Previous year"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
            <span><?php echo $ysYear; ?></span>
            <?php if ($ysYear < $ysNow): ?><a class="wd-btn wd-btn--ghost wd-btn--sm" href="?yr=<?php echo $ysYear + 1; ?>" title="Next year"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
          </div>
        </div>
        <div class="ys-collapse" id="ysCollapse"><div>
        <div class="ys-body">
          <div class="ys-grid">
            <?php foreach ($ysTiles as $t): ?>
              <div class="ys-cell<?php echo $ys ? ' ys-drill' : ''; ?>" data-drill="<?php echo $t[2]; ?>" data-title="<?php echo $t[1]; ?>" role="button" tabindex="0" title="Click to see the days">
                <div class="ys-cell__l"><i class="fa-solid <?php echo $t[0]; ?>"></i> <?php echo $t[1]; ?></div>
                <div class="ys-cell__n"><?php echo $ys ? $ysNum($ys[$t[2]]) : '&mdash;'; ?></div>
                <?php if ($t[2] === 'leave' && $wdCredit && $ysYear === $ysNow): ?>
                  <div class="ys-cell__s ys-credit"><i class="fa-solid fa-wallet"></i> <b><?php echo $ysNum((float)$wdCredit['CT']); ?></b><?php if ((float)$wdCredit['CTH'] > 0) { echo ' of ' . $ysNum((float)$wdCredit['CTH']); } ?> credits left</div>
                <?php endif; ?>
                <div class="ys-cell__s">
                  <?php
                    if ($t[2] === 'leave' && $ys && ($ys['leaveUnpaid'] > 0 || $ys['leaveUpcoming'] > 0)) {
                      $ysLv = array();
                      if ($ys['leaveUnpaid'] > 0)   { $ysLv[] = $ysNum($ys['leaveUnpaid']) . ' unpaid'; }
                      if ($ys['leaveUpcoming'] > 0) { $ysLv[] = '+' . $ysNum($ys['leaveUpcoming']) . ' upcoming'; }
                      echo implode(' &middot; ', $ysLv);
                    } else {
                      echo $t[3] !== '' ? $t[3] : '&nbsp;';
                    }
                  ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($ys && $ys['scheduled'] === 0): ?>
            <div class="ys-foot"><div class="ys-foot__txt">No work schedule on file for this period.</div></div>
          <?php elseif ($ys): ?>
            <div class="ys-foot">
              <div class="ys-foot__txt">
                <b><?php echo number_format($ys['accounted']); ?></b> of <?php echo number_format($ys['scheduledYear']); ?> scheduled days accounted for
                <?php if ($ys['unaccounted'] > 0): ?>
                  <span class="wd-pill wd-pill--warn ys-drill" data-drill="unaccounted" data-title="Unaccounted days" role="button" tabindex="0" title="Click to see the days"><?php echo number_format($ys['unaccounted']); ?> unaccounted</span>
                <?php endif; ?>
                <?php if ($ys['absent'] > 0): ?>
                  <span class="wd-pill wd-pill--danger ys-drill" data-drill="absent" data-title="Absent days" role="button" tabindex="0" title="Leave/OB filed for these days was disapproved or cancelled"><?php
                    $ysWhy = $ys['absentWhy'];
                    if (count($ysWhy) === 1) { $ysWhyTxt = key($ysWhy); }
                    else { $ysWhyTxt = implode(', ', array_map(function ($w, $n) { return $n . ' ' . $w; }, array_keys($ysWhy), $ysWhy)); }
                    echo number_format($ys['absent']) . ' absent &middot; ' . htmlspecialchars($ysWhyTxt);
                  ?></span>
                <?php endif; ?>
                <?php if ($ys['pending'] > 0): ?>
                  <span class="wd-pill ys-drill" data-drill="pending" data-title="Pending approval" role="button" tabindex="0" title="Leave/OB filed for these days is still awaiting approval"><?php echo number_format($ys['pending']); ?> pending</span>
                <?php endif; ?>
                <?php if ($ys['conflicts']): $ysC = count($ys['conflicts']); ?>
                  <span class="wd-pill wd-pill--danger ys-drill" data-drill="conflicts" data-title="Conflicts" role="button" tabindex="0" title="Timed in on a whole-day approved leave: <?php echo htmlspecialchars(implode(', ', array_map(function ($d) { return date('M j', strtotime($d)); }, $ys['conflicts']))); ?>. Ask HR to cancel the leave or remove the time-in.">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $ysC . ' conflict' . ($ysC > 1 ? 's' : ''); ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="ys-track"><div class="ys-fill" style="width:<?php echo $ysPct; ?>%"></div></div>
            </div>
          <?php endif; ?>
        </div>
        </div></div><!-- /.ys-collapse -->
      </section>
      <script>
        // always starts expanded; the toggle only collapses it for this view
        (function () {
          var card = document.getElementById('ysCard'), btn = document.getElementById('ysToggle');
          btn.addEventListener('click', function () {
            var closed = card.classList.toggle('is-collapsed');
            btn.setAttribute('aria-expanded', closed ? 'false' : 'true');
          });
        })();
      </script>

      <section class="wd-card">
        <div class="wd-card__head">
          <h3>Daily activity report</h3>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <input type="date" id="dpfrom" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" class="wd-input" style="width:auto;padding:7px 10px">
            <span style="color:var(--text-3);font-size:12px">to</span>
            <input type="date" id="dpto" value="<?php echo date('Y-m-d'); ?>" class="wd-input" style="width:auto;padding:7px 10px">
            <button class="wd-btn wd-btn--ghost btnref" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
          </div>
        </div>
        <div class="wd-tablewrap">
          <table class="wd-table">
            <thead><tr><th>Date</th><th>Day</th><th>Time</th><th>Activity</th></tr></thead>
            <tbody id="adddar"><?php displayShiftMonitor($_SESSION['id']); ?></tbody>
          </table>
        </div>
      </section>

      <section class="wd-card">
        <div class="wd-card__head">
          <h3>Attendance log</h3>
          <div style="display:flex;align-items:center;gap:10px">
            <button class="wd-btn wd-btn--ghost btnref2" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            <button class="wd-btn wd-btn--primary lilosave" id="v_logins" data-toggle="modal" data-target="#LoginWarning"><i class="fa-solid fa-right-to-bracket"></i> Time in / out</button>
          </div>
        </div>
        <div class="wd-tablewrap">
          <table class="wd-table">
            <thead><tr><th>Date</th><th>Day</th><th>Schedule</th><th>Time in</th><th>Time out</th><th>Type/Status</th><th>Duration</th></tr></thead>
            <tbody id="addlilo">
              <?php
                $dt1 = date('Y-m-d', strtotime('-7 days'));
                $dt2 = date('Y-m-d');
                include 'includes/home-attendancelog.php';
              ?>
            </tbody>
          </table>
        </div>
      </section>

    <?php include 'includes/wd-footer.php'; ?>

    <!-- My year so far: drill-down (rows come pre-built from getYearSummary) -->
    <div class="modal" id="ysDrill" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header ys-modalhead">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
            <h4 class="modal-title"><i class="fa-solid fa-magnifying-glass-chart"></i> <span id="ysDrillTitle">Detail</span></h4>
          </div>
          <div class="modal-body" id="ysDrillBody"></div>
        </div>
      </div>
    </div>
    <script>
      (function(){
        var data = <?php echo json_encode($ys ? $ys['detail'] : new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
        var period = <?php echo json_encode(date('M j', strtotime($ysFrom)) . ' – ' . date('M j, Y', strtotime($ysYear . '-12-31'))); ?>;
        function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
        function render(secs){
          var h = '<div class="ys-dd-period">' + esc(period) + '</div>';
          (secs || []).forEach(function(s){
            if (s.hideEmpty && !s.rows.length) return;
            h += '<div class="ys-dd-sec"><div class="ys-dd-sec__h">' + esc(s.title) + '<span class="ys-dd-count">' + s.rows.length + '</span></div>';
            if (!s.rows.length) { h += '<div class="ys-dd-none">Nothing in this period.</div></div>'; return; }
            h += '<div class="wd-tablewrap"><table class="ys-dd-tbl"><thead><tr>';
            s.cols.forEach(function(c){ h += '<th>' + esc(c) + '</th>'; });
            h += '</tr></thead><tbody>';
            s.rows.forEach(function(r){
              h += '<tr>' + r.map(function(v, i){ return '<td' + (i === 0 ? ' class="ys-dd-date"' : '') + '>' + esc(v) + '</td>'; }).join('') + '</tr>';
            });
            h += '</tbody></table></div></div>';
          });
          return h;
        }
        document.querySelectorAll('.ys-drill[data-drill]').forEach(function(el){
          function go(){
            var k = el.getAttribute('data-drill');
            if (!data[k] || !window.jQuery) return;
            document.getElementById('ysDrillTitle').textContent = el.getAttribute('data-title') || 'Detail';
            document.getElementById('ysDrillBody').innerHTML = render(data[k]);
            jQuery('#ysDrill').modal('show');
          }
          el.addEventListener('click', go);
          el.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });
      })();
    </script>

    <!-- ===== Modals (Bootstrap; hooks preserved for script-home.js) ===== -->
    <div class="modal" id="newformd">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header" style="padding:0;background-color:#f93627;color:#fff">
            <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;font-size:30px !important">&times;</button>
            <h4 class="modal-title" style="padding:10px">Activity logger</h4>
          </div>
          <div class="modal-body ob-body">
            <form id="gdata" action="">
              <div class="form-group darshow" style="display:block">
                <textarea id="daract" name="daract" class="form-control" placeholder="Input activity here..."></textarea>
              </div>
              <button type="button" id="obsave" class="wd-btn wd-btn--primary" style="margin-top:10px">Update DAR</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="LoginWarning">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="padding:7px 8px;border:none">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="dv-q">
              <div class="lg-question"><h5 style="font-size:20px">You are about to log in. Continue?</h5></div>
              <div class="lg-buttons">
                <a class="btn-login" id="lgyes" style="background:#1c7a44">Yes</a>
                <a class="btn-cancel" id="lgno" data-dismiss="modal" style="background:#b22a1d">No</a>
              </div>
              <h3 class="loadd" style="display:none;text-align:center">Loading ...</h3>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="LoginEOUnder">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="padding:7px 8px;border:none">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="dv-q">
              <div class="lg-question">
                <h3 style="color:#b22a1d">You are about to log in. Continue?</h3>
                <h4><i class="fa-solid fa-triangle-exclamation" style="color:#e3c80b"></i></h4>
              </div>
              <div class="lg-buttons">
                <a id="lgyesf" style="background:#1c7a44">Confirm</a>
                <a class="btn-cancel" id="lgnof" data-dismiss="modal" style="background:#b22a1d">No</a>
              </div>
              <h3 class="loadd" style="display:none;text-align:center">Loading ...</h3>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal" id="modalWarning">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="padding:7px 8px;border:none">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body"><div class="alert alert-success"></div></div>
        </div>
      </div>
    </div>

    <div class="modal" id="LoadingIndexViewer">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header" style="padding:7px 8px;border:none"></div>
          <div class="modal-body">
            <div class="loadingarea"><h2>Loading</h2><img src="assets/images/load.gif"></div>
          </div>
        </div>
      </div>
    </div>

  </body>
</html>
