<?php
/* ==========================================================================
   account-gate.php  —  resigned / terminated employees can't use WeDo.

   The rule is the employee status (employees.EmpStatusID, Maintenance >
   Employee Status, table estatus): Resigned (2), Terminated (4) and AWOL (5,
   sql/2026-10-14-add-employee-lifecycle.sql) are blocked. The resignation DATE is not used — active staff have one too.
   Accounts without an employees row (system accounts) are never blocked.

   Exception: the access right `aftersep` — "Sign in after resignation /
   termination" (Access Rights > Others, sql/2026-10-14-add-after-separation-
   access-right.sql) — e.g. someone who still prepares payroll after leaving.
   Until that migration runs there are no exceptions.

   Where it applies:
     - sign-in (query/query-login.php, query/Query-queslogin.php)
     - both "remember me" tokens: the website's WeDoID cookie (login.php,
       index.php) and the app's 30-day token (includes/app-remember.php)
     - someone already signed in: wd_account_gate() on every page
       (includes/wd-header.php, includes/header.php) and wd_account_gate_api()
       on the background requests every open page makes (query/Query-calls.php
       polls every few seconds; query/Query-messages.php) — at most once a
       minute per session. The API answer carries "X-WeDo-Inactive: 1", which
       the script in includes/wd-footer.php turns into the sign-in page.
     - HR setting the status (query/Query-updateEmployee.php) signs every
       device out at once and stops push notifications (wd_account_revoke)
   ========================================================================== */

const WD_BLOCKED_STATUSES = [2, 4, 5];   // estatus.ID: Resigned, Terminated, AWOL
const WD_GATE_EVERY       = 60;       // seconds between re-checks of a signed-in session

function wd_status_blocked($statusId): bool
{
    return $statusId !== null && $statusId !== '' && in_array((int) $statusId, WD_BLOCKED_STATUSES, true);
}

/** Which of these EmpIDs may not use WeDo (blocked status, no `aftersep` exception). */
function wd_blocked_among(PDO $pdo, array $empIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $empIds), 'strlen')));
    if (!$ids) { return []; }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = implode(',', array_map('intval', WD_BLOCKED_STATUSES));
    try {
        $q = $pdo->prepare("SELECT DISTINCT e.EmpID FROM employees e
                            LEFT JOIN accessrights ar ON ar.EmpID = e.EmpID
                            WHERE e.EmpID IN ($in) AND e.EmpStatusID IN ($st) AND COALESCE(ar.aftersep, 1) <> 2");
        $q->execute($ids);
    } catch (PDOException $e) {   // accessrights.aftersep not created yet on this server: no exceptions
        $q = $pdo->prepare("SELECT EmpID FROM employees WHERE EmpID IN ($in) AND EmpStatusID IN ($st)");
        $q->execute($ids);
    }
    return array_map('strval', $q->fetchAll(PDO::FETCH_COLUMN));
}

function wd_account_blocked(PDO $pdo, $empId): bool
{
    return (string) $empId !== '' && in_array((string) $empId, wd_blocked_among($pdo, [$empId]), true);
}

/** Sign the employee out everywhere: website "remember me", every phone's app token, push notifications. */
function wd_account_revoke(PDO $pdo, $empId): void
{
    foreach (["UPDATE empdetails SET remember_hash = NULL, remember_expiry = NULL WHERE EmpID = ?",
              "DELETE FROM app_remember WHERE EmpID = ?",
              "DELETE FROM push_devices WHERE EmpID = ?"] as $sql) {
        try { $pdo->prepare($sql)->execute([(string) $empId]); }
        catch (Throwable $e) { error_log('[wedo account-gate] revoke: ' . $e->getMessage()); }   // e.g. table not on this server
    }
}

/**
 * Signed-in request: has the account been blocked since? Re-checked at most every WD_GATE_EVERY
 * seconds per session (needs the session still open for writing). If it has, it is signed out
 * everywhere (tokens, push, this session, its cookies) and true is returned. A database error
 * never signs anyone out.
 */
function wd_gate_signed_out(?PDO $pdo = null): bool
{
    if (empty($_SESSION['id']) || $_SESSION['id'] == "0") { return false; }
    $now = time();
    if (isset($_SESSION['wd_gate_at']) && $now - (int) $_SESSION['wd_gate_at'] < WD_GATE_EVERY) { return false; }
    try {
        if (!$pdo) {
            $pdo = new PDO("mysql:host={$GLOBALS['servername']};dbname={$GLOBALS['db']}", $GLOBALS['username'], $GLOBALS['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $blocked = wd_account_blocked($pdo, $_SESSION['id']);
    } catch (Throwable $e) {
        error_log('[wedo account-gate] check: ' . $e->getMessage());
        return false;
    }
    if (!$blocked) { $_SESSION['wd_gate_at'] = $now; return false; }

    wd_account_revoke($pdo, $_SESSION['id']);
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
    if (!headers_sent()) {
        $secure = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
        foreach (['WeDoID', 'WeDoApp'] as $cookie) {
            setcookie($cookie, '', ['expires' => $now - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
        }
    }
    return true;
}

/** Pages: a blocked account goes to the sign-in page ("This account is no longer active"). */
function wd_account_gate(?PDO $pdo = null): void
{
    if (wd_gate_signed_out($pdo)) {
        if (!headers_sent()) { header('Location: login.php?inactive=1'); }
        else { echo '<script>location.replace("login.php?inactive=1")</script>'; }
        exit;
    }
    wd_password_gate($pdo);
}

/* --------------------------------------------------------------------------
   Temporary / default passwords (sql/2026-10-14-add-force-password-change.sql):
   empdetails.must_change_pw = 1 after Applicants > Hire, Enroll Employee or an
   admin password reset. Until the person sets their own password, every page
   sends them to change-password.php. Cleared by any password change.
   -------------------------------------------------------------------------- */

/** A one-time temporary password for HR to hand over (no look-alike characters). */
function wd_temp_password(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $p = '';
    for ($i = 0; $i < 10; $i++) { $p .= $chars[random_int(0, strlen($chars) - 1)]; }
    return $p . random_int(10, 99);
}

/** Must this account replace its password first? False when the column isn't there yet. */
function wd_must_change_password(PDO $pdo, $empId): bool
{
    try {
        $st = $pdo->prepare("SELECT must_change_pw FROM empdetails WHERE EmpID = :id");
        $st->execute([':id' => (string) $empId]);
        return (int) $st->fetchColumn() === 1;
    } catch (Throwable $e) {
        return false;
    }
}

/** Flag (or clear) a forced password change. Silent before the migration has run. */
function wd_set_must_change_password(PDO $pdo, $empId, bool $on): void
{
    try {
        $pdo->prepare("UPDATE empdetails SET must_change_pw = :f WHERE EmpID = :id")->execute([':f' => $on ? 1 : 0, ':id' => (string) $empId]);
    } catch (Throwable $e) { /* column not on this server yet */ }
}

/** Pages: a temporary password goes to change-password.php first (checked until it is changed). */
function wd_password_gate(?PDO $pdo = null): void
{
    if (empty($_SESSION['id']) || $_SESSION['id'] == "0" || !empty($_SESSION['wd_pw_ok'])) { return; }
    try {
        if (!$pdo) {
            $pdo = new PDO("mysql:host={$GLOBALS['servername']};dbname={$GLOBALS['db']}", $GLOBALS['username'], $GLOBALS['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $must = wd_must_change_password($pdo, $_SESSION['id']);
    } catch (Throwable $e) {
        return;
    }
    if (!$must) { $_SESSION['wd_pw_ok'] = 1; return; }
    if (!headers_sent()) { header('Location: change-password.php'); }
    else { echo '<script>location.replace("change-password.php")</script>'; }
    exit;
}

/** JSON endpoints: 401 + "X-WeDo-Inactive: 1" (includes/wd-footer.php then opens the sign-in page). */
function wd_account_gate_api(?PDO $pdo = null): void
{
    if (!wd_gate_signed_out($pdo)) { return; }
    if (!headers_sent()) {
        http_response_code(401);
        header('X-WeDo-Inactive: 1');
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['status' => 'error', 'inactive' => true, 'redirect' => 'login.php?inactive=1',
                      'msg' => 'This account is no longer active. Please contact HR.']);
    exit;
}
