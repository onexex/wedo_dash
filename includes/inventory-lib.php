<?php
/* ==========================================================================
   inventory-lib.php  —  rules for the Inventory module.
     - the register: assets, documents and consumables, tagged per category
     - custody: issue / return / transfer, lost / found, disposal; a lot is
       split when only part of it moves, and can be merged back afterwards
     - My Items: the holder confirms receipt and reports problems
     - supplies: stock in / out / adjust through a signed ledger
     - repairs and servicing, warranty and expiry alerts
     - physical counts per location
   Screens: inventory.php, myitems.php. Writes: query/inventory-action.php.
   Files:   query/inventory-file.php. Tables: sql/2026-11-05-add-inventory.sql.

   Every "now" is bound from PHP (Manila): the servers' MySQL runs on UTC, so
   NOW() and CURDATE() would be eight hours behind. Public write functions own
   their transaction; the _inv_*_tx helpers assume one is already open.
   ========================================================================== */
require_once __DIR__ . '/lifecycle-lib.php';

const INV_KINDS      = ['asset' => 'Asset', 'document' => 'Document', 'consumable' => 'Consumable'];
const INV_CONDITIONS = ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'damaged' => 'Damaged'];
const INV_STATUSES   = ['active' => 'In service', 'repair' => 'Under repair', 'lost' => 'Lost', 'disposed' => 'Disposed', 'merged' => 'Merged'];
const INV_DISPOSAL   = ['sold' => 'Sold', 'donated' => 'Donated', 'scrapped' => 'Scrapped', 'returned' => 'Returned to supplier', 'other' => 'Other'];
const INV_FILE_KINDS = ['photo' => 'Photo', 'scan' => 'Scanned copy', 'receipt' => 'Receipt', 'other' => 'Other'];
const INV_DOC_TYPES  = ['pdf', 'jpg', 'jpeg', 'png'];
const INV_TABLE_LIMIT = 1000;
const INV_MAX_UNITS  = 200;   // separate items one Add can create

/* ------------------------------------------------------------------ access */

/** Manages the whole register. Everyone may see their own items (My Items). */
function inv_can_manage(PDO $pdo) { return lcy_right($pdo, 'inventory'); }

/** Per-session token for query/inventory-action.php. */
function inv_csrf_token() {
    if (empty($_SESSION['inv_csrf'])) { $_SESSION['inv_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['inv_csrf'];
}

/** Has the migration run on this server? */
function inv_ready(PDO $pdo) {
    try {
        $pdo->query("SELECT 1 FROM inv_count_lines LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/* ------------------------------------------------------------------ helpers */

function inv_now() { return date('Y-m-d H:i:s'); }
function inv_today() { return date('Y-m-d'); }
function inv_audit(PDO $pdo, $text) { lcy_audit($pdo, 'Inventory: ' . $text); }
function inv_fail($msg) { return ['ok' => false, 'msg' => $msg]; }

/** Trimmed text, or null when empty (non-strict MySQL would otherwise keep ''). */
function inv_opt($v, $max) {
    $v = lcy_text($v, $max);
    return $v === '' ? null : $v;
}

/** A money amount, or null when blank or unusable. */
function inv_money($v) {
    $v = str_replace(',', '', trim((string) $v));
    if ($v === '' || !is_numeric($v) || (float) $v < 0 || (float) $v > 9999999999.99) { return null; }
    return number_format((float) $v, 2, '.', '');
}

/** A whole number, or null. */
function inv_int($v) {
    $v = trim((string) $v);
    return preg_match('/^-?\d{1,9}$/', $v) ? (int) $v : null;
}

/** Tag numbers are upper case; only letters, digits and dashes. */
function inv_clean_tag($v) { return strtoupper(trim((string) $v)); }
function inv_valid_tag($v) { return (bool) preg_match('/^[A-Z0-9][A-Z0-9-]{1,29}$/', (string) $v); }

function inv_categories(PDO $pdo, $onlyActive = false) {
    $sql = "SELECT c.*, (SELECT COUNT(*) FROM inv_items i WHERE i.CategoryID = c.id) AS Items FROM inv_categories c"
         . ($onlyActive ? " WHERE c.Active = 1" : "") . " ORDER BY c.SortOrder, c.Name";
    return array_map(function ($r) {
        return ['id' => (int) $r['id'], 'code' => $r['Code'], 'name' => $r['Name'], 'kind' => $r['Kind'],
                'active' => (int) $r['Active'] === 1, 'sort' => (int) $r['SortOrder'], 'items' => (int) $r['Items']];
    }, $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
}

function inv_locations(PDO $pdo, $onlyActive = false) {
    $sql = "SELECT l.*, (SELECT COUNT(*) FROM inv_items i WHERE i.LocationID = l.id AND i.Status NOT IN ('disposed','merged')) AS Items
            FROM inv_locations l" . ($onlyActive ? " WHERE l.Active = 1" : "") . " ORDER BY l.Name";
    return array_map(function ($r) {
        return ['id' => (int) $r['id'], 'name' => $r['Name'], 'notes' => (string) $r['Notes'],
                'active' => (int) $r['Active'] === 1, 'items' => (int) $r['Items']];
    }, $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
}

function inv_departments(PDO $pdo) {
    return array_map(function ($r) { return ['id' => (int) $r['DepartmentID'], 'name' => $r['DepartmentDesc']]; },
        $pdo->query("SELECT DepartmentID, DepartmentDesc FROM departments ORDER BY DepartmentDesc")->fetchAll(PDO::FETCH_ASSOC));
}

function inv_companies(PDO $pdo) {
    return array_map(function ($r) { return ['id' => $r['CompanyID'], 'name' => $r['CompanyDesc']]; },
        $pdo->query("SELECT CompanyID, CompanyDesc FROM companies ORDER BY CompanyDesc")->fetchAll(PDO::FETCH_ASSOC));
}

/** Everything the screens need for their dropdowns. */
function inv_lookups(PDO $pdo) {
    return ['categories' => inv_categories($pdo), 'locations' => inv_locations($pdo), 'departments' => inv_departments($pdo),
            'companies' => inv_companies($pdo), 'conditions' => INV_CONDITIONS, 'statuses' => INV_STATUSES,
            'disposal' => INV_DISPOSAL, 'fileKinds' => INV_FILE_KINDS];
}

/** Code, name and kind are checked here; Kind and Code never change once items carry them. */
function inv_category_save(PDO $pdo, array $d) {
    $id   = (int) ($d['id'] ?? 0);
    $code = strtoupper(trim((string) ($d['code'] ?? '')));
    $name = lcy_text($d['name'] ?? '', 60);
    $kind = (string) ($d['kind'] ?? 'asset');
    if ($name === '') { return inv_fail('Enter the category name.'); }
    if (!preg_match('/^[A-Z0-9]{2,6}$/', $code)) { return inv_fail('The code is 2 to 6 letters or digits, e.g. FUR.'); }
    if (!isset(INV_KINDS[$kind])) { return inv_fail('Choose what kind of category this is.'); }
    $active = !empty($d['active']) ? 1 : 0;
    $sort   = (int) ($d['sort'] ?? 0);
    try {
        if ($id > 0) {
            $st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM inv_items i WHERE i.CategoryID = c.id) AS Items FROM inv_categories c WHERE c.id = :id");
            $st->execute([':id' => $id]);
            $old = $st->fetch(PDO::FETCH_ASSOC);
            if (!$old) { return inv_fail('That category no longer exists.'); }
            if ((int) $old['Items'] > 0 && ($old['Code'] !== $code || $old['Kind'] !== $kind)) {
                return inv_fail('Code and kind cannot change once the category has items.');
            }
            $pdo->prepare("UPDATE inv_categories SET Code = :c, Name = :n, Kind = :k, Active = :a, SortOrder = :s WHERE id = :id")
                ->execute([':c' => $code, ':n' => $name, ':k' => $kind, ':a' => $active, ':s' => $sort, ':id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO inv_categories (Code, Name, Kind, Active, SortOrder, CreatedAt) VALUES (:c, :n, :k, :a, :s, :t)")
                ->execute([':c' => $code, ':n' => $name, ':k' => $kind, ':a' => $active, ':s' => $sort, ':t' => inv_now()]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { return inv_fail('Another category already uses the code ' . $code . '.'); }
        throw $e;
    }
    inv_audit($pdo, 'category saved: ' . $code . ' ' . $name);
    return ['ok' => true, 'id' => $id];
}

function inv_location_save(PDO $pdo, array $d) {
    $id   = (int) ($d['id'] ?? 0);
    $name = lcy_text($d['name'] ?? '', 80);
    if ($name === '') { return inv_fail('Enter the location name.'); }
    $active = !empty($d['active']) ? 1 : 0;
    $notes  = inv_opt($d['notes'] ?? '', 200);
    try {
        if ($id > 0) {
            $st = $pdo->prepare("UPDATE inv_locations SET Name = :n, Notes = :o, Active = :a WHERE id = :id");
            $st->execute([':n' => $name, ':o' => $notes, ':a' => $active, ':id' => $id]);
        } else {
            $pdo->prepare("INSERT INTO inv_locations (Name, Notes, Active, CreatedAt) VALUES (:n, :o, :a, :t)")
                ->execute([':n' => $name, ':o' => $notes, ':a' => $active, ':t' => inv_now()]);
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { return inv_fail('There is already a location called ' . $name . '.'); }
        throw $e;
    }
    inv_audit($pdo, 'location saved: ' . $name);
    return ['ok' => true, 'id' => $id];
}

/** Employed people only: nothing can be issued to someone who has left. */
function inv_find_employees(PDO $pdo, $q) {
    return array_values(array_filter(lcy_find_employees($pdo, $q, 25), function ($e) { return $e['statusId'] === 1; }));
}

/* ------------------------------------------------------------------ reading items */

const INV_ITEM_SELECT = "SELECT i.*, c.Code AS CatCode, c.Name AS CatName, c.Kind, l.Name AS LocName,
            h.EmpFN, h.EmpMN, h.EmpLN, h.EmpSuffix, h.EmpStatusID AS HolderStatus
        FROM inv_items i
        JOIN inv_categories c ON c.id = i.CategoryID
        JOIN inv_locations l  ON l.id = i.LocationID
        LEFT JOIN employees h ON h.EmpID = i.HolderEmpID";

function inv_item_out(array $r) {
    $qty  = (int) $r['Qty'];
    $cost = $r['UnitCost'] === null ? null : (float) $r['UnitCost'];
    return [
        'id' => (int) $r['id'], 'tag' => $r['TagNo'], 'name' => $r['Name'], 'description' => (string) $r['Description'],
        'categoryId' => (int) $r['CategoryID'], 'category' => $r['CatName'], 'catCode' => $r['CatCode'], 'kind' => $r['Kind'],
        'brand' => (string) $r['Brand'], 'model' => (string) $r['Model'], 'serial' => (string) $r['SerialNo'],
        'qty' => $qty, 'unit' => $r['Unit'], 'condition' => $r['ItemCondition'],
        'conditionLabel' => INV_CONDITIONS[$r['ItemCondition']] ?? $r['ItemCondition'],
        'status' => $r['Status'], 'statusLabel' => INV_STATUSES[$r['Status']] ?? $r['Status'],
        'locationId' => (int) $r['LocationID'], 'location' => $r['LocName'], 'spot' => (string) $r['Spot'],
        'holderId' => $r['HolderEmpID'], 'holder' => $r['HolderEmpID'] ? lcy_full_name($r) : '',
        'holderLeft' => $r['HolderEmpID'] && (int) $r['HolderStatus'] !== 1,
        'issuedOn' => $r['IssuedOn'], 'ackAt' => $r['AckAt'],
        'companyId' => (string) $r['CompanyID'], 'acquiredOn' => $r['AcquiredOn'], 'supplier' => (string) $r['Supplier'],
        'unitCost' => $cost, 'totalCost' => $cost === null ? null : round($cost * $qty, 2),
        'reference' => (string) $r['ReferenceNo'], 'warrantyUntil' => $r['WarrantyUntil'],
        'docNumber' => (string) $r['DocNumber'], 'docIssuer' => (string) $r['DocIssuer'], 'docIssuedOn' => $r['DocIssuedOn'],
        'expiresOn' => $r['ExpiresOn'], 'remindDays' => (int) $r['RemindDays'],
        'reorderLevel' => $r['ReorderLevel'] === null ? null : (int) $r['ReorderLevel'],
        'low' => $r['ReorderLevel'] !== null && $qty <= (int) $r['ReorderLevel'],
        'parentId' => $r['ParentID'] === null ? null : (int) $r['ParentID'],
        'mergedInto' => $r['MergedIntoID'] === null ? null : (int) $r['MergedIntoID'],
        'disposedOn' => $r['DisposedOn'], 'disposalMethod' => (string) $r['DisposalMethod'], 'disposalNote' => (string) $r['DisposalNote'],
        'lastCountedOn' => $r['LastCountedOn'], 'notes' => (string) $r['Notes'], 'createdAt' => $r['CreatedAt'],
    ];
}

function inv_item(PDO $pdo, $id) {
    $st = $pdo->prepare(INV_ITEM_SELECT . " WHERE i.id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? inv_item_out($r) : null;
}

/**
 * The item with this tag. A merged row answers with the lot it went into. With
 * a location, a lot sticker resolves to the part of the lot kept there: the
 * twenty chairs carry one sticker even after five moved to another room.
 */
function inv_by_tag(PDO $pdo, $tag, $locationId = null) {
    $tag = inv_clean_tag($tag);
    if (!inv_valid_tag($tag)) { return null; }
    $st = $pdo->prepare(INV_ITEM_SELECT . " WHERE i.TagNo = :t");
    $st->execute([':t' => $tag]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { return null; }
    if ($r['Status'] === 'merged' && $r['MergedIntoID']) {
        $item = inv_item($pdo, $r['MergedIntoID']);
        if (!$item) { return null; }
    } else {
        $item = inv_item_out($r);
    }
    if ($locationId && $item['locationId'] !== (int) $locationId) {
        $root = $item['parentId'] ?: $item['id'];
        $st = $pdo->prepare(INV_ITEM_SELECT . " WHERE (i.id = :r1 OR i.ParentID = :r2) AND i.LocationID = :l
                            AND i.Status NOT IN ('disposed','merged') ORDER BY i.id LIMIT 1");
        $st->execute([':r1' => $root, ':r2' => $root, ':l' => (int) $locationId]);
        $here = $st->fetch(PDO::FETCH_ASSOC);
        if ($here) { return inv_item_out($here); }
    }
    return $item;
}

/** Filtered register. Merged rows never show; disposed ones only when asked for. */
function inv_list(PDO $pdo, array $f = []) {
    $where = ["i.Status <> 'merged'"];
    $args  = [];
    if (!empty($f['category'])) { $where[] = 'i.CategoryID = :cat'; $args[':cat'] = (int) $f['category']; }
    if (!empty($f['location'])) { $where[] = 'i.LocationID = :loc'; $args[':loc'] = (int) $f['location']; }
    if (($f['kind'] ?? '') === 'property') { $where[] = "c.Kind <> 'consumable'"; }
    elseif (!empty($f['kind']) && isset(INV_KINDS[$f['kind']])) { $where[] = 'c.Kind = :kind'; $args[':kind'] = $f['kind']; }
    $status = (string) ($f['status'] ?? '');
    if ($status === 'issued') { $where[] = "i.HolderEmpID IS NOT NULL AND i.Status <> 'disposed'"; }
    elseif ($status === 'available') { $where[] = "i.HolderEmpID IS NULL AND i.Status = 'active'"; }
    elseif ($status === 'all') { /* everything */ }
    elseif (isset(INV_STATUSES[$status])) { $where[] = 'i.Status = :st'; $args[':st'] = $status; }
    else { $where[] = "i.Status <> 'disposed'"; }
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        foreach (array_slice(preg_split('/\s+/u', $q), 0, 4) as $n => $w) {
            $like = '%' . addcslashes($w, '%_\\') . '%';
            $or = [];
            foreach (['i.TagNo', 'i.Name', 'i.Brand', 'i.Model', 'i.SerialNo', 'i.DocNumber', 'h.EmpFN', 'h.EmpLN'] as $k => $col) {
                $or[] = "$col LIKE :q{$n}x{$k}";
                $args[":q{$n}x{$k}"] = $like;
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
    }
    $st = $pdo->prepare(INV_ITEM_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY i.TagNo LIMIT ' . INV_TABLE_LIMIT);
    $st->execute($args);
    return array_map('inv_item_out', $st->fetchAll(PDO::FETCH_ASSOC));
}

function inv_stats(PDO $pdo) {
    $r = $pdo->query("SELECT COUNT(*) AS items,
            COALESCE(SUM(i.HolderEmpID IS NOT NULL), 0) AS issued,
            COALESCE(SUM(i.Status = 'repair'), 0) AS repair,
            COALESCE(SUM(i.UnitCost * i.Qty), 0) AS cost
        FROM inv_items i WHERE i.Status NOT IN ('disposed','merged')")->fetch(PDO::FETCH_ASSOC);
    return ['items' => (int) $r['items'], 'issued' => (int) $r['issued'], 'repair' => (int) $r['repair'],
            'cost' => round((float) $r['cost'], 2), 'alerts' => inv_alert_count($pdo)];
}

/** The other rows of a lot. */
function inv_family(PDO $pdo, array $item) {
    $root = $item['parentId'] ?: $item['id'];
    $st = $pdo->prepare(INV_ITEM_SELECT . " WHERE (i.id = :r1 OR i.ParentID = :r2) AND i.id <> :me AND i.Status <> 'merged' ORDER BY i.id");
    $st->execute([':r1' => $root, ':r2' => $root, ':me' => $item['id']]);
    return array_map('inv_item_out', $st->fetchAll(PDO::FETCH_ASSOC));
}

function inv_history(PDO $pdo, $itemId) {
    $st = $pdo->prepare("SELECT m.*, lf.Name AS FromLoc, lt.Name AS ToLoc,
            ef.EmpFN AS FFN, ef.EmpLN AS FLN, et.EmpFN AS TFN, et.EmpLN AS TLN, b.EmpFN AS BFN, b.EmpLN AS BLN, r.TagNo AS RefTag
        FROM inv_movements m
        LEFT JOIN inv_locations lf ON lf.id = m.FromLocationID
        LEFT JOIN inv_locations lt ON lt.id = m.ToLocationID
        LEFT JOIN employees ef ON ef.EmpID = m.FromEmpID
        LEFT JOIN employees et ON et.EmpID = m.ToEmpID
        LEFT JOIN employees b  ON b.EmpID = m.ByEmpID
        LEFT JOIN inv_items r  ON r.id = m.RefItemID
        WHERE m.ItemID = :i ORDER BY m.id DESC LIMIT 200");
    $st->execute([':i' => (int) $itemId]);
    $name = function ($a, $b) { return trim((string) $a . ' ' . (string) $b); };
    return array_map(function ($r) use ($name) {
        return ['type' => $r['Type'], 'at' => $r['CreatedAt'], 'qty' => $r['Qty'] === null ? null : (int) $r['Qty'],
                'fromLoc' => (string) $r['FromLoc'], 'toLoc' => (string) $r['ToLoc'],
                'fromEmp' => $name($r['FFN'], $r['FLN']) ?: (string) $r['FromEmpID'],
                'toEmp' => $name($r['TFN'], $r['TLN']) ?: (string) $r['ToEmpID'],
                'refTag' => (string) $r['RefTag'], 'notes' => (string) $r['Notes'],
                'by' => $name($r['BFN'], $r['BLN'])];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Everything the item panel shows. */
function inv_item_detail(PDO $pdo, $id) {
    $item = inv_item($pdo, $id);
    if (!$item) { return null; }
    return ['item' => $item, 'history' => inv_history($pdo, $item['id']), 'files' => inv_files($pdo, $item['id']),
            'repairs' => inv_repairs($pdo, ['item' => $item['id']]), 'family' => inv_family($pdo, $item),
            'canMerge' => inv_can_merge($pdo, $item), 'ledger' => $item['kind'] === 'consumable' ? inv_stock_ledger($pdo, $item['id']) : [],
            'openCount' => inv_open_count_for($pdo, $item['locationId'])];
}

/* ------------------------------------------------------------------ writing items */

/** Insert one history row. Assumes the caller's transaction, if any. */
function _inv_move_tx(PDO $pdo, $itemId, $type, array $f, $actor) {
    $pdo->prepare("INSERT INTO inv_movements (ItemID, Type, FromLocationID, ToLocationID, FromEmpID, ToEmpID, Qty, RefItemID, RefID, Notes, ByEmpID, CreatedAt)
                   VALUES (:i, :t, :fl, :tl, :fe, :te, :q, :ri, :r, :n, :b, :at)")
        ->execute([':i' => (int) $itemId, ':t' => $type, ':fl' => $f['fromLoc'] ?? null, ':tl' => $f['toLoc'] ?? null,
                   ':fe' => $f['fromEmp'] ?? null, ':te' => $f['toEmp'] ?? null, ':q' => $f['qty'] ?? null,
                   ':ri' => $f['refItem'] ?? null, ':r' => $f['ref'] ?? null,
                   ':n' => isset($f['notes']) ? mb_substr((string) $f['notes'], 0, 500) : null,
                   ':b' => (string) $actor, ':at' => inv_now()]);
}

/** Lock a row inside the caller's transaction. Null when it doesn't exist. */
function _inv_lock_tx(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT i.*, c.Kind, c.Code AS CatCode FROM inv_items i JOIN inv_categories c ON c.id = i.CategoryID WHERE i.id = :id FOR UPDATE");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/** Run $fn inside a transaction: it returns the answer, ok=false rolls back. */
function _inv_tx(PDO $pdo, callable $fn) {
    $pdo->beginTransaction();
    try {
        $r = $fn();
        if (empty($r['ok'])) { $pdo->rollBack(); return $r; }
        $pdo->commit();
        return $r;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

/** Next free tag of a category, e.g. FUR-0007. Locks the category row, so it needs a transaction. */
function _inv_next_tag_tx(PDO $pdo, $categoryId) {
    $st = $pdo->prepare("SELECT Code, NextSeq FROM inv_categories WHERE id = :id FOR UPDATE");
    $st->execute([':id' => (int) $categoryId]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    $seq = (int) $c['NextSeq'];
    $chk = $pdo->prepare("SELECT 1 FROM inv_items WHERE TagNo = :t");
    do {   // a hand-typed sticker number may already sit on this number
        $tag = sprintf('%s-%04d', $c['Code'], $seq++);
        $chk->execute([':t' => $tag]);
    } while ($chk->fetchColumn());
    $pdo->prepare("UPDATE inv_categories SET NextSeq = :n WHERE id = :id")->execute([':n' => $seq, ':id' => (int) $categoryId]);
    return $tag;
}

/**
 * Add ($d['id'] empty) or edit an item. Location, holder, status and a
 * consumable's quantity change only through their own actions, so an edit
 * ignores them. A consumable's opening balance is the ledger's first row.
 * With eachUnit, a new asset of quantity N becomes N items of 1, each with its
 * own tag, and the answer lists them in `units`.
 * @return array{ok:bool, msg?:string, id?:int, tag?:string, name?:string, units?:array}
 */
function inv_save(PDO $pdo, array $d, $actor) {
    $id   = (int) ($d['id'] ?? 0);
    $name = lcy_text($d['name'] ?? '', 150);
    if ($name === '') { return inv_fail('Enter the item name.'); }
    $st = $pdo->prepare("SELECT * FROM inv_categories WHERE id = :id");
    $st->execute([':id' => (int) ($d['categoryId'] ?? 0)]);
    $cat = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cat) { return inv_fail('Choose a category.'); }
    $kind = $cat['Kind'];

    $condition = (string) ($d['condition'] ?? 'good');
    if (!isset(INV_CONDITIONS[$condition])) { return inv_fail('Choose the condition.'); }
    $tag = inv_clean_tag($d['tag'] ?? '');
    if ($tag !== '' && !inv_valid_tag($tag)) { return inv_fail('A tag number uses letters, digits and dashes only (30 at most).'); }
    $qty = inv_int($d['qty'] ?? ($kind === 'consumable' ? 0 : 1));
    if ($qty === null || $qty < ($kind === 'consumable' ? 0 : 1)) {
        return inv_fail($kind === 'consumable' ? 'Enter the quantity on hand (0 or more).' : 'The quantity must be at least 1.');
    }
    $cost = trim((string) ($d['unitCost'] ?? '')) === '' ? null : inv_money($d['unitCost']);
    if ($cost === null && trim((string) ($d['unitCost'] ?? '')) !== '') { return inv_fail('Enter the unit cost as a number.'); }

    $fields = [
        'Name' => $name, 'Description' => inv_opt($d['description'] ?? '', 500),
        'Unit' => lcy_text($d['unit'] ?? '', 20) ?: 'pc', 'ItemCondition' => $condition,
        'Spot' => inv_opt($d['spot'] ?? '', 100),
        'CompanyID' => inv_opt($d['companyId'] ?? '', 10), 'AcquiredOn' => lcy_date($d['acquiredOn'] ?? ''),
        'Supplier' => inv_opt($d['supplier'] ?? '', 120), 'UnitCost' => $cost,
        'ReferenceNo' => inv_opt($d['reference'] ?? '', 60), 'Notes' => inv_opt($d['notes'] ?? '', 500),
        'Brand' => null, 'Model' => null, 'SerialNo' => null, 'WarrantyUntil' => null,
        'DocNumber' => null, 'DocIssuer' => null, 'DocIssuedOn' => null, 'ExpiresOn' => null, 'RemindDays' => 30, 'ReorderLevel' => null,
    ];
    if ($kind !== 'document') {
        $fields['Brand'] = inv_opt($d['brand'] ?? '', 80);
        $fields['Model'] = inv_opt($d['model'] ?? '', 80);
    }
    if ($kind === 'asset') {
        $fields['SerialNo'] = inv_opt($d['serial'] ?? '', 80);
        $fields['WarrantyUntil'] = lcy_date($d['warrantyUntil'] ?? '');
    }
    if ($kind === 'document') {
        $fields['DocNumber'] = inv_opt($d['docNumber'] ?? '', 80);
        $fields['DocIssuer'] = inv_opt($d['docIssuer'] ?? '', 120);
        $fields['DocIssuedOn'] = lcy_date($d['docIssuedOn'] ?? '');
        $fields['ExpiresOn'] = lcy_date($d['expiresOn'] ?? '');
        $days = inv_int($d['remindDays'] ?? 30);
        if ($days === null || $days < 0 || $days > 3650) { return inv_fail('Remind days must be between 0 and 3650.'); }
        $fields['RemindDays'] = $days;
    }
    if ($kind === 'consumable') {
        $raw = trim((string) ($d['reorderLevel'] ?? ''));
        if ($raw !== '') {
            $lvl = inv_int($raw);
            if ($lvl === null || $lvl < 0) { return inv_fail('The reorder level is a whole number, 0 or more.'); }
            $fields['ReorderLevel'] = $lvl;
        }
    }

    $eachUnit = $id === 0 && $kind === 'asset' && $qty > 1 && (string) ($d['eachUnit'] ?? '') === '1';
    if ($eachUnit) {
        if ($qty > INV_MAX_UNITS) { return inv_fail('Add at most ' . INV_MAX_UNITS . ' separate items at a time.'); }
        if ($tag !== '') { return inv_fail('A typed tag fits one item only: leave it blank so each one gets its own.'); }
        $fields['SerialNo'] = null;   // one serial cannot be true of every unit
    }

    try {
        return _inv_tx($pdo, function () use ($pdo, $id, $d, $cat, $kind, $tag, $qty, $cost, $fields, $actor, $name, $eachUnit) {
            if ($id > 0) {
                $old = _inv_lock_tx($pdo, $id);
                if (!$old) { return inv_fail('That item no longer exists.'); }
                if ($old['Status'] === 'merged') { return inv_fail('That row was merged back into its lot.'); }
                if ($old['Kind'] !== $kind) { return inv_fail('An item stays the same kind: choose a category of the same kind.'); }
                $set = ['CategoryID = :cat', 'TagNo = :tag', 'UpdatedAt = :up'];
                $args = [':cat' => (int) $cat['id'], ':tag' => $tag !== '' ? $tag : $old['TagNo'], ':up' => inv_now(), ':id' => $id];
                foreach ($fields as $col => $v) { $set[] = "`$col` = :f$col"; $args[":f$col"] = $v; }
                if ($kind !== 'consumable') { $set[] = 'Qty = :qty'; $args[':qty'] = $qty; }
                $pdo->prepare("UPDATE inv_items SET " . implode(', ', $set) . " WHERE id = :id")->execute($args);
                _inv_move_tx($pdo, $id, 'edited', [], $actor);
                return ['ok' => true, 'id' => $id, 'tag' => $args[':tag']];
            }

            $st = $pdo->prepare("SELECT Active FROM inv_locations WHERE id = :id");
            $st->execute([':id' => (int) ($d['locationId'] ?? 0)]);
            if ((int) $st->fetchColumn() !== 1) { return inv_fail('Choose a location.'); }
            $insert = function ($newTag, $newQty) use ($pdo, $d, $cat, $fields, $actor) {
                $cols = array_merge(array_keys($fields), ['TagNo', 'CategoryID', 'LocationID', 'Qty', 'CreatedBy', 'CreatedAt', 'UpdatedAt']);
                $vals = array_merge(array_values($fields), [$newTag, (int) $cat['id'], (int) $d['locationId'], $newQty, (string) $actor, inv_now(), inv_now()]);
                $pdo->prepare("INSERT INTO inv_items (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
                $newId = (int) $pdo->lastInsertId();
                _inv_move_tx($pdo, $newId, 'added', ['toLoc' => (int) $d['locationId'], 'qty' => $newQty], $actor);
                return $newId;
            };

            if ($eachUnit) {
                $units = [];
                for ($n = 0; $n < $qty; $n++) {
                    $unitTag = _inv_next_tag_tx($pdo, $cat['id']);
                    $units[] = ['id' => $insert($unitTag, 1), 'tag' => $unitTag];
                }
                return ['ok' => true, 'id' => $units[0]['id'], 'tag' => $units[0]['tag'], 'name' => $name, 'units' => $units];
            }

            $newTag = $tag !== '' ? $tag : _inv_next_tag_tx($pdo, $cat['id']);
            $newId = $insert($newTag, $qty);
            if ($kind === 'consumable' && $qty > 0) {
                _inv_ledger_tx($pdo, $newId, 'in', $qty, $qty, ['unitCost' => $cost, 'supplier' => $fields['Supplier'], 'notes' => 'Opening balance'], $actor);
            }
            return ['ok' => true, 'id' => $newId, 'tag' => $newTag];
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { return inv_fail('Another item already uses the tag ' . $tag . '.'); }
        throw $e;
    }
}

/** Only a row with nothing behind it can vanish; everything else is disposed of. */
function inv_delete(PDO $pdo, $id, $actor) {
    $r = _inv_tx($pdo, function () use ($pdo, $id, $actor) {
        $old = _inv_lock_tx($pdo, $id);
        if (!$old) { return inv_fail('That item no longer exists.'); }
        $count = function ($sql) use ($pdo, $id) {
            $st = $pdo->prepare($sql);
            $st->execute([':i' => (int) $id]);
            return (int) $st->fetchColumn();
        };
        $history = $count("SELECT COUNT(*) FROM inv_movements WHERE ItemID = :i AND Type NOT IN ('added','edited')");
        $others  = $count("SELECT COUNT(*) FROM inv_files WHERE ItemID = :i")
                 + $count("SELECT COUNT(*) FROM inv_repairs WHERE ItemID = :i")
                 + $count("SELECT COUNT(*) FROM inv_count_lines WHERE ItemID = :i")
                 + $count("SELECT COUNT(*) FROM inv_items WHERE ParentID = :i");
        $ledger  = $count("SELECT COUNT(*) FROM inv_stock_moves WHERE ItemID = :i");
        if ($history > 0 || $others > 0 || $ledger > 1 || $old['HolderEmpID'] !== null) {
            return inv_fail('This item has a history: dispose of it instead, so the record stays.');
        }
        $pdo->prepare("DELETE FROM inv_stock_moves WHERE ItemID = :i")->execute([':i' => (int) $id]);
        $pdo->prepare("DELETE FROM inv_movements WHERE ItemID = :i")->execute([':i' => (int) $id]);
        $pdo->prepare("DELETE FROM inv_items WHERE id = :i")->execute([':i' => (int) $id]);
        return ['ok' => true, 'tag' => $old['TagNo'], 'itemName' => $old['Name']];
    });
    if ($r['ok']) { inv_audit($pdo, 'deleted ' . $r['tag'] . ' ' . $r['itemName']); }
    return $r;
}

/* ------------------------------------------------------------------ lots */

/**
 * Split $qty off a locked row. The new row copies it, hangs under the ROOT of
 * the lot and takes the root's tag plus a running number (FUR-0012-2).
 * @return array the new row, locked
 */
function _inv_split_tx(PDO $pdo, array $row, $qty, $actor) {
    $rootId = (int) ($row['ParentID'] ?: $row['id']);
    $root = $rootId === (int) $row['id'] ? $row : _inv_lock_tx($pdo, $rootId);
    $seq = (int) $root['SplitSeq'];
    $chk = $pdo->prepare("SELECT 1 FROM inv_items WHERE TagNo = :t");
    do {
        $tag = $root['TagNo'] . '-' . (++$seq);
        $chk->execute([':t' => $tag]);
    } while ($chk->fetchColumn());
    $pdo->prepare("UPDATE inv_items SET SplitSeq = :s WHERE id = :id")->execute([':s' => $seq, ':id' => $rootId]);

    $copy = ['CategoryID', 'Name', 'Description', 'Brand', 'Model', 'SerialNo', 'Unit', 'ItemCondition', 'Status', 'LocationID', 'Spot',
             'HolderEmpID', 'IssuedOn', 'AckAt', 'CompanyID', 'AcquiredOn', 'Supplier', 'UnitCost', 'ReferenceNo', 'WarrantyUntil',
             'DocNumber', 'DocIssuer', 'DocIssuedOn', 'ExpiresOn', 'RemindDays', 'LastCountedOn', 'Notes'];
    $cols = array_merge($copy, ['TagNo', 'Qty', 'ParentID', 'CreatedBy', 'CreatedAt', 'UpdatedAt']);
    $vals = array_map(function ($c) use ($row) { return $row[$c]; }, $copy);
    $vals = array_merge($vals, [$tag, (int) $qty, $rootId, (string) $actor, inv_now(), inv_now()]);
    $pdo->prepare("INSERT INTO inv_items (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
    $childId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE inv_items SET Qty = Qty - :q, UpdatedAt = :u WHERE id = :id")
        ->execute([':q' => (int) $qty, ':u' => inv_now(), ':id' => (int) $row['id']]);
    _inv_move_tx($pdo, (int) $row['id'], 'split', ['qty' => (int) $qty, 'refItem' => $childId, 'notes' => 'Split off as ' . $tag], $actor);
    _inv_move_tx($pdo, $childId, 'split', ['qty' => (int) $qty, 'refItem' => (int) $row['id'], 'notes' => 'Split from ' . $row['TagNo']], $actor);
    return _inv_lock_tx($pdo, $childId);
}

/** The row to act on: the row itself, or a new one holding just $qty. Null qty = all of it. */
function _inv_portion_tx(PDO $pdo, array $row, $qty, $actor) {
    if ($qty === null || (int) $qty === (int) $row['Qty']) { return $row; }
    return _inv_split_tx($pdo, $row, $qty, $actor);
}

/** A quantity from a form: null when blank (the whole row), false when unusable. */
function inv_part_qty($v, array $row) {
    $raw = trim((string) $v);
    if ($raw === '') { return null; }
    $q = inv_int($raw);
    return ($q === null || $q < 1 || $q > (int) $row['Qty']) ? false : $q;
}

/** May this split row go back into its lot? */
function inv_can_merge(PDO $pdo, array $item) {
    if (!$item['parentId'] || $item['status'] !== 'active' || $item['holderId'] || $item['qty'] < 1) { return false; }
    $root = inv_item($pdo, $item['parentId']);
    return $root && $root['status'] === 'active' && !$root['holderId'] && $root['locationId'] === $item['locationId']
        && $root['condition'] === $item['condition'];
}

/** Soft merge: the child keeps its id so repairs, files and counts stay attached. */
function inv_merge(PDO $pdo, $id, $actor) {
    $item = inv_item($pdo, $id);
    if (!$item) { return inv_fail('That item no longer exists.'); }
    if (!inv_can_merge($pdo, $item)) {
        return inv_fail('Only an unissued split row in service, at the same location and in the same condition as its lot, can be merged back.');
    }
    return _inv_tx($pdo, function () use ($pdo, $item, $actor) {
        $root  = _inv_lock_tx($pdo, $item['parentId']);
        $child = _inv_lock_tx($pdo, $item['id']);
        $qty = (int) $child['Qty'];
        $pdo->prepare("UPDATE inv_items SET Qty = Qty + :q, UpdatedAt = :u WHERE id = :id")
            ->execute([':q' => $qty, ':u' => inv_now(), ':id' => (int) $root['id']]);
        $pdo->prepare("UPDATE inv_items SET Qty = 0, Status = 'merged', MergedIntoID = :r, UpdatedAt = :u WHERE id = :id")
            ->execute([':r' => (int) $root['id'], ':u' => inv_now(), ':id' => (int) $child['id']]);
        _inv_move_tx($pdo, (int) $root['id'], 'merged', ['qty' => $qty, 'refItem' => (int) $child['id'], 'notes' => $child['TagNo'] . ' merged back'], $actor);
        _inv_move_tx($pdo, (int) $child['id'], 'merged', ['qty' => $qty, 'refItem' => (int) $root['id'], 'notes' => 'Merged into ' . $root['TagNo']], $actor);
        return ['ok' => true, 'id' => (int) $root['id']];
    });
}

/* ------------------------------------------------------------------ custody */

/** Assets and documents can be held. Consumables use the ledger. */
function _inv_held_kind(array $row) { return $row['Kind'] !== 'consumable'; }

/**
 * Issue a lot or part of it. Clears any earlier confirmation: the new holder
 * confirms receipt in My Items.
 * @return array{ok:bool, msg?:string, id?:int, empId?:string}
 */
function inv_issue(PDO $pdo, $id, $empId, $qty, $date, $notes, $actor) {
    $emp = lcy_employee($pdo, $empId);
    if (!$emp) { return inv_fail('Choose an employee.'); }
    if ($emp['statusId'] !== 1) { return inv_fail($emp['name'] . ' is no longer employed (' . $emp['status'] . ').'); }
    $on = lcy_date($date) ?: inv_today();
    $r = _inv_tx($pdo, function () use ($pdo, $id, $emp, $qty, $on, $notes, $actor) {
        return _inv_issue_tx($pdo, $id, $emp, $qty, $on, $notes, $actor);
    });
    if ($r['ok']) { inv_audit($pdo, 'issued ' . $r['tag'] . ' to ' . $emp['empId']); }
    return $r;
}

/** One item (or part of a lot) to $emp, inside the caller's transaction. */
function _inv_issue_tx(PDO $pdo, $id, array $emp, $qty, $on, $notes, $actor) {
    $row = _inv_lock_tx($pdo, $id);
    if (!$row) { return inv_fail('That item no longer exists.'); }
    if (!_inv_held_kind($row)) { return inv_fail('Supplies are released with Stock out, not issued.'); }
    if ($row['Status'] !== 'active') { return inv_fail('Only an item in service can be issued (this one is: ' . (INV_STATUSES[$row['Status']] ?? $row['Status']) . ').'); }
    if ($row['HolderEmpID']) { return inv_fail('This item is already issued — return it first.'); }
    $part = inv_part_qty($qty, $row);
    if ($part === false) { return inv_fail('Issue between 1 and ' . $row['Qty'] . '.'); }
    $target = _inv_portion_tx($pdo, $row, $part, $actor);
    $pdo->prepare("UPDATE inv_items SET HolderEmpID = :e, IssuedOn = :d, AckAt = NULL, UpdatedAt = :u WHERE id = :id")
        ->execute([':e' => $emp['empId'], ':d' => $on, ':u' => inv_now(), ':id' => (int) $target['id']]);
    _inv_move_tx($pdo, (int) $target['id'], 'issued', ['toEmp' => $emp['empId'], 'qty' => (int) $target['Qty'], 'notes' => inv_opt($notes, 500)], $actor);
    return ['ok' => true, 'id' => (int) $target['id'], 'tag' => $target['TagNo'], 'itemName' => $target['Name'], 'empId' => $emp['empId']];
}

/**
 * Several whole items to one employee in one go: all of them or none (the
 * first one that cannot be issued says why, with its tag).
 * @return array{ok:bool, msg?:string, ids?:int[], tags?:string[], empId?:string}
 */
function inv_issue_many(PDO $pdo, array $ids, $empId, $date, $notes, $actor) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) { return inv_fail('Choose what to issue.'); }
    $emp = lcy_employee($pdo, $empId);
    if (!$emp) { return inv_fail('Choose an employee.'); }
    if ($emp['statusId'] !== 1) { return inv_fail($emp['name'] . ' is no longer employed (' . $emp['status'] . ').'); }
    $on = lcy_date($date) ?: inv_today();
    $r = _inv_tx($pdo, function () use ($pdo, $ids, $emp, $on, $notes, $actor) {
        $out = ['ok' => true, 'ids' => [], 'tags' => [], 'empId' => $emp['empId']];
        foreach ($ids as $id) {
            $one = _inv_issue_tx($pdo, $id, $emp, null, $on, $notes, $actor);
            if (!$one['ok']) {
                $tag = $pdo->query("SELECT TagNo FROM inv_items WHERE id = " . (int) $id)->fetchColumn();
                return inv_fail(($tag ? $tag . ': ' : '') . $one['msg']);
            }
            $out['ids'][] = $one['id'];
            $out['tags'][] = $one['tag'];
        }
        return $out;
    });
    if ($r['ok']) { inv_audit($pdo, 'issued ' . implode(', ', $r['tags']) . ' to ' . $emp['empId']); }
    return $r;
}

/** Take a lot or part of it back. It may land at another location, in another condition. */
function inv_return(PDO $pdo, $id, $qty, $locationId, $condition, $notes, $actor) {
    $r = _inv_tx($pdo, function () use ($pdo, $id, $qty, $locationId, $condition, $notes, $actor) {
        $row = _inv_lock_tx($pdo, $id);
        if (!$row) { return inv_fail('That item no longer exists.'); }
        if (!$row['HolderEmpID']) { return inv_fail('This item is not issued to anyone.'); }
        if ($row['Status'] === 'lost') { return inv_fail('This item is marked lost — mark it found first.'); }
        $part = inv_part_qty($qty, $row);
        if ($part === false) { return inv_fail('Return between 1 and ' . $row['Qty'] . '.'); }
        $to = (int) ($locationId ?: $row['LocationID']);
        $st = $pdo->prepare("SELECT 1 FROM inv_locations WHERE id = :id");
        $st->execute([':id' => $to]);
        if (!$st->fetchColumn()) { return inv_fail('Choose a location.'); }
        $cond = isset(INV_CONDITIONS[(string) $condition]) ? (string) $condition : null;
        $target = _inv_portion_tx($pdo, $row, $part, $actor);
        $pdo->prepare("UPDATE inv_items SET HolderEmpID = NULL, IssuedOn = NULL, AckAt = NULL, LocationID = :l,
                       ItemCondition = :c, UpdatedAt = :u WHERE id = :id")
            ->execute([':l' => $to, ':c' => $cond ?: $target['ItemCondition'], ':u' => inv_now(), ':id' => (int) $target['id']]);
        _inv_move_tx($pdo, (int) $target['id'], 'returned', ['fromEmp' => $target['HolderEmpID'], 'qty' => (int) $target['Qty'],
            'fromLoc' => (int) $target['LocationID'], 'toLoc' => $to, 'notes' => inv_opt($notes, 500)], $actor);
        return ['ok' => true, 'id' => (int) $target['id'], 'tag' => $target['TagNo'], 'empId' => $target['HolderEmpID']];
    });
    if ($r['ok']) { inv_audit($pdo, 'returned ' . $r['tag'] . ' from ' . $r['empId']); }
    return $r;
}

/** Move rows to another location. Several ids move whole; one id may move part of its lot. All or nothing. */
function inv_transfer(PDO $pdo, array $ids, $locationId, $qty, $spot, $notes, $actor) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) { return inv_fail('Choose what to move.'); }
    $st = $pdo->prepare("SELECT Active FROM inv_locations WHERE id = :id");
    $st->execute([':id' => (int) $locationId]);
    if ((int) $st->fetchColumn() !== 1) { return inv_fail('Choose the new location.'); }
    $r = _inv_tx($pdo, function () use ($pdo, $ids, $locationId, $qty, $spot, $notes, $actor) {
        $moved = 0;
        foreach ($ids as $id) {
            $row = _inv_lock_tx($pdo, $id);
            if (!$row) { return inv_fail('An item no longer exists.'); }
            $tag = $row['TagNo'];
            if (!_inv_held_kind($row)) { return inv_fail($tag . ': a supply stays where it is kept. Stock it out and in instead.'); }
            if ($row['Status'] !== 'active') { return inv_fail($tag . ' is not in service.'); }
            if ($row['HolderEmpID']) { return inv_fail($tag . ' is issued — return it first.'); }
            if ((int) $row['LocationID'] === (int) $locationId) { return inv_fail($tag . ' is already there.'); }
            $part = count($ids) === 1 ? inv_part_qty($qty, $row) : null;
            if ($part === false) { return inv_fail('Move between 1 and ' . $row['Qty'] . '.'); }
            $target = _inv_portion_tx($pdo, $row, $part, $actor);
            $pdo->prepare("UPDATE inv_items SET LocationID = :l, Spot = :s, UpdatedAt = :u WHERE id = :id")
                ->execute([':l' => (int) $locationId, ':s' => inv_opt($spot, 100), ':u' => inv_now(), ':id' => (int) $target['id']]);
            _inv_move_tx($pdo, (int) $target['id'], 'transferred', ['fromLoc' => (int) $target['LocationID'], 'toLoc' => (int) $locationId,
                'qty' => (int) $target['Qty'], 'notes' => inv_opt($notes, 500)], $actor);
            $moved++;
        }
        return ['ok' => true, 'moved' => $moved];
    });
    if ($r['ok']) { inv_audit($pdo, 'moved ' . $r['moved'] . ' item(s) to location ' . (int) $locationId); }
    return $r;
}

/** Lost keeps the holder: the person is still accountable, and clearance stays blocked. */
function inv_mark_lost(PDO $pdo, $id, $qty, $notes, $actor) {
    $r = _inv_tx($pdo, function () use ($pdo, $id, $qty, $notes, $actor) {
        $row = _inv_lock_tx($pdo, $id);
        if (!$row) { return inv_fail('That item no longer exists.'); }
        if (!_inv_held_kind($row)) { return inv_fail('Correct a supply with Adjust.'); }
        if ($row['Status'] !== 'active') { return inv_fail('Only an item in service can be marked lost.'); }
        $part = inv_part_qty($qty, $row);
        if ($part === false) { return inv_fail('Between 1 and ' . $row['Qty'] . '.'); }
        $target = _inv_portion_tx($pdo, $row, $part, $actor);
        $pdo->prepare("UPDATE inv_items SET Status = 'lost', UpdatedAt = :u WHERE id = :id")->execute([':u' => inv_now(), ':id' => (int) $target['id']]);
        _inv_move_tx($pdo, (int) $target['id'], 'lost', ['qty' => (int) $target['Qty'], 'notes' => inv_opt($notes, 500)], $actor);
        return ['ok' => true, 'id' => (int) $target['id'], 'tag' => $target['TagNo']];
    });
    if ($r['ok']) { inv_audit($pdo, 'marked lost ' . $r['tag']); }
    return $r;
}

function inv_mark_found(PDO $pdo, $id, $locationId, $notes, $actor) {
    return _inv_tx($pdo, function () use ($pdo, $id, $locationId, $notes, $actor) {
        $row = _inv_lock_tx($pdo, $id);
        if (!$row) { return inv_fail('That item no longer exists.'); }
        if ($row['Status'] !== 'lost') { return inv_fail('This item is not marked lost.'); }
        $to = (int) ($locationId ?: $row['LocationID']);
        $pdo->prepare("UPDATE inv_items SET Status = 'active', LocationID = :l, UpdatedAt = :u WHERE id = :id")
            ->execute([':l' => $to, ':u' => inv_now(), ':id' => (int) $row['id']]);
        _inv_move_tx($pdo, (int) $row['id'], 'found', ['fromLoc' => (int) $row['LocationID'], 'toLoc' => $to, 'notes' => inv_opt($notes, 500)], $actor);
        return ['ok' => true, 'id' => (int) $row['id']];
    });
}

/** Write an item off. The holder is released: this also settles a lost item for clearance. */
function inv_dispose(PDO $pdo, $id, $qty, $method, $date, $note, $actor) {
    if (!isset(INV_DISPOSAL[(string) $method])) { return inv_fail('Choose how it was disposed of.'); }
    $on = lcy_date($date);
    if ($on === null) { return inv_fail('Enter the disposal date.'); }
    $r = _inv_tx($pdo, function () use ($pdo, $id, $qty, $method, $on, $note, $actor) {
        $row = _inv_lock_tx($pdo, $id);
        if (!$row) { return inv_fail('That item no longer exists.'); }
        if (!_inv_held_kind($row)) { return inv_fail('Correct a supply with Adjust.'); }
        if (in_array($row['Status'], ['disposed', 'merged'], true)) { return inv_fail('This item is already off the register.'); }
        $st = $pdo->prepare("SELECT COUNT(*) FROM inv_repairs WHERE ItemID = :i AND Status = 'open'");
        $st->execute([':i' => (int) $row['id']]);
        if ((int) $st->fetchColumn() > 0) { return inv_fail('Close its open repair first.'); }
        $part = inv_part_qty($qty, $row);
        if ($part === false) { return inv_fail('Dispose of between 1 and ' . $row['Qty'] . '.'); }
        $target = _inv_portion_tx($pdo, $row, $part, $actor);
        $pdo->prepare("UPDATE inv_items SET Status = 'disposed', HolderEmpID = NULL, IssuedOn = NULL, AckAt = NULL, DisposedOn = :d,
                       DisposalMethod = :m, DisposalNote = :n, UpdatedAt = :u WHERE id = :id")
            ->execute([':d' => $on, ':m' => $method, ':n' => inv_opt($note, 300), ':u' => inv_now(), ':id' => (int) $target['id']]);
        _inv_move_tx($pdo, (int) $target['id'], 'disposed', ['fromEmp' => $target['HolderEmpID'], 'qty' => (int) $target['Qty'],
            'notes' => INV_DISPOSAL[$method] . ($note !== '' ? ': ' . lcy_text($note, 300) : '')], $actor);
        return ['ok' => true, 'id' => (int) $target['id'], 'tag' => $target['TagNo']];
    });
    if ($r['ok']) { inv_audit($pdo, 'disposed ' . $r['tag'] . ' (' . $method . ')'); }
    return $r;
}

/* ------------------------------------------------------------------ the holder's side */

/** What this employee is accountable for. Costs stay off this view. */
function inv_held_by(PDO $pdo, $empId) {
    $st = $pdo->prepare(INV_ITEM_SELECT . " WHERE i.HolderEmpID = :e AND i.Status NOT IN ('disposed','merged') ORDER BY i.IssuedOn DESC, i.TagNo");
    $st->execute([':e' => (string) $empId]);
    return array_map(function ($r) {
        $o = inv_item_out($r);
        unset($o['unitCost'], $o['totalCost'], $o['supplier'], $o['reference']);
        return $o;
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** My Items: the holder's list with the photos they may open. */
function inv_my_items(PDO $pdo, $empId) {
    $items = inv_held_by($pdo, $empId);
    if (!$items) { return []; }
    $byId = array_column($items, 'id');
    $st = $pdo->prepare("SELECT id, ItemID FROM inv_files WHERE Kind = 'photo' AND ItemID IN (" . implode(',', array_fill(0, count($byId), '?')) . ") ORDER BY id");
    $st->execute($byId);
    $photos = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) { $photos[(int) $f['ItemID']][] = (int) $f['id']; }
    foreach ($items as &$i) { $i['photos'] = $photos[$i['id']] ?? []; }
    return $items;
}

/** Separation clearance: what still has to come back. Lost items count until written off. */
function inv_held_summary(PDO $pdo, $empId) {
    $items = inv_held_by($pdo, $empId);
    return ['count' => count($items), 'items' => array_map(function ($i) {
        return ['tag' => $i['tag'], 'name' => $i['name'], 'qty' => $i['qty'], 'unit' => $i['unit'], 'status' => $i['statusLabel']];
    }, $items)];
}

function inv_unacked_count(PDO $pdo, $empId) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM inv_items WHERE HolderEmpID = :e AND AckAt IS NULL AND Status IN ('active','repair')");
        $st->execute([':e' => (string) $empId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** The holder confirms receipt. Nobody can confirm for someone else. */
function inv_ack(PDO $pdo, $id, $actor) {
    return _inv_tx($pdo, function () use ($pdo, $id, $actor) {
        $row = _inv_lock_tx($pdo, $id);
        if (!$row || (string) $row['HolderEmpID'] !== (string) $actor) { return inv_fail('This item is not issued to you.'); }
        if ($row['AckAt'] === null) {
            $pdo->prepare("UPDATE inv_items SET AckAt = :t WHERE id = :id")->execute([':t' => inv_now(), ':id' => (int) $row['id']]);
            _inv_move_tx($pdo, (int) $row['id'], 'acknowledged', ['toEmp' => (string) $actor], $actor);
        }
        return ['ok' => true];
    });
}

/** The holder flags a problem; a manager picks it up in Repairs. */
function inv_report_problem(PDO $pdo, $id, $problem, $actor) {
    $problem = lcy_text($problem, 500);
    if ($problem === '') { return inv_fail('Describe the problem.'); }
    return _inv_tx($pdo, function () use ($pdo, $id, $problem, $actor) {
        $row = _inv_lock_tx($pdo, $id);
        if (!$row || (string) $row['HolderEmpID'] !== (string) $actor) { return inv_fail('This item is not issued to you.'); }
        $st = $pdo->prepare("SELECT COUNT(*) FROM inv_repairs WHERE ItemID = :i AND Status IN ('reported','open')");
        $st->execute([':i' => (int) $row['id']]);
        if ((int) $st->fetchColumn() > 0) { return inv_fail('A problem is already on file for this item.'); }
        $now = inv_now();
        $pdo->prepare("INSERT INTO inv_repairs (ItemID, Kind, Status, Problem, ReportedBy, ByEmpID, CreatedAt)
                       VALUES (:i, 'repair', 'reported', :p, :r, :b, :t)")
            ->execute([':i' => (int) $row['id'], ':p' => $problem, ':r' => (string) $actor, ':b' => (string) $actor, ':t' => $now]);
        return ['ok' => true];
    });
}

/** Everyone currently holding something. */
function inv_people(PDO $pdo) {
    $rows = $pdo->query("SELECT i.HolderEmpID, COUNT(*) AS Items, COALESCE(SUM(i.AckAt IS NULL), 0) AS Pending, MAX(i.IssuedOn) AS LastIssued,
            e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, e.EmpStatusID, s.StatusEmpDesc, p.PositionDesc, dp.DepartmentDesc
        FROM inv_items i
        LEFT JOIN employees e    ON e.EmpID = i.HolderEmpID
        LEFT JOIN estatus s      ON s.ID = e.EmpStatusID
        LEFT JOIN positions p    ON p.PSID = e.PosID
        LEFT JOIN empdetails d   ON d.EmpID = i.HolderEmpID
        LEFT JOIN departments dp ON dp.DepartmentID = d.EmpdepID
        WHERE i.HolderEmpID IS NOT NULL AND i.Status NOT IN ('disposed','merged')
        GROUP BY i.HolderEmpID ORDER BY e.EmpLN, e.EmpFN")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(function ($r) {
        return ['empId' => $r['HolderEmpID'], 'name' => lcy_full_name($r) ?: $r['HolderEmpID'], 'position' => (string) $r['PositionDesc'],
                'department' => (string) $r['DepartmentDesc'], 'items' => (int) $r['Items'], 'pending' => (int) $r['Pending'],
                'lastIssued' => $r['LastIssued'], 'status' => (string) $r['StatusEmpDesc'], 'left' => (int) $r['EmpStatusID'] !== 1];
    }, $rows);
}

/** Property Accountability Form data for one employee. */
function inv_accountability(PDO $pdo, $empId) {
    $emp = lcy_employee($pdo, $empId);
    if (!$emp) { return null; }
    $st = $pdo->prepare("SELECT dp.DepartmentDesc FROM empdetails d LEFT JOIN departments dp ON dp.DepartmentID = d.EmpdepID WHERE d.EmpID = :e");
    $st->execute([':e' => $emp['empId']]);
    $emp['department'] = (string) $st->fetchColumn();
    return ['employee' => $emp, 'items' => inv_held_by($pdo, $emp['empId']), 'printedOn' => inv_today()];
}

/* ------------------------------------------------------------------ supplies */

/** Insert one ledger row. */
function _inv_ledger_tx(PDO $pdo, $itemId, $type, $delta, $balance, array $f, $actor) {
    $pdo->prepare("INSERT INTO inv_stock_moves (ItemID, Type, Qty, BalanceAfter, UnitCost, Supplier, ToEmpID, ToDepartmentID, Reference, Notes, CountID, ByEmpID, CreatedAt)
                   VALUES (:i, :t, :q, :b, :c, :s, :e, :d, :r, :n, :cn, :by, :at)")
        ->execute([':i' => (int) $itemId, ':t' => $type, ':q' => (int) $delta, ':b' => (int) $balance,
                   ':c' => $f['unitCost'] ?? null, ':s' => $f['supplier'] ?? null, ':e' => $f['emp'] ?? null, ':d' => $f['dept'] ?? null,
                   ':r' => $f['reference'] ?? null, ':n' => $f['notes'] ?? null, ':cn' => $f['count'] ?? null,
                   ':by' => (string) $actor, ':at' => inv_now()]);
}

/** Change a locked consumable's balance. The balance can never go below zero. */
function _inv_stock_tx(PDO $pdo, array $row, $type, $delta, array $f, $actor) {
    $balance = (int) $row['Qty'] + (int) $delta;
    if ($balance < 0) { return inv_fail('Only ' . $row['Qty'] . ' ' . $row['Unit'] . ' of ' . $row['Name'] . ' on hand.'); }
    $pdo->prepare("UPDATE inv_items SET Qty = :q, UpdatedAt = :u WHERE id = :id")
        ->execute([':q' => $balance, ':u' => inv_now(), ':id' => (int) $row['id']]);
    _inv_ledger_tx($pdo, (int) $row['id'], $type, (int) $delta, $balance, $f, $actor);
    return ['ok' => true, 'balance' => $balance];
}

/** A locked consumable in service, or an error answer. */
function _inv_consumable_tx(PDO $pdo, $id) {
    $row = _inv_lock_tx($pdo, $id);
    if (!$row) { return inv_fail('That item no longer exists.'); }
    if ($row['Kind'] !== 'consumable') { return inv_fail('Stock in and out are for supplies only.'); }
    if ($row['Status'] !== 'active') { return inv_fail('This supply is not in service.'); }
    return ['ok' => true, 'row' => $row];
}

/** Receive stock. A given unit cost becomes the item's latest cost. */
function inv_stock_in(PDO $pdo, $id, $qty, $unitCost, $supplier, $reference, $notes, $actor) {
    $n = inv_int($qty);
    if ($n === null || $n < 1) { return inv_fail('Enter how many came in.'); }
    $rawCost = trim((string) $unitCost);
    $cost = $rawCost === '' ? null : inv_money($rawCost);
    if ($rawCost !== '' && $cost === null) { return inv_fail('Enter the unit cost as a number.'); }
    $r = _inv_tx($pdo, function () use ($pdo, $id, $n, $cost, $supplier, $reference, $notes, $actor) {
        $c = _inv_consumable_tx($pdo, $id);
        if (!$c['ok']) { return $c; }
        $sup = inv_opt($supplier, 120);
        $res = _inv_stock_tx($pdo, $c['row'], 'in', $n, ['unitCost' => $cost, 'supplier' => $sup,
            'reference' => inv_opt($reference, 60), 'notes' => inv_opt($notes, 300)], $actor);
        if ($res['ok'] && ($cost !== null || $sup !== null)) {
            $pdo->prepare("UPDATE inv_items SET UnitCost = COALESCE(:c, UnitCost), Supplier = COALESCE(:s, Supplier) WHERE id = :id")
                ->execute([':c' => $cost, ':s' => $sup, ':id' => (int) $id]);
        }
        return $res + ['tag' => $c['row']['TagNo']];
    });
    if ($r['ok']) { inv_audit($pdo, 'stock in ' . $n . ' of ' . $r['tag']); }
    return $r;
}

/**
 * Release stock to an employee or a department, with a purpose. An employee's
 * department is kept on the row, so the usage report groups every release.
 */
function inv_stock_out(PDO $pdo, $id, $qty, $empId, $departmentId, $purpose, $reference, $actor) {
    $n = inv_int($qty);
    if ($n === null || $n < 1) { return inv_fail('Enter how many went out.'); }
    $purpose = lcy_text($purpose, 300);
    if ($purpose === '') { return inv_fail('Enter what it is for.'); }
    $empId = trim((string) $empId);
    $dept  = (int) $departmentId;
    if ($empId !== '') {
        $emp = lcy_employee($pdo, $empId);
        if (!$emp) { return inv_fail('That employee no longer exists.'); }
        $st = $pdo->prepare("SELECT EmpdepID FROM empdetails WHERE EmpID = :e");
        $st->execute([':e' => $emp['empId']]);
        $dept = (int) $st->fetchColumn() ?: $dept;
    } elseif ($dept > 0) {
        $st = $pdo->prepare("SELECT 1 FROM departments WHERE DepartmentID = :d");
        $st->execute([':d' => $dept]);
        if (!$st->fetchColumn()) { return inv_fail('Choose a department.'); }
    } else {
        return inv_fail('Choose who receives it: an employee or a department.');
    }
    $r = _inv_tx($pdo, function () use ($pdo, $id, $n, $empId, $dept, $purpose, $reference, $actor) {
        $c = _inv_consumable_tx($pdo, $id);
        if (!$c['ok']) { return $c; }
        $res = _inv_stock_tx($pdo, $c['row'], 'out', -$n, ['emp' => $empId !== '' ? $empId : null, 'dept' => $dept ?: null,
            'reference' => inv_opt($reference, 60), 'notes' => $purpose], $actor);
        return $res + ['tag' => $c['row']['TagNo']];
    });
    if ($r['ok']) { inv_audit($pdo, 'stock out ' . $n . ' of ' . $r['tag']); }
    return $r;
}

/** Correct the balance to what is really there. Corrections are new rows, never edits. */
function inv_stock_adjust(PDO $pdo, $id, $newQty, $reason, $actor) {
    $n = inv_int($newQty);
    if ($n === null || $n < 0) { return inv_fail('Enter the correct quantity on hand.'); }
    $reason = lcy_text($reason, 300);
    if ($reason === '') { return inv_fail('Enter the reason for the correction.'); }
    $r = _inv_tx($pdo, function () use ($pdo, $id, $n, $reason, $actor) {
        $c = _inv_consumable_tx($pdo, $id);
        if (!$c['ok']) { return $c; }
        $delta = $n - (int) $c['row']['Qty'];
        if ($delta === 0) { return inv_fail('That is already the quantity on hand.'); }
        $res = _inv_stock_tx($pdo, $c['row'], 'adjust', $delta, ['notes' => $reason], $actor);
        return $res + ['tag' => $c['row']['TagNo'], 'delta' => $delta];
    });
    if ($r['ok']) { inv_audit($pdo, 'stock adjusted ' . $r['tag'] . ' by ' . $r['delta']); }
    return $r;
}

function inv_stock_ledger(PDO $pdo, $itemId, $limit = 200) {
    $st = $pdo->prepare("SELECT m.*, e.EmpFN, e.EmpLN, d.DepartmentDesc, b.EmpFN AS BFN, b.EmpLN AS BLN
        FROM inv_stock_moves m
        LEFT JOIN employees e    ON e.EmpID = m.ToEmpID
        LEFT JOIN departments d  ON d.DepartmentID = m.ToDepartmentID
        LEFT JOIN employees b    ON b.EmpID = m.ByEmpID
        WHERE m.ItemID = :i ORDER BY m.id DESC LIMIT " . (int) $limit);
    $st->execute([':i' => (int) $itemId]);
    return array_map(function ($r) {
        return ['id' => (int) $r['id'], 'type' => $r['Type'], 'qty' => (int) $r['Qty'], 'balance' => (int) $r['BalanceAfter'],
                'unitCost' => $r['UnitCost'] === null ? null : (float) $r['UnitCost'], 'supplier' => (string) $r['Supplier'],
                'to' => trim((string) $r['EmpFN'] . ' ' . (string) $r['EmpLN']), 'department' => (string) $r['DepartmentDesc'],
                'reference' => (string) $r['Reference'], 'notes' => (string) $r['Notes'],
                'by' => trim((string) $r['BFN'] . ' ' . (string) $r['BLN']), 'at' => $r['CreatedAt']];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Quantity released per item, department and employee in a date range (stock out only). */
function inv_usage_report(PDO $pdo, $from, $to) {
    $from = lcy_date($from) ?: date('Y-m-01');
    $to   = lcy_date($to) ?: inv_today();
    $st = $pdo->prepare("SELECT i.id, i.TagNo, i.Name, i.Unit, i.UnitCost, m.ToEmpID, m.ToDepartmentID,
            d.DepartmentDesc, e.EmpFN, e.EmpLN, -SUM(m.Qty) AS Released, COUNT(*) AS LineCount
        FROM inv_stock_moves m
        JOIN inv_items i        ON i.id = m.ItemID
        LEFT JOIN departments d ON d.DepartmentID = m.ToDepartmentID
        LEFT JOIN employees e   ON e.EmpID = m.ToEmpID
        WHERE m.Type = 'out' AND m.CreatedAt >= :f AND m.CreatedAt < DATE_ADD(:t, INTERVAL 1 DAY)
        GROUP BY i.id, i.TagNo, i.Name, i.Unit, i.UnitCost, m.ToEmpID, m.ToDepartmentID, d.DepartmentDesc, e.EmpFN, e.EmpLN
        ORDER BY i.Name, d.DepartmentDesc, e.EmpLN");
    $st->execute([':f' => $from, ':t' => $to]);
    $rows = array_map(function ($r) {
        $cost = $r['UnitCost'] === null ? null : round((float) $r['UnitCost'] * (int) $r['Released'], 2);
        return ['itemId' => (int) $r['id'], 'tag' => $r['TagNo'], 'name' => $r['Name'], 'unit' => $r['Unit'],
                'department' => (string) $r['DepartmentDesc'], 'employee' => trim((string) $r['EmpFN'] . ' ' . (string) $r['EmpLN']),
                'released' => (int) $r['Released'], 'lines' => (int) $r['LineCount'], 'value' => $cost];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
    return ['from' => $from, 'to' => $to, 'rows' => $rows];
}

/* ------------------------------------------------------------------ repairs */

function inv_repairs(PDO $pdo, array $f = []) {
    $where = [];
    $args  = [];
    if (!empty($f['item'])) { $where[] = 'r.ItemID = :i'; $args[':i'] = (int) $f['item']; }
    if (!empty($f['open'])) { $where[] = "r.Status IN ('reported','open')"; }
    $st = $pdo->prepare("SELECT r.*, i.TagNo, i.Name, i.Status AS ItemStatus, e.EmpFN, e.EmpLN FROM inv_repairs r
        JOIN inv_items i ON i.id = r.ItemID
        LEFT JOIN employees e ON e.EmpID = r.ReportedBy"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY FIELD(r.Status, 'reported', 'open', 'done', 'cancelled'), r.id DESC LIMIT 300");
    $st->execute($args);
    return array_map(function ($r) {
        return ['id' => (int) $r['id'], 'itemId' => (int) $r['ItemID'], 'tag' => $r['TagNo'], 'name' => $r['Name'],
                'kind' => $r['Kind'], 'status' => $r['Status'], 'problem' => $r['Problem'], 'vendor' => (string) $r['Vendor'],
                'sentOn' => $r['SentOn'], 'returnedOn' => $r['ReturnedOn'], 'cost' => $r['Cost'] === null ? null : (float) $r['Cost'],
                'result' => (string) $r['Result'], 'reportedBy' => trim((string) $r['EmpFN'] . ' ' . (string) $r['EmpLN']),
                'createdAt' => $r['CreatedAt'], 'closedAt' => $r['ClosedAt']];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Back in service only when it was under repair and nothing else is still open. */
function _inv_repair_settle_tx(PDO $pdo, $itemId) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM inv_repairs WHERE ItemID = :i AND Status = 'open'");
    $st->execute([':i' => (int) $itemId]);
    if ((int) $st->fetchColumn() > 0) { return; }
    $pdo->prepare("UPDATE inv_items SET Status = 'active', UpdatedAt = :u WHERE id = :id AND Status = 'repair'")
        ->execute([':u' => inv_now(), ':id' => (int) $itemId]);
}

/**
 * Open a repair: from a holder's report ($repairId), or directly on an item.
 * Part of a lot is split off first.
 */
function inv_repair_open(PDO $pdo, array $d, $actor) {
    $kind = ($d['kind'] ?? 'repair') === 'service' ? 'service' : 'repair';
    $problem = lcy_text($d['problem'] ?? '', 500);
    $r = _inv_tx($pdo, function () use ($pdo, $d, $kind, $problem, $actor) {
        $repairId = (int) ($d['repairId'] ?? 0);
        $rep = null;
        if ($repairId > 0) {
            $st = $pdo->prepare("SELECT * FROM inv_repairs WHERE id = :id FOR UPDATE");
            $st->execute([':id' => $repairId]);
            $rep = $st->fetch(PDO::FETCH_ASSOC);
            if (!$rep || $rep['Status'] !== 'reported') { return inv_fail('That report was already handled.'); }
        }
        $row = _inv_lock_tx($pdo, $rep ? $rep['ItemID'] : ($d['id'] ?? 0));
        if (!$row) { return inv_fail('That item no longer exists.'); }
        if (!_inv_held_kind($row) || $row['Kind'] === 'document') { return inv_fail('Repairs are for assets.'); }
        if ($row['Status'] !== 'active') { return inv_fail('Only an item in service can go to repair.'); }
        $text = $problem !== '' ? $problem : ($rep['Problem'] ?? '');
        if ($text === '') { return inv_fail('Describe the problem.'); }
        $part = $rep ? null : inv_part_qty($d['qty'] ?? '', $row);
        if ($part === false) { return inv_fail('Between 1 and ' . $row['Qty'] . '.'); }
        $target = _inv_portion_tx($pdo, $row, $part, $actor);
        $vendor = inv_opt($d['vendor'] ?? '', 120);
        $sent   = lcy_date($d['sentOn'] ?? '') ?: inv_today();
        if ($rep) {
            $pdo->prepare("UPDATE inv_repairs SET Status = 'open', Kind = :k, Problem = :p, Vendor = :v, SentOn = :s, ByEmpID = :b WHERE id = :id")
                ->execute([':k' => $kind, ':p' => $text, ':v' => $vendor, ':s' => $sent, ':b' => (string) $actor, ':id' => (int) $rep['id']]);
            $rid = (int) $rep['id'];
        } else {
            $pdo->prepare("INSERT INTO inv_repairs (ItemID, Kind, Status, Problem, Vendor, SentOn, ByEmpID, CreatedAt)
                           VALUES (:i, :k, 'open', :p, :v, :s, :b, :t)")
                ->execute([':i' => (int) $target['id'], ':k' => $kind, ':p' => $text, ':v' => $vendor, ':s' => $sent,
                           ':b' => (string) $actor, ':t' => inv_now()]);
            $rid = (int) $pdo->lastInsertId();
        }
        $pdo->prepare("UPDATE inv_items SET Status = 'repair', UpdatedAt = :u WHERE id = :id")->execute([':u' => inv_now(), ':id' => (int) $target['id']]);
        _inv_move_tx($pdo, (int) $target['id'], 'repair_open', ['ref' => $rid, 'notes' => $text], $actor);
        return ['ok' => true, 'id' => $rid, 'itemId' => (int) $target['id'], 'tag' => $target['TagNo']];
    });
    if ($r['ok']) { inv_audit($pdo, 'repair opened on ' . $r['tag']); }
    return $r;
}

function inv_repair_close(PDO $pdo, $id, array $d, $actor) {
    $rawCost = trim((string) ($d['cost'] ?? ''));
    $cost = $rawCost === '' ? null : inv_money($rawCost);
    if ($rawCost !== '' && $cost === null) { return inv_fail('Enter the cost as a number.'); }
    $returned = lcy_date($d['returnedOn'] ?? '') ?: inv_today();
    return _inv_tx($pdo, function () use ($pdo, $id, $d, $cost, $returned, $actor) {
        $st = $pdo->prepare("SELECT * FROM inv_repairs WHERE id = :id FOR UPDATE");
        $st->execute([':id' => (int) $id]);
        $rep = $st->fetch(PDO::FETCH_ASSOC);
        if (!$rep || $rep['Status'] !== 'open') { return inv_fail('That repair is not open.'); }
        _inv_lock_tx($pdo, $rep['ItemID']);
        $result = inv_opt($d['result'] ?? '', 500);
        $pdo->prepare("UPDATE inv_repairs SET Status = 'done', ReturnedOn = :r, Cost = :c, Result = :x, ClosedAt = :t WHERE id = :id")
            ->execute([':r' => $returned, ':c' => $cost, ':x' => $result, ':t' => inv_now(), ':id' => (int) $id]);
        _inv_repair_settle_tx($pdo, $rep['ItemID']);
        _inv_move_tx($pdo, (int) $rep['ItemID'], 'repair_close', ['ref' => (int) $id, 'notes' => $result], $actor);
        return ['ok' => true];
    });
}

function inv_repair_cancel(PDO $pdo, $id, $note, $actor) {
    return _inv_tx($pdo, function () use ($pdo, $id, $note, $actor) {
        $st = $pdo->prepare("SELECT * FROM inv_repairs WHERE id = :id FOR UPDATE");
        $st->execute([':id' => (int) $id]);
        $rep = $st->fetch(PDO::FETCH_ASSOC);
        if (!$rep || !in_array($rep['Status'], ['reported', 'open'], true)) { return inv_fail('That repair is already closed.'); }
        _inv_lock_tx($pdo, $rep['ItemID']);
        $pdo->prepare("UPDATE inv_repairs SET Status = 'cancelled', Result = :x, ClosedAt = :t WHERE id = :id")
            ->execute([':x' => inv_opt($note, 500), ':t' => inv_now(), ':id' => (int) $id]);
        _inv_repair_settle_tx($pdo, $rep['ItemID']);
        if ($rep['Status'] === 'open') {
            _inv_move_tx($pdo, (int) $rep['ItemID'], 'repair_close', ['ref' => (int) $id, 'notes' => 'Cancelled'], $actor);
        }
        return ['ok' => true];
    });
}

/* ------------------------------------------------------------------ files */

/** uploads/inventory/, created with a deny-all .htaccess on first use. */
function inv_dir(?string $dir = null) {
    $dir = $dir ?? dirname(__DIR__) . '/uploads/inventory';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess',
            "# Inventory photos and scans: served only through query/inventory-file.php\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/** A photo is re-drawn as a JPEG, so nothing but the picture is kept. */
function inv_store_photo(array $f, $uploaded = true, ?string $dir = null) {
    require_once __DIR__ . '/applicants-lib.php';
    return apl_store_image($f, $uploaded, inv_dir($dir));
}

/** A scan or receipt: PDF or picture, the content must match the extension. */
function inv_store_doc(array $f, $uploaded = true, ?string $dir = null) {
    require_once __DIR__ . '/msg-files.php';
    $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, INV_DOC_TYPES, true)) { return inv_fail('Attach a PDF, JPG or PNG file.'); }
    $r = mf_store($f, (bool) $uploaded, inv_dir($dir));
    if (!$r['ok']) { return inv_fail($r['error']); }
    $card = json_decode($r['text'], true);
    return ['ok' => true, 'key' => $card['k'], 'name' => $card['n']];
}

function inv_files(PDO $pdo, $itemId) {
    $st = $pdo->prepare("SELECT * FROM inv_files WHERE ItemID = :i ORDER BY id");
    $st->execute([':i' => (int) $itemId]);
    return array_map(function ($r) {
        return ['id' => (int) $r['id'], 'kind' => $r['Kind'], 'name' => (string) $r['Name'], 'size' => (int) $r['Size'],
                'pdf' => substr($r['FileKey'], -4) === '.pdf', 'at' => $r['CreatedAt']];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Store one upload for an item. $uploaded = false only for tests. */
function inv_file_add(PDO $pdo, $itemId, $kind, array $f, $actor, $uploaded = true, ?string $dir = null) {
    if (!isset(INV_FILE_KINDS[(string) $kind])) { return inv_fail('Choose what kind of file this is.'); }
    if (!inv_item($pdo, $itemId)) { return inv_fail('That item no longer exists.'); }
    $stored = $kind === 'photo' ? inv_store_photo($f, $uploaded, $dir) : inv_store_doc($f, $uploaded, $dir);
    if (!$stored['ok']) { return $stored; }
    $name = (string) ($stored['name'] ?? ($f['name'] ?? 'photo'));
    $path = inv_dir($dir) . '/' . $stored['key'];
    $pdo->prepare("INSERT INTO inv_files (ItemID, Kind, FileKey, Name, Size, ByEmpID, CreatedAt) VALUES (:i, :k, :f, :n, :s, :b, :t)")
        ->execute([':i' => (int) $itemId, ':k' => $kind, ':f' => $stored['key'], ':n' => mb_substr($name, 0, 100),
                   ':s' => (int) @filesize($path), ':b' => (string) $actor, ':t' => inv_now()]);
    return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
}

/** Full path of a stored file, or null when the key isn't one of ours / the file is gone. */
function inv_file_path($key, ?string $dir = null) {
    if (!is_string($key) || !preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$#', $key)) { return null; }
    $path = inv_dir($dir) . '/' . $key;
    return is_file($path) ? $path : null;
}

function inv_file_delete(PDO $pdo, $id, ?string $dir = null) {
    $st = $pdo->prepare("SELECT * FROM inv_files WHERE id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { return inv_fail('That file no longer exists.'); }
    $pdo->prepare("DELETE FROM inv_files WHERE id = :id")->execute([':id' => (int) $id]);
    $path = inv_file_path($r['FileKey'], $dir);
    if ($path) { @unlink($path); }
    return ['ok' => true, 'itemId' => (int) $r['ItemID']];
}

/** The row of a file this viewer may open: managers any file, the holder the photos of their item. */
function inv_file_for_viewer(PDO $pdo, $id, $actor, $canManage) {
    $st = $pdo->prepare("SELECT f.*, i.HolderEmpID FROM inv_files f JOIN inv_items i ON i.id = f.ItemID WHERE f.id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { return null; }
    if ($canManage) { return $r; }
    return ($r['Kind'] === 'photo' && (string) $r['HolderEmpID'] === (string) $actor) ? $r : null;
}

/* ------------------------------------------------------------------ physical counts */

function inv_open_count_for(PDO $pdo, $locationId) {
    $st = $pdo->prepare("SELECT id, Title FROM inv_counts WHERE LocationID = :l AND Status = 'open' ORDER BY id DESC LIMIT 1");
    $st->execute([':l' => (int) $locationId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? ['id' => (int) $r['id'], 'title' => $r['Title']] : null;
}

function inv_counts(PDO $pdo) {
    $rows = $pdo->query("SELECT n.*, l.Name AS LocName,
            (SELECT COUNT(*) FROM inv_count_lines x WHERE x.CountID = n.id) AS Total,
            (SELECT COUNT(*) FROM inv_count_lines x WHERE x.CountID = n.id AND x.CheckedAt IS NOT NULL) AS Checked,
            (SELECT COUNT(*) FROM inv_count_lines x WHERE x.CountID = n.id AND x.Result IN ('missing','misplaced','extra','moved')) AS Variances
        FROM inv_counts n JOIN inv_locations l ON l.id = n.LocationID
        ORDER BY FIELD(n.Status, 'open', 'closed'), n.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(function ($r) {
        return ['id' => (int) $r['id'], 'title' => $r['Title'], 'location' => $r['LocName'], 'status' => $r['Status'],
                'startedAt' => $r['StartedAt'], 'closedAt' => $r['ClosedAt'], 'total' => (int) $r['Total'],
                'checked' => (int) $r['Checked'], 'variances' => (int) $r['Variances']];
    }, $rows);
}

/** One count with its lines. */
function inv_count(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT n.*, l.Name AS LocName FROM inv_counts n JOIN inv_locations l ON l.id = n.LocationID WHERE n.id = :id");
    $st->execute([':id' => (int) $id]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) { return null; }
    $st = $pdo->prepare("SELECT x.*, i.TagNo, i.Name, i.Unit, i.Spot, i.Status AS ItemStatus, ic.Kind, h.EmpFN, h.EmpLN, l.Name AS ItemLoc
        FROM inv_count_lines x
        LEFT JOIN inv_items i      ON i.id = x.ItemID
        LEFT JOIN inv_categories ic ON ic.id = i.CategoryID
        LEFT JOIN inv_locations l  ON l.id = i.LocationID
        LEFT JOIN employees h      ON h.EmpID = i.HolderEmpID
        WHERE x.CountID = :c ORDER BY x.CheckedAt IS NOT NULL, i.Spot, i.TagNo, x.id");
    $st->execute([':c' => (int) $id]);
    $lines = array_map(function ($r) {
        return ['id' => (int) $r['id'], 'itemId' => $r['ItemID'] === null ? null : (int) $r['ItemID'],
                'tag' => (string) $r['TagNo'], 'name' => $r['ItemID'] === null ? (string) $r['ExtraName'] : (string) $r['Name'],
                'unit' => (string) $r['Unit'], 'spot' => (string) $r['Spot'], 'kind' => (string) $r['Kind'],
                'holder' => trim((string) $r['EmpFN'] . ' ' . (string) $r['EmpLN']), 'itemLocation' => (string) $r['ItemLoc'],
                'expected' => (int) $r['ExpectedQty'], 'found' => $r['FoundQty'] === null ? null : (int) $r['FoundQty'],
                'result' => (string) $r['Result'], 'condition' => (string) $r['ItemCondition'], 'notes' => (string) $r['Notes'],
                'applied' => (int) $r['Applied'] === 1, 'checkedAt' => $r['CheckedAt']];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
    return ['id' => (int) $c['id'], 'title' => $c['Title'], 'locationId' => (int) $c['LocationID'], 'location' => $c['LocName'],
            'status' => $c['Status'], 'notes' => (string) $c['Notes'], 'startedAt' => $c['StartedAt'], 'closedAt' => $c['ClosedAt'], 'lines' => $lines];
}

/** Open a count: one line for everything recorded at the location. One open count per location. */
function inv_count_start(PDO $pdo, $locationId, $title, $notes, $actor) {
    $st = $pdo->prepare("SELECT Name FROM inv_locations WHERE id = :id AND Active = 1");
    $st->execute([':id' => (int) $locationId]);
    $loc = $st->fetchColumn();
    if (!$loc) { return inv_fail('Choose a location.'); }
    $title = lcy_text($title, 120) ?: ('Count of ' . $loc . ', ' . inv_today());
    $r = _inv_tx($pdo, function () use ($pdo, $locationId, $title, $notes, $actor) {
        $st = $pdo->prepare("SELECT id FROM inv_counts WHERE LocationID = :l AND Status = 'open' FOR UPDATE");
        $st->execute([':l' => (int) $locationId]);
        if ($st->fetchColumn()) { return inv_fail('This location already has an open count.'); }
        $pdo->prepare("INSERT INTO inv_counts (LocationID, Title, Notes, StartedBy, StartedAt) VALUES (:l, :t, :n, :b, :at)")
            ->execute([':l' => (int) $locationId, ':t' => $title, ':n' => inv_opt($notes, 500), ':b' => (string) $actor, ':at' => inv_now()]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO inv_count_lines (CountID, ItemID, ExpectedQty)
                       SELECT :c, i.id, i.Qty FROM inv_items i WHERE i.LocationID = :l AND i.Status IN ('active','repair')")
            ->execute([':c' => $id, ':l' => (int) $locationId]);
        return ['ok' => true, 'id' => $id];
    });
    if ($r['ok']) { inv_audit($pdo, 'count started: ' . $title); }
    return $r;
}

/** An open count, locked. */
function _inv_open_count_tx(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT * FROM inv_counts WHERE id = :id FOR UPDATE");
    $st->execute([':id' => (int) $id]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    return ($c && $c['Status'] === 'open') ? $c : null;
}

/**
 * Tick an item off, by id or tag. The expected quantity is read again now,
 * because the item may have moved since the count began. An item recorded
 * elsewhere becomes a "misplaced" line.
 */
function inv_count_check(PDO $pdo, $countId, array $d, $actor) {
    return _inv_tx($pdo, function () use ($pdo, $countId, $d, $actor) {
        $count = _inv_open_count_tx($pdo, $countId);
        if (!$count) { return inv_fail('That count is closed.'); }
        $itemId = (int) ($d['itemId'] ?? 0);
        if ($itemId === 0) {
            $it = inv_by_tag($pdo, $d['tag'] ?? '', (int) $count['LocationID']);
            if (!$it) { return inv_fail('No item has that tag.'); }
            $itemId = $it['id'];
        }
        $row = _inv_lock_tx($pdo, $itemId);
        if (!$row) { return inv_fail('That item no longer exists.'); }
        if (in_array($row['Status'], ['disposed', 'merged'], true)) { return inv_fail($row['TagNo'] . ' is off the register.'); }
        $here = (int) $row['LocationID'] === (int) $count['LocationID'];
        $expected = $here ? (int) $row['Qty'] : 0;
        $raw = trim((string) ($d['found'] ?? ''));
        $found = $raw === '' ? (int) $row['Qty'] : inv_int($raw);
        if ($found === null || $found < 0) { return inv_fail('Enter the quantity found.'); }
        $result = !$here ? 'misplaced' : ($found === 0 ? 'missing' : 'found');
        $cond = isset(INV_CONDITIONS[(string) ($d['condition'] ?? '')]) ? $d['condition'] : null;
        $st = $pdo->prepare("SELECT id FROM inv_count_lines WHERE CountID = :c AND ItemID = :i");
        $st->execute([':c' => (int) $countId, ':i' => (int) $row['id']]);
        $lineId = (int) $st->fetchColumn();
        $args = [':e' => $expected, ':f' => $found, ':r' => $result, ':k' => $cond, ':n' => inv_opt($d['notes'] ?? '', 300),
                 ':b' => (string) $actor, ':t' => inv_now()];
        if ($lineId) {
            $pdo->prepare("UPDATE inv_count_lines SET ExpectedQty = :e, FoundQty = :f, Result = :r, ItemCondition = :k, Notes = :n,
                           ByEmpID = :b, CheckedAt = :t WHERE id = :id")->execute($args + [':id' => $lineId]);
        } else {
            $pdo->prepare("INSERT INTO inv_count_lines (CountID, ItemID, ExpectedQty, FoundQty, Result, ItemCondition, Notes, ByEmpID, CheckedAt)
                           VALUES (:c, :i, :e, :f, :r, :k, :n, :b, :t)")->execute($args + [':c' => (int) $countId, ':i' => (int) $row['id']]);
            $lineId = (int) $pdo->lastInsertId();
        }
        return ['ok' => true, 'lineId' => $lineId, 'result' => $result, 'tag' => $row['TagNo'], 'itemName' => $row['Name']];
    });
}

/** Undo a tick. Lines that exist only because of the tick go away. */
function inv_count_uncheck(PDO $pdo, $lineId, $actor) {
    return _inv_tx($pdo, function () use ($pdo, $lineId) {
        $st = $pdo->prepare("SELECT * FROM inv_count_lines WHERE id = :id");
        $st->execute([':id' => (int) $lineId]);
        $l = $st->fetch(PDO::FETCH_ASSOC);
        if (!$l || !_inv_open_count_tx($pdo, $l['CountID'])) { return inv_fail('That count is closed.'); }
        if (in_array($l['Result'], ['misplaced', 'extra'], true)) {
            $pdo->prepare("DELETE FROM inv_count_lines WHERE id = :id")->execute([':id' => (int) $lineId]);
        } else {
            $pdo->prepare("UPDATE inv_count_lines SET FoundQty = NULL, Result = NULL, ItemCondition = NULL, Notes = NULL, ByEmpID = NULL, CheckedAt = NULL WHERE id = :id")
                ->execute([':id' => (int) $lineId]);
        }
        return ['ok' => true];
    });
}

/** Something on site that the register doesn't know about. */
function inv_count_extra(PDO $pdo, $countId, $name, $qty, $notes, $actor) {
    $name = lcy_text($name, 150);
    if ($name === '') { return inv_fail('Describe what was found.'); }
    $n = inv_int($qty === '' ? 1 : $qty);
    if ($n === null || $n < 1) { return inv_fail('Enter the quantity found.'); }
    return _inv_tx($pdo, function () use ($pdo, $countId, $name, $n, $notes, $actor) {
        if (!_inv_open_count_tx($pdo, $countId)) { return inv_fail('That count is closed.'); }
        $pdo->prepare("INSERT INTO inv_count_lines (CountID, ItemID, ExtraName, ExpectedQty, FoundQty, Result, Notes, ByEmpID, CheckedAt)
                       VALUES (:c, NULL, :x, 0, :f, 'extra', :n, :b, :t)")
            ->execute([':c' => (int) $countId, ':x' => $name, ':f' => $n, ':n' => inv_opt($notes, 300), ':b' => (string) $actor, ':t' => inv_now()]);
        return ['ok' => true];
    });
}

/**
 * Close a count. Unticked lines become "missing", or "moved" when the item has
 * left the location since the count began. Found items get their counted date.
 */
function inv_count_close(PDO $pdo, $id, $actor) {
    $r = _inv_tx($pdo, function () use ($pdo, $id, $actor) {
        $count = _inv_open_count_tx($pdo, $id);
        if (!$count) { return inv_fail('That count is already closed.'); }
        $st = $pdo->prepare("SELECT x.id, x.ItemID, x.Result, i.LocationID, i.Status, i.Qty FROM inv_count_lines x
                             JOIN inv_items i ON i.id = x.ItemID WHERE x.CountID = :c");
        $st->execute([':c' => (int) $id]);
        $today = inv_today();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            if ($l['Result'] === null) {
                $gone = (int) $l['LocationID'] !== (int) $count['LocationID'] || in_array($l['Status'], ['disposed', 'merged', 'lost'], true);
                $pdo->prepare("UPDATE inv_count_lines SET Result = :r, FoundQty = 0, ExpectedQty = :e WHERE id = :id")
                    ->execute([':r' => $gone ? 'moved' : 'missing', ':e' => $gone ? 0 : (int) $l['Qty'], ':id' => (int) $l['id']]);
            } elseif (in_array($l['Result'], ['found', 'misplaced'], true)) {
                $pdo->prepare("UPDATE inv_items SET LastCountedOn = :d WHERE id = :id")->execute([':d' => $today, ':id' => (int) $l['ItemID']]);
                _inv_move_tx($pdo, (int) $l['ItemID'], 'counted', ['ref' => (int) $id], $actor);
            }
        }
        $pdo->prepare("UPDATE inv_counts SET Status = 'closed', ClosedBy = :b, ClosedAt = :t WHERE id = :id")
            ->execute([':b' => (string) $actor, ':t' => inv_now(), ':id' => (int) $id]);
        return ['ok' => true, 'title' => $count['Title']];
    });
    if ($r['ok']) { inv_audit($pdo, 'count closed: ' . $r['title']); }
    return $r;
}

/**
 * After closing: act on the differences. Every line applies once.
 *   misplaced  move the items to the counted location
 *   missing    mark shortages lost (a partial shortage splits the lot)
 *   stock      post the supply differences to the ledger
 * @return array{ok:bool, msg?:string, applied?:int}
 */
function inv_count_apply(PDO $pdo, $id, $what, $actor) {
    if (!in_array($what, ['misplaced', 'missing', 'stock'], true)) { return inv_fail('Choose what to apply.'); }
    $r = _inv_tx($pdo, function () use ($pdo, $id, $what, $actor) {
        $st = $pdo->prepare("SELECT * FROM inv_counts WHERE id = :id FOR UPDATE");
        $st->execute([':id' => (int) $id]);
        $count = $st->fetch(PDO::FETCH_ASSOC);
        if (!$count || $count['Status'] !== 'closed') { return inv_fail('Close the count first.'); }
        $st = $pdo->prepare("SELECT x.*, c.Kind FROM inv_count_lines x JOIN inv_items i ON i.id = x.ItemID
                             JOIN inv_categories c ON c.id = i.CategoryID WHERE x.CountID = :c AND x.Applied = 0 AND x.Result IS NOT NULL");
        $st->execute([':c' => (int) $id]);
        $done = $pdo->prepare("UPDATE inv_count_lines SET Applied = 1 WHERE id = :id");
        $applied = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $consumable = $l['Kind'] === 'consumable';
            $short = (int) $l['ExpectedQty'] - (int) $l['FoundQty'];
            if ($what === 'misplaced') {
                if ($l['Result'] !== 'misplaced' || $consumable) { continue; }
                $row = _inv_lock_tx($pdo, $l['ItemID']);
                if ($row && $row['Status'] === 'active' && !$row['HolderEmpID']) {
                    $pdo->prepare("UPDATE inv_items SET LocationID = :l, Spot = NULL, UpdatedAt = :u WHERE id = :id")
                        ->execute([':l' => (int) $count['LocationID'], ':u' => inv_now(), ':id' => (int) $row['id']]);
                    _inv_move_tx($pdo, (int) $row['id'], 'transferred', ['fromLoc' => (int) $row['LocationID'], 'toLoc' => (int) $count['LocationID'],
                        'qty' => (int) $row['Qty'], 'ref' => (int) $id, 'notes' => 'Physical count'], $actor);
                    $applied++;
                }
            } elseif ($what === 'missing') {
                if ($consumable || $short <= 0 || !in_array($l['Result'], ['missing', 'found'], true)) { continue; }
                $row = _inv_lock_tx($pdo, $l['ItemID']);
                if ($row && $row['Status'] === 'active') {
                    $part = min($short, (int) $row['Qty']);
                    $target = _inv_portion_tx($pdo, $row, $part, $actor);
                    $pdo->prepare("UPDATE inv_items SET Status = 'lost', UpdatedAt = :u WHERE id = :id")->execute([':u' => inv_now(), ':id' => (int) $target['id']]);
                    _inv_move_tx($pdo, (int) $target['id'], 'lost', ['qty' => (int) $target['Qty'], 'ref' => (int) $id, 'notes' => 'Physical count'], $actor);
                    $applied++;
                }
            } else {
                if (!$consumable || !in_array($l['Result'], ['missing', 'found'], true) || $short === 0) { continue; }
                $row = _inv_lock_tx($pdo, $l['ItemID']);
                if ($row) {
                    $res = _inv_stock_tx($pdo, $row, 'adjust', -$short, ['count' => (int) $id, 'notes' => 'Physical count: ' . $count['Title']], $actor);
                    if (!$res['ok']) { return $res; }
                    $applied++;
                }
            }
            $done->execute([':id' => (int) $l['id']]);
        }
        return ['ok' => true, 'applied' => $applied, 'title' => $count['Title']];
    });
    if ($r['ok'] && $r['applied'] > 0) { inv_audit($pdo, 'count applied (' . $what . ', ' . $r['applied'] . ' lines): ' . $r['title']); }
    return $r;
}

/* ------------------------------------------------------------------ alerts */

/**
 * What needs attention, most urgent first. WeDo has no scheduled jobs, so
 * alerts are worked out when someone looks: documents inside their reminder
 * window, warranties within 30 days, receipts unconfirmed for a week, reported
 * problems and low stock.
 */
function inv_alerts(PDO $pdo) {
    $today = inv_today();
    $out = [];
    $add = function ($type, $tone, array $r, $detail, $date = null) use (&$out) {
        $out[] = ['type' => $type, 'tone' => $tone, 'itemId' => (int) $r['id'], 'tag' => $r['TagNo'], 'name' => $r['Name'],
                  'detail' => $detail, 'date' => $date];
    };
    $st = $pdo->prepare("SELECT id, TagNo, Name, ExpiresOn FROM inv_items
        WHERE ExpiresOn IS NOT NULL AND Status NOT IN ('disposed','merged') AND ExpiresOn <= DATE_ADD(:t, INTERVAL RemindDays DAY) ORDER BY ExpiresOn");
    $st->execute([':t' => $today]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $add('expiry', $r['ExpiresOn'] < $today ? 'danger' : 'warn', $r, $r['ExpiresOn'] < $today ? 'Expired' : 'Expires soon', $r['ExpiresOn']);
    }
    $st = $pdo->prepare("SELECT id, TagNo, Name, WarrantyUntil FROM inv_items
        WHERE WarrantyUntil IS NOT NULL AND Status NOT IN ('disposed','merged') AND WarrantyUntil >= :t AND WarrantyUntil <= DATE_ADD(:t2, INTERVAL 30 DAY) ORDER BY WarrantyUntil");
    $st->execute([':t' => $today, ':t2' => $today]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $add('warranty', 'warn', $r, 'Warranty ends soon', $r['WarrantyUntil']); }

    foreach ($pdo->query("SELECT i.id, i.TagNo, i.Name, i.Qty, i.Unit, i.ReorderLevel FROM inv_items i JOIN inv_categories c ON c.id = i.CategoryID
        WHERE c.Kind = 'consumable' AND i.Status = 'active' AND i.ReorderLevel IS NOT NULL AND i.Qty <= i.ReorderLevel ORDER BY i.Name")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $add('low', (int) $r['Qty'] === 0 ? 'danger' : 'warn', $r, ((int) $r['Qty'] === 0 ? 'Out of stock' : $r['Qty'] . ' ' . $r['Unit'] . ' left') . ', reorder at ' . $r['ReorderLevel']);
    }
    foreach ($pdo->query("SELECT i.id, i.TagNo, i.Name, r.Problem, r.CreatedAt FROM inv_repairs r JOIN inv_items i ON i.id = r.ItemID
        WHERE r.Status = 'reported' ORDER BY r.id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $add('problem', 'danger', $r, 'Problem reported: ' . $r['Problem'], substr($r['CreatedAt'], 0, 10));
    }
    $st = $pdo->prepare("SELECT id, TagNo, Name, IssuedOn FROM inv_items
        WHERE HolderEmpID IS NOT NULL AND AckAt IS NULL AND Status IN ('active','repair') AND IssuedOn <= DATE_SUB(:t, INTERVAL 7 DAY) ORDER BY IssuedOn");
    $st->execute([':t' => $today]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $add('unconfirmed', 'info', $r, 'Receipt not confirmed', $r['IssuedOn']); }
    return $out;
}

/** For the menu badge: 0 before the migration has run. */
function inv_alert_count(PDO $pdo) {
    try { return count(inv_alerts($pdo)); }
    catch (Throwable $e) { return 0; }
}
