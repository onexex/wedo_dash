<?php
/* ==========================================================================
   corner-lib.php — helpers shared by the Corner page (corner.php) and the
   floating Corner bubble (includes/corner-bubble*.php).
   ========================================================================== */

/* "New" window for announcements: the sidebar badge, the bubble badge and
   mark-as-seen all use the same 30 days, so opening either one clears both. */
if (!defined('WD_CORNER_NEW_DAYS')) { define('WD_CORNER_NEW_DAYS', 30); }

/* Who may post to the Corner (announcements and polls): super users plus a
   few named accounts. */
if (!function_exists('wd_corner_can_post')) {
    function wd_corner_can_post() {
        $id = (string) ($_SESSION['id'] ?? '');
        return (($_SESSION['UserType'] ?? '') == 1) || in_array($id, ['WeDoinc-002', 'WeDoinc-006', 'WeDoinc-003'], true);
    }
}

/* Announcement bodies are escaped so user-typed HTML stays inert. The one
   exception is the birthday cake icon that wd_post_birthday_greetings() embeds
   when it auto-posts "Happy Birthday" announcements; that exact tag is allowed
   back through after escaping so it renders as an icon instead of literal text. */
if (!function_exists('wd_announcement_body')) {
    function wd_announcement_body($text) {
        $safe = htmlspecialchars($text);
        $safe = preg_replace(
            '/&lt;i class=(?:&#039;|&quot;)fa fa-birthday-cake(?:&#039;|&quot;)&gt;&lt;\/i&gt;/',
            '<i class="fa-solid fa-cake-candles cn__cake" aria-label="birthday"></i>',
            $safe
        );
        return $safe;
    }
}

/* Birthday greetings: the first sign-in of the day (password, remember-me
   cookie, the app, or a page opened by someone already signed in) posts one
   "Happy Birthday" announcement, from 'admin', for each active employee born
   on that day. Feb 29 birthdays are greeted on Mar 1 in non-leap years, like
   the Corner calendar. tblsyslog keeps one row per day already handled, and a
   named lock (per database, so staging and live never block each other) stops
   two sign-ins at the same moment from both posting.
   $today (Y-m-d) is for tests. Returns how many greetings were posted. */
function wd_post_birthday_greetings(PDO $pdo, $today = null) {
    date_default_timezone_set("Asia/Manila");
    if ($today === null) { $today = date('Y-m-d'); }

    if ((int) $pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), '.bday_greetings'), 5)")->fetchColumn() !== 1) {
        return 0;   // another request is posting today's greetings right now
    }
    try {
        $done = $pdo->prepare("SELECT 1 FROM tblsyslog WHERE datecheck = :d LIMIT 1");
        $done->execute([':d' => $today]);
        if ($done->fetchColumn()) { return 0; }

        [$y, $m, $d] = array_map('intval', explode('-', $today));
        $leapDay = ($m === 3 && $d === 1 && !checkdate(2, 29, $y)) ? 1 : 0;
        $st = $pdo->prepare("SELECT e.EmpFN, e.EmpLN FROM employees e
            INNER JOIN empprofiles p ON p.EmpID = e.EmpID
            WHERE e.EmpStatusID = 1
              AND ((MONTH(p.EmpDOB) = :m AND DAY(p.EmpDOB) = :d)
                   OR (:leap = 1 AND MONTH(p.EmpDOB) = 2 AND DAY(p.EmpDOB) = 29))
            ORDER BY e.EmpFN, e.EmpLN");
        $st->execute([':m' => $m, ':d' => $d, ':leap' => $leapDay]);
        $people = $st->fetchAll(PDO::FETCH_ASSOC);

        $now = $today . date(' H:i:s');
        $pdo->beginTransaction();
        $post = $pdo->prepare("INSERT INTO announcements (EmpID, Title, ADesc, ADate)
            VALUES ('admin', 'Announcement', :msg, :dt)");
        foreach ($people as $p) {
            $post->execute([':msg' => 'Happy Birthday ' . $p['EmpFN'] . ' ' . $p['EmpLN'] . " <i class='fa fa-birthday-cake'></i>",
                            ':dt'  => $now]);
        }
        $pdo->prepare("INSERT INTO tblsyslog (datecheck) VALUES (:d)")->execute([':d' => $today]);
        $pdo->commit();
        return count($people);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '.bday_greetings'))");
    }
}

/* Count of recent announcements I have not seen, excluding my own posts. */
function wd_corner_unseen_count(PDO $pdo, $empId) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM announcements a
        LEFT JOIN annseen s ON s.aid = a.aid AND s.EmpID = :id
        WHERE s.aid IS NULL AND a.EmpID <> :id2
          AND a.ADate >= (NOW() - INTERVAL " . (int) WD_CORNER_NEW_DAYS . " DAY)");
    $st->execute([':id' => $empId, ':id2' => $empId]);
    return (int) $st->fetchColumn();
}

/* Mark every recent announcement I haven't seen as seen (clears the badges). */
function wd_corner_mark_seen(PDO $pdo, $empId) {
    date_default_timezone_set("Asia/Manila");
    $now = date("Y-m-d H:i:s"); // datetime column: 24h, no AM/PM
    $unseen = $pdo->prepare(
        "SELECT a.aid FROM announcements a
         LEFT JOIN annseen s ON s.aid = a.aid AND s.EmpID = :id
         WHERE s.aid IS NULL AND a.ADate >= (NOW() - INTERVAL " . (int) WD_CORNER_NEW_DAYS . " DAY)");
    $unseen->execute([':id' => $empId]);
    $aids = $unseen->fetchAll(PDO::FETCH_COLUMN);
    if ($aids) {
        $mark = $pdo->prepare(
            "INSERT INTO annseen (aid, EmpID, FSeenDate, LSeenDate, Status)
             VALUES (:aid, :id, :sd, :ld, 1)");
        foreach ($aids as $aid) {
            $mark->execute([':aid' => $aid, ':id' => $empId, ':sd' => $now, ':ld' => $now]);
        }
    }
    return count($aids);
}

/* Profile picture for an announcement author: uploaded photo if the file is
   there, else the gender default. $root is the app root on disk, so this works
   from pages at the root and from endpoints inside includes/. */
function wd_corner_avatar($ppath, $gender, $root) {
    if ($ppath !== null && $ppath !== '' && file_exists($root . '/' . $ppath)) { return $ppath; }
    if ($gender === 'Male')   { return 'assets/images/profiles/man_d.jpg'; }
    if ($gender === 'Female') { return 'assets/images/profiles/woman_d.jpg'; }
    return 'assets/images/profiles/default.png';
}

/* "just now", "5m ago", "3h ago", "Yesterday", "Mon", "Sep 3", "Sep 3, 2024" */
function wd_corner_ago($datetime) {
    date_default_timezone_set("Asia/Manila");
    $t = strtotime($datetime);
    if (!$t) { return ''; }
    $diff = time() - $t;
    if ($diff < 60)     { return 'just now'; }
    if ($diff < 3600)   { return floor($diff / 60) . 'm ago'; }
    if ($diff < 86400 && date('Y-m-d', $t) === date('Y-m-d')) { return floor($diff / 3600) . 'h ago'; }
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) { return 'Yesterday'; }
    if ($diff < 6 * 86400) { return date('D', $t); }
    if (date('Y', $t) === date('Y')) { return date('M j', $t); }
    return date('M j, Y', $t);
}

/* ==========================================================================
   Announcements feed (corner.php + query/corner-feed.php)
   ========================================================================== */

if (!defined('WD_CORNER_PAGE')) { define('WD_CORNER_PAGE', 15); }

function wd_corner_csrf_token() {
    if (empty($_SESSION['corner_csrf'])) { $_SESSION['corner_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['corner_csrf'];
}

/* the author, or a super user, may edit / delete a post */
function wd_corner_can_edit(array $r, $me) {
    return (string) $r['EmpID'] === (string) $me || (($_SESSION['UserType'] ?? '') == 1);
}

/* "Juan Dela Cruz" from an auto-posted "Happy Birthday Juan Dela Cruz <i …>", else null */
function wd_corner_bday_name(array $r) {
    if ((string) $r['EmpID'] !== 'admin') { return null; }
    return preg_match('/^Happy Birthday\s+(.+?)\s*(<i\b.*)?$/is', trim((string) $r['ADesc']), $m) ? trim(strip_tags($m[1])) : null;
}

/* One page of posts, newest first, older than $before (an aid) when given.
   Returns [rows, hasMore]. is_new = recent, not mine, and not seen yet —
   read it BEFORE wd_corner_mark_seen() runs. */
function wd_corner_feed(PDO $pdo, $me, $before = 0, $limit = WD_CORNER_PAGE) {
    $where = $before > 0 ? 'WHERE a.aid < ' . (int) $before : '';
    $st = $pdo->prepare("SELECT a.aid, a.EmpID, a.Title, a.ADesc, a.ADate, e.EmpFN, e.EmpLN,
            (SELECT p.EmpPPath  FROM empprofiles p WHERE p.EmpID = a.EmpID LIMIT 1) AS EmpPPath,
            (SELECT p.EmpGender FROM empprofiles p WHERE p.EmpID = a.EmpID LIMIT 1) AS EmpGender,
            (a.EmpID <> :me2 AND a.ADate >= (NOW() - INTERVAL " . (int) WD_CORNER_NEW_DAYS . " DAY)
              AND NOT EXISTS (SELECT 1 FROM annseen s WHERE s.aid = a.aid AND s.EmpID = :me)) AS is_new
        FROM announcements a
        INNER JOIN employees e ON e.EmpID = a.EmpID
        $where
        ORDER BY a.aid DESC
        LIMIT " . ((int) $limit + 1));
    $st->execute([':me' => $me, ':me2' => $me]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $more = count($rows) > $limit;
    return [array_slice($rows, 0, $limit), $more];
}

/* PollID from the "Poll#<id>" title a new poll's announcement carries (corner-polls-lib), else 0 */
function wd_corner_poll_id($title) {
    return preg_match('/^Poll#(\d+)$/', trim((string) $title), $m) ? (int) $m[1] : 0;
}

/* Feed markup. Birthday auto-posts of the same day collapse into one card;
   a new poll's announcement becomes a "started a poll" card. */
function wd_corner_feed_html(array $rows, $me, $root) {
    $h = '';
    $n = count($rows);
    for ($i = 0; $i < $n; $i++) {
        $r = $rows[$i];
        if ($pid = wd_corner_poll_id($r['Title'])) { $h .= wd_corner_poll_teaser($r, $pid, $root); continue; }
        $bday = wd_corner_bday_name($r);
        if ($bday !== null) {
            $day = substr($r['ADate'], 0, 10);
            $names = [$bday]; $new = (int) $r['is_new'] === 1; $last = $r['aid'];
            while ($i + 1 < $n && ($nb = wd_corner_bday_name($rows[$i + 1])) !== null && substr($rows[$i + 1]['ADate'], 0, 10) === $day) {
                $i++; $names[] = $nb; $new = $new || (int) $rows[$i]['is_new'] === 1; $last = $rows[$i]['aid'];
            }
            $h .= wd_corner_bday_card(array_reverse($names), $r['ADate'], $new, $last);
            continue;
        }
        $h .= wd_corner_post_card($r, $me, $root);
    }
    return $h;
}

function wd_corner_bday_card(array $names, $date, $isNew, $lastAid) {
    $t = strtotime($date);
    $isToday = date('Y-m-d', $t) === date('Y-m-d');
    $chips = '';
    foreach ($names as $nm) { $chips .= '<span class="cf-bday__name">' . htmlspecialchars($nm) . '</span>'; }
    return '<article class="cf cf--bday' . ($isNew ? ' is-new' : '') . '" data-kind="bday" data-aid="' . (int) $lastAid . '">'
         . '<div class="cf-bday__icon" aria-hidden="true"><i class="fa-solid fa-cake-candles"></i></div>'
         . '<div class="cf__main">'
         .   '<div class="cf__meta"><span class="cf__author">' . ($isToday ? 'Birthdays today' : 'Birthdays') . '</span>'
         .     ($isNew ? '<span class="cf__new">New</span>' : '')
         .     '<time class="cf__time" datetime="' . date('c', $t) . '" title="' . htmlspecialchars(date('l, F j, Y', $t)) . '">'
         .       htmlspecialchars($isToday ? 'Today' : wd_corner_ago($date)) . '</time></div>'
         .   '<p class="cf-bday__text">Happy birthday! &#127881; Wish ' . (count($names) === 1 ? 'them' : 'them all') . ' a great one.</p>'
         .   '<div class="cf-bday__names">' . $chips . '</div>'
         . '</div></article>';
}

function wd_corner_post_card(array $r, $me, $root) {
    $author = ($r['EmpFN'] === 'admin') ? 'WeDo Family' : trim($r['EmpFN'] . ' ' . $r['EmpLN']);
    $t      = strtotime($r['ADate']);
    $isNew  = (int) ($r['is_new'] ?? 0) === 1;
    $title  = trim((string) $r['Title']);
    $edit   = wd_corner_can_edit($r, $me);
    $h = '<article class="cf' . ($isNew ? ' is-new' : '') . '" data-kind="news" data-aid="' . (int) $r['aid'] . '">'
       . '<img class="cf__avatar" src="' . htmlspecialchars(wd_corner_avatar($r['EmpPPath'], $r['EmpGender'], $root)) . '" alt="" width="44" height="44" loading="lazy">'
       . '<div class="cf__main">'
       .   '<div class="cf__meta">'
       .     '<span class="cf__author">' . htmlspecialchars($author) . '</span>'
       .     ($isNew ? '<span class="cf__new">New</span>' : '')
       .     '<time class="cf__time" datetime="' . date('c', $t) . '" title="' . htmlspecialchars(date('l, F j, Y · g:i A', $t)) . '">'
       .       htmlspecialchars(wd_corner_ago($r['ADate'])) . '</time>';
    if ($edit) {
        $h .= '<div class="cf__menu">'
            . '<button type="button" class="cf__menubtn" aria-haspopup="true" aria-expanded="false" aria-label="Post options"><i class="fa-solid fa-ellipsis"></i></button>'
            . '<div class="cf__menulist" role="menu" hidden>'
            . '<button type="button" role="menuitem" data-act="edit"><i class="fa-solid fa-pen"></i> Edit</button>'
            . '<button type="button" role="menuitem" data-act="delete" class="is-danger"><i class="fa-solid fa-trash-can"></i> Delete</button>'
            . '</div></div>';
    }
    $h .=   '</div>'
       .   (strcasecmp($title, 'Announcement') !== 0 && $title !== '' ? '<h4 class="cf__title">' . htmlspecialchars($title) . '</h4>' : '')
       .   '<div class="cf__body">' . nl2br(wd_announcement_body($r['ADesc'])) . '</div>'
       .   '<button type="button" class="cf__more" hidden>See more</button>'
       .   ($edit ? '<textarea class="cf__src" hidden>' . htmlspecialchars($r['ADesc']) . '</textarea>' : '')
       . '</div></article>';
    return $h;
}

/* Active employees in $compId whose birthday is today (Feb 29 birthdays on
   Mar 1 in non-leap years, like the calendar). [['id','name','me'], ...] */
function wd_corner_birthdays_today(PDO $pdo, $compId, $me) {
    date_default_timezone_set("Asia/Manila");
    if ((string) $compId === '') { return []; }
    $m = (int) date('n'); $d = (int) date('j');
    $leapDay = ($m === 3 && $d === 1 && !checkdate(2, 29, (int) date('Y'))) ? 1 : 0;
    $st = $pdo->prepare("SELECT e.EmpID, e.EmpFN, e.EmpLN FROM employees e
        INNER JOIN empdetails dt ON dt.EmpID = e.EmpID
        INNER JOIN empprofiles p ON p.EmpID = e.EmpID
        WHERE e.EmpStatusID = 1 AND dt.EmpCompID = :cid
          AND ((MONTH(p.EmpDOB) = :m AND DAY(p.EmpDOB) = :d) OR (:leap = 1 AND MONTH(p.EmpDOB) = 2 AND DAY(p.EmpDOB) = 29))
        ORDER BY e.EmpFN, e.EmpLN");
    $st->execute([':cid' => $compId, ':m' => $m, ':d' => $d, ':leap' => $leapDay]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => $r['EmpID'], 'name' => trim($r['EmpFN'] . ' ' . $r['EmpLN']), 'me' => (string) $r['EmpID'] === (string) $me];
    }
    return $out;
}

/* The company's next holiday from today on: ['date' => Y-m-d, 'name' => …] or null */
function wd_corner_next_holiday(PDO $pdo, $compId) {
    date_default_timezone_set("Asia/Manila");
    if ((string) $compId === '') { return null; }
    $st = $pdo->prepare("SELECT Hdate, Hdescription FROM holidays WHERE HCompID = :cid AND Hdate >= :today ORDER BY Hdate LIMIT 1");
    $st->execute([':cid' => $compId, ':today' => date('Y-m-d')]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? ['date' => $r['Hdate'], 'name' => $r['Hdescription']] : null;
}

function wd_corner_poll_teaser(array $r, $pollId, $root) {
    $author = ($r['EmpFN'] === 'admin') ? 'WeDo Family' : trim($r['EmpFN'] . ' ' . $r['EmpLN']);
    $t      = strtotime($r['ADate']);
    $isNew  = (int) ($r['is_new'] ?? 0) === 1;
    $q      = preg_replace('/^New poll:\s*/', '', (string) $r['ADesc']);
    return '<article class="cf cf--poll' . ($isNew ? ' is-new' : '') . '" data-kind="news" data-aid="' . (int) $r['aid'] . '">'
         . '<img class="cf__avatar" src="' . htmlspecialchars(wd_corner_avatar($r['EmpPPath'], $r['EmpGender'], $root)) . '" alt="" width="44" height="44" loading="lazy">'
         . '<div class="cf__main">'
         .   '<div class="cf__meta"><span class="cf__author">' . htmlspecialchars($author) . '</span>'
         .     '<span class="cf-poll__did"><i class="fa-solid fa-square-poll-horizontal"></i> started a poll</span>'
         .     ($isNew ? '<span class="cf__new">New</span>' : '')
         .     '<time class="cf__time" datetime="' . date('c', $t) . '" title="' . htmlspecialchars(date('l, F j, Y · g:i A', $t)) . '">'
         .       htmlspecialchars(wd_corner_ago($r['ADate'])) . '</time></div>'
         .   '<div class="cf-poll__q">' . htmlspecialchars($q) . '</div>'
         .   '<a class="cf-poll__go" href="#poll-' . (int) $pollId . '" data-poll="' . (int) $pollId . '">Vote now <i class="fa-solid fa-arrow-up"></i></a>'
         . '</div></article>';
}

/* Corner pop-up (assets/js/wedo-corner-alert.js, via the every-page check-in in
   query/Query-calls.php). $after is the last post this browser was told about:
   -1 = first check-in, answer only the newest id so old posts never pop up;
   otherwise the newest post after it from the last day, not mine. Uses the
   primary key only, so the 3-second check-in stays cheap. */
function wd_corner_alert(PDO $pdo, $me, $after) {
    date_default_timezone_set("Asia/Manila");
    try {
        if ($after < 0) {
            return ['corner_top' => (int) $pdo->query("SELECT COALESCE(MAX(aid), 0) FROM announcements")->fetchColumn()];
        }
        $st = $pdo->prepare("SELECT a.aid, a.EmpID, a.Title, a.ADesc, e.EmpFN, e.EmpLN FROM announcements a
            LEFT JOIN employees e ON e.EmpID = a.EmpID
            WHERE a.aid > :after AND a.EmpID <> :me AND a.ADate >= :since
            ORDER BY a.aid DESC LIMIT 1");
        $st->execute([':after' => (int) $after, ':me' => $me, ':since' => date('Y-m-d H:i:s', time() - 86400)]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) { return ['corner' => null]; }
        $poll = wd_corner_poll_id($r['Title']);
        $bday = wd_corner_bday_name($r);
        $who  = ($r['EmpFN'] === 'admin' || $r['EmpFN'] === null) ? 'WeDo Family' : trim($r['EmpFN'] . ' ' . $r['EmpLN']);
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $r['ADesc']), ENT_QUOTES, 'UTF-8')));
        if ($poll) { $text = preg_replace('/^New poll:\s*/', '', $text); }
        if (mb_strlen($text) > 140) { $text = rtrim(mb_substr($text, 0, 137)) . '…'; }
        return ['corner' => [
            'aid'   => (int) $r['aid'],
            'kind'  => $poll ? 'poll' : ($bday !== null ? 'bday' : 'news'),
            'title' => $poll ? 'New poll from ' . $who : ($bday !== null ? 'Birthday today' : 'Announcement from ' . $who),
            'text'  => $text,
            'url'   => $poll ? 'corner#poll-' . $poll : 'corner',
        ]];
    } catch (Exception $e) {
        return [];
    }
}
