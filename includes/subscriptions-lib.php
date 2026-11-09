<?php
/* ==========================================================================
   subscriptions-lib.php  —  rules for the Subscriptions module: the company's
   domains, hosting, hosting security services and PhilGEPS registration.
     - the register, tagged by company; an owner and watchers per item
     - "Mark renewed": the next expiry, what was paid, a receipt; history kept
     - PhilGEPS: the certificate plus each eligibility document with its own
       expiry; "can we bid" holds until the earliest of them
     - answers for the overview: what needs action, what it costs and when
     - daily reminders from NotifyDays before expiry (30, PhilGEPS 45) until
       renewed or cancelled: one digest per person per day, pushed to the
       phone; expired ones also go to everyone with the right
   Screen:  subscriptions.php. Writes: query/subscriptions-action.php.
   Files:   query/subscriptions-file.php. Daily: tools/subscriptions-daily.php.
   Tables:  sql/2026-11-09-add-subscriptions.sql.

   Every "today" is a Manila date from PHP: the servers' MySQL runs on UTC.
   ========================================================================== */
require_once __DIR__ . '/lifecycle-lib.php';

const SUB_CATEGORIES = ['domain' => 'Domain', 'hosting' => 'Hosting', 'security' => 'Security service', 'philgeps' => 'PhilGEPS'];
const SUB_CYCLES     = [1 => 'Monthly', 3 => 'Every 3 months', 6 => 'Every 6 months', 12 => 'Yearly', 24 => 'Every 2 years',
                        36 => 'Every 3 years', 60 => 'Every 5 years', 120 => 'Every 10 years', 0 => 'No fixed cycle'];
const SUB_FILE_KINDS = ['receipt' => 'Receipt / invoice', 'document' => 'Document', 'other' => 'Other'];
const SUB_DOC_TYPES  = ['pdf', 'jpg', 'jpeg', 'png'];
const SUB_NOTIFY_DEFAULT  = 30;
const SUB_NOTIFY_PHILGEPS = 45;
const SUB_RUN_HOUR   = 8;     // reminders go out from 8:00 AM Manila
const SUB_URGENT_DAYS = 7;
/** Eligibility documents a new PhilGEPS registration starts with (editable). */
const SUB_PHILGEPS_DOCS = [
    "Mayor's / business permit",
    'Tax clearance (BIR)',
    'Audited financial statements',
    'SEC / DTI registration',
    'BIR certificate of registration (Form 2303)',
];

/* ------------------------------------------------------------------ access */

function sub_can_manage(PDO $pdo) { return lcy_right($pdo, 'subscriptions'); }

function sub_csrf_token() {
    if (empty($_SESSION['sub_csrf'])) { $_SESSION['sub_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['sub_csrf'];
}

/** Has the migration run on this server? */
function sub_ready(PDO $pdo) {
    try {
        $pdo->query("SELECT 1 FROM sub_notify_log LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Which companies this viewer sees: super users (role 1) every company and may
 * switch; everyone else only their own.
 */
function sub_scope(PDO $pdo) {
    $me = (string) ($_SESSION['id'] ?? '');
    $st = $pdo->prepare("SELECT EmpCompID, EmpRoleID FROM empdetails WHERE EmpID = :e");
    $st->execute([':e' => $me]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $company = (string) ($r['EmpCompID'] ?? ($_SESSION['CompID'] ?? ''));
    return ['all' => (int) ($r['EmpRoleID'] ?? 0) === 1, 'company' => $company];
}

/** The company filter a request may use: '' = every company (super users only). */
function sub_company_filter(array $scope, $asked) {
    $asked = (string) $asked;
    if (!$scope['all']) { return $scope['company']; }
    return ($asked === 'all' || $asked === '') ? '' : $asked;
}

/* ------------------------------------------------------------------ helpers */

function sub_now() { return date('Y-m-d H:i:s'); }
function sub_today() { return date('Y-m-d'); }
function sub_fail($msg) { return ['ok' => false, 'msg' => $msg]; }
function sub_audit(PDO $pdo, $text) { lcy_audit($pdo, 'Subscriptions: ' . $text); }

function sub_opt($v, $max) {
    $v = lcy_text($v, $max);
    return $v === '' ? null : $v;
}

/** A peso amount, or null when blank or unusable. */
function sub_money($v) {
    $v = str_replace([',', '₱', ' '], '', trim((string) $v));
    if ($v === '' || !is_numeric($v) || (float) $v < 0 || (float) $v > 9999999999.99) { return null; }
    return number_format((float) $v, 2, '.', '');
}

/** Whole days from $today to $date (negative = past), or null without a date. */
function sub_days_left($date, $today = null) {
    if (!$date) { return null; }
    $a = new DateTime(($today ?: sub_today()) . ' 00:00:00');
    $b = new DateTime(substr((string) $date, 0, 10) . ' 00:00:00');
    return (int) $a->diff($b)->format('%r%a');
}

/** expired | urgent (0-7 days) | soon (inside the reminder window) | ok | none (no date) | cancelled */
function sub_tone($days, $notifyDays, $status = 'active') {
    if ($status === 'cancelled') { return 'cancelled'; }
    if ($days === null) { return 'none'; }
    if ($days < 0) { return 'expired'; }
    if ($days <= SUB_URGENT_DAYS) { return 'urgent'; }
    if ($days <= (int) $notifyDays) { return 'soon'; }
    return 'ok';
}

/** "expires today", "expires in 5 days", "expired 3 days ago" */
function sub_when_text($days) {
    if ($days === null) { return 'has no expiry date'; }
    if ($days === 0) { return 'expires today'; }
    if ($days === 1) { return 'expires tomorrow'; }
    if ($days > 1) { return 'expires in ' . $days . ' days'; }
    return $days === -1 ? 'expired yesterday' : 'expired ' . (-$days) . ' days ago';
}

/** $date plus whole months, clamped to the month's last day (Jan 31 + 1 month = Feb 28/29). */
function sub_add_months($date, $months) {
    $d = new DateTime(substr((string) $date, 0, 10));
    $day = (int) $d->format('j');
    $d->modify('first day of this month')->modify(($months >= 0 ? '+' : '') . (int) $months . ' months');
    $d->setDate((int) $d->format('Y'), (int) $d->format('n'), min($day, (int) $d->format('t')));
    return $d->format('Y-m-d');
}

/** What "Mark renewed" suggests: the old expiry plus one cycle (a year when there is no cycle). */
function sub_suggest_expiry($expires, $cycleMonths, $today = null) {
    $base = $expires ?: ($today ?: sub_today());
    return sub_add_months($base, (int) $cycleMonths > 0 ? (int) $cycleMonths : 12);
}

function sub_companies(PDO $pdo) {
    return array_map(function ($r) { return ['id' => (string) $r['CompanyID'], 'name' => $r['CompanyDesc']]; },
        $pdo->query("SELECT CompanyID, CompanyDesc FROM companies ORDER BY CompanyDesc")->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Everyone with the Subscriptions right who can still sign in:
 * EmpID => [name, company, super].
 */
function sub_right_holders(PDO $pdo) {
    $rows = $pdo->query("SELECT a.EmpID, e.EmpFN, e.EmpLN, d.EmpCompID, d.EmpRoleID
        FROM accessrights a
        JOIN employees e ON e.EmpID = a.EmpID
        LEFT JOIN empdetails d ON d.EmpID = a.EmpID
        WHERE a.subscriptions = 2
        ORDER BY e.EmpFN, e.EmpLN")->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(function ($r) { return (string) $r['EmpID']; }, $rows);
    $blocked = [];
    if ($ids) {
        require_once __DIR__ . '/account-gate.php';
        $blocked = array_flip(wd_blocked_among($pdo, $ids));
    }
    $out = [];
    foreach ($rows as $r) {
        $id = (string) $r['EmpID'];
        if (isset($blocked[$id])) { continue; }
        $out[$id] = ['name' => trim($r['EmpFN'] . ' ' . $r['EmpLN']), 'company' => (string) $r['EmpCompID'],
                     'super' => (int) $r['EmpRoleID'] === 1];
    }
    return $out;
}

/** "First Last" for each id that is an employee. */
function sub_names(PDO $pdo, array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), 'strlen')));
    if (!$ids) { return []; }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT EmpID, EmpFN, EmpLN FROM employees WHERE EmpID IN ($in)");
    $st->execute($ids);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(string) $r['EmpID']] = trim($r['EmpFN'] . ' ' . $r['EmpLN']); }
    return $out;
}

/** Everything the screen needs for its dropdowns. */
function sub_lookups(PDO $pdo, array $scope) {
    $people = [];
    foreach (sub_right_holders($pdo) as $id => $p) {
        if ($scope['all'] || $p['company'] === $scope['company'] || $p['super']) {
            $people[] = ['id' => $id, 'name' => $p['name'], 'company' => $p['company']];
        }
    }
    $companies = sub_companies($pdo);
    if (!$scope['all']) {
        $companies = array_values(array_filter($companies, function ($c) use ($scope) { return $c['id'] === $scope['company']; }));
    }
    return ['categories' => SUB_CATEGORIES, 'cycles' => SUB_CYCLES, 'fileKinds' => SUB_FILE_KINDS, 'people' => $people,
            'companies' => $companies, 'allCompanies' => $scope['all'], 'myCompany' => $scope['company'],
            'philgepsDocs' => SUB_PHILGEPS_DOCS, 'notifyDefault' => SUB_NOTIFY_DEFAULT, 'notifyPhilgeps' => SUB_NOTIFY_PHILGEPS];
}

/* ------------------------------------------------------------------ reading */

function sub_item_row(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT * FROM sub_items WHERE id = :id");
    $st->execute([':id' => (int) $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function sub_req_out(array $r, $today) {
    $days = sub_days_left($r['ExpiresOn'], $today);
    return ['id' => (int) $r['id'], 'itemId' => (int) $r['ItemID'], 'name' => $r['Name'], 'issuedOn' => $r['IssuedOn'],
            'expiresOn' => $r['ExpiresOn'], 'days' => $days, 'tone' => sub_tone($days, $r['NotifyDays']),
            'notifyDays' => (int) $r['NotifyDays'], 'notes' => (string) $r['Notes'], 'sort' => (int) $r['SortOrder'],
            'files' => (int) ($r['Files'] ?? 0)];
}

/** requirements of the given PhilGEPS items: itemId => [req, ...] */
function sub_requirements_of(PDO $pdo, array $itemIds, $today) {
    $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
    if (!$itemIds) { return []; }
    $in = implode(',', array_fill(0, count($itemIds), '?'));
    $st = $pdo->prepare("SELECT r.*, (SELECT COUNT(*) FROM sub_files f WHERE f.ReqID = r.id) AS Files
        FROM sub_requirements r WHERE r.ItemID IN ($in) ORDER BY r.SortOrder, r.id");
    $st->execute($itemIds);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int) $r['ItemID']][] = sub_req_out($r, $today); }
    return $out;
}

/**
 * PhilGEPS "can we bid": valid until the earliest of the certificate's expiry
 * and every document that expires. Documents without a date count as current.
 */
function sub_eligibility(?string $certExpiry, array $reqs, $today) {
    $until = $certExpiry ?: null;
    $limitedBy = $certExpiry ? 'PhilGEPS certificate' : null;
    $current = 0; $next = null; $expired = [];
    foreach ($reqs as $r) {
        if ($r['expiresOn'] === null || $r['days'] >= 0) { $current++; }
        else { $expired[] = $r['name']; }
        if ($r['expiresOn'] !== null) {
            if ($until === null || $r['expiresOn'] < $until) { $until = $r['expiresOn']; $limitedBy = $r['name']; }
            if ($r['days'] >= 0 && ($next === null || $r['expiresOn'] < $next['date'])) { $next = ['name' => $r['name'], 'date' => $r['expiresOn']]; }
        }
    }
    $certOk = !$certExpiry || sub_days_left($certExpiry, $today) >= 0;
    return ['canBid' => $certOk && !$expired && ($until === null || $until >= $today), 'until' => $until, 'limitedBy' => $limitedBy,
            'current' => $current, 'total' => count($reqs), 'expired' => $expired, 'next' => $next,
            'certMissing' => !$certExpiry];
}

function sub_item_out(array $r, $today, array $names = [], array $reqs = [], array $watchers = [], array $parents = []) {
    $days = sub_days_left($r['ExpiresOn'], $today);
    $notify = (int) $r['NotifyDays'];
    $out = [
        'id' => (int) $r['id'], 'category' => $r['Category'], 'categoryLabel' => SUB_CATEGORIES[$r['Category']] ?? $r['Category'],
        'name' => $r['Name'], 'provider' => (string) $r['Provider'], 'plan' => (string) $r['Plan'], 'accountRef' => (string) $r['AccountRef'],
        'companyId' => (string) $r['CompanyID'], 'company' => (string) ($r['CompanyDesc'] ?? ''),
        'expiresOn' => $r['ExpiresOn'], 'days' => $days, 'tone' => sub_tone($days, $notify, $r['Status']),
        'cycle' => (int) $r['CycleMonths'], 'cycleLabel' => SUB_CYCLES[(int) $r['CycleMonths']] ?? '',
        'cost' => $r['Cost'] === null ? null : (float) $r['Cost'], 'autoRenew' => (int) $r['AutoRenew'] === 1,
        'paymentNote' => (string) $r['PaymentNote'], 'renewUrl' => (string) $r['RenewUrl'],
        'ownerId' => (string) $r['OwnerEmpID'], 'owner' => $names[(string) $r['OwnerEmpID']] ?? '',
        'notifyDays' => $notify, 'remindFrom' => $r['ExpiresOn'] ? sub_add_days($r['ExpiresOn'], -$notify) : null,
        'parentId' => $r['ParentID'] === null ? null : (int) $r['ParentID'], 'parent' => $parents[(int) $r['ParentID']] ?? '',
        'impact' => (string) $r['Impact'], 'notes' => (string) $r['Notes'], 'status' => $r['Status'],
        'watchers' => $watchers, 'suggestExpiry' => sub_suggest_expiry($r['ExpiresOn'], $r['CycleMonths'], $today),
    ];
    if ($r['Category'] === 'philgeps') {
        $out['requirements'] = $reqs;
        $out['eligibility'] = sub_eligibility($r['ExpiresOn'], $reqs, $today);
    }
    return $out;
}

function sub_add_days($date, $n) {
    $d = new DateTime(substr((string) $date, 0, 10));
    $d->modify(($n >= 0 ? '+' : '') . (int) $n . ' days');
    return $d->format('Y-m-d');
}

/** The register for one company ('' = every company), soonest expiry first. */
function sub_list(PDO $pdo, $company, $today = null, $withCancelled = true) {
    $today = $today ?: sub_today();
    $sql = "SELECT s.*, c.CompanyDesc FROM sub_items s LEFT JOIN companies c ON c.CompanyID = s.CompanyID WHERE 1 = 1";
    $args = [];
    if ($company !== '') { $sql .= " AND s.CompanyID = :c"; $args[':c'] = $company; }
    if (!$withCancelled) { $sql .= " AND s.Status = 'active'"; }
    $sql .= " ORDER BY s.Status = 'cancelled', s.ExpiresOn IS NULL, s.ExpiresOn, s.Name";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { return []; }

    $ids = array_map(function ($r) { return (int) $r['id']; }, $rows);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $w = $pdo->prepare("SELECT ItemID, EmpID FROM sub_watchers WHERE ItemID IN ($in)");
    $w->execute($ids);
    $watch = [];
    foreach ($w->fetchAll(PDO::FETCH_ASSOC) as $x) { $watch[(int) $x['ItemID']][] = (string) $x['EmpID']; }
    $pg = array_map(function ($r) { return (int) $r['id']; }, array_filter($rows, function ($r) { return $r['Category'] === 'philgeps'; }));
    $reqs = sub_requirements_of($pdo, $pg, $today);
    $names = sub_names($pdo, array_column($rows, 'OwnerEmpID'));
    $parents = sub_parent_names($pdo, array_filter(array_column($rows, 'ParentID')));

    return array_map(function ($r) use ($today, $names, $reqs, $watch, $parents) {
        return sub_item_out($r, $today, $names, $reqs[(int) $r['id']] ?? [], $watch[(int) $r['id']] ?? [], $parents);
    }, $rows);
}

function sub_parent_names(PDO $pdo, array $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) { return []; }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, Name FROM sub_items WHERE id IN ($in)");
    $st->execute($ids);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[(int) $r['id']] = $r['Name']; }
    return $out;
}

/**
 * What needs renewing, as entries: an item's own expiry, or one PhilGEPS
 * document. Only active items with a date. $withinWindow keeps those inside
 * their reminder window or past expiry.
 */
function sub_entries(array $items, $withinWindow = true) {
    $out = [];
    foreach ($items as $i) {
        if ($i['status'] !== 'active') { continue; }
        if ($i['days'] !== null && (!$withinWindow || $i['days'] <= $i['notifyDays'])) {
            $out[] = ['itemId' => $i['id'], 'reqId' => null, 'name' => $i['name'], 'label' => $i['name'],
                      'category' => $i['category'], 'categoryLabel' => $i['categoryLabel'], 'companyId' => $i['companyId'],
                      'expiresOn' => $i['expiresOn'], 'days' => $i['days'], 'tone' => $i['tone'], 'cost' => $i['cost'],
                      'autoRenew' => $i['autoRenew'], 'owner' => $i['owner'], 'impact' => $i['impact']];
        }
        foreach ($i['requirements'] ?? [] as $r) {
            if ($r['days'] === null || ($withinWindow && $r['days'] > $r['notifyDays'])) { continue; }
            $out[] = ['itemId' => $i['id'], 'reqId' => $r['id'], 'name' => $r['name'], 'label' => $r['name'] . ' (PhilGEPS)',
                      'category' => 'philgeps', 'categoryLabel' => 'PhilGEPS document', 'companyId' => $i['companyId'],
                      'expiresOn' => $r['expiresOn'], 'days' => $r['days'], 'tone' => $r['tone'], 'cost' => null,
                      'autoRenew' => false, 'owner' => $i['owner'], 'impact' => 'Needed to stay eligible to bid on PhilGEPS'];
        }
    }
    usort($out, function ($a, $b) { return $a['days'] <=> $b['days'] ?: strcmp($a['label'], $b['label']); });
    return $out;
}

/**
 * Renewal costs over the next 12 months, month by month, from each item's
 * expiry and cycle. Anything already past due counts in this month.
 */
function sub_timeline(array $items, $today) {
    $start = substr($today, 0, 7) . '-01';
    $end = sub_add_months($start, 12);
    $months = [];
    for ($k = 0; $k < 12; $k++) {
        $m = sub_add_months($start, $k);
        $months[substr($m, 0, 7)] = ['ym' => substr($m, 0, 7), 'label' => date('M', strtotime($m)), 'year' => (int) substr($m, 0, 4),
                                     'amount' => 0.0, 'count' => 0, 'items' => []];
    }
    foreach ($items as $i) {
        if ($i['status'] !== 'active' || !$i['expiresOn']) { continue; }
        $d = $i['expiresOn'];
        $guard = 0;
        while ($d < $end && $guard++ < 200) {
            $key = $d < $start ? substr($start, 0, 7) : substr($d, 0, 7);
            if (isset($months[$key])) {
                $months[$key]['amount'] += (float) $i['cost'];
                $months[$key]['count']++;
                $months[$key]['items'][] = ['id' => $i['id'], 'name' => $i['name'], 'date' => $d < $start ? $i['expiresOn'] : $d, 'cost' => $i['cost']];
            }
            if ($i['cycle'] <= 0) { break; }
            $d = sub_add_months($d, $i['cycle']);
        }
    }
    $list = array_values($months);
    $total = array_sum(array_column($list, 'amount'));
    $busiest = null;
    foreach ($list as $m) { if ($m['amount'] > 0 && ($busiest === null || $m['amount'] > $busiest['amount'])) { $busiest = $m; } }
    return ['months' => $list, 'total' => round($total, 2), 'busiest' => $busiest ? $busiest['ym'] : null];
}

/** The overview: answers first, then the register. */
function sub_overview(PDO $pdo, $company, $today = null) {
    $today = $today ?: sub_today();
    $items = sub_list($pdo, $company, $today);
    $all = sub_entries($items, false);
    $attention = sub_entries($items, true);
    $stats = ['expired' => 0, 'due7' => 0, 'due30' => 0, 'autoOff' => 0, 'active' => 0, 'noDate' => 0, 'due30Cost' => 0.0];
    foreach ($all as $e) {
        if ($e['days'] < 0) { $stats['expired']++; }
        elseif ($e['days'] <= SUB_URGENT_DAYS) { $stats['due7']++; }
        if ($e['days'] >= 0 && $e['days'] <= 30) { $stats['due30']++; }
        if ($e['days'] <= 30 && $e['cost'] !== null) { $stats['due30Cost'] += $e['cost']; }
    }
    foreach ($items as $i) {
        if ($i['status'] !== 'active') { continue; }
        $stats['active']++;
        if (!$i['expiresOn']) { $stats['noDate']++; }
        if (!$i['autoRenew'] && $i['category'] !== 'philgeps' && $i['cycle'] > 0) { $stats['autoOff']++; }
    }
    $stats['due30Cost'] = round($stats['due30Cost'], 2);
    $philgeps = [];
    foreach ($items as $i) {
        if ($i['category'] === 'philgeps' && $i['status'] === 'active') {
            $philgeps[] = ['id' => $i['id'], 'name' => $i['name'], 'company' => $i['company']] + $i['eligibility'];
        }
    }
    $next = null;
    foreach ($all as $e) { if ($e['days'] >= 0) { $next = $e; break; } }
    return ['today' => $today, 'items' => $items, 'attention' => $attention, 'stats' => $stats,
            'timeline' => sub_timeline($items, $today), 'philgeps' => $philgeps, 'next' => $next];
}

/** Entries needing action that this viewer can see (sidebar badge). */
function sub_menu_count(PDO $pdo, array $scope) {
    try {
        $company = $scope['all'] ? '' : $scope['company'];
        return count(sub_entries(sub_list($pdo, $company, null, false), true));
    } catch (Throwable $e) {
        return 0;
    }
}

function sub_item_detail(PDO $pdo, $id, array $scope, $today = null) {
    $today = $today ?: sub_today();
    $r = sub_item_row($pdo, $id);
    if (!$r || !sub_in_scope($r, $scope)) { return null; }
    $items = array_values(array_filter(sub_list($pdo, (string) $r['CompanyID'], $today), function ($i) use ($id) { return $i['id'] === (int) $id; }));
    if (!$items) { return null; }
    $item = $items[0];

    $st = $pdo->prepare("SELECT r.*, q.Name AS ReqName FROM sub_renewals r LEFT JOIN sub_requirements q ON q.id = r.ReqID WHERE r.ItemID = :i ORDER BY r.id DESC");
    $st->execute([':i' => (int) $id]);
    $ren = $st->fetchAll(PDO::FETCH_ASSOC);
    $names = sub_names($pdo, array_column($ren, 'CreatedBy'));
    $item['renewals'] = array_map(function ($x) use ($names) {
        return ['id' => (int) $x['id'], 'reqId' => $x['ReqID'] === null ? null : (int) $x['ReqID'], 'what' => $x['ReqName'] ?: '',
                'oldExpiry' => $x['OldExpiry'], 'newExpiry' => $x['NewExpiry'], 'amount' => $x['Amount'] === null ? null : (float) $x['Amount'],
                'paidOn' => $x['PaidOn'], 'reference' => (string) $x['Reference'], 'notes' => (string) $x['Notes'],
                'by' => $names[(string) $x['CreatedBy']] ?? (string) $x['CreatedBy'], 'at' => $x['CreatedAt']];
    }, $ren);
    $item['files'] = sub_files($pdo, (int) $id);

    $ch = $pdo->prepare("SELECT id, Name, Category, ExpiresOn, Status FROM sub_items WHERE ParentID = :i ORDER BY Name");
    $ch->execute([':i' => (int) $id]);
    $item['children'] = array_map(function ($c) use ($today) {
        return ['id' => (int) $c['id'], 'name' => $c['Name'], 'categoryLabel' => SUB_CATEGORIES[$c['Category']] ?? $c['Category'],
                'expiresOn' => $c['ExpiresOn'], 'status' => $c['Status'], 'days' => sub_days_left($c['ExpiresOn'], $today)];
    }, $ch->fetchAll(PDO::FETCH_ASSOC));

    $holders = sub_right_holders($pdo);
    $item['watcherNames'] = array_values(array_map(function ($w) use ($holders) { return $holders[$w]['name'] ?? $w; }, $item['watchers']));
    $item['reminders'] = sub_reminder_plan($pdo, $item, $holders, $today);
    $item['vault'] = sub_vault_of($pdo, (int) $id);
    return $item;
}

/** Who gets reminded about this item and from when — the detail panel explains it. */
function sub_reminder_plan(PDO $pdo, array $item, array $holders, $today) {
    $people = [];
    foreach (array_merge([$item['ownerId']], $item['watchers']) as $id) {
        if ($id !== '' && isset($holders[$id])) { $people[$id] = $holders[$id]['name']; }
    }
    $missing = [];
    if ($item['ownerId'] === '') { $missing[] = 'no owner'; }
    elseif (!isset($holders[$item['ownerId']])) { $missing[] = 'the owner no longer has the Subscriptions right'; }
    $phones = 0;
    if ($people) {
        $in = implode(',', array_fill(0, count($people), '?'));
        $st = $pdo->prepare("SELECT COUNT(DISTINCT EmpID) FROM push_devices WHERE EmpID IN ($in)");
        try { $st->execute(array_keys($people)); $phones = (int) $st->fetchColumn(); } catch (Throwable $e) { $phones = 0; }
    }
    return ['people' => array_values($people), 'from' => $item['remindFrom'], 'active' => $item['status'] === 'active'
            && $item['days'] !== null && $item['days'] <= $item['notifyDays'], 'withApp' => $phones, 'problems' => $missing];
}

function sub_in_scope(array $row, array $scope) {
    return $scope['all'] || (string) $row['CompanyID'] === (string) $scope['company'];
}

/* ------------------------------------------------------------------ writing */

/** Would making $parentId the parent of $id loop back to $id? */
function sub_parent_loops(PDO $pdo, $id, $parentId) {
    $seen = [];
    $cur = (int) $parentId;
    while ($cur > 0 && !isset($seen[$cur])) {
        if ($cur === (int) $id) { return true; }
        $seen[$cur] = true;
        $st = $pdo->prepare("SELECT ParentID FROM sub_items WHERE id = :id");
        $st->execute([':id' => $cur]);
        $cur = (int) $st->fetchColumn();
    }
    return false;
}

function sub_item_save(PDO $pdo, array $d, array $scope, $actor) {
    $id = (int) ($d['id'] ?? 0);
    $old = $id > 0 ? sub_item_row($pdo, $id) : null;
    if ($id > 0 && (!$old || !sub_in_scope($old, $scope))) { return sub_fail('That subscription no longer exists.'); }

    $cat = (string) ($d['category'] ?? '');
    if (!isset(SUB_CATEGORIES[$cat])) { return sub_fail('Choose what kind of subscription this is.'); }
    $name = lcy_text($d['name'] ?? '', 150);
    if ($name === '') { return sub_fail($cat === 'domain' ? 'Enter the domain name, e.g. wedo.ph.' : 'Enter a name for it.'); }
    if ($cat === 'domain') { $name = strtolower($name); }

    $company = $scope['all'] ? (string) ($d['companyId'] ?? '') : $scope['company'];
    if ($company === '') { return sub_fail('Choose the company it belongs to.'); }
    $st = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE CompanyID = :c");
    $st->execute([':c' => $company]);
    if (!(int) $st->fetchColumn()) { return sub_fail('That company no longer exists.'); }

    $rawExp = trim((string) ($d['expiresOn'] ?? ''));
    $expires = $rawExp === '' ? null : lcy_date($rawExp);
    if ($rawExp !== '' && $expires === null) { return sub_fail('Enter the expiry date as a real date.'); }

    $cycle = (int) ($d['cycle'] ?? 12);
    if (!array_key_exists($cycle, SUB_CYCLES)) { return sub_fail('Choose how often it renews.'); }

    $rawCost = trim((string) ($d['cost'] ?? ''));
    $cost = $rawCost === '' ? null : sub_money($rawCost);
    if ($rawCost !== '' && $cost === null) { return sub_fail('Enter the cost as a number, e.g. 1250.00.'); }

    $notify = trim((string) ($d['notifyDays'] ?? '')) === '' ? ($cat === 'philgeps' ? SUB_NOTIFY_PHILGEPS : SUB_NOTIFY_DEFAULT) : (int) $d['notifyDays'];
    if ($notify < 1 || $notify > 365) { return sub_fail('Reminders can start 1 to 365 days before expiry.'); }

    $url = trim((string) ($d['renewUrl'] ?? ''));
    if ($url !== '' && !preg_match('#^https?://[^\s<>"]+$#i', $url)) { return sub_fail('The renewal link must start with http:// or https://.'); }
    if (mb_strlen($url) > 255) { return sub_fail('The renewal link is too long.'); }

    $holders = sub_right_holders($pdo);
    $owner = trim((string) ($d['ownerId'] ?? ''));
    if ($owner !== '' && !isset($holders[$owner]) && !($old && (string) $old['OwnerEmpID'] === $owner)) {
        return sub_fail('The owner must be someone with the Subscriptions right.');
    }
    $watchers = array_values(array_unique(array_filter(array_map('strval', (array) ($d['watchers'] ?? [])), function ($w) use ($holders, $owner) {
        return $w !== '' && $w !== $owner && isset($holders[$w]);
    })));

    $parent = (int) ($d['parentId'] ?? 0) ?: null;
    if ($parent !== null) {
        $p = sub_item_row($pdo, $parent);
        if (!$p || !sub_in_scope($p, $scope)) { return sub_fail('The item it depends on no longer exists.'); }
        if ($id > 0 && ($parent === $id || sub_parent_loops($pdo, $id, $parent))) { return sub_fail('An item cannot depend on itself.'); }
    }

    $f = [
        ':co' => $company, ':ca' => $cat, ':n' => $name, ':pr' => sub_opt($d['provider'] ?? '', 100), ':pl' => sub_opt($d['plan'] ?? '', 100),
        ':ac' => sub_opt($d['accountRef'] ?? '', 150), ':ex' => $expires, ':cy' => $cycle, ':cs' => $cost,
        ':au' => !empty($d['autoRenew']) ? 1 : 0, ':pn' => sub_opt($d['paymentNote'] ?? '', 100), ':ru' => $url === '' ? null : $url,
        ':ow' => $owner === '' ? null : $owner, ':nd' => $notify, ':pa' => $parent, ':im' => sub_opt($d['impact'] ?? '', 255),
        ':no' => sub_opt($d['notes'] ?? '', 1000), ':t' => sub_now(), ':by' => (string) $actor,
    ];
    $pdo->beginTransaction();
    try {
        if ($old) {
            $pdo->prepare("UPDATE sub_items SET CompanyID = :co, Category = :ca, Name = :n, Provider = :pr, Plan = :pl, AccountRef = :ac,
                    ExpiresOn = :ex, CycleMonths = :cy, Cost = :cs, AutoRenew = :au, PaymentNote = :pn, RenewUrl = :ru, OwnerEmpID = :ow,
                    NotifyDays = :nd, ParentID = :pa, Impact = :im, Notes = :no, UpdatedAt = :t, UpdatedBy = :by WHERE id = :id")
                ->execute($f + [':id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO sub_items (CompanyID, Category, Name, Provider, Plan, AccountRef, ExpiresOn, CycleMonths, Cost, AutoRenew,
                    PaymentNote, RenewUrl, OwnerEmpID, NotifyDays, ParentID, Impact, Notes, Status, CreatedAt, CreatedBy)
                VALUES (:co, :ca, :n, :pr, :pl, :ac, :ex, :cy, :cs, :au, :pn, :ru, :ow, :nd, :pa, :im, :no, 'active', :t, :by)")
                ->execute($f);
            $id = (int) $pdo->lastInsertId();
            if ($cat === 'philgeps') {
                $ins = $pdo->prepare("INSERT INTO sub_requirements (ItemID, Name, NotifyDays, SortOrder, UpdatedAt, UpdatedBy) VALUES (?, ?, ?, ?, ?, ?)");
                foreach (SUB_PHILGEPS_DOCS as $k => $doc) { $ins->execute([$id, $doc, SUB_NOTIFY_PHILGEPS, $k + 1, sub_now(), (string) $actor]); }
            }
        }
        $pdo->prepare("DELETE FROM sub_watchers WHERE ItemID = :i")->execute([':i' => $id]);
        $w = $pdo->prepare("INSERT INTO sub_watchers (ItemID, EmpID) VALUES (?, ?)");
        foreach ($watchers as $emp) { $w->execute([$id, $emp]); }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    sub_audit($pdo, ($old ? 'edited ' : 'added ') . SUB_CATEGORIES[$cat] . ' ' . $name);
    return ['ok' => true, 'id' => $id];
}

/** Stop tracking (and stop reminders) without losing the history; or track it again. */
function sub_item_status(PDO $pdo, $id, $status, array $scope, $actor) {
    $r = sub_item_row($pdo, $id);
    if (!$r || !sub_in_scope($r, $scope)) { return sub_fail('That subscription no longer exists.'); }
    if (!in_array($status, ['active', 'cancelled'], true)) { return sub_fail('Unknown status.'); }
    $pdo->prepare("UPDATE sub_items SET Status = :s, CancelledAt = :c, UpdatedAt = :t, UpdatedBy = :b WHERE id = :id")
        ->execute([':s' => $status, ':c' => $status === 'cancelled' ? sub_now() : null, ':t' => sub_now(), ':b' => (string) $actor, ':id' => (int) $id]);
    sub_audit($pdo, ($status === 'cancelled' ? 'cancelled ' : 'resumed ') . $r['Name']);
    return ['ok' => true, 'id' => (int) $id];
}

/** Remove an item added by mistake, with its documents, history, files and saved logins. */
function sub_item_delete(PDO $pdo, $id, array $scope, ?string $dir = null) {
    $r = sub_item_row($pdo, $id);
    if (!$r || !sub_in_scope($r, $scope)) { return sub_fail('That subscription no longer exists.'); }
    $st = $pdo->prepare("SELECT FileKey FROM sub_files WHERE ItemID = :i");
    $st->execute([':i' => (int) $id]);
    $keys = $st->fetchAll(PDO::FETCH_COLUMN);
    $cr = $pdo->prepare("SELECT id, ItemID, Label FROM sub_creds WHERE ItemID = :i");
    $cr->execute([':i' => (int) $id]);
    $creds = $cr->fetchAll(PDO::FETCH_ASSOC);
    $pdo->beginTransaction();
    try {
        foreach ($creds as $c) { sub_cred_log_add($pdo, $c, (string) ($_SESSION['id'] ?? ''), 'delete'); }   // the log outlives the item
        foreach (['sub_files', 'sub_renewals', 'sub_requirements', 'sub_watchers', 'sub_creds'] as $t) {
            $pdo->prepare("DELETE FROM `$t` WHERE ItemID = :i")->execute([':i' => (int) $id]);
        }
        $pdo->prepare("UPDATE sub_items SET ParentID = NULL WHERE ParentID = :i")->execute([':i' => (int) $id]);
        $pdo->prepare("DELETE FROM sub_items WHERE id = :i")->execute([':i' => (int) $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    foreach ($keys as $k) { $p = sub_file_path($k, $dir); if ($p) { @unlink($p); } }
    sub_audit($pdo, 'deleted ' . $r['Name']);
    return ['ok' => true];
}

/**
 * "Mark renewed": the item (or one PhilGEPS document, $reqId) gets its new
 * expiry, the payment is logged, an optional receipt or new scan is kept.
 * Reminders stop at once because the new date is outside the window.
 */
function sub_renew(PDO $pdo, $itemId, $reqId, array $d, ?array $file, array $scope, $actor, $uploaded = true, ?string $dir = null) {
    $item = sub_item_row($pdo, $itemId);
    if (!$item || !sub_in_scope($item, $scope)) { return sub_fail('That subscription no longer exists.'); }
    if ($item['Status'] !== 'active') { return sub_fail('Resume tracking this subscription before renewing it.'); }
    $req = null;
    if ($reqId) {
        $st = $pdo->prepare("SELECT * FROM sub_requirements WHERE id = :id AND ItemID = :i");
        $st->execute([':id' => (int) $reqId, ':i' => (int) $itemId]);
        $req = $st->fetch(PDO::FETCH_ASSOC);
        if (!$req) { return sub_fail('That document no longer exists.'); }
    }
    $oldExp = $req ? $req['ExpiresOn'] : $item['ExpiresOn'];
    $new = lcy_date($d['newExpiry'] ?? '');
    if ($new === null) { return sub_fail('Enter the new expiry date.'); }
    if ($oldExp && $new <= $oldExp) { return sub_fail('The new expiry must be later than the current one (' . date('M j, Y', strtotime($oldExp)) . ').'); }
    $rawAmt = trim((string) ($d['amount'] ?? ''));
    $amount = $rawAmt === '' ? null : sub_money($rawAmt);
    if ($rawAmt !== '' && $amount === null) { return sub_fail('Enter the amount paid as a number.'); }
    $rawPaid = trim((string) ($d['paidOn'] ?? ''));
    $paid = $rawPaid === '' ? null : lcy_date($rawPaid);
    if ($rawPaid !== '' && $paid === null) { return sub_fail('Enter the payment date as a real date.'); }
    $issued = $req ? lcy_date($d['issuedOn'] ?? '') : null;

    $stored = null;
    if ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $stored = sub_store_doc($file, $uploaded, $dir);
        if (!$stored['ok']) { return $stored; }
    }

    $pdo->beginTransaction();
    try {
        if ($req) {
            $pdo->prepare("UPDATE sub_requirements SET ExpiresOn = :e, IssuedOn = COALESCE(:iss, IssuedOn), UpdatedAt = :t, UpdatedBy = :b WHERE id = :id")
                ->execute([':e' => $new, ':iss' => $issued, ':t' => sub_now(), ':b' => (string) $actor, ':id' => (int) $reqId]);
        } else {
            $pdo->prepare("UPDATE sub_items SET ExpiresOn = :e, UpdatedAt = :t, UpdatedBy = :b WHERE id = :id")
                ->execute([':e' => $new, ':t' => sub_now(), ':b' => (string) $actor, ':id' => (int) $itemId]);
            if ($amount !== null && $item['Cost'] === null) {
                $pdo->prepare("UPDATE sub_items SET Cost = :c WHERE id = :id")->execute([':c' => $amount, ':id' => (int) $itemId]);
            }
        }
        $pdo->prepare("INSERT INTO sub_renewals (ItemID, ReqID, OldExpiry, NewExpiry, Amount, PaidOn, Reference, Notes, CreatedAt, CreatedBy)
                       VALUES (:i, :r, :o, :n, :a, :p, :ref, :no, :t, :b)")
            ->execute([':i' => (int) $itemId, ':r' => $req ? (int) $reqId : null, ':o' => $oldExp, ':n' => $new, ':a' => $amount,
                       ':p' => $paid, ':ref' => sub_opt($d['reference'] ?? '', 100), ':no' => sub_opt($d['notes'] ?? '', 500),
                       ':t' => sub_now(), ':b' => (string) $actor]);
        $renId = (int) $pdo->lastInsertId();
        if ($stored) {
            _sub_file_insert($pdo, (int) $itemId, $req ? (int) $reqId : null, $renId, $req ? 'document' : 'receipt', $stored, $actor, $dir);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ($stored) { $p = sub_file_path($stored['key'], $dir); if ($p) { @unlink($p); } }
        throw $e;
    }
    sub_audit($pdo, 'renewed ' . ($req ? $req['Name'] . ' of ' : '') . $item['Name'] . ' until ' . $new);
    return ['ok' => true, 'id' => (int) $itemId, 'renewalId' => $renId];
}

/** Add, rename or re-date a PhilGEPS document (no renewal logged: use sub_renew for that). */
function sub_req_save(PDO $pdo, array $d, array $scope, $actor) {
    $itemId = (int) ($d['itemId'] ?? 0);
    $item = sub_item_row($pdo, $itemId);
    if (!$item || !sub_in_scope($item, $scope)) { return sub_fail('That subscription no longer exists.'); }
    if ($item['Category'] !== 'philgeps') { return sub_fail('Documents belong to a PhilGEPS registration.'); }
    $id = (int) ($d['id'] ?? 0);
    $name = lcy_text($d['name'] ?? '', 120);
    if ($name === '') { return sub_fail('Enter the document name.'); }
    $rawExp = trim((string) ($d['expiresOn'] ?? ''));
    $exp = $rawExp === '' ? null : lcy_date($rawExp);
    if ($rawExp !== '' && $exp === null) { return sub_fail('Enter the expiry date as a real date.'); }
    $rawIss = trim((string) ($d['issuedOn'] ?? ''));
    $iss = $rawIss === '' ? null : lcy_date($rawIss);
    if ($rawIss !== '' && $iss === null) { return sub_fail('Enter the issue date as a real date.'); }
    $notify = trim((string) ($d['notifyDays'] ?? '')) === '' ? SUB_NOTIFY_PHILGEPS : (int) $d['notifyDays'];
    if ($notify < 1 || $notify > 365) { return sub_fail('Reminders can start 1 to 365 days before expiry.'); }
    $args = [':n' => $name, ':e' => $exp, ':iss' => $iss, ':nd' => $notify, ':no' => sub_opt($d['notes'] ?? '', 500), ':t' => sub_now(), ':b' => (string) $actor];
    if ($id > 0) {
        $st = $pdo->prepare("UPDATE sub_requirements SET Name = :n, ExpiresOn = :e, IssuedOn = :iss, NotifyDays = :nd, Notes = :no, UpdatedAt = :t, UpdatedBy = :b
                             WHERE id = :id AND ItemID = :i");
        $st->execute($args + [':id' => $id, ':i' => $itemId]);
        if (!$st->rowCount() && !sub_req_exists($pdo, $id, $itemId)) { return sub_fail('That document no longer exists.'); }
    } else {
        $sort = (int) $pdo->query("SELECT COALESCE(MAX(SortOrder), 0) + 1 FROM sub_requirements WHERE ItemID = " . $itemId)->fetchColumn();
        $pdo->prepare("INSERT INTO sub_requirements (ItemID, Name, ExpiresOn, IssuedOn, NotifyDays, Notes, SortOrder, UpdatedAt, UpdatedBy)
                       VALUES (:i, :n, :e, :iss, :nd, :no, :s, :t, :b)")->execute($args + [':i' => $itemId, ':s' => $sort]);
        $id = (int) $pdo->lastInsertId();
    }
    return ['ok' => true, 'id' => $itemId, 'reqId' => $id];
}

function sub_req_exists(PDO $pdo, $id, $itemId) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM sub_requirements WHERE id = :id AND ItemID = :i");
    $st->execute([':id' => (int) $id, ':i' => (int) $itemId]);
    return (int) $st->fetchColumn() > 0;
}

function sub_req_delete(PDO $pdo, $id, array $scope, ?string $dir = null) {
    $st = $pdo->prepare("SELECT r.*, s.CompanyID FROM sub_requirements r JOIN sub_items s ON s.id = r.ItemID WHERE r.id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r || !sub_in_scope($r, $scope)) { return sub_fail('That document no longer exists.'); }
    $k = $pdo->prepare("SELECT FileKey FROM sub_files WHERE ReqID = :id");
    $k->execute([':id' => (int) $id]);
    $keys = $k->fetchAll(PDO::FETCH_COLUMN);
    $pdo->prepare("DELETE FROM sub_files WHERE ReqID = :id")->execute([':id' => (int) $id]);
    $pdo->prepare("UPDATE sub_renewals SET ReqID = NULL, Notes = CONCAT_WS(' · ', :n, Notes) WHERE ReqID = :id")->execute([':n' => 'Removed document: ' . $r['Name'], ':id' => (int) $id]);
    $pdo->prepare("DELETE FROM sub_requirements WHERE id = :id")->execute([':id' => (int) $id]);
    foreach ($keys as $key) { $p = sub_file_path($key, $dir); if ($p) { @unlink($p); } }
    return ['ok' => true, 'id' => (int) $r['ItemID']];
}

/* ------------------------------------------------------------------ files */

/** uploads/subscriptions/, created with a deny-all .htaccess on first use. */
function sub_dir(?string $dir = null) {
    $dir = $dir ?? dirname(__DIR__) . '/uploads/subscriptions';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess',
            "# Subscription receipts and documents: served only through query/subscriptions-file.php\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/** A receipt or scan: PDF or picture, the content must match the extension. */
function sub_store_doc(array $f, $uploaded = true, ?string $dir = null) {
    require_once __DIR__ . '/msg-files.php';
    $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, SUB_DOC_TYPES, true)) { return sub_fail('Attach a PDF, JPG or PNG file.'); }
    $r = mf_store($f, (bool) $uploaded, sub_dir($dir));
    if (!$r['ok']) { return sub_fail($r['error']); }
    $card = json_decode($r['text'], true);
    return ['ok' => true, 'key' => $card['k'], 'name' => $card['n']];
}

function _sub_file_insert(PDO $pdo, $itemId, $reqId, $renewalId, $kind, array $stored, $actor, ?string $dir = null) {
    $path = sub_dir($dir) . '/' . $stored['key'];
    $pdo->prepare("INSERT INTO sub_files (ItemID, ReqID, RenewalID, Kind, FileKey, Name, Size, CreatedAt, CreatedBy) VALUES (:i, :r, :n, :k, :f, :nm, :s, :t, :b)")
        ->execute([':i' => (int) $itemId, ':r' => $reqId, ':n' => $renewalId, ':k' => $kind, ':f' => $stored['key'],
                   ':nm' => mb_substr((string) ($stored['name'] ?? 'file'), 0, 100), ':s' => (int) @filesize($path), ':t' => sub_now(), ':b' => (string) $actor]);
    return (int) $pdo->lastInsertId();
}

function sub_file_add(PDO $pdo, $itemId, $reqId, $kind, array $f, array $scope, $actor, $uploaded = true, ?string $dir = null) {
    if (!isset(SUB_FILE_KINDS[(string) $kind])) { return sub_fail('Choose what kind of file this is.'); }
    $item = sub_item_row($pdo, $itemId);
    if (!$item || !sub_in_scope($item, $scope)) { return sub_fail('That subscription no longer exists.'); }
    if ($reqId && !sub_req_exists($pdo, $reqId, $itemId)) { return sub_fail('That document no longer exists.'); }
    $stored = sub_store_doc($f, $uploaded, $dir);
    if (!$stored['ok']) { return $stored; }
    $id = _sub_file_insert($pdo, (int) $itemId, $reqId ? (int) $reqId : null, null, $kind, $stored, $actor, $dir);
    return ['ok' => true, 'id' => (int) $itemId, 'fileId' => $id];
}

function sub_files(PDO $pdo, $itemId) {
    $st = $pdo->prepare("SELECT id, ReqID, RenewalID, Kind, Name, Size, CreatedAt FROM sub_files WHERE ItemID = :i ORDER BY id DESC");
    $st->execute([':i' => (int) $itemId]);
    return array_map(function ($f) {
        return ['id' => (int) $f['id'], 'reqId' => $f['ReqID'] === null ? null : (int) $f['ReqID'],
                'renewalId' => $f['RenewalID'] === null ? null : (int) $f['RenewalID'], 'kind' => $f['Kind'],
                'kindLabel' => SUB_FILE_KINDS[$f['Kind']] ?? $f['Kind'], 'name' => (string) $f['Name'], 'size' => (int) $f['Size'], 'at' => $f['CreatedAt']];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Full path of a stored file, or null when the key isn't one of ours / the file is gone. */
function sub_file_path($key, ?string $dir = null) {
    if (!is_string($key) || !preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$#', $key)) { return null; }
    $path = sub_dir($dir) . '/' . $key;
    return is_file($path) ? $path : null;
}

/** The file row when this viewer's company scope covers its item, else null. */
function sub_file_get(PDO $pdo, $id, array $scope) {
    $st = $pdo->prepare("SELECT f.*, s.CompanyID FROM sub_files f JOIN sub_items s ON s.id = f.ItemID WHERE f.id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return ($r && sub_in_scope($r, $scope)) ? $r : null;
}

function sub_file_delete(PDO $pdo, $id, array $scope, ?string $dir = null) {
    $r = sub_file_get($pdo, $id, $scope);
    if (!$r) { return sub_fail('That file no longer exists.'); }
    $pdo->prepare("DELETE FROM sub_files WHERE id = :id")->execute([':id' => (int) $id]);
    $p = sub_file_path($r['FileKey'], $dir);
    if ($p) { @unlink($p); }
    return ['ok' => true, 'id' => (int) $r['ItemID']];
}

/* ------------------------------------------------------------------ reminders */

/**
 * Who is reminded about each entry today: EmpID => [entry, ...], most urgent
 * first. The owner and watchers while it is inside its window; once expired,
 * also everyone with the right in that company (and super users). Only people
 * who still have the right and can sign in.
 */
function sub_digests(PDO $pdo, $today = null, array $onlyFor = []) {
    $today = $today ?: sub_today();
    $items = sub_list($pdo, '', $today, false);
    $holders = sub_right_holders($pdo);
    $byId = [];
    foreach ($items as $i) { $byId[$i['id']] = $i; }
    $out = [];
    foreach (sub_entries($items, true) as $e) {
        $i = $byId[$e['itemId']];
        $to = [];
        foreach (array_merge([$i['ownerId']], $i['watchers']) as $emp) { if ($emp !== '' && isset($holders[$emp])) { $to[$emp] = true; } }
        if ($e['days'] < 0) {
            foreach ($holders as $emp => $h) { if ($h['super'] || $h['company'] === $i['companyId']) { $to[$emp] = true; } }
        }
        foreach (array_keys($to) as $emp) {
            $emp = (string) $emp;
            if ($onlyFor && !in_array($emp, $onlyFor, true)) { continue; }
            $out[$emp][] = $e;
        }
    }
    return $out;
}

/** What one person is being reminded about today (bell, pop-up, dashboard card). */
function sub_my_due(PDO $pdo, $empId, $today = null) {
    try {
        $d = sub_digests($pdo, $today, [(string) $empId]);
        return $d[(string) $empId] ?? [];
    } catch (Throwable $e) {
        return [];   // migration not run
    }
}

/** The digest's words: [title, body]. Most urgent first. */
function sub_digest_text(array $entries) {
    $n = count($entries);
    if ($n === 0) { return ['', '']; }
    $e = $entries[0];
    if ($n === 1) {
        $title = ($e['days'] < 0 ? 'Expired: ' : '') . $e['label'] . ' ' . ($e['days'] < 0 ? '' : sub_when_text($e['days']));
        $body = ($e['days'] < 0 ? ucfirst(sub_when_text($e['days'])) . '. ' : '') . 'Renew it, then tap Mark renewed in WeDo to stop these reminders.';
        return [trim($title), $body];
    }
    $expired = count(array_filter($entries, function ($x) { return $x['days'] < 0; }));
    $title = $n . ' renewals need attention' . ($expired ? ' (' . $expired . ' expired)' : '');
    $parts = [];
    foreach (array_slice($entries, 0, 3) as $x) {
        $parts[] = $x['label'] . ' ' . ($x['days'] < 0 ? 'expired' : ($x['days'] === 0 ? 'today' : 'in ' . $x['days'] . ' day' . ($x['days'] === 1 ? '' : 's')));
    }
    if ($n > 3) { $parts[] = '+' . ($n - 3) . ' more'; }
    return [$title, implode(' · ', $parts)];
}

/**
 * The day's reminders. Safe to call any number of times: the first call of
 * the day (cron, or a page load after SUB_RUN_HOUR) sends; later calls find
 * the day done. Each person gets at most one digest a day (sub_notify_log).
 *
 * @return array{status:string, people?:int, entries?:int}
 *   status: sent | done (already ran today) | early (page load before 8 AM) | busy | off (no migration)
 */
function sub_run_daily(PDO $pdo, $source = 'cron', $now = null, $dryRun = false) {
    $now = $now ?: time();
    $today = date('Y-m-d', $now);
    if ($source === 'page' && (int) date('G', $now) < SUB_RUN_HOUR) { return ['status' => 'early']; }
    if (!sub_ready($pdo)) { return ['status' => 'off']; }
    if ($dryRun) {
        $digests = sub_digests($pdo, $today);
        $out = [];
        foreach ($digests as $emp => $entries) { $out[$emp] = sub_digest_text($entries); }
        return ['status' => 'dry-run', 'people' => count($digests), 'digests' => $out];
    }
    $lock = (int) $pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), '.sub_daily'), 0)")->fetchColumn();
    if ($lock !== 1) { return ['status' => 'busy']; }
    try {
        $st = $pdo->prepare("SELECT * FROM sub_runs WHERE Day = :d");
        $st->execute([':d' => $today]);
        $run = $st->fetch(PDO::FETCH_ASSOC);
        // a run that died half-way (no FinishedAt after 15 minutes) may be picked up again: the log stops doubles
        if ($run && ($run['FinishedAt'] !== null || strtotime($run['StartedAt']) > $now - 900)) { return ['status' => 'done']; }
        $stamp = date('Y-m-d H:i:s', $now);
        if ($run) {
            $pdo->prepare("UPDATE sub_runs SET Source = :s, StartedAt = :t WHERE Day = :d")->execute([':s' => $source, ':t' => $stamp, ':d' => $today]);
        } else {
            $pdo->prepare("INSERT INTO sub_runs (Day, Source, StartedAt) VALUES (:d, :s, :t)")->execute([':d' => $today, ':s' => $source, ':t' => $stamp]);
        }

        require_once __DIR__ . '/push-lib.php';
        $digests = sub_digests($pdo, $today);
        $people = 0; $entries = 0;
        $log = $pdo->prepare("INSERT IGNORE INTO sub_notify_log (Day, EmpID, Entries, Title, Body, SentAt) VALUES (:d, :e, :n, :t, :b, :s)");
        foreach ($digests as $emp => $list) {
            [$title, $body] = sub_digest_text($list);
            $log->execute([':d' => $today, ':e' => (string) $emp, ':n' => count($list), ':t' => mb_substr($title, 0, 100), ':b' => mb_substr($body, 0, 255), ':s' => $stamp]);
            if ($log->rowCount() !== 1) { continue; }   // already reminded today
            push_send($pdo, (string) $emp, $title, $body, ['url' => 'subscriptions', 'tag' => 'sub-reminder']);
            $people++;
            $entries += count($list);
        }
        $pdo->prepare("UPDATE sub_runs SET FinishedAt = :f, People = People + :p, Entries = Entries + :n WHERE Day = :d")
            ->execute([':f' => date('Y-m-d H:i:s'), ':p' => $people, ':n' => $entries, ':d' => $today]);
        return ['status' => 'sent', 'people' => $people, 'entries' => $entries];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '.sub_daily'))");
    }
}

/** The last few days' runs and today's digests, for the Reminders tab. */
function sub_reminder_log(PDO $pdo, array $scope, $days = 14) {
    $runs = $pdo->query("SELECT * FROM sub_runs ORDER BY Day DESC LIMIT " . (int) $days)->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare("SELECT l.*, e.EmpFN, e.EmpLN FROM sub_notify_log l LEFT JOIN employees e ON e.EmpID = l.EmpID
                         WHERE l.Day >= :d ORDER BY l.Day DESC, l.id");
    $st->execute([':d' => date('Y-m-d', strtotime('-' . ((int) $days - 1) . ' days'))]);
    $holders = sub_right_holders($pdo);
    $sent = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $emp = (string) $r['EmpID'];
        if (!$scope['all'] && isset($holders[$emp]) && $holders[$emp]['company'] !== $scope['company'] && !$holders[$emp]['super']) { continue; }
        $sent[] = ['day' => $r['Day'], 'emp' => trim($r['EmpFN'] . ' ' . $r['EmpLN']) ?: $emp, 'entries' => (int) $r['Entries'],
                   'title' => $r['Title'], 'body' => $r['Body'], 'at' => $r['SentAt']];
    }
    return ['runs' => array_map(function ($r) {
        return ['day' => $r['Day'], 'source' => $r['Source'], 'startedAt' => $r['StartedAt'], 'finishedAt' => $r['FinishedAt'],
                'people' => (int) $r['People'], 'entries' => (int) $r['Entries']];
    }, $runs), 'sent' => $sent, 'runHour' => SUB_RUN_HOUR];
}

/* ------------------------------------------------------------------ logins vault
   Usernames, passwords and notes of the accounts behind each subscription
   (registrar, cPanel, PhilGEPS portal ...). Separate right `subcreds` on top of
   `subscriptions`. Stored encrypted (AES-256-GCM, key 'sub_vault_key' in
   config.local.php or env WEDO_SUB_VAULT_KEY) and bound to their item and
   field, so a database dump or backup gives nothing away and a value cannot be
   moved to another item. The password and notes are only sent when someone
   asks to see or copy them, and every such request is logged (sub_cred_log).
   ------------------------------------------------------------------ */

const SUB_CRED_ACTIONS = ['view' => 'Viewed', 'copy' => 'Copied', 'add' => 'Added', 'edit' => 'Changed', 'delete' => 'Deleted'];

/** May this viewer see and change saved logins? (needs both rights) */
function sub_can_creds(PDO $pdo) {
    try { return sub_can_manage($pdo) && lcy_right($pdo, 'subcreds'); } catch (Throwable $e) { return false; }
}

/** The 32-byte vault key (64 hex characters or base64), or null = vault locked on this server. */
function sub_vault_key() {
    $raw = getenv('WEDO_SUB_VAULT_KEY');
    if ($raw === false || $raw === '') {
        require_once __DIR__ . '/bot-guard.php';
        $raw = (string) (wd_local_config()['sub_vault_key'] ?? '');
    }
    $raw = trim((string) $raw);
    if (preg_match('/^[0-9a-fA-F]{64}$/', $raw)) { return hex2bin($raw); }
    $b = $raw === '' ? false : base64_decode($raw, true);
    return ($b !== false && strlen($b) === 32) ? $b : null;
}

/** Encrypt one value of one item's field; '' stays empty (NULL). */
function sub_seal($plain, $key, $itemId, $field) {
    $plain = (string) $plain;
    if ($plain === '') { return null; }
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, "sub:$itemId:$field", 16);
    if ($ct === false) { throw new RuntimeException('Could not encrypt.'); }
    return 'v1:' . base64_encode($iv . $tag . $ct);
}

/** Decrypt; '' for empty, null when it cannot be read (another key, tampered, moved). */
function sub_unseal($sealed, $key, $itemId, $field) {
    if ($sealed === null || $sealed === '') { return ''; }
    if (strncmp($sealed, 'v1:', 3) !== 0) { return null; }
    $b = base64_decode(substr($sealed, 3), true);
    if ($b === false || strlen($b) < 29) { return null; }
    $pt = openssl_decrypt(substr($b, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16), "sub:$itemId:$field");
    return $pt === false ? null : $pt;
}

function sub_cred_row(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT * FROM sub_creds WHERE id = :i");
    $st->execute([':i' => (int) $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function sub_cred_log_add(PDO $pdo, array $cred, $actor, $action) {
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $pdo->prepare("INSERT INTO sub_cred_log (CredID, ItemID, Label, EmpID, Action, Ip, At) VALUES (?,?,?,?,?,?,?)")
        ->execute([(int) $cred['id'], (int) $cred['ItemID'], (string) $cred['Label'], (string) $actor, $action, $ip ?: null, sub_now()]);
}

/**
 * What the item panel shows about its logins. Without the right: only how many
 * there are. With it: name, sign-in page and username (never the password or
 * notes), plus the recent access log.
 */
function sub_vault_of(PDO $pdo, $itemId) {
    $st = $pdo->prepare("SELECT * FROM sub_creds WHERE ItemID = :i ORDER BY Label, id");
    $st->execute([':i' => (int) $itemId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $can = sub_can_creds($pdo);
    $out = ['can' => $can, 'count' => count($rows), 'locked' => false, 'creds' => [], 'log' => []];
    if (!$can) { return $out; }
    $key = sub_vault_key();
    $out['locked'] = $key === null;
    if ($key === null) { return $out; }
    foreach ($rows as $r) {
        $user = sub_unseal($r['UserEnc'], $key, $r['ItemID'], 'user');
        $out['creds'][] = ['id' => (int) $r['id'], 'label' => $r['Label'], 'url' => (string) $r['Url'], 'username' => $user ?? '',
                           'unreadable' => $user === null, 'hasSecret' => (string) $r['SecretEnc'] !== '', 'hasNotes' => (string) $r['NotesEnc'] !== ''];
    }
    $lg = $pdo->prepare("SELECT * FROM sub_cred_log WHERE ItemID = :i ORDER BY id DESC LIMIT 20");
    $lg->execute([':i' => (int) $itemId]);
    $log = $lg->fetchAll(PDO::FETCH_ASSOC);
    $names = sub_names($pdo, array_column($log, 'EmpID'));
    $out['log'] = array_map(function ($l) use ($names) {
        return ['at' => $l['At'], 'by' => $names[(string) $l['EmpID']] ?? (string) $l['EmpID'], 'action' => SUB_CRED_ACTIONS[$l['Action']] ?? $l['Action'], 'label' => $l['Label']];
    }, $log);
    return $out;
}

/** A login with its password and notes, for $why = view | copy | edit (logged as view or copy). */
function sub_cred_reveal(PDO $pdo, $id, $why, array $scope, $actor) {
    $c = sub_cred_row($pdo, $id);
    $item = $c ? sub_item_row($pdo, $c['ItemID']) : null;
    if (!$c || !$item || !sub_in_scope($item, $scope)) { return sub_fail('That login no longer exists.'); }
    $key = sub_vault_key();
    if ($key === null) { return sub_fail('The logins vault is locked on this server (no key set up).'); }
    $vals = [];
    foreach (['user' => 'UserEnc', 'secret' => 'SecretEnc', 'notes' => 'NotesEnc'] as $f => $col) {
        $vals[$f] = sub_unseal($c[$col], $key, $c['ItemID'], $f);
        if ($vals[$f] === null) { return sub_fail("This login cannot be read with this server's key. Enter it again."); }
    }
    sub_cred_log_add($pdo, $c, $actor, $why === 'copy' ? 'copy' : 'view');
    return ['ok' => true, 'cred' => ['id' => (int) $c['id'], 'itemId' => (int) $c['ItemID'], 'label' => $c['Label'], 'url' => (string) $c['Url'],
            'username' => $vals['user'], 'secret' => $vals['secret'], 'notes' => $vals['notes']]];
}

/** Add (id 0) or change a login. Every field is written as given: an empty password removes it. */
function sub_cred_save(PDO $pdo, array $d, array $scope, $actor) {
    $id = (int) ($d['id'] ?? 0);
    $old = $id ? sub_cred_row($pdo, $id) : null;
    if ($id && !$old) { return sub_fail('That login no longer exists.'); }
    $itemId = $old ? (int) $old['ItemID'] : (int) ($d['itemId'] ?? 0);
    $item = sub_item_row($pdo, $itemId);
    if (!$item || !sub_in_scope($item, $scope)) { return sub_fail('That subscription no longer exists.'); }
    $key = sub_vault_key();
    if ($key === null) { return sub_fail('The logins vault is locked on this server (no key set up).'); }
    $label = trim((string) ($d['label'] ?? ''));
    $url = trim((string) ($d['url'] ?? ''));
    $user = trim((string) ($d['username'] ?? ''));
    $secret = (string) ($d['secret'] ?? '');
    $notes = trim((string) ($d['notes'] ?? ''));
    if ($label === '') { return sub_fail('Give the login a name, e.g. Registrar account or cPanel.'); }
    if (mb_strlen($label) > 100) { return sub_fail('The name is too long (100 characters at most).'); }
    if ($url !== '' && !preg_match('#^https?://\S+$#i', $url)) { return sub_fail('The sign-in page must be a web address starting with https://'); }
    if (strlen($url) > 255) { return sub_fail('The sign-in page address is too long.'); }
    if (mb_strlen($user) > 255 || strlen($secret) > 1000 || mb_strlen($notes) > 2000) { return sub_fail('One of the fields is too long.'); }
    if ($user === '' && $secret === '' && $notes === '') { return sub_fail('Enter at least a username or a password.'); }
    $vals = [$label, $url !== '' ? $url : null, sub_seal($user, $key, $itemId, 'user'), sub_seal($secret, $key, $itemId, 'secret'),
             sub_seal($notes, $key, $itemId, 'notes'), sub_now(), (string) $actor];
    if ($old) {
        $pdo->prepare("UPDATE sub_creds SET Label = ?, Url = ?, UserEnc = ?, SecretEnc = ?, NotesEnc = ?, UpdatedAt = ?, UpdatedBy = ? WHERE id = ?")
            ->execute(array_merge($vals, [$id]));
    } else {
        $pdo->prepare("INSERT INTO sub_creds (Label, Url, UserEnc, SecretEnc, NotesEnc, CreatedAt, CreatedBy, ItemID) VALUES (?,?,?,?,?,?,?,?)")
            ->execute(array_merge($vals, [$itemId]));
        $id = (int) $pdo->lastInsertId();
    }
    sub_cred_log_add($pdo, ['id' => $id, 'ItemID' => $itemId, 'Label' => $label], $actor, $old ? 'edit' : 'add');
    return ['ok' => true, 'id' => $id, 'itemId' => $itemId];
}

function sub_cred_delete(PDO $pdo, $id, array $scope, $actor) {
    $c = sub_cred_row($pdo, $id);
    $item = $c ? sub_item_row($pdo, $c['ItemID']) : null;
    if (!$c || !$item || !sub_in_scope($item, $scope)) { return sub_fail('That login no longer exists.'); }
    $pdo->prepare("DELETE FROM sub_creds WHERE id = :i")->execute([':i' => (int) $id]);
    sub_cred_log_add($pdo, $c, $actor, 'delete');
    return ['ok' => true, 'itemId' => (int) $c['ItemID']];
}
