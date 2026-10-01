<?php
/* =============================================================================
 * includes/msg-mentions.php — @mentions in group chats.
 *
 * The message text keeps the plain "@Ben Bautista" the sender typed (picked from
 * the member list in the composer). When a group message is sent, the server
 * reads the text and records who it names in msg_mentions — "@everyone" names
 * every other member. The mentioned see the message highlighted and an "@" on
 * the group in their conversation list until they've read it.
 *
 * Used by: includes/msg-groups.php (grp_send / grp_messages / grp_threads)
 * ========================================================================== */

/** Whether the msg_mentions table exists (sql/2026-10-01-add-message-mentions.sql). */
function mn_ready(PDO $pdo): bool
{
    static $ready = [];
    $k = spl_object_id($pdo);
    if (!isset($ready[$k])) {
        try { $pdo->query("SELECT MSID, EmpID FROM msg_mentions LIMIT 0"); $ready[$k] = true; }
        catch (Throwable $e) { $ready[$k] = false; }
    }
    return $ready[$k];
}

/** Whether $text contains "@<name>" as a whole name (any case, not followed by a letter or digit). */
function mn_names_in(string $text, string $name): bool
{
    $name = trim($name);
    if ($name === '') { return false; }
    return (bool) preg_match('/@' . preg_quote($name, '/') . '(?![\p{L}\p{N}])/iu', $text);
}

/** EmpIDs of the members (other than $me) that $text mentions; members = grp_members() cards. */
function mn_find(array $members, string $me, string $text): array
{
    if (strpos($text, '@') === false) { return []; }
    $all = mn_names_in($text, 'everyone');
    $ids = [];
    foreach ($members as $m) {
        if ($m['id'] === $me) { continue; }
        if ($all || mn_names_in($text, $m['name'])) { $ids[] = $m['id']; }
    }
    return $ids;
}

/** Record who message $msid mentions. */
function mn_record(PDO $pdo, int $gid, int $msid, string $me, string $text): array
{
    if (!mn_ready($pdo)) { return []; }
    $ids = mn_find(grp_members($pdo, $gid), $me, $text);
    $ins = $pdo->prepare("INSERT IGNORE INTO msg_mentions (MSID, EmpID) VALUES (:m, :e)");
    foreach ($ids as $id) { $ins->execute([':m' => $msid, ':e' => $id]); }
    return $ids;
}

/** Of these message ids, the ones that mention $me. */
function mn_mentioning(PDO $pdo, string $me, array $msids): array
{
    if (!$msids || !mn_ready($pdo)) { return []; }
    $in = implode(',', array_map('intval', $msids));
    $st = $pdo->prepare("SELECT MSID FROM msg_mentions WHERE EmpID = :me AND MSID IN ($in)");
    $st->execute([':me' => $me]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** How many unread messages in group $gid mention $me. */
function mn_unread(PDO $pdo, string $me, int $gid, int $lastRead): int
{
    if (!mn_ready($pdo)) { return 0; }
    $st = $pdo->prepare("SELECT COUNT(*) FROM msg_mentions mn JOIN messages m ON m.MSID = mn.MSID
                          WHERE mn.EmpID = :me AND m.MHID = :h AND m.MSID > :r");
    $st->execute([':me' => $me, ':h' => grp_key($gid), ':r' => $lastRead]);
    return (int) $st->fetchColumn();
}
