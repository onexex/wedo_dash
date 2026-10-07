<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    include 'w_conn.php';
    require_once __DIR__ . '/includes/app-remember.php';

    // 1. Handle Logout immediately
    if (isset($_GET['logout'])) {
    // invalidate the server-side remember-me tokens (website's and, in the app, this phone's)
    $pdoLogout = null;
    try {
        $pdoLogout = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $pdoLogout->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!empty($_SESSION['id'])) {
            $pdoLogout->prepare("UPDATE empdetails SET remember_hash=NULL, remember_expiry=NULL WHERE EmpID=:id")
                      ->execute([':id' => $_SESSION['id']]);
        }
    } catch (Exception $e) { /* non-fatal */ }
    app_remember_revoke($pdoLogout);
    $_SESSION = [];
    session_destroy();
    setcookie('WeDoID', '', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!=='off')]);
    header('location: login.php');
    exit();
    }

    // Seasonal login theme (Maintenance > Login Theme). ?lt_preview=<id> lets a
    // signed-in holder of the `logintheme` right see an entry before its dates,
    // so it has to be resolved BEFORE the "already signed in" redirect below.
    include_once __DIR__ . '/includes/login-theme.php';
    $loginTheme = null;
    $ltPreviewName = null;
    try {
        $ltpdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $ltpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (isset($_GET['lt_preview']) && lt_can_manage($ltpdo)) {
            $ltst = $ltpdo->prepare("SELECT * FROM login_themes WHERE id = :id");
            $ltst->execute([':id' => (int) $_GET['lt_preview']]);
            if ($ltrow = $ltst->fetch(PDO::FETCH_ASSOC)) {
                $loginTheme    = lt_view_data($ltrow);
                $ltPreviewName = $ltrow['name'];
            }
        } else {
            $loginTheme = lt_for_login($ltpdo);
        }
    } catch (Throwable $e) { $loginTheme = null; } // never block sign-in over a theme

    // 2. If session already exists, redirect to index
    if (isset($_SESSION['id']) && $_SESSION['id'] != "0" && $ltPreviewName === null) {
    header('location: index.php');
    exit();
    }

    // Sign the employee of one empdetails row (+ company columns) in and go home.
    // Used by both "remember" cookies below.
    $wdStartSession = function (array $row) {
        // Set all session variables at once
        $_SESSION['id']       = $row['EmpID'];
        $_SESSION['UserType'] = $row['EmpRoleID'];
        $_SESSION['CompID']   = $row['EmpCompID'];
        $_SESSION['EmpISID']  = $row['EmpISID'];
        $_SESSION['PassHash'] = $row['EmpPW'];

        // Handle Company details or Admin defaults
        if (! empty($row['EmpCompID'])) {
            $_SESSION['CompanyName']  = $row['CompanyDesc'];
            $_SESSION['CompanyLogo']  = $row['logopath'];
            $_SESSION['CompanyColor'] = $row['comcolor'];
        } else {
            $_SESSION['CompanyName']  = "ADMIN";
            $_SESSION['CompanyLogo']  = "";
            $_SESSION['CompanyColor'] = "red";
        }

        header('location: index.php');
        exit();
    };

    // 3. The mobile app's own 30-day, per-phone token (includes/app-remember.php)
    if (isset($_COOKIE[APP_REMEMBER_COOKIE]) && $ltPreviewName === null) {
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $appEmp = app_remember_restore($pdo);
        if ($appEmp !== null) {
            $stmt = $pdo->prepare("SELECT e.*, c.CompanyDesc, c.logopath, c.comcolor
                                     FROM empdetails e
                                     LEFT JOIN companies c ON e.EmpCompID = c.CompanyID
                                    WHERE e.EmpID = :id");
            $stmt->execute([':id' => $appEmp]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $wdStartSession($row); }
        }
    } catch (PDOException $e) {
        error_log("Connection Error: " . $e->getMessage());
    }
    }

    if (isset($_COOKIE["WeDoID"]) && $ltPreviewName === null) {
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch everything in ONE query using a LEFT JOIN
        $sql = "SELECT e.*, c.CompanyDesc, c.logopath, c.comcolor
                  FROM empdetails e
                  LEFT JOIN companies c ON e.EmpCompID = c.CompanyID";

        $stmt = $pdo->query($sql);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Check if this row's EmpID matches the hashed cookie
            if ((!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash']) && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time()))) {
                $wdStartSession($row);
            }
        }
    } catch (PDOException $e) {
        error_log("Connection Error: " . $e->getMessage());
    }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>WeDo | Unified Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/images/logos/wedo-favicon.png">

    <!-- WeDo design system (token-driven theme) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">

    <!-- Functional scripts (unchanged) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="assets/js/logintime.js"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php
    $lt       = $loginTheme;
    $ltThemed = $lt && !$lt['plain'];
    $ltc      = $lt ? $lt['colors'] : [];
    $lte      = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
    if ($lt):
?>
    <style>
        /* ---- Seasonal login theme (Maintenance > Login Theme) — only emitted when a theme shows ---- */
<?php if (!empty($ltc)): /* colours come only from lt_presets(), never from user input */ ?>
        .wd-login.lt-themed {
            --brand: <?php echo $ltc['primary']; ?>; --brand-600: <?php echo $ltc['primaryDark']; ?>; --brand-700: <?php echo $ltc['primaryDark']; ?>;
            --lt-accent: <?php echo $ltc['accent']; ?>; --lt-deep: <?php echo $ltc['primaryDeep']; ?>;
            background: radial-gradient(120% 120% at 0% 0%, <?php echo $ltc['bg1']; ?> 0%, <?php echo $ltc['bg2']; ?> 45%, <?php echo $ltc['bg3']; ?> 100%);
        }
        .wd-login.lt-themed::before, .wd-login.lt-themed::after { content: ''; position: absolute; border-radius: 50%; filter: blur(90px); pointer-events: none; z-index: 0; }
        .wd-login.lt-themed::before { width: 46vw; height: 46vw; top: -14vw; left: -10vw; background: <?php echo $ltc['glow1']; ?>; }
        .wd-login.lt-themed::after  { width: 38vw; height: 38vw; bottom: -12vw; right: -8vw; background: <?php echo $ltc['glow2']; ?>; }
<?php endif; ?>
        .wd-login .wd-login__card, .wd-login .wd-clock { position: relative; z-index: 2; }
        .wd-login .wd-clock { position: absolute; }
        .lt-stack { position: relative; z-index: 2; width: 380px; max-width: 88%; }
        .lt-stack .wd-login__card { width: 100%; max-width: 100%; }
        .lt-announce {
            display: flex; align-items: center; gap: 10px; margin: 0 0 14px; text-align: left;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); color: #fff;
            backdrop-filter: blur(10px); border-radius: 14px; padding: 10px 14px; font-size: 13px; line-height: 1.45;
        }
        .lt-announce .ico { width: 28px; height: 28px; flex: 0 0 auto; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: var(--lt-accent, var(--brand)); color: var(--lt-deep, #fff); }
        .lt-preview-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 20; text-align: center; background: #fbbf24; color: #422006; font-size: 13px; font-weight: 700; padding: 7px 16px; }
        .lt-preview-bar a { color: #422006; margin-left: 10px; }

        .lt-banner { margin: -40px -34px 22px; height: 150px; border-radius: 22px 22px 0 0; background-size: cover; background-position: center; }
        .lt-greet { margin: 0 0 20px; }
        .lt-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 10.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; padding: 5px 12px; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); color: var(--lt-accent); margin-bottom: 10px; }
        .lt-title { font-family: var(--font-head); color: #fff; font-size: 24px; font-weight: 800; line-height: 1.25; margin: 0 0 6px; }
        .lt-title .hl { color: var(--lt-accent); }
        .lt-msg { color: rgba(255,255,255,.72); font-size: 13px; line-height: 1.55; margin: 0 auto; max-width: 34ch; }
        .wd-login.lt-themed .wd-login__card h2 { font-size: 18px; }

        .lt-logo { position: relative; display: inline-block; margin: 0 auto 18px; }
        .lt-logo img { margin: 0 !important; }
        .lt-badge { position: absolute; top: -14px; right: -22px; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 17px; background: var(--lt-accent); color: var(--lt-deep); border: 3px solid rgba(255,255,255,.9); box-shadow: 0 6px 14px -6px rgba(0,0,0,.6); animation: ltBob 3s ease-in-out infinite; }
        @keyframes ltBob { 0%,100% { transform: translateY(0) rotate(-6deg); } 50% { transform: translateY(-5px) rotate(6deg); } }

        .lt-lights { position: absolute; left: 18px; right: 18px; top: -9px; height: 24px; z-index: 5; display: flex; justify-content: space-between; pointer-events: none; }
        .lt-lights::before { content: ''; position: absolute; left: -8px; right: -8px; top: 3px; height: 14px; border-top: 2px solid rgba(255,255,255,.35); border-radius: 50%; }
        .lt-lights i { position: relative; top: 6px; width: 8px; height: 13px; border-radius: 50% / 40% 40% 60% 60%; animation: ltTwinkle 1.6s ease-in-out infinite; }
        .lt-lights i:nth-child(4n+1) { background: #ff4d4d; color: #ff4d4d; }
        .lt-lights i:nth-child(4n+2) { background: #ffd54f; color: #ffd54f; animation-delay: .4s; }
        .lt-lights i:nth-child(4n+3) { background: #4caf50; color: #4caf50; animation-delay: .8s; }
        .lt-lights i:nth-child(4n+4) { background: #42a5f5; color: #42a5f5; animation-delay: 1.2s; }
        @keyframes ltTwinkle { 0%,100% { box-shadow: 0 0 8px 2px currentColor; opacity: 1; } 50% { box-shadow: none; opacity: .35; } }

        #loginFx { position: fixed; inset: 0; pointer-events: none; z-index: 1; overflow: hidden; }
        #loginFx .p { position: absolute; top: -40px; will-change: transform; animation: ltFall linear infinite; }
        #loginFx .p.rise { top: auto; bottom: -40px; animation-name: ltRise; }
        @keyframes ltFall { 0% { transform: translate3d(0,-40px,0) rotate(0); } 100% { transform: translate3d(var(--sway),110vh,0) rotate(var(--spin)); } }
        @keyframes ltRise { 0% { transform: translate3d(0,0,0) scale(.8); opacity: 0; } 10% { opacity: .9; } 100% { transform: translate3d(var(--sway),-110vh,0) scale(1.1); opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { #loginFx { display: none; } .lt-lights i, .lt-badge { animation: none; } }
    </style>
<?php endif; ?>
</head>
<body onload="startTime()">
<?php if ($ltPreviewName !== null): ?>
    <div class="lt-preview-bar"><i class="fa-solid fa-eye"></i> Preview of "<?php echo $lte($ltPreviewName); ?>" — users only see it on its scheduled dates. <a href="logintheme">Back to Login Theme</a></div>
<?php endif; ?>
<?php if ($lt && $lt['effect']): ?>
    <div id="loginFx" data-fx="<?php echo $lte(json_encode($lt['effect'])); ?>" aria-hidden="true"></div>
<?php endif; ?>

    <div class="wd-login<?php echo $ltThemed ? ' lt-themed' : ''; ?>">
<?php if ($lt): ?>
      <div class="lt-stack">
<?php if ($lt['announcement']): ?>
        <div class="lt-announce" role="status"><span class="ico"><i class="fa-solid fa-bullhorn"></i></span><span><?php echo $lte($lt['announcement']); ?></span></div>
<?php endif; ?>
<?php endif; ?>
        <div class="wd-login__card">
<?php if ($lt && $lt['lights']): ?>
            <div class="lt-lights" aria-hidden="true"><?php echo str_repeat('<i></i>', 22); ?></div>
<?php endif; ?>
<?php if ($ltThemed && $lt['banner_url']): ?>
            <div class="lt-banner" style="background-image:url('<?php echo $lte($lt['banner_url']); ?>')"></div>
<?php endif; ?>
<?php if ($ltThemed): ?>
            <span class="lt-logo">
                <img src="assets/images/logos/wedo-logo.png" class="wd-login__brand" alt="WeDo BPO Inc." style="height:62px;width:auto;margin:0 auto 18px;display:block">
<?php if ($lt['badge']): ?>
                <span class="lt-badge" aria-hidden="true"><i class="fa-solid <?php echo $lt['badge']; ?>"></i></span>
<?php endif; ?>
            </span>
            <div class="lt-greet">
<?php if ($lt['season_label']): ?>
                <div><span class="lt-pill"><?php if ($lt['pill_icon']): ?><i class="fa-solid <?php echo $lt['pill_icon']; ?>"></i><?php endif; ?> <?php echo $lte($lt['season_label']); ?></span></div>
<?php endif; ?>
                <div class="lt-title"><?php echo $lte($lt['headline']); ?> <span class="hl"><?php echo $lte($lt['accent']); ?></span></div>
<?php if ($lt['message']): ?>
                <p class="lt-msg"><?php echo $lte($lt['message']); ?></p>
<?php endif; ?>
            </div>
<?php else: ?>
            <img src="assets/images/logos/wedo-logo.png" class="wd-login__brand" alt="WeDo BPO Inc." style="height:62px;width:auto;margin:0 auto 18px;display:block">
<?php endif; ?>
            <h2>Sign in</h2>
            <p class="wd-login__sub">Secure access for WeDo BPO personnel</p>

            <h6 class="lg-warning" id="error-msg" style="display:none;background:#dc3545;color:#fff;padding:11px;border-radius:12px;font-size:13px;margin:0 0 16px">Incorrect credentials</h6>

            <form class="loginform">
                <div class="wd-field" style="text-align:left">
                    <input class="wd-input" type="text" name="uname" id="uname" placeholder="Username" autocomplete="off">
                </div>
                <div class="wd-field" style="text-align:left;margin-bottom:8px">
                    <input class="wd-input" type="password" name="pass" id="pass" placeholder="Password">
                </div>
                <label class="wd-login__chk"><input type="checkbox" id="showPass" onclick="togglePass()"> Show password</label>

                <div class="wd-field" style="margin-bottom:18px">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACayjoaxKu_lYec-"></div>
                </div>

                <button type="button" class="wd-btn wd-btn--primary btnsubmit" style="width:100%;justify-content:center">Sign in</button>
            </form>
        </div>
<?php if ($lt): ?>
      </div><!-- /.lt-stack -->
<?php endif; ?>

        <div class="wd-clock">
            <div class="wd-clock__date" id="dtnow">Loading…</div>
            <div class="wd-clock__time" id="hr-mn">00:00</div>
            <div class="wd-clock__sec" id="sec">:00 AM</div>
        </div>
    </div>

    <script>
        // Toggle password visibility (kept from original)
        function togglePass() {
            var x = document.getElementById("pass");
            x.type = x.type === "password" ? "text" : "password";
        }
    </script>
<?php if ($lt && $lt['effect']): ?>
    <script src="assets/js/login-theme.js"></script>
<?php endif; ?>
</body>
</html>
