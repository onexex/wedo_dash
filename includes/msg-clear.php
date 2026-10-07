<?php
/* ==========================================================================
   msg-clear.php  —  "Delete conversation" in Messages, for ME only.

   Like Messenger / Viber: the conversation disappears from my list and its
   history from my view; the other person keeps everything (HR records stay
   intact). A new message brings the conversation back, showing only what came
   after. Anyone may do this to their own conversations.

   Table msg_cleared (sql/2026-10-07-add-message-clear.sql): one row per person
   per conversation (MHID; "grp:<id>" for groups) with the newest message id
   they cleared. Every read path compares message ids against it:
     msg_threads / msg_messages / msg_first_unread / msg_unread_threads
     grp_threads / grp_messages, chat heads (mh_unread_rows)
   For groups the member's last_read is moved forward too, so nothing old
   counts as unread. 1-to-1 messages are NOT marked read: the other person
   must not see a "Seen" that never happened.

   Until the migration is run, mc_ready() is false and nothing changes.
   ========================================================================== */

function mc_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $ready = (bool) $pdo->query("SHOW TABLES LIKE 'msg_cleared'")->fetchColumn();
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/**
 * SQL condition: message $msid (column expression) in conversation $mhid (column
 * expression) is newer than what the person in placeholder $meParam cleared.
 * Always true while the feature isn't set up.
 */
function mc_after_sql(PDO $pdo, string $msid, string $mhid, string $meParam): string
{
    if (!mc_ready($pdo)) { return '1=1'; }
    return "$msid > COALESCE((SELECT c.cleared_msid FROM msg_cleared c WHERE c.EmpID = $meParam AND c.MHID = $mhid), 0)";
}

/** Newest message id I cleared in conversation $mhid (0 = never cleared). */
function mc_cleared(PDO $pdo, string $me, ?string $mhid): int
{
    if ($mhid === null || !mc_ready($pdo)) { return 0; }
    $st = $pdo->prepare("SELECT cleared_msid FROM msg_cleared WHERE EmpID = :me AND MHID = :h");
    $st->execute([':me' => $me, ':h' => $mhid]);
    return (int) $st->fetchColumn();
}

/**
 * Delete conversation $with (an EmpID, or "grp:<id>") for $me.
 * Returns ['ok' => true] or ['ok' => false, 'error' => '...'].
 */
function mc_clear(PDO $pdo, string $me, string $with): array
{
    if (!mc_ready($pdo)) { return ['ok' => false, 'error' => 'Deleting conversations isn’t set up on this server yet.']; }

    $gid = grp_id_from_key($with);
    if ($gid) {
        if (!grp_member($pdo, $gid, $me)) { return ['ok' => false, 'error' => 'You’re not in this group.']; }
        $mhids = [grp_key($gid)];
    } else {
        // every header between the two of us (a pair can have two) is one conversation
        $st = $pdo->prepare("SELECT DISTINCT MHID FROM messageheader
            WHERE (SenderID = :a AND RecieverID = :b) OR (SenderID = :b2 AND RecieverID = :a2)");
        $st->execute([':a' => $me, ':b' => $with, ':b2' => $with, ':a2' => $me]);
        $mhids = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    if (!$mhids) { return ['ok' => false, 'error' => 'There’s no conversation to delete.']; }

    $in  = implode(',', array_fill(0, count($mhids), '?'));
    $max = $pdo->prepare("SELECT MHID, MAX(MSID) AS top FROM messages WHERE MHID IN ($in) GROUP BY MHID");
    $max->execute($mhids);
    $tops = $max->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!$tops) { return ['ok' => false, 'error' => 'There’s no conversation to delete.']; }

    $now = date('Y-m-d H:i:s');
    $up  = $pdo->prepare("INSERT INTO msg_cleared (EmpID, MHID, cleared_msid, cleared_at) VALUES (:e, :h, :m, :t)
                          ON DUPLICATE KEY UPDATE cleared_msid = GREATEST(cleared_msid, VALUES(cleared_msid)), cleared_at = VALUES(cleared_at)");
    foreach ($tops as $mhid => $top) {
        $up->execute([':e' => $me, ':h' => $mhid, ':m' => (int) $top, ':t' => $now]);
    }
    if ($gid) {
        $pdo->prepare("UPDATE msg_group_members SET last_read = GREATEST(last_read, :m) WHERE group_id = :g AND EmpID = :e")
            ->execute([':m' => (int) max($tops), ':g' => $gid, ':e' => $me]);
    }
    return ['ok' => true];
}
