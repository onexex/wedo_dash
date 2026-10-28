<?php

  require_once 'includes/class.calendar.php';
  $phpCalendar = new PHPCalendar ();
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
   if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{
    if(!isset($_COOKIE["WeDoID"])) {

        header ('location: login'); exit;
    }else{
        if(!isset($_COOKIE["WeDoID"])) {
          session_destroy();
          header ('location: login'); exit;
        }else{
              try{
              include 'w_conn.php';
              $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                 }
              catch(PDOException $e)
                 {
              die("ERROR: Could not connect. " . $e->getMessage());
                 }
              $statement = $pdo->prepare("select * from empdetails");
              $statement->execute();

              while ($row=$statement->fetch()) {
                if ((!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash']) && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time()))){
                        $_SESSION['id']=$row['EmpID'];

                        $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                        $statement->bindParam(':un' , $_SESSION['id']);
                        $statement->execute();
                        $count=$statement->rowCount();
                        $row=$statement->fetch();
                        $hash = $row['EmpPW'];
                        $_SESSION['UserType']=$row['EmpRoleID'];
                        $cid=$row['EmpCompID'];
                        $_SESSION['CompID']=$row['EmpCompID'];
                        $_SESSION['EmpISID']=$row['EmpISID'];
                        $statement = $pdo->prepare("select * from companies where CompanyID = :pw");
                        $statement->bindParam(':pw' , $cid);
                        $statement->execute();
                        $comcount=$statement->rowCount();
                        $row=$statement->fetch();
                        if ($comcount>0){
                          $_SESSION['CompanyName']=$row['CompanyDesc'];
                          $_SESSION['CompanyLogo']=$row['logopath'];
                          $_SESSION['CompanyColor']=$row['comcolor'];
                        }else{
                          $_SESSION['CompanyName']="ADMIN";
                          $_SESSION['CompanyLogo']="";
                          $_SESSION['CompanyColor']="red";
                        }
                         $_SESSION['PassHash']=$hash;

                }
                else{

                }
              }
            }
    }

  }
  if (!isset($_SESSION['id']) || $_SESSION['id']=="0") { header ('location: login'); exit; }   // the WeDoID cookie did not sign anyone in
  require_once 'includes/corner-lib.php';
  date_default_timezone_set("Asia/Manila");

/* Older form handlers, kept for pages that still post here; the Corner itself
   now uses query/corner-feed.php. Both check who may post / edit. */
if (isset($_GET['addannoun'])) {
    if (!wd_corner_can_post()) { http_response_code(403); exit; }
    include 'w_conn.php';
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->prepare("INSERT INTO announcements (EmpID, Title, ADesc, ADate) VALUES (?, 'Announcement', ?, ?)")
        ->execute([$_SESSION['id'], (string) ($_POST['announ'] ?? ''), date("Y-m-d H:i:s")]);
    // mobile push: the author's company, on the app
    require_once __DIR__ . '/includes/push-lib.php';
    push_announcement($pdo, (string) $_SESSION['id'], (string) ($_POST['announ'] ?? ''));
    return;
}
if (isset($_GET['updateann'])) {
    include 'w_conn.php';
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $own = $pdo->prepare("SELECT EmpID FROM announcements WHERE aid = ?");
    $own->execute([(int) $_GET['updateann']]);
    $owner = $own->fetchColumn();
    if ($owner === false || !wd_corner_can_edit(['EmpID' => $owner], $_SESSION['id'])) { http_response_code(403); exit; }
    $pdo->prepare("UPDATE announcements SET ADesc = ? WHERE aid = ?")->execute([(string) ($_POST['announ'] ?? ''), (int) $_GET['updateann']]);
    header("location: corner");
    exit;
}

include 'w_conn.php';
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}
$me     = (string) $_SESSION['id'];
$compId = (string) ($_SESSION['CompID'] ?? '');

/* first page of the feed — read BEFORE marking seen so unseen posts get "New".
   Opening the Corner then marks recent announcements seen, which clears the
   badges (sidebar + floating bubble). */
[$feedRows, $feedMore] = wd_corner_feed($pdo, $me);
try { wd_corner_mark_seen($pdo, $me); } catch (Exception $e) { /* badge simply persists until next view */ }

$meRow = $pdo->prepare("SELECT e.EmpFN, p.EmpPPath, p.EmpGender FROM employees e LEFT JOIN empprofiles p ON p.EmpID = e.EmpID WHERE e.EmpID = ? LIMIT 1");
$meRow->execute([$me]);
$meRow  = $meRow->fetch(PDO::FETCH_ASSOC) ?: ['EmpFN' => '', 'EmpPPath' => '', 'EmpGender' => ''];
$first  = $meRow['EmpFN'] === 'admin' ? '' : trim((string) $meRow['EmpFN']);
$hour   = (int) date('G');
$greet  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$canPost = wd_corner_can_post();

/* ---- polls (only once sql/2026-10-28-add-corner-polls.sql has run) ---- */
require_once 'includes/corner-polls-lib.php';
$pollsOn = false; $canPoll = false; $openPolls = []; $closedPolls = [];
try {
    $pollPdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pollPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pollsOn = wd_polls_ready($pollPdo);
    if ($pollsOn) {
        $canPoll     = wd_poll_can_create($pollPdo, $me);
        $openPolls   = wd_polls_load($pollPdo, $me, 'open');
        $closedPolls = wd_polls_load($pollPdo, $me, 'closed', 5);
    }
} catch (PDOException $e) { $pollsOn = false; }
$showPolls = $pollsOn && ($openPolls || $closedPolls || $canPoll);
$unvoted   = count(array_filter($openPolls, function ($p) { return !$p['mine']; }));

/* ---- today strip ---- */
$bdays   = wd_corner_birthdays_today($pdo, $compId, $me);
$holiday = wd_corner_next_holiday($pdo, $compId);
$myBday  = (bool) array_filter($bdays, function ($b) { return $b['me']; });
$title   = ($_SESSION['CompanyName'] ?? '') !== '' ? $_SESSION['CompanyName'] . ' Corner' : 'WeDo Corner';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (modals + popovers + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" type="text/css" href="assets/css/wedo-calendar.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/wedo-calendar.css'); ?>">
    <link rel="stylesheet" href="assets/css/corner-feed.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/corner-feed.css'); ?>">
    <link rel="stylesheet" href="assets/css/corner-polls.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/corner-polls.css'); ?>">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script src="assets/js/corner-feed.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/corner-feed.js'); ?>"></script>
    <script src="assets/js/corner-polls.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/corner-polls.js'); ?>"></script>

    <style>
      .corner-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:18px;align-items:start}
      .corner-main{min-width:0}
      #cornerPolls,#cornerFeed{overflow:visible}   /* post / poll menus may hang past a card's edge */
      @media (max-width:992px){.corner-grid{grid-template-columns:1fr}}
      .corner-card__body{padding:16px 18px}
      .corner-grid .wd-card{min-width:0}
      @media (min-width:993px){.corner-grid .wd-card--cal{position:sticky;top:calc(var(--topbar-h) + 16px)}}

      .corner-legend{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
      .corner-legend span{display:inline-flex;align-items:center;gap:6px;color:var(--text-2);font-size:12px}
      .corner-legend i{width:14px;height:14px;border-radius:3px;display:inline-block}

      .popover{display:inline-block !important}
    </style>

    <script type="text/javascript">
      $(document).ready(function () {

        // Any day cell click (or Enter/Space) → select it and show that day's
        // detail (holiday + birthdays), pre-rendered by PHP as a <template>
        function showDay($cell) {
          var day = $cell.data("day");
          $(".wd-cal .clckday, .wd-cal__hitem").removeClass("is-active");
          $cell.addClass("is-active");
          $(".wd-cal__hitem[data-day='" + day + "']").addClass("is-active");
          var tpl = $(".wd-cal .wd-cal__tpl[data-day='" + day + "']")[0];
          if (tpl) { $("#calDayDetail").html(tpl.innerHTML); }
        }
        $(document).on("click", ".wd-cal .clckday", function () { showDay($(this)); });
        $(document).on("keydown", ".wd-cal .clckday", function (e) {
          if (e.key === "Enter" || e.key === " ") { e.preventDefault(); showDay($(this)); }
        });
        // Clicking a holiday in the list selects that day on the grid
        $(document).on("click", ".wd-cal__hitem", function () {
          showDay($(".wd-cal .clckday[data-day='" + $(this).data("day") + "']"));
        });

        $('[data-toggle="popover"]').popover();

        // Calendar month navigation (AJAX): arrows, Today, and the month/year pickers
        $(document).on("click", ".wd-cal .prev, .wd-cal .next, .wd-cal .today", function () {
          getCalendar($(this).data("month"), $(this).data("year"));
        });
        $(document).on("change", "#currentMonth, #currentYear", function () {
          getCalendar($("#currentMonth").val(), $("#currentYear").val());
        });
        // Left/right arrow keys flip months when the calendar has focus
        $(document).on("keydown", "#calendar-html-output", function (e) {
          if ($(e.target).is("select")) return;
          if (e.key === "ArrowLeft")  { $(".wd-cal .prev").trigger("click"); e.preventDefault(); }
          if (e.key === "ArrowRight") { $(".wd-cal .next").trigger("click"); e.preventDefault(); }
        });

        var calRequest = null;
        function getCalendar(month, year) {
          if (calRequest) { calRequest.abort(); }
          $("#calendar-outer").addClass("is-loading");
          calRequest = $.ajax({
            url: "includes/calendar-ajax.php",
            type: "POST",
            data: { month: month, year: year },
            success: function (response) { $("#calendar-html-output").html(response); },
            error: function (xhr, status) { if (status !== "abort") { $("#calendar-outer").removeClass("is-loading"); } },
            complete: function () { calRequest = null; }
          });
        }
      });
    </script>
</head>

<body>
    <?php $wd_active = 'corner'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p><?php echo htmlspecialchars($greet . ($first !== '' ? ', ' . $first : '')); ?> &middot; <?php echo date('l, F j, Y'); ?></p>
        </div>
    </div>

    <!-- ===== Today ===== -->
    <div class="ct-strip">
        <div class="ct-tile ct-tile--bday<?php echo $myBday ? ' is-me' : ''; ?>">
            <span class="ct-tile__icon"><i class="fa-solid fa-cake-candles"></i></span>
            <div class="ct-tile__main">
                <span class="ct-tile__label">Birthdays today</span>
                <?php if ($myBday): ?>
                    <b class="ct-tile__value">Happy birthday to you! &#127881;</b>
                <?php elseif ($bdays): ?>
                    <b class="ct-tile__value"><?php
                        $shown = array_slice($bdays, 0, 2);
                        echo htmlspecialchars(implode(', ', array_column($shown, 'name')));
                        if (count($bdays) > 2) { echo ' +' . (count($bdays) - 2); }
                    ?></b>
                    <span class="ct-tile__sub">
                        <?php foreach ($shown as $b): ?>
                        <a href="messages?with=<?php echo rawurlencode($b['id']); ?>"><i class="fa-regular fa-comment"></i> Greet <?php echo htmlspecialchars(strtok($b['name'], ' ')); ?></a>
                        <?php endforeach; ?>
                    </span>
                <?php else: ?>
                    <b class="ct-tile__value is-muted">None today</b>
                <?php endif; ?>
            </div>
        </div>

        <div class="ct-tile ct-tile--holiday">
            <span class="ct-tile__icon"><i class="fa-solid fa-umbrella-beach"></i></span>
            <div class="ct-tile__main">
                <span class="ct-tile__label">Next holiday</span>
                <?php if ($holiday):
                    $days = (int) round((strtotime($holiday['date']) - strtotime(date('Y-m-d'))) / 86400); ?>
                    <b class="ct-tile__value"><?php echo htmlspecialchars($holiday['name']); ?></b>
                    <span class="ct-tile__sub"><?php echo date('D, M j', strtotime($holiday['date'])); ?> &middot;
                        <?php echo $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in ' . $days . ' days'); ?></span>
                <?php else: ?>
                    <b class="ct-tile__value is-muted">None scheduled</b>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pollsOn): ?>
        <a class="ct-tile ct-tile--polls" href="#cornerPolls">
            <span class="ct-tile__icon"><i class="fa-solid fa-square-poll-horizontal"></i></span>
            <div class="ct-tile__main">
                <span class="ct-tile__label">Polls</span>
                <b class="ct-tile__value<?php echo $openPolls ? '' : ' is-muted'; ?>"><?php echo $openPolls ? count($openPolls) . ' open' : 'None open'; ?></b>
                <?php if ($unvoted): ?>
                <span class="ct-tile__sub ct-tile__sub--alert"><?php echo $unvoted; ?> waiting for your vote</span>
                <?php elseif ($openPolls): ?>
                <span class="ct-tile__sub">You've voted on all of them</span>
                <?php endif; ?>
            </div>
        </a>
        <?php endif; ?>
    </div>

    <div class="corner-grid">
      <div class="corner-main">

        <?php if ($showPolls): ?>
        <!-- ===== Polls ===== -->
        <section class="wd-card" id="cornerPolls">
            <div class="wd-card__head">
                <h3>Polls</h3>
                <?php if ($canPoll): ?>
                <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-toggle="modal" data-target="#pollModal"><i class="fa-solid fa-square-poll-horizontal"></i> New poll</button>
                <?php endif; ?>
            </div>
            <div class="corner-card__body">
                <div class="cp-list" id="cpOpen">
                    <?php foreach ($openPolls as $p) { echo wd_poll_card($p, $me); } ?>
                </div>
                <div class="cp-empty" id="cpEmpty"<?php echo $openPolls ? ' hidden' : ''; ?>>
                    <div class="cp-empty__icon"><i class="fa-solid fa-square-poll-horizontal"></i></div>
                    <b>No open polls right now</b>
                    <?php if ($canPoll): ?>
                    <span>Ask everyone where to eat, where to go, or which day works.</span>
                    <button type="button" class="wd-btn wd-btn--primary wd-btn--sm" style="margin-top:8px" data-toggle="modal" data-target="#pollModal"><i class="fa-solid fa-plus"></i> Start a poll</button>
                    <?php else: ?>
                    <span>When someone asks the team something, you can vote here.</span>
                    <?php endif; ?>
                </div>
                <?php if ($closedPolls): ?>
                <details class="cp-closed">
                    <summary><i class="fa-solid fa-chevron-right"></i> Recently closed (<?php echo count($closedPolls); ?>)</summary>
                    <div class="cp-list">
                        <?php foreach ($closedPolls as $p) { echo wd_poll_card($p, $me); } ?>
                    </div>
                </details>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== Announcements ===== -->
        <section class="wd-card" id="cornerFeed">
            <div class="wd-card__head">
                <h3>Announcements</h3>
                <div class="cf-filters" role="group" aria-label="Show">
                    <button type="button" data-filter="all" aria-pressed="true">All</button>
                    <button type="button" data-filter="news" aria-pressed="false">News</button>
                    <button type="button" data-filter="bday" aria-pressed="false"><i class="fa-solid fa-cake-candles"></i> Birthdays</button>
                </div>
            </div>
            <div class="corner-card__body">
                <?php if ($canPost): ?>
                <form class="cf-compose" id="cfCompose" autocomplete="off">
                    <img class="cf__avatar" src="<?php echo htmlspecialchars(wd_corner_avatar($meRow['EmpPPath'], $meRow['EmpGender'], __DIR__)); ?>" alt="" width="44" height="44">
                    <div class="cf-compose__main">
                        <input type="text" class="cf-compose__title" maxlength="100" placeholder="Title (optional)" aria-label="Title (optional)">
                        <textarea class="cf-compose__body" rows="1" maxlength="1000" aria-label="Announcement"
                                  placeholder="Share news with everyone<?php echo $first !== '' ? ', ' . htmlspecialchars($first) : ''; ?>&hellip;"></textarea>
                        <div class="cf-compose__bar">
                            <span class="cf-compose__hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> to post &middot; app users get a notification</span>
                            <span class="cf-compose__count">0/1000</span>
                            <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm cf-compose__cancel">Cancel</button>
                            <button type="submit" class="wd-btn wd-btn--primary wd-btn--sm cf-compose__post" disabled><i class="fa-solid fa-paper-plane"></i> Post</button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>

                <div class="cf-list" id="cfList"><?php echo wd_corner_feed_html($feedRows, $me, __DIR__); ?></div>

                <div class="cf-empty" id="cfEmpty"<?php echo $feedRows ? ' hidden' : ''; ?>>
                    <div class="cf-empty__icon"><i class="fa-solid fa-bullhorn"></i></div>
                    <b>No announcements yet</b>
                    <span><?php echo $canPost ? 'Share the first bit of company news above.' : 'Company news will show up here.'; ?></span>
                </div>
                <p class="cf-filterempty" id="cfFilterEmpty" hidden>Nothing here in the posts loaded so far.</p>

                <?php if ($feedMore): $lastRow = end($feedRows); ?>
                <button type="button" class="cf-older" id="cfOlder" data-before="<?php echo (int) $lastRow['aid']; ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i> Show older posts
                </button>
                <?php endif; ?>
            </div>
        </section>
      </div>

        <!-- ===== Calendar ===== -->
        <section class="wd-card wd-card--cal">
            <div class="wd-card__head">
                <h3>Calendar</h3>
                <div class="corner-legend">
                    <span><i style="background:var(--navy)"></i> Today</span>
                    <span title="Regular holiday"><i style="background:var(--danger-bg);border:1px solid var(--danger-text)"></i> Regular</span>
                    <span title="Special holiday"><i style="background:var(--info-bg);border:1px solid var(--info-text)"></i> Special</span>
                    <span title="Birthday of an active employee"><svg viewBox="0 0 24 24" aria-hidden="true" style="width:14px;height:14px;fill:none;stroke:var(--bday-text);stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M5 16.5c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0"/><path d="M12 12V8.5"/><path d="M12 6c.8 0 1.3-.6 1.3-1.3C13.3 3.8 12 2.5 12 2.5s-1.3 1.3-1.3 2.2c0 .7.5 1.3 1.3 1.3z"/></svg> Birthday</span>
                </div>
            </div>
            <div class="corner-card__body">
                <div id="calendar-html-output" tabindex="0" aria-label="Company calendar">
                    <?php echo $phpCalendar->getCalendarHTML(); ?>
                </div>
            </div>
        </section>

    </div>

    <?php include 'includes/wd-footer.php'; ?>

    <div class="cp-toast" id="cfToast" role="status" aria-live="polite"></div>
    <script>window.WD_FEED = { api: "query/corner-feed.php", token: <?php echo json_encode(wd_corner_csrf_token()); ?> };</script>

    <?php if ($pollsOn): ?>
    <div class="cp-toast" id="cpToast" role="status" aria-live="polite"></div>
    <script>window.WD_POLLS = { api: "query/corner-polls.php", token: <?php echo json_encode(wd_poll_csrf_token()); ?> };</script>
    <?php if ($canPoll): ?>
    <!-- ===== New poll ===== -->
    <div class="modal fade" id="pollModal" tabindex="-1" role="dialog" aria-labelledby="pmTitle">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form novalidate autocomplete="off">
                    <div class="pm__head">
                        <div class="pm__icon"><i class="fa-solid fa-square-poll-horizontal"></i></div>
                        <div>
                            <h4 id="pmTitle">New poll</h4>
                            <p>Everyone can vote from the Corner; app users get a notification.</p>
                        </div>
                        <button type="button" class="pm__x" data-dismiss="modal" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    </div>

                    <div class="pm__body">
                        <div class="pm__label" style="margin-top:4px">Quick start</div>
                        <div class="pm__templates">
                            <button type="button" class="pm__chip" data-tpl="lunch">🍱 Lunch</button>
                            <button type="button" class="pm__chip" data-tpl="outing">📍 Team outing</button>
                            <button type="button" class="pm__chip" data-tpl="day">📅 Pick a day</button>
                            <button type="button" class="pm__chip" data-tpl="yesno">👍 Yes / No</button>
                        </div>

                        <label class="pm__label" for="pmQuestion">Question <small class="pm__count">0/200</small></label>
                        <input type="text" class="wd-input pm__q" id="pmQuestion" maxlength="200" placeholder="e.g. Where should we eat on Friday?">
                        <button type="button" class="pm__more"><i class="fa-solid fa-plus"></i> Add details</button>
                        <div id="pmDetailsWrap" hidden>
                            <label class="pm__label" for="pmDetails">Details <small>optional</small></label>
                            <textarea class="wd-textarea" id="pmDetails" rows="2" maxlength="500" placeholder="Budget, time, anything people should know"></textarea>
                        </div>

                        <div class="pm__label">Options <small>2 to 10 &middot; Enter adds the next one</small></div>
                        <div class="pm__opts"></div>
                        <button type="button" class="pm__addopt"><i class="fa-solid fa-plus"></i> Add option</button>

                        <div class="pm__label">Settings</div>
                        <div class="pm__settings">
                            <label class="pm__set"><i class="fa-solid fa-list-check"></i>
                                <span><b>Allow multiple answers</b><small>People can pick more than one option</small></span>
                                <input type="checkbox" id="pmMulti"><span class="pm__sw"></span></label>
                            <label class="pm__set"><i class="fa-solid fa-lightbulb"></i>
                                <span><b>Let people suggest options</b><small>Good for places and food ideas</small></span>
                                <input type="checkbox" id="pmAllowAdd"><span class="pm__sw"></span></label>
                            <label class="pm__set"><i class="fa-solid fa-user-secret"></i>
                                <span><b>Anonymous voting</b><small>Show totals only, never who voted for what</small></span>
                                <input type="checkbox" id="pmAnon"><span class="pm__sw"></span></label>
                        </div>

                        <div class="pm__label">Voting ends</div>
                        <div class="pm__ends" role="radiogroup" aria-label="Voting ends">
                            <input type="radio" name="closes" id="pmEndNone" value="none" checked><label for="pmEndNone">When I close it</label>
                            <input type="radio" name="closes" id="pmEnd1" value="1d"><label for="pmEnd1">In 1 day</label>
                            <input type="radio" name="closes" id="pmEnd3" value="3d"><label for="pmEnd3">In 3 days</label>
                            <input type="radio" name="closes" id="pmEnd7" value="7d"><label for="pmEnd7">In 1 week</label>
                            <input type="radio" name="closes" id="pmEndC" value="custom"><label for="pmEndC">Pick a time</label>
                        </div>
                        <div class="pm__custom" hidden>
                            <input type="datetime-local" class="wd-input" id="pmClosesAt" aria-label="Voting ends at">
                        </div>

                        <p class="pm__err" role="alert" hidden></p>
                    </div>

                    <div class="pm__foot">
                        <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="wd-btn wd-btn--primary pm__submit"><i class="fa-solid fa-paper-plane"></i> Post poll</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</body>
</html>
