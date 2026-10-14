<?php
/* ==========================================================================
   change-password.php  —  "Set your own password" after a temporary one.
   Every page sends the account here while empdetails.must_change_pw = 1
   (includes/account-gate.php wd_password_gate): after Applicants > Hire,
   Enroll Employee or an admin password reset. Posts to
   query/force-password.php. Also reachable any time to change a password.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }
include 'w_conn.php';
require_once __DIR__ . '/includes/account-gate.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$forced = false;
try {
    $pwpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pwpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (wd_gate_signed_out($pwpdo)) { header('Location: login.php?inactive=1'); exit(); }
    $forced = wd_must_change_password($pwpdo, $_SESSION['id']);
} catch (Throwable $e) { $forced = false; }
if (empty($_SESSION['pwc_csrf'])) { $_SESSION['pwc_csrf'] = bin2hex(random_bytes(16)); }
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Set your password | WeDo</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="assets/images/logos/wedo-favicon.png">
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/lifecycle.css?v=<?php echo $v('assets/css/lifecycle.css'); ?>">
</head>
<body>
    <div class="wd-login">
        <div class="wd-login__card">
            <img src="assets/images/logos/wedo-logo.png" alt="WeDo BPO Inc." style="height:56px;width:auto;margin:0 auto 18px;display:block">
            <h2><?php echo $forced ? 'Set your own password' : 'Change your password'; ?></h2>
            <p class="wd-login__sub"><?php echo $forced
                ? 'You signed in with a temporary password. Choose your own before you continue.'
                : 'Choose a new password for your WeDo account.'; ?></p>
            <form id="pwForm" autocomplete="off" novalidate>
                <input type="hidden" name="token" value="<?php echo $e($_SESSION['pwc_csrf']); ?>">
                <div class="wd-field"><input type="password" class="wd-input" name="current" placeholder="Current (temporary) password" autocomplete="current-password" required></div>
                <div class="wd-field"><input type="password" class="wd-input" name="new" placeholder="New password" autocomplete="new-password" required minlength="8"></div>
                <div class="wd-field"><input type="password" class="wd-input" name="confirm" placeholder="Type the new password again" autocomplete="new-password" required></div>
                <p class="wd-login__sub" style="margin:0 0 14px;font-size:12px">At least 8 characters, with letters and numbers. Not your username.</p>
                <p class="lcy-modal-error" id="pwErr" role="alert" style="color:#ffb4ab"></p>
                <button type="submit" class="wd-btn wd-btn--primary" id="pwSave">Save password</button>
            </form>
            <a href="login.php?logout" style="display:block;margin-top:14px;font-size:13px;color:rgba(255,255,255,.6)">Sign out</a>
        </div>
    </div>
    <script src="assets/js/change-password.js?v=<?php echo $v('assets/js/change-password.js'); ?>"></script>
</body>
</html>
