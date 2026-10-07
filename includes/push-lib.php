<?php
/* ==========================================================================
   push-lib.php  —  mobile push notifications for the WeDo app
   (Firebase Cloud Messaging, HTTP v1 API).

   Phones register their FCM token via query/Query-devicetoken.php (table
   push_devices, sql/2026-10-07-add-push-devices.sql). Feature code calls one of
   the push_* event helpers at the bottom of this file right after its own
   write has succeeded, e.g.

       push_request_decided($pdo, 'HL', $applicantId, 4, $me);

   Credentials: the Firebase service-account key (JSON) is read from the path in
   env FCM_KEY_FILE, or 'fcm_key' in the untracked config.local.php. Keep the
   file OUTSIDE the web root. With no key configured every call is a silent
   no-op, so this is safe to deploy before push is set up.

   Pushing must never break the action that triggered it: every public function
   swallows its own errors (logged with error_log) and returns quietly.
   ========================================================================== */

const PUSH_SCOPE   = 'https://www.googleapis.com/auth/firebase.messaging';
const PUSH_TIMEOUT = 6;   // seconds per HTTP call; sends to several phones run in parallel

/* ---------- configuration / credentials ---------- */

/** Parsed service-account key, or null when push is not configured. */
function push_credentials(): ?array
{
    static $cred = false;
    if ($cred !== false) { return $cred; }
    $cred = null;

    $file = getenv('FCM_KEY_FILE') ?: '';
    if ($file === '') {
        $local = __DIR__ . '/../config.local.php';
        if (is_file($local)) {
            $cfg = require $local;
            if (is_array($cfg) && !empty($cfg['fcm_key'])) { $file = (string) $cfg['fcm_key']; }
        }
    }
    if ($file === '' || !is_readable($file)) { return $cred; }

    $json = json_decode((string) file_get_contents($file), true);
    if (is_array($json) && !empty($json['client_email']) && !empty($json['private_key']) && !empty($json['project_id'])) {
        $cred = $json;
    } else {
        error_log('[wedo push] FCM key file is not a valid service-account JSON: ' . $file);
    }
    return $cred;
}

function push_enabled(): bool { return push_credentials() !== null; }

/** OAuth access token for FCM, cached on disk for ~55 minutes. */
function push_access_token(array $cred): ?string
{
    $cache = sys_get_temp_dir() . '/wedo-fcm-' . md5($cred['client_email']) . '.json';
    $hit = is_file($cache) ? json_decode((string) @file_get_contents($cache), true) : null;
    if (is_array($hit) && ($hit['exp'] ?? 0) > time() + 60) { return $hit['token']; }

    $b64 = function (string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); };
    $now = time();
    $aud = $cred['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.'
         . $b64(json_encode(['iss' => $cred['client_email'], 'scope' => PUSH_SCOPE, 'aud' => $aud,
                             'iat' => $now, 'exp' => $now + 3600]));
    if (!openssl_sign($jwt, $sig, $cred['private_key'], OPENSSL_ALGO_SHA256)) {
        error_log('[wedo push] could not sign the FCM auth request (bad private key?)');
        return null;
    }
    $jwt .= '.' . $b64($sig);

    $ch = curl_init($aud);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => PUSH_TIMEOUT,
        CURLOPT_TIMEOUT => PUSH_TIMEOUT,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $body = json_decode((string) $res, true);
    if ($code !== 200 || empty($body['access_token'])) {
        error_log('[wedo push] FCM auth failed (HTTP ' . $code . '): ' . substr((string) $res, 0, 300));
        return null;
    }
    @file_put_contents($cache, json_encode(['token' => $body['access_token'], 'exp' => $now + (int) ($body['expires_in'] ?? 3600)]), LOCK_EX);
    @chmod($cache, 0600);
    return $body['access_token'];
}

/* ---------- device registry ---------- */

function push_register(PDO $pdo, string $empId, string $token, string $platform = 'android'): void
{
    $platform = $platform === 'ios' ? 'ios' : 'android';
    $st = $pdo->prepare("INSERT INTO push_devices (EmpID, token, platform, created_at, last_seen)
                         VALUES (:e, :t, :p, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE EmpID = VALUES(EmpID), platform = VALUES(platform), last_seen = NOW()");
    $st->execute([':e' => $empId, ':t' => $token, ':p' => $platform]);
}

function push_unregister(PDO $pdo, string $empId, string $token): void
{
    $pdo->prepare("DELETE FROM push_devices WHERE token = :t AND EmpID = :e")->execute([':t' => $token, ':e' => $empId]);
}

/* ---------- sending ---------- */

/**
 * Send one notification to every registered phone of the given employee(s).
 *
 * $data keys (all optional): url   page to open when tapped, relative to the site root
 *                            kind  'call' uses the high-priority calls channel
 *                            tag   replaces an earlier notification with the same tag
 */
function push_send(PDO $pdo, $empIds, string $title, string $body, array $data = []): void
{
    try {
        $cred = push_credentials();
        $ids  = array_values(array_unique(array_filter(array_map('strval', (array) $empIds), 'strlen')));
        if (!$cred || !$ids) { return; }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT token FROM push_devices WHERE EmpID IN ($in)");
        $st->execute($ids);
        $tokens = $st->fetchAll(PDO::FETCH_COLUMN);
        if (!$tokens) { return; }

        $access = push_access_token($cred);
        if (!$access) { return; }

        $isCall  = ($data['kind'] ?? '') === 'call';
        $channel = $isCall ? 'wedo_calls' : 'wedo';
        $title   = mb_substr($title, 0, 100);
        $body    = mb_substr($body, 0, 240);
        $payload = array_map('strval', $data + ['url' => 'notifications.php']);   // FCM data values must be strings

        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($cred['project_id']) . '/messages:send';
        $mh = curl_multi_init();
        $handles = [];
        foreach ($tokens as $token) {
            $android = ['priority' => 'HIGH', 'notification' => ['channel_id' => $channel, 'sound' => 'default']];
            if (!empty($data['tag'])) { $android['notification']['tag'] = (string) $data['tag']; }
            if ($isCall) { $android['ttl'] = '30s'; }
            $msg = ['message' => [
                'token'        => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data'         => $payload,
                'android'      => $android,
                'apns'         => ['payload' => ['aps' => ['sound' => 'default']]],
            ]];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access, 'Content-Type: application/json; charset=utf-8'],
                CURLOPT_POSTFIELDS => json_encode($msg, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => PUSH_TIMEOUT,
                CURLOPT_TIMEOUT => PUSH_TIMEOUT,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[] = [$ch, $token];
        }
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) { curl_multi_select($mh, 1.0); }
        } while ($running && $status === CURLM_OK);

        $dead = [];
        foreach ($handles as [$ch, $token]) {
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            if ($code !== 200) {
                $res = (string) curl_multi_getcontent($ch);
                // uninstalled app / token rotated: forget it so we stop trying
                if ($code === 404 || strpos($res, 'UNREGISTERED') !== false
                    || ($code === 400 && strpos($res, 'registration token') !== false)) {
                    $dead[] = $token;
                } else {
                    error_log('[wedo push] send failed (HTTP ' . $code . '): ' . substr($res, 0, 300));
                }
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        if ($dead) {
            $in = implode(',', array_fill(0, count($dead), '?'));
            $pdo->prepare("DELETE FROM push_devices WHERE token IN ($in)")->execute($dead);
        }
    } catch (Throwable $e) {
        error_log('[wedo push] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}

/* ---------- helpers for the event functions ---------- */

/** "First Last" for an EmpID (falls back to the id). */
function push_name(PDO $pdo, string $empId): string
{
    try {
        $st = $pdo->prepare("SELECT EmpFN, EmpLN FROM employees WHERE EmpID = :id LIMIT 1");
        $st->execute([':id' => $empId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $n = $r ? trim(ucwords(strtolower(trim($r['EmpFN'] . ' ' . $r['EmpLN'])))) : '';
        return $n !== '' ? $n : $empId;
    } catch (Throwable $e) {
        return $empId;
    }
}

/** HR / super users (role 1) of the applicant's company. */
function push_hr_of(PDO $pdo, string $empId): array
{
    try {
        $st = $pdo->prepare("SELECT h.EmpID FROM empdetails h
                             JOIN empdetails a ON a.EmpID = :id AND h.EmpCompID = a.EmpCompID
                             WHERE h.EmpRoleID = 1");
        $st->execute([':id' => $empId]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/** Human label for a request type code used by Query-UpdateApp.php / Query-UpdateAppDis.php. */
function push_type_label(string $type): string
{
    return ['HL' => 'leave', 'OT' => 'overtime', 'OB' => 'official business', 'EO' => 'early out'][strtoupper($type)] ?? 'request';
}

/* ---------- request watchers ----------
   Filing and approving are spread over many branches (one endpoint has six
   inserts). Rather than a push call in each branch, the endpoint calls one
   watcher at the top; it snapshots the table and, when the script ends,
   pushes for whatever actually changed. Refused or failed actions change
   nothing, so they send nothing. */

// type => [table, id column, applicant column, approver column, status column]
const PUSH_REQUEST_TABLES = [
    'EO' => ['earlyout',        'SID',     'EMPID', 'EmpISID', 'Status'],
    'OB' => ['obs',             'OBID',    'EmpID', 'EmpSID',  'OBStatus'],
    'HL' => ['hleaves',         'LeaveID', 'EmpID', 'EmpSID',  'LStatus'],
    'OT' => ['otattendancelog', 'OTLOGID', 'EmpID', 'EmpISID', 'Status'],
];

/** Applicant + status of one request, or null. */
function push_request_state(PDO $pdo, string $type, int $id): ?array
{
    $t = PUSH_REQUEST_TABLES[strtoupper($type)] ?? null;
    if (!$t || $id <= 0) { return null; }
    try {
        [$table, $pk, $emp, , $status] = $t;
        $st = $pdo->prepare("SELECT `$emp` AS emp, `$status` AS status FROM `$table` WHERE `$pk` = :id");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? ['emp' => (string) $r['emp'], 'status' => (int) $r['status']] : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Approve / disapprove endpoints: call once the record id is known. When the
 * script ends, a changed status is pushed to the applicant (and to HR when a
 * superior approved). $reason = disapproval remarks.
 */
function push_watch_decision(PDO $pdo, string $type, int $id, string $actorId, string $reason = ''): void
{
    if (!push_enabled()) { return; }
    $before = push_request_state($pdo, $type, $id);
    if (!$before) { return; }
    register_shutdown_function(function () use ($pdo, $type, $id, $actorId, $reason, $before) {
        $after = push_request_state($pdo, $type, $id);
        if ($after && $after['status'] !== $before['status']) {
            push_request_decided($pdo, $type, $after['emp'], $after['status'], $actorId, trim($reason));
        }
    });
}

/**
 * Filing endpoints: call at the top. When the script ends, every request of
 * this type inserted meanwhile is pushed to whoever must act on it:
 * pending (1) -> the immediate superior, approved by superior (2) -> HR,
 * filed already approved by HR (4) -> the employee it was filed for.
 */
function push_watch_new_requests(PDO $pdo, string $type, string $actorId): void
{
    if (!push_enabled()) { return; }
    $t = PUSH_REQUEST_TABLES[strtoupper($type)] ?? null;
    if (!$t) { return; }
    [$table, $pk, $emp, $approver, $status] = $t;
    try {
        $maxBefore = (int) $pdo->query("SELECT COALESCE(MAX(`$pk`), 0) FROM `$table`")->fetchColumn();
    } catch (Throwable $e) {
        return;
    }
    register_shutdown_function(function () use ($pdo, $type, $actorId, $table, $pk, $emp, $approver, $status, $maxBefore) {
        try {
            // only this request's rows (filed by me, or by me on someone's behalf), not other users' concurrent filings
            $st = $pdo->prepare("SELECT `$emp` AS emp, `$approver` AS approver, `$status` AS status
                                 FROM `$table` WHERE `$pk` > :max AND (`$emp` = :me OR `$approver` = :me2)
                                 ORDER BY `$pk` LIMIT 50");
            $st->execute([':max' => $maxBefore, ':me' => $actorId, ':me2' => $actorId]);
            $seen = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $key = $r['emp'] . '|' . $r['status'];
                if (isset($seen[$key])) { continue; }   // one push per applicant, even for multi-day filings
                $seen[$key] = true;
                $s = (int) $r['status'];
                if ($s === 1) {
                    push_request_filed($pdo, $type, (string) $r['emp'], (string) $r['approver'], $actorId);
                } elseif ($s === 2 || $s === 4) {
                    push_request_decided($pdo, $type, (string) $r['emp'], $s, $actorId);
                }
            }
        } catch (Throwable $e) {
            error_log('[wedo push] new ' . $type . ': ' . $e->getMessage());
        }
    });
}

/* ---------- event helpers (call these from feature code) ---------- */

/** An employee filed a leave / OT / OB / early-out request: tell the approver. */
function push_request_filed(PDO $pdo, string $type, string $applicantId, ?string $approverId, string $actorId = ''): void
{
    $label = push_type_label($type);
    $who   = push_name($pdo, $applicantId);
    $to    = array_diff([(string) $approverId], [$actorId !== '' ? $actorId : $applicantId]);
    push_send($pdo, $to, 'New ' . $label . ' request', $who . ' filed a ' . $label . ' request for your approval.',
              ['url' => 'notifications.php', 'tag' => 'req-' . $type . '-' . $applicantId]);
}

/**
 * A request changed status. $status uses the app's codes:
 * 2 approved by superior (now with HR) · 4/9 approved by HR · 8 approved without pay
 * 3 disapproved by superior · 5 disapproved by HR · 6 auto-rejected
 */
function push_request_decided(PDO $pdo, string $type, string $applicantId, int $status, string $actorId, string $reason = ''): void
{
    if ($applicantId === '' || $applicantId === $actorId) { return; }
    $label = push_type_label($type);
    $Label = ucfirst($label);
    switch ($status) {
        case 2:  $t = $Label . ' request approved'; $b = 'Approved by your immediate superior. It now goes to HR for final approval.'; break;
        case 4:
        case 9:  $t = $Label . ' request approved'; $b = 'Your ' . $label . ' request was approved by HR.'; break;
        case 8:  $t = $Label . ' request approved'; $b = 'Your ' . $label . ' request was approved without pay.'; break;
        case 3:
        case 5:  $t = $Label . ' request disapproved'; $b = 'Your ' . $label . ' request was disapproved' . ($reason !== '' ? ': ' . $reason : '.'); break;
        case 6:  $t = $Label . ' request rejected'; $b = 'Your ' . $label . ' request was automatically rejected.'; break;
        default: return;
    }
    push_send($pdo, $applicantId, $t, $b, ['url' => 'notifications.php']);

    // superior approved -> HR now has something to act on
    if ($status === 2) {
        $hr = array_diff(push_hr_of($pdo, $applicantId), [$actorId, $applicantId]);
        push_send($pdo, $hr, $Label . ' request for HR approval',
                  push_name($pdo, $applicantId) . '\'s ' . $label . ' request was approved by their superior and needs HR approval.',
                  ['url' => 'notifications.php']);
    }
}

/** A profile change request was filed: tell everyone allowed to review them. */
function push_profile_change_filed(PDO $pdo, string $applicantId, array $reviewerIds): void
{
    push_send($pdo, array_diff($reviewerIds, [$applicantId]), 'Profile change request',
              push_name($pdo, $applicantId) . ' asked to update their profile.', ['url' => 'profilerequests']);
}

/** A profile change request was approved or rejected. */
function push_profile_change_decided(PDO $pdo, string $applicantId, string $decision, string $actorId, string $remarks = ''): void
{
    if ($applicantId === $actorId) { return; }
    $ok = $decision === 'approved';
    push_send($pdo, $applicantId, 'Profile change ' . ($ok ? 'approved' : 'rejected'),
              'Your profile change request was ' . ($ok ? 'approved' : 'rejected') . ($remarks !== '' ? ': ' . $remarks : '.'),
              ['url' => 'e201']);
}

/**
 * New chat message. $with is the conversation key from Query-messages.php
 * (an EmpID for 1:1, "grp:ID" for a group); $kind is text | gif | file.
 */
function push_chat_message(PDO $pdo, string $me, string $with, string $text, string $kind = 'text'): void
{
    try {
        $sender  = push_name($pdo, $me);
        $preview = $kind === 'text'
            ? trim(preg_replace('/\s+/', ' ', strip_tags($text)))
            : (['gif' => 'sent a GIF', 'image' => 'sent a photo'][$kind] ?? 'sent a file');
        if (strpos($with, 'grp:') === 0) {
            $gid = (int) substr($with, 4);
            $g = $pdo->prepare("SELECT name FROM msg_groups WHERE id = :id");
            $g->execute([':id' => $gid]);
            $group = (string) $g->fetchColumn();
            $m = $pdo->prepare("SELECT EmpID FROM msg_group_members WHERE group_id = :id");
            $m->execute([':id' => $gid]);
            $to = array_diff($m->fetchAll(PDO::FETCH_COLUMN), [$me]);
            push_send($pdo, $to, $group !== '' ? $group : 'Group chat', $sender . ': ' . $preview,
                      ['url' => 'messages?with=' . rawurlencode($with), 'tag' => 'chat-' . $with]);
        } else {
            push_send($pdo, $with, $sender, $preview,
                      ['url' => 'messages?with=' . rawurlencode($me), 'tag' => 'chat-' . $me]);
        }
    } catch (Throwable $e) {
        error_log('[wedo push] chat: ' . $e->getMessage());
    }
}

/** Someone started a call: ring the invited people's phones. */
function push_incoming_call(PDO $pdo, string $me, array $invitees, ?int $groupId): void
{
    $with = $groupId ? 'grp:' . $groupId : $me;
    push_send($pdo, array_diff($invitees, [$me]), 'Incoming call', push_name($pdo, $me) . ' is calling you',
              ['url' => 'messages?with=' . rawurlencode($with), 'kind' => 'call', 'tag' => 'call-' . $with]);
}

/** New WeDo Corner announcement: everyone in the author's company who has the app. */
function push_announcement(PDO $pdo, string $authorId, string $text): void
{
    try {
        $st = $pdo->prepare("SELECT DISTINCT pd.EmpID FROM push_devices pd
                             JOIN empdetails d ON d.EmpID = pd.EmpID
                             JOIN empdetails a ON a.EmpID = :me AND a.EmpCompID = d.EmpCompID
                             WHERE pd.EmpID <> :me2");
        $st->execute([':me' => $authorId, ':me2' => $authorId]);
        push_send($pdo, $st->fetchAll(PDO::FETCH_COLUMN), 'New announcement',
                  trim(preg_replace('/\s+/', ' ', strip_tags($text))), ['url' => 'corner', 'tag' => 'corner']);
    } catch (Throwable $e) {
        error_log('[wedo push] announcement: ' . $e->getMessage());
    }
}
