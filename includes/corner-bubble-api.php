<?php
/* ==========================================================================
   corner-bubble-api.php — data for the floating Corner bubble.
     GET  ?action=feed   -> HTML fragment: latest announcements (marks "new")
     GET  ?action=count  -> JSON {unseen:n}   (badge refresh while a page is open)
     POST ?action=seen   -> JSON {unseen:0}   (mark recent announcements as seen)
   The calendar tab reuses includes/calendar-ajax.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");

$action = $_GET['action'] ?? '';
$isJson = ($action !== 'feed');

if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    http_response_code(401);
    if ($isJson) { header('Content-Type: application/json'); echo '{"error":"session"}'; }
    exit;
}
$me = $_SESSION['id'];
session_write_close(); // read-only from here: don't hold the session lock

require_once __DIR__ . '/corner-lib.php';
include 'w_conn.php';
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    if ($isJson) { header('Content-Type: application/json'); echo '{"error":"db"}'; }
    exit;
}

if ($action === 'count') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['unseen' => wd_corner_unseen_count($pdo, $me)]);
    exit;
}

if ($action === 'seen') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"method"}'; exit; }
    wd_corner_mark_seen($pdo, $me);
    echo json_encode(['unseen' => 0]);
    exit;
}

if ($action !== 'feed') { http_response_code(400); exit; }

/* ---------- feed ---------- */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$limit = 20;
$root  = dirname(__DIR__);

/* profile + seen lookups are per-row subqueries (only $limit rows) so a
   duplicate empprofiles/annseen row can never repeat a post */
$st = $pdo->prepare("SELECT a.aid, a.EmpID, a.Title, a.ADesc, a.ADate, e.EmpFN, e.EmpLN,
        (SELECT p.EmpPPath  FROM empprofiles p WHERE p.EmpID = a.EmpID LIMIT 1) AS EmpPPath,
        (SELECT p.EmpGender FROM empprofiles p WHERE p.EmpID = a.EmpID LIMIT 1) AS EmpGender,
        (a.EmpID <> :me2 AND a.ADate >= (NOW() - INTERVAL " . (int) WD_CORNER_NEW_DAYS . " DAY)
          AND NOT EXISTS (SELECT 1 FROM annseen s WHERE s.aid = a.aid AND s.EmpID = :me)) AS is_new
    FROM announcements a
    INNER JOIN employees e ON e.EmpID = a.EmpID
    ORDER BY a.ADate DESC
    LIMIT $limit");
$st->execute([':me' => $me, ':me2' => $me]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo '<div class="wdcb-empty"><div class="wdcb-empty__icon" aria-hidden="true">'
       . '<svg viewBox="0 0 24 24"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></div>'
       . '<p>No announcements yet.</p><span>Company news will show up here.</span></div>';
    exit;
}

echo '<ul class="wdcb-feed">';
foreach ($rows as $r) {
    $author = ($r['EmpFN'] === 'admin') ? 'WeDo Family' : trim($r['EmpFN'] . ' ' . $r['EmpLN']);
    $avatar = wd_corner_avatar($r['EmpPPath'], $r['EmpGender'], $root);
    $full   = date('l, F j, Y · g:i A', strtotime($r['ADate']));
    $isNew  = (int) $r['is_new'] === 1;
    echo '<li class="wdcb-post' . ($isNew ? ' is-new' : '') . '">'
       . '<img class="wdcb-post__avatar" src="' . htmlspecialchars($avatar) . '" alt="" loading="lazy" width="40" height="40">'
       . '<div class="wdcb-post__main">'
       .   '<div class="wdcb-post__meta">'
       .     '<span class="wdcb-post__author">' . htmlspecialchars($author) . '</span>'
       .     ($isNew ? '<span class="wdcb-post__new">New</span>' : '')
       .     '<time class="wdcb-post__time" datetime="' . htmlspecialchars(date('c', strtotime($r['ADate']))) . '" title="' . htmlspecialchars($full) . '">'
       .       htmlspecialchars(wd_corner_ago($r['ADate'])) . '</time>'
       .   '</div>'
       // every post is titled "Announcement" today; only show a real title
       .   (strcasecmp(trim($r['Title']), 'Announcement') !== 0 && trim($r['Title']) !== ''
              ? '<div class="wdcb-post__title">' . htmlspecialchars($r['Title']) . '</div>' : '')
       .   '<div class="wdcb-post__body">' . nl2br(wd_announcement_body($r['ADesc'])) . '</div>'
       .   '<button type="button" class="wdcb-post__more" hidden>See more</button>'
       . '</div>'
       . '</li>';
}
echo '</ul>';
echo '<a class="wdcb-feed__all" href="corner">See all announcements</a>';
