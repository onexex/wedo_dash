<?php
/* ==========================================================================
   api-auth-lib.php  —  sign-in API for other apps (api/auth-login.php).

   Another app (e.g. a Laravel site) lets its users sign in with their WeDo
   username and password. ITS SERVER calls api/auth-login.php with its own
   secret API key; WeDo answers yes/no plus a short-lived signed token and the
   person's basic profile. Login only — no other WeDo data is exposed.

   The same rules as the website's sign-in (query/query-login.php):
     - wrong passwords lock the username after 15 tries / an address after 50
       (includes/bot-guard.php, table login_attempts) — tries through the API
       and through the website count together
     - an unknown username and a wrong password get the same answer and take
       the same time
     - resigned / terminated / AWOL accounts are refused unless they have the
       `aftersep` access right (includes/account-gate.php)
     - a default / temporary password must be changed in WeDo first
   Plus, for the API:
     - each app has its own key: config.local.php 'api_clients' (or env
       WEDO_API_CLIENTS="name:key,name2:key2"); never in git
     - HTTPS only (plain http only from this machine, for local development)
     - each key may make 'per_minute' requests a minute (default 60); an
       address sending wrong keys is refused for 15 minutes after 20 tries
       (table api_auth_requests, sql/2026-10-20-add-api-auth.sql)
     - every successful sign-in is written to the activity log (dars)
   Integration guide for the other app: tools/api-auth-login.md
   ========================================================================== */

require_once __DIR__ . '/bot-guard.php';
require_once __DIR__ . '/account-gate.php';

const WD_API_KEY_MIN_LEN    = 32;    // shorter keys in the config are ignored
const WD_API_PER_MINUTE     = 60;    // default requests a minute per key
const WD_API_TOKEN_TTL      = 3600;  // default token lifetime, seconds
const WD_API_BAD_KEY_MAX    = 20;    // wrong keys from one address …
const WD_API_BAD_KEY_WINDOW = 900;   // … within 15 minutes = refused for the rest of those 15 minutes

/**
 * The configured client apps: name => ['key' => …, 'per_minute' => …, 'token_ttl' => …].
 * config.local.php 'api_clients' (a name => key string is also fine), then
 * env WEDO_API_CLIENTS="name:key,…" on top.
 */
function wd_api_clients(): array
{
    $raw = wd_local_config()['api_clients'] ?? [];
    $raw = is_array($raw) ? $raw : [];
    $env = getenv('WEDO_API_CLIENTS');
    if (is_string($env) && $env !== '') {
        foreach (explode(',', $env) as $pair) {
            $parts = explode(':', trim($pair), 2);
            if (count($parts) === 2) { $raw[trim($parts[0])] = trim($parts[1]); }
        }
    }
    $clients = [];
    foreach ($raw as $name => $c) {
        $c = is_array($c) ? $c : ['key' => $c];
        $name = substr(preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $name), 0, 50);
        $key = trim((string) ($c['key'] ?? ''));
        if ($name === '') { continue; }
        if (strlen($key) < WD_API_KEY_MIN_LEN) {
            error_log("[wedo api-auth] client '$name' ignored: its key is shorter than " . WD_API_KEY_MIN_LEN . ' characters');
            continue;
        }
        $clients[$name] = [
            'key'        => $key,
            'per_minute' => max(1, (int) ($c['per_minute'] ?? WD_API_PER_MINUTE)),
            'token_ttl'  => max(60, (int) ($c['token_ttl'] ?? WD_API_TOKEN_TTL)),
        ];
    }
    return $clients;
}

/** The API key sent with this request: header X-WeDo-Key, or Authorization: Bearer <key>. */
function wd_api_request_key(): string
{
    $key = (string) ($_SERVER['HTTP_X_WEDO_KEY'] ?? '');
    if ($key === '') {
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (stripos($auth, 'Bearer ') === 0) { $key = substr($auth, 7); }
    }
    return trim($key);
}

/** Which client app this key belongs to (constant-time compare), or null. */
function wd_api_client_for_key(string $key): ?string
{
    if ($key === '') { return null; }
    $found = null;
    foreach (wd_api_clients() as $name => $c) {
        if (hash_equals($c['key'], $key)) { $found = $name; }
    }
    return $found;
}

/**
 * Over HTTPS? Cloudflare's own scheme headers are trusted only when the request
 * really comes from Cloudflare. Plain http is allowed only from this machine.
 */
function wd_api_is_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') { return true; }
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (in_array($remote, ['127.0.0.1', '::1'], true)) { return true; }
    foreach (WD_CF_RANGES as $r) {
        if (wd_ip_in($remote, $r)) {
            return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
                || strpos((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"') !== false;
        }
    }
    return false;
}

/** The request body: JSON (Content-Type: application/json) or a normal form post. */
function wd_api_body(): array
{
    if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json') !== false) {
        $j = json_decode((string) file_get_contents('php://input'), true);
        return is_array($j) ? $j : [];
    }
    return $_POST;
}

function wd_api_ready(PDO $pdo): bool
{
    try { $pdo->query("SELECT 1 FROM api_auth_requests LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}

/** Remember one API request (never the password). Now and then: keep only the last 30 days. */
function wd_api_log(PDO $pdo, string $client, string $ip, string $username, string $result): void
{
    try {
        $pdo->prepare("INSERT INTO api_auth_requests (ClientID, IP, Username, Result, CreatedAt) VALUES (?, ?, ?, ?, ?)")
            ->execute([$client, substr($ip, 0, 45), mb_substr(strtolower(trim($username)), 0, 150), $result, wd_login_clock(time())]);
        if (random_int(1, 100) === 1) {
            $pdo->exec("DELETE FROM api_auth_requests WHERE CreatedAt < (NOW() - INTERVAL 30 DAY)");
        }
    } catch (Throwable $e) {
        error_log('[wedo api-auth] log: ' . $e->getMessage());
    }
}

/** Seconds until this client may call again (0 = now): at most per_minute requests in any 60 seconds. */
function wd_api_rate_wait(PDO $pdo, string $client, int $perMinute): int
{
    $st = $pdo->prepare("SELECT CreatedAt FROM api_auth_requests WHERE ClientID = ? AND CreatedAt > ?
                         ORDER BY CreatedAt DESC LIMIT " . (int) $perMinute);
    $st->execute([$client, wd_login_clock(time() - 60)]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) < $perMinute) { return 0; }
    return max(1, wd_login_ts(end($rows)) + 60 - time());
}

/** Seconds this address stays refused for sending wrong keys (0 = not refused). */
function wd_api_bad_key_wait(PDO $pdo, string $ip): int
{
    $st = $pdo->prepare("SELECT CreatedAt FROM api_auth_requests WHERE ClientID = '' AND IP = ? AND CreatedAt > ?
                         ORDER BY CreatedAt DESC LIMIT " . WD_API_BAD_KEY_MAX);
    $st->execute([$ip, wd_login_clock(time() - WD_API_BAD_KEY_WINDOW)]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) < WD_API_BAD_KEY_MAX) { return 0; }
    return max(1, wd_login_ts(end($rows)) + WD_API_BAD_KEY_WINDOW - time());
}

function wd_api_b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** A JWT (HS256) signed with the client's own key, so the client app can check it itself. */
function wd_api_token(array $claims, string $key): string
{
    $head = wd_api_b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body = wd_api_b64url(json_encode($claims));
    return "$head.$body." . wd_api_b64url(hash_hmac('sha256', "$head.$body", $key, true));
}

/** The basic profile handed to the client app. */
function wd_api_profile(PDO $pdo, array $det): array
{
    $st = $pdo->prepare("SELECT EmpFN, EmpMN, EmpLN, EmpSuffix FROM employees WHERE EmpID = ?");
    $st->execute([(string) $det['EmpID']]);
    $emp = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $st = $pdo->prepare("SELECT CompanyDesc FROM companies WHERE CompanyID = ?");
    $st->execute([(string) $det['EmpCompID']]);
    $company = $st->fetchColumn();

    $first = trim((string) ($emp['EmpFN'] ?? ''));
    $last  = trim((string) ($emp['EmpLN'] ?? ''));
    $name  = trim(implode(' ', array_filter([$first, $last, trim((string) ($emp['EmpSuffix'] ?? ''))], 'strlen')));
    $role  = (int) $det['EmpRoleID'];
    return [
        'emp_id'      => (string) $det['EmpID'],
        'username'    => (string) $det['EmpUN'],
        'first_name'  => $first,
        'middle_name' => trim((string) ($emp['EmpMN'] ?? '')),
        'last_name'   => $last,
        'name'        => $name !== '' ? $name : (string) $det['EmpUN'],
        'company_id'  => (string) $det['EmpCompID'],
        'company'     => $company !== false ? (string) $company : '',
        // WeDo's user type: 1 = sees the whole organization, 2 = sees their team, other = sees only themself
        'role_id'     => $role,
        'role'        => $role === 1 ? 'admin' : ($role === 2 ? 'team_lead' : 'employee'),
    ];
}

/**
 * Handle one sign-in request. Returns [HTTP status, JSON body, extra headers].
 * Kept apart from the endpoint so the steps read top to bottom.
 */
function wd_api_auth_login(PDO $pdo): array
{
    $fail = fn(int $code, string $err, string $msg, array $extra = [], array $headers = []) =>
        [$code, ['ok' => false, 'error' => $err, 'message' => $msg] + $extra, $headers];

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return $fail(405, 'method_not_allowed', 'Use POST.', [], ['Allow' => 'POST']);
    }
    if (!wd_api_is_https()) {
        return $fail(403, 'https_required', 'Call this API over HTTPS.');
    }
    if (!wd_api_ready($pdo)) {
        return $fail(503, 'not_ready', 'The sign-in API is not set up on this server yet.');
    }

    // 1. which app is calling (wrong keys from one address are slowed down)
    $callerIp = wd_client_ip();
    $client = wd_api_client_for_key(wd_api_request_key());
    if ($client === null) {
        $wait = wd_api_bad_key_wait($pdo, $callerIp);
        if ($wait > 0) {
            return $fail(429, 'rate_limited', 'Too many requests with a wrong API key.', ['retry_after' => $wait], ['Retry-After' => (string) $wait]);
        }
        wd_api_log($pdo, '', $callerIp, '', 'bad_key');
        return $fail(401, 'invalid_api_key', 'Missing or wrong API key.', [], ['WWW-Authenticate' => 'Bearer']);
    }
    $cfg = wd_api_clients()[$client];

    // 2. this app's requests a minute
    $wait = wd_api_rate_wait($pdo, $client, $cfg['per_minute']);
    if ($wait > 0) {
        return $fail(429, 'rate_limited', 'Too many requests for this API key. Slow down.', ['retry_after' => $wait], ['Retry-After' => (string) $wait]);
    }

    $in   = wd_api_body();
    $user = is_string($in['username'] ?? null) ? trim($in['username']) : '';
    $pass = is_string($in['password'] ?? null) ? $in['password'] : '';
    // the person's own address, passed on by the calling server, counts toward the per-address limit
    $ip = (is_string($in['ip'] ?? null) && filter_var($in['ip'], FILTER_VALIDATE_IP)) ? $in['ip'] : $callerIp;
    if ($user === '' || $pass === '' || mb_strlen($user) > 150 || strlen($pass) > 1024) {
        wd_api_log($pdo, $client, $ip, $user, 'missing_fields');
        return $fail(422, 'missing_fields', 'Send a username and a password.');
    }

    // 3. the website's sign-in rules
    $lock = wd_login_lock($pdo, $user, $ip);
    if ($lock['wait'] > 0) {
        wd_api_log($pdo, $client, $ip, $user, 'locked');
        $mins = (int) ceil($lock['wait'] / 60);
        return $fail(429, 'locked', "Too many wrong passwords. Try again in $mins minute" . ($mins === 1 ? '' : 's') . '.',
                     ['retry_after' => $lock['wait']], ['Retry-After' => (string) $lock['wait']]);
    }

    $st = $pdo->prepare("SELECT EmpID, EmpUN, EmpPW, EmpRoleID, EmpCompID FROM empdetails WHERE EmpUN = ?");
    $st->execute([$user]);
    $det = $st->fetch(PDO::FETCH_ASSOC);
    $ok = $det ? password_verify($pass, (string) $det['EmpPW']) : false;
    if (!$det) { wd_login_dummy_verify($pass); }   // same work, same answer: usernames can't be probed
    if (!$ok) {
        wd_login_record($pdo, $user, $ip, false);
        wd_api_log($pdo, $client, $ip, $user, 'invalid_credentials');
        return $fail(401, 'invalid_credentials', 'Wrong username or password.');
    }
    wd_login_record($pdo, $user, $ip, true);
    $empId = (string) $det['EmpID'];

    if (in_array(strtolower($pass), WD_DEFAULT_PASSWORDS, true)) { wd_set_must_change_password($pdo, $empId, true); }
    if (wd_account_blocked($pdo, $empId)) {
        wd_api_log($pdo, $client, $ip, $user, 'inactive');
        return $fail(403, 'account_inactive', 'This account is no longer active. Please contact HR.');
    }
    if (wd_must_change_password($pdo, $empId)) {
        wd_api_log($pdo, $client, $ip, $user, 'password_change_required');
        return $fail(403, 'password_change_required', 'Sign in to WeDo and set your own password first.');
    }

    // 4. signed in
    $profile = wd_api_profile($pdo, $det);
    $now = time();
    $token = wd_api_token(['iss' => 'wedo', 'aud' => $client, 'sub' => $empId, 'name' => $profile['name'],
                           'iat' => $now, 'exp' => $now + $cfg['token_ttl'], 'jti' => bin2hex(random_bytes(12))], $cfg['key']);
    try {
        $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (?, ?, ?)")
            ->execute([$empId, "Signed in to $client (sign-in API)", wd_login_clock($now)]);
    } catch (Throwable $e) {
        error_log('[wedo api-auth] dars: ' . $e->getMessage());
    }
    wd_api_log($pdo, $client, $ip, $user, 'ok');

    return [200, ['ok' => true, 'token' => $token, 'token_type' => 'Bearer', 'expires_in' => $cfg['token_ttl'],
                  'expires_at' => gmdate('c', $now + $cfg['token_ttl']), 'user' => $profile], []];
}
