<?php
/* -----------------------------------------------------------------------------
 * A pretend ZKTeco device for tests/DoorControlTest.php: listens on a free
 * local port, speaks just enough of the ZK protocol (connect, Comm Key, read a
 * setting, read the clock, release the lock, hang up) and writes every lock
 * release to a file so the test can see what "the door" was told.
 *
 *   php fake-zk-device.php <commKey> <logFile> [refuse|old]
 *
 * Prints its port on the first line of stdout. Ends by itself after 2 minutes.
 * Never reachable over HTTP: CLI only.
 * -------------------------------------------------------------------------- */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__, 2) . '/includes/door-lib.php';

$key    = (int) ($argv[1] ?? 0);
$log    = (string) ($argv[2] ?? '');
$refuse = ($argv[3] ?? '') === 'refuse';
$old    = ($argv[3] ?? '') === 'old';      // old firmware: short user records, list handed over in one go

// who is "enrolled": [uid, privilege, device password, name, card, ID number]
$people = [[1, 14, 'SECRETPW', 'Ada Admin', 5550001, '1001'], [2, 0, '', 'Juan Dela Cruz', 0, '1002'], [3, 0, '', '', 0, '1003']];
$list = '';
foreach ($people as [$uid, $priv, $pw, $name, $card, $id]) {
    $list .= $old
        ? pack('vC', $uid, $priv) . str_pad(substr($pw, 0, 5), 5, "\0") . str_pad(substr($name, 0, 8), 8, "\0") . pack('V', $card) . "\0\1" . pack('v', 0) . pack('V', (int) $id)
        : pack('vC', $uid, $priv) . str_pad($pw, 8, "\0") . str_pad($name, 24, "\0") . pack('V', $card) . "\0" . str_pad('1', 7, "\0") . "\0" . str_pad($id, 24, "\0");
}
$list = pack('V', strlen($list)) . $list;

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$server) { fwrite(STDERR, "fake-zk-device: $errstr\n"); exit(1); }
echo (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1), "\n";
flush();

$readN = function ($conn, int $n): ?string {
    $buf = '';
    while (strlen($buf) < $n) {
        $chunk = fread($conn, $n - strlen($buf));
        if ($chunk === false || $chunk === '') { return null; }
        $buf .= $chunk;
    }
    return $buf;
};

$until = time() + 120;
while (time() < $until) {
    $conn = @stream_socket_accept($server, 1);
    if (!$conn) { continue; }
    stream_set_timeout($conn, 3);
    $session = 0x1234;
    $authed  = false;

    while (($top = $readN($conn, 8)) !== null) {
        $body = $readN($conn, unpack('V', substr($top, 4))[1]);
        if ($body === null) { break; }
        $h    = unpack('vcmd/vsum/vsession/vreply', $body);
        $data = (string) substr($body, 8);
        $code = ZK_ACK_OK;
        $out  = '';

        if ($h['cmd'] === ZK_CMD_CONNECT) {
            $authed = $key === 0;
            $code   = $authed ? ZK_ACK_OK : ZK_ACK_UNAUTH;
        } elseif ($h['cmd'] === ZK_CMD_AUTH) {
            $authed = hash_equals(zk_commkey($key, $session), $data);
            $code   = $authed ? ZK_ACK_OK : ZK_ACK_UNAUTH;
        } elseif (!$authed) {
            $code = ZK_ACK_UNAUTH;
        } elseif ($h['cmd'] === ZK_CMD_OPTIONS_RRQ) {
            $name = rtrim($data, "\0");
            $have = ['~SerialNumber' => 'FAKE0001', '~DeviceName' => 'F04'];
            $out  = $name . '=' . ($have[$name] ?? '') . "\0";
        } elseif ($h['cmd'] === ZK_CMD_GET_TIME) {
            // 2026-11-11 08:30:15 in the device's own encoding
            $out = pack('V', ((26 * 12 * 31 + 10 * 31 + 10) * 24 + 8) * 3600 + 30 * 60 + 15);
        } elseif ($h['cmd'] === ZK_CMD_GET_FREE_SIZES) {
            $f = array_fill(0, 20, 0);
            $f[4] = count($people); $f[6] = 5; $f[15] = 1000;
            $out = pack('V20', ...$f);
        } elseif ($h['cmd'] === ZK_CMD_PREPARE_BUFFER) {
            if ($old) { $code = ZK_CMD_DATA; $out = $list; }                   // small list: handed over at once
            else { $out = "\0" . pack('V', strlen($list)) . "\0\0\0\0"; }     // big list: "come and get it"
        } elseif ($h['cmd'] === ZK_CMD_READ_BUFFER) {
            // "data follows" + the piece in two packets + OK
            $q     = unpack('Vstart/Vlen', $data);
            $piece = substr($list, $q['start'], $q['len']);
            $half  = intdiv(strlen($piece), 2);
            $frames = [[ZK_CMD_PREPARE_DATA, pack('V', strlen($piece))], [ZK_CMD_DATA, substr($piece, 0, $half)],
                       [ZK_CMD_DATA, substr($piece, $half)], [ZK_ACK_OK, '']];
            foreach ($frames as [$c, $d]) {
                $packet = pack('v4', $c, 0, $session, $h['reply']) . $d;
                fwrite($conn, ZK_TCP_MAGIC . pack('V', strlen($packet)) . $packet);
            }
            continue;
        } elseif ($h['cmd'] === ZK_CMD_UNLOCK) {
            if ($refuse) { $code = 2001; }
            else { file_put_contents($log, 'unlock ' . unpack('V', $data)[1] . "\n", FILE_APPEND); }
        }

        $packet = pack('v4', $code, 0, $session, $h['reply']) . $out;
        fwrite($conn, ZK_TCP_MAGIC . pack('V', strlen($packet)) . $packet);
        if ($h['cmd'] === ZK_CMD_EXIT) { break; }
    }
    fclose($conn);
}
