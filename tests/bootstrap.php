<?php
/* -----------------------------------------------------------------------------
 * Regression-test bootstrap: (re)creates a THROWAWAY database from the
 * structure-only snapshot in tests/fixtures/schema.sql. Tests seed their own
 * rows (see Support/AppTestCase.php); real data is never read or written.
 *
 * Configure with env vars (defaults suit local XAMPP):
 *   TEST_DB_HOST=localhost  TEST_DB_USER=root  TEST_DB_PASS=  TEST_DB_NAME=wedo_test
 * -------------------------------------------------------------------------- */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../vendor/autoload.php';

$env = fn(string $k, string $d) => (($v = getenv($k)) !== false && $v !== '') ? $v : $d;
$GLOBALS['WEDO_TEST_DB'] = $cfg = [
    'host' => $env('TEST_DB_HOST', 'localhost'),
    'user' => $env('TEST_DB_USER', 'root'),
    'pass' => $env('TEST_DB_PASS', ''),
    'name' => $env('TEST_DB_NAME', 'wedo_test'),
];

// This database is dropped on every run — refuse anything that isn't clearly a test DB.
if (!preg_match('/test/i', $cfg['name'])) {
    fwrite(STDERR, "Refusing to use database '{$cfg['name']}': TEST_DB_NAME must contain 'test'.\n");
    exit(1);
}

$server = new PDO("mysql:host={$cfg['host']};charset=utf8mb4", $cfg['user'], $cfg['pass'],
                  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec("DROP DATABASE IF EXISTS `{$cfg['name']}`");
$server->exec("CREATE DATABASE `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$server->exec("USE `{$cfg['name']}`");

$schema = file_get_contents(__DIR__ . '/fixtures/schema.sql');
foreach (preg_split('/;\s*\n/', $schema) as $stmt) {
    $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
    if ($stmt !== '') { $server->exec($stmt); }
}
