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
                    if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])) {

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
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

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
        $wdct = '&mdash;';
        try {
          $cstmt = $wdpdo->prepare("SELECT CT FROM credit WHERE EmpID = :id");
          $cstmt->execute([':id' => $_SESSION['id']]);
          $cv = $cstmt->fetchColumn();
          if ($cv !== false) { $wdct = number_format((float)$cv, 1); }
        } catch (Exception $e) {}
      ?>
      <section class="wd-stats">
        <div class="wd-stat"><div class="wd-stat__label"><i class="fa-solid fa-calendar-day"></i> Today</div><div class="wd-stat__value"><?php echo date('M j'); ?></div></div>
        <div class="wd-stat"><div class="wd-stat__label"><i class="fa-solid fa-bell"></i> Notifications</div><div class="wd-stat__value"><?php echo (int)$nrow; ?></div></div>
        <div class="wd-stat"><div class="wd-stat__label"><i class="fa-solid fa-wallet"></i> Leave credits</div><div class="wd-stat__value"><?php echo $wdct; ?></div></div>
      </section>

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
