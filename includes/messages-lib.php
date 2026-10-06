<?php
/* =============================================================================
 * includes/messages-lib.php — one-to-one employee messaging.
 *
 * A conversation is one `messageheader` row (MHID "<starter>_<other>", either
 * direction) holding `messages` rows. Status 1 = unread by the recipient,
 * 2 = read. Message text is stored raw and must only ever be rendered as text.
 *
 * Used by: messages.php, query/Query-messages.php, includes/wd-header.php (badge),
 *          e201.php (badge)
 * ========================================================================== */

const MSG_MAX_LEN = 500;   // messages.Message is varchar(500)

/**
 * Whether messages.Kind exists ('text' = normal message, 'event' = a small centred
 * line such as "Ramon added Carlo" or a call note). Added by the groups migration;
 * before it runs every message is plain text.
 */
function msg_has_kind(PDO $pdo): bool
{
    static $has = [];
    $k = spl_object_id($pdo);
    if (!isset($has[$k])) {
        try { $has[$k] = (bool) $pdo->query("SHOW COLUMNS FROM messages LIKE 'Kind'")->fetch(); }
        catch (Throwable $e) { $has[$k] = false; }
    }
    return $has[$k];
}

/** Preview text for a non-text message ('gif' / 'image' / 'file' / 'deleted'), or null for plain text. */
function msg_kind_label(string $kind, string $text): ?string
{
    if ($kind === 'deleted') { return 'Message deleted'; }
    if ($kind === 'gif')   { return 'GIF'; }
    if ($kind === 'image') { return '📷 Photo'; }
    if ($kind === 'file') {
        $c = json_decode($text, true);
        return '📎 ' . (is_array($c) && isset($c['n']) && is_string($c['n']) ? $c['n'] : 'Document');
    }
    return null;
}

function msg_csrf_token(): string
{
    if (empty($_SESSION['msg_csrf'])) { $_SESSION['msg_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['msg_csrf'];
}

/** Profile photo path if the file exists (paths are relative to the app root), else null. */
function msg_photo(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '' || !is_file(dirname(__DIR__) . '/' . $path)) { return null; }
    return $path;
}

function msg_initials(?string $fn, ?string $ln): string
{
    return strtoupper(mb_substr(trim((string) $fn), 0, 1) . mb_substr(trim((string) $ln), 0, 1));
}

/** Display card for one employee, or null when the ID doesn't exist. */
function msg_person(PDO $pdo, string $empId): ?array
{
    $st = $pdo->prepare("SELECT e.EmpID, e.EmpFN, e.EmpLN, p.PositionDesc, pr.EmpPPath
        FROM employees e
        LEFT JOIN positions p ON p.PSID = e.PosID
        LEFT JOIN empprofiles pr ON pr.EmpID = e.EmpID
        WHERE e.EmpID = :id LIMIT 1");
    $st->execute([':id' => $empId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { return null; }
    return [
        'id'       => $r['EmpID'],
        'name'     => trim($r['EmpFN'] . ' ' . $r['EmpLN']),
        'position' => (string) $r['PositionDesc'],
        'photo'    => msg_photo($r['EmpPPath']),
        'initials' => msg_initials($r['EmpFN'], $r['EmpLN']),
    ];
}

/** MHID of the conversation between two people (either direction), or null. */
function msg_thread_id(PDO $pdo, string $me, string $other): ?string
{
    $st = $pdo->prepare("SELECT MHID FROM messageheader
        WHERE (SenderID = :a AND RecieverID = :b) OR (SenderID = :b2 AND RecieverID = :a2)
        ORDER BY ID LIMIT 1");
    $st->execute([':a' => $me, ':b' => $other, ':b2' => $other, ':a2' => $me]);
    $id = $st->fetchColumn();
    return $id === false ? null : (string) $id;
}

/**
 * Whether $me may message $other: anyone they already have a conversation with,
 * their immediate superior / direct reports, anyone in their company, or anyone
 * at all for super users (UserType 1).
 */
function msg_can_message(PDO $pdo, string $me, string $other, $userType): bool
{
    if ($other === '' || $other === $me) { return false; }
    $st = $pdo->prepare("SELECT d.EmpCompID, d.EmpISID FROM employees e
        LEFT JOIN empdetails d ON d.EmpID = e.EmpID WHERE e.EmpID = :id");
    $st->execute([':id' => $other]);
    $them = $st->fetch(PDO::FETCH_ASSOC);
    if (!$them) { return false; }
    if ((string) $userType === '1' || msg_thread_id($pdo, $me, $other) !== null) { return true; }

    $st->execute([':id' => $me]);
    $mine = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return ($them['EmpISID'] ?? '') === $me
        || ($mine['EmpISID'] ?? '') === $other
        || (($mine['EmpCompID'] ?? '') !== '' && ($mine['EmpCompID'] ?? '') === ($them['EmpCompID'] ?? ''));
}

/** My conversations, most recent activity first, each with the last message and my unread count. */
function msg_threads(PDO $pdo, string $me): array
{
    $st = $pdo->prepare("SELECT h.MHID,
            IF(h.SenderID = :me1, h.RecieverID, h.SenderID) AS other,
            lm.Message AS last_text, lm.SenderID AS last_sender, " . (msg_has_kind($pdo) ? "lm.Kind" : "'text'") . " AS last_kind,
            COALESCE(lm.DateSent, h.dateMessage) AS last_at,
            (SELECT COUNT(*) FROM messages u WHERE u.MHID = h.MHID AND u.SenderID <> :me2 AND u.Status = 1) AS unread,
            e.EmpFN, e.EmpLN, pr.EmpPPath
        FROM messageheader h
        LEFT JOIN messages lm ON lm.MSID = (SELECT MAX(m.MSID) FROM messages m WHERE m.MHID = h.MHID)
        LEFT JOIN employees e ON e.EmpID = IF(h.SenderID = :me3, h.RecieverID, h.SenderID)
        LEFT JOIN empprofiles pr ON pr.EmpID = e.EmpID
        WHERE h.SenderID = :me4 OR h.RecieverID = :me5
        ORDER BY last_at DESC, h.ID DESC");
    $st->execute([':me1' => $me, ':me2' => $me, ':me3' => $me, ':me4' => $me, ':me5' => $me]);

    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $name = trim($r['EmpFN'] . ' ' . $r['EmpLN']);
        $out[] = [
            'id'       => $r['other'],
            'name'     => $name !== '' ? $name : $r['other'],
            'photo'    => msg_photo($r['EmpPPath']),
            'initials' => msg_initials($r['EmpFN'], $r['EmpLN']),
            'last'     => msg_kind_label((string) $r['last_kind'], (string) $r['last_text']) ?? (string) $r['last_text'],
            'lastMine' => $r['last_sender'] === $me && $r['last_kind'] !== 'event',
            'at'       => (string) $r['last_at'],
            'unread'   => (int) $r['unread'],
        ];
    }
    return $out;
}

/** Messages with $other, oldest first; only those after message $afterId when given. */
function msg_messages(PDO $pdo, string $me, string $other, int $afterId = 0): array
{
    $mhid = msg_thread_id($pdo, $me, $other);
    if ($mhid === null) { return []; }
    $st = $pdo->prepare("SELECT MSID, SenderID, Message, DateSent, Status, " . (msg_has_kind($pdo) ? "Kind" : "'text'") . " AS Kind FROM messages
        WHERE MHID = :h AND MSID > :after ORDER BY MSID");
    $st->execute([':h' => $mhid, ':after' => $afterId]);
    return array_map(fn($r) => [
        'id'   => (int) $r['MSID'],
        'mine' => $r['SenderID'] === $me,
        'text' => (string) $r['Message'],
        'at'   => (string) $r['DateSent'],
        'read' => (int) $r['Status'] === 2,
        'kind' => (string) $r['Kind'],
    ], $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Mark what $other sent me as read (never my own messages). */
function msg_mark_read(PDO $pdo, string $me, string $other): void
{
    $mhid = msg_thread_id($pdo, $me, $other);
    if ($mhid === null) { return; }
    $pdo->prepare("UPDATE messages SET Status = 2 WHERE MHID = :h AND SenderID <> :me AND Status = 1")
        ->execute([':h' => $mhid, ':me' => $me]);
}

/**
 * Send $text from $me to $other, starting the conversation if needed.
 * Returns ['ok' => true, 'message' => [...]] or ['ok' => false, 'error' => '...'].
 */
// Not "msg_send": that name is a built-in PHP function (sysvmsg extension) on Linux servers.
function msg_send_dm(PDO $pdo, string $me, string $other, string $text, $userType, string $kind = 'text'): array
{
    $text = trim(str_replace("\r\n", "\n", $text));
    if ($text === '') { return ['ok' => false, 'error' => 'Write a message first.']; }
    if (mb_strlen($text) > MSG_MAX_LEN) { return ['ok' => false, 'error' => 'Messages are limited to ' . MSG_MAX_LEN . ' characters.']; }
    if (!msg_can_message($pdo, $me, $other, $userType)) { return ['ok' => false, 'error' => 'You can’t message this person.']; }

    $now  = date('Y-m-d H:i:s');
    $mhid = msg_thread_id($pdo, $me, $other);
    $pdo->beginTransaction();
    try {
        if ($mhid === null) {
            $mhid = $me . '_' . $other;
            $pdo->prepare("INSERT INTO messageheader (MHID, SenderID, RecieverID, dateMessage) VALUES (:h, :s, :r, :d)")
                ->execute([':h' => $mhid, ':s' => $me, ':r' => $other, ':d' => $now]);
        }
        if (msg_has_kind($pdo)) {
            $pdo->prepare("INSERT INTO messages (MHID, SenderID, Message, Kind, DateSent, Status) VALUES (:h, :s, :m, :k, :d, 1)")
                ->execute([':h' => $mhid, ':s' => $me, ':m' => $text, ':k' => $kind, ':d' => $now]);
        } else {
            $pdo->prepare("INSERT INTO messages (MHID, SenderID, Message, DateSent, Status) VALUES (:h, :s, :m, :d, 1)")
                ->execute([':h' => $mhid, ':s' => $me, ':m' => $text, ':d' => $now]);
        }
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'message' => ['id' => $id, 'mine' => true, 'text' => $text, 'at' => $now, 'read' => false, 'kind' => $kind]];
}

/** Newest message of mine that $other has read (0 = none yet). Drives the "Seen" receipt. */
function msg_seen_up_to(PDO $pdo, string $me, string $other): int
{
    $mhid = msg_thread_id($pdo, $me, $other);
    if ($mhid === null) { return 0; }
    $st = $pdo->prepare("SELECT COALESCE(MAX(MSID), 0) FROM messages WHERE MHID = :h AND SenderID = :me AND Status = 2");
    $st->execute([':h' => $mhid, ':me' => $me]);
    return (int) $st->fetchColumn();
}

/** Oldest message from $other I haven't read yet (0 = none). Read BEFORE msg_mark_read() for the "New messages" line. */
function msg_first_unread(PDO $pdo, string $me, string $other): int
{
    $mhid = msg_thread_id($pdo, $me, $other);
    if ($mhid === null) { return 0; }
    $st = $pdo->prepare("SELECT COALESCE(MIN(MSID), 0) FROM messages WHERE MHID = :h AND SenderID <> :me AND Status = 1");
    $st->execute([':h' => $mhid, ':me' => $me]);
    return (int) $st->fetchColumn();
}

/* ------------------------------------------------------------------ presence
   Online status and "typing…" (table msg_presence, sql/2026-10-01-add-message-presence.sql).
   Best-effort: if the table isn't there yet these do nothing / report nothing. */

const MSG_ONLINE_SECS = 60;   // seen within this many seconds = "Active now"
const MSG_TYPING_SECS = 6;    // a typing ping stays valid this long

/** Presence times are stored as Manila wall-clock time no matter how the caller set PHP's timezone. */
function msg_manila_now(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
}
function msg_manila_ts(?string $s): ?int
{
    if ($s === null || $s === '') { return null; }
    try { return (new DateTime($s, new DateTimeZone('Asia/Manila')))->getTimestamp(); } catch (Throwable $e) { return null; }
}

/**
 * I'm here (Messages is open). $typingTo: null = leave typing as is,
 * '' = stopped typing, an EmpID = typing to them right now.
 */
function msg_touch(PDO $pdo, string $me, ?string $typingTo = null): void
{
    $now = msg_manila_now();
    try {
        if ($typingTo === null) {
            $pdo->prepare("INSERT INTO msg_presence (EmpID, last_seen) VALUES (:me, :now)
                           ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)")
                ->execute([':me' => $me, ':now' => $now]);
        } else {
            $to = $typingTo === '' ? null : $typingTo;
            $pdo->prepare("INSERT INTO msg_presence (EmpID, last_seen, typing_to, typing_at) VALUES (:me, :now, :to, :at)
                           ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen), typing_to = VALUES(typing_to), typing_at = VALUES(typing_at)")
                ->execute([':me' => $me, ':now' => $now, ':to' => $to, ':at' => $to === null ? null : $now]);
        }
    } catch (Throwable $e) { /* presence is optional */ }
}

/**
 * Presence of some people as seen by $me:
 * [EmpID => ['online' => bool, 'lastSeen' => 'Y-m-d H:i:s'|null, 'ago' => seconds|null, 'typing' => bool]].
 * Typing is only reported when they are typing to $me.
 */
function msg_presence(PDO $pdo, string $me, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), 'strlen')));
    $out = [];
    foreach ($ids as $id) { $out[$id] = ['online' => false, 'lastSeen' => null, 'ago' => null, 'typing' => false]; }
    if (!$ids) { return $out; }
    try {
        $st = $pdo->prepare("SELECT EmpID, last_seen, typing_to, typing_at FROM msg_presence
                             WHERE EmpID IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        $st->execute($ids);
        $now = time();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $seen  = msg_manila_ts($r['last_seen']);
            $typed = msg_manila_ts($r['typing_at']);
            $out[$r['EmpID']] = [
                'online'   => $seen !== null && $now - $seen <= MSG_ONLINE_SECS,
                'lastSeen' => $r['last_seen'],
                'ago'      => $seen === null ? null : max(0, $now - $seen),   // seconds since last active
                'typing'   => $r['typing_to'] === $me && $typed !== null && $now - $typed <= MSG_TYPING_SECS,
            ];
        }
    } catch (Throwable $e) { /* table not there yet: nobody shows as online */ }
    return $out;
}

/**
 * Colleagues online right now (seen on any WeDo page in the last minute), as display cards,
 * limited to people I could message: everyone for super users, otherwise my company,
 * my superior, my direct reports and anyone I already have a conversation with.
 * Returns null when presence isn't set up (migration not applied).
 */
function msg_online(PDO $pdo, string $me, $userType): ?array
{
    $since = (new DateTime('now', new DateTimeZone('Asia/Manila')))->modify('-' . MSG_ONLINE_SECS . ' seconds')->format('Y-m-d H:i:s');
    $sql = "SELECT e.EmpID, e.EmpFN, e.EmpLN, p.PositionDesc, pr.EmpPPath
        FROM msg_presence mp
        JOIN employees e ON e.EmpID = mp.EmpID
        JOIN empdetails d ON d.EmpID = e.EmpID
        LEFT JOIN positions p ON p.PSID = e.PosID
        LEFT JOIN empprofiles pr ON pr.EmpID = e.EmpID
        WHERE mp.last_seen >= :since AND e.EmpID <> :me AND e.EmpStatusID = 1";
    $params = [':since' => $since, ':me' => $me];
    if ((string) $userType !== '1') {
        $sql .= " AND (d.EmpCompID = (SELECT EmpCompID FROM empdetails WHERE EmpID = :me2)
                       OR d.EmpISID = :me3
                       OR e.EmpID = (SELECT EmpISID FROM empdetails WHERE EmpID = :me4)
                       OR EXISTS (SELECT 1 FROM messageheader h
                                  WHERE (h.SenderID = :me5 AND h.RecieverID = e.EmpID) OR (h.RecieverID = :me6 AND h.SenderID = e.EmpID)))";
        $params += [':me2' => $me, ':me3' => $me, ':me4' => $me, ':me5' => $me, ':me6' => $me];
    }
    $sql .= " ORDER BY e.EmpFN, e.EmpLN LIMIT 60";
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
    } catch (Throwable $e) {
        return null;   // msg_presence not there yet
    }
    return array_map(fn($r) => [
        'id'       => $r['EmpID'],
        'name'     => trim($r['EmpFN'] . ' ' . $r['EmpLN']),
        'first'    => trim((string) $r['EmpFN']) ?: $r['EmpID'],
        'position' => (string) $r['PositionDesc'],
        'photo'    => msg_photo($r['EmpPPath']),
        'initials' => msg_initials($r['EmpFN'], $r['EmpLN']),
    ], $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Number of conversations with something unread for me. */
function msg_unread_threads(PDO $pdo, string $me): int
{
    $st = $pdo->prepare("SELECT COUNT(DISTINCT m.MHID) FROM messages m
        JOIN messageheader h ON h.MHID = m.MHID
        WHERE (h.SenderID = :me1 OR h.RecieverID = :me2) AND m.SenderID <> :me3 AND m.Status = 1");
    $st->execute([':me1' => $me, ':me2' => $me, ':me3' => $me]);
    $n = (int) $st->fetchColumn();
    try {   // + groups with something new for me (groups migration may not be applied yet)
        $g = $pdo->prepare("SELECT COUNT(*) FROM msg_group_members gm
            WHERE gm.EmpID = :me1 AND EXISTS (SELECT 1 FROM messages m
                WHERE m.MHID = CONCAT('grp:', gm.group_id) AND m.MSID > gm.last_read AND m.SenderID <> :me2)");
        $g->execute([':me1' => $me, ':me2' => $me]);
        $n += (int) $g->fetchColumn();
    } catch (Throwable $e) { /* no groups yet */ }
    return $n;
}

/** People I may start a conversation with, matching name / employee ID. Excludes me and anyone no longer employed (EmpStatusID <> 1). */
function msg_search(PDO $pdo, string $me, string $term, $userType): array
{
    $term = trim($term);
    if (mb_strlen($term) < 2) { return []; }
    $like = '%' . addcslashes($term, '%_\\') . '%';

    $sql = "SELECT e.EmpID, e.EmpFN, e.EmpLN, p.PositionDesc, pr.EmpPPath
        FROM employees e
        JOIN empdetails d ON d.EmpID = e.EmpID
        LEFT JOIN positions p ON p.PSID = e.PosID
        LEFT JOIN empprofiles pr ON pr.EmpID = e.EmpID
        WHERE e.EmpID <> :me
          AND e.EmpStatusID = 1   -- employed; EmpDateResigned is not a reliable flag (active staff carry dates there, see alas.php)
          AND (e.EmpFN LIKE :t1 OR e.EmpLN LIKE :t2 OR CONCAT(e.EmpFN, ' ', e.EmpLN) LIKE :t3
               OR e.EmpID LIKE :t4 OR e.EmployeeIDNumber LIKE :t5)";
    $params = [':me' => $me, ':t1' => $like, ':t2' => $like, ':t3' => $like, ':t4' => $like, ':t5' => $like];
    if ((string) $userType !== '1') {
        $sql .= " AND (d.EmpCompID = (SELECT EmpCompID FROM empdetails WHERE EmpID = :me2)
                       OR d.EmpISID = :me3
                       OR e.EmpID = (SELECT EmpISID FROM empdetails WHERE EmpID = :me4))";
        $params += [':me2' => $me, ':me3' => $me, ':me4' => $me];
    }
    $sql .= " ORDER BY e.EmpLN, e.EmpFN LIMIT 15";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return array_map(fn($r) => [
        'id'       => $r['EmpID'],
        'name'     => trim($r['EmpFN'] . ' ' . $r['EmpLN']),
        'position' => (string) $r['PositionDesc'],
        'photo'    => msg_photo($r['EmpPPath']),
        'initials' => msg_initials($r['EmpFN'], $r['EmpLN']),
    ], $st->fetchAll(PDO::FETCH_ASSOC));
}
