<?php
/* ==========================================================================
   lifecycle-lib.php  —  rules for the employee lifecycle:
     - separation (Resigned / Terminated / AWOL) with last day, reason and a
       rehire flag                                         separation.php
     - lifecycle history (hired, Trainee -> IC, status changes, separation,
       COEs issued)                                        separation.php
     - COE requests: employees ask, `coefunction` users approve; HR logs
       requests for people who can no longer sign in      coe.php
     - employment verification (CI): look a person up in WeDo and in the
       legacy AMS archive, log every call                  verify.php
   Writes: query/lifecycle-action.php.  Tables: sql/2026-10-14-add-employee-lifecycle.sql.
   ========================================================================== */
date_default_timezone_set("Asia/Manila");   // last days, control numbers and history dates are Manila dates

/** Separation type -> employees.EmpStatusID (estatus.ID). */
const LCY_SEP_STATUS = ['resigned' => 2, 'terminated' => 4, 'awol' => 5];
const LCY_SEP_LABEL  = ['resigned' => 'Resigned', 'terminated' => 'Terminated', 'awol' => 'AWOL'];
const LCY_REHIRE     = ['yes', 'no', 'review'];
const LCY_PURPOSE    = ['ci' => 'Credit / character investigation', 'background' => 'Background check',
                        'loan' => 'Loan / bank verification', 'other' => 'Other'];
const LCY_DISCLOSE   = ['dates' => 'Employment dates', 'position' => 'Position', 'status' => 'Separation / status',
                        'salary' => 'Salary', 'rehire' => 'Rehire eligibility'];

/* ------------------------------------------------------------------ access */

function lcy_right(PDO $pdo, $col) {
    if (empty($_SESSION['id']) || $_SESSION['id'] == '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT `$col` FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $_SESSION['id']]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;   // column missing = migration not run = no access
    }
}

/** Management > Separation & Lifecycle. */
function lcy_can_separate(PDO $pdo) { return lcy_right($pdo, 'separation'); }

/** Management > Employment Verification (CI). */
function lcy_can_verify(PDO $pdo) { return lcy_right($pdo, 'empverify'); }

/** Approves COE requests and issues COEs for anyone (the existing COE right). */
function lcy_can_issue_coe(PDO $pdo) { return lcy_right($pdo, 'coefunction'); }

/** Per-session token for query/lifecycle-action.php. */
function lcy_csrf_token() {
    if (empty($_SESSION['lcy_csrf'])) { $_SESSION['lcy_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['lcy_csrf'];
}

/** Has the migration run on this server? */
function lcy_ready(PDO $pdo) {
    try {
        $pdo->query("SELECT 1 FROM emp_separations LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/* ------------------------------------------------------------------ helpers */

/** 'YYYY-MM-DD' that is a real date, else null. */
function lcy_date($v) {
    $v = trim((string) $v);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) { return null; }
    return $v;
}

function lcy_text($v, $max) {
    $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
    return mb_substr($v, 0, $max);
}

function lcy_is_separated($statusId) {
    return in_array((int) $statusId, LCY_SEP_STATUS, true);
}

/** estatus.ID -> separation type (null for Employed / anything else). */
function lcy_type_of_status($statusId) {
    $t = array_search((int) $statusId, LCY_SEP_STATUS, true);
    return $t === false ? null : $t;
}

function lcy_audit(PDO $pdo, $text) {
    try {
        $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :act, NOW())")
            ->execute([':id' => $_SESSION['id'] ?? '', ':act' => mb_substr($text, 0, 250)]);
    } catch (Throwable $e) { /* audit is best-effort, never blocks the change */ }
}

function lcy_status_names(PDO $pdo) {
    return $pdo->query("SELECT ID, StatusEmpDesc FROM estatus")->fetchAll(PDO::FETCH_KEY_PAIR);
}

function lcy_class_names(PDO $pdo) {
    return $pdo->query("SELECT EmpStatID, EmpStatDesc FROM empstatus")->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** One employee (any status) with what the lifecycle screens show, or null. */
function lcy_employee(PDO $pdo, $empId) {
    $st = $pdo->prepare("SELECT e.EmpID, e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, e.EmpStatusID,
            s.StatusEmpDesc, d.EmpStatID, c.EmpStatDesc AS ClassDesc, c.EmpCODE AS ClassCode,
            d.EmpDateHired, d.EmpDateResigned, p.PositionDesc,
            (SELECT pr.EmpGender FROM empprofiles pr WHERE pr.EmpID = e.EmpID LIMIT 1) AS EmpGender
        FROM employees e
        LEFT JOIN empdetails d ON d.EmpID = e.EmpID
        LEFT JOIN estatus s    ON s.ID = e.EmpStatusID
        LEFT JOIN empstatus c  ON c.EmpStatID = d.EmpStatID
        LEFT JOIN positions p  ON p.PSID = e.PosID
        WHERE e.EmpID = :id LIMIT 1");
    $st->execute([':id' => (string) $empId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { return null; }
    return [
        'empId'      => $r['EmpID'],
        'name'       => lcy_full_name($r),
        'first'      => trim((string) $r['EmpFN']),
        'last'       => trim((string) $r['EmpLN']),
        'position'   => (string) $r['PositionDesc'],
        'statusId'   => (int) $r['EmpStatusID'],
        'status'     => (string) ($r['StatusEmpDesc'] ?? ''),
        'separated'  => lcy_is_separated($r['EmpStatusID']),
        'classId'    => (int) $r['EmpStatID'],
        'classDesc'  => (string) ($r['ClassDesc'] ?? ''),
        'classCode'  => (string) ($r['ClassCode'] ?? ''),
        'hired'      => lcy_date($r['EmpDateHired']),
        'resigned'   => lcy_date($r['EmpDateResigned']),
        'gender'     => (string) $r['EmpGender'],
    ];
}

function lcy_full_name(array $r) {
    $parts = [trim((string) ($r['EmpFN'] ?? '')), trim((string) ($r['EmpMN'] ?? '')), trim((string) ($r['EmpLN'] ?? ''))];
    $name = trim(implode(' ', array_filter($parts, 'strlen')));
    $suffix = trim((string) ($r['EmpSuffix'] ?? ''));
    return $suffix !== '' ? $name . ' ' . $suffix : $name;
}

/**
 * Employees of any status whose name or EmpID matches every word of $q.
 * @return array<int, array{empId:string, name:string, position:string, statusId:int, status:string}>
 */
function lcy_find_employees(PDO $pdo, $q, $limit = 15) {
    $words = array_slice(array_filter(preg_split('/\s+/u', trim((string) $q)), 'strlen'), 0, 4);
    if (!$words) { return []; }
    $where = [];
    $args  = [];
    foreach ($words as $i => $w) {
        $where[] = "(e.EmpFN LIKE :w{$i}a OR e.EmpLN LIKE :w{$i}b OR e.EmpMN LIKE :w{$i}c OR e.EmpID LIKE :w{$i}d)";
        foreach (['a', 'b', 'c', 'd'] as $k) { $args[":w{$i}{$k}"] = '%' . addcslashes($w, '%_\\') . '%'; }
    }
    $sql = "SELECT e.EmpID, e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, e.EmpStatusID, s.StatusEmpDesc, p.PositionDesc
            FROM employees e
            LEFT JOIN estatus s   ON s.ID = e.EmpStatusID
            LEFT JOIN positions p ON p.PSID = e.PosID
            WHERE e.EmpID <> 'admin' AND " . implode(' AND ', $where) . "
            ORDER BY e.EmpLN, e.EmpFN LIMIT " . (int) $limit;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['empId' => $r['EmpID'], 'name' => lcy_full_name($r), 'position' => (string) $r['PositionDesc'],
                  'statusId' => (int) $r['EmpStatusID'], 'status' => (string) ($r['StatusEmpDesc'] ?? '')];
    }
    return $out;
}

/* ------------------------------------------------------------------ history */

function lcy_log_event(PDO $pdo, $empId, $type, $from = null, $to = null, $date = null, $notes = null, $by = null) {
    $pdo->prepare("INSERT INTO emp_lifecycle_events (EmpID, EventType, FromVal, ToVal, EffectiveDate, Notes, ByEmpID)
                   VALUES (:e, :t, :f, :to, :d, :n, :b)")
        ->execute([':e' => (string) $empId, ':t' => $type,
                   ':f' => $from === null ? null : mb_substr((string) $from, 0, 100),
                   ':to' => $to === null ? null : mb_substr((string) $to, 0, 100),
                   ':d' => $date, ':n' => $notes === null ? null : mb_substr((string) $notes, 0, 500),
                   ':b' => $by ?? ($_SESSION['id'] ?? null)]);
}

/** Status + classification right now — taken before Update Employee Information saves. */
function lcy_snapshot(PDO $pdo, $empId) {
    $st = $pdo->prepare("SELECT e.EmpStatusID, d.EmpStatID FROM employees e
                         LEFT JOIN empdetails d ON d.EmpID = e.EmpID WHERE e.EmpID = :id LIMIT 1");
    $st->execute([':id' => (string) $empId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? ['status' => (int) $r['EmpStatusID'], 'class' => (int) $r['EmpStatID']] : null;
}

/**
 * After HR saved Update Employee Information: record a classification change
 * (e.g. Trainee -> Independent Contractor) and a status change. A new
 * separation made there gets a minimal separation record (last day = today,
 * rehire = review) for HR to finish on the Separation & Lifecycle page.
 */
function lcy_after_employee_update(PDO $pdo, $empId, ?array $before, $actor) {
    if (!$before || !lcy_ready($pdo)) { return; }
    $now = lcy_snapshot($pdo, $empId);
    if (!$now) { return; }
    $today = date('Y-m-d');

    if ($now['class'] !== $before['class']) {
        $names = lcy_class_names($pdo);
        lcy_log_event($pdo, $empId, 'classification', $names[$before['class']] ?? (string) $before['class'],
                      $names[$now['class']] ?? (string) $now['class'], $today, 'Update Employee Information', $actor);
    }
    if ($now['status'] === $before['status']) { return; }

    $names = lcy_status_names($pdo);
    $from  = $names[$before['status']] ?? (string) $before['status'];
    $to    = $names[$now['status']] ?? (string) $now['status'];
    $type  = lcy_type_of_status($now['status']);

    if ($type !== null && !lcy_is_separated($before['status'])) {
        $pdo->prepare("INSERT INTO emp_separations (EmpID, Type, LastDay, RehireEligible, RecordedBy)
                       VALUES (:e, :t, :d, 'review', :b)")
            ->execute([':e' => (string) $empId, ':t' => $type, ':d' => $today, ':b' => $actor]);
        lcy_log_event($pdo, $empId, 'separated', $from, $to, $today,
                      'Set in Update Employee Information — finish the separation record', $actor);
        return;
    }
    if ($type !== null) {   // Resigned -> Terminated etc.: the latest record follows
        $pdo->prepare("UPDATE emp_separations SET Type = :t WHERE EmpID = :e ORDER BY LastDay DESC, id DESC LIMIT 1")
            ->execute([':t' => $type, ':e' => (string) $empId]);
    }
    lcy_log_event($pdo, $empId, 'status', $from, $to, $today, null, $actor);
}

/** Separation records (newest first). $empId null = everyone, newest 50. */
function lcy_separations(PDO $pdo, $empId = null, $onlyOpen = false) {
    $where = [];
    $args  = [];
    if ($empId !== null) { $where[] = 's.EmpID = :e'; $args[':e'] = (string) $empId; }
    if ($onlyOpen) { $where[] = "(s.RehireEligible = 'review' OR s.Reason IS NULL OR s.Reason = '')"; }
    $sql = "SELECT s.*, e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, p.PositionDesc
            FROM emp_separations s
            LEFT JOIN employees e ON e.EmpID = s.EmpID
            LEFT JOIN positions p ON p.PSID = e.PosID"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY s.LastDay DESC, s.id DESC LIMIT 50";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return array_map('lcy_separation_out', $st->fetchAll(PDO::FETCH_ASSOC));
}

function lcy_separation_out(array $r) {
    return [
        'id' => (int) $r['id'], 'empId' => $r['EmpID'], 'name' => lcy_full_name($r),
        'position' => (string) ($r['PositionDesc'] ?? ''),
        'type' => $r['Type'], 'typeLabel' => LCY_SEP_LABEL[$r['Type']] ?? $r['Type'],
        'lastDay' => $r['LastDay'], 'reason' => (string) $r['Reason'],
        'rehire' => $r['RehireEligible'], 'rehireNote' => (string) $r['RehireNote'],
        'clearance' => (int) $r['ClearanceDone'] === 1, 'recordedAt' => $r['RecordedAt'],
    ];
}

function lcy_latest_separation(PDO $pdo, $empId) {
    $list = lcy_separations($pdo, $empId);
    return $list ? $list[0] : null;
}

/**
 * Record a separation: status Resigned/Terminated/AWOL, last day, separation
 * record, history, then sign the person out everywhere.
 * @return array{ok:bool, msg?:string, id?:int}
 */
function lcy_separate(PDO $pdo, $empId, $type, $lastDay, $reason, $rehire, $rehireNote, $clearance, $actor) {
    $emp = lcy_employee($pdo, $empId);
    if (!$emp) { return ['ok' => false, 'msg' => 'That employee no longer exists.']; }
    if (!isset(LCY_SEP_STATUS[$type])) { return ['ok' => false, 'msg' => 'Choose Resigned, Terminated or AWOL.']; }
    $lastDay = lcy_date($lastDay);
    if ($lastDay === null) { return ['ok' => false, 'msg' => 'Enter the last day worked.']; }
    if ($emp['hired'] && $lastDay < $emp['hired']) { return ['ok' => false, 'msg' => 'The last day is before the hire date.']; }
    if (!in_array($rehire, LCY_REHIRE, true)) { return ['ok' => false, 'msg' => 'Choose whether this person may be rehired.']; }
    $reason = lcy_text($reason, 500);
    if ($reason === '') { return ['ok' => false, 'msg' => 'Enter the reason for leaving.']; }
    if ($emp['separated']) {
        return ['ok' => false, 'msg' => $emp['name'] . ' is already ' . $emp['status'] . ' — edit the existing separation record instead.'];
    }

    $statusId = LCY_SEP_STATUS[$type];
    $names = lcy_status_names($pdo);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE employees SET EmpStatusID = :s WHERE EmpID = :e")->execute([':s' => $statusId, ':e' => $emp['empId']]);
        $pdo->prepare("UPDATE empdetails SET EmpDateResigned = :d WHERE EmpID = :e")->execute([':d' => $lastDay, ':e' => $emp['empId']]);
        $pdo->prepare("INSERT INTO emp_separations (EmpID, Type, LastDay, Reason, RehireEligible, RehireNote, ClearanceDone, RecordedBy)
                       VALUES (:e, :t, :d, :r, :h, :hn, :c, :b)")
            ->execute([':e' => $emp['empId'], ':t' => $type, ':d' => $lastDay, ':r' => $reason, ':h' => $rehire,
                       ':hn' => lcy_text($rehireNote, 500) ?: null, ':c' => $clearance ? 1 : 0, ':b' => $actor]);
        $id = (int) $pdo->lastInsertId();
        lcy_log_event($pdo, $emp['empId'], 'separated', $emp['status'], $names[$statusId] ?? LCY_SEP_LABEL[$type], $lastDay, $reason, $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    require_once __DIR__ . '/account-gate.php';
    if (wd_account_blocked($pdo, $emp['empId'])) { wd_account_revoke($pdo, $emp['empId']); }
    lcy_audit($pdo, 'Separation: ' . $emp['name'] . ' (' . $emp['empId'] . ') ' . LCY_SEP_LABEL[$type] . ', last day ' . $lastDay);
    return ['ok' => true, 'id' => $id];
}

/** Edit a separation record (reason, last day, rehire flag, clearance). The type follows the status. */
function lcy_update_separation(PDO $pdo, $id, $lastDay, $reason, $rehire, $rehireNote, $clearance, $actor) {
    $st = $pdo->prepare("SELECT * FROM emp_separations WHERE id = :id");
    $st->execute([':id' => (int) $id]);
    $old = $st->fetch(PDO::FETCH_ASSOC);
    if (!$old) { return ['ok' => false, 'msg' => 'That separation record no longer exists.']; }
    $lastDay = lcy_date($lastDay);
    if ($lastDay === null) { return ['ok' => false, 'msg' => 'Enter the last day worked.']; }
    if (!in_array($rehire, LCY_REHIRE, true)) { return ['ok' => false, 'msg' => 'Choose whether this person may be rehired.']; }
    $reason = lcy_text($reason, 500);
    if ($reason === '') { return ['ok' => false, 'msg' => 'Enter the reason for leaving.']; }

    $pdo->prepare("UPDATE emp_separations SET LastDay = :d, Reason = :r, RehireEligible = :h, RehireNote = :hn, ClearanceDone = :c
                   WHERE id = :id")
        ->execute([':d' => $lastDay, ':r' => $reason, ':h' => $rehire, ':hn' => lcy_text($rehireNote, 500) ?: null,
                   ':c' => $clearance ? 1 : 0, ':id' => (int) $id]);
    // the latest separation's last day is the employee's resignation date
    $latest = lcy_latest_separation($pdo, $old['EmpID']);
    if ($latest && $latest['id'] === (int) $id) {
        $pdo->prepare("UPDATE empdetails SET EmpDateResigned = :d WHERE EmpID = :e")->execute([':d' => $lastDay, ':e' => $old['EmpID']]);
    }
    if ($old['RehireEligible'] !== $rehire) {
        $label = ['yes' => 'Eligible for rehire', 'no' => 'Not eligible for rehire', 'review' => 'Rehire: to review'];
        lcy_log_event($pdo, $old['EmpID'], 'rehire_flag', $label[$old['RehireEligible']] ?? $old['RehireEligible'],
                      $label[$rehire], date('Y-m-d'), lcy_text($rehireNote, 500) ?: null, $actor);
    }
    lcy_audit($pdo, 'Separation record updated: ' . $old['EmpID'] . ', rehire ' . $rehire);
    return ['ok' => true];
}

/** Everything the Separation & Lifecycle page shows for one person. */
function lcy_timeline(PDO $pdo, $empId) {
    $emp = lcy_employee($pdo, $empId);
    if (!$emp) { return null; }
    $st = $pdo->prepare("SELECT ev.*, b.EmpFN AS ByFN, b.EmpLN AS ByLN FROM emp_lifecycle_events ev
                         LEFT JOIN employees b ON b.EmpID = ev.ByEmpID
                         WHERE ev.EmpID = :e ORDER BY COALESCE(ev.EffectiveDate, DATE(ev.CreatedAt)), ev.id");
    $st->execute([':e' => $emp['empId']]);
    $events = [];
    $hasHired = false;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $hasHired = $hasHired || $r['EventType'] === 'hired';
        $events[] = ['type' => $r['EventType'], 'from' => $r['FromVal'], 'to' => $r['ToVal'],
                     'date' => $r['EffectiveDate'] ?: substr($r['CreatedAt'], 0, 10), 'notes' => $r['Notes'],
                     'by' => trim(($r['ByFN'] ?? '') . ' ' . ($r['ByLN'] ?? '')), 'at' => $r['CreatedAt']];
    }
    // people enrolled before this module: the hire date stands in for a "hired" event
    if (!$hasHired && $emp['hired']) {
        array_unshift($events, ['type' => 'hired', 'from' => null, 'to' => $emp['classDesc'], 'date' => $emp['hired'],
                                'notes' => null, 'by' => '', 'at' => null]);
    }
    return ['employee' => $emp, 'events' => $events, 'separations' => lcy_separations($pdo, $emp['empId']),
            'coe' => lcy_coe_list($pdo, ['emp' => $emp['empId']])];
}

/* ------------------------------------------------------------------ COE */

/** Monthly basic in words for the certificate: "TWENTY FIVE THOUSAND (Php 25,000.00)". */
function lcy_amount_words($amount) {
    $amount = round((float) $amount, 2);
    $pesos  = (int) floor($amount);
    $cents  = (int) round(($amount - $pesos) * 100);
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
             'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $three = function ($n) use ($ones, $tens) {
        $w = [];
        if ($n >= 100) { $w[] = $ones[intdiv($n, 100)] . ' hundred'; $n %= 100; }
        if ($n >= 20) { $w[] = $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : ''); }
        elseif ($n > 0) { $w[] = $ones[$n]; }
        return implode(' ', $w);
    };
    $scales = ['', ' thousand', ' million', ' billion'];
    $parts = [];
    for ($i = 0, $n = $pesos; $n > 0 && $i < count($scales); $i++, $n = intdiv($n, 1000)) {
        if ($n % 1000) { array_unshift($parts, $three($n % 1000) . $scales[$i]); }
    }
    $words = $parts ? implode(' ', $parts) : 'zero';
    if ($cents > 0) { $words .= ' and ' . sprintf('%02d', $cents) . '/100'; }
    return strtoupper($words) . ' (Php ' . number_format($amount, 2) . ')';
}

function lcy_coe_out(array $r) {
    return [
        'id' => (int) $r['id'], 'empId' => $r['EmpID'], 'name' => lcy_full_name($r),
        'purpose' => $r['Purpose'], 'salary' => (int) $r['IncludeSalary'] === 1, 'channel' => $r['Channel'],
        'status' => $r['Status'], 'requestedAt' => $r['RequestedAt'], 'processedAt' => $r['ProcessedAt'],
        'remarks' => (string) $r['Remarks'], 'controlNo' => (string) $r['ControlNo'], 'printedAt' => $r['PrintedAt'],
        'empStatus' => (string) ($r['StatusEmpDesc'] ?? ''),
    ];
}

/** COE requests. Filters: emp (one person), status. Newest first, at most 100. */
function lcy_coe_list(PDO $pdo, array $f = []) {
    $where = [];
    $args  = [];
    if (!empty($f['emp']))    { $where[] = 'r.EmpID = :e';  $args[':e'] = (string) $f['emp']; }
    if (!empty($f['status'])) { $where[] = 'r.Status = :s'; $args[':s'] = (string) $f['status']; }
    $sql = "SELECT r.*, e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, s.StatusEmpDesc FROM coe_requests r
            LEFT JOIN employees e ON e.EmpID = r.EmpID
            LEFT JOIN estatus s   ON s.ID = e.EmpStatusID"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY (r.Status = 'pending') DESC, r.RequestedAt DESC, r.id DESC LIMIT 100";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return array_map('lcy_coe_out', $st->fetchAll(PDO::FETCH_ASSOC));
}

function lcy_coe_get(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT r.*, e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, s.StatusEmpDesc FROM coe_requests r
                         LEFT JOIN employees e ON e.EmpID = r.EmpID
                         LEFT JOIN estatus s   ON s.ID = e.EmpStatusID WHERE r.id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? lcy_coe_out($r) : null;
}

function lcy_coe_pending_count(PDO $pdo) {
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM coe_requests WHERE Status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Next control number for the year: COE-2026-0001. */
function lcy_coe_next_control(PDO $pdo) {
    $prefix = 'COE-' . date('Y') . '-';
    $st = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(ControlNo, 10) AS UNSIGNED)) FROM coe_requests WHERE ControlNo LIKE :p");
    $st->execute([':p' => $prefix . '%']);
    return $prefix . sprintf('%04d', (int) $st->fetchColumn() + 1);
}

/** Give an approved request its control number (retries if two HR users approve at once). */
function lcy_coe_assign_control(PDO $pdo, $id) {
    for ($try = 0; $try < 5; $try++) {
        try {
            $pdo->prepare("UPDATE coe_requests SET ControlNo = :c WHERE id = :id AND ControlNo IS NULL")
                ->execute([':c' => lcy_coe_next_control($pdo), ':id' => (int) $id]);
            return;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') { throw $e; }
        }
    }
}

/**
 * File a COE request. $byHr = a `coefunction` user logging it (for anyone,
 * e.g. a separated person who can't sign in) — approved at once.
 */
function lcy_coe_request(PDO $pdo, $empId, $purpose, $withSalary, $byHr, $actor) {
    $emp = lcy_employee($pdo, $empId);
    if (!$emp) { return ['ok' => false, 'msg' => 'That employee no longer exists.']; }
    $purpose = lcy_text($purpose, 200);
    if ($purpose === '') { return ['ok' => false, 'msg' => 'Say what the certificate is for (e.g. bank loan, visa).']; }
    if (!$byHr) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM coe_requests WHERE EmpID = :e AND Status = 'pending'");
        $st->execute([':e' => $emp['empId']]);
        if ((int) $st->fetchColumn() >= 3) {
            return ['ok' => false, 'msg' => 'You already have 3 requests waiting — HR will get to them soon.'];
        }
    }
    $pdo->prepare("INSERT INTO coe_requests (EmpID, Purpose, IncludeSalary, Channel, Status, RequestedBy, ProcessedBy, ProcessedAt)
                   VALUES (:e, :p, :s, :c, :st, :b, :pb, :pa)")
        ->execute([':e' => $emp['empId'], ':p' => $purpose, ':s' => $withSalary ? 1 : 0, ':c' => $byHr ? 'hr' : 'self',
                   ':st' => $byHr ? 'approved' : 'pending', ':b' => $actor,
                   ':pb' => $byHr ? $actor : null, ':pa' => $byHr ? date('Y-m-d H:i:s') : null]);
    $id = (int) $pdo->lastInsertId();
    if ($byHr) { lcy_coe_assign_control($pdo, $id); }
    lcy_audit($pdo, 'COE ' . ($byHr ? 'issued for ' : 'requested by ') . $emp['empId'] . ': ' . $purpose);
    return ['ok' => true, 'id' => $id];
}

/** Approve or reject a pending request (`coefunction`). */
function lcy_coe_decide(PDO $pdo, $id, $decision, $remarks, $actor) {
    if (!in_array($decision, ['approved', 'rejected'], true)) { return ['ok' => false, 'msg' => 'Unknown decision.']; }
    $req = lcy_coe_get($pdo, $id);
    if (!$req) { return ['ok' => false, 'msg' => 'That request no longer exists.']; }
    if ($req['status'] !== 'pending') { return ['ok' => false, 'msg' => 'This request was already ' . $req['status'] . '.']; }
    $remarks = lcy_text($remarks, 300);
    if ($decision === 'rejected' && $remarks === '') { return ['ok' => false, 'msg' => 'Give the reason for rejecting.']; }
    $st = $pdo->prepare("UPDATE coe_requests SET Status = :s, Remarks = :r, ProcessedBy = :b, ProcessedAt = NOW()
                         WHERE id = :id AND Status = 'pending'");
    $st->execute([':s' => $decision, ':r' => $remarks ?: null, ':b' => $actor, ':id' => (int) $id]);
    if ($st->rowCount() === 0) { return ['ok' => false, 'msg' => 'Someone else already handled this request.']; }
    if ($decision === 'approved') { lcy_coe_assign_control($pdo, $id); }
    lcy_audit($pdo, 'COE request #' . (int) $id . ' ' . $decision . ' (' . $req['empId'] . ')');
    return ['ok' => true, 'request' => lcy_coe_get($pdo, $id)];
}

/** The employee withdraws their own pending request. */
function lcy_coe_cancel(PDO $pdo, $id, $actor) {
    $st = $pdo->prepare("UPDATE coe_requests SET Status = 'cancelled', ProcessedBy = :b, ProcessedAt = NOW()
                         WHERE id = :id AND EmpID = :e AND Status = 'pending'");
    $st->execute([':b' => $actor, ':id' => (int) $id, ':e' => $actor]);
    return $st->rowCount() > 0 ? ['ok' => true] : ['ok' => false, 'msg' => 'Only your own waiting requests can be cancelled.'];
}

/**
 * Who may see/print request $req: HR (`coefunction`) always; the employee
 * only their own approved one.
 */
function lcy_coe_can_print(PDO $pdo, ?array $req, $viewer) {
    if (!$req || $req['status'] !== 'approved') { return false; }
    return lcy_can_issue_coe($pdo) || (string) $req['empId'] === (string) $viewer;
}

/** The values the certificate template needs for an approved request. */
function lcy_coe_certificate(PDO $pdo, array $req) {
    $emp = lcy_employee($pdo, $req['empId']);
    if (!$emp) { return null; }
    $male = strcasecmp($emp['gender'], 'Male') === 0;
    $lastDay = null;
    if ($emp['separated']) {
        $sep = lcy_latest_separation($pdo, $emp['empId']);
        $lastDay = $sep ? $sep['lastDay'] : $emp['resigned'];
    }
    $fmt = function ($d) { return $d ? date('F j, Y', strtotime($d)) : ''; };
    $salary = null;
    if ($req['salary']) {
        $st = $pdo->prepare("SELECT EmpBasic FROM empdetails2 WHERE EmpID = :e LIMIT 1");
        $st->execute([':e' => $emp['empId']]);
        $basic = $st->fetchColumn();
        $salary = ($basic !== false && (float) $basic > 0) ? lcy_amount_words($basic) : null;
    }
    return [
        'controlNo' => $req['controlNo'], 'fullName' => $emp['name'], 'lastName' => $emp['last'],
        'salutation' => $male ? 'Mr.' : 'Ms.', 'his' => $male ? 'his' : 'her', 'he' => $male ? 'he' : 'she',
        'position' => $emp['position'], 'hired' => $fmt($emp['hired']),
        'separated' => $emp['separated'], 'lastDay' => $fmt($lastDay),
        'salary' => $salary, 'purpose' => $req['purpose'], 'issued' => date('F j, Y'),
    ];
}

/** First print of a request: remember when, and add it to the person's history. */
function lcy_coe_mark_printed(PDO $pdo, array $req, $actor) {
    $st = $pdo->prepare("UPDATE coe_requests SET PrintedAt = NOW() WHERE id = :id AND PrintedAt IS NULL");
    $st->execute([':id' => $req['id']]);
    if ($st->rowCount() > 0) {
        lcy_log_event($pdo, $req['empId'], 'coe_issued', null, $req['controlNo'], date('Y-m-d'), $req['purpose'], $actor);
    }
    lcy_audit($pdo, 'COE printed: ' . $req['controlNo'] . ' (' . $req['empId'] . ')');
}

/**
 * A caller quotes a COE control number: was it really issued, to whom, when?
 * Null = no such certificate (likely fake). The check itself is logged.
 */
function lcy_coe_check(PDO $pdo, $controlNo) {
    $controlNo = strtoupper(trim((string) $controlNo));
    if (!preg_match('/^COE-\d{4}-\d{4,}$/', $controlNo)) { return null; }
    $st = $pdo->prepare("SELECT id FROM coe_requests WHERE ControlNo = :c");
    $st->execute([':c' => $controlNo]);
    $id = $st->fetchColumn();
    lcy_audit($pdo, 'COE control number checked: ' . $controlNo . ($id ? '' : ' (not found)'));
    if (!$id) { return null; }
    $req = lcy_coe_get($pdo, $id);
    $emp = lcy_employee($pdo, $req['empId']);
    $sep = $emp && $emp['separated'] ? lcy_latest_separation($pdo, $req['empId']) : null;
    return [
        'controlNo' => $req['controlNo'], 'name' => $req['name'], 'empId' => $req['empId'], 'status' => $req['status'],
        'approvedAt' => $req['processedAt'], 'printedAt' => $req['printedAt'], 'withSalary' => $req['salary'],
        'purpose' => $req['purpose'], 'position' => $emp ? $emp['position'] : '', 'hired' => $emp ? $emp['hired'] : null,
        'employment' => $emp ? $emp['status'] : '', 'lastDay' => $sep ? $sep['lastDay'] : null,
    ];
}

/** People who approve COE requests (for the push notification). */
function lcy_coe_approvers(PDO $pdo) {
    try {
        return $pdo->query("SELECT EmpID FROM accessrights WHERE coefunction = 2")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/* ------------------------------------------------------------------ verification (CI) */

/** Verification calls about one WeDo employee or one AMS archive row. */
function lcy_verifications(PDO $pdo, $empId = null, $amsId = null, $limit = 50) {
    $where = [];
    $args  = [];
    if ($empId !== null) { $where[] = 'v.EmpID = :e'; $args[':e'] = (string) $empId; }
    if ($amsId !== null) { $where[] = 'v.AmsID = :a'; $args[':a'] = (int) $amsId; }
    $sql = "SELECT v.*, h.EmpFN AS HFN, h.EmpLN AS HLN FROM employment_verifications v
            LEFT JOIN employees h ON h.EmpID = v.HandledBy"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY v.CalledAt DESC, v.id DESC LIMIT " . (int) $limit;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'id' => (int) $r['id'], 'empId' => $r['EmpID'], 'amsId' => $r['AmsID'] === null ? null : (int) $r['AmsID'],
            'subject' => $r['SubjectName'], 'caller' => $r['CallerName'], 'company' => $r['CallerCompany'],
            'contact' => (string) $r['CallerContact'], 'purpose' => $r['Purpose'],
            'purposeLabel' => LCY_PURPOSE[$r['Purpose']] ?? $r['Purpose'], 'consent' => (int) $r['ConsentConfirmed'] === 1,
            'disclosed' => array_values(array_filter(explode(',', (string) $r['Disclosed']), 'strlen')),
            'notes' => (string) $r['Notes'], 'by' => trim(($r['HFN'] ?? '') . ' ' . ($r['HLN'] ?? '')), 'at' => $r['CalledAt'],
        ];
    }
    return $out;
}

/**
 * Look a person up for a verification call: WeDo employees of any status
 * plus the legacy AMS archive (typed by hand, not linked to an EmpID).
 */
function lcy_verify_lookup(PDO $pdo, $q) {
    $q = trim((string) $q);
    if (mb_strlen($q) < 2) { return ['employees' => [], 'archive' => []]; }

    $employees = [];
    foreach (lcy_find_employees($pdo, $q, 20) as $hit) {
        $emp = lcy_employee($pdo, $hit['empId']);
        if (!$emp) { continue; }
        $sep = $emp['separated'] ? lcy_latest_separation($pdo, $emp['empId']) : null;
        $employees[] = [
            'empId' => $emp['empId'], 'name' => $emp['name'], 'position' => $emp['position'],
            'classDesc' => $emp['classDesc'], 'status' => $emp['status'], 'separated' => $emp['separated'],
            'hired' => $emp['hired'], 'lastDay' => $sep ? $sep['lastDay'] : ($emp['separated'] ? $emp['resigned'] : null),
            'separation' => $sep ? $sep['typeLabel'] : null, 'reason' => $sep ? $sep['reason'] : '',
            'rehire' => $sep ? $sep['rehire'] : ($emp['separated'] ? 'review' : null),
            'rehireNote' => $sep ? $sep['rehireNote'] : '', 'clearance' => $sep ? $sep['clearance'] : null,
            'calls' => lcy_verifications($pdo, $emp['empId'], null, 10),
        ];
    }

    $words = array_slice(array_filter(preg_split('/\s+/u', $q), 'strlen'), 0, 4);
    $where = [];
    $args  = [];
    foreach ($words as $i => $w) {
        $where[] = "(a.fname LIKE :w{$i}a OR a.lname LIKE :w{$i}b)";
        $args[":w{$i}a"] = $args[":w{$i}b"] = '%' . addcslashes($w, '%_\\') . '%';
    }
    $st = $pdo->prepare("SELECT a.* FROM amsarchive a WHERE " . implode(' AND ', $where) . " ORDER BY a.lname, a.fname LIMIT 20");
    $st->execute($args);
    $archive = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $archive[] = [
            'amsId' => (int) $r['id'], 'name' => trim($r['fname'] . ' ' . $r['lname']), 'position' => (string) $r['pos'],
            'from' => (string) $r['empdatesfrom'], 'to' => (string) $r['empdatesto'],
            'status' => (string) $r['employmentstatus'], 'reason' => (string) $r['reasonforleaving'],
            'derogatory' => (string) $r['derogatoryrecords'], 'clearance' => (string) $r['clearance'],
            'remarks' => (string) $r['addremarks'], 'calls' => lcy_verifications($pdo, null, (int) $r['id'], 10),
        ];
    }
    return ['employees' => $employees, 'archive' => $archive];
}

/** Log one verification call. $d: emp | ams, caller, company, contact, purpose, consent, disclosed[], notes. */
function lcy_log_verification(PDO $pdo, array $d, $actor) {
    $empId = trim((string) ($d['emp'] ?? ''));
    $amsId = (int) ($d['ams'] ?? 0);
    $subject = '';
    if ($empId !== '') {
        $emp = lcy_employee($pdo, $empId);
        if (!$emp) { return ['ok' => false, 'msg' => 'That employee no longer exists.']; }
        $subject = $emp['name'];
        $amsId = 0;
    } elseif ($amsId > 0) {
        $st = $pdo->prepare("SELECT fname, lname FROM amsarchive WHERE id = :id");
        $st->execute([':id' => $amsId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return ['ok' => false, 'msg' => 'That archive record no longer exists.']; }
        $subject = trim($r['fname'] . ' ' . $r['lname']);
    } else {
        return ['ok' => false, 'msg' => 'Pick the person the call is about.'];
    }
    $caller  = lcy_text($d['caller'] ?? '', 120);
    $company = lcy_text($d['company'] ?? '', 150);
    $purpose = (string) ($d['purpose'] ?? '');
    if ($caller === '' || $company === '') { return ['ok' => false, 'msg' => "Enter the caller's name and company."]; }
    if (!isset(LCY_PURPOSE[$purpose])) { return ['ok' => false, 'msg' => 'Choose the purpose of the call.']; }
    $disclosed = array_values(array_intersect(array_keys(LCY_DISCLOSE), (array) ($d['disclosed'] ?? [])));
    $consent = !empty($d['consent']);
    if (in_array('salary', $disclosed, true) && !$consent) {
        return ['ok' => false, 'msg' => "Salary may only be disclosed with the person's written authorization — tick that it was confirmed."];
    }
    $pdo->prepare("INSERT INTO employment_verifications (EmpID, AmsID, SubjectName, CallerName, CallerCompany, CallerContact,
                       Purpose, ConsentConfirmed, Disclosed, Notes, HandledBy)
                   VALUES (:e, :a, :s, :cn, :cc, :ct, :p, :c, :d, :n, :h)")
        ->execute([':e' => $empId !== '' ? $empId : null, ':a' => $amsId > 0 ? $amsId : null, ':s' => mb_substr($subject, 0, 150),
                   ':cn' => $caller, ':cc' => $company, ':ct' => lcy_text($d['contact'] ?? '', 120) ?: null, ':p' => $purpose,
                   ':c' => $consent ? 1 : 0, ':d' => implode(',', $disclosed) ?: null,
                   ':n' => lcy_text($d['notes'] ?? '', 500) ?: null, ':h' => $actor]);
    lcy_audit($pdo, 'Employment verification logged: ' . $subject . ' — ' . $company);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}
