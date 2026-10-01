<?php
/* ==========================================================================
   Query-messages.php  —  JSON API for messages.php
   (includes/messages-lib.php for 1-to-1, includes/msg-groups.php for groups).

   A conversation is addressed by a key: an EmpID (1-to-1) or 'grp:<id>' (group).

   GET  action=threads                        my conversations, 1-to-1 and groups (+ online / typing)
   GET  action=thread&with=KEY[&after=MSID]   messages (marks them read), receipts, first unread,
                                              presence / typing; for groups also members + call in progress
   GET  action=search&term=...                people I can start a conversation with / add to a group
   POST action=send&with=KEY&text=...         send a message
   POST action=typing&with=KEY|''             I'm typing there ('' = stopped)
   POST action=group_create&name=..&members[]=..      new group (me = admin)
   POST action=group_add&id=..&members[]=..           admins
   POST action=group_remove&id=..&member=..           admins
   POST action=group_rename&id=..&name=..             admins
   POST action=group_leave&id=..
   Every POST needs the per-session token; every call marks me active (online status).
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function msg_out($code, array $body) { http_response_code($code); echo json_encode($body, JSON_HEX_TAG | JSON_HEX_AMP); exit; }

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    msg_out(401, ['status' => 'error', 'msg' => 'Your session has expired — please sign in again.']);
}

include 'w_conn.php';
require_once __DIR__ . '/../includes/messages-lib.php';
require_once __DIR__ . '/../includes/msg-groups.php';
require_once __DIR__ . '/../includes/msg-calls.php';

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    msg_out(500, ['status' => 'error', 'msg' => 'Database unavailable.']);
}

$me       = (string) $_SESSION['id'];
$userType = $_SESSION['UserType'] ?? '';
$action   = (string) ($_REQUEST['action'] ?? '');
$with     = trim((string) ($_REQUEST['with'] ?? ''));
$gid      = grp_id_from_key($with);
$today    = date('Y-m-d');
$groupsOn = grp_ready($pdo);

function msg_ids_param(): array
{
    $v = $_POST['members'] ?? [];
    return is_array($v) ? array_map('strval', $v) : [];
}

/* ---------------------------------------------------------------- writes */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(msg_csrf_token(), (string) ($_POST['token'] ?? ''))) {
        msg_out(419, ['status' => 'error', 'msg' => 'This page has expired — reload it and try again.']);
    }
    if (strpos($action, 'group_') === 0 && !$groupsOn) {
        msg_out(503, ['status' => 'error', 'msg' => 'Group chats aren’t set up on this server yet.']);
    }
    $id = (int) ($_POST['id'] ?? 0);

    switch ($action) {
        case 'typing':
            if ($gid) {
                $ok = $groupsOn && grp_member($pdo, $gid, $me);
            } else {
                $ok = $with === '' || msg_can_message($pdo, $me, $with, $userType);
            }
            msg_touch($pdo, $me, $ok ? $with : '');   // only announce typing where I'm allowed to write
            msg_out(200, ['status' => 'ok']);

        case 'send':
            $res = $gid
                ? ($groupsOn ? grp_send($pdo, $gid, $me, (string) ($_POST['text'] ?? '')) : ['ok' => false, 'error' => 'Group chats aren’t set up on this server yet.'])
                : msg_send($pdo, $me, $with, (string) ($_POST['text'] ?? ''), $userType);
            if (!$res['ok']) { msg_out(422, ['status' => 'error', 'msg' => $res['error']]); }
            msg_touch($pdo, $me, '');   // sent = no longer typing
            msg_out(200, ['status' => 'ok', 'message' => $res['message'], 'today' => $today]);

        case 'group_create':
            $res = grp_create($pdo, $me, (string) ($_POST['name'] ?? ''), msg_ids_param(), $userType);
            if (!$res['ok']) { msg_out(422, ['status' => 'error', 'msg' => $res['error']]); }
            msg_out(200, ['status' => 'ok', 'key' => grp_key($res['id'])]);

        case 'group_add':
            $res = grp_add($pdo, $id, $me, msg_ids_param(), $userType);
            break;
        case 'group_remove':
            $res = grp_remove($pdo, $id, $me, (string) ($_POST['member'] ?? ''));
            break;
        case 'group_rename':
            $res = grp_rename($pdo, $id, $me, (string) ($_POST['name'] ?? ''));
            break;
        case 'group_leave':
            $res = grp_leave($pdo, $id, $me);
            break;
        default:
            msg_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
    }
    if (!$res['ok']) { msg_out(422, ['status' => 'error', 'msg' => $res['error']]); }
    msg_out(200, ['status' => 'ok']);
}

/* ---------------------------------------------------------------- reads */
msg_touch($pdo, $me);

switch ($action) {
    case 'threads':
        $threads  = msg_threads($pdo, $me);
        $presence = msg_presence($pdo, $me, array_column($threads, 'id'));
        foreach ($threads as &$t) {
            $p = $presence[$t['id']] ?? null;
            $t['key']    = $t['id'];
            $t['type']   = 'dm';
            $t['online'] = $p ? $p['online'] : false;
            $t['typing'] = $p ? $p['typing'] : false;
        }
        unset($t);
        if ($groupsOn) {
            foreach (grp_threads($pdo, $me) as $g) {
                $typing = grp_typing($pdo, $g['id'], $me);
                $g['online'] = false;
                $g['typing'] = $typing ? $typing[0] : false;   // a first name, shown as "Bea is typing…"
                $threads[] = $g;
            }
            usort($threads, fn($a, $b) => strcmp($b['at'], $a['at']));
        }
        msg_out(200, ['status' => 'ok', 'threads' => $threads, 'groups' => $groupsOn,
                      'online' => msg_online($pdo, $me, $userType),
                      'unread' => msg_unread_threads($pdo, $me), 'today' => $today]);

    case 'thread':
        $after = max(0, (int) ($_GET['after'] ?? 0));
        if ($gid) {
            $g = $groupsOn ? grp_get($pdo, $gid) : null;
            if (!$g || !grp_member($pdo, $gid, $me)) { msg_out(404, ['status' => 'error', 'msg' => 'You’re not in this group.']); }
            $firstUnread = $after === 0 ? grp_first_unread($pdo, $gid, $me) : 0;
            $msgs = grp_messages($pdo, $gid, $me, $after);
            grp_mark_read($pdo, $gid, $me);
            $members = grp_members($pdo, $gid);
            $mine = array_values(array_filter($members, fn($m) => $m['id'] === $me))[0] ?? null;
            $activeCall = null;
            if (call_ready($pdo) && ($c = call_open_in_group($pdo, $gid))) {
                $in = array_values(array_filter(call_public($pdo, $c, $me)['members'], fn($m) => $m['state'] === 'joined'));
                $activeCall = ['id' => (int) $c['id'], 'joined' => array_column($in, 'name'), 'max' => CALL_MAX_PEOPLE,
                               'imIn' => in_array($me, array_column($in, 'id'), true)];
            }
            msg_out(200, ['status' => 'ok', 'kind' => 'group',
                'group'       => ['id' => $gid, 'key' => grp_key($gid), 'name' => $g['name'], 'members' => $members,
                                  'myRole' => $mine['role'] ?? 'member'],
                'messages'    => $msgs,
                'firstUnread' => $firstUnread,
                'readers'     => array_values(array_map(fn($m) => ['id' => $m['id'], 'name' => $m['first'], 'lastRead' => $m['lastRead']],
                                                        array_filter($members, fn($m) => $m['id'] !== $me))),
                'typing'      => grp_typing($pdo, $gid, $me),
                'activeCall'  => $activeCall,
                'canSend'     => true, 'today' => $today]);
        }
        $person = msg_person($pdo, $with);
        if ($person === null || $with === $me) { msg_out(404, ['status' => 'error', 'msg' => 'That employee no longer exists.']); }
        $firstUnread = $after === 0 ? msg_first_unread($pdo, $me, $with) : 0;   // before marking them read
        $msgs        = msg_messages($pdo, $me, $with, $after);
        msg_mark_read($pdo, $me, $with);
        msg_out(200, ['status' => 'ok', 'kind' => 'dm', 'person' => $person, 'messages' => $msgs,
                      'firstUnread' => $firstUnread,
                      'seenUpTo'    => msg_seen_up_to($pdo, $me, $with),
                      'presence'    => msg_presence($pdo, $me, [$with])[$with],
                      'canSend'     => msg_can_message($pdo, $me, $with, $userType), 'today' => $today]);

    case 'search':
        msg_out(200, ['status' => 'ok', 'people' => msg_search($pdo, $me, (string) ($_GET['term'] ?? ''), $userType)]);
}
msg_out(400, ['status' => 'error', 'msg' => 'Unknown action.']);
