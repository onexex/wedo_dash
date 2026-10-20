<?php
/* =============================================================================
 * includes/db-backup-lib.php — Maintenance > Database Backup.
 *
 * People holding the `dbbackup` access right (accessrights.dbbackup = 2,
 * sql/2026-10-20-add-db-backup-access-right.sql; nobody gets it by default)
 * can download the whole app database — every table's structure and data,
 * plus views and triggers — as one gzipped .sql file.
 *
 * Pure PHP/PDO (no mysqldump / exec, which GoDaddy shared hosting doesn't
 * allow). Rows are read unbuffered and gzipped on the fly straight to the
 * browser, so big tables never sit in memory and nothing is written to disk.
 * The file restores with phpMyAdmin > Import or `mysql db < file.sql`.
 *
 * Used by: dbbackup.php (the page), query/dbbackup-download.php (the download)
 * ========================================================================== */

const DBB_INSERT_ROWS  = 500;       // rows per INSERT statement ...
const DBB_INSERT_BYTES = 262144;    // ... or ~256 KB, whichever comes first (well under a 1 MB max_allowed_packet)
const DBB_LOG_PREFIX   = 'Database backup';

/** Whether $me holds the `dbbackup` right. False until the migration has run. */
function dbb_can_backup(PDO $pdo, string $me): bool
{
    if ($me === '' || $me === '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT dbbackup FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $me]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;   // column not there yet
    }
}

/** Per-session token for the download form. */
function dbb_csrf_token(): string
{
    if (empty($_SESSION['dbb_csrf'])) { $_SESSION['dbb_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['dbb_csrf'];
}

/** Download name, e.g. wedodb2020-backup-2026-10-20_143005.sql.gz (Manila time). */
function dbb_filename(string $dbName, ?DateTimeInterface $at = null): string
{
    $at = $at ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $dbName) ?: 'wedo';
    return $safe . '-backup-' . $at->format('Y-m-d_His') . '.sql.gz';
}

/** Tables and views in the current database: [['name' => .., 'view' => bool], ...] */
function dbb_objects(PDO $pdo): array
{
    $out = [];
    foreach (dbb_all($pdo, "SHOW FULL TABLES", PDO::FETCH_NUM) as $r) {
        $out[] = ['name' => (string) $r[0], 'view' => strtoupper((string) $r[1]) === 'VIEW'];
    }
    return $out;
}

/** Table count + approximate size on disk, for the page. */
function dbb_summary(PDO $pdo): array
{
    $r = $pdo->query("SELECT COUNT(*) AS tables, COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) AS bytes,
                             COALESCE(SUM(TABLE_ROWS), 0) AS approx_rows
                        FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetch(PDO::FETCH_ASSOC);
    return ['tables' => (int) $r['tables'], 'bytes' => (int) $r['bytes'], 'rows' => (int) $r['approx_rows']];
}

/** Recent backups from the activity log, newest first. */
function dbb_recent(PDO $pdo, int $limit = 10): array
{
    $st = $pdo->prepare("SELECT d.EMPID, d.EmpActivity, d.DarDateTime,
                                TRIM(CONCAT(COALESCE(e.EmpFN, ''), ' ', COALESCE(e.EmpLN, ''))) AS name
                           FROM dars d LEFT JOIN employees e ON e.EmpID = d.EMPID
                          WHERE d.EmpActivity LIKE :p
                          ORDER BY d.DarDateTime DESC, d.DARSID DESC
                          LIMIT " . max(1, $limit));
    $st->execute([':p' => DBB_LOG_PREFIX . '%']);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Write one line to the activity log (dars). */
function dbb_log(PDO $pdo, string $me, string $activity): void
{
    $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :act, :at)")
        ->execute([':id' => $me, ':act' => mb_substr($activity, 0, 250),
                   ':at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s')]);
}

/* ------------------------------------------------------------------ the dump */

function dbb_ident(string $name): string { return '`' . str_replace('`', '``', $name) . '`'; }

/** All rows of a small metadata query (read to the end: the connection may be unbuffered). */
function dbb_all(PDO $pdo, string $sql, int $mode = PDO::FETCH_ASSOC): array
{
    $st = $pdo->query($sql);
    $rows = $st->fetchAll($mode);
    $st->closeCursor();
    return $rows;
}

/** A string literal, escaped like mysql_real_escape_string (the dump runs under SET NAMES utf8mb4). */
function dbb_str(string $v): string
{
    return "'" . strtr($v, ["\\" => "\\\\", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "'" => "\\'", '"' => '\\"', "\x1a" => "\\Z"]) . "'";
}

/** Drop the DEFINER=`user`@`host` clause: the restoring server's account is usually different. */
function dbb_strip_definer(string $sql): string
{
    return preg_replace('/\sDEFINER\s*=\s*(`[^`]*`|\'[^\']*\'|\S+)@(`[^`]*`|\'[^\']*\'|\S+)/i', '', $sql, 1);
}

/**
 * Write a full SQL dump of the current database through $write(string $chunk).
 * $pdo must use utf8mb4 with buffered queries OFF (dbb_connect() does both).
 * Returns ['tables' => n, 'rows' => n]. Throws on a database error — whatever
 * was written so far then lacks the closing "-- Dump completed" line.
 */
function dbb_dump(PDO $pdo, callable $write, string $dbName = ''): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $ver = (string) dbb_all($pdo, "SELECT VERSION()", PDO::FETCH_NUM)[0][0];
    // read TIMESTAMP columns in UTC and restore them in UTC, like mysqldump
    $pdo->exec("SET time_zone = '+00:00'");
    // one consistent point in time for every InnoDB table (mysqldump --single-transaction)
    $pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
    $pdo->exec("START TRANSACTION WITH CONSISTENT SNAPSHOT");

    $write("-- WeDo database backup\n"
         . "-- Database: " . str_replace(["\r", "\n"], ' ', $dbName) . "\n"
         . "-- Server version: " . str_replace(["\r", "\n"], ' ', $ver) . "\n"
         . "-- Created: " . $now->format('Y-m-d H:i:s') . " (Asia/Manila)\n"
         . "-- Restore: phpMyAdmin > (select an EMPTY database) > Import, or  mysql <database> < file.sql\n"
         . "-- ------------------------------------------------------\n\n"
         . "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n"
         . "/*!40101 SET NAMES utf8mb4 */;\n"
         . "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n"
         . "/*!40103 SET TIME_ZONE='+00:00' */;\n"
         . "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n"
         . "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n"
         . "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n"
         . "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");

    $objects = dbb_objects($pdo);
    $tables = 0; $rows = 0;

    foreach ($objects as $o) {
        if ($o['view']) { continue; }
        $rows += dbb_dump_table($pdo, $o['name'], $write);
        $tables++;
    }

    // views after every table they may read from
    foreach ($objects as $o) {
        if (!$o['view']) { continue; }
        $create = dbb_all($pdo, "SHOW CREATE VIEW " . dbb_ident($o['name']), PDO::FETCH_NUM)[0][1];
        $write("--\n-- View " . dbb_ident($o['name']) . "\n--\n\n"
             . "DROP TABLE IF EXISTS " . dbb_ident($o['name']) . ";\n"
             . "DROP VIEW IF EXISTS " . dbb_ident($o['name']) . ";\n"
             . dbb_strip_definer($create) . ";\n\n");
    }

    // triggers last, so restoring the data above doesn't fire them
    $triggers = dbb_all($pdo, "SHOW TRIGGERS");
    if ($triggers) {
        $write("--\n-- Triggers\n--\n\nDELIMITER ;;\n");
        foreach ($triggers as $t) {
            $create = dbb_all($pdo, "SHOW CREATE TRIGGER " . dbb_ident($t['Trigger']))[0];
            $write("DROP TRIGGER IF EXISTS " . dbb_ident($t['Trigger']) . ";;\n"
                 . dbb_strip_definer((string) $create['SQL Original Statement']) . ";;\n");
        }
        $write("DELIMITER ;\n\n");
    }
    $pdo->exec("COMMIT");

    $write("/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n"
         . "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n"
         . "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n"
         . "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n"
         . "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n"
         . "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n\n"
         . "-- Dump completed: $tables tables, $rows rows, "
         . (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s') . "\n");

    return ['tables' => $tables, 'rows' => $rows];
}

/** One table: DROP + CREATE, then its rows as multi-row INSERTs. Returns the row count. */
function dbb_dump_table(PDO $pdo, string $table, callable $write): int
{
    $q = dbb_ident($table);
    $create = dbb_all($pdo, "SHOW CREATE TABLE $q", PDO::FETCH_NUM)[0][1];
    $write("--\n-- Table $q\n--\n\nDROP TABLE IF EXISTS $q;\n$create;\n\n");

    // per column: how to write its values; generated columns are left out (the server computes them)
    $cols = [];
    foreach (dbb_all($pdo, "SHOW COLUMNS FROM $q") as $c) {
        if (stripos((string) $c['Extra'], 'GENERATED') !== false) { continue; }
        $type = strtolower((string) $c['Type']);
        $bit  = preg_match('/^bit\b/', $type) === 1;
        $flt  = preg_match('/^(float|double|real)\b/', $type) === 1;
        $cols[] = [
            'name'   => $c['Field'],
            // BIT is read as a number (the driver's own BIT format differs between versions);
            // FLOAT/DOUBLE as the server's text (PHP's float -> string conversion drops digits)
            'select' => $bit ? 'CAST(' . dbb_ident($c['Field']) . ' AS UNSIGNED)'
                      : ($flt ? 'CONCAT(' . dbb_ident($c['Field']) . ')' : dbb_ident($c['Field'])),
            'kind'   => $bit || preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|year)\b/', $type) ? 'num'
                      : (preg_match('/^(binary|varbinary|tinyblob|blob|mediumblob|longblob)\b/', $type) ? 'bin' : 'str'),
        ];
    }
    if (!$cols) { return 0; }

    $head  = "INSERT INTO $q (" . implode(', ', array_map(fn($c) => dbb_ident($c['name']), $cols)) . ") VALUES\n";
    $list  = implode(', ', array_column($cols, 'select'));
    $st    = $pdo->query("SELECT $list FROM $q");   // unbuffered: rows stream from the server
    $batch = []; $size = 0; $count = 0;
    $flush = function () use (&$batch, &$size, $head, $write) {
        if ($batch) { $write($head . implode(",\n", $batch) . ";\n"); }
        $batch = []; $size = 0;
    };

    $write("/*!40000 ALTER TABLE $q DISABLE KEYS */;\n");
    while (($r = $st->fetch(PDO::FETCH_NUM)) !== false) {
        $vals = [];
        foreach ($cols as $i => $c) {
            $v = $r[$i];
            if ($v === null)                                { $vals[] = 'NULL'; }
            elseif ($c['kind'] === 'num' && is_numeric($v)) { $vals[] = (string) $v; }
            elseif ($c['kind'] === 'bin')                   { $vals[] = $v === '' ? "''" : '0x' . bin2hex((string) $v); }
            else                                            { $vals[] = dbb_str((string) $v); }
        }
        $line = '(' . implode(',', $vals) . ')';
        $batch[] = $line;
        $size += strlen($line);
        $count++;
        if (count($batch) >= DBB_INSERT_ROWS || $size >= DBB_INSERT_BYTES) { $flush(); }
    }
    $st->closeCursor();
    $flush();
    $write("/*!40000 ALTER TABLE $q ENABLE KEYS */;\n\n");
    return $count;
}

/** A connection suited to dbb_dump(): utf8mb4, unbuffered, values as strings. */
function dbb_connect(string $host, string $user, string $pass, string $name): PDO
{
    return new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        PDO::ATTR_STRINGIFY_FETCHES        => true,
        PDO::ATTR_EMULATE_PREPARES         => true,
    ]);
}
