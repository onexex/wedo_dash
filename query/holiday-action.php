<?php
/* ==========================================================================
   holiday-action.php  —  Maintenance > Holidays: add / update / delete.

   POST token=<hld_csrf_token()> act=add|update|delete [id=<SID>]
        hldate=YYYY-MM-DD desc=<name> hldtype=1|2        (add / update)
     -> JSON {ok: true, id} | {ok: false, msg}

   Gated by the `hldy` access right (includes/holiday-lib.php). Holidays belong
   to the signed-in user's company (HCompID); rows of another company are
   never read or changed here.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function hla_out(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    hla_out(401, ['ok' => false, 'msg' => 'Your session has expired — please sign in again.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/holiday-lib.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    hla_out(500, ['ok' => false, 'msg' => 'Database unavailable.']);
}

$me   = (string) $_SESSION['id'];
$comp = (string) ($_SESSION['CompID'] ?? '');
if (!hld_can($pdo, $me)) {
    hla_out(403, ['ok' => false, 'msg' => 'You do not have access to Holidays.']);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !hash_equals(hld_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    hla_out(400, ['ok' => false, 'msg' => 'This page has expired — reload Holidays and try again.']);
}
if ($comp === '') {
    hla_out(400, ['ok' => false, 'msg' => 'Your account has no company, so holidays can’t be filed under it.']);
}

$act = (string) ($_POST['act'] ?? '');
$id  = (int) ($_POST['id'] ?? 0);

try {
    switch ($act) {
        case 'add':    $r = hld_save($pdo, $comp, $me, $_POST, 0); break;
        case 'update': $r = $id > 0 ? hld_save($pdo, $comp, $me, $_POST, $id) : ['ok' => false, 'msg' => 'Which holiday?']; break;
        case 'delete': $r = $id > 0 ? hld_delete($pdo, $comp, $me, $id) : ['ok' => false, 'msg' => 'Which holiday?']; break;
        default:       $r = ['ok' => false, 'msg' => 'Unknown action.'];
    }
} catch (Throwable $e) {
    $r = ['ok' => false, 'msg' => 'The change could not be saved. Please try again.'];
}
hla_out($r['ok'] ? 200 : (isset($r['msg']) && str_contains($r['msg'], 'no longer') ? 404 : 400), $r);
