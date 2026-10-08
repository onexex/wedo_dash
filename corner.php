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
  if (isset($_GET['addannoun'])){
      include 'w_conn.php';
      if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
      else{ header ('location: login.php'); exit; }
  try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       }
    catch(PDOException $e)
       {
    die("ERROR: Could not connect. " . $e->getMessage());
       }
         date_default_timezone_set("Asia/Manila");
         $today = date("Y-m-d H:i:s");
       $ann='Announcement';
        $sql = "INSERT INTO announcements (EmpID,Title,ADesc,ADate)
          VALUES (:id,:AnnT,:announ,:dtte)";
           $stmt = $pdo->prepare($sql);
           $stmt->bindParam(':id' ,$_SESSION['id']);
           $stmt->bindParam(':AnnT' ,$ann);
           $stmt->bindParam(':announ' ,$_POST['announ']);
             $stmt->bindParam(':dtte' ,$today);
           $stmt->execute();

           // mobile push: the author's company, on the app
           require_once __DIR__ . '/includes/push-lib.php';
           push_announcement($pdo, (string) $_SESSION['id'], (string) $_POST['announ']);
       return;
}

if (isset($_GET['updateann'])){
      date_default_timezone_set("Asia/Manila");
     include 'w_conn.php';
      try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       }
    catch(PDOException $e)
       {
    die("ERROR: Could not connect. " . $e->getMessage());
       }
        $sql = "UPDATE announcements SET ADesc=:announ  WHERE aid=:id";
           $stmt = $pdo->prepare($sql);
           $stmt->bindParam(':id' ,$_GET['updateann']);
           $stmt->bindParam(':announ' ,$_POST['announ']);
           $stmt->execute();
           header("location: corner");
}

/* Opening the Corner marks every recent announcement this user hasn't seen
   as seen, which clears the unseen badges (sidebar + floating bubble). Runs
   only on a normal page load (the handlers above exit on AJAX/redirect). */
require_once 'includes/corner-lib.php';
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
    try {
        include 'w_conn.php';
        $seenPdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $seenPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        wd_corner_mark_seen($seenPdo, $_SESSION['id']);
    } catch (Exception $e) { /* non-fatal: badge simply persists until next view */ }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else { echo $_SESSION['CompanyName'] . " Corner"; } ?></title>
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

    <script type="text/javascript" src="assets/js/script.js"></script>

    <style>
      .corner-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:18px;align-items:start}
      @media (max-width:992px){.corner-grid{grid-template-columns:1fr}}
      .corner-card__body{padding:16px 18px}
      .corner-grid .wd-card{min-width:0}
      @media (min-width:993px){.corner-grid .wd-card--cal{position:sticky;top:calc(var(--topbar-h) + 16px)}}

      .ann{display:flex;flex-direction:column;gap:12px;max-height:640px;overflow-y:auto;padding-right:4px}
      .cn{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px}
      .cn__top{display:flex;gap:12px;align-items:flex-start}
      .cn .pci-mid{width:54px;height:54px;border-radius:50%;background-position:center;background-size:cover;flex:0 0 54px;background-color:var(--surface)}
      .cn__title{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--text);margin:0 0 2px;font-size:15px;line-height:1.3}
      .cn__title .fa-bullhorn{color:var(--brand)}
      .cn__author{color:var(--text-3);font-size:12px;margin:0}
      .cn__body{color:var(--text-2);margin:10px 0 8px;white-space:pre-wrap;word-break:break-word;font-size:14px}
      .cn__cake{color:var(--brand);margin-left:4px}
      .cn__date{color:var(--text-3);font-size:12px;margin:0;display:flex;align-items:center;gap:6px}
      .cn__edit{margin-left:auto;color:var(--text-3);cursor:pointer;flex:0 0 auto}
      .cn__edit:hover{color:var(--brand)}
      .ann-empty{color:var(--text-3);text-align:center;padding:30px 10px}

      .corner-legend{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
      .corner-legend span{display:inline-flex;align-items:center;gap:6px;color:var(--text-2);font-size:12px}
      .corner-legend i{width:14px;height:14px;border-radius:3px;display:inline-block}

      .popover{display:inline-block !important}
      #modalWarning .modal-body{text-align:center}
      #modalWarning .fa-circle-exclamation{font-size:50px;margin-bottom:10px;color:#d1c156}
    </style>

    <script type="text/javascript">
      $(document).ready(function () {

        $('#myModal').on('shown.bs.modal', function () { $('#desc').focus(); });

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

        // Post a new announcement
        $(".btnsaveann").click(function () {
          if ($("#desc").val().length < 6) {
            alert("Please input Announcement more than 8 letters.");
            return;
          }
          var data = $(".addfrmannouncement").serialize();
          $(this).text("Saving Data ..");
          document.getElementById("saveAnn").disabled = true;
          $.ajax({
            url: 'corner.php?addannoun',
            type: 'post',
            data: data,
            success: function () { location.reload(); }
          });
        });

        // Read more / read less
        $(document).on("click", ".ann .btnrdmore", function () {
          var tid = $(this).attr("id");
          if ($(this).hasClass("cmore")) {
            $.ajax({ url: 'query/Query-IUCorner.php', type: 'post', data: { idn: tid } });
            $("#rdv" + tid).css("display", "none");
            $("#rdmore" + tid).fadeIn(200);
            $("#dtmore" + tid).css("display", "block");
            $(this).text("Read Less..").removeClass("cmore").addClass("cless");
          } else {
            $("#rdv" + tid).css("display", "block");
            $("#rdmore" + tid).css("display", "none");
            $("#dtmore" + tid).css("display", "none");
            $(this).text("Read More..").removeClass("cless").addClass("cmore");
          }
        });
      });
    </script>
</head>

<body>
    <?php $wd_active = 'corner'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1><?php echo htmlspecialchars(($_SESSION['CompanyName'] ?: 'WeDo') . ' Corner'); ?></h1>
            <p>Company announcements and calendar &mdash; <?php echo date('l, F j, Y'); ?></p>
        </div>
        <?php if ($_SESSION['UserType']==1 || $_SESSION['id']=="WeDoinc-002" || $_SESSION['id']=="WeDoinc-006" || $_SESSION['id']=="WeDoinc-003"): ?>
        <button type="button" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#myModal"><i class="fa-solid fa-plus"></i> Add announcement</button>
        <?php endif; ?>
    </div>

    <div class="corner-grid">

        <!-- ===== Announcements ===== -->
        <section class="wd-card">
            <div class="wd-card__head"><h3>Announcements</h3></div>
            <div class="ann corner-card__body">
                <?php
                    try{
                        include 'w_conn.php';
                        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    }
                    catch(PDOException $e)
                    {
                        die("ERROR: Could not connect. " . $e->getMessage());
                    }
                    $id=$_SESSION['id'];
                    $statement = $pdo->prepare("select * from announcements inner join employees on announcements.EmpID=employees.EmpID   order by ADate desc");
                    $statement->execute();
                    $annCount = $statement->rowCount();

                    while ($row2 = $statement->fetch()){
                        $st = $pdo->prepare("select * from empprofiles where EmpID=:id");
                        $st->bindParam(':id' , $row2['EmpID']);
                        $st->execute();

                        $count=$st->rowCount();
                        $row=$st->fetch();
                        if ($count<1){
                            $path="assets/images/profiles/default.png";
                            $url = "'assets/images/profiles/default.png'";
                        }else{
                            $path="assets/images/profiles/default.png";
                            try{
                                if ($row['EmpPPath']==""){
                                    $path="assets/images/profiles/default.png";
                                    $url= 'assets/images/profiles/default.png';
                                }else{
                                    $path=$row['EmpPPath'];
                                    $url=  $row['EmpPPath'];
                                }
                            }
                            catch(Exception $e) {
                                $path="assets/images/profiles/default.png";
                                $url = "'assets/images/profiles/default.png'";
                            }
                        }
                ?>
                <!-- announcement -->
                <div class="cn">
                    <div class="cn__top">
                        <div class="pci-mid" style="background-image: url('<?php if(file_exists($url)){ echo $url; }else{ if ($row['EmpGender']=="Male"){ echo "assets/images/profiles/man_d.jpg"; }else{ echo "assets/images/profiles/woman_d.jpg"; } } ?>');"></div>
                        <div style="flex:1;min-width:0">
                            <h5 class="cn__title">
                                <i class="fa-solid fa-bullhorn" aria-hidden="true"></i>
                                <span style="flex:1;min-width:0"><?php echo htmlspecialchars($row2['Title']); ?></span>
                                <?php if ($_SESSION['id']==$row2['EmpID']): ?>
                                <a class="cn__edit" data-toggle="modal" data-target="#myModal<?php echo $row2[0]; ?>" title="Edit this announcement"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></a>
                                <?php endif; ?>
                            </h5>
                            <p class="cn__author"><?php
                                if ($row2['EmpFN']=="admin") { echo "WeDo Family"; }
                                else { echo htmlspecialchars($row2['EmpFN'] . " " . $row2['EmpLN']); }
                            ?></p>
                        </div>
                    </div>

                    <div class="cn__body" id="rdv<?php echo $row2[0]; ?>"><?php echo nl2br(wd_announcement_body($row2['ADesc'])); ?></div>
                    <p class="cn__date"><i class="fa-regular fa-clock" aria-hidden="true"></i> <?php echo date("F d, Y h:i:s A", strtotime($row2['ADate'])); ?></p>

                    <!-- Edit announcement modal -->
                    <div class="modal" id="myModal<?php echo $row2[0]; ?>">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header" style="background-color:#f93627;color:#fff">
                                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                                    <h4 class="modal-title">Edit announcement</h4>
                                </div>
                                <div class="modal-body">
                                    <form method="post" action="?updateann=<?php echo $row2[0]; ?>" class="frmannouncement">
                                        <div class="form-group">
                                            <label>Description:</label>
                                            <textarea class="form-control" required="required" rows="5" name="announ"><?php echo htmlspecialchars($row2['ADesc']); ?></textarea>
                                        </div>
                                        <button class="wd-btn wd-btn--primary btnupdating" style="width:100%;justify-content:center">Update</button>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end edit modal -->
                </div>
                <!-- end announcement -->
                <?php
                    }
                    if ($annCount < 1) {
                        echo '<div class="ann-empty"><i class="fa-solid fa-bullhorn" style="font-size:28px;display:block;margin-bottom:8px;opacity:.5"></i>No announcements yet.</div>';
                    }
                ?>
            </div>
        </section>

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

    <!-- ===== Add announcement modal ===== -->
    <div class="modal" id="myModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color:#f93627;color:#fff">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                    <h4 class="modal-title">New announcement</h4>
                </div>
                <div class="modal-body">
                    <form method="post" class="addfrmannouncement">
                        <div class="form-group">
                            <label for="desc">Announcement:</label>
                            <textarea class="form-control" minlength="6" required="required" rows="5" name="announ" id="desc"></textarea>
                        </div>
                        <button id="saveAnn" type="button" class="wd-btn wd-btn--primary btnsaveann" style="width:100%;justify-content:center">Save</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Warning modal ===== -->
    <div class="modal" id="modalWarning">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="padding:7px 8px;border:none">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div class="alert alert-danger"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- modal end -->

</body>
</html>
