<?php
/* ==========================================================================
   corner-lib.php — helpers shared by the Corner page (corner.php) and the
   floating Corner bubble (includes/corner-bubble*.php).
   ========================================================================== */

/* "New" window for announcements: the sidebar badge, the bubble badge and
   mark-as-seen all use the same 30 days, so opening either one clears both. */
if (!defined('WD_CORNER_NEW_DAYS')) { define('WD_CORNER_NEW_DAYS', 30); }

/* Announcement bodies are escaped so user-typed HTML stays inert. The one
   exception is the birthday cake icon that query-login.php embeds when it
   auto-posts "Happy Birthday" announcements; that exact tag is allowed back
   through after escaping so it renders as an icon instead of literal text. */
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
