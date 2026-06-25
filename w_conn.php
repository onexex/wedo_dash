<?php
/* =============================================================================
 * w_conn.php  —  SINGLE source of database connectivity for the whole app.
 *
 * includes/w_conn.php and query/w_conn.php are thin forwarders to THIS file, so
 * credentials live in exactly one place.
 *
 * Credentials are resolved in this order (highest priority first):
 *   1. Environment variables  DB_HOST / DB_USER / DB_PASS / DB_NAME
 *   2. An untracked local override file  config.local.php  (returns an array,
 *      e.g.  <?php return ['user'=>'root','pass'=>''];  )
 *   3. The built-in fallback below (current local-dev defaults)
 *
 * This keeps REAL production credentials out of git: on the production server,
 * set DB_* env vars or drop a config.local.php (both untracked). The committed
 * fallback stays on local-dev defaults so nothing changes for local work.
 * ========================================================================== */

// 3. fallback (local dev defaults — unchanged from before)
$__cfg = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'wedodb2020',
];

// 2. untracked local override file wins over the fallback
$__local = __DIR__ . '/config.local.php';
if (is_file($__local)) {
    $__over = require $__local;
    if (is_array($__over)) { $__cfg = array_merge($__cfg, $__over); }
}

// 1. environment variables win over everything
foreach (['host' => 'DB_HOST', 'user' => 'DB_USER', 'pass' => 'DB_PASS', 'name' => 'DB_NAME'] as $__k => $__env) {
    $__v = getenv($__env);
    if ($__v !== false && $__v !== '') { $__cfg[$__k] = $__v; }
}

// Variables the rest of the app expects (PDO is built from these four; $con is
// the shared mysqli handle used by the few mysqli_* call sites).
$servername = $__cfg['host'];
$username   = $__cfg['user'];
$password   = $__cfg['pass'];
$db         = $__cfg['name'];

$con = mysqli_connect($servername, $username, $password, $db);

// Check connection
if (mysqli_connect_errno()) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
}

/* Session lifetime — preserved from the production config (~1 hour). These only
   take effect when w_conn is included before any output / session_start(), so the
   guard applies them exactly then and stays silent on the many later includes
   (avoids "cannot be changed after headers already sent" warnings). */
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 3600);
    session_set_cookie_params(3600);
}
