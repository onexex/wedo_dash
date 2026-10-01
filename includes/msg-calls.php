<?php
/* =============================================================================
 * includes/msg-calls.php — video calls: 1-to-1, and group calls up to 4 people.
 *
 * Media goes browser-to-browser (WebRTC). In a group call every pair of people
 * has its own connection (a "mesh"), which is why calls stop at 4. The server
 * keeps the call (msg_calls), who's in it (msg_call_members) and relays the
 * set-up messages for each pair (msg_call_signals, addressed sender -> recipient).
 *
 *   call:    ringing --2nd person joins--> active --> ended (end_reason)
 *   member:  invited --> joined --> left
 *                   \--> declined / missed (45 s without answering)
 *
 *   A ringing call ends when the starter gives up (cancelled) or nobody is left
 *   to answer (declined if everyone declined, else missed). An active call ends
 *   when nobody is left, or one person is left with nobody still being rung.
 *   A page that hasn't checked in for 20 s counts as having left.
 *
 * When a call ends a note goes into the conversation — "📹 Missed video call",
 * "📹 Video call · 2m 13s", "📹 Group call · 5m 2s" — as an 'event' message.
 *
 * Who may call: 1-to-1 = whoever may message them (msg_can_message); group =
 * any member, ringing every other member who isn't already on a call.
 * Tables: sql/2026-10-01-add-message-calls.sql (after the groups migration).
 * ========================================================================== */

require_once __DIR__ . '/messages-lib.php';
require_once __DIR__ . '/msg-groups.php';

const CALL_RING_SECS   = 45;     // unanswered for this long = missed
const CALL_STALE_SECS  = 20;     // a page silent this long has left
const CALL_MAX_PEOPLE  = 4;      // mesh limit
const CALL_SIGNAL_MAX  = 20000;  // bytes per signal

function call_ready(PDO $pdo): bool
{
    static $ready = [];
    $k = spl_object_id($pdo);
    if (!isset($ready[$k])) {
        try { $pdo->query("SELECT 1 FROM msg_call_members LIMIT 0"); $ready[$k] = grp_ready($pdo); }
        catch (Throwable $e) { $ready[$k] = false; }
    }
    return $ready[$k];
}

/** STUN (free, public) plus an optional TURN relay from config.local.php: ['turn' => ['url'=>…, 'user'=>…, 'pass'=>…]]. */
function call_ice_servers(): array
{
    $servers = [['urls' => ['stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302']]];
    $cfgFile = dirname(__DIR__) . '/config.local.php';
    $turn = null;
    if (is_file($cfgFile)) {
        $cfg = include $cfgFile;
        if (is_array($cfg) && !empty($cfg['turn']['url'])) { $turn = $cfg['turn']; }
    }
    if ($turn === null && getenv('TURN_URL')) {
        $turn = ['url' => getenv('TURN_URL'), 'user' => getenv('TURN_USER') ?: '', 'pass' => getenv('TURN_PASS') ?: ''];
    }
    if ($turn) {
        $servers[] = ['urls' => $turn['url'], 'username' => (string) ($turn['user'] ?? ''), 'credential' => (string) ($turn['pass'] ?? '')];
    }
    return $servers;
}

function call_get(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare("SELECT * FROM msg_calls WHERE id = :id");
    $st->execute([':id' => $id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** [EmpID => member row] */
function call_members(PDO $pdo, int $id): array
{
    $st = $pdo->prepare("SELECT * FROM msg_call_members WHERE call_id = :id");
    $st->execute([':id' => $id]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[$r['EmpID']] = $r; }
    return $out;
}

function call_set_member(PDO $pdo, int $id, string $emp, string $state, bool $joinedNow = false): void
{
    $now = msg_manila_now();
    if ($joinedNow) {
        $pdo->prepare("INSERT INTO msg_call_members (call_id, EmpID, state, joined_at, ping) VALUES (:c, :e, 'joined', :t, :p)
                       ON DUPLICATE KEY UPDATE state = 'joined', joined_at = VALUES(joined_at), ping = VALUES(ping)")
            ->execute([':c' => $id, ':e' => $emp, ':t' => $now, ':p' => $now]);
    } else {
        $pdo->prepare("UPDATE msg_call_members SET state = :s WHERE call_id = :c AND EmpID = :e")
            ->execute([':s' => $state, ':c' => $id, ':e' => $emp]);
    }
}

/** "2m 13s" / "45s" / "1h 2m" */
function call_duration_text(int $secs): string
{
    if ($secs < 60) { return $secs . 's'; }
    if ($secs < 3600) { return intdiv($secs, 60) . 'm ' . ($secs % 60) . 's'; }
    return intdiv($secs, 3600) . 'h ' . intdiv($secs % 3600, 60) . 'm';
}

/** Close a call (once), tidy its members and leave a note in the conversation. */
function call_finish(PDO $pdo, array $call, string $reason): array
{
    $now = msg_manila_now();
    $st = $pdo->prepare("UPDATE msg_calls SET status = 'ended', end_reason = :r, ended_at = :now WHERE id = :id AND status <> 'ended'");
    $st->execute([':r' => $reason, ':now' => $now, ':id' => $call['id']]);
    if ($st->rowCount() === 0) { return call_get($pdo, (int) $call['id']) ?? $call; }   // already closed elsewhere

    $pdo->prepare("UPDATE msg_call_members SET state = CASE state WHEN 'joined' THEN 'left' WHEN 'invited' THEN 'missed' ELSE state END
                   WHERE call_id = :id")->execute([':id' => $call['id']]);
    $pdo->prepare("DELETE FROM msg_call_signals WHERE call_id = :id")->execute([':id' => $call['id']]);

    $secs = $call['connected_at'] ? max(0, (msg_manila_ts($now) ?? 0) - (msg_manila_ts($call['connected_at']) ?? 0)) : null;
    try {
        if ($call['group_id']) {
            grp_event($pdo, (int) $call['group_id'], $call['starter'],
                $secs !== null ? '📹 Group call · ' . call_duration_text($secs) : '📹 Missed group call');
        } else {
            $others = array_values(array_diff(array_keys(call_members($pdo, (int) $call['id'])), [$call['starter']]));
            if ($others) {
                $text = $secs !== null ? '📹 Video call · ' . call_duration_text($secs)
                      : ($reason === 'declined' ? '📹 Video call declined' : '📹 Missed video call');
                msg_send($pdo, $call['starter'], $others[0], $text, '1', 'event');
            }
        }
    } catch (Throwable $e) { /* the note is a nicety */ }
    return call_get($pdo, (int) $call['id']) ?? $call;
}

/** Apply the time and headcount rules to an open call; returns it as it now stands. */
function call_tick(PDO $pdo, array $call): array
{
    if ($call['status'] === 'ended') { return $call; }
    $id = (int) $call['id'];
    $now = time();
    $ringingOver = $now - (msg_manila_ts($call['created_at']) ?? $now) > CALL_RING_SECS;
    foreach (call_members($pdo, $id) as $emp => $m) {
        if ($m['state'] === 'invited' && $ringingOver) { call_set_member($pdo, $id, $emp, 'missed'); }
        if ($m['state'] === 'joined' && $now - (msg_manila_ts($m['ping']) ?? $now) > CALL_STALE_SECS) { call_set_member($pdo, $id, $emp, 'left'); }
    }
    $members = call_members($pdo, $id);
    $states = array_count_values(array_column($members, 'state'));
    $joined = $states['joined'] ?? 0;
    $invited = $states['invited'] ?? 0;

    if ($call['status'] === 'ringing') {
        if (($members[$call['starter']]['state'] ?? '') !== 'joined') { return call_finish($pdo, $call, 'cancelled'); }
        if ($joined >= 2) {
            $pdo->prepare("UPDATE msg_calls SET status = 'active', connected_at = :t WHERE id = :id")->execute([':t' => msg_manila_now(), ':id' => $id]);
            return call_get($pdo, $id);
        }
        if ($invited === 0) {
            $everyoneDeclined = ($states['declined'] ?? 0) > 0 && ($states['missed'] ?? 0) === 0;
            return call_finish($pdo, $call, $everyoneDeclined ? 'declined' : 'missed');
        }
    } elseif ($joined === 0 || ($joined === 1 && $invited === 0)) {
        return call_finish($pdo, $call, 'ended');
    }
    return $call;
}

/** My open call (I'm being rung or I'm in it), after applying the rules; null if none. */
function call_open_for(PDO $pdo, string $me): ?array
{
    $st = $pdo->prepare("SELECT c.* FROM msg_calls c JOIN msg_call_members m ON m.call_id = c.id
        WHERE m.EmpID = :me AND m.state IN ('invited', 'joined') AND c.status <> 'ended' ORDER BY c.id DESC");
    $st->execute([':me' => $me]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $c = call_tick($pdo, $c);
        if ($c['status'] !== 'ended' && in_array(call_members($pdo, (int) $c['id'])[$me]['state'] ?? '', ['invited', 'joined'], true)) {
            return $c;
        }
    }
    return null;
}

/** The open call in a group, if any (for the "Call in progress · Join" banner). */
function call_open_in_group(PDO $pdo, int $gid): ?array
{
    $st = $pdo->prepare("SELECT * FROM msg_calls WHERE group_id = :g AND status <> 'ended' ORDER BY id DESC LIMIT 1");
    $st->execute([':g' => $gid]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) { return null; }
    $c = call_tick($pdo, $c);
    return $c['status'] === 'ended' ? null : $c;
}

/**
 * Start a call. $target = ['emp' => EmpID] or ['group' => id].
 * Returns ['ok' => true, 'call' => row] or ['ok' => false, 'code' => ..., 'error' => ...].
 */
function call_start(PDO $pdo, string $me, array $target, $userType): array
{
    if (call_open_for($pdo, $me)) { return ['ok' => false, 'code' => 'busy_me', 'error' => 'You’re already in a call.']; }

    $gid = isset($target['group']) ? (int) $target['group'] : null;
    if ($gid) {
        if (!grp_member($pdo, $gid, $me)) { return ['ok' => false, 'code' => 'forbidden', 'error' => 'You’re not in this group.']; }
        if (call_open_in_group($pdo, $gid)) { return ['ok' => false, 'code' => 'in_progress', 'error' => 'A call is already going on in this group — join it instead.']; }
        $invite = array_values(array_filter(array_column(grp_members($pdo, $gid), 'id'),
            fn($id) => $id !== $me && call_open_for($pdo, $id) === null));
        if (!$invite) { return ['ok' => false, 'code' => 'busy', 'error' => 'Everyone else in the group is on another call.']; }
    } else {
        $other = (string) ($target['emp'] ?? '');
        if (!msg_can_message($pdo, $me, $other, $userType)) { return ['ok' => false, 'code' => 'forbidden', 'error' => 'You can’t call this person.']; }
        if (call_open_for($pdo, $other)) { return ['ok' => false, 'code' => 'busy', 'error' => 'They’re on another call right now.']; }
        $invite = [$other];
    }

    $now = msg_manila_now();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO msg_calls (starter, group_id, status, created_at) VALUES (:s, :g, 'ringing', :t)")
            ->execute([':s' => $me, ':g' => $gid, ':t' => $now]);
        $id = (int) $pdo->lastInsertId();
        call_set_member($pdo, $id, $me, 'joined', true);
        $inv = $pdo->prepare("INSERT INTO msg_call_members (call_id, EmpID, state) VALUES (:c, :e, 'invited')");
        foreach ($invite as $emp) { $inv->execute([':c' => $id, ':e' => $emp]); }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['ok' => true, 'call' => call_get($pdo, $id)];
}

/** The call if $me belongs to it (any state), else null. */
function call_for_member(PDO $pdo, int $id, string $me): ?array
{
    $c = call_get($pdo, $id);
    if (!$c) { return null; }
    if (isset(call_members($pdo, $id)[$me])) { return $c; }
    // a group member added after the call started may still join
    return ($c['group_id'] && grp_member($pdo, (int) $c['group_id'], $me)) ? $c : null;
}

/** Join (answer, or join a group call late). */
function call_join(PDO $pdo, array $call, string $me): array
{
    $call = call_tick($pdo, $call);
    if ($call['status'] === 'ended') { return ['ok' => false, 'code' => 'ended', 'error' => 'This call has already ended.', 'call' => $call]; }
    $members = call_members($pdo, (int) $call['id']);
    if (($members[$me]['state'] ?? '') === 'joined') { return ['ok' => true, 'call' => $call]; }
    $other = call_open_for($pdo, $me);
    if ($other && (int) $other['id'] !== (int) $call['id']) { return ['ok' => false, 'code' => 'busy_me', 'error' => 'You’re already in another call.']; }
    $joined = count(array_filter($members, fn($m) => $m['state'] === 'joined'));
    if ($joined >= CALL_MAX_PEOPLE) { return ['ok' => false, 'code' => 'full', 'error' => 'This call is full (' . CALL_MAX_PEOPLE . ' people max).']; }
    call_set_member($pdo, (int) $call['id'], $me, 'joined', true);
    return ['ok' => true, 'call' => call_tick($pdo, call_get($pdo, (int) $call['id']))];
}

/** Decline (while being rung) or leave (while in it). */
function call_leave(PDO $pdo, array $call, string $me): array
{
    if ($call['status'] === 'ended') { return $call; }
    $state = call_members($pdo, (int) $call['id'])[$me]['state'] ?? '';
    if ($state === 'invited') { call_set_member($pdo, (int) $call['id'], $me, 'declined'); }
    elseif ($state === 'joined') { call_set_member($pdo, (int) $call['id'], $me, 'left'); }
    return call_tick($pdo, call_get($pdo, (int) $call['id']));
}

function call_add_signal(PDO $pdo, array $call, string $me, string $to, string $kind, string $payload): array
{
    if (!in_array($kind, ['offer', 'answer', 'ice'], true)) { return ['ok' => false, 'error' => 'Unknown signal.']; }
    if (strlen($payload) > CALL_SIGNAL_MAX || json_decode($payload) === null) { return ['ok' => false, 'error' => 'Bad signal.']; }
    if ($call['status'] === 'ended') { return ['ok' => false, 'error' => 'This call has ended.']; }
    $members = call_members($pdo, (int) $call['id']);
    if (($members[$me]['state'] ?? '') !== 'joined') { return ['ok' => false, 'error' => 'You’re not in this call.']; }
    if ($to === $me || ($members[$to]['state'] ?? '') !== 'joined') { return ['ok' => false, 'error' => 'They’re not in this call.']; }
    $pdo->prepare("INSERT INTO msg_call_signals (call_id, sender, recipient, kind, payload, created_at) VALUES (:c, :s, :r, :k, :p, :t)")
        ->execute([':c' => $call['id'], ':s' => $me, ':r' => $to, ':k' => $kind, ':p' => $payload, ':t' => msg_manila_now()]);
    return ['ok' => true];
}

/** Signals addressed to me after $after. */
function call_signals(PDO $pdo, array $call, string $me, int $after): array
{
    $st = $pdo->prepare("SELECT id, sender, kind, payload FROM msg_call_signals WHERE call_id = :c AND recipient = :me AND id > :a ORDER BY id");
    $st->execute([':c' => $call['id'], ':me' => $me, ':a' => $after]);
    return array_map(fn($r) => ['id' => (int) $r['id'], 'from' => $r['sender'], 'kind' => $r['kind'], 'data' => json_decode($r['payload'], true)],
                     $st->fetchAll(PDO::FETCH_ASSOC));
}

/** I'm still here. */
function call_ping(PDO $pdo, array $call, string $me): void
{
    $pdo->prepare("UPDATE msg_call_members SET ping = :t WHERE call_id = :c AND EmpID = :e AND state = 'joined'")
        ->execute([':t' => msg_manila_now(), ':c' => $call['id'], ':e' => $me]);
}

/** What the browser needs about a call, from $me's point of view. */
function call_public(PDO $pdo, array $call, string $me): array
{
    $people = [];
    foreach (call_members($pdo, (int) $call['id']) as $emp => $m) {
        $p = msg_person($pdo, $emp) ?? ['id' => $emp, 'name' => $emp, 'position' => '', 'photo' => null, 'initials' => '?'];
        $people[] = ['id' => $emp, 'name' => $p['name'], 'photo' => $p['photo'], 'initials' => $p['initials'], 'state' => $m['state']];
    }
    $group = null;
    if ($call['group_id'] && ($g = grp_get($pdo, (int) $call['group_id']))) {
        $group = ['id' => (int) $g['id'], 'key' => grp_key((int) $g['id']), 'name' => $g['name']];
    }
    $starter = msg_person($pdo, $call['starter']);
    return [
        'id'           => (int) $call['id'],
        'status'       => $call['status'],
        'reason'       => $call['end_reason'],
        'group'        => $group,
        'starter'      => $starter ? ['id' => $starter['id'], 'name' => $starter['name'], 'photo' => $starter['photo'], 'initials' => $starter['initials']]
                                   : ['id' => $call['starter'], 'name' => $call['starter'], 'photo' => null, 'initials' => '?'],
        'me'           => $me,
        'members'      => $people,
        'max'          => CALL_MAX_PEOPLE,
        'connectedAgo' => $call['connected_at'] ? max(0, time() - (msg_manila_ts($call['connected_at']) ?? time())) : null,
    ];
}

/** A call ringing for me right now (not one I started), or null. */
function call_incoming(PDO $pdo, string $me): ?array
{
    $c = call_open_for($pdo, $me);
    if (!$c || (call_members($pdo, (int) $c['id'])[$me]['state'] ?? '') !== 'invited') { return null; }
    return call_public($pdo, $c, $me);
}
