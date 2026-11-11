<?php
/* ==========================================================================
   inventory-tsdr-lib.php  —  TSDR (Technical Services Data Report): the IT
   service form for equipment handed to an employee. Same sections as the old
   projInventory form: requisitioner (account, services, items, approver),
   receiver (requested from), SysAd (status, evaluation, accomplished).

   Numbers are YYMMDD_<running number>; the running number continues the old
   app's count, so the first TSDR made here after the import is 229.
   An issued item "needs a TSDR" until one made for its holder on or after the
   day it was issued lists it.
   Screens: inventory.php (People > Make TSDR, TSDR tab). Writes:
   query/inventory-action.php. Tables: sql/2026-11-11-add-inventory-tsdr.sql.
   ========================================================================== */
require_once __DIR__ . '/inventory-lib.php';

const INV_TSDR_SERVICES = [
    'install' => 'Install', 'update' => 'Update', 'troubleshoot' => 'Troubleshoot', 'transfer' => 'Transfer',
    'format' => 'Format', 'load' => 'Load', 'create' => 'Create', 'pullout' => 'Pull-out',
    'convert' => 'Convert', 'replace' => 'Replace', 'configure' => 'Configure', 'patch' => 'Patch',
    'newsoftware' => 'New software', 'software' => 'Software revisions', 'actdeact' => 'Activated / Deactivated', 'others' => 'Others',
];
const INV_TSDR_FROM   = ['it' => 'IT Specialist', 'dev' => 'Software Developer'];
const INV_TSDR_STATUS = ['done' => 'Done', 'pending' => 'Pending', 'cannot' => 'Cannot be done', 'others' => 'Others'];
const INV_TSDR_LIMIT  = 500;

/** Has the TSDR migration run on this server? */
function inv_tsdr_ready(PDO $pdo) {
    try {
        $pdo->query("SELECT 1 FROM inv_tsdr_items LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** What a TSDR prints for one item: "Laptop · LENOVO 81WH · SN PF37WYW6 (EQP-0001)". */
function inv_tsdr_item_text(array $i) {
    $make = trim(($i['brand'] ?? '') . ' ' . ($i['model'] ?? ''));
    $parts = array_filter([(string) $i['name'], $make, ($i['serial'] ?? '') !== '' ? 'SN ' . $i['serial'] : '']);
    return mb_substr(implode(' · ', $parts) . ' (' . $i['tag'] . ')', 0, 250);
}

/** Services as stored ("install,configure") <-> a clean list of known codes. */
function inv_tsdr_services($v) {
    $list = is_array($v) ? $v : explode(',', (string) $v);
    return array_values(array_intersect(array_keys(INV_TSDR_SERVICES), array_map('trim', $list)));
}

/** Ids of the employee's issued items that no TSDR made for them since the issue lists yet. */
function inv_tsdr_pending_ids(PDO $pdo, $empId) {
    $st = $pdo->prepare("SELECT i.id FROM inv_items i
        WHERE i.HolderEmpID = :e AND i.Status NOT IN ('disposed','merged')
          AND NOT EXISTS (SELECT 1 FROM inv_tsdr_items ti JOIN inv_tsdr t ON t.id = ti.TsdrID
                          WHERE ti.ItemID = i.id AND t.EmpID = i.HolderEmpID AND DATE(t.CreatedAt) >= COALESCE(i.IssuedOn, '1000-01-01'))");
    $st->execute([':e' => (string) $empId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Everything the Make TSDR form starts from. */
function inv_tsdr_draft(PDO $pdo, $empId, $actor) {
    $a = inv_accountability($pdo, $empId);
    if (!$a) { return null; }
    $pending = array_flip(inv_tsdr_pending_ids($pdo, $a['employee']['empId']));
    $items = array_map(function ($i) use ($pending) {
        return ['id' => $i['id'], 'tag' => $i['tag'], 'text' => inv_tsdr_item_text($i), 'issuedOn' => $i['issuedOn'], 'pending' => isset($pending[$i['id']])];
    }, array_values(array_filter($a['items'], function ($i) { return $i['kind'] !== 'consumable'; })));
    $me = lcy_employee($pdo, $actor);
    return [
        'employee' => $a['employee'], 'items' => $items,
        'defaults' => ['accountDept' => $a['employee']['department'], 'dateNeeded' => inv_today(), 'requestedFrom' => 'it',
                       'status' => 'done', 'sysadId' => $me ? $me['empId'] : '', 'sysad' => $me ? $me['name'] : '',
                       'accomplishedAt' => date('Y-m-d\TH:i')],
        'nextNo' => inv_tsdr_peek_no($pdo),
    ];
}

/** "261111_01": the date and the running number, at least two digits. */
function inv_tsdr_no($seq) {
    return date('ymd') . '_' . str_pad((string) (int) $seq, 2, '0', STR_PAD_LEFT);
}

/** The number the next TSDR would get (display only; the real one is taken when it is saved). */
function inv_tsdr_peek_no(PDO $pdo) {
    return inv_tsdr_no((int) $pdo->query("SELECT COALESCE(MAX(Seq), 0) + 1 FROM inv_tsdr")->fetchColumn());
}

/** Take the next number inside the caller's transaction (locks the top of the Seq index). */
function _inv_tsdr_next_tx(PDO $pdo) {
    $seq = (int) $pdo->query("SELECT COALESCE(MAX(Seq), 0) + 1 FROM inv_tsdr FOR UPDATE")->fetchColumn();
    return [inv_tsdr_no($seq), $seq];
}

/** "2026-11-11T14:05" / "2026-11-11 14:05" -> "2026-11-11 14:05:00", or null. */
function inv_tsdr_datetime($v) {
    $v = str_replace('T', ' ', trim((string) $v));
    foreach (['Y-m-d H:i', 'Y-m-d H:i:s'] as $f) {
        $d = DateTime::createFromFormat('!' . $f, $v);
        if ($d && $d->format($f) === $v) { return $d->format('Y-m-d H:i:s'); }
    }
    return null;
}

/**
 * Make ($d['id'] empty) or edit a TSDR. Items must be issued to the
 * requisitioner, except ones already on this TSDR (an edit may keep them after
 * they were returned).
 * @return array{ok:bool, msg?:string, id?:int, no?:string}
 */
function inv_tsdr_save(PDO $pdo, array $d, $actor) {
    $id  = (int) ($d['id'] ?? 0);
    $old = $id ? inv_tsdr_get($pdo, $id) : null;
    if ($id && !$old) { return inv_fail('That TSDR no longer exists.'); }

    $emp = lcy_employee($pdo, $old ? $old['empId'] : (string) ($d['emp'] ?? ''));
    if (!$emp) { return inv_fail('Choose the requisitioner.'); }
    $services = inv_tsdr_services($d['services'] ?? []);
    if (!$services) { return inv_fail('Tick at least one service being requested.'); }
    $other = inv_opt($d['otherService'] ?? '', 200);
    if (in_array('others', $services, true) && $other === null) { return inv_fail('Say what the other service is.'); }
    $from   = isset(INV_TSDR_FROM[$d['requestedFrom'] ?? '']) ? $d['requestedFrom'] : 'it';
    $status = isset(INV_TSDR_STATUS[$d['status'] ?? '']) ? $d['status'] : null;
    if (!$status) { return inv_fail('Choose the status of the request.'); }
    $needed = lcy_date($d['dateNeeded'] ?? '') ?: null;
    $doneAt = inv_tsdr_datetime($d['accomplishedAt'] ?? '');
    if ($status === 'done' && !$doneAt) { $doneAt = inv_now(); }

    $approver = null;
    if (trim((string) ($d['approvedBy'] ?? '')) !== '') {
        $approver = lcy_employee($pdo, $d['approvedBy']);
        if (!$approver) { return inv_fail('The approver is not in the employee list.'); }
    }
    $sysad = lcy_employee($pdo, trim((string) ($d['sysad'] ?? '')) !== '' ? $d['sysad'] : $actor);

    // items: issued to the requisitioner now, or already on this TSDR
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($d['items'] ?? [])))));
    $keep = $old ? array_column(array_filter($old['items'], function ($x) { return $x['itemId']; }), 'text', 'itemId') : [];
    $lines = [];
    foreach ($ids as $itemId) {
        $it = inv_item($pdo, $itemId);
        if ($it && $it['holderId'] === $emp['empId']) { $lines[] = [$itemId, inv_tsdr_item_text($it)]; continue; }
        if (isset($keep[$itemId])) { $lines[] = [$itemId, $keep[$itemId]]; continue; }
        return inv_fail(($it ? $it['tag'] : 'An item') . ' is not issued to ' . $emp['name'] . '.');
    }
    if ($old) {   // lines of the old app that never matched an item stay as printed
        foreach ($old['items'] as $x) { if (!$x['itemId']) { $lines[] = [null, $x['text']]; } }
    }

    $f = [
        ':acc' => inv_opt($d['accountDept'] ?? '', 120), ':need' => $needed, ':svc' => implode(',', $services),
        ':oth' => in_array('others', $services, true) ? $other : null,
        ':apid' => $approver ? $approver['empId'] : null, ':ap' => $approver ? $approver['name'] : null,
        ':from' => $from, ':rec' => inv_opt($d['receivedBy'] ?? '', 150),
        ':sid' => $sysad ? $sysad['empId'] : null, ':sys' => $sysad ? $sysad['name'] : null,
        ':st' => $status, ':sd' => inv_opt($d['statusDetails'] ?? '', 300),
        ':ev' => inv_opt($d['evaluation'] ?? '', 500), ':rc' => inv_opt($d['recommendation'] ?? '', 500),
        ':done' => $doneAt, ':u' => inv_now(),
    ];
    $r = _inv_tx($pdo, function () use ($pdo, $id, $emp, $f, $lines, $actor) {
        if ($id) {
            $pdo->prepare("UPDATE inv_tsdr SET AccountDept = :acc, DateNeeded = :need, Services = :svc, OtherService = :oth,
                    ApprovedByEmpID = :apid, ApprovedBy = :ap, RequestedFrom = :from, ReceivedBy = :rec, SysadEmpID = :sid, Sysad = :sys,
                    Status = :st, StatusDetails = :sd, Evaluation = :ev, Recommendation = :rc, AccomplishedAt = :done, UpdatedAt = :u
                WHERE id = :id")->execute($f + [':id' => $id]);
            $pdo->prepare("DELETE FROM inv_tsdr_items WHERE TsdrID = :t")->execute([':t' => $id]);
            $no = $pdo->query("SELECT TsdrNo FROM inv_tsdr WHERE id = " . (int) $id)->fetchColumn();
        } else {
            list($no, $seq) = _inv_tsdr_next_tx($pdo);
            $pdo->prepare("INSERT INTO inv_tsdr (TsdrNo, Seq, EmpID, Requisitioner, AccountDept, DateNeeded, Services, OtherService,
                    ApprovedByEmpID, ApprovedBy, RequestedFrom, ReceivedBy, SysadEmpID, Sysad, Status, StatusDetails, Evaluation,
                    Recommendation, AccomplishedAt, Source, CreatedBy, CreatedAt, UpdatedAt)
                VALUES (:no, :seq, :emp, :req, :acc, :need, :svc, :oth, :apid, :ap, :from, :rec, :sid, :sys, :st, :sd, :ev, :rc, :done,
                    'wedo', :by, :u, :u)")
                ->execute($f + [':no' => $no, ':seq' => $seq, ':emp' => $emp['empId'], ':req' => $emp['name'], ':by' => (string) $actor]);
            $id = (int) $pdo->lastInsertId();
        }
        $ins = $pdo->prepare("INSERT INTO inv_tsdr_items (TsdrID, ItemID, ItemText) VALUES (:t, :i, :x)");
        foreach ($lines as $l) { $ins->execute([':t' => $id, ':i' => $l[0], ':x' => $l[1]]); }
        return ['ok' => true, 'id' => $id, 'no' => $no];
    });
    if ($r['ok']) { inv_audit($pdo, ($old ? 'edited' : 'made') . ' TSDR ' . $r['no'] . ' for ' . $emp['empId']); }
    return $r;
}

function _inv_tsdr_out(array $r) {
    $services = inv_tsdr_services($r['Services']);
    return [
        'id' => (int) $r['id'], 'no' => $r['TsdrNo'], 'empId' => $r['EmpID'], 'requisitioner' => $r['Requisitioner'],
        'accountDept' => (string) $r['AccountDept'], 'dateNeeded' => $r['DateNeeded'],
        'services' => $services, 'serviceLabels' => array_map(function ($s) { return INV_TSDR_SERVICES[$s]; }, $services),
        'otherService' => (string) $r['OtherService'],
        'approvedById' => (string) $r['ApprovedByEmpID'], 'approvedBy' => (string) $r['ApprovedBy'],
        'requestedFrom' => $r['RequestedFrom'], 'receivedBy' => (string) $r['ReceivedBy'],
        'sysadId' => (string) $r['SysadEmpID'], 'sysad' => (string) $r['Sysad'],
        'status' => $r['Status'], 'statusLabel' => INV_TSDR_STATUS[$r['Status']] ?? $r['Status'],
        'statusDetails' => (string) $r['StatusDetails'], 'evaluation' => (string) $r['Evaluation'],
        'recommendation' => (string) $r['Recommendation'], 'accomplishedAt' => $r['AccomplishedAt'],
        'source' => $r['Source'], 'createdAt' => $r['CreatedAt'],
    ];
}

function inv_tsdr_get(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT * FROM inv_tsdr WHERE id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { return null; }
    $t = _inv_tsdr_out($r);
    $st = $pdo->prepare("SELECT ti.ItemID, ti.ItemText, i.TagNo FROM inv_tsdr_items ti LEFT JOIN inv_items i ON i.id = ti.ItemID WHERE ti.TsdrID = :t ORDER BY ti.id");
    $st->execute([':t' => $t['id']]);
    $t['items'] = array_map(function ($x) {
        return ['itemId' => $x['ItemID'] === null ? null : (int) $x['ItemID'], 'tag' => (string) $x['TagNo'], 'text' => $x['ItemText']];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
    return $t;
}

/** Newest first. Filters: emp (EmpID), q (number, name, account or item words). */
function inv_tsdr_list(PDO $pdo, array $f = []) {
    $where = ['1 = 1'];
    $args = [];
    if (!empty($f['emp'])) { $where[] = 't.EmpID = :emp'; $args[':emp'] = (string) $f['emp']; }
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        foreach (array_slice(preg_split('/\s+/u', $q), 0, 4) as $n => $w) {
            $like = '%' . addcslashes($w, '%_\\') . '%';
            $or = [];
            foreach (['t.TsdrNo', 't.Requisitioner', 't.EmpID', 't.AccountDept', 't.Sysad'] as $k => $col) {
                $or[] = "$col LIKE :q{$n}x{$k}";
                $args[":q{$n}x{$k}"] = $like;
            }
            $or[] = "EXISTS (SELECT 1 FROM inv_tsdr_items s WHERE s.TsdrID = t.id AND s.ItemText LIKE :q{$n}it)";
            $args[":q{$n}it"] = $like;
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
    }
    $st = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM inv_tsdr_items s WHERE s.TsdrID = t.id) AS ItemCount
        FROM inv_tsdr t WHERE " . implode(' AND ', $where) . " ORDER BY t.Seq DESC, t.id DESC LIMIT " . INV_TSDR_LIMIT);
    $st->execute($args);
    return array_map(function ($r) { return _inv_tsdr_out($r) + ['itemCount' => (int) $r['ItemCount']]; }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * For issued items: the newest TSDR made for their current holder since the
 * issue that lists them (the same rule as "No TSDR yet"). Adds 'tsdr' =>
 * {id, no, status, statusLabel, createdAt} or null to each item.
 */
function inv_tsdr_attach(PDO $pdo, array $items) {
    $ids = [];
    foreach ($items as $i) { if (!empty($i['holderId'])) { $ids[] = (int) $i['id']; } }
    $map = [];
    if ($ids) {
        $rows = $pdo->query("SELECT ti.ItemID, t.id, t.TsdrNo, t.Status, t.CreatedAt FROM inv_tsdr_items ti
            JOIN inv_tsdr t ON t.id = ti.TsdrID JOIN inv_items i ON i.id = ti.ItemID
            WHERE ti.ItemID IN (" . implode(',', $ids) . ") AND t.EmpID = i.HolderEmpID
              AND DATE(t.CreatedAt) >= COALESCE(i.IssuedOn, '1000-01-01')
            ORDER BY t.Seq, t.id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {   // the newest one wins
            $map[(int) $r['ItemID']] = ['id' => (int) $r['id'], 'no' => $r['TsdrNo'], 'status' => $r['Status'],
                'statusLabel' => INV_TSDR_STATUS[$r['Status']] ?? $r['Status'], 'createdAt' => $r['CreatedAt']];
        }
    }
    foreach ($items as &$i) { $i['tsdr'] = $map[(int) $i['id']] ?? null; }
    unset($i);
    return $items;
}

/** The TSDRs an item is on, newest first. */
function inv_tsdr_for_item(PDO $pdo, $itemId) {
    $st = $pdo->prepare("SELECT DISTINCT t.id, t.TsdrNo, t.Requisitioner, t.Status, t.CreatedAt, t.Seq FROM inv_tsdr t
        JOIN inv_tsdr_items ti ON ti.TsdrID = t.id WHERE ti.ItemID = :i ORDER BY t.Seq DESC, t.id DESC");
    $st->execute([':i' => (int) $itemId]);
    return array_map(function ($r) {
        return ['id' => (int) $r['id'], 'no' => $r['TsdrNo'], 'requisitioner' => $r['Requisitioner'], 'status' => $r['Status'],
                'statusLabel' => INV_TSDR_STATUS[$r['Status']] ?? $r['Status'], 'createdAt' => $r['CreatedAt']];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Lists for the form's choices. */
function inv_tsdr_lookups() {
    return ['services' => INV_TSDR_SERVICES, 'from' => INV_TSDR_FROM, 'statuses' => INV_TSDR_STATUS];
}
