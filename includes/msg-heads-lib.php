<?php
/* ==========================================================================
   msg-heads-lib.php — Messenger-style "chat heads": the conversations with
   something unread for me, newest first, for the floating heads on every
   page (includes/msg-heads.php, assets/js/wedo-msg-heads.js).

   The heads ride the ringer's 3-second check-in (query/Query-calls.php
   action=incoming&hs=SIG). The cheap part — which conversations are unread,
   how many, and the newest message id — runs every time and gives a short
   signature; names, photos and previews are only looked up when that
   signature differs from the one the page already has.
   ========================================================================== */
require_once __DIR__ . '/messages-lib.php';
require_once __DIR__ . '/msg-groups.php';

/** How many heads get full details (3 are shown; the rest stand in when some are dismissed). */
const MH_MAX_HEADS = 6;

/**
 * Unread conversations: [['key' => EmpID | 'grp:<id>', 'count' => n, 'last' => newest unread MSID], ...],
 * newest first. Counted the same way as msg_unread_threads().
 */
function mh_unread_rows(PDO $pdo, string $me): array
{
    $rows = [];
    $st = $pdo->prepare("SELECT MAX(IF(h.SenderID = :me1, h.RecieverID, h.SenderID)) AS other,
            COUNT(*) AS cnt, MAX(m.MSID) AS last
        FROM messages m
        JOIN messageheader h ON h.MHID = m.MHID
        WHERE (h.SenderID = :me2 OR h.RecieverID = :me3) AND m.SenderID <> :me4 AND m.Status = 1
        GROUP BY m.MHID");
    $st->execute([':me1' => $me, ':me2' => $me, ':me3' => $me, ':me4' => $me]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $k = (string) $r['other'];
        if (isset($rows[$k])) {          // two headers for the same pair: one conversation
            $rows[$k]['count'] += (int) $r['cnt'];
            $rows[$k]['last'] = max($rows[$k]['last'], (int) $r['last']);
        } else {
            $rows[$k] = ['key' => $k, 'count' => (int) $r['cnt'], 'last' => (int) $r['last']];
        }
    }
    try {   // groups migration may not be applied yet
        $g = $pdo->prepare("SELECT gm.group_id, COUNT(*) AS cnt, MAX(m.MSID) AS last
            FROM msg_group_members gm
            JOIN messages m ON m.MHID = CONCAT('grp:', gm.group_id) AND m.MSID > gm.last_read AND m.SenderID <> :me1
            WHERE gm.EmpID = :me2
            GROUP BY gm.group_id");
        $g->execute([':me1' => $me, ':me2' => $me]);
        foreach ($g->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $k = grp_key((int) $r['group_id']);
            $rows[$k] = ['key' => $k, 'count' => (int) $r['cnt'], 'last' => (int) $r['last']];
        }
    } catch (Throwable $e) { /* no groups yet */ }

    $rows = array_values($rows);
    usort($rows, fn($a, $b) => $b['last'] <=> $a['last']);
    return $rows;
}

/** One line of preview text for a message. */
function mh_preview(string $kind, string $text): string
{
    if ($kind === 'gif') { return 'Sent a GIF'; }
    $t = trim(preg_replace('/\s+/u', ' ', $text));
    return mb_strlen($t) > 90 ? rtrim(mb_substr($t, 0, 89)) . '…' : $t;
}

/** Name, photo and preview for one unread row. */
function mh_head(PDO $pdo, array $row): array
{
    $st = $pdo->prepare("SELECT m.SenderID, m.Message, " . (msg_has_kind($pdo) ? "m.Kind" : "'text'") . " AS Kind, e.EmpFN
        FROM messages m LEFT JOIN employees e ON e.EmpID = m.SenderID WHERE m.MSID = :id");
    $st->execute([':id' => $row['last']]);
    $m = $st->fetch(PDO::FETCH_ASSOC) ?: ['SenderID' => '', 'Message' => '', 'Kind' => 'text', 'EmpFN' => ''];

    $head = $row + ['preview' => mh_preview((string) $m['Kind'], (string) $m['Message']),
                    'from' => $m['Kind'] === 'event' ? '' : trim((string) $m['EmpFN'])];
    $gid = grp_id_from_key($row['key']);
    if ($gid) {
        $g = grp_get($pdo, $gid);
        $name = $g ? (string) $g['name'] : 'Group chat';
        return $head + ['group' => true, 'name' => $name, 'photo' => null,
                        'initials' => mb_strtoupper(mb_substr($name, 0, 2))];
    }
    $p = msg_person($pdo, $row['key']);
    return $head + ['group' => false, 'name' => $p ? $p['name'] : $row['key'],
                    'photo' => $p ? $p['photo'] : null, 'initials' => $p ? $p['initials'] : '?'];
}

/**
 * What the heads need: ['sig' => string, 'total' => unread conversations,
 * 'heads' => details for the newest MH_MAX_HEADS, or null when $known
 * already matches the signature (nothing changed since the page last asked)].
 */
function mh_state(PDO $pdo, string $me, ?string $known = null): array
{
    $rows = mh_unread_rows($pdo, $me);
    $sig  = $rows ? substr(md5(json_encode($rows)), 0, 12) : '0';
    $out  = ['sig' => $sig, 'total' => count($rows), 'heads' => null];
    if ($known !== $sig) {
        $out['heads'] = array_map(fn($r) => mh_head($pdo, $r), array_slice($rows, 0, MH_MAX_HEADS));
    }
    return $out;
}
