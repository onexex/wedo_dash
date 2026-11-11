<?php
/* ==========================================================================
   inventory-import-lib.php  —  brings the IT equipment register of the old
   Laravel app "projInventory" (database dbinventory) into Inventory.

     tb_equipment  -> inv_items        one row per unit, tag EQP-0001 ...
     tb_eqpname    -> item name + category (see INVIMP_CATEGORY)
     tb_brand      -> inv_items.Brand (text)
     tb_specsdsc   -> inv_items.Description ("RAM: 8GB; SSD: 256GB")
     tb_eqpdeploy  -> holder of the item + issued / returned history
     tbl_tsdr*     -> inv_tsdr + inv_tsdr_items (invimp_tsdr_run, same number, e.g. 261110_228)

   Rules:
     Stock / Deployed / Defective -> Status active (Defective = condition damaged)
     Disposed                     -> Status disposed, method "other"
     everything sits in one location (default "IT Room"); a deployed item is
     issued to the employee in deployed_to (an EmpID, same as employees.EmpID)
     and counts as acknowledged when its TSDR was issued (issued_TSDR = 2).

   Safe to run again: an item whose serial number is already in inv_items is
   skipped. The whole run is one transaction; a dry run rolls it back, so it
   shows the exact tags a real run would hand out.
   CLI: tools/import-projinventory.php. Tables: sql/2026-11-05-add-inventory.sql.
   ========================================================================== */
require_once __DIR__ . '/inventory-lib.php';
require_once __DIR__ . '/inventory-tsdr-lib.php';

const INVIMP_SOURCE = 'projInventory';

/** tbl_tsdrsbr column -> INV_TSDR_SERVICES code. */
const INVIMP_TSDR_SERVICES = [
    'sbr_install' => 'install', 'sbr_update' => 'update', 'sbr_troubleshoot' => 'troubleshoot', 'sbr_transfer' => 'transfer',
    'sbr_format' => 'format', 'sbr_load' => 'load', 'sbr_create' => 'create', 'sbr_pullout' => 'pullout',
    'sbr_convert' => 'convert', 'sbr_replace' => 'replace', 'sbr_configure' => 'configure', 'sbr_patch' => 'patch',
    'sbr_newsoftware' => 'newsoftware', 'sbr_software' => 'software', 'sbr_actDec' => 'actdeact', 'sbr_others' => 'others',
];
const INVIMP_TSDR_FROM   = [1 => 'it', 2 => 'dev'];
const INVIMP_TSDR_STATUS = [1 => 'done', 2 => 'pending', 3 => 'cannot', 4 => 'others'];

/** tb_eqpname.name (upper case) -> inv_categories.Code. Anything else is EQP. */
const INVIMP_CATEGORY = [
    'MICROWAVE OVEN' => 'APP', 'AIR PURIFIER' => 'APP', 'TV' => 'APP',
    'DOCS' => 'DOC',
    'KEY' => 'OTH', 'LADDER' => 'OTH', 'MULTIFUNCTIONAL WHISTLE' => 'OTH',
];

/** Words kept upper case when an equipment name is title-cased. */
const INVIMP_ACRONYMS = ['AVR', 'CAT6', 'GSM', 'ID', 'IP', 'NVR', 'SIM', 'TV', 'UPS', 'UTP'];

/** "IP CAMERA" -> "IP Camera", "EXTENSION  WHEEL" -> "Extension Wheel". */
function invimp_name($raw) {
    $words = preg_split('/\s+/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', array_map(function ($w) {
        $u = strtoupper($w);
        return in_array($u, INVIMP_ACRONYMS, true) ? $u : ucfirst(strtolower($w));
    }, $words));
}

/** Blank, "N/A", "NA", "-" -> null; otherwise the trimmed, single-spaced text cut to $max. */
function invimp_text($v, $max) {
    $v = lcy_text($v, $max);
    return ($v === '' || in_array(strtoupper($v), ['N/A', 'NA', '-', 'NONE', 'NULL'], true)) ? null : $v;
}

/** "2026-11-10 11:53 AM" (the old app's text dates) or a timestamp -> "Y-m-d H:i:s", null when unusable. */
function invimp_datetime($v) {
    $v = trim((string) $v);
    if ($v === '') { return null; }
    foreach (['Y-m-d / h:i A', 'Y-m-d h:i A', 'Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $f) {
        $d = DateTime::createFromFormat('!' . $f, $v);
        if ($d && $d->format('Y') > 1990) { return $d->format('Y-m-d H:i:s'); }
    }
    return null;
}

/** ['EQP' => ['EQP-0001', 'EQP-0002'], ...] -> "EQP-0001..EQP-0002 (2), OTH-0001 (1)" */
function invimp_tag_summary(array $tags) {
    $parts = [];
    foreach ($tags as $list) {
        $parts[] = (count($list) > 1 ? $list[0] . '..' . end($list) : $list[0]) . ' (' . count($list) . ')';
    }
    return implode(', ', $parts);
}

function invimp_condition($status, $condition) {
    return ($status === 'Defective' || $condition === 'Defective') ? 'damaged' : 'good';
}

/**
 * Run the import. $src reads dbinventory, $dst writes the WeDo database (they
 * may be the same connection). Options: apply (bool, default false = dry run),
 * location (string, default "IT Room"), actor (EmpID written as the importer
 * when the old record has no user).
 * @return array{ok:bool, msg?:string, applied:bool, items:int, issued:int, disposed:int, movements:int,
 *               skipped:array, warnings:array, tags:array<string,string[]>, location:string}
 */
function invimp_run(PDO $src, PDO $dst, array $opt = []) {
    $apply    = !empty($opt['apply']);
    $locName  = lcy_text($opt['location'] ?? 'IT Room', 80) ?: 'IT Room';
    $actor    = (string) ($opt['actor'] ?? '');
    $out = ['ok' => true, 'applied' => false, 'items' => 0, 'issued' => 0, 'disposed' => 0, 'movements' => 0,
            'skipped' => [], 'warnings' => [], 'tags' => [], 'location' => $locName];

    if (!inv_ready($dst)) { return ['ok' => false, 'msg' => 'Inventory is not set up here: run sql/2026-11-05-add-inventory.sql first.'] + $out; }

    // ---- read the old register
    $users  = $src->query("SELECT id, ESID FROM tb_user")->fetchAll(PDO::FETCH_KEY_PAIR);
    $brands = $src->query("SELECT id, brand_name FROM tb_brand")->fetchAll(PDO::FETCH_KEY_PAIR);
    $specs  = [];
    foreach ($src->query("SELECT equip_id, specs_dsc, details FROM tb_specsdsc ORDER BY equip_id, id")->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $label = invimp_text($s['specs_dsc'], 50);
        $value = invimp_text($s['details'], 50);
        if ($label !== null && $value !== null) { $specs[(int) $s['equip_id']][] = invimp_name($label) . ': ' . $value; }
    }
    $deploys = [];
    foreach ($src->query("SELECT * FROM tb_eqpdeploy ORDER BY eqp_id, id")->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $deploys[(int) $d['eqp_id']][] = $d;
    }
    $items = $src->query("SELECT * FROM tb_equipment ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $orphans = array_diff(array_keys($deploys), array_map('intval', array_column($items, 'id')));
    foreach ($orphans as $eqp) {
        $out['warnings'][] = count($deploys[$eqp]) . " deployment record(s) point to equipment #$eqp, which no longer exists — left out.";
    }

    // ---- target lookups
    $cats = [];
    foreach ($dst->query("SELECT id, Code, Kind FROM inv_categories")->fetchAll(PDO::FETCH_ASSOC) as $c) { $cats[$c['Code']] = $c; }
    foreach (array_unique(array_merge(['EQP'], array_values(INVIMP_CATEGORY))) as $code) {
        if (!isset($cats[$code])) { return ['ok' => false, 'msg' => "Category $code is missing in Inventory > Categories."] + $out; }
    }
    $haveSerial = $dst->prepare("SELECT TagNo FROM inv_items WHERE SerialNo = :s LIMIT 1");
    $empExists  = $dst->prepare("SELECT 1 FROM employees WHERE EmpID = :e");
    $known = [];
    $isEmp = function ($id) use ($empExists, &$known) {
        if ($id === null || $id === '') { return false; }
        if (!isset($known[$id])) { $empExists->execute([':e' => $id]); $known[$id] = (bool) $empExists->fetchColumn(); }
        return $known[$id];
    };
    $userEmp = function ($userId) use ($users, $actor) {
        $e = trim((string) ($users[$userId] ?? ''));
        return $e !== '' ? $e : ($actor !== '' ? $actor : null);
    };

    $insItem = $dst->prepare("INSERT INTO inv_items (TagNo, CategoryID, Name, Description, Brand, Model, SerialNo, Qty, Unit,
            ItemCondition, Status, LocationID, HolderEmpID, IssuedOn, AckAt, DocNumber, DisposedOn, DisposalMethod, DisposalNote,
            Notes, CreatedBy, CreatedAt, UpdatedAt)
        VALUES (:tag, :cat, :name, :descr, :brand, :model, :sn, 1, 'pc', :cond, :status, :loc, :holder, :issued, :ack, :doc,
            :dispOn, :dispM, :dispN, :notes, :by, :created, :updated)");
    $insMove = $dst->prepare("INSERT INTO inv_movements (ItemID, Type, FromLocationID, ToLocationID, FromEmpID, ToEmpID, Qty, Notes, ByEmpID, CreatedAt)
        VALUES (:i, :t, :fl, :tl, :fe, :te, 1, :n, :b, :at)");
    $move = function ($itemId, $type, $at, array $f) use ($insMove, &$out) {
        $insMove->execute([':i' => $itemId, ':t' => $type, ':fl' => $f['fromLoc'] ?? null, ':tl' => $f['toLoc'] ?? null,
            ':fe' => $f['fromEmp'] ?? null, ':te' => $f['toEmp'] ?? null,
            ':n' => isset($f['notes']) ? mb_substr((string) $f['notes'], 0, 500) : null, ':b' => $f['by'] ?? null, ':at' => $at]);
        $out['movements']++;
    };

    $dst->beginTransaction();
    try {
        // ---- the one location everything is kept in
        $st = $dst->prepare("SELECT id FROM inv_locations WHERE Name = :n");
        $st->execute([':n' => $locName]);
        $locId = (int) $st->fetchColumn();
        if (!$locId) {
            $dst->prepare("INSERT INTO inv_locations (Name, Notes, Active, CreatedAt) VALUES (:n, :o, 1, :t)")
                ->execute([':n' => $locName, ':o' => 'Created by the ' . INVIMP_SOURCE . ' import', ':t' => inv_now()]);
            $locId = (int) $dst->lastInsertId();
        }

        foreach ($items as $e) {
            $oldId  = (int) $e['id'];
            $serial = invimp_text($e['sn'], 80);
            $label  = trim((string) $e['eqpn_nm']) . ' #' . $oldId;
            if ($serial !== null) {
                $haveSerial->execute([':s' => $serial]);
                if ($tag = $haveSerial->fetchColumn()) { $out['skipped'][] = "$label ($serial) is already in Inventory as $tag."; continue; }
            }

            $upper  = strtoupper(preg_replace('/\s+/', ' ', trim((string) $e['eqpn_nm'])));
            $cat    = $cats[INVIMP_CATEGORY[$upper] ?? 'EQP'];
            $status = trim((string) $e['status']);
            $brandRaw = trim((string) $e['brand']);
            $brand  = invimp_text(ctype_digit($brandRaw) ? ($brands[$brandRaw] ?? '') : $brandRaw, 80);
            $created = invimp_datetime($e['created_at']) ?: inv_now();
            $updated = invimp_datetime($e['updated_at']) ?: $created;
            $by      = $userEmp($e['user_id']);
            $remarks = invimp_text($e['remarks'], 400);
            $notes   = 'From ' . INVIMP_SOURCE . ' #' . $oldId . ($remarks !== null ? '. ' . $remarks : '');

            // the open deployment, if any: the last record still "Deployed"
            $history = $deploys[$oldId] ?? [];
            $open = null;
            foreach ($history as $d) { if ($d['status'] === 'Deployed') { $open = $d; } }
            $holder = null; $issuedOn = null; $ack = null;
            if ($status === 'Deployed' && $open) {
                if ($isEmp($open['deployed_to'])) {
                    $holder   = $open['deployed_to'];
                    $issuedAt = invimp_datetime($open['date_Dep']) ?: $updated;
                    $issuedOn = substr($issuedAt, 0, 10);
                    $ack      = (int) $open['issued_TSDR'] === 2 ? $issuedAt : null;
                } else {
                    $out['warnings'][] = "$label was deployed to " . ($open['deployed_to'] ?: '(blank)') . ', who is not in employees — imported as not issued.';
                }
            } elseif ($status === 'Deployed') {
                $out['warnings'][] = "$label is marked Deployed but has no open deployment record — imported as not issued.";
            }
            $disposed = $status === 'Disposed';

            $tag = _inv_next_tag_tx($dst, $cat['id']);
            $insItem->execute([
                ':tag' => $tag, ':cat' => $cat['id'], ':name' => invimp_name($e['eqpn_nm']) ?: 'Equipment',
                ':descr' => isset($specs[$oldId]) ? mb_substr(implode('; ', $specs[$oldId]), 0, 500) : null,
                ':brand' => $brand, ':model' => invimp_text($e['model'], 80), ':sn' => $serial,
                ':cond' => invimp_condition($status, $e['condition']), ':status' => $disposed ? 'disposed' : 'active',
                ':loc' => $locId, ':holder' => $holder, ':issued' => $issuedOn, ':ack' => $ack,
                ':doc' => $cat['Kind'] === 'document' ? $serial : null,
                ':dispOn' => $disposed ? substr($updated, 0, 10) : null, ':dispM' => $disposed ? 'other' : null,
                ':dispN' => $disposed ? ($remarks !== null ? mb_substr($remarks, 0, 300) : 'Disposed in ' . INVIMP_SOURCE) : null,
                ':notes' => mb_substr($notes, 0, 500), ':by' => $by, ':created' => $created, ':updated' => $updated,
            ]);
            $itemId = (int) $dst->lastInsertId();
            $out['items']++;
            $out['tags'][$cat['Code']][] = $tag;

            // ---- history: added, then every issue / return by date (the screen lists by row id), then disposal
            $move($itemId, 'added', $created, ['toLoc' => $locId, 'by' => $by, 'notes' => 'Imported from ' . INVIMP_SOURCE . ' (equipment #' . $oldId . ')']);
            $custody = [];
            foreach ($history as $d) {
                $emp = trim((string) $d['deployed_to']) ?: null;
                $dBy = $userEmp($d['user_id']);
                $remark = invimp_text($d['remarks'], 450);
                if ($emp === null) { $remark = 'Recipient not recorded in ' . INVIMP_SOURCE . ($remark !== null ? '. ' . $remark : ''); }
                $dep = invimp_datetime($d['date_Dep']) ?: invimp_datetime($d['created_at']);
                if ($dep) { $custody[] = [$dep, count($custody), 'issued', ['fromLoc' => $locId, 'toEmp' => $emp, 'by' => $dBy, 'notes' => $remark]]; }
                if ($d['status'] === 'Returned') {
                    $ret = invimp_datetime($d['date_Ret']) ?: invimp_datetime($d['updated_at']);
                    if ($ret) { $custody[] = [$ret, count($custody), 'returned', ['fromEmp' => $emp, 'toLoc' => $locId, 'by' => $dBy]]; }
                }
            }
            usort($custody, function ($a, $b) { return [$a[0], $a[1]] <=> [$b[0], $b[1]]; });
            foreach ($custody as $c) { $move($itemId, $c[2], $c[0], $c[3]); }
            if ($holder) { $out['issued']++; }
            if ($disposed) {
                $move($itemId, 'disposed', $updated, ['fromLoc' => $locId, 'by' => $by, 'notes' => $remarks ?? 'Disposed in ' . INVIMP_SOURCE]);
                $out['disposed']++;
            }
        }

        if ($apply) {
            $dst->commit();
            $out['applied'] = true;
            inv_audit($dst, 'imported ' . $out['items'] . ' items from ' . INVIMP_SOURCE . ' (' . invimp_tag_summary($out['tags']) . ')');
        } else {
            $dst->rollBack();
        }
    } catch (Throwable $ex) {
        if ($dst->inTransaction()) { $dst->rollBack(); }
        throw $ex;
    }
    return $out;
}

/**
 * Bring the old app's TSDRs into inv_tsdr, keeping their numbers. Items are
 * matched to Inventory by serial number, so run invimp_run first. Safe to run
 * again: a number that is already there is skipped. Same options as invimp_run.
 * @return array{ok:bool, msg?:string, applied:bool, tsdrs:int, items:int, unmatched:int, skipped:int, warnings:array}
 */
function invimp_tsdr_run(PDO $src, PDO $dst, array $opt = []) {
    $apply = !empty($opt['apply']);
    $out = ['ok' => true, 'applied' => false, 'tsdrs' => 0, 'items' => 0, 'unmatched' => 0, 'skipped' => 0, 'warnings' => []];
    if (!inv_tsdr_ready($dst)) { return ['ok' => false, 'msg' => 'TSDR is not set up here: run sql/2026-11-11-add-inventory-tsdr.sql first.'] + $out; }

    $sysads = [];
    foreach ($src->query("SELECT empName, ESID FROM tb_user")->fetchAll(PDO::FETCH_ASSOC) as $u) { $sysads[trim((string) $u['empName'])] = trim((string) $u['ESID']); }
    $sbr = [];
    foreach ($src->query("SELECT * FROM tbl_tsdrsbr ORDER BY id_tsdrSbr")->fetchAll(PDO::FETCH_ASSOC) as $s) { $sbr[$s['id_tsdr']] = $s; }   // the last row wins
    $lines = [];
    foreach ($src->query("SELECT ti.id_tsdr, e.eqpn_nm, e.sn FROM tbl_tsdritem ti
            LEFT JOIN tb_eqpdeploy d ON d.id = ti.id_eqp LEFT JOIN tb_equipment e ON e.id = d.eqp_id ORDER BY ti.id_tsdritem")->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $lines[$l['id_tsdr']][] = $l;
    }
    $tsdrs = $src->query("SELECT * FROM tbl_tsdr ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    $names = [];
    $name = function ($empId) use ($dst, &$names) {
        $empId = trim((string) $empId);
        if ($empId === '') { return null; }
        if (!array_key_exists($empId, $names)) { $e = lcy_employee($dst, $empId); $names[$empId] = $e ? $e['name'] : null; }
        return $names[$empId];
    };
    $bySerial = $dst->prepare("SELECT id FROM inv_items WHERE SerialNo = :s LIMIT 1");
    $exists   = $dst->prepare("SELECT 1 FROM inv_tsdr WHERE TsdrNo = :n");
    $insT = $dst->prepare("INSERT INTO inv_tsdr (TsdrNo, Seq, EmpID, Requisitioner, AccountDept, DateNeeded, Services, OtherService,
            ApprovedByEmpID, ApprovedBy, RequestedFrom, ReceivedBy, SysadEmpID, Sysad, Status, StatusDetails, Evaluation, Recommendation,
            AccomplishedAt, Source, CreatedBy, CreatedAt, UpdatedAt)
        VALUES (:no, :seq, :emp, :req, :acc, :need, :svc, :oth, :apid, :ap, :from, :rec, :sid, :sys, :st, :sd, :ev, :rc, :done,
            'projinventory', :by, :c, :u)");
    $insI = $dst->prepare("INSERT INTO inv_tsdr_items (TsdrID, ItemID, ItemText) VALUES (:t, :i, :x)");

    $dst->beginTransaction();
    try {
        foreach ($tsdrs as $t) {
            $no = trim((string) $t['id_tsdr']);
            if (!preg_match('/^\d{6}_(\d+)$/', $no, $m)) { $out['warnings'][] = "TSDR \"$no\" has an unexpected number — left out."; continue; }
            $exists->execute([':n' => $no]);
            if ($exists->fetchColumn()) { $out['skipped']++; continue; }

            $s = $sbr[$no] ?? [];
            $services = [];
            foreach (INVIMP_TSDR_SERVICES as $col => $code) { if ((int) ($s[$col] ?? 0) === 1) { $services[] = $code; } }
            $sysad   = invimp_text($t['tsdr_SPA'], 150);
            $sysadId = $sysad !== null ? ($sysads[$sysad] ?? null) : null;
            $created = invimp_datetime($t['created_at']) ?: inv_now();
            $insT->execute([
                ':no' => $no, ':seq' => (int) $m[1], ':emp' => trim((string) $t['id_Emp']),
                ':req' => $name($t['id_Emp']) ?: (invimp_text($t['tsdr_Requis'], 150) ?: trim((string) $t['id_Emp'])),
                ':acc' => invimp_text($t['tsdr_AccDep'], 120), ':need' => lcy_date($t['tsdr_DateNeeded']) ?: null,
                ':svc' => implode(',', $services), ':oth' => invimp_text($t['tsdr_otherspecify'], 200),
                ':apid' => invimp_text($t['tsdr_ApprovedBy'], 50), ':ap' => $name($t['tsdr_ApprovedBy']) ?: invimp_text($t['tsdr_ApprovedBy'], 150),
                ':from' => INVIMP_TSDR_FROM[(int) $t['tsdr_RequestFrm']] ?? 'it', ':rec' => invimp_text($t['tsdr_RecievedBy'], 150),
                ':sid' => $sysadId, ':sys' => ($sysadId ? $name($sysadId) : null) ?: $sysad,
                ':st' => INVIMP_TSDR_STATUS[(int) $t['tsdr_StatusReq']] ?? 'others',
                ':sd' => invimp_text($t['tsdr_StatusComment'], 300), ':ev' => invimp_text($t['tsdr_Eva'], 500), ':rc' => invimp_text($t['tsdr_Recom'], 500),
                ':done' => invimp_datetime($t['tsdr_DTAccom']), ':by' => $sysadId,
                ':c' => $created, ':u' => invimp_datetime($t['updated_at']) ?: $created,
            ]);
            $tid = (int) $dst->lastInsertId();
            foreach ($lines[$no] ?? [] as $l) {
                $itemId = null;
                $serial = invimp_text($l['sn'], 80);
                if ($serial !== null) { $bySerial->execute([':s' => $serial]); $itemId = (int) $bySerial->fetchColumn() ?: null; }
                $item = $itemId ? inv_item($dst, $itemId) : null;
                $text = $item ? inv_tsdr_item_text($item) : mb_substr(invimp_name($l['eqpn_nm'] ?: 'Item') . ($serial !== null ? ' · SN ' . $serial : ''), 0, 250);
                $insI->execute([':t' => $tid, ':i' => $itemId, ':x' => $text]);
                $out['items']++;
                if (!$itemId) { $out['unmatched']++; }
            }
            $out['tsdrs']++;
        }
        if ($apply) {
            $dst->commit();
            $out['applied'] = true;
            if ($out['tsdrs']) { inv_audit($dst, 'imported ' . $out['tsdrs'] . ' TSDRs from ' . INVIMP_SOURCE); }
        } else {
            $dst->rollBack();
        }
    } catch (Throwable $ex) {
        if ($dst->inTransaction()) { $dst->rollBack(); }
        throw $ex;
    }
    return $out;
}
