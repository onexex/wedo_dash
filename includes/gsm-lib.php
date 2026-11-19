<?php
/* =============================================================================
 * includes/gsm-lib.php — Management > SIM Inbox.
 *
 * The office GoIP GSM gateway holds the company's SIMs. A collector on an office
 * PC on the gateway's own network (tools/gsm-collector.php, run every minute)
 * reads the gateway's inbox and posts new texts to api/gsm-ingest.php with a
 * secret key. The gateway never has to be reachable from the internet, and it
 * does not matter which of the office's internet lines it is on.
 *
 * Here WeDo:
 *   - keeps every text once (gsm_sms, unique by line + gateway time + sender + text)
 *   - reads each SIM's load balance and expiry from the carrier's texts
 *     (short codes / named senders), and lets a person correct them by hand
 *   - remembers when the collector last checked in (gsm_collectors), so the
 *     page can say when it stopped
 * People holding the `gsminbox` access right (accessrights.gsminbox = 2,
 * sql/2026-11-19-add-gsm-inbox.sql; nobody by default) see it all in gsm.php.
 *
 * The collector's key lives in config.local.php ('gsm' => ['ingest_key' => ...]),
 * never in git or the database. Env WEDO_GSM_KEY wins over the file (tests).
 *
 * Used by: gsm.php (the page), query/gsm-action.php (page reads + edits),
 *          api/gsm-ingest.php (the collector), includes/wd-header.php (menu badge)
 * ========================================================================== */

require_once __DIR__ . '/bot-guard.php';   // wd_local_config()

const GSM_SQL           = 'sql/2026-11-19-add-gsm-inbox.sql';
const GSM_KEY_MIN_LEN   = 32;
const GSM_LINE_MAX      = 32;     // GoIP gateways come with up to 32 slots
const GSM_BATCH_MAX     = 500;    // texts in one collector post
const GSM_TEXT_MAX      = 2000;   // characters kept from one text
const GSM_WARN_DAYS     = 3;      // warn this many days before a load expires
const GSM_STALE_MINUTES = 10;     // collector silent this long = "stopped"

/* ------------------------------------------------------------------ access */

/** Whether the signed-in user holds the `gsminbox` right. False until the migration has run. */
function gsm_can(PDO $pdo, ?string $me = null): bool
{
    $me = $me ?? (string) ($_SESSION['id'] ?? '');
    if ($me === '' || $me === '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT gsminbox FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $me]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;   // column not there yet
    }
}

/** Whether the tables exist (the migration has run on this server). */
function gsm_ready(PDO $pdo): bool
{
    try { $pdo->query("SELECT 1 FROM gsm_sms LIMIT 1"); $pdo->query("SELECT 1 FROM gsm_collectors LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}

/** Per-session token for the page's requests. */
function gsm_csrf_token(): string
{
    if (empty($_SESSION['gsm_csrf'])) { $_SESSION['gsm_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['gsm_csrf'];
}

/* ------------------------------------------------------------------ config */

/** ['key' (the collector's secret, '' = ingest off), 'warn_days', 'stale_minutes']. */
function gsm_config(): array
{
    $c = wd_local_config()['gsm'] ?? [];
    if (!is_array($c)) { $c = []; }
    $key = getenv('WEDO_GSM_KEY');
    $key = trim((string) (($key === false || $key === '') ? ($c['ingest_key'] ?? '') : $key));
    if ($key !== '' && strlen($key) < GSM_KEY_MIN_LEN) {
        error_log('[wedo gsm] ingest_key ignored: shorter than ' . GSM_KEY_MIN_LEN . ' characters');
        $key = '';
    }
    return [
        'key'           => $key,
        'warn_days'     => max(0, min(30, (int) ($c['warn_days'] ?? GSM_WARN_DAYS))),
        'stale_minutes' => max(2, min(1440, (int) ($c['stale_minutes'] ?? GSM_STALE_MINUTES))),
    ];
}

/* ---------------------------------------------------------- reading texts */

/** The same fingerprint the collector uses, so a text is stored once however often it is sent. */
function gsm_hash(int $line, string $deviceTime, string $sender, string $text): string
{
    return sha1($line . "\n" . $deviceTime . "\n" . $sender . "\n" . $text);
}

/**
 * A text from the network rather than a person: a sender with letters in it
 * ("Globe", "GCash") or a short code ("222", "8080"). Phone numbers are people.
 */
function gsm_is_carrier_sender(string $sender): bool
{
    $s = trim($sender);
    if ($s === '') { return false; }
    if (preg_match('/\p{L}/u', $s)) { return true; }
    $digits = preg_replace('/\D/', '', $s);
    return $digits !== '' && strlen($digits) <= 6;
}

/**
 * A date written the ways carriers write them, as Y-m-d, or null.
 * 12/31/2026, 12-31-26, 2026-12-31, Dec 31, 2026, 31 Dec 2026, December 31.
 * Numbers are month first (as in the Philippines) unless the first is over 12.
 * Without a year: the next such date on or after $ref (less a day's slack).
 */
function gsm_parse_date(string $s, ?DateTimeImmutable $ref = null): ?string
{
    $ref = $ref ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
               'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    $mon = '(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?';
    $y = $m = $d = null;

    if (preg_match('/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $s, $x)) {
        [$y, $m, $d] = [(int) $x[1], (int) $x[2], (int) $x[3]];
    } elseif (preg_match('/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4}|\d{2})\b/', $s, $x)) {
        [$m, $d, $y] = [(int) $x[1], (int) $x[2], (int) $x[3]];
        if ($m > 12 && $d <= 12) { [$m, $d] = [$d, $m]; }
    } elseif (preg_match('/\b' . $mon . '\s*(\d{1,2})(?!\d)(?:st|nd|rd|th)?(?:,?\s*(\d{4}))?/i', $s, $x)) {
        [$m, $d, $y] = [$months[strtolower($x[1])], (int) $x[2], isset($x[3]) && $x[3] !== '' ? (int) $x[3] : null];
    } elseif (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+' . $mon . '(?:,?\s*(\d{4}))?/i', $s, $x)) {
        [$d, $m, $y] = [(int) $x[1], $months[strtolower($x[2])], isset($x[3]) && $x[3] !== '' ? (int) $x[3] : null];
    } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\b/', $s, $x)) {
        [$m, $d] = [(int) $x[1], (int) $x[2]];
        if ($m > 12 && $d <= 12) { [$m, $d] = [$d, $m]; }
    } else {
        return null;
    }

    if ($y !== null && $y < 100) { $y += 2000; }
    if ($y === null) {
        $y = (int) $ref->format('Y');
        if (checkdate($m, $d, $y) && sprintf('%04d-%02d-%02d', $y, $m, $d) < $ref->modify('-1 day')->format('Y-m-d')) { $y++; }
    }
    if (!checkdate($m, $d, $y) || $y < 2000 || $y > 2100) { return null; }
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

/**
 * What a carrier text says about the load: ['balance' => float|null, 'expires' => 'Y-m-d'|null].
 * Only what is plainly said: a balance needs the word balance (or "your load")
 * before the amount, an expiry needs valid until / expires / until before a date.
 * Promo texts ("Load P50 and get ...") give nothing.
 */
function gsm_parse_load(string $text, ?DateTimeImmutable $at = null): array
{
    $at  = $at ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $out = ['balance' => null, 'expires' => null];
    $t   = preg_replace('/\s+/u', ' ', $text);

    // keywords in any case; the peso sign / P exactly as carriers write it (a lowercase p is just a letter)
    if (preg_match('/\b(?i:balance|bal\b|remaining load|your load|load wallet)[^0-9₱]{0,30}?(?:PHP|Php|php|P|₱)\s?([0-9][0-9,]*(?:\.[0-9]{1,2})?)/u', $t, $x)
        || preg_match('/(?:PHP|Php|P|₱)\s?([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s+(?i:load\s+)?(?i:balance)\b/u', $t, $x)) {
        $out['balance'] = (float) str_replace(',', '', $x[1]);
    }

    if (preg_match_all('/\b(?:valid\s+(?:until|till|til|thru|through|up\s*to)|expir(?:es|ing|y|ation)?(?:\s+(?:on|date|is))?|until|til|exp\.)\s*[:\-]?\s*(.{0,40})/iu', $t, $all, PREG_SET_ORDER)) {
        foreach ($all as $x) {
            $d = gsm_parse_date($x[1], $at);
            if ($d !== null) { $out['expires'] = $d; break; }
        }
    }
    if ($out['expires'] === null && preg_match('/\bexpir\w*\s+in\s+(\d{1,3})\s+days?\b/iu', $t, $x)) {
        $out['expires'] = $at->modify('+' . (int) $x[1] . ' days')->format('Y-m-d');
    }
    return $out;
}

/* ------------------------------------------------------------------ ingest */

/** Who/what a collector calls itself: plain characters only. */
function gsm_collector_name($raw): string
{
    $n = substr(trim(preg_replace('/[^A-Za-z0-9 ._-]/', '', (string) $raw)), 0, 40);
    return $n !== '' ? $n : 'collector';
}

/**
 * Store what the collector sent. $body:
 *   collector  its name             version  its version
 *   ok         could it read the gateway     error  why not (when ok is false)
 *   lines      the slots it reads (ints)
 *   messages   [{line, sender, text, device_time, seen_at}]
 * Returns [http code, answer]. The answer's `accepted` lists the fingerprints
 * now safely here (new or already stored) so the collector stops resending them.
 */
function gsm_ingest(PDO $pdo, array $body, string $ip): array
{
    $msgs = $body['messages'] ?? [];
    if (!is_array($msgs)) { return [400, ['ok' => false, 'error' => 'bad_request', 'message' => 'messages must be a list.']]; }
    if (count($msgs) > GSM_BATCH_MAX) {
        return [413, ['ok' => false, 'error' => 'too_many', 'message' => 'Send at most ' . GSM_BATCH_MAX . ' texts at a time.']];
    }

    $tz   = new DateTimeZone('Asia/Manila');
    $now  = new DateTimeImmutable('now', $tz);
    $name = gsm_collector_name($body['collector'] ?? '');
    $lines = [];
    foreach ((array) ($body['lines'] ?? []) as $l) {
        if (is_numeric($l) && (int) $l >= 1 && (int) $l <= GSM_LINE_MAX) { $lines[(int) $l] = true; }
    }

    $accepted = []; $stored = 0; $dups = 0; $skipped = 0;
    $ins = $pdo->prepare("INSERT IGNORE INTO gsm_sms (Line, Sender, Body, DeviceTime, ReceivedAt, IsCarrier, Hash, Collector, CreatedAt)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $pdo->beginTransaction();
    try {
        foreach ($msgs as $m) {
            if (!is_array($m)) { $skipped++; continue; }
            $line   = is_numeric($m['line'] ?? null) ? (int) $m['line'] : 0;
            $sender = trim((string) ($m['sender'] ?? ''));
            $text   = (string) ($m['text'] ?? '');
            $dtime  = trim((string) ($m['device_time'] ?? ''));
            if ($line < 1 || $line > GSM_LINE_MAX || ($sender === '' && trim($text) === '')
                || !mb_check_encoding($sender . $text . $dtime, 'UTF-8')) { $skipped++; continue; }
            // the fingerprint is taken from what was sent, before anything is shortened
            $hash   = gsm_hash($line, $dtime, $sender, $text);
            $seen   = gsm_seen_at($m['seen_at'] ?? null, $now);
            $carrier = gsm_is_carrier_sender($sender);
            $ins->execute([$line, mb_substr($sender, 0, 40), mb_substr($text, 0, GSM_TEXT_MAX), $dtime !== '' ? mb_substr($dtime, 0, 30) : null,
                           $seen->format('Y-m-d H:i:s'), $carrier ? 1 : 0, $hash, $name, $now->format('Y-m-d H:i:s')]);
            $accepted[] = $hash;
            $lines[$line] = true;
            if ($ins->rowCount() === 0) { $dups++; continue; }
            $stored++;
            if ($carrier) { gsm_apply_load($pdo, $line, (int) $pdo->lastInsertId(), $text, $seen); }
        }

        if ($lines) {
            $add = $pdo->prepare("INSERT IGNORE INTO gsm_lines (Line) VALUES (?)");
            foreach (array_keys($lines) as $l) { $add->execute([$l]); }
        }

        $ok    = !array_key_exists('ok', $body) || filter_var($body['ok'], FILTER_VALIDATE_BOOLEAN);
        $error = $ok ? null : mb_substr(trim((string) ($body['error'] ?? 'The collector could not read the gateway.')), 0, 255);
        $pdo->prepare("INSERT INTO gsm_collectors (Name, LastSeenAt, LastOkAt, LastError, IP, Version) VALUES (?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE LastSeenAt = VALUES(LastSeenAt), LastOkAt = COALESCE(VALUES(LastOkAt), LastOkAt),
                                               LastError = VALUES(LastError), IP = VALUES(IP), Version = VALUES(Version)")
            ->execute([$name, $now->format('Y-m-d H:i:s'), $ok ? $now->format('Y-m-d H:i:s') : null, $error, substr($ip, 0, 45),
                       substr(preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($body['version'] ?? '')), 0, 20) ?: null]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [200, ['ok' => true, 'stored' => $stored, 'duplicates' => $dups, 'skipped' => $skipped, 'accepted' => $accepted]];
}

/** When the collector first saw a text (its clock), kept only if believable; else now. */
function gsm_seen_at($raw, DateTimeImmutable $now): DateTimeImmutable
{
    if (is_string($raw) && $raw !== '') {
        try {
            $t = (new DateTimeImmutable($raw))->setTimezone($now->getTimezone());
            if ($t <= $now->modify('+5 minutes') && $t >= $now->modify('-30 days')) { return $t; }
        } catch (Throwable $e) { /* unreadable: use now */ }
    }
    return $now;
}

/** A newer carrier text updates the line's balance / expiry; an older one arriving late does not. */
function gsm_apply_load(PDO $pdo, int $line, int $smsId, string $text, DateTimeImmutable $at): void
{
    $p = gsm_parse_load($text, $at);
    $when = $at->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT IGNORE INTO gsm_lines (Line) VALUES (?)")->execute([$line]);
    if ($p['balance'] !== null) {
        $pdo->prepare("UPDATE gsm_lines SET Balance = ?, BalanceAt = ? WHERE Line = ? AND (BalanceAt IS NULL OR BalanceAt <= ?)")
            ->execute([$p['balance'], $when, $line, $when]);
    }
    if ($p['expires'] !== null) {
        $pdo->prepare("UPDATE gsm_lines SET ExpiresOn = ?, ExpiryAt = ?, ExpirySource = 'text', SourceSmsID = ?
                        WHERE Line = ? AND (ExpiryAt IS NULL OR ExpiryAt <= ?)")
            ->execute([$p['expires'], $when, $smsId, $line, $when]);
    }
}

/* ------------------------------------------------------------- the page */

/** Where a load stands: ['state' => ok|soon|expired|unknown, 'days' => int|null, 'text' => ...]. */
function gsm_expiry_state(?string $expiresOn, int $warnDays, ?DateTimeImmutable $today = null): array
{
    if ($expiresOn === null || $expiresOn === '') {
        return ['state' => 'unknown', 'days' => null, 'text' => 'Expiry not known yet'];
    }
    $today = ($today ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->setTime(0, 0);
    $exp   = new DateTimeImmutable($expiresOn, $today->getTimezone());
    $days  = (int) $today->diff($exp)->format('%r%a');
    $date  = $exp->format('M j, Y');
    if ($days < 0)  { return ['state' => 'expired', 'days' => $days, 'text' => 'Expired ' . $date]; }
    if ($days === 0) { return ['state' => 'soon', 'days' => 0, 'text' => 'Expires today']; }
    if ($days <= $warnDays) { return ['state' => 'soon', 'days' => $days, 'text' => 'Expires in ' . $days . ($days === 1 ? ' day' : ' days') . ' (' . $date . ')']; }
    return ['state' => 'ok', 'days' => $days, 'text' => 'Valid until ' . $date];
}

/** Every line in use, with its load and its latest text. */
function gsm_lines_view(PDO $pdo): array
{
    $cfg = gsm_config();
    $rows = $pdo->query("SELECT g.*, (SELECT MAX(s.ReceivedAt) FROM gsm_sms s WHERE s.Line = g.Line) AS LastText,
                                (SELECT COUNT(*) FROM gsm_sms s WHERE s.Line = g.Line) AS Texts
                           FROM gsm_lines g ORDER BY g.Line")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $exp = gsm_expiry_state($r['ExpiresOn'], $cfg['warn_days']);
        $out[] = [
            'line'      => (int) $r['Line'],
            'label'     => (string) $r['Label'],
            'number'    => (string) $r['Number'],
            'balance'   => $r['Balance'] !== null ? (float) $r['Balance'] : null,
            'balanceAt' => $r['BalanceAt'] ? date('M j, g:i A', strtotime($r['BalanceAt'])) : '',
            'expiresOn' => (string) $r['ExpiresOn'],
            'expiry'    => $exp,
            'source'    => (string) $r['ExpirySource'],
            'sourceSms' => $r['SourceSmsID'] !== null ? (int) $r['SourceSmsID'] : null,
            'lastText'  => $r['LastText'] ? date('M j, g:i A', strtotime($r['LastText'])) : '',
            'texts'     => (int) $r['Texts'],
        ];
    }
    return $out;
}

/** Now in Manila, whatever the PHP default time zone (times in these tables are Manila time). */
function gsm_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
}

/** Collectors that have checked in, and whether each still is. */
function gsm_collectors_view(PDO $pdo): array
{
    $cfg = gsm_config();
    $now = gsm_now()->getTimestamp();
    $out = [];
    foreach ($pdo->query("SELECT * FROM gsm_collectors ORDER BY LastSeenAt DESC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $seen = (new DateTimeImmutable($r['LastSeenAt'], new DateTimeZone('Asia/Manila')))->getTimestamp();
        $ago  = max(0, $now - $seen);
        if ($ago > $cfg['stale_minutes'] * 60) {
            $state = 'stale'; $msg = 'Not heard from since ' . date('M j, g:i A', $seen) . '. Check that the office PC is on and the collector task is running.';
        } elseif ($r['LastError'] !== null && $r['LastError'] !== '') {
            $state = 'gateway'; $msg = 'Running, but it cannot read the gateway: ' . $r['LastError'];
        } else {
            $state = 'ok'; $msg = 'Reading the gateway normally.';
        }
        $out[] = ['name' => $r['Name'], 'state' => $state, 'msg' => $msg, 'ago' => $ago,
                  'seen' => date('M j, g:i:s A', $seen), 'version' => (string) $r['Version']];
    }
    return $out;
}

/**
 * Texts, newest first. $f: line (int), q (search), carrier (bool),
 * before (id: older than), after (id: newer than), limit.
 */
function gsm_messages(PDO $pdo, array $f = []): array
{
    $where = []; $args = [];
    if (!empty($f['line'])) { $where[] = 's.Line = ?'; $args[] = (int) $f['line']; }
    if (!empty($f['carrier'])) { $where[] = 's.IsCarrier = 1'; }
    if (!empty($f['before'])) { $where[] = 's.id < ?'; $args[] = (int) $f['before']; }
    if (!empty($f['after'])) { $where[] = 's.id > ?'; $args[] = (int) $f['after']; }
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes(mb_substr($q, 0, 80), '\\%_') . '%';
        $where[] = '(s.Body LIKE ? OR s.Sender LIKE ?)'; $args[] = $like; $args[] = $like;
    }
    $limit = max(1, min(200, (int) ($f['limit'] ?? 50)));
    $st = $pdo->prepare("SELECT s.id, s.Line, s.Sender, s.Body, s.DeviceTime, s.ReceivedAt, s.IsCarrier
                           FROM gsm_sms s" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . "
                          ORDER BY s.id DESC LIMIT $limit");
    $st->execute($args);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'line' => (int) $r['Line'], 'sender' => $r['Sender'], 'text' => $r['Body'],
                  'deviceTime' => (string) $r['DeviceTime'], 'carrier' => (bool) $r['IsCarrier'],
                  'at' => date('M j, Y g:i A', strtotime($r['ReceivedAt']))];
    }
    return $out;
}

/**
 * A person names a line or corrects its load. $expires 'Y-m-d' or '' (not known),
 * $balance a number or '' (not known). Only what changed is touched.
 * Returns ['ok' => bool, 'msg' => ...].
 */
function gsm_save_line(PDO $pdo, string $actor, int $line, string $label, string $number, string $expires, string $balance): array
{
    $st = $pdo->prepare("SELECT * FROM gsm_lines WHERE Line = ?");
    $st->execute([$line]);
    $cur = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cur) { return ['ok' => false, 'msg' => 'That line is not in the list.']; }

    $label  = mb_substr(trim(preg_replace('/\s+/u', ' ', $label)), 0, 60);
    $number = trim($number);
    if ($number !== '' && !preg_match('/^\+?[0-9 -]{3,20}$/', $number)) { return ['ok' => false, 'msg' => 'The SIM number can only have digits, spaces, dashes and a leading +.']; }
    $expires = trim($expires);
    if ($expires !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $expires);
        if (!$d || $d->format('Y-m-d') !== $expires) { return ['ok' => false, 'msg' => 'Pick the expiry date from the calendar.']; }
    }
    $balance = str_replace([',', '₱', 'P', 'p', ' '], '', trim($balance));
    if ($balance !== '' && (!is_numeric($balance) || (float) $balance < 0 || (float) $balance > 99999999)) {
        return ['ok' => false, 'msg' => 'The balance must be an amount, like 25.50.'];
    }

    $now = gsm_now()->format('Y-m-d H:i:s');
    $set = ['Label = ?' => $label !== '' ? $label : null, 'Number = ?' => $number !== '' ? $number : null];
    if ($expires !== (string) $cur['ExpiresOn']) {
        $set += ['ExpiresOn = ?' => $expires !== '' ? $expires : null, 'ExpiryAt = ?' => $now,
                 'ExpirySource = ?' => $expires !== '' ? 'manual' : null, 'SourceSmsID = ?' => null];
    }
    $oldBal = $cur['Balance'] !== null ? (float) $cur['Balance'] : null;
    $newBal = $balance !== '' ? round((float) $balance, 2) : null;
    if ($oldBal !== $newBal) { $set += ['Balance = ?' => $newBal, 'BalanceAt = ?' => $newBal !== null ? $now : null]; }
    $set += ['UpdatedBy = ?' => $actor, 'UpdatedAt = ?' => $now];

    $pdo->prepare("UPDATE gsm_lines SET " . implode(', ', array_keys($set)) . " WHERE Line = ?")
        ->execute(array_merge(array_values($set), [$line]));
    return ['ok' => true, 'msg' => 'Line ' . $line . ' saved.'];
}

/** Menu badge: loads expired or expiring soon, plus collectors that stopped. */
function gsm_menu_count(PDO $pdo): int
{
    try {
        $cfg = gsm_config();
        $soon = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->modify('+' . $cfg['warn_days'] . ' days')->format('Y-m-d');
        $st = $pdo->prepare("SELECT COUNT(*) FROM gsm_lines WHERE ExpiresOn IS NOT NULL AND ExpiresOn <= ?");
        $st->execute([$soon]);
        $n = (int) $st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM gsm_collectors WHERE LastSeenAt < ?");
        $st->execute([gsm_now()->modify('-' . $cfg['stale_minutes'] . ' minutes')->format('Y-m-d H:i:s')]);
        return $n + (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;   // migration not run yet
    }
}
