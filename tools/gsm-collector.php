<?php
/* ==========================================================================
   gsm-collector.php  —  SIM Inbox collector (runs on an office PC).

   Reads the GoIP gateway's SMS inbox over the office network and sends new
   texts to WeDo (api/gsm-ingest.php). Run it on a PC on the SAME network as
   the gateway (same router / internet line); WeDo itself never has to reach
   the gateway. Setup guide: tools/gsm-collector.md

     php gsm-collector.php                read the inbox once, send what is new (Task Scheduler, every minute)
     php gsm-collector.php --loop         keep doing that every 'interval' seconds
     php gsm-collector.php --test         read the inbox and print it; sends nothing, remembers nothing
     php gsm-collector.php --check        read the gateway, then tell WeDo the result (tests the key and URL)
     php gsm-collector.php --dump=FILE    save the gateway's raw inbox page to FILE (to check its format)
     php gsm-collector.php --balance      send the balance-inquiry text now ('balance_check' in the config)
     add --config=PATH to use another config file (default: gsm-collector.config.php next to this file)

   Needs only PHP 7.4+ with curl (XAMPP's php.exe is fine). Nothing from WeDo
   is loaded: this file can be copied to any PC on its own, with its config.
   Command line only.
   ========================================================================== */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const GSMC_VERSION   = '1.0';
const GSMC_BATCH     = 200;      // texts per post to WeDo
const GSMC_KEEP_DAYS = 14;       // remember a text this long after it leaves the gateway's inbox
const GSMC_LINE_MAX  = 32;

/* ------------------------------------------------------------------ config */

/** Read and check the config file. Returns [config, null] or [null, problem]. */
function gsmc_load_config(string $path): array
{
    if (!is_file($path)) { return [null, "No config file at $path. Copy gsm-collector.config.example.php to gsm-collector.config.php and fill it in."]; }
    $c = require $path;
    if (!is_array($c)) { return [null, "$path must return an array."]; }

    $g = $c['goip'] ?? [];
    $url = rtrim(trim((string) ($g['url'] ?? '')), '/');
    if (!preg_match('#^https?://[A-Za-z0-9.-]+(:\d+)?$#', $url)) { return [null, "'goip' => ['url' => ...] must look like http://192.168.35.51"]; }
    if ((string) ($g['user'] ?? '') === '') { return [null, "'goip' => ['user' => ...] is empty."]; }

    $targets = [];
    foreach ((array) ($c['targets'] ?? []) as $t) {
        $tu = trim((string) ($t['url'] ?? ''));
        $tk = trim((string) ($t['key'] ?? ''));
        if (!preg_match('#^https?://#', $tu) || strlen($tk) < 32) { return [null, "Each target needs 'url' (https://.../api/gsm-ingest.php) and 'key' (32+ characters, the server's ingest_key)."]; }
        $targets[] = ['url' => $tu, 'key' => $tk];
    }
    if (!$targets) { return [null, "Add at least one entry to 'targets'."]; }

    $lines = [];
    foreach ((array) ($c['lines'] ?? []) as $l) {
        if (is_numeric($l) && (int) $l >= 1 && (int) $l <= GSMC_LINE_MAX) { $lines[] = (int) $l; }
    }

    $bal = $c['balance_check'] ?? null;
    if ($bal !== null) {
        if (!is_array($bal) || trim((string) ($bal['number'] ?? '')) === '' || trim((string) ($bal['text'] ?? '')) === '') {
            return [null, "'balance_check' needs 'number' and 'text' (or set it to null)."];
        }
        if (!$lines) { return [null, "'balance_check' only sends from the lines listed in 'lines': list them first."]; }
        $at = (string) ($bal['at'] ?? '08:00');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $at)) { return [null, "'balance_check' => ['at' => 'HH:MM'], e.g. '08:00'."]; }
        $bal = ['number' => trim((string) $bal['number']), 'text' => trim((string) $bal['text']), 'at' => $at];
    }

    $dir = dirname($path);
    return [[
        'name'     => substr(preg_replace('/[^A-Za-z0-9 ._-]/', '', (string) ($c['name'] ?? gethostname())), 0, 40) ?: 'collector',
        'goip'     => ['url' => $url, 'user' => (string) $g['user'], 'pass' => (string) ($g['pass'] ?? '')],
        'lines'    => $lines,
        'targets'  => $targets,
        'interval' => max(15, (int) ($c['interval'] ?? 60)),
        'balance'  => $bal,
        'state'    => (string) ($c['state_file'] ?? $dir . DIRECTORY_SEPARATOR . 'gsm-collector.state.json'),
        'log'      => (string) ($c['log_file'] ?? ''),
        'ca_file'  => (string) ($c['ca_file'] ?? ''),
        'timezone' => (string) ($c['timezone'] ?? 'Asia/Manila'),
    ], null];
}

/* -------------------------------------------------------------------- http */

/** One HTTP request. Returns ['status' => int (0 = no answer), 'body' => string, 'error' => string]. */
function gsmc_http(string $method, string $url, array $headers = [], ?string $body = null, ?array $basic = null, int $timeout = 15, string $caFile = ''): array
{
    if (!function_exists('curl_init')) { return ['status' => 0, 'body' => '', 'error' => 'PHP curl is not enabled (php.ini: extension=curl).']; }
    $h = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => min(8, $timeout), CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'WeDo-GSM-Collector/' . GSMC_VERSION];
    if ($body !== null) { $opt[CURLOPT_POSTFIELDS] = $body; }
    if ($basic !== null) { $opt[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC; $opt[CURLOPT_USERPWD] = $basic[0] . ':' . $basic[1]; }
    if ($caFile !== '') { $opt[CURLOPT_CAINFO] = $caFile; }
    curl_setopt_array($h, $opt);
    $out = curl_exec($h);
    $res = ['status' => (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), 'body' => is_string($out) ? $out : '', 'error' => $out === false ? curl_error($h) : ''];
    curl_close($h);
    return $res;
}

/* ------------------------------------------------------------------- GoIP */

/** A JavaScript string literal's contents, unescaped. */
function gsmc_js_string(string $raw): string
{
    $j = json_decode('"' . str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], preg_replace("/\\\\'/", "'", $raw)) . '"');
    return is_string($j) ? $j : stripcslashes($raw);
}

/** Text from the gateway as valid UTF-8 (WeDo refuses anything else). */
function gsmc_utf8(string $s): string
{
    if (mb_check_encoding($s, 'UTF-8')) { return $s; }
    $c = @iconv('GBK', 'UTF-8//IGNORE', $s);
    return ($c !== false && $c !== '') ? $c : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
}

/** Whether a piece of an inbox entry is the gateway's time stamp ("11-19 10:15:07", "2026-11-19 10:15:07"). */
function gsmc_is_time(string $s): bool
{
    return (bool) preg_match('/^\s*(\d{2,4}[-\/.])?\d{1,2}[-\/.]\d{1,2}[ T]\d{1,2}:\d{2}(:\d{2})?\s*$/', $s);
}

/**
 * The texts on the GoIP inbox page (tools.html?type=sms_inbox). The page holds
 * one JavaScript block per line:
 *     sms= ["11-19 10:15:07,222,Your balance is ...", "", ...];
 *     pos=3;
 *     sms_row_insert(l1_sms_store, sms, pos, 1);
 * Each entry is "time,sender,text" (some firmware: "sender,time,text"); empty
 * entries are free slots. Returns [['line', 'device_time', 'sender', 'text'], ...].
 */
function gsmc_parse_inbox(string $html): array
{
    $out = [];
    if (!preg_match_all('/\bsms\s*=\s*\[(.*?)\]\s*;(.*?)(?=\bsms\s*=\s*\[|$)/s', $html, $blocks, PREG_SET_ORDER)) { return $out; }
    foreach ($blocks as $b) {
        if (preg_match('/\bl(\d+)_sms_store\b/', $b[2], $x)) { $line = (int) $x[1]; }
        elseif (preg_match('/sms_row_insert\s*\([^)]*?,\s*(\d+)\s*\)/', $b[2], $x)) { $line = (int) $x[1]; }
        else { continue; }
        if ($line < 1 || $line > GSMC_LINE_MAX) { continue; }
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'/s', $b[1], $items, PREG_SET_ORDER);
        foreach ($items as $it) {
            $entry = gsmc_utf8(gsmc_js_string($it[1] !== '' ? $it[1] : ($it[2] ?? '')));
            if (trim($entry) === '') { continue; }
            $p = explode(',', $entry, 3);
            if (count($p) < 3) { continue; }
            if (gsmc_is_time($p[0]))     { [$time, $sender, $text] = [$p[0], $p[1], $p[2]]; }
            elseif (gsmc_is_time($p[1])) { [$sender, $time, $text] = [$p[0], $p[1], $p[2]]; }
            else { continue; }
            $out[] = ['line' => $line, 'device_time' => trim($time), 'sender' => trim($sender), 'text' => $text];
        }
    }
    return $out;
}

/** Same fingerprint as WeDo's gsm_hash(). */
function gsmc_hash(array $m): string
{
    return sha1($m['line'] . "\n" . $m['device_time'] . "\n" . $m['sender'] . "\n" . $m['text']);
}

/** Read the inbox. Returns [messages, null] or [null, problem in plain words]. */
function gsmc_read_inbox(array $cfg, ?string &$raw = null): array
{
    $g = $cfg['goip'];
    $r = gsmc_http('GET', $g['url'] . '/default/en_US/tools.html?type=sms_inbox', [], null, [$g['user'], $g['pass']], 15);
    $raw = $r['body'];
    if ($r['status'] === 0)   { return [null, 'The gateway did not answer at ' . $g['url'] . ' (' . $r['error'] . '). Is this PC on the same network as the gateway?']; }
    if ($r['status'] === 401) { return [null, 'The gateway refused the username or password in the collector config.']; }
    if ($r['status'] !== 200) { return [null, 'The gateway answered HTTP ' . $r['status'] . ' for its inbox page.']; }
    $msgs = gsmc_parse_inbox($r['body']);
    if (!$msgs && !preg_match('/sms_store|sms\s*=\s*\[/', $r['body'])) {
        return [null, 'The gateway\'s inbox page has an unexpected layout. Save it with --dump=inbox.html and send it to whoever looks after WeDo.'];
    }
    if ($cfg['lines']) { $msgs = array_values(array_filter($msgs, function ($m) use ($cfg) { return in_array($m['line'], $cfg['lines'], true); })); }
    return [$msgs, null];
}

/** Send one text from a line (the gateway's send.html). Returns null when the gateway took it, or the problem. */
function gsmc_send_sms(array $cfg, int $line, string $number, string $text): ?string
{
    $g = $cfg['goip'];
    $url = $g['url'] . '/default/en_US/send.html?' . http_build_query(['u' => $g['user'], 'p' => $g['pass'], 'l' => $line, 'n' => $number, 'm' => $text]);
    $r = gsmc_http('GET', $url, [], null, [$g['user'], $g['pass']], 20);
    if ($r['status'] !== 200) { return 'the gateway answered ' . ($r['status'] ?: 'nothing') . ($r['error'] ? ' (' . $r['error'] . ')' : ''); }
    if (stripos($r['body'], 'error') !== false) { return 'the gateway said: ' . substr(trim(strip_tags($r['body'])), 0, 120); }
    return null;
}

/* ------------------------------------------------------------------- WeDo */

/** Post to one WeDo. Returns [accepted fingerprints, null] or [null, problem]. */
function gsmc_post(array $cfg, array $target, array $payload): array
{
    $r = gsmc_http('POST', $target['url'], ['Content-Type: application/json', 'X-WeDo-Key: ' . $target['key']],
                   json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), null, 20, $cfg['ca_file']);
    $j = json_decode($r['body'], true);
    if ($r['status'] === 200 && is_array($j) && !empty($j['ok'])) { return [(array) ($j['accepted'] ?? []), null]; }
    if ($r['status'] === 0) { return [null, 'WeDo did not answer (' . $r['error'] . ')']; }
    return [null, 'WeDo answered HTTP ' . $r['status'] . (is_array($j) && isset($j['message']) ? ': ' . $j['message'] : '')];
}

/* ------------------------------------------------------------------ state */

function gsmc_state_load(string $file): array
{
    $s = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $s = is_array($s) ? $s : [];
    return ['seen' => (array) ($s['seen'] ?? []), 'sent' => (array) ($s['sent'] ?? []), 'balance' => (array) ($s['balance'] ?? [])];
}

function gsmc_state_save(string $file, array $s): void
{
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if (!@rename($tmp, $file)) { @unlink($file); rename($tmp, $file); }   // Windows won't rename over a file
}

/* ------------------------------------------------------------------- runs */

function gsmc_say(array $cfg, string $msg, bool $err = false): void
{
    $line = date('Y-m-d H:i:s') . '  ' . $msg . "\n";
    fwrite($err ? STDERR : STDOUT, $line);
    if ($cfg['log'] !== '') { @file_put_contents($cfg['log'], $line, FILE_APPEND); }
}

/** One round: read the gateway, send what each WeDo hasn't got yet. Returns the exit code. */
function gsmc_poll(array $cfg, bool $checkOnly = false): int
{
    $state = gsmc_state_load($cfg['state']);
    [$msgs, $problem] = gsmc_read_inbox($cfg);
    $now = date('c');
    $here = [];

    if ($msgs !== null) {
        foreach ($msgs as &$m) {
            $h = gsmc_hash($m);
            $here[$h] = true;
            if (!isset($state['seen'][$h])) { $state['seen'][$h] = $now; }
            $m['seen_at'] = $state['seen'][$h];
            $m['hash'] = $h;
        }
        unset($m);
        // forget texts that left the gateway's inbox a while ago
        $cut = date('c', time() - GSMC_KEEP_DAYS * 86400);
        foreach ($state['seen'] as $h => $t) { if (!isset($here[$h]) && $t < $cut) { unset($state['seen'][$h]); } }
    } else {
        gsmc_say($cfg, 'Gateway: ' . $problem, true);
    }

    $code = $msgs === null ? 1 : 0;
    $base = ['collector' => $cfg['name'], 'version' => GSMC_VERSION, 'ok' => $msgs !== null, 'error' => $problem, 'lines' => $cfg['lines']];
    foreach ($cfg['targets'] as $t) {
        $sent = (array) ($state['sent'][$t['url']] ?? []);
        foreach ($sent as $h => $_) { if (!isset($state['seen'][$h])) { unset($sent[$h]); } }
        $todo = [];
        if (!$checkOnly && $msgs !== null) {
            foreach ($msgs as $m) {
                if (!isset($sent[$m['hash']])) { $todo[] = ['line' => $m['line'], 'sender' => $m['sender'], 'text' => $m['text'], 'device_time' => $m['device_time'], 'seen_at' => $m['seen_at']]; }
            }
        }
        $n = 0;
        do {   // at least one post: it is also the collector's "still here"
            $chunk = array_splice($todo, 0, GSMC_BATCH);
            [$ok, $err] = gsmc_post($cfg, $t, $base + ['messages' => $chunk]);
            if ($ok === null) { gsmc_say($cfg, $t['url'] . ': ' . $err, true); $code = 1; break; }
            foreach ($ok as $h) { if (is_string($h)) { $sent[$h] = 1; } }
            $n += count($chunk);
        } while ($todo);
        $state['sent'][$t['url']] = $sent;
        if ($ok !== null) {
            gsmc_say($cfg, $t['url'] . ': ' . ($msgs === null ? 'WeDo was told the gateway could not be read.'
                : ($checkOnly ? 'WeDo accepted the key.' : ($n ? "$n new text" . ($n === 1 ? '' : 's') . ' sent.' : 'nothing new.'))));
        }
    }

    if (!$checkOnly && $cfg['balance'] && $msgs !== null) { gsmc_balance_due($cfg, $state, false); }
    gsmc_state_save($cfg['state'], $state);
    return $code;
}

/** The daily balance inquiry: once a day per line, after 'at' (or now with $force). */
function gsmc_balance_due(array $cfg, array &$state, bool $force): void
{
    $b = $cfg['balance'];
    if (!$b) { gsmc_say($cfg, "No 'balance_check' in the config: nothing to send.", true); return; }
    if (!$force && date('H:i') < $b['at']) { return; }
    foreach ($cfg['lines'] as $l) {
        if (!$force && ($state['balance'][(string) $l] ?? '') === date('Y-m-d')) { continue; }
        $err = gsmc_send_sms($cfg, $l, $b['number'], $b['text']);
        if ($err === null) {
            $state['balance'][(string) $l] = date('Y-m-d');
            gsmc_say($cfg, "Line $l: sent \"{$b['text']}\" to {$b['number']}; the reply will show in WeDo.");
        } else {
            gsmc_say($cfg, "Line $l: balance inquiry not sent: $err", true);
        }
    }
}

function gsmc_main(array $argv): int
{
    $opts = [];
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $x)) { $opts[$x[1]] = $x[2] ?? true; }
        else { fwrite(STDERR, "Unknown argument: $a (see the top of this file)\n"); return 2; }
    }
    $path = is_string($opts['config'] ?? null) ? $opts['config'] : __DIR__ . DIRECTORY_SEPARATOR . 'gsm-collector.config.php';
    [$cfg, $problem] = gsmc_load_config($path);
    if ($cfg === null) { fwrite(STDERR, $problem . "\n"); return 2; }
    @date_default_timezone_set($cfg['timezone']);

    if (isset($opts['dump'])) {
        $file = is_string($opts['dump']) ? $opts['dump'] : 'gsm-inbox.html';
        [$m, $p] = gsmc_read_inbox($cfg, $raw);
        file_put_contents($file, (string) $raw);
        echo "Saved the gateway's inbox page (" . strlen((string) $raw) . " bytes) to $file\n";
        echo $p !== null ? "Problem: $p\n" : count($m) . " texts recognised.\n";
        echo "It may contain private texts (one-time codes): delete it once it has been looked at.\n";
        return $p !== null ? 1 : 0;
    }
    if (isset($opts['test'])) {
        [$m, $p] = gsmc_read_inbox($cfg);
        if ($p !== null) { fwrite(STDERR, "Problem: $p\n"); return 1; }
        foreach ($m as $x) { printf("line %-2d  %-19s  %-16s  %s\n", $x['line'], $x['device_time'], $x['sender'], str_replace(["\r", "\n"], ' ', $x['text'])); }
        echo count($m) . " texts on the gateway" . ($cfg['lines'] ? ' (lines ' . implode(', ', $cfg['lines']) . ')' : '') . ". Nothing was sent.\n";
        return 0;
    }

    // one run at a time (a slow gateway must not pile up scheduled runs)
    $lock = fopen($cfg['state'] . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { echo "Another run is still going; skipping this one.\n"; return 0; }

    if (isset($opts['balance'])) {
        $state = gsmc_state_load($cfg['state']);
        gsmc_balance_due($cfg, $state, true);
        gsmc_state_save($cfg['state'], $state);
        return 0;
    }
    if (isset($opts['check'])) { return gsmc_poll($cfg, true); }
    if (isset($opts['loop'])) {
        gsmc_say($cfg, "Collector '{$cfg['name']}' started: every {$cfg['interval']} seconds. Ctrl+C stops it.");
        while (true) { gsmc_poll($cfg); sleep($cfg['interval']); }
    }
    return gsmc_poll($cfg);
}

if (!defined('GSMC_LIBRARY_ONLY')) { exit(gsmc_main($argv)); }
