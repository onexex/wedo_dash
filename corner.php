<?php

  require_once 'includes/class.calendar.php';
  $phpCalendar = new PHPCalendar ();
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
   if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{
    if(!isset($_COOKIE["WeDoID"])) {

        header ('location: login');
    }else{
        if(!isset($_COOKIE["WeDoID"])) {
          session_destroy();
          header ('location: login');
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
                if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])){
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
  if (isset($_GET['addannoun'])){
      include 'w_conn.php';
      if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
      else{ header ('location: login.php'); }
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

/* Opening the Corner marks every announcement this user hasn't seen yet as
   seen, which clears the unseen-announcement badge in the sidebar. Runs only
   on a normal page load (the handlers above exit on AJAX/redirect). */
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
    try {
        include 'w_conn.php';
        $seenPdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $seenPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        date_default_timezone_set("Asia/Manila");
        $seenNow = date("Y-m-d H:i:s"); // datetime column: 24h, no AM/PM
        // recent announcements this user has not seen yet (same 30-day window
        // the sidebar badge counts, so opening the Corner clears the badge)
        $unseen = $seenPdo->prepare(
            "SELECT a.aid FROM announcements a
             LEFT JOIN annseen s ON s.aid = a.aid AND s.EmpID = :id
             WHERE s.aid IS NULL AND a.ADate >= (NOW() - INTERVAL 30 DAY)");
        $unseen->execute([':id' => $_SESSION['id']]);
        $unseenAids = $unseen->fetchAll(PDO::FETCH_COLUMN);
        if ($unseenAids) {
            $markSeen = $seenPdo->prepare(
                "INSERT INTO annseen (aid, EmpID, FSeenDate, LSeenDate, Status)
                 VALUES (:aid, :id, :sd, :ld, 1)");
            foreach ($unseenAids as $aid) {
                $markSeen->execute([':aid' => $aid, ':id' => $_SESSION['id'], ':sd' => $seenNow, ':ld' => $seenNow]);
            }
        }
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
    <link rel="stylesheet" href="assets/css/wedo-theme.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style-cal.css">

    <script type="text/javascript" src="assets/js/script.js"></script>

    <style>
      /* style-cal.css (loaded above for the calendar widget) sets a global
         body{font-family:tahoma} and a :focus reset that leak onto the themed
         shell. Re-assert the theme here so the sidebar/topbar match the other
         migrated pages. */
      body{font-family:var(--font-body)}
      .wd-app :focus{outline:revert;background:revert}

      .corner-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}
      @media (max-width:992px){.corner-grid{grid-template-columns:1fr}}

      .ann{display:flex;flex-direction:column;gap:12px;max-height:640px;overflow-y:auto;padding-right:4px}
      .cn{background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-lg);padding:14px}
      .cn__top{display:flex;gap:12px;align-items:flex-start}
      .cn .pci-mid{width:54px;height:54px;border-radius:50%;background-position:center;background-size:cover;flex:0 0 54px;background-color:var(--surface)}
      .cn__title{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--text);margin:0 0 2px;font-size:15px;line-height:1.3}
      .cn__title .fa-bullhorn{color:var(--brand)}
      .cn__author{color:var(--text-3);font-size:12px;margin:0}
      .cn__body{color:var(--text-2);margin:10px 0 8px;white-space:pre-wrap;word-break:break-word;font-size:14px}
      .cn__date{color:var(--text-3);font-size:12px;margin:0;display:flex;align-items:center;gap:6px}
      .cn__edit{margin-left:auto;color:var(--text-3);cursor:pointer;flex:0 0 auto}
      .cn__edit:hover{color:var(--brand)}
      .ann-empty{color:var(--text-3);text-align:center;padding:30px 10px}

      .corner-legend{display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
      .corner-legend span{display:inline-flex;align-items:center;gap:6px;color:var(--text-2);font-size:12px}
      .corner-legend i{width:14px;height:14px;border-radius:3px;display:inline-block}

      .popover{display:inline-block !important}
      #modalWarning .modal-body{text-align:center}
      #modalWarning .fa-circle-exclamation{font-size:50px;margin-bottom:10px;color:#d1c156}
    </style>

    <script type="text/javascript">
      $(document).ready(function () {

        $('#myModal').on('shown.bs.modal', function () { $('#desc').focus(); });

        // Holiday day-cell click → reveal that day's holiday detail
        $(document).on("click", ".clckday", function () {
          var ddi = $(this).attr("id");
          $(".hldviewer").hide();
          $("." + ddi).show();
        });

        $('[data-toggle="popover"]').popover();

        // Calendar month navigation (AJAX)
        $(document).on("click", '.prev', function () { getCalendar($(this).data("prev-month"), $(this).data("prev-year")); });
        $(document).on("click", '.next', function () { getCalendar($(this).data("next-month"), $(this).data("next-year")); });
        $(document).on("blur", '#currentYear', function () { getCalendar($('#currentMonth').text(), $('#currentYear').text()); });

        function getCalendar(month, year) {
          $("#body-overlay").show();
          $.ajax({
            url: "includes/calendar-ajax.php",
            type: "POST",
            data: 'month=' + month + '&year=' + year,
            success: function (response) {
              setTimeout(function () { $("#body-overlay").hide(); }, 500);
              $("#calendar-html-output").html(response);
            },
            error: function () {}
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
            <div class="ann">
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

                    <div class="cn__body" id="rdv<?php echo $row2[0]; ?>"><?php echo nl2br(htmlspecialchars($row2['ADesc'])); ?></div>
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
        <section class="wd-card">
            <div class="wd-card__head"><h3>Calendar</h3></div>
            <div class="corner-legend">
                <span><i style="background:#000"></i> Current day</span>
                <span><i style="background:red"></i> Holiday</span>
            </div>
            <div id="calendar-html-output">
                <?php echo $phpCalendar->getCalendarHTML(); ?>
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
