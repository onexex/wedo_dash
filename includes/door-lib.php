<?php
/* =============================================================================
 * includes/door-lib.php — Management > Door Control.
 *
 * The office door's magnetic lock hangs off the ZKTeco biometric device. People
 * holding the `doorctl` access right (accessrights.doorctl = 2,
 * sql/2026-11-11-add-door-control.sql; nobody gets it by default) can see
 * whether the device is online and release the lock for a few seconds — for
 * the days someone's fingerprint won't read. Every try, opened or not, is
 * written to door_log with who, when and from which address.
 *
 * WeDo talks to the device itself over the ZK protocol (TCP, port 4370): pure
 * PHP sockets, no vendor SDK. The device's address and Comm Key live in
 * config.local.php ('door' => [...], see config.local.example.php), never in
 * git or the database. Env WEDO_DOOR_HOST / WEDO_DOOR_PORT / WEDO_DOOR_KEY /
 * WEDO_DOOR_SECONDS win over the file (tests point them at a fake device).
 *
 * Three things are asked of the device: "who are you" (serial, name, clock),
 * "who is enrolled" (ID number, name, role — never their device passwords,
 * cards or fingerprints) and "release the lock for N seconds". Nothing is ever
 * written to the device, and its attendance logs are not read.
 *
 * Used by: door.php (the page), query/door-action.php (status + unlock)
 * ========================================================================== */

require_once __DIR__ . '/bot-guard.php';   // wd_local_config(), wd_client_ip()

const DOOR_SQL          = 'sql/2026-11-11-add-door-control.sql';
const DOOR_PORT         = 4370;
const DOOR_SECONDS      = 5;     // how long the lock stays released
const DOOR_SECONDS_MAX  = 15;
const DOOR_TIMEOUT      = 4;     // seconds to wait for the device before calling it offline
const DOOR_UNLOCK_GAP   = 3;     // seconds between two unlocks by the same person (double taps)

/* ZK protocol */
const ZK_CMD_CONNECT     = 1000;
const ZK_CMD_EXIT        = 1001;
const ZK_CMD_AUTH        = 1102;
const ZK_CMD_OPTIONS_RRQ = 11;
const ZK_CMD_UNLOCK      = 31;
const ZK_CMD_GET_TIME    = 201;
const ZK_CMD_GET_FREE_SIZES = 50;
const ZK_CMD_USERTEMP_RRQ   = 9;
const ZK_FCT_USER           = 5;
const ZK_CMD_PREPARE_DATA   = 1500;
const ZK_CMD_DATA           = 1501;
const ZK_CMD_FREE_DATA      = 1502;
const ZK_CMD_PREPARE_BUFFER = 1503;
const ZK_CMD_READ_BUFFER    = 1504;
const ZK_CHUNK              = 0xFFC0;    // most the device hands over in one piece
const ZK_LIST_MAX           = 2097152;   // 2 MB: far more than a full device's user list
const ZK_ACK_OK          = 2000;
const ZK_ACK_UNAUTH      = 2005;
const ZK_USHRT_MAX       = 65535;
const ZK_TCP_MAGIC       = "\x50\x50\x82\x7d";

/* ------------------------------------------------------------------ access */

/** Whether the signed-in user holds the `doorctl` right. False until the migration has run. */
function door_can(PDO $pdo, ?string $me = null): bool
{
    $me = $me ?? (string) ($_SESSION['id'] ?? '');
    if ($me === '' || $me === '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT doorctl FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $me]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;   // column not there yet
    }
}

/** Whether the door_log table exists (the migration has run on this server). */
function door_ready(PDO $pdo): bool
{
    try { $pdo->query("SELECT 1 FROM door_log LIMIT 1"); return true; } catch (Throwable $e) { return false; }
}

/** Per-session token for the page's requests. */
function door_csrf_token(): string
{
    if (empty($_SESSION['door_csrf'])) { $_SESSION['door_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['door_csrf'];
}

/* ------------------------------------------------------------------ config */

/**
 * The device this server controls, or null when none is set up here:
 * ['host', 'port', 'key' (Comm Key, 0 = none), 'seconds', 'name'].
 */
function door_config(): ?array
{
    $c = wd_local_config()['door'] ?? [];
    if (!is_array($c)) { $c = []; }
    $env = function ($name, $fallback) {
        $v = getenv($name);
        return ($v === false || $v === '') ? $fallback : $v;
    };
    $host = trim((string) $env('WEDO_DOOR_HOST', $c['host'] ?? ''));
    // an address or a host name, nothing else (it goes into a socket address)
    if ($host === '' || !preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/', $host)) { return null; }
    $port = (int) $env('WEDO_DOOR_PORT', $c['port'] ?? DOOR_PORT);
    if ($port < 1 || $port > 65535) { $port = DOOR_PORT; }
    $secs = (int) $env('WEDO_DOOR_SECONDS', $c['open_seconds'] ?? DOOR_SECONDS);
    $name = trim((string) ($c['name'] ?? ''));
    return [
        'host'    => $host,
        'port'    => $port,
        'key'     => (int) $env('WEDO_DOOR_KEY', $c['comm_key'] ?? 0),
        'seconds' => max(1, min(DOOR_SECONDS_MAX, $secs ?: DOOR_SECONDS)),
        'name'    => $name !== '' ? mb_substr($name, 0, 60) : 'Office door',
    ];
}

/* ------------------------------------------------------------- ZK protocol */

/** A reply the device gave that WeDo can't continue from. */
final class DoorException extends RuntimeException
{
    /** offline | auth | refused */
    public string $kind;
    public function __construct(string $kind, string $message) { parent::__construct($message); $this->kind = $kind; }
}

/** The 16-bit checksum the ZK protocol puts in every packet header. */
function zk_checksum(string $buf): int
{
    $sum = 0;
    $n = strlen($buf);
    for ($i = 0; $i + 1 < $n; $i += 2) {
        $sum += ord($buf[$i]) | (ord($buf[$i + 1]) << 8);
        if ($sum > ZK_USHRT_MAX) { $sum -= ZK_USHRT_MAX; }
    }
    if ($n % 2 === 1) { $sum += ord($buf[$n - 1]); }
    while ($sum > ZK_USHRT_MAX) { $sum -= ZK_USHRT_MAX; }
    $sum = ~$sum;
    while ($sum < 0) { $sum += ZK_USHRT_MAX; }
    return $sum & 0xFFFF;
}

/** The 4 bytes that answer the device's "not authorised": the Comm Key scrambled with the session id. */
function zk_commkey(int $key, int $sessionId, int $ticks = 50): string
{
    $k = 0;
    for ($i = 0; $i < 32; $i++) {
        $k = (($k << 1) | (($key >> $i) & 1)) & 0xFFFFFFFF;   // the key's 32 bits, reversed
    }
    $k = ($k + $sessionId) & 0xFFFFFFFF;
    $b = array_values(unpack('C4', pack('V', $k)));
    $b = [$b[0] ^ ord('Z'), $b[1] ^ ord('K'), $b[2] ^ ord('S'), $b[3] ^ ord('O')];
    $b = [$b[2], $b[3], $b[0], $b[1]];                         // swap the two 16-bit halves
    $t = $ticks & 0xFF;
    return pack('C4', $b[0] ^ $t, $b[1] ^ $t, $t, $b[3] ^ $t);
}

/** The device's clock value as 'Y-m-d H:i:s'. */
function zk_decode_time(int $t): string
{
    $s = $t % 60; $t = intdiv($t, 60);
    $m = $t % 60; $t = intdiv($t, 60);
    $h = $t % 24; $t = intdiv($t, 24);
    $d = $t % 31 + 1; $t = intdiv($t, 31);
    $mo = $t % 12 + 1; $t = intdiv($t, 12);
    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $t + 2000, $mo, $d, $h, $m, $s);
}

/** One conversation with a ZK device over TCP: connect (+ Comm Key), ask, release, hang up. */
final class DoorDevice
{
    /** @var resource|null */
    private $sock = null;
    private int $session = 0;
    private int $reply = ZK_USHRT_MAX - 1;

    public function __construct(private string $host, private int $port, private int $key, private int $timeout = DOOR_TIMEOUT) {}

    public function __destruct() { $this->close(); }

    /** Open the line and sign in. Throws DoorException('offline' | 'auth' | 'refused'). */
    public function connect(): void
    {
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout);
        if (!$sock) { throw new DoorException('offline', 'The device did not answer.'); }
        stream_set_timeout($sock, $this->timeout);
        $this->sock = $sock;
        $this->session = 0;
        $this->reply = ZK_USHRT_MAX - 1;

        $r = $this->send(ZK_CMD_CONNECT);
        $this->session = $r['session'];
        if ($r['code'] === ZK_ACK_UNAUTH) {
            $r = $this->send(ZK_CMD_AUTH, zk_commkey($this->key, $this->session));
            if ($r['code'] === ZK_ACK_UNAUTH) { throw new DoorException('auth', 'The device rejected the Comm Key.'); }
        }
        if ($r['code'] !== ZK_ACK_OK) { throw new DoorException('refused', 'The device refused the connection (code ' . $r['code'] . ').'); }
    }

    /** One of the device's settings by name (~SerialNumber, ~DeviceName), or '' when it has none. */
    public function option(string $name): string
    {
        $r = $this->send(ZK_CMD_OPTIONS_RRQ, $name . "\0");
        if ($r['code'] !== ZK_ACK_OK) { return ''; }
        $text = explode("\0", $r['data'], 2)[0];
        $at = strpos($text, '=');
        return $at === false ? '' : trim(preg_replace('/[^\x20-\x7E]/', '', substr($text, $at + 1)));
    }

    /** The device's own clock ('Y-m-d H:i:s'), or '' when it won't say. */
    public function time(): string
    {
        $r = $this->send(ZK_CMD_GET_TIME);
        if ($r['code'] !== ZK_ACK_OK || strlen($r['data']) < 4) { return ''; }
        return zk_decode_time(unpack('V', $r['data'])[1]);
    }

    /** Release the lock for $seconds. Throws DoorException('refused') when the device says no. */
    public function unlock(int $seconds): void
    {
        $r = $this->send(ZK_CMD_UNLOCK, pack('V', $seconds * 10));   // the device counts in tenths of a second
        if ($r['code'] !== ZK_ACK_OK) { throw new DoorException('refused', 'The device refused to release the lock (code ' . $r['code'] . ').'); }
    }

    public function close(): void
    {
        if (!$this->sock) { return; }
        try { $this->send(ZK_CMD_EXIT); } catch (Throwable $e) { /* already gone */ }
        @fclose($this->sock);
        $this->sock = null;
    }

    /** How full the device is: ['users', 'fingers', 'userCap'], or null when it won't say. */
    public function sizes(): ?array
    {
        $r = $this->send(ZK_CMD_GET_FREE_SIZES);
        if ($r['code'] !== ZK_ACK_OK || strlen($r['data']) < 80) { return null; }
        $f = array_values(unpack('V20', $r['data']));
        return ['users' => $f[4], 'fingers' => $f[6], 'userCap' => $f[15]];
    }

    /**
     * Everyone enrolled on the device: [['uid', 'userId', 'name', 'role'], ...].
     * Their device passwords and card numbers are read past, never returned.
     */
    public function users(): array
    {
        $count = $this->sizes()['users'] ?? 0;
        if ($count < 1) { return []; }
        $blob = $this->readBuffer(ZK_CMD_USERTEMP_RRQ, ZK_FCT_USER);
        if (strlen($blob) <= 4) { return []; }
        $total = unpack('V', $blob)[1];
        $blob  = (string) substr($blob, 4);
        $size  = intdiv($total, $count);
        if ($size !== 28 && $size !== 72) { $size = strlen($blob) % 72 === 0 ? 72 : 28; }   // old / new firmware record
        $text = function (string $raw): string {
            $s = explode("\0", $raw, 2)[0];
            if (!mb_check_encoding($s, 'UTF-8')) { $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252'); }
            return trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '');
        };
        $out = [];
        for ($at = 0; $at + $size <= strlen($blob); $at += $size) {
            $rec = substr($blob, $at, $size);
            $h = unpack('vuid/Cpriv', $rec);
            if ($size === 28) {
                $name = $text(substr($rec, 8, 8));
                $userId = (string) unpack('V', substr($rec, 24, 4))[1];
            } else {
                $name = $text(substr($rec, 11, 24));
                $userId = $text(substr($rec, 48, 24));
            }
            $out[] = ['uid' => $h['uid'], 'userId' => $userId, 'name' => $name,
                      'role' => $h['priv'] === 0 ? 'User' : ($h['priv'] === 14 ? 'Admin' : 'Other')];
        }
        return $out;
    }

    /** One of the device's lists (users ...), fetched in pieces the way the device hands big answers over. */
    private function readBuffer(int $cmd, int $fct): string
    {
        $r = $this->send(ZK_CMD_PREPARE_BUFFER, pack('CvVV', 1, $cmd, $fct, 0));
        if ($r['code'] === ZK_CMD_DATA) { return $r['data']; }   // small enough to come back at once
        if ($r['code'] !== ZK_ACK_OK || strlen($r['data']) < 5) { throw new DoorException('refused', 'The device would not hand over its list (code ' . $r['code'] . ').'); }
        $size = unpack('V', substr($r['data'], 1, 4))[1];
        if ($size > ZK_LIST_MAX) { throw new DoorException('refused', 'The device\'s list is too large to show here.'); }

        $out = '';
        while (strlen($out) < $size) {
            $want = min(ZK_CHUNK, $size - strlen($out));
            $r = $this->send(ZK_CMD_READ_BUFFER, pack('VV', strlen($out), $want));
            if ($r['code'] === ZK_CMD_DATA) {
                $piece = $r['data'];
            } elseif ($r['code'] === ZK_CMD_PREPARE_DATA) {
                // "data follows": one or more data packets, then an OK
                $piece = '';
                while (strlen($piece) < $want) {
                    $f = $this->receive();
                    if ($f['code'] !== ZK_CMD_DATA) { throw new DoorException('refused', 'The device stopped part-way through its list.'); }
                    $piece .= $f['data'];
                }
                $this->receive();
            } else {
                throw new DoorException('refused', 'The device stopped part-way through its list (code ' . $r['code'] . ').');
            }
            if ($piece === '') { throw new DoorException('refused', 'The device stopped part-way through its list.'); }
            $out .= $piece;
        }
        $this->send(ZK_CMD_FREE_DATA);
        return substr($out, 0, $size);
    }

    /** Send one command, wait for its reply: ['code', 'session', 'data']. */
    private function send(int $cmd, string $data = ''): array
    {
        // the checksum covers the header as it stood BEFORE the reply counter moves on
        $sum = zk_checksum(pack('v4', $cmd, 0, $this->session, $this->reply) . $data);
        $this->reply++;
        if ($this->reply >= ZK_USHRT_MAX) { $this->reply -= ZK_USHRT_MAX; }
        $packet = pack('v4', $cmd, $sum, $this->session, $this->reply) . $data;
        $frame  = ZK_TCP_MAGIC . pack('V', strlen($packet)) . $packet;
        if (@fwrite($this->sock, $frame) !== strlen($frame)) { throw new DoorException('offline', 'The connection to the device dropped.'); }
        return $this->receive();
    }

    /** The next packet from the device: ['code', 'session', 'data']. */
    private function receive(): array
    {
        $top = $this->read(8);
        if (substr($top, 0, 4) !== ZK_TCP_MAGIC) { throw new DoorException('offline', 'That address did not answer like a biometric device.'); }
        $len = unpack('V', substr($top, 4))[1];
        if ($len < 8 || $len > 65536) { throw new DoorException('offline', 'That address did not answer like a biometric device.'); }
        $body = $this->read($len);
        $h = unpack('vcode/vsum/vsession/vreply', $body);
        $this->reply = $h['reply'];
        return ['code' => $h['code'], 'session' => $h['session'], 'data' => (string) substr($body, 8)];
    }

    private function read(int $n): string
    {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = @fread($this->sock, $n - strlen($buf));
            if ($chunk === false || $chunk === '') { throw new DoorException('offline', 'The device stopped answering.'); }
            $buf .= $chunk;
        }
        return $buf;
    }
}

/* ------------------------------------------------------------ what the page asks */

/**
 * Is the device there, and will it take our Comm Key?
 * ['state' => unset | online | auth | offline, 'msg', 'name', + online: 'serial', 'model', 'clock', 'ms', 'seconds']
 */
function door_status(): array
{
    $c = door_config();
    if (!$c) {
        return ['state' => 'unset', 'msg' => 'No door device is set up on this server yet. Add the \'door\' entry to config.local.php.'];
    }
    $out = ['name' => $c['name'], 'seconds' => $c['seconds']];
    $t0 = microtime(true);
    try {
        $dev = new DoorDevice($c['host'], $c['port'], $c['key']);
        $dev->connect();
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $out += ['state' => 'online', 'msg' => 'Online and ready.', 'ms' => $ms,
                 'serial' => $dev->option('~SerialNumber'), 'model' => $dev->option('~DeviceName'), 'clock' => $dev->time()];
        $dev->close();
        return $out;
    } catch (DoorException $e) {
        if ($e->kind === 'auth') {
            return $out + ['state' => 'auth', 'msg' => 'The device is online but rejected the Comm Key. Check \'comm_key\' in config.local.php against the device menu.'];
        }
        return $out + ['state' => 'offline', 'msg' => $e->getMessage() . ' It may be switched off, off the network, or this server may not be allowed to reach it.'];
    }
}

/**
 * Who is enrolled on the device, by name.
 * ['ok' => bool, 'msg', 'users' => [['userId', 'name', 'role'], ...], 'fingers' => int|null]
 */
function door_enrolled(): array
{
    $c = door_config();
    if (!$c) { return ['ok' => false, 'msg' => 'No door device is set up on this server yet.']; }
    try {
        $dev = new DoorDevice($c['host'], $c['port'], $c['key']);
        $dev->connect();
        $sizes = $dev->sizes();
        $users = $dev->users();
        $dev->close();
    } catch (DoorException $e) {
        return ['ok' => false, 'msg' => $e->kind === 'auth' ? 'The device rejected the Comm Key.' : $e->getMessage()];
    }
    usort($users, function ($a, $b) { return strnatcasecmp($a['name'] . ' ' . $a['userId'], $b['name'] . ' ' . $b['userId']); });
    $users = array_map(function ($u) { return ['userId' => $u['userId'], 'name' => $u['name'], 'role' => $u['role']]; }, $users);
    return ['ok' => true, 'msg' => '', 'users' => $users, 'fingers' => $sizes['fingers'] ?? null];
}

/** Write one try to door_log. */
function door_log_add(PDO $pdo, string $actor, string $result, string $detail, string $note, int $seconds): void
{
    $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO door_log (EmpID, Action, Result, Detail, Note, Seconds, IP, CreatedAt)
                   VALUES (:e, 'unlock', :r, :d, :n, :s, :ip, :at)")
        ->execute([':e' => $actor, ':r' => $result, ':d' => mb_substr($detail, 0, 200) ?: null, ':n' => $note !== '' ? $note : null,
                   ':s' => $seconds, ':ip' => mb_substr(wd_client_ip(), 0, 45), ':at' => $now]);
}

/**
 * Release the lock for the configured seconds and log it either way.
 * ['ok' => bool, 'msg', 'seconds']
 */
function door_unlock(PDO $pdo, string $actor, string $note = ''): array
{
    $note = mb_substr(trim(preg_replace('/\s+/', ' ', $note)), 0, 150);
    $c = door_config();
    if (!$c) { return ['ok' => false, 'msg' => 'No door device is set up on this server yet.']; }

    // a second tap right after the first: the lock is still released, don't fire again
    $last = $pdo->prepare("SELECT CreatedAt FROM door_log WHERE EmpID = :e AND Result = 'ok' ORDER BY id DESC LIMIT 1");
    $last->execute([':e' => $actor]);
    $at = $last->fetchColumn();
    $tz = new DateTimeZone('Asia/Manila');
    if ($at && (new DateTimeImmutable('now', $tz))->getTimestamp() - (new DateTimeImmutable($at, $tz))->getTimestamp() < DOOR_UNLOCK_GAP) {
        return ['ok' => false, 'msg' => 'The door was just unlocked. Give it a moment.'];
    }

    try {
        $dev = new DoorDevice($c['host'], $c['port'], $c['key']);
        $dev->connect();
        $dev->unlock($c['seconds']);
        $dev->close();
    } catch (DoorException $e) {
        $why = $e->kind === 'auth' ? 'The device rejected the Comm Key.' : $e->getMessage();
        door_log_add($pdo, $actor, 'failed', $why, $note, $c['seconds']);
        return ['ok' => false, 'msg' => 'The door was NOT unlocked. ' . $why];
    }
    door_log_add($pdo, $actor, 'ok', '', $note, $c['seconds']);
    return ['ok' => true, 'msg' => 'Unlocked for ' . $c['seconds'] . ' seconds.', 'seconds' => $c['seconds']];
}

/** The latest tries, newest first, with the person's name. */
function door_log_rows(PDO $pdo, int $limit = 30): array
{
    $st = $pdo->query("SELECT l.id, l.EmpID, l.Result, l.Detail, l.Note, l.Seconds, l.CreatedAt,
                              TRIM(CONCAT(COALESCE(e.EmpFN, ''), ' ', COALESCE(e.EmpLN, ''))) AS name
                         FROM door_log l LEFT JOIN employees e ON e.EmpID = l.EmpID
                        ORDER BY l.id DESC LIMIT " . max(1, min(200, $limit)));
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'who' => $r['name'] !== '' ? $r['name'] : $r['EmpID'], 'ok' => $r['Result'] === 'ok',
                  'detail' => (string) $r['Detail'], 'note' => (string) $r['Note'], 'seconds' => (int) $r['Seconds'],
                  'at' => date('M j, Y g:i:s A', strtotime($r['CreatedAt']))];
    }
    return $out;
}
