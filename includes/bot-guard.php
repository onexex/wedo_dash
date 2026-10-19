<?php
/* ==========================================================================
   bot-guard.php  —  shared protection against automated (bot) requests:
     - wd_client_ip()       the visitor's real address (Cloudflare-aware)
     - wd_turnstile*()      Cloudflare Turnstile ("I am human") — keys from
                            config.local.php turnstile_site / turnstile_secret
                            or env TURNSTILE_SITE / TURNSTILE_SECRET
     - wd_login_*()         sign-in throttling (table login_attempts,
                            sql/2026-10-14-add-login-attempts.sql)
   Used by the sign-in (query/query-login.php, login.php) and the public
   Apply page (includes/applicants-lib.php).
   ========================================================================== */

/* Cloudflare's edge ranges (https://www.cloudflare.com/ips/): only when the
   request really comes from one of them is CF-Connecting-IP trusted as the
   visitor's address — anyone else could fake that header. */
const WD_CF_RANGES = ['173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
    '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15',
    '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32', '2606:4700::/32',
    '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'];

/* Sign-in throttling: wrong passwords per username, and failed sign-ins per address. */
const WD_LOGIN_USER_MAX = 15;     // wrong passwords for one username …
const WD_LOGIN_IP_MAX   = 50;     // … or failed sign-ins from one address (generous: a whole office shares one address) …
const WD_LOGIN_WINDOW   = 900;    // … within 15 minutes = locked for the rest of those 15 minutes

function wd_ip_in($ip, $cidr): bool
{
    [$net, $bits] = explode('/', $cidr);
    $a = @inet_pton((string) $ip);
    $b = @inet_pton($net);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) { return false; }
    $bits = (int) $bits;
    $bytes = intdiv($bits, 8);
    if (strncmp($a, $b, $bytes) !== 0) { return false; }
    if ($bits % 8 === 0) { return true; }
    $mask = chr((0xFF << (8 - $bits % 8)) & 0xFF);
    return (($a[$bytes] & $mask) === ($b[$bytes] & $mask));
}

/** The visitor's address (behind Cloudflare: the real one, never a spoofed header). */
function wd_client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        foreach (WD_CF_RANGES as $r) {
            if (wd_ip_in($remote, $r)) { return substr($cf, 0, 45); }
        }
    }
    return substr($remote, 0, 45);
}

/**
 * config.local.php's settings, read straight from the file (once per request).
 * Not via w_conn.php: the deploy never uploads w_conn.php, so a server may run
 * an older copy that doesn't load config.local.php (production did) — the same
 * reason push-lib.php reads the file itself.
 */
function wd_local_config(): array
{
    static $cfg = null;
    if ($cfg !== null) { return $cfg; }
    $cfg = [];
    $file = dirname(__DIR__) . '/config.local.php';
    if (is_file($file)) {
        $read = require $file;
        if (is_array($read)) { $cfg = $read; }
    }
    if (isset($GLOBALS['__cfg']) && is_array($GLOBALS['__cfg'])) { $cfg += $GLOBALS['__cfg']; }
    return $cfg;
}

/** Turnstile keys, or null when not configured (then pages rely on their other guards). */
function wd_turnstile(): ?array
{
    $cfg  = wd_local_config();
    $site = getenv('TURNSTILE_SITE') ?: ($cfg['turnstile_site'] ?? '');
    $sec  = getenv('TURNSTILE_SECRET') ?: ($cfg['turnstile_secret'] ?? '');
    return ($site !== '' && $sec !== '') ? ['site' => trim((string) $site), 'secret' => trim((string) $sec)] : null;
}

/**
 * Ask Cloudflare whether a Turnstile answer is genuine.
 * Returns 'ok', 'bad' (missing / rejected — refuse the request) or
 * 'unreachable' (Cloudflare didn't answer: callers let it through, so an
 * outage there never locks everyone out; the rate limits still apply).
 */
function wd_turnstile_check($response, string $ip): string
{
    $ts = wd_turnstile();
    if (!$ts) { return 'ok'; }
    if (!is_string($response) || $response === '' || strlen($response) > 2048) { return 'bad'; }
    $body = http_build_query(['secret' => $ts['secret'], 'response' => $response, 'remoteip' => $ip]);
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 8, 'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body]]);
    $res = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    $j = is_string($res) ? json_decode($res, true) : null;
    if (!is_array($j)) {
        error_log('[wedo bot-guard] Turnstile verification unreachable');
        return 'unreachable';
    }
    return !empty($j['success']) ? 'ok' : 'bad';
}

/* ---------------------------------------------------------------- sign-in throttling */

function wd_login_ready(PDO $pdo): bool
{
    try { $pdo->query("SELECT 1 FROM login_attempts LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}

/**
 * Seconds until this username / address may try again (0 = may sign in now).
 * Wrong passwords count since the account's last successful sign-in.
 */
function wd_login_wait(PDO $pdo, string $username, string $ip): int
{
    return wd_login_lock($pdo, $username, $ip)['wait'];
}

/**
 * The lock in force: ['wait' => seconds, 'scope' => 'user' (this account) |
 * 'ip' (this address, any account) | ''] — the longer one when both apply.
 */
function wd_login_lock(PDO $pdo, string $username, string $ip): array
{
    if (!wd_login_ready($pdo)) { return ['wait' => 0, 'scope' => '']; }
    $since = date('Y-m-d H:i:s', time() - WD_LOGIN_WINDOW);
    $wait = wd_login_user_wait($pdo, $username);
    $scope = $wait > 0 ? 'user' : '';

    $st = $pdo->prepare("SELECT CreatedAt FROM login_attempts WHERE IP = :ip AND Success = 0 AND CreatedAt > :s
                         ORDER BY CreatedAt DESC LIMIT " . WD_LOGIN_IP_MAX);
    $st->execute([':ip' => $ip, ':s' => $since]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) >= WD_LOGIN_IP_MAX) {
        $ipWait = strtotime(end($rows)) + WD_LOGIN_WINDOW - time();
        if ($ipWait > $wait) { $wait = $ipWait; $scope = 'ip'; }
    }
    return $wait > 0 ? ['wait' => $wait, 'scope' => $scope] : ['wait' => 0, 'scope' => ''];
}

/** Seconds this username stays locked by its own wrong passwords (0 = not locked). */
function wd_login_user_wait(PDO $pdo, string $username): int
{
    if (!wd_login_ready($pdo)) { return 0; }
    $user = strtolower(trim($username));
    $st = $pdo->prepare("SELECT CreatedAt FROM login_attempts WHERE Username = :u AND Success = 0 AND CreatedAt > :s
                         AND CreatedAt > COALESCE((SELECT MAX(CreatedAt) FROM login_attempts WHERE Username = :u2 AND Success = 1), '1970-01-01')
                         ORDER BY CreatedAt DESC LIMIT " . WD_LOGIN_USER_MAX);
    $st->execute([':u' => $user, ':u2' => $user, ':s' => date('Y-m-d H:i:s', time() - WD_LOGIN_WINDOW)]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) < WD_LOGIN_USER_MAX) { return 0; }
    // locked until the oldest of the last N failures is 15 minutes old
    return max(0, strtotime(end($rows)) + WD_LOGIN_WINDOW - time());
}

/**
 * Unknown username: do the same bcrypt work as a real password check, so the
 * answer takes as long as "wrong password" and timing can't reveal accounts.
 * (A real bcrypt hash of a random throw-away value.)
 */
function wd_login_dummy_verify(string $password): void
{
    password_verify($password, '$2y$10$epVM34qUXoQ2SBWIA0FYIOVZBQjOk4G.d.yprllXEtBMNQgQ168kG');
}

/**
 * HR "Unlock sign-in": wrong passwords only count since the last success, so a
 * success row (IP 'unlock') ends this username's lock at once. Locks on an
 * address (a whole office) are not touched.
 */
function wd_login_unlock(PDO $pdo, string $username): void
{
    if (trim($username) === '') { return; }
    wd_login_record($pdo, $username, 'unlock', true);
}

/** Remember one sign-in attempt (the typed username, never the password). */
function wd_login_record(PDO $pdo, string $username, string $ip, bool $success): void
{
    if (!wd_login_ready($pdo)) { return; }
    try {
        $pdo->prepare("INSERT INTO login_attempts (Username, IP, Success, CreatedAt) VALUES (:u, :ip, :s, :t)")
            ->execute([':u' => mb_substr(strtolower(trim($username)), 0, 150), ':ip' => $ip, ':s' => $success ? 1 : 0,
                       ':t' => date('Y-m-d H:i:s')]);
        if (random_int(1, 100) === 1) {   // now and then: keep only the last day
            $pdo->exec("DELETE FROM login_attempts WHERE CreatedAt < (NOW() - INTERVAL 1 DAY)");
        }
    } catch (Throwable $e) {
        error_log('[wedo bot-guard] record: ' . $e->getMessage());
    }
}
