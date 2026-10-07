<?php
/* =============================================================================
 * includes/msg-groups.php — group chats in Messages.
 *
 * A group is a msg_groups row with members in msg_group_members (role admin |
 * member). Its messages live in `messages` with MHID 'grp:<id>', so 1-to-1 and
 * group messages share one store; each member's read position is
 * msg_group_members.last_read (newest MSID they have seen).
 *
 * Membership changes leave an 'event' message ("Ramon added Carlo").
 * Admins add / remove people and rename; anyone can leave (the longest-standing
 * member becomes admin if the last admin leaves). You can only add people you
 * could message 1-to-1 (msg_can_message).
 *
 * Tables: sql/2026-10-01-add-message-groups.sql. Used by query/Query-messages.php
 * and includes/msg-calls.php (group calls).
 * ========================================================================== */

require_once __DIR__ . '/messages-lib.php';
require_once __DIR__ . '/msg-mentions.php';

const GRP_NAME_MAX    = 60;
const GRP_MAX_MEMBERS = 50;

function grp_ready(PDO $pdo): bool
{
    static $ready = [];
    $k = spl_object_id($pdo);
    if (!isset($ready[$k])) {
        try { $pdo->query("SELECT 1 FROM msg_group_members LIMIT 0"); $ready[$k] = msg_has_kind($pdo); }
        catch (Throwable $e) { $ready[$k] = false; }
    }
    return $ready[$k];
}

function grp_key(int $id): string { return 'grp:' . $id; }

/** 'grp:12' -> 12, anything else -> null */
function grp_id_from_key(string $key): ?int
{
    return preg_match('/^grp:(\d+)$/', $key, $m) ? (int) $m[1] : null;
}

function grp_get(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT * FROM msg_groups WHERE id = :id");
    $st->execute([':id' => $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** My membership row, or null if I'm not (or no longer) in the group. */
function grp_member(PDO $pdo, int $gid, string $emp): ?array
{
    $st = $pdo->prepare("SELECT * FROM msg_group_members WHERE group_id = :g AND EmpID = :e");
    $st->execute([':g' => $gid, ':e' => $emp]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Members as display cards (admins first, then by name), each with role and read position. */
function grp_members(PDO $pdo, int $gid): array
{
    $st = $pdo->prepare("SELECT gm.EmpID, gm.role, gm.last_read, gm.joined_at, e.EmpFN, e.EmpLN, p.PositionDesc, pr.EmpPPath
        FROM msg_group_members gm
        LEFT JOIN employees e ON e.EmpID = gm.EmpID
        LEFT JOIN positions p ON p.PSID = e.PosID
        LEFT JOIN empprofiles pr ON pr.EmpID = gm.EmpID
        WHERE gm.group_id = :g
        ORDER BY gm.role = 'admin' DESC, e.EmpFN, e.EmpLN");
    $st->execute([':g' => $gid]);
    return array_map(fn($r) => [
        'id'       => $r['EmpID'],
        'name'     => trim($r['EmpFN'] . ' ' . $r['EmpLN']) ?: $r['EmpID'],
        'first'    => trim((string) $r['EmpFN']) ?: $r['EmpID'],
        'position' => (string) $r['PositionDesc'],
        'photo'    => msg_photo($r['EmpPPath']),
        'initials' => msg_initials($r['EmpFN'], $r['EmpLN']),
        'role'     => $r['role'],
        'lastRead' => (int) $r['last_read'],
    ], $st->fetchAll(PDO::FETCH_ASSOC));
}

function grp_first_name(PDO $pdo, string $emp): string
{
    $p = msg_person($pdo, $emp);
    return $p ? (explode(' ', $p['name'])[0] ?: $p['name']) : $emp;
}

/** Store a message in the group; returns the message as the browser sees it. */
function grp_insert(PDO $pdo, int $gid, string $sender, string $text, string $kind = 'text'): array
{
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO messages (MHID, SenderID, Message, Kind, DateSent, Status) VALUES (:h, :s, :m, :k, :d, 1)")
        ->execute([':h' => grp_key($gid), ':s' => $sender, ':m' => $text, ':k' => $kind, ':d' => $now]);
    $id = (int) $pdo->lastInsertId();
    // the sender has obviously seen their own message
    $pdo->prepare("UPDATE msg_group_members SET last_read = GREATEST(last_read, :id) WHERE group_id = :g AND EmpID = :e")
        ->execute([':id' => $id, ':g' => $gid, ':e' => $sender]);
    return ['id' => $id, 'mine' => true, 'text' => $text, 'at' => $now, 'kind' => $kind, 'sender' => $sender];
}

function grp_event(PDO $pdo, int $gid, string $actor, string $text): void
{
    grp_insert($pdo, $gid, $actor, $text, 'event');
}

function grp_clean_name(string $name): string
{
    return trim(preg_replace('/\s+/u', ' ', $name));
}

/** "Ana", "Ana and Ben", "Ana, Ben and 3 others" */
function grp_names_text(array $names): string
{
    $n = count($names);
    if ($n <= 1) { return $names[0] ?? ''; }
    if ($n === 2) { return $names[0] . ' and ' . $names[1]; }
    if ($n === 3) { return $names[0] . ', ' . $names[1] . ' and ' . $names[2]; }
    return $names[0] . ', ' . $names[1] . ' and ' . ($n - 2) . ' others';
}

/**
 * Create a group with me as admin plus $ids (at least 2 other people I may message).
 * Returns ['ok' => true, 'id' => n] or ['ok' => false, 'error' => '...'].
 */
function grp_create(PDO $pdo, string $me, string $name, array $ids, $userType): array
{
    $name = grp_clean_name($name);
    if ($name === '') { return ['ok' => false, 'error' => 'Give the group a name.']; }
    if (mb_strlen($name) > GRP_NAME_MAX) { return ['ok' => false, 'error' => 'Group names are limited to ' . GRP_NAME_MAX . ' characters.']; }
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), fn($i) => $i !== '' && $i !== $me)));
    if (count($ids) < 2) { return ['ok' => false, 'error' => 'Add at least two people — for one person, just message them directly.']; }
    if (count($ids) + 1 > GRP_MAX_MEMBERS) { return ['ok' => false, 'error' => 'Groups are limited to ' . GRP_MAX_MEMBERS . ' people.']; }
    foreach ($ids as $id) {
        if (!msg_can_message($pdo, $me, $id, $userType)) { return ['ok' => false, 'error' => 'You can’t add ' . $id . ' to a group.']; }
    }

    $now = date('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO msg_groups (name, created_by, created_at) VALUES (:n, :b, :d)")
            ->execute([':n' => $name, ':b' => $me, ':d' => $now]);
        $gid = (int) $pdo->lastInsertId();
        $add = $pdo->prepare("INSERT INTO msg_group_members (group_id, EmpID, role, joined_at) VALUES (:g, :e, :r, :d)");
        $add->execute([':g' => $gid, ':e' => $me, ':r' => 'admin', ':d' => $now]);
        foreach ($ids as $id) { $add->execute([':g' => $gid, ':e' => $id, ':r' => 'member', ':d' => $now]); }
        grp_event($pdo, $gid, $me, grp_first_name($pdo, $me) . ' created the group “' . $name . '”');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'id' => $gid];
}

/** My groups for the conversation list (same shape as msg_threads items, plus type/key/members). */
function grp_threads(PDO $pdo, string $me): array
{
    $st = $pdo->prepare("SELECT g.id, g.name, g.created_at, gm.last_read,
            (SELECT COUNT(*) FROM msg_group_members x WHERE x.group_id = g.id) AS members,
            lm.Message AS last_text, lm.SenderID AS last_sender, lm.Kind AS last_kind, lm.DateSent AS last_at,
            le.EmpFN AS last_fn,
            (SELECT COUNT(*) FROM messages u WHERE u.MHID = CONCAT('grp:', g.id) AND u.MSID > gm.last_read AND u.SenderID <> :me1) AS unread
        FROM msg_group_members gm
        JOIN msg_groups g ON g.id = gm.group_id
        LEFT JOIN messages lm ON lm.MSID = (SELECT MAX(m.MSID) FROM messages m WHERE m.MHID = CONCAT('grp:', g.id))
        LEFT JOIN employees le ON le.EmpID = lm.SenderID
        WHERE gm.EmpID = :me2
          AND (lm.MSID IS NULL OR " . mc_after_sql($pdo, 'lm.MSID', "CONCAT('grp:', g.id)", ':meC') . ")");   // deleted by me, nothing new since
    $params = [':me1' => $me, ':me2' => $me];
    if (mc_ready($pdo)) { $params[':meC'] = $me; }
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mine = $r['last_sender'] === $me;
        $out[] = [
            'key'        => grp_key((int) $r['id']),
            'type'       => 'group',
            'id'         => (int) $r['id'],
            'name'       => $r['name'],
            'photo'      => null,
            'initials'   => mb_strtoupper(mb_substr($r['name'], 0, 2)),
            'members'    => (int) $r['members'],
            'last'       => msg_kind_label((string) $r['last_kind'], (string) $r['last_text']) ?? (string) $r['last_text'],
            'lastMine'   => $mine && $r['last_kind'] !== 'event',
            'lastSender' => ($r['last_kind'] === 'event' || $mine) ? '' : trim((string) $r['last_fn']),
            'at'         => (string) ($r['last_at'] ?: $r['created_at']),
            'unread'     => (int) $r['unread'],
            'mentions'   => (int) $r['unread'] > 0 ? mn_unread($pdo, $me, (int) $r['id'], (int) $r['last_read']) : 0,
        ];
    }
    return $out;
}

/** Group messages after $after, oldest first, each with its sender's display card. */
function grp_messages(PDO $pdo, int $gid, string $me, int $after = 0): array
{
    $st = $pdo->prepare("SELECT MSID, SenderID, Message, Kind, DateSent FROM messages WHERE MHID = :h AND MSID > :a ORDER BY MSID");
    $st->execute([':h' => grp_key($gid), ':a' => max($after, mc_cleared($pdo, $me, grp_key($gid)))]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $mentionsMe = array_flip(mn_mentioning($pdo, $me, array_column($rows, 'MSID')));
    $people = [];
    $out = [];
    foreach ($rows as $r) {
        $sid = $r['SenderID'];
        if (!array_key_exists($sid, $people)) {
            $p = msg_person($pdo, $sid);
            $people[$sid] = $p ? ['id' => $p['id'], 'name' => $p['name'], 'photo' => $p['photo'], 'initials' => $p['initials']]
                               : ['id' => $sid, 'name' => $sid, 'photo' => null, 'initials' => '?'];
        }
        $out[] = [
            'id'     => (int) $r['MSID'],
            'mine'   => $sid === $me,
            'text'   => (string) $r['Message'],
            'at'     => (string) $r['DateSent'],
            'kind'   => (string) $r['Kind'],
            'sender' => $people[$sid],
            'mentionsMe' => isset($mentionsMe[(int) $r['MSID']]),
        ];
    }
    return $out;
}

/** Oldest message I haven't seen yet (0 = none). Read BEFORE grp_mark_read(). */
function grp_first_unread(PDO $pdo, int $gid, string $me): int
{
    $st = $pdo->prepare("SELECT COALESCE(MIN(m.MSID), 0) FROM messages m
        JOIN msg_group_members gm ON gm.group_id = :g AND gm.EmpID = :me1
        WHERE m.MHID = :h AND m.MSID > gm.last_read AND m.SenderID <> :me2");
    $st->execute([':g' => $gid, ':me1' => $me, ':h' => grp_key($gid), ':me2' => $me]);
    return (int) $st->fetchColumn();
}

function grp_mark_read(PDO $pdo, int $gid, string $me): void
{
    $pdo->prepare("UPDATE msg_group_members SET last_read = GREATEST(last_read,
                     (SELECT COALESCE(MAX(MSID), 0) FROM messages WHERE MHID = :h))
                   WHERE group_id = :g AND EmpID = :e")
        ->execute([':h' => grp_key($gid), ':g' => $gid, ':e' => $me]);
}

/** First names of members typing in this group right now (needs msg_presence). */
function grp_typing(PDO $pdo, int $gid, string $me): array
{
    try {
        $st = $pdo->prepare("SELECT p.EmpID, p.typing_at, e.EmpFN FROM msg_presence p
            JOIN msg_group_members gm ON gm.group_id = :g AND gm.EmpID = p.EmpID
            LEFT JOIN employees e ON e.EmpID = p.EmpID
            WHERE p.typing_to = :k AND p.EmpID <> :me");
        $st->execute([':g' => $gid, ':k' => grp_key($gid), ':me' => $me]);
        $now = time();
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $t = msg_manila_ts($r['typing_at']);
            if ($t !== null && $now - $t <= MSG_TYPING_SECS) { $out[] = trim((string) $r['EmpFN']) ?: $r['EmpID']; }
        }
        return $out;
    } catch (Throwable $e) { return []; }
}

function grp_send(PDO $pdo, int $gid, string $me, string $text, string $kind = 'text'): array
{
    if (!grp_member($pdo, $gid, $me)) { return ['ok' => false, 'error' => 'You’re no longer in this group.']; }
    $text = trim(str_replace("\r\n", "\n", $text));
    if ($text === '') { return ['ok' => false, 'error' => 'Write a message first.']; }
    if (mb_strlen($text) > MSG_MAX_LEN) { return ['ok' => false, 'error' => 'Messages are limited to ' . MSG_MAX_LEN . ' characters.']; }
    $msg = grp_insert($pdo, $gid, $me, $text, $kind);
    if ($kind === 'text') { mn_record($pdo, $gid, $msg['id'], $me, $text); }   // "@Ben Bautista" / "@everyone"
    return ['ok' => true, 'message' => $msg];
}

/* ------------------------------------------------------------------ managing a group */

function grp_require_admin(PDO $pdo, int $gid, string $me): ?string
{
    $m = grp_member($pdo, $gid, $me);
    if (!$m) { return 'You’re no longer in this group.'; }
    if ($m['role'] !== 'admin') { return 'Only group admins can do that.'; }
    return null;
}

function grp_add(PDO $pdo, int $gid, string $me, array $ids, $userType): array
{
    if ($err = grp_require_admin($pdo, $gid, $me)) { return ['ok' => false, 'error' => $err]; }
    $current = array_column(grp_members($pdo, $gid), 'id');
    $ids = array_values(array_unique(array_filter(array_map('strval', $ids), fn($i) => $i !== '' && !in_array($i, $current, true))));
    if (!$ids) { return ['ok' => false, 'error' => 'Pick someone who isn’t in the group yet.']; }
    if (count($current) + count($ids) > GRP_MAX_MEMBERS) { return ['ok' => false, 'error' => 'Groups are limited to ' . GRP_MAX_MEMBERS . ' people.']; }
    foreach ($ids as $id) {
        if (!msg_can_message($pdo, $me, $id, $userType)) { return ['ok' => false, 'error' => 'You can’t add ' . $id . ' to a group.']; }
    }
    $now = date('Y-m-d H:i:s');
    // new members start "caught up" so the whole history isn't unread for them
    $top = $pdo->prepare("SELECT COALESCE(MAX(MSID), 0) FROM messages WHERE MHID = :h");
    $top->execute([':h' => grp_key($gid)]);
    $last = (int) $top->fetchColumn();
    $add = $pdo->prepare("INSERT INTO msg_group_members (group_id, EmpID, role, joined_at, last_read) VALUES (:g, :e, 'member', :d, :r)");
    foreach ($ids as $id) { $add->execute([':g' => $gid, ':e' => $id, ':d' => $now, ':r' => $last]); }
    grp_event($pdo, $gid, $me, grp_first_name($pdo, $me) . ' added ' . grp_names_text(array_map(fn($i) => grp_first_name($pdo, $i), $ids)));
    return ['ok' => true];
}

function grp_remove(PDO $pdo, int $gid, string $me, string $who): array
{
    if ($err = grp_require_admin($pdo, $gid, $me)) { return ['ok' => false, 'error' => $err]; }
    if ($who === $me) { return ['ok' => false, 'error' => 'Use “Leave group” to leave.']; }
    if (!grp_member($pdo, $gid, $who)) { return ['ok' => false, 'error' => 'They’re not in this group.']; }
    $pdo->prepare("DELETE FROM msg_group_members WHERE group_id = :g AND EmpID = :e")->execute([':g' => $gid, ':e' => $who]);
    grp_event($pdo, $gid, $me, grp_first_name($pdo, $me) . ' removed ' . grp_first_name($pdo, $who));
    return ['ok' => true];
}

function grp_rename(PDO $pdo, int $gid, string $me, string $name): array
{
    if ($err = grp_require_admin($pdo, $gid, $me)) { return ['ok' => false, 'error' => $err]; }
    $name = grp_clean_name($name);
    if ($name === '') { return ['ok' => false, 'error' => 'Give the group a name.']; }
    if (mb_strlen($name) > GRP_NAME_MAX) { return ['ok' => false, 'error' => 'Group names are limited to ' . GRP_NAME_MAX . ' characters.']; }
    $g = grp_get($pdo, $gid);
    if ($g && $g['name'] === $name) { return ['ok' => true]; }
    $pdo->prepare("UPDATE msg_groups SET name = :n WHERE id = :g")->execute([':n' => $name, ':g' => $gid]);
    grp_event($pdo, $gid, $me, grp_first_name($pdo, $me) . ' renamed the group to “' . $name . '”');
    return ['ok' => true];
}

function grp_leave(PDO $pdo, int $gid, string $me): array
{
    $m = grp_member($pdo, $gid, $me);
    if (!$m) { return ['ok' => false, 'error' => 'You’re not in this group.']; }
    grp_event($pdo, $gid, $me, grp_first_name($pdo, $me) . ' left the group');
    $pdo->prepare("DELETE FROM msg_group_members WHERE group_id = :g AND EmpID = :e")->execute([':g' => $gid, ':e' => $me]);
    // never leave a group without an admin: the longest-standing member takes over
    $admins = $pdo->prepare("SELECT COUNT(*) FROM msg_group_members WHERE group_id = :g AND role = 'admin'");
    $admins->execute([':g' => $gid]);
    if ((int) $admins->fetchColumn() === 0) {
        $pdo->prepare("UPDATE msg_group_members SET role = 'admin' WHERE group_id = :g ORDER BY joined_at, EmpID LIMIT 1")
            ->execute([':g' => $gid]);
    }
    return ['ok' => true];
}
