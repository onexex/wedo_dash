<?php
/* =============================================================================
 * includes/msg-delete.php — deleting your own messages in Messages.
 *
 * Gated by the `msgdel` access right (accessrights.msgdel = 2,
 * sql/2026-10-06-add-message-delete-access-right.sql). Holders can delete
 * messages THEY sent (text, GIF, picture, document) — never someone else's.
 *
 * A deleted message keeps its row and place in the conversation so the chat
 * still reads in order: Kind becomes 'deleted', the text is emptied, its
 * reactions and @mentions go, and a picture/document file is removed from
 * uploads/messages. Everyone sees "This message was deleted" in its place;
 * open conversations learn about it from the `deleted` id list on each poll.
 *
 * Used by: query/Query-messages.php (action=delete, `canDelete`, `deleted`)
 * ========================================================================== */

require_once __DIR__ . '/messages-lib.php';
require_once __DIR__ . '/msg-groups.php';
require_once __DIR__ . '/msg-files.php';

/** Whether $me holds the `msgdel` right. False until the migration has run. */
function md_can_delete(PDO $pdo, string $me): bool
{
    if (!msg_has_kind($pdo)) { return false; }
    try {
        $st = $pdo->prepare("SELECT msgdel FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $me]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;   // column not there yet
    }
}

/** Delete message $msid, which $me sent. Returns ['ok' => true] or ['ok' => false, 'error' => '...']. */
function md_delete(PDO $pdo, string $me, int $msid): array
{
    if (!md_can_delete($pdo, $me)) { return ['ok' => false, 'error' => 'You don’t have access to delete messages.']; }
    $st = $pdo->prepare("SELECT MSID, MHID, SenderID, Message, Kind FROM messages WHERE MSID = :id");
    $st->execute([':id' => $msid]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) { return ['ok' => false, 'error' => 'That message no longer exists.']; }
    if ((string) $m['SenderID'] !== $me) { return ['ok' => false, 'error' => 'You can only delete your own messages.']; }
    if ($m['Kind'] === 'deleted') { return ['ok' => true]; }   // already gone: nothing to do
    if ($m['Kind'] === 'event') { return ['ok' => false, 'error' => 'That can’t be deleted.']; }
    $gid = grp_id_from_key((string) $m['MHID']);
    if ($gid !== null && !grp_member($pdo, $gid, $me)) { return ['ok' => false, 'error' => 'You’re no longer in this group.']; }

    $card = in_array($m['Kind'], ['image', 'file'], true) ? mf_card((string) $m['Message']) : null;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE messages SET Message = '', Kind = 'deleted' WHERE MSID = :id")->execute([':id' => $msid]);
        foreach (['msg_reactions', 'msg_mentions'] as $t) {   // tables from optional migrations
            try { $pdo->prepare("DELETE FROM `$t` WHERE MSID = :id")->execute([':id' => $msid]); }
            catch (PDOException $e) { if ($e->getCode() !== '42S02') { throw $e; } }   // 42S02 = table not there
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    if ($card) { @unlink(mf_dir() . '/' . $card['k']); }   // after the commit: a failed delete keeps the file
    return ['ok' => true];
}

/** IDs of the deleted messages in conversation $mhid (lets open chats update older bubbles). */
function md_deleted_ids(PDO $pdo, ?string $mhid): array
{
    if ($mhid === null || !msg_has_kind($pdo)) { return []; }
    $st = $pdo->prepare("SELECT MSID FROM messages WHERE MHID = :h AND Kind = 'deleted' ORDER BY MSID");
    $st->execute([':h' => $mhid]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}
