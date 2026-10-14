<?php
/* =============================================================================
 * includes/msg-review.php — Management > Message Review (investigations).
 *
 * People holding the `msgaudit` access right (accessrights.msgaudit = 2,
 * sql/2026-10-14-add-message-review.sql; nobody gets it by default) can read
 * EVERY conversation in Messages, 1-to-1 and groups, to look into harassment
 * or misuse. It is strictly read-only: nothing here marks a message read,
 * moves a group read pointer or touches presence, so nobody can tell a
 * conversation was looked at.
 *
 * Deleted messages: md_delete() (includes/msg-delete.php) calls mr_archive()
 * first, so the original text and its picture/document stay available here
 * while the chat itself shows "This message was deleted".
 *
 * Every review action is written to msg_review_log and listed on the page,
 * so reviewers are accountable to each other.
 *
 * Used by: msgreview.php, query/msgreview-action.php, query/msgreview-file.php,
 *          includes/msg-delete.php (mr_archive)
 * ========================================================================== */

require_once __DIR__ . '/messages-lib.php';
require_once __DIR__ . '/msg-groups.php';
require_once __DIR__ . '/msg-files.php';

const MR_PAGE       = 40;    // conversations per list page
const MR_THREAD_MAX = 400;   // messages per transcript page ("Load older" for more)

/** Whether $me holds the `msgaudit` right. False until the migration has run. */
function mr_can_review(PDO $pdo, string $me): bool
{
    if ($me === '' || $me === '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT msgaudit FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $me]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;   // column not there yet
    }
}

/** Per-session token for the page's one write (recording a print). */
function mr_csrf_token(): string
{
    if (empty($_SESSION['mr_csrf'])) { $_SESSION['mr_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['mr_csrf'];
}

/** Whether table $t exists (the archive / log tables come with the migration). */
function mr_table(PDO $pdo, string $t): bool
{
    static $has = [];
    $k = spl_object_id($pdo) . ':' . $t;
    if (!isset($has[$k])) {
        try { $pdo->query("SELECT 1 FROM `$t` LIMIT 0"); $has[$k] = true; }
        catch (Throwable $e) { $has[$k] = false; }
    }
    return $has[$k];
}

/* ------------------------------------------------------------------ archive */

/**
 * Keep a copy of message row $m (MSID, MHID, SenderID, Message, Kind) before its
 * sender deletes it. Call inside md_delete()'s transaction. Returns true when it
 * was archived — the caller must then keep the attachment file on disk.
 */
function mr_archive(PDO $pdo, array $m): bool
{
    if (!mr_table($pdo, 'msg_deleted_archive')) { return false; }
    $card = in_array($m['Kind'], ['image', 'file'], true) ? mf_card((string) $m['Message']) : null;
    $pdo->prepare("INSERT INTO msg_deleted_archive (MSID, MHID, SenderID, Kind, Message, file_key, deleted_at)
                   VALUES (:id, :h, :s, :k, :m, :f, :at)
                   ON DUPLICATE KEY UPDATE MSID = MSID")   // already archived: keep the first copy
        ->execute([':id' => (int) $m['MSID'], ':h' => $m['MHID'], ':s' => $m['SenderID'], ':k' => $m['Kind'],
                   ':m' => (string) $m['Message'], ':f' => $card['k'] ?? null, ':at' => date('Y-m-d H:i:s')]);
    return true;
}

/* ------------------------------------------------------------------ audit log */

/** Record a review action. Best-effort: never blocks the reviewer. */
function mr_log(PDO $pdo, string $me, string $action, ?string $mhid, string $detail = ''): void
{
    if (!mr_table($pdo, 'msg_review_log')) { return; }
    try {
        $pdo->prepare("INSERT INTO msg_review_log (EmpID, action, MHID, detail, logged_at) VALUES (:e, :a, :h, :d, :t)")
            ->execute([':e' => $me, ':a' => $action, ':h' => $mhid, ':d' => mb_substr($detail, 0, 255), ':t' => date('Y-m-d H:i:s')]);
    } catch (Throwable $e) { /* audit is best-effort */ }
}

/** Latest review actions, newest first, with the reviewer's name and a readable conversation title. */
function mr_log_list(PDO $pdo, int $limit = 200): array
{
    if (!mr_table($pdo, 'msg_review_log')) { return []; }
    $st = $pdo->prepare("SELECT l.id, l.EmpID, l.action, l.MHID, l.detail, l.logged_at, e.EmpFN, e.EmpLN
        FROM msg_review_log l LEFT JOIN employees e ON e.EmpID = l.EmpID
        ORDER BY l.id DESC LIMIT " . max(1, min(500, $limit)));
    $st->execute();
    $titles = [];
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $h = $r['MHID'];
        if ($h !== null && !array_key_exists($h, $titles)) {
            $c = mr_conversation($pdo, $h);
            $titles[$h] = $c ? $c['title'] : $h;
        }
        $out[] = [
            'id'     => (int) $r['id'],
            'who'    => trim($r['EmpFN'] . ' ' . $r['EmpLN']) ?: $r['EmpID'],
            'action' => $r['action'],
            'key'    => $h,
            'title'  => $h === null ? '' : $titles[$h],
            'detail' => $r['detail'],
            'at'     => $r['logged_at'],
        ];
    }
    return $out;
}

/* ------------------------------------------------------------------ people */

/** Display card for anyone who ever took part (resigned staff included). Cached per request. */
function mr_person(PDO $pdo, string $id): array
{
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $p = msg_person($pdo, $id);
        $cache[$id] = $p ? ['id' => $p['id'], 'name' => $p['name'] ?: $id, 'position' => $p['position'],
                            'photo' => $p['photo'], 'initials' => $p['initials']]
                         : ['id' => $id, 'name' => $id, 'position' => '', 'photo' => null, 'initials' => '?'];
    }
    return $cache[$id];
}

/** Employees matching a name / ID for the "Involving" filter (everyone, resigned too). */
function mr_people(PDO $pdo, string $term): array
{
    $term = trim($term);
    if (mb_strlen($term) < 2) { return []; }
    $like = '%' . addcslashes($term, '%_\\') . '%';
    $st = $pdo->prepare("SELECT e.EmpID, e.EmpFN, e.EmpLN, e.EmpStatusID, p.PositionDesc
        FROM employees e LEFT JOIN positions p ON p.PSID = e.PosID
        WHERE e.EmpFN LIKE :t1 OR e.EmpLN LIKE :t2 OR CONCAT(e.EmpFN, ' ', e.EmpLN) LIKE :t3
           OR e.EmpID LIKE :t4 OR e.EmployeeIDNumber LIKE :t5
        ORDER BY e.EmpStatusID <> 1, e.EmpLN, e.EmpFN LIMIT 12");
    $st->execute([':t1' => $like, ':t2' => $like, ':t3' => $like, ':t4' => $like, ':t5' => $like]);
    return array_map(fn($r) => [
        'id'       => $r['EmpID'],
        'name'     => trim($r['EmpFN'] . ' ' . $r['EmpLN']) ?: $r['EmpID'],
        'position' => (string) $r['PositionDesc'],
        'active'   => (int) $r['EmpStatusID'] === 1,
    ], $st->fetchAll(PDO::FETCH_ASSOC));
}

/* ------------------------------------------------------------------ conversations */

/**
 * Who/what conversation $mhid is: ['key', 'type' => 'dm'|'group', 'title', 'people' => [cards]]
 * (group: current members, then anyone who posted and has since left), or null if unknown.
 */
function mr_conversation(PDO $pdo, string $mhid): ?array
{
    $gid = grp_id_from_key($mhid);
    if ($gid !== null) {
        if (!grp_ready($pdo)) { return null; }
        $g = grp_get($pdo, $gid);
        $people = [];
        foreach (grp_members($pdo, $gid) as $m) {
            $people[$m['id']] = mr_person($pdo, $m['id']) + ['role' => $m['role'], 'left' => false];
        }
        $st = $pdo->prepare("SELECT DISTINCT SenderID FROM messages WHERE MHID = :h");
        $st->execute([':h' => $mhid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            if (!isset($people[$sid])) { $people[$sid] = mr_person($pdo, $sid) + ['role' => 'member', 'left' => true]; }
        }
        return [
            'key'    => $mhid,
            'type'   => 'group',
            'title'  => $g ? $g['name'] : 'Deleted group #' . $gid,
            'people' => array_values($people),
        ];
    }
    $st = $pdo->prepare("SELECT SenderID, RecieverID FROM messageheader WHERE MHID = :h ORDER BY ID LIMIT 1");
    $st->execute([':h' => $mhid]);
    $h = $st->fetch(PDO::FETCH_ASSOC);
    if (!$h) { return null; }
    $a = mr_person($pdo, (string) $h['SenderID']);
    $b = mr_person($pdo, (string) $h['RecieverID']);
    return ['key' => $mhid, 'type' => 'dm', 'title' => $a['name'] . ' & ' . $b['name'], 'people' => [$a, $b]];
}

/**
 * Conversations for the review list, most recent activity first.
 * $f: emp (EmpID taking part), q (words in a message, deleted originals included),
 *     from / to ('Y-m-d', limits which messages count), type ('dm' | 'group' | ''), page (0-based).
 * Returns ['items' => [...], 'more' => bool].
 */
function mr_conversations(PDO $pdo, array $f): array
{
    $archive = mr_table($pdo, 'msg_deleted_archive');
    $groups  = grp_ready($pdo);
    $where = ["(m.MHID LIKE 'grp:%' OR m.MHID IN (SELECT MHID FROM messageheader))"];   // skip orphans
    if (msg_has_kind($pdo)) { $where[] = "m.Kind <> 'event'"; }
    $params = [];

    $emp = trim((string) ($f['emp'] ?? ''));
    if ($emp !== '') {
        $in = "m.MHID IN (SELECT MHID FROM messageheader WHERE SenderID = :e1 OR RecieverID = :e2)
               OR m.MHID IN (SELECT DISTINCT x.MHID FROM messages x WHERE x.SenderID = :e3)";   // groups they posted in (left ones too)
        $params += [':e1' => $emp, ':e2' => $emp, ':e3' => $emp];
        if ($groups) {
            $in .= " OR m.MHID IN (SELECT CONCAT('grp:', gm.group_id) FROM msg_group_members gm WHERE gm.EmpID = :e4)";
            $params[':e4'] = $emp;
        }
        $where[] = "($in)";
    }
    $type = (string) ($f['type'] ?? '');
    if ($type === 'group') { $where[] = "m.MHID LIKE 'grp:%'"; }
    if ($type === 'dm')    { $where[] = "m.MHID NOT LIKE 'grp:%'"; }
    foreach (['from' => ['>=', ' 00:00:00'], 'to' => ['<=', ' 23:59:59']] as $k => [$op, $time]) {
        $d = (string) ($f[$k] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { $where[] = "m.DateSent $op :$k"; $params[":$k"] = $d . $time; }
    }

    $q = trim((string) ($f['q'] ?? ''));
    $hits = '0';
    $having = '';
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $hits = "SUM(m.Message LIKE :q1" . ($archive ? " OR a.Message LIKE :q2" : '') . ")";
        $params[':q1'] = $like;
        if ($archive) { $params[':q2'] = $like; }
        $having = "HAVING hits > 0";
    }

    $page  = max(0, (int) ($f['page'] ?? 0));
    $sql = "SELECT m.MHID, COUNT(*) AS n, MIN(m.DateSent) AS first_at, MAX(m.DateSent) AS last_at, MAX(m.MSID) AS last_id,
                   " . (msg_has_kind($pdo) ? "SUM(m.Kind = 'deleted')" : '0') . " AS deleted, $hits AS hits
            FROM messages m " . ($archive ? "LEFT JOIN msg_deleted_archive a ON a.MSID = m.MSID " : '') . "
            WHERE " . implode(' AND ', $where) . "
            GROUP BY m.MHID $having
            ORDER BY last_at DESC, last_id DESC
            LIMIT " . (MR_PAGE + 1) . " OFFSET " . ($page * MR_PAGE);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $more = count($rows) > MR_PAGE;

    $out = [];
    foreach (array_slice($rows, 0, MR_PAGE) as $r) {
        $c = mr_conversation($pdo, (string) $r['MHID']);
        if (!$c) { continue; }
        $out[] = $c + [
            'count'   => (int) $r['n'],
            'deleted' => (int) $r['deleted'],
            'hits'    => (int) $r['hits'],
            'first'   => (string) $r['first_at'],
            'last'    => (string) $r['last_at'],
        ];
    }
    return ['items' => $out, 'more' => $more];
}

/**
 * One conversation's messages, oldest first: the newest MR_THREAD_MAX, or the
 * ones before message $before. Deleted messages carry their archived original.
 * Returns ['conversation' => ..., 'messages' => [...], 'older' => bool] or null.
 */
function mr_thread(PDO $pdo, string $mhid, int $before = 0): ?array
{
    $conv = mr_conversation($pdo, $mhid);
    if (!$conv) { return null; }
    $kind = msg_has_kind($pdo) ? "m.Kind" : "'text'";
    $archive = mr_table($pdo, 'msg_deleted_archive');
    $st = $pdo->prepare("SELECT m.MSID, m.SenderID, m.Message, $kind AS Kind, m.DateSent, m.Status"
        . ($archive ? ", a.Kind AS a_kind, a.Message AS a_text, a.file_key AS a_file, a.deleted_at AS a_at" : "") . "
        FROM messages m " . ($archive ? "LEFT JOIN msg_deleted_archive a ON a.MSID = m.MSID " : "") . "
        WHERE m.MHID = :h" . ($before > 0 ? " AND m.MSID < :b" : "") . "
        ORDER BY m.MSID DESC LIMIT " . (MR_THREAD_MAX + 1));
    $st->execute($before > 0 ? [':h' => $mhid, ':b' => $before] : [':h' => $mhid]);
    $rows  = $st->fetchAll(PDO::FETCH_ASSOC);
    $older = count($rows) > MR_THREAD_MAX;
    $rows  = array_reverse(array_slice($rows, 0, MR_THREAD_MAX));

    $reactions = mr_reactions($pdo, array_map(fn($r) => (int) $r['MSID'], $rows));
    $isGroup = $conv['type'] === 'group';
    $msgs = [];
    foreach ($rows as $r) {
        $id = (int) $r['MSID'];
        $m = [
            'id'     => $id,
            'sender' => mr_person($pdo, (string) $r['SenderID']),
            'kind'   => (string) $r['Kind'],
            'text'   => (string) $r['Message'],
            'at'     => (string) $r['DateSent'],
            'read'   => $isGroup ? null : (int) $r['Status'] === 2,
            'reactions' => $reactions[$id] ?? [],
        ];
        if ($m['kind'] === 'deleted') {
            $m['original'] = !empty($r['a_at']) ? [
                'kind' => (string) $r['a_kind'],
                'text' => (string) $r['a_text'],
                'at'   => (string) $r['a_at'],
                'file' => !empty($r['a_file']) && is_file(mf_dir() . '/' . $r['a_file']),
            ] : null;   // deleted before archiving existed
        }
        if (in_array($m['kind'], ['image', 'file'], true)) {
            $card = mf_card($m['text']);
            $m['file'] = $card !== null && is_file(mf_dir() . '/' . $card['k']);
        }
        $msgs[] = $m;
    }
    return ['conversation' => $conv, 'messages' => $msgs, 'older' => $older];
}

/** Reactions on the given messages: [MSID => [['emoji' => '😂', 'name' => 'Ana Cruz'], ...]]. */
function mr_reactions(PDO $pdo, array $ids): array
{
    if (!$ids || !mr_table($pdo, 'msg_reactions')) { return []; }
    $st = $pdo->prepare("SELECT MSID, EmpID, Emoji FROM msg_reactions
                         WHERE MSID IN (" . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY DateReacted");
    $st->execute(array_values($ids));
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['MSID']][] = ['emoji' => $r['Emoji'], 'name' => mr_person($pdo, (string) $r['EmpID'])['name']];
    }
    return $out;
}

/**
 * The picture/document of message $msid for a reviewer — the live one, or the
 * archived original of a deleted message. ['card' => mf_card, 'kind', 'mhid'] or null.
 */
function mr_file(PDO $pdo, int $msid): ?array
{
    if ($msid <= 0 || !msg_has_kind($pdo)) { return null; }
    $st = $pdo->prepare("SELECT MHID, Message, Kind FROM messages WHERE MSID = :id");
    $st->execute([':id' => $msid]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) { return null; }
    if ($m['Kind'] === 'deleted' && mr_table($pdo, 'msg_deleted_archive')) {
        $a = $pdo->prepare("SELECT Kind, Message FROM msg_deleted_archive WHERE MSID = :id");
        $a->execute([':id' => $msid]);
        $m = ($a->fetch(PDO::FETCH_ASSOC) ?: ['Kind' => '', 'Message' => '']) + ['MHID' => $m['MHID']];
    }
    if (!in_array($m['Kind'], ['image', 'file'], true)) { return null; }
    $card = mf_card((string) $m['Message']);
    return $card ? ['card' => $card, 'kind' => $m['Kind'], 'mhid' => (string) $m['MHID']] : null;
}
