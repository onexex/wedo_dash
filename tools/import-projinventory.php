<?php
/* ==========================================================================
   import-projinventory.php  —  copies the IT equipment register of the old
   projInventory app (database dbinventory) into Inventory. The rules are in
   includes/inventory-import-lib.php; the guide is tools/import-projinventory.md.

     php tools/import-projinventory.php            dry run: shows what would be added, changes nothing
     php tools/import-projinventory.php --apply    adds the items

   Options:
     --location="IT Room"    where the items are kept (created when missing)
     --actor=WeDoinc-0010    EmpID recorded as the importer when the old record has no user
     --src-host= --src-user= --src-pass= --src-db=
                             the dbinventory connection (default localhost, root, no
                             password, dbinventory). Env SRC_DB_HOST / SRC_DB_USER /
                             SRC_DB_PASS / SRC_DB_NAME work too.
   The WeDo database is the one in w_conn.php (config.local.php or DB_* env vars).
   Safe to run again: items whose serial number is already in Inventory are skipped.
   Command line only.
   ========================================================================== */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Manila');

require dirname(__DIR__) . '/w_conn.php';
require_once dirname(__DIR__) . '/includes/inventory-import-lib.php';

$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--apply') { $opt['apply'] = true; continue; }
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $a, $m)) { $opt[$m[1]] = $m[2]; continue; }
    fwrite(STDERR, "Unknown option: $a\n");
    exit(2);
}
$pick = function ($key, $env, $default) use ($opt) {
    if (isset($opt[$key])) { return $opt[$key]; }
    $v = getenv($env);
    return $v !== false ? $v : $default;
};

$pdoOpts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
try {
    $dst = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password, $pdoOpts);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the WeDo database.\n");
    exit(1);
}
$srcDb = $pick('src-db', 'SRC_DB_NAME', 'dbinventory');
try {
    $src = new PDO('mysql:host=' . $pick('src-host', 'SRC_DB_HOST', 'localhost') . ";dbname=$srcDb;charset=utf8mb4",
        $pick('src-user', 'SRC_DB_USER', 'root'), $pick('src-pass', 'SRC_DB_PASS', ''), $pdoOpts);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the projInventory database ($srcDb).\n");
    exit(1);
}

$actor = (string) ($opt['actor'] ?? '');
$_SESSION = ['id' => $actor];   // the audit line (dars) is written as this person
$r = invimp_run($src, $dst, ['apply' => !empty($opt['apply']), 'location' => $opt['location'] ?? 'IT Room', 'actor' => $actor]);

if (!$r['ok']) { fwrite(STDERR, $r['msg'] . "\n"); exit(1); }
echo ($r['applied'] ? 'Imported' : 'Dry run — nothing saved. Would import') . " {$r['items']} items from $srcDb into $db\n";
if ($r['tags']) { echo '  tags:       ' . invimp_tag_summary($r['tags']) . "\n"; }
echo "  location:   {$r['location']}\n";
echo "  issued:     {$r['issued']} (currently with an employee)\n";
echo "  disposed:   {$r['disposed']}\n";
echo "  history:    {$r['movements']} movements\n";
echo '  skipped:    ' . count($r['skipped']) . " (serial number already in Inventory)\n";
foreach (array_slice($r['skipped'], 0, 10) as $s) { echo "    - $s\n"; }
if (count($r['skipped']) > 10) { echo '    … and ' . (count($r['skipped']) - 10) . " more\n"; }
if ($r['warnings']) {
    echo '  warnings:   ' . count($r['warnings']) . "\n";
    foreach ($r['warnings'] as $w) { echo "    - $w\n"; }
}

// the old TSDR forms, once the TSDR tables exist (they match items by serial number, so after the items)
if (!inv_tsdr_ready($dst)) {
    echo "TSDRs: skipped — run sql/2026-11-11-add-inventory-tsdr.sql to bring them over too.\n";
} else {
    $t = invimp_tsdr_run($src, $dst, ['apply' => !empty($opt['apply'])]);
    echo ($t['applied'] ? 'Imported' : 'Dry run — nothing saved. Would import') . " {$t['tsdrs']} TSDRs\n";
    echo "  items:      {$t['items']} lines" . ($t['unmatched'] ? ", {$t['unmatched']} not matched to an Inventory item (kept as text)" : '') . "\n";
    echo "  skipped:    {$t['skipped']} (number already in Inventory)\n";
    foreach ($t['warnings'] as $w) { echo "    - $w\n"; }
}
if (empty($opt['apply'])) { echo "Run again with --apply to save.\n"; }
exit(0);
