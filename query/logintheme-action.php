<?php
/* ==========================================================================
   logintheme-action.php  —  writes for Maintenance > Login Theme.
   POST action = save | toggle | delete, returns JSON.

   Gated by the `logintheme` access right (==2) and a per-session token from
   lt_csrf_token(), both checked here — the page is not the guard. Every change
   is written to `dars` like updateaccess.php does.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');

function lt_out($code, array $body) { http_response_code($code); echo json_encode($body); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    lt_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lt_out(405, ['status' => 'error', 'msg' => 'POST only.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/login-theme.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    lt_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

if (!lt_can_manage($pdo)) {
    lt_out(403, ['status' => 'error', 'msg' => 'You do not have access to Login Theme.']);
}
if (!hash_equals(lt_csrf_token(), (string) ($_POST['token'] ?? ''))) {
    lt_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
}

$BANNER_DIR = __DIR__ . '/../assets/images/login-themes';
$action     = $_POST['action'] ?? '';
$actor      = (string) $_SESSION['id'];

function lt_log(PDO $pdo, $text) {
    try {
        $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity) VALUES (:id, :act)")
            ->execute([':id' => $_SESSION['id'], ':act' => $text]);
    } catch (Throwable $e) { /* audit is best-effort, never blocks the change */ }
}

function lt_find(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT * FROM login_themes WHERE id = :id");
    $st->execute([':id' => (int) $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function lt_unlink_banner($dir, $name) {
    if ($name) { @unlink($dir . '/' . basename($name)); }
}

/* ---------------------------------------------------------------- toggle */
if ($action === 'toggle') {
    $t = lt_find($pdo, $_POST['id'] ?? 0);
    if (!$t) { lt_out(404, ['status' => 'error', 'msg' => 'That entry no longer exists.']); }
    $on = empty($t['is_active']) ? 1 : 0;
    $pdo->prepare("UPDATE login_themes SET is_active = :on, updated_by = :by WHERE id = :id")
        ->execute([':on' => $on, ':by' => $actor, ':id' => $t['id']]);
    lt_log($pdo, 'Login Theme "' . $t['name'] . '" turned ' . ($on ? 'ON' : 'OFF'));
    lt_out(200, ['status' => 'ok', 'msg' => $on ? 'Turned on — it will show automatically on its dates.' : 'Turned off.']);
}

/* ---------------------------------------------------------------- delete */
if ($action === 'delete') {
    $t = lt_find($pdo, $_POST['id'] ?? 0);
    if (!$t) { lt_out(404, ['status' => 'error', 'msg' => 'That entry no longer exists.']); }
    lt_unlink_banner($BANNER_DIR, $t['banner_path']);
    $pdo->prepare("DELETE FROM login_themes WHERE id = :id")->execute([':id' => $t['id']]);
    lt_log($pdo, 'Login Theme "' . $t['name'] . '" deleted');
    lt_out(200, ['status' => 'ok', 'msg' => 'Login theme deleted.']);
}

/* ------------------------------------------------------------------ save */
if ($action !== 'save') {
    lt_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
}

$in = function ($k, $max = null) {
    $v = trim((string) ($_POST[$k] ?? ''));
    if ($max !== null && function_exists('mb_substr')) { $v = mb_substr($v, 0, $max); }
    return $v;
};
$flag = function ($k) { return in_array((string) ($_POST[$k] ?? '0'), ['1', 'true', 'on'], true) ? 1 : 0; };

$errors  = [];
$id      = (int) ($_POST['id'] ?? 0);
$name    = $in('name', 120);
$preset  = $in('preset');
$start   = $in('starts_on');
$end     = $in('ends_on');
$yearly  = $flag('repeats_yearly');
$annc    = $in('announcement', 300);

$validDate = function ($d) { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; };

if ($name === '')                 { $errors['name'] = 'Give the entry a name.'; }
if (!lt_preset($preset))          { $errors['preset'] = 'Choose a season.'; }
if (!$validDate($start))          { $errors['starts_on'] = 'Enter a start date.'; }
if (!$validDate($end))            { $errors['ends_on'] = 'Enter an end date.'; }
// A one-time window can't end before it starts; a yearly one may (Dec 31 -> Jan 6).
if (empty($errors['starts_on']) && empty($errors['ends_on']) && !$yearly && $end < $start) {
    $errors['ends_on'] = 'The end date must be on or after the start date.';
}
if ($errors) { lt_out(200, ['status' => 'invalid', 'error' => $errors]); }

if ($preset === 'plain' && $annc === '') {
    lt_out(200, ['status' => 'warn', 'msg' => 'An "Announcement only" entry needs announcement text — otherwise it shows nothing.']);
}

$existing = $id ? lt_find($pdo, $id) : null;
if ($id && !$existing) { lt_out(404, ['status' => 'error', 'msg' => 'That entry no longer exists.']); }

/* banner: remove and/or replace. Validated as a real image by content, not by
   the client's MIME, and stored under a random name so nothing user-typed
   reaches the filesystem path. */
$banner = $existing['banner_path'] ?? null;
if ($flag('remove_banner') && $banner) {
    lt_unlink_banner($BANNER_DIR, $banner);
    $banner = null;
}
if (!empty($_FILES['banner']) && ($_FILES['banner']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['banner'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        lt_out(200, ['status' => 'invalid', 'error' => ['banner' => 'The image did not upload. Try a smaller file.']]);
    }
    if ($f['size'] > 4 * 1024 * 1024) {
        lt_out(200, ['status' => 'invalid', 'error' => ['banner' => 'The banner image must be 4 MB or smaller.']]);
    }
    $info = @getimagesize($f['tmp_name']);
    $ext  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$info || !$ext) {
        lt_out(200, ['status' => 'invalid', 'error' => ['banner' => 'Use a JPG, PNG or WEBP image.']]);
    }
    if (!is_dir($BANNER_DIR)) { @mkdir($BANNER_DIR, 0755, true); }
    $newName = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $BANNER_DIR . '/' . $newName)) {
        lt_out(500, ['status' => 'error', 'msg' => 'Could not save the banner image on the server.']);
    }
    if ($banner) { lt_unlink_banner($BANNER_DIR, $banner); }
    $banner = $newName;
}

$vals = [
    ':name'   => $name,
    ':preset' => $preset,
    ':sl'     => $in('season_label', 60) ?: null,
    ':hl'     => $in('headline', 80) ?: null,
    ':ha'     => $in('headline_accent', 60) ?: null,
    ':msg'    => $in('message', 300) ?: null,
    ':ann'    => $annc ?: null,
    ':fx'     => $flag('show_effects'),
    ':banner' => $banner,
    ':s'      => $start,
    ':e'      => $end,
    ':y'      => $yearly,
    ':on'     => $flag('is_active'),
    ':by'     => $actor,
];

if ($existing) {
    $vals[':id'] = $existing['id'];
    $pdo->prepare("UPDATE login_themes SET name=:name, preset=:preset, season_label=:sl, headline=:hl,
            headline_accent=:ha, message=:msg, announcement=:ann, show_effects=:fx, banner_path=:banner,
            starts_on=:s, ends_on=:e, repeats_yearly=:y, is_active=:on, updated_by=:by WHERE id=:id")
        ->execute($vals);
    lt_log($pdo, 'Login Theme "' . $name . '" updated');
} else {
    $pdo->prepare("INSERT INTO login_themes (name, preset, season_label, headline, headline_accent, message,
            announcement, show_effects, banner_path, starts_on, ends_on, repeats_yearly, is_active, updated_by)
            VALUES (:name, :preset, :sl, :hl, :ha, :msg, :ann, :fx, :banner, :s, :e, :y, :on, :by)")
        ->execute($vals);
    lt_log($pdo, 'Login Theme "' . $name . '" created');
}

lt_out(200, ['status' => 'ok', 'msg' => 'Login theme saved.']);
