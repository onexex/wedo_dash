<?php
/* ==========================================================================
   account-gate.php  —  resigned / terminated employees can't use WeDo.

   The rule is the employee status (employees.EmpStatusID, Maintenance >
   Employee Status, table estatus): Resigned (2) and Terminated (4) are
   blocked. The resignation DATE is not used — active staff have one too.
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
       (includes/wd-header.php, includes/header.php), at most once a minute
     - HR setting the status (query/Query-updateEmployee.php) signs every
       device out at once and stops push notifications (wd_account_revoke)
   ========================================================================== */

const WD_BLOCKED_STATUSES = [2, 4];   // estatus.ID: Resigned, Terminated
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
 * For a signed-in request: if the account has since been blocked, sign it out everywhere and send it
 * to the sign-in page ("This account is no longer active"). Re-checked at most every WD_GATE_EVERY
 * seconds per session. A database error never signs anyone out.
 */
function wd_account_gate(?PDO $pdo = null): void
{
    if (empty($_SESSION['id']) || $_SESSION['id'] == "0") { return; }
    $now = time();
    if (isset($_SESSION['wd_gate_at']) && $now - (int) $_SESSION['wd_gate_at'] < WD_GATE_EVERY) { return; }
    try {
        if (!$pdo) {
            $pdo = new PDO("mysql:host={$GLOBALS['servername']};dbname={$GLOBALS['db']}", $GLOBALS['username'], $GLOBALS['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $blocked = wd_account_blocked($pdo, $_SESSION['id']);
    } catch (Throwable $e) {
        error_log('[wedo account-gate] check: ' . $e->getMessage());
        return;
    }
    if (!$blocked) { $_SESSION['wd_gate_at'] = $now; return; }

    wd_account_revoke($pdo, $_SESSION['id']);
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
    if (!headers_sent()) {
        $secure = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
        foreach (['WeDoID', 'WeDoApp'] as $cookie) {
            setcookie($cookie, '', ['expires' => $now - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
        }
        header('Location: login.php?inactive=1');
    } else {
        echo '<script>location.replace("login.php?inactive=1")</script>';
    }
    exit;
}
