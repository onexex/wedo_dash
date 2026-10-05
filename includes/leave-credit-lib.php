<?php
/* ==========================================================================
   leave-credit-lib.php  —  rules for Modules > Leave Credit Viewer.
   credit.CTH = the employee's leave credit for the year (it changes yearly),
   credit.CT  = what is left of it (approvals deduct, Query-UpdateApp.php).
   Starting a new leave year sets both to the new year's leave credit; unused
   days are NOT carried over. Writes: query/leavecredit-action.php.
   Tables: sql/2026-10-05-add-leave-credit-years.sql.
   ========================================================================== */

/** Highest leave credit accepted for one employee in one year. */
const LC_MAX_CREDIT = 365;

function lc_right(PDO $pdo, $col) {
    if (empty($_SESSION['id']) || $_SESSION['id'] == '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT `$col` FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $_SESSION['id']]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;
    }
}

/** May set leave credits and start a new leave year. */
function lc_can_manage(PDO $pdo) { return lc_right($pdo, 'lcreditedit'); }

/** May open the Leave Credit Viewer (managers always may). */
function lc_can_view(PDO $pdo) { return lc_right($pdo, 'lcreaditview') || lc_can_manage($pdo); }

/** Per-session token for the leave credit write endpoint. */
function lc_csrf_token() {
    if (empty($_SESSION['lc_csrf'])) { $_SESSION['lc_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['lc_csrf'];
}

/** A credit amount typed by HR -> float, or null when it isn't 0..LC_MAX_CREDIT (up to 4 decimals). */
function lc_amount($v) {
    $v = trim((string) $v);
    if (!preg_match('/^\d{1,3}(\.\d{1,4})?$/', $v)) { return null; }
    $f = (float) $v;
    return $f <= LC_MAX_CREDIT ? $f : null;
}

/** The started-year record for $year, or null if that leave year hasn't been started. */
function lc_year_record(PDO $pdo, $year) {
    $st = $pdo->prepare("SELECT y.*, CONCAT(e.EmpFN, ' ', e.EmpLN) AS started_name
                         FROM credit_years y LEFT JOIN employees e ON e.EmpID = y.started_by
                         WHERE y.leave_year = :y");
    $st->execute([':y' => (int) $year]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Active employees that have a credit row: EmpID, EmpFN, EmpLN, CT, CTH (by last name). */
function lc_rows(PDO $pdo) {
    return $pdo->query("SELECT e.EmpID, e.EmpFN, e.EmpLN, c.CT, c.CTH
                        FROM employees e JOIN credit c ON c.EmpID = e.EmpID
                        WHERE e.EmpStatusID = 1
                        ORDER BY e.EmpLN, e.EmpFN")->fetchAll(PDO::FETCH_ASSOC);
}

/** Active employees with no credit row yet (they can't be paid for leave until one is added). */
function lc_missing(PDO $pdo) {
    return $pdo->query("SELECT e.EmpID, e.EmpFN, e.EmpLN
                        FROM employees e LEFT JOIN credit c ON c.EmpID = e.EmpID
                        WHERE e.EmpStatusID = 1 AND c.EmpID IS NULL
                        ORDER BY e.EmpLN, e.EmpFN")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Leave filings dated before $year that are still awaiting approval (1 = with
 * supervisor, 2 = with HR). Approving them after the new year starts takes the
 * days out of the NEW year's credit, so HR should clear them first.
 */
function lc_pending_before(PDO $pdo, $year) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM hleaves WHERE LStatus IN (1,2) AND LStart < :d");
    $st->execute([':d' => sprintf('%04d-01-01', (int) $year)]);
    return (int) $st->fetchColumn();
}

function lc_log(PDO $pdo, $emp, $year, $action, $old, $ct, $cth, $actor) {
    $pdo->prepare("INSERT INTO credit_log (EmpID, leave_year, action, old_ct, old_cth, new_ct, new_cth, changed_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$emp, (int) $year, $action, $old['CT'] ?? null, $old['CTH'] ?? null, $ct, $cth, $actor]);
}

/**
 * Start leave year $year: every active employee's credit row becomes
 * CTH = CT = $credits[EmpID] (the new year's leave credit). Unused days from
 * the old year are dropped. $credits must name every active employee with a
 * credit row and nobody else.
 *
 * @return string[] errors ([] = done)
 */
function lc_start_year(PDO $pdo, $year, array $credits, $actor) {
    $year = (int) $year;
    if ($year !== (int) date('Y')) { return ['Only the current year (' . date('Y') . ') can be started.']; }
    if (lc_year_record($pdo, $year)) { return ["The $year leave year has already been started."]; }

    $rows = [];
    foreach (lc_rows($pdo) as $r) { $rows[$r['EmpID']] = $r; }
    if (!$rows) { return ['There are no employees with leave credits.']; }

    $errors = [];
    $new = [];
    foreach ($rows as $id => $r) {
        $name = $r['EmpFN'] . ' ' . $r['EmpLN'];
        if (!array_key_exists($id, $credits)) { $errors[] = "Enter a leave credit for $name."; continue; }
        $amt = lc_amount($credits[$id]);
        if ($amt === null) { $errors[] = "Leave credit for $name must be a number from 0 to " . LC_MAX_CREDIT . '.'; continue; }
        $new[$id] = $amt;
    }
    foreach (array_diff_key($credits, $rows) as $id => $_) {
        $errors[] = 'The employee list changed since you opened this — reload the page and try again.';
        break;
    }
    if ($errors) { return $errors; }

    $pdo->beginTransaction();
    try {
        // the primary key makes a second click (or a second HR user) fail here
        $pdo->prepare("INSERT INTO credit_years (leave_year, started_by, employees) VALUES (?, ?, ?)")
            ->execute([$year, $actor, count($new)]);
        $upd = $pdo->prepare("UPDATE credit SET CT = :c, CTH = :c2 WHERE EmpID = :id");
        foreach ($new as $id => $amt) {
            lc_log($pdo, $id, $year, 'new_year', $rows[$id], $amt, $amt, $actor);
            $upd->execute([':c' => $amt, ':c2' => $amt, ':id' => $id]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') { return ["The $year leave year has already been started."]; }
        throw $e;
    }
    return [];
}

/**
 * Set one active employee's leave credit for the year ($cth) and what is left
 * of it ($ct). Adds the credit row if the employee has none.
 *
 * @return string[] errors ([] = done)
 */
function lc_save(PDO $pdo, $emp, $ct, $cth, $actor) {
    $st = $pdo->prepare("SELECT EmpID FROM employees WHERE EmpID = :id AND EmpStatusID = 1");
    $st->execute([':id' => $emp]);
    if (!$st->fetchColumn()) { return ['That employee is not active.']; }

    $cthV = lc_amount($cth);
    $ctV  = lc_amount($ct);
    $errors = [];
    if ($cthV === null) { $errors[] = 'Leave credit must be a number from 0 to ' . LC_MAX_CREDIT . '.'; }
    if ($ctV === null)  { $errors[] = 'Remaining credit must be a number from 0 to ' . LC_MAX_CREDIT . '.'; }
    if (!$errors && $ctV > $cthV) { $errors[] = 'Remaining credit cannot be more than the leave credit for the year.'; }
    if ($errors) { return $errors; }

    $year = (int) date('Y');
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT CT, CTH FROM credit WHERE EmpID = :id FOR UPDATE");
        $st->execute([':id' => $emp]);
        $old = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($old) {
            $pdo->prepare("UPDATE credit SET CT = ?, CTH = ? WHERE EmpID = ?")->execute([$ctV, $cthV, $emp]);
        } else {
            $pdo->prepare("INSERT INTO credit (EmpID, CT, CTH) VALUES (?, ?, ?)")->execute([$emp, $ctV, $cthV]);
        }
        lc_log($pdo, $emp, $year, $old ? 'edit' : 'add', $old, $ctV, $cthV, $actor);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') { return ['Someone else just added this employee — reload the page.']; }
        throw $e;
    }
    return [];
}
