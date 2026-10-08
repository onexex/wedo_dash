<?php
/* -----------------------------------------------------------------------------
 * Child-process runner used by the regression tests (see AppTestCase::run()).
 *
 * Executes ONE app page/endpoint the way a web request would: fresh process,
 * superglobals set from a JSON spec, errors displayed in the response body (so
 * a PHP warning shows up exactly where the browser/AJAX caller would see it).
 * Each run is its own process because the app's scripts call exit/header and
 * rely on include-once state.
 *
 * Never reachable over HTTP: CLI only (tests/.htaccess also denies the folder,
 * and the deploy zip excludes tests/).
 * -------------------------------------------------------------------------- */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$spec = json_decode((string)file_get_contents($argv[1] ?? ''), true);
if (!is_array($spec)) { fwrite(STDERR, "run-script: bad spec\n"); exit(2); }

$_GET     = $spec['get'];
$_POST    = $spec['post'];
$_REQUEST = array_merge($_GET, $_POST);
$_COOKIE  = $spec['cookies'] ?? [];
$_FILES   = [];
$_SERVER['REQUEST_METHOD'] = $spec['post'] ? 'POST' : 'GET';
$_SERVER['HTTP_HOST']      = 'localhost';

// Session: the parent wrote sess_<id> into session_dir. Point the session
// module at it, but do NOT start it — scripts that forget session_start()
// must behave exactly as they would on the server ($_SESSION undefined).
ini_set('session.save_path', $spec['session_dir']);
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_strict_mode', '0');
ini_set('session.cache_limiter', '');
if ($spec['session_id'] !== null) { session_id($spec['session_id']); }

register_shutdown_function(function () {
    $code = http_response_code();
    fwrite(STDERR, "\n__HTTP_STATUS__=" . ($code ?: 200) . "\n");
});

chdir(dirname($spec['script']));
require $spec['script'];
