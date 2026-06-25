<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    include 'w_conn.php';

    // 1. Handle Logout immediately
    if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    setcookie('WeDoID', '', time() - 3600, '/');
    header('location: login.php');
    exit();
    }

    // 2. If session already exists, redirect to index
    if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
    header('location: index.php');
    exit();
    }

    if (isset($_COOKIE["WeDoID"])) {
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
            if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])) {

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
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <!-- Functional scripts (unchanged) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="assets/js/logintime.js"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body onload="startTime()">

    <div class="wd-login">
        <div class="wd-login__card">
            <img src="assets/images/logos/wedo-logo.png" class="wd-login__brand" alt="WeDo BPO Inc." style="height:62px;width:auto;margin:0 auto 18px;display:block">
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
</body>
</html>
