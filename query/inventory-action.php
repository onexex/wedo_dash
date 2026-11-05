<?php
/* ==========================================================================
   inventory-action.php  —  reads + writes for inventory.php and myitems.php.
   POST action=..., returns JSON. Every action checks its own access (the
   pages are not the guard) plus the per-session token from inv_csrf_token().

     my_items, my_ack, my_report              any signed-in employee, own items only
     everything else                          the `inventory` right
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function inv_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    inv_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inv_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}
// over post_max_size PHP drops the whole body (token included): say so instead of "page expired"
if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    inv_out(413, ['status' => 'error', 'msg' => 'That file is too large for the server.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/inventory-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    inv_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!hash_equals(inv_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    inv_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}
if (!inv_ready($pdo)) {
    inv_out(503, ['status' => 'error', 'msg' => 'This feature is not set up on this server yet (database update pending).']);
}

$action = (string) ($_POST['action'] ?? '');
$actor  = (string) $_SESSION['id'];
$p = function ($k) { return (string) ($_POST[$k] ?? ''); };
$result = function (array $r, array $extra = []) {
    if (!$r['ok']) { inv_out(422, ['status' => 'error', 'msg' => $r['msg']]); }
    inv_out(200, ['status' => 'ok'] + $extra + $r);
};

try {
    /* -------------------------------------------------- the employee's own items */
    switch ($action) {
    case 'my_items':
        inv_out(200, ['status' => 'ok', 'items' => inv_my_items($pdo, $actor), 'pending' => inv_unacked_count($pdo, $actor)]);

    case 'my_ack':
        $result(inv_ack($pdo, (int) $p('id'), $actor));

    case 'my_report':
        $result(inv_report_problem($pdo, (int) $p('id'), $p('problem'), $actor));
    }

    /* -------------------------------------------------- everything below manages the register */
    if (!inv_can_manage($pdo)) {
        inv_out(403, ['status' => 'error', 'msg' => 'You do not have access to Inventory.']);
    }

    switch ($action) {
    /* ---------- reading */
    case 'lookups':
        inv_out(200, ['status' => 'ok'] + inv_lookups($pdo));

    case 'emp_search':
        inv_out(200, ['status' => 'ok', 'employees' => inv_find_employees($pdo, $p('q'))]);

    case 'items':
        $f = ['category' => $p('category'), 'location' => $p('location'), 'status' => $p('filterStatus'), 'q' => $p('q'), 'kind' => $p('kind')];
        inv_out(200, ['status' => 'ok', 'items' => inv_list($pdo, $f), 'stats' => inv_stats($pdo), 'limit' => INV_TABLE_LIMIT]);

    case 'stats':
        inv_out(200, ['status' => 'ok', 'stats' => inv_stats($pdo)]);

    case 'item':
        $d = inv_item_detail($pdo, (int) $p('id'));
        if (!$d) { inv_out(404, ['status' => 'error', 'msg' => 'That item no longer exists.']); }
        inv_out(200, ['status' => 'ok'] + $d);

    case 'tag_lookup':
        $it = inv_by_tag($pdo, $p('tag'), (int) $p('location') ?: null);
        if (!$it) { inv_out(404, ['status' => 'error', 'msg' => 'No item has that tag.']); }
        inv_out(200, ['status' => 'ok', 'id' => $it['id']]);

    case 'people':
        inv_out(200, ['status' => 'ok', 'people' => inv_people($pdo)]);

    case 'person':
        $a = inv_accountability($pdo, $p('emp'));
        if (!$a) { inv_out(404, ['status' => 'error', 'msg' => 'That employee no longer exists.']); }
        inv_out(200, ['status' => 'ok'] + $a);

    case 'repairs':
        inv_out(200, ['status' => 'ok', 'repairs' => inv_repairs($pdo)]);

    case 'alerts':
        inv_out(200, ['status' => 'ok', 'alerts' => inv_alerts($pdo)]);

    case 'stock_ledger':
        inv_out(200, ['status' => 'ok', 'ledger' => inv_stock_ledger($pdo, (int) $p('id'))]);

    case 'usage_report':
        inv_out(200, ['status' => 'ok'] + inv_usage_report($pdo, $p('from'), $p('to')));

    case 'counts':
        inv_out(200, ['status' => 'ok', 'counts' => inv_counts($pdo)]);

    case 'count':
        $c = inv_count($pdo, (int) $p('id'));
        if (!$c) { inv_out(404, ['status' => 'error', 'msg' => 'That count no longer exists.']); }
        inv_out(200, ['status' => 'ok', 'count' => $c]);

    /* ---------- the register */
    case 'item_save':
        $result(inv_save($pdo, $_POST, $actor));

    case 'item_delete':
        $result(inv_delete($pdo, (int) $p('id'), $actor));

    case 'issue':
        $r = inv_issue($pdo, (int) $p('id'), $p('emp'), $p('qty'), $p('date'), $p('notes'), $actor);
        if ($r['ok']) {
            require_once __DIR__ . '/../includes/push-lib.php';
            if (push_enabled()) {
                try { push_send($pdo, [$r['empId']], 'Item issued to you', $r['tag'] . ' · ' . $r['itemName'] . ' — confirm receipt in My Items.', ['type' => 'inventory']); }
                catch (Throwable $e) { error_log('[wedo inventory] push: ' . $e->getMessage()); }   // the issue itself stands
            }
        }
        $result($r);

    case 'return':
        $result(inv_return($pdo, (int) $p('id'), $p('qty'), (int) $p('location'), $p('condition'), $p('notes'), $actor));

    case 'transfer':
        $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : [$p('id')];
        $result(inv_transfer($pdo, $ids, (int) $p('location'), $p('qty'), $p('spot'), $p('notes'), $actor));

    case 'mark_lost':
        $result(inv_mark_lost($pdo, (int) $p('id'), $p('qty'), $p('notes'), $actor));

    case 'mark_found':
        $result(inv_mark_found($pdo, (int) $p('id'), (int) $p('location'), $p('notes'), $actor));

    case 'dispose':
        $result(inv_dispose($pdo, (int) $p('id'), $p('qty'), $p('method'), $p('date'), $p('notes'), $actor));

    case 'lot_merge':
        $result(inv_merge($pdo, (int) $p('id'), $actor));

    case 'file_add':
        if (empty($_FILES['file'])) { inv_out(422, ['status' => 'error', 'msg' => 'Choose a file.']); }
        $result(inv_file_add($pdo, (int) $p('id'), $p('kind'), $_FILES['file'], $actor));

    case 'file_delete':
        $result(inv_file_delete($pdo, (int) $p('id')));

    /* ---------- repairs */
    case 'repair_open':
        $result(inv_repair_open($pdo, $_POST, $actor));

    case 'repair_close':
        $result(inv_repair_close($pdo, (int) $p('repairId'), $_POST, $actor));

    case 'repair_cancel':
        $result(inv_repair_cancel($pdo, (int) $p('repairId'), $p('notes'), $actor));

    /* ---------- supplies */
    case 'stock_in':
        $result(inv_stock_in($pdo, (int) $p('id'), $p('qty'), $p('unitCost'), $p('supplier'), $p('reference'), $p('notes'), $actor));

    case 'stock_out':
        $result(inv_stock_out($pdo, (int) $p('id'), $p('qty'), $p('emp'), (int) $p('department'), $p('notes'), $p('reference'), $actor));

    case 'stock_adjust':
        $result(inv_stock_adjust($pdo, (int) $p('id'), $p('qty'), $p('notes'), $actor));

    /* ---------- counts */
    case 'count_start':
        $result(inv_count_start($pdo, (int) $p('location'), $p('title'), $p('notes'), $actor));

    case 'count_check':
        $result(inv_count_check($pdo, (int) $p('id'), $_POST, $actor));

    case 'count_uncheck':
        $result(inv_count_uncheck($pdo, (int) $p('lineId'), $actor));

    case 'count_extra':
        $result(inv_count_extra($pdo, (int) $p('id'), $p('name'), $p('qty'), $p('notes'), $actor));

    case 'count_close':
        $result(inv_count_close($pdo, (int) $p('id'), $actor));

    case 'count_apply':
        $result(inv_count_apply($pdo, (int) $p('id'), $p('what'), $actor));

    /* ---------- setup */
    case 'cat_save':
        $result(inv_category_save($pdo, $_POST));

    case 'loc_save':
        $result(inv_location_save($pdo, $_POST));
    }
} catch (Throwable $e) {
    error_log('[wedo inventory] ' . $action . ': ' . $e->getMessage());
    inv_out(500, ['status' => 'error', 'msg' => 'Something went wrong — please try again.']);
}

inv_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
