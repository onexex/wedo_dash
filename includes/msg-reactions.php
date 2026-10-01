<?php
/* =============================================================================
 * includes/msg-reactions.php — emoji reactions on messages (1-to-1 and groups).
 *
 * msg_reactions: one row per (message, person). Picking another emoji replaces
 * mine; picking the same one again removes it. Only people in the conversation
 * can react, and never to an event line ("Ramon added Carlo").
 *
 * Used by: query/Query-messages.php (action=react, and `reactions` on action=thread)
 * Needs:   includes/messages-lib.php, includes/msg-groups.php
 * ========================================================================== */

const RX_EMOJI = ['👍', '❤️', '😂', '😮', '😢', '🙏', '🖕'];

/** Whether the msg_reactions table exists (sql/2026-10-01-add-message-reactions.sql). */
function rx_ready(PDO $pdo): bool
{
    static $ready = [];
    $k = spl_object_id($pdo);
    if (!isset($ready[$k])) {
        try { $pdo->query("SELECT MSID, EmpID, Emoji, DateReacted FROM msg_reactions LIMIT 0"); $ready[$k] = true; }
        catch (Throwable $e) { $ready[$k] = false; }
    }
    return $ready[$k];
}

/** The conversation (MHID) of message $msid if $me is in it and it can take a reaction, else null. */
function rx_message_thread(PDO $pdo, string $me, int $msid): ?string
{
    $st = $pdo->prepare("SELECT MHID, " . (msg_has_kind($pdo) ? "Kind" : "'text'") . " AS Kind FROM messages WHERE MSID = :id");
    $st->execute([':id' => $msid]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m || $m['Kind'] === 'event') { return null; }
    $mhid = (string) $m['MHID'];
    $gid = grp_id_from_key($mhid);
    if ($gid) {
        return grp_ready($pdo) && grp_member($pdo, $gid, $me) ? $mhid : null;
    }
    $st = $pdo->prepare("SELECT 1 FROM messageheader WHERE MHID = :h AND (SenderID = :me OR RecieverID = :me2) LIMIT 1");
    $st->execute([':h' => $mhid, ':me' => $me, ':me2' => $me]);
    return $st->fetchColumn() ? $mhid : null;
}

/**
 * Reactions in one conversation, keyed by MSID:
 *   [msid => [['emoji' => '❤️', 'count' => 2, 'mine' => true, 'names' => ['You', 'Bea']], …]]
 * Emoji most used first (ties: first reacted first); in names, "You" leads.
 */
function rx_for_thread(PDO $pdo, string $me, string $mhid, ?int $onlyMsid = null): array
{
    $sql = "SELECT r.MSID, r.EmpID, r.Emoji, e.EmpFN
              FROM msg_reactions r
              JOIN messages m ON m.MSID = r.MSID
              LEFT JOIN employees e ON e.EmpID = r.EmpID
             WHERE m.MHID = :h" . ($onlyMsid ? " AND r.MSID = :id" : "") . "
             ORDER BY r.MSID, r.DateReacted, r.EmpID";
    $st = $pdo->prepare($sql);
    $st->execute($onlyMsid ? [':h' => $mhid, ':id' => $onlyMsid] : [':h' => $mhid]);
    $by = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $id = (int) $r['MSID'];
        $e  = (string) $r['Emoji'];
        $mine = $r['EmpID'] === $me;
        if (!isset($by[$id][$e])) { $by[$id][$e] = ['emoji' => $e, 'count' => 0, 'mine' => false, 'names' => []]; }
        $g = &$by[$id][$e];
        $g['count']++;
        if ($mine) { $g['mine'] = true; array_unshift($g['names'], 'You'); }
        else { $g['names'][] = trim((string) ($r['EmpFN'] ?? '')) ?: (string) $r['EmpID']; }
        unset($g);
    }
    $out = [];
    foreach ($by as $id => $groups) {
        $list = array_values($groups);
        $order = array_flip(array_keys($groups));
        usort($list, fn($a, $b) => $b['count'] <=> $a['count'] ?: $order[$a['emoji']] <=> $order[$b['emoji']]);
        $out[$id] = $list;
    }
    return $out;
}

/**
 * Toggle my reaction on a message: none -> $emoji, same -> removed, other -> replaced.
 * Returns ['ok' => true, 'reactions' => [...this message's groups...]] or ['ok' => false, 'error' => '...'].
 */
function rx_toggle(PDO $pdo, string $me, int $msid, string $emoji): array
{
    if (!in_array($emoji, RX_EMOJI, true)) { return ['ok' => false, 'error' => 'That reaction isn’t available.']; }
    $mhid = rx_message_thread($pdo, $me, $msid);
    if ($mhid === null) { return ['ok' => false, 'error' => 'You can’t react to this message.']; }

    $st = $pdo->prepare("SELECT Emoji FROM msg_reactions WHERE MSID = :id AND EmpID = :me");
    $st->execute([':id' => $msid, ':me' => $me]);
    $current = $st->fetchColumn();
    if ($current === $emoji) {
        $pdo->prepare("DELETE FROM msg_reactions WHERE MSID = :id AND EmpID = :me")->execute([':id' => $msid, ':me' => $me]);
    } else {
        $pdo->prepare("INSERT INTO msg_reactions (MSID, EmpID, Emoji, DateReacted) VALUES (:id, :me, :e, :d)
                       ON DUPLICATE KEY UPDATE Emoji = VALUES(Emoji), DateReacted = VALUES(DateReacted)")
            ->execute([':id' => $msid, ':me' => $me, ':e' => $emoji, ':d' => date('Y-m-d H:i:s')]);
    }
    return ['ok' => true, 'reactions' => rx_for_thread($pdo, $me, $mhid, $msid)[$msid] ?? []];
}
