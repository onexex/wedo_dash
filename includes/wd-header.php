<?php
/* ==========================================================================
   wd-header.php  —  themed app shell (sidebar + topbar) using wedo-theme.css
   Drop-in replacement for includes/header.php on migrated pages.
   A page opts in with:  $wd_active='index'; include 'includes/wd-header.php';
   ...content...        include 'includes/wd-footer.php';
   Preserves the same access-rights gating, user info and notification count.
   ========================================================================== */
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { return; }
include 'w_conn.php';
try {
    $wdpdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $wdpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

/* user + position */
$wdu = $wdpdo->prepare("SELECT e.EmpLN, e.EmpFN, p.PositionDesc
    FROM employees e LEFT JOIN positions p ON e.posid = p.psid WHERE e.EmpID = :id");
$wdu->execute([':id' => $_SESSION['id']]);
$wdrow      = $wdu->fetch();
$wdName     = trim(($wdrow['EmpLN'] ?? '') . ', ' . ($wdrow['EmpFN'] ?? ''));
$wdPosition = ($_SESSION['UserType'] == 1) ? 'Super User' : ($wdrow['PositionDesc'] ?? '');
$wdInitials = strtoupper(substr($wdrow['EmpFN'] ?? '', 0, 1) . substr($wdrow['EmpLN'] ?? '', 0, 1));

/* notification count (mirrors includes/header.php bell) */
if ($_SESSION['UserType'] == 2) {
    $wdnsql = "SELECT
        (SELECT COUNT(*) FROM obs WHERE EmpSID=:id1 AND OBStatus<>1 AND OBStatus<>3) +
        (SELECT COUNT(*) FROM earlyout WHERE EmpISID=:id2 AND Status<>1 AND Status<>3) +
        (SELECT COUNT(*) FROM hleaves WHERE EmpSID=:id3 AND LStatus<>1 AND LStatus<>3) +
        (SELECT COUNT(*) FROM otattendancelog WHERE EmpISID=:id4 AND Status<>1 AND Status<>3) AS n";
} else {
    $wdnsql = "SELECT
        (SELECT COUNT(*) FROM obs WHERE EmpID=:id1 AND OBStatus<>1) +
        (SELECT COUNT(*) FROM earlyout WHERE EmpID=:id2 AND Status<>1) +
        (SELECT COUNT(*) FROM hleaves WHERE EmpID=:id3 AND LStatus<>1) +
        (SELECT COUNT(*) FROM otattendancelog WHERE EmpID=:id4 AND Status<>1) AS n";
}
try {
    $wdnst = $wdpdo->prepare($wdnsql);
    $wdnst->execute([':id1'=>$_SESSION['id'], ':id2'=>$_SESSION['id'], ':id3'=>$_SESSION['id'], ':id4'=>$_SESSION['id']]);
    $nrow = (int) $wdnst->fetchColumn();
} catch (Exception $e) { $nrow = 0; }

/* unseen company announcements (Corner badge): recent announcements with no
   annseen row for me, excluding my own posts. The 30-day window keeps the badge
   a "new announcements" signal — without it, every user would see all ~hundreds
   of historical posts as unseen until they first open the Corner. Cleared when
   I open the Corner page (corner.php marks the same recent set as seen). */
$wdUnseenAnn = 0;
try {
    $wdua = $wdpdo->prepare("SELECT COUNT(*) FROM announcements a
        LEFT JOIN annseen s ON s.aid = a.aid AND s.EmpID = :id
        WHERE s.aid IS NULL AND a.EmpID <> :id2
          AND a.ADate >= (NOW() - INTERVAL 30 DAY)");
    $wdua->execute([':id' => $_SESSION['id'], ':id2' => $_SESSION['id']]);
    $wdUnseenAnn = (int) $wdua->fetchColumn();
} catch (Exception $e) { $wdUnseenAnn = 0; }

/* access rights */
$wdar = $wdpdo->prepare("SELECT * FROM accessrights WHERE EmpID = :id");
$wdar->execute([':id' => $_SESSION['id']]);
$ar = $wdar->fetch();
if (!$ar) { $ar = []; }

$wd_active = $wd_active ?? '';
function wd_on($k, $a) { return $k === $a ? ' is-active' : ''; }
function wd_can($ar, $k) { return isset($ar[$k]) && $ar[$k] == 2; }
?>
<div class="wd-app">
  <aside class="wd-sidebar" id="wdSidebar">
    <div class="wd-brand"><img src="assets/images/logos/wedo-logo.png" alt="WeDo BPO Inc." style="height:46px;width:auto"></div>
    <div class="wd-brand__tag"><?php echo htmlspecialchars($_SESSION['CompanyName'] ?: 'WeDo BPO'); ?></div>

    <a class="wd-nav<?php echo wd_on('index',$wd_active); ?>" href="index"><i class="fa-solid fa-gauge"></i> Home</a>

    <?php
      /* mirror of includes/header.php section gates so nothing is dropped */
      $wd_show_reports = wd_can($ar,'alasv')||wd_can($ar,'lilov')||wd_can($ar,'darv')||wd_can($ar,'access_13')||wd_can($ar,'access_13_attachement')
                       ||wd_can($ar,'eov')||wd_can($ar,'coe')||wd_can($ar,'payslipt')||wd_can($ar,'obv')||wd_can($ar,'atv')
                       ||wd_can($ar,'fdetls')||wd_can($ar,'lcreaditview');
      $wd_show_mgmt = wd_can($ar,'arights')||wd_can($ar,'ams')||wd_can($ar,'payeereg')||wd_can($ar,'bookletreg')||wd_can($ar,'eemployee')
                    ||wd_can($ar,'e201d')||wd_can($ar,'EF')||wd_can($ar,'schedv')||wd_can($ar,'agncy')||wd_can($ar,'comp')||wd_can($ar,'dep')
                    ||wd_can($ar,'pos')||wd_can($ar,'jl')||wd_can($ar,'hmo')||wd_can($ar,'est')||wd_can($ar,'rel')||wd_can($ar,'classf')
                    ||wd_can($ar,'wt')||wd_can($ar,'tlv')||wd_can($ar,'lval')||wd_can($ar,'ur')||wd_can($ar,'otfs')||wd_can($ar,'hldy')
                    ||wd_can($ar,'gprdv')||wd_can($ar,'obval')||wd_can($ar,'eoval')||wd_can($ar,'SPPContrib');
    ?>

    <div class="wd-navsection">
      <button class="wd-navgroup" type="button" onclick="this.closest('.wd-navsection').classList.toggle('is-collapsed')">Modules <i class="fa-solid fa-chevron-down"></i></button>
      <div class="wd-navitems">
        <?php if(wd_can($ar,'alas')): ?><a class="wd-nav<?php echo wd_on('alas',$wd_active); ?>" href="alas"><i class="fa-solid fa-calendar-check"></i> Automated Leave Application</a><?php endif; ?>
        <?php if(wd_can($ar,'checkregister')): ?><a class="wd-nav<?php echo wd_on('checkregister',$wd_active); ?>" href="checkregister"><i class="fa-solid fa-file-lines"></i> Check Register</a><?php endif; ?>
        <?php if(wd_can($ar,'eo')): ?><a class="wd-nav<?php echo wd_on('earlyout',$wd_active); ?>" href="earlyout"><i class="fa-solid fa-calendar-minus"></i> Early Out Application</a><?php endif; ?>
        <?php if(wd_can($ar,'e201')): ?><a class="wd-nav<?php echo wd_on('e201',$wd_active); ?>" href="e201"><i class="fa-solid fa-folder"></i> Electronic 201 File</a><?php endif; ?>
        <?php if(wd_can($ar,'memo')): ?><a class="wd-nav<?php echo wd_on('memo',$wd_active); ?>" href="memo"><i class="fa-solid fa-sticky-note"></i> Memorandum Generator</a><?php endif; ?>
        <?php if(wd_can($ar,'ob')): ?><a class="wd-nav<?php echo wd_on('ob',$wd_active); ?>" href="ob"><i class="fa-solid fa-briefcase"></i> Official Business Trip Tracker</a><?php endif; ?>
        <?php if(wd_can($ar,'ot')): ?><a class="wd-nav<?php echo wd_on('otfilling',$wd_active); ?>" href="otfilling"><i class="fa-solid fa-business-time"></i> Overtime Filing</a><?php endif; ?>
        <?php if(wd_can($ar,'payroll')): ?><a class="wd-nav<?php echo wd_on('payroll',$wd_active); ?>" href="payroll"><i class="fa-solid fa-money-bill-wave"></i> Payroll Management System</a><?php endif; ?>
        <?php if(wd_can($ar,'debitadvise')): ?><a class="wd-nav<?php echo wd_on('debitadvise',$wd_active); ?>" href="debitadvise"><i class="fa-solid fa-file-invoice-dollar"></i> PMS-Debit Advise (Letter)</a><?php endif; ?>
        <?php if(wd_can($ar,'debitadvisesettings')): ?><a class="wd-nav<?php echo wd_on('debitsetting',$wd_active); ?>" href="maintenance?debitsetting"><i class="fa-solid fa-file-invoice-dollar"></i> PMS-Debit Advise (Settings)</a><?php endif; ?>
        <?php if(wd_can($ar,'sob')): ?><a class="wd-nav<?php echo wd_on('SendToOB',$wd_active); ?>" href="SendToOB"><i class="fa-solid fa-plane"></i> Send to OBT Filing</a><?php endif; ?>
      </div>
    </div>

    <?php if($wd_show_reports): ?>
    <div class="wd-navsection is-collapsed">
      <button class="wd-navgroup" type="button" onclick="this.closest('.wd-navsection').classList.toggle('is-collapsed')">Reports <i class="fa-solid fa-chevron-down"></i></button>
      <div class="wd-navitems">
        <?php if(wd_can($ar,'access_13_attachement')): ?><a class="wd-nav<?php echo wd_on('attachement_13',$wd_active); ?>" href="attachement_13"><i class="fa-solid fa-chart-column"></i> 13<sup>th</sup> Month Attachment</a><?php endif; ?>
        <?php if(wd_can($ar,'alasv')): ?><a class="wd-nav<?php echo wd_on('alasviewer',$wd_active); ?>" href="alasviewer"><i class="fa-solid fa-chart-column"></i> ALAS Viewer</a><?php endif; ?>
        <?php if(wd_can($ar,'lilov')): ?><a class="wd-nav<?php echo wd_on('liloviewer',$wd_active); ?>" href="liloviewer"><i class="fa-solid fa-chart-column"></i> Attendance Viewer</a><?php endif; ?>
        <?php if(wd_can($ar,'darv')): ?><a class="wd-nav<?php echo wd_on('dar',$wd_active); ?>" href="dar"><i class="fa-solid fa-chart-column"></i> Daily Activity Viewer</a><?php endif; ?>
        <?php if(wd_can($ar,'eov')): ?><a class="wd-nav<?php echo wd_on('earlyoutviewer',$wd_active); ?>" href="earlyoutviewer"><i class="fa-solid fa-chart-column"></i> Early Out Viewer</a><?php endif; ?>
        <?php if(wd_can($ar,'fdetls')): ?><a class="wd-nav<?php echo wd_on('FamilyDetails',$wd_active); ?>" href="FamilyDetails"><i class="fa-solid fa-chart-column"></i> Family Details</a><?php endif; ?>
        <?php if(wd_can($ar,'lcreaditview')): ?><a class="wd-nav<?php echo wd_on('leavecredit',$wd_active); ?>" href="leavecredit"><i class="fa-solid fa-wallet"></i> Leave Credit Viewer</a><?php endif; ?>
        <?php if(wd_can($ar,'coe')): ?><a class="wd-nav<?php echo wd_on('coe',$wd_active); ?>" href="coe"><i class="fa-solid fa-file"></i> My Documents (COE)</a><?php endif; ?>
        <?php if(wd_can($ar,'payslipt')): ?><a class="wd-nav<?php echo wd_on('payslip',$wd_active); ?>" href="payslip"><i class="fa-solid fa-file"></i> My Documents (Payslip)</a><?php endif; ?>
        <?php if(wd_can($ar,'obv')): ?><a class="wd-nav<?php echo wd_on('obviewer',$wd_active); ?>" href="obviewer"><i class="fa-solid fa-chart-column"></i> Official Business Viewer</a><?php endif; ?>
        <?php if(wd_can($ar,'atv')): ?><a class="wd-nav<?php echo wd_on('overtimeviewer',$wd_active); ?>" href="overtimeviewer"><i class="fa-solid fa-chart-column"></i> Overtime Viewer</a><?php endif; ?>
        <?php if(wd_can($ar,'access_13')): ?><a class="wd-nav<?php echo wd_on('generalreport',$wd_active); ?>" href="generalreport"><i class="fa-solid fa-chart-column"></i> YTD 13<sup>th</sup> Month</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if($wd_show_mgmt): ?>
    <div class="wd-navsection is-collapsed">
      <button class="wd-navgroup" type="button" onclick="this.closest('.wd-navsection').classList.toggle('is-collapsed')">Management <i class="fa-solid fa-chevron-down"></i></button>
      <div class="wd-navitems">
        <?php if(wd_can($ar,'arights')): ?><a class="wd-nav<?php echo wd_on('accessrights',$wd_active); ?>" href="accessrights.php"><i class="fa-solid fa-lock"></i> Access Rights</a><?php endif; ?>
        <?php if(wd_can($ar,'ams')): ?><a class="wd-nav<?php echo wd_on('ams',$wd_active); ?>" href="ams"><i class="fa-solid fa-box-archive"></i> Archived Management System</a><?php endif; ?>
        <?php if(wd_can($ar,'bookletreg')): ?><a class="wd-nav<?php echo wd_on('bookletregistry',$wd_active); ?>" href="bookletregistry"><i class="fa-solid fa-box-archive"></i> Booklet Management System</a><?php endif; ?>
        <?php if(wd_can($ar,'e201d')): ?><a class="wd-nav<?php echo wd_on('e201files',$wd_active); ?>" href="e201files"><i class="fa-solid fa-file-pdf"></i> Electronic 201 Document</a><?php endif; ?>
        <?php if(wd_can($ar,'EF')): ?><a class="wd-nav<?php echo wd_on('scheduler',$wd_active); ?>" href="scheduler"><i class="fa-solid fa-calendar-days"></i> Employee Scheduler</a><?php endif; ?>
        <?php if(wd_can($ar,'eemployee')): ?><a class="wd-nav<?php echo wd_on('newemployee',$wd_active); ?>" href="newemployee"><i class="fa-solid fa-user-plus"></i> Enroll Employee</a><?php endif; ?>
        <?php if(wd_can($ar,'payeereg')): ?><a class="wd-nav<?php echo wd_on('payeereg',$wd_active); ?>" href="payeereg"><i class="fa-solid fa-box-archive"></i> Payee Management System</a><?php endif; ?>
        <?php if(wd_can($ar,'schedv')): ?><a class="wd-nav<?php echo wd_on('schedviewer',$wd_active); ?>" href="schedviewer"><i class="fa-solid fa-chart-column"></i> Schedule Viewer</a><?php endif; ?>

        <div class="wd-navsection wd-navsub is-collapsed">
          <button class="wd-navgroup" type="button" onclick="this.closest('.wd-navsection').classList.toggle('is-collapsed')"><i class="fa-solid fa-wrench" style="font-size:11px"></i> Maintenance <i class="fa-solid fa-chevron-down"></i></button>
          <div class="wd-navitems">
            <?php if(wd_can($ar,'agncy')): ?><a class="wd-nav" href="maintenance?agency"><i class="fa-solid fa-square-plus"></i> Agencies</a><?php endif; ?>
            <?php if(wd_can($ar,'classf')): ?><a class="wd-nav" href="maintenance?classification"><i class="fa-solid fa-square-plus"></i> Classifications</a><?php endif; ?>
            <?php if(wd_can($ar,'comp')): ?><a class="wd-nav" href="maintenance?company"><i class="fa-solid fa-square-plus"></i> Companies</a><?php endif; ?>
            <?php if(wd_can($ar,'dep')): ?><a class="wd-nav" href="maintenance?department"><i class="fa-solid fa-square-plus"></i> Departments</a><?php endif; ?>
            <?php if(wd_can($ar,'est')): ?><a class="wd-nav" href="maintenance?employeestatus"><i class="fa-solid fa-square-plus"></i> Employee Status</a><?php endif; ?>
            <?php if(wd_can($ar,'eoval')): ?><a class="wd-nav" href="maintenance?eovalidation"><i class="fa-solid fa-square-plus"></i> EO Validation</a><?php endif; ?>
            <a class="wd-nav" href="maintenance?parentalfamilydetails"><i class="fa-solid fa-square-plus"></i> Family Details for Parental</a>
            <?php if(wd_can($ar,'hmo')): ?><a class="wd-nav" href="maintenance?hmo"><i class="fa-solid fa-square-plus"></i> HMOs</a><?php endif; ?>
            <?php if(wd_can($ar,'hldy')): ?><a class="wd-nav" href="maintenance?holiday"><i class="fa-solid fa-square-plus"></i> Holiday Logger</a><?php endif; ?>
            <?php if(wd_can($ar,'jl')): ?><a class="wd-nav" href="maintenance?joblevel"><i class="fa-solid fa-square-plus"></i> Job Levels</a><?php endif; ?>
            <?php if(wd_can($ar,'lval')): ?><a class="wd-nav" href="maintenance?leavevalidation"><i class="fa-solid fa-square-plus"></i> Leave Validation</a><?php endif; ?>
            <?php if(wd_can($ar,'gprdv')): ?><a class="wd-nav" href="maintenance?lilovalidation"><i class="fa-solid fa-square-plus"></i> Lilo Validation</a><?php endif; ?>
            <?php if(wd_can($ar,'obval')): ?><a class="wd-nav" href="maintenance?obvalidation"><i class="fa-solid fa-square-plus"></i> OB Validation</a><?php endif; ?>
            <?php if(wd_can($ar,'otfs')): ?><a class="wd-nav" href="maintenance?otfsm"><i class="fa-solid fa-square-plus"></i> OT Filing System Maintenance</a><?php endif; ?>
            <?php if(wd_can($ar,'SPPContrib')): ?><a class="wd-nav" href="maintenance?pagibig"><i class="fa-solid fa-square-plus"></i> Pagibig Contribution</a><?php endif; ?>
            <?php if(wd_can($ar,'SPPContrib')): ?><a class="wd-nav" href="maintenance?philhealth"><i class="fa-solid fa-square-plus"></i> PhilHealth Contribution</a><?php endif; ?>
            <?php if(wd_can($ar,'pos')): ?><a class="wd-nav" href="maintenance?position"><i class="fa-solid fa-square-plus"></i> Positions</a><?php endif; ?>
            <?php if(wd_can($ar,'rel')): ?><a class="wd-nav" href="maintenance?relationship"><i class="fa-solid fa-square-plus"></i> Relationships</a><?php endif; ?>
            <?php if(wd_can($ar,'SPPContrib')): ?><a class="wd-nav" href="maintenance?silloan"><i class="fa-solid fa-square-plus"></i> SIL LOAN</a><?php endif; ?>
            <?php if(wd_can($ar,'SPPContrib')): ?><a class="wd-nav" href="maintenance?sss"><i class="fa-solid fa-square-plus"></i> SSS Contribution</a><?php endif; ?>
            <?php if(wd_can($ar,'tlv')): ?><a class="wd-nav" href="maintenance?typesofleave"><i class="fa-solid fa-square-plus"></i> Types of Leaves</a><?php endif; ?>
            <?php if(wd_can($ar,'ur')): ?><a class="wd-nav" href="maintenance?userrole"><i class="fa-solid fa-square-plus"></i> User Roles</a><?php endif; ?>
            <?php if(wd_can($ar,'wt')): ?><a class="wd-nav" href="maintenance?worktime"><i class="fa-solid fa-square-plus"></i> Work Shifts</a><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if(wd_can($ar,'gcorner')):
      $wdCornerName = trim($_SESSION['CompanyName'] . ' Corner');
      $wdCornerTip  = $wdUnseenAnn > 0
        ? $wdCornerName . ' — ' . $wdUnseenAnn . ' new announcement' . ($wdUnseenAnn > 1 ? 's' : '')
        : $wdCornerName;
    ?>
    <a class="wd-nav wd-nav--badge<?php echo wd_on('corner',$wd_active); ?>" href="corner" title="<?php echo htmlspecialchars($wdCornerTip); ?>">
      <i class="fa-solid fa-bullhorn"></i>
      <span class="wd-nav__label"><?php echo htmlspecialchars($wdCornerName); ?></span>
      <?php if($wdUnseenAnn > 0): ?><span class="wd-nav__badge"><?php echo $wdUnseenAnn > 99 ? '99+' : (int)$wdUnseenAnn; ?></span><?php endif; ?>
    </a><?php endif; ?>
    <?php if($_SESSION['UserType']==5): ?><a class="wd-nav" href="Reset"><i class="fa-solid fa-triangle-exclamation"></i> Reset Data</a><?php endif; ?>
  </aside>

  <div class="wd-main">
    <header class="wd-topbar">
      <button class="wd-iconbtn wd-menu-toggle" type="button" onclick="document.querySelector('.wd-app').classList.toggle('is-collapsed')" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
      <div class="wd-search"><i class="fa-solid fa-magnifying-glass"></i><input placeholder="Search&hellip;"></div>
      <div style="flex:1"></div>
      <a href="notifications.php" class="wd-iconbtn" title="<?php echo (int)$nrow; ?> notification(s)" aria-label="Notifications"><i class="fa-solid fa-bell"></i><?php if($nrow>0): ?><span class="wd-iconbtn__dot"></span><?php endif; ?></a>
      <div class="wd-user" onclick="this.classList.toggle('is-open');event.stopPropagation();">
        <div class="wd-avatar"><?php echo htmlspecialchars($wdInitials); ?></div>
        <div><div class="wd-user__name"><?php echo htmlspecialchars($wdName); ?></div><div class="wd-user__role"><?php echo htmlspecialchars($wdPosition); ?></div></div>
        <i class="fa-solid fa-chevron-down wd-user__caret"></i>
        <div class="wd-usermenu" onclick="event.stopPropagation();">
          <div class="wd-usermenu__head"><div class="n"><?php echo htmlspecialchars($wdName); ?></div><div class="r"><?php echo htmlspecialchars($wdPosition); ?></div></div>
          <a class="wd-usermenu__item" data-toggle="modal" data-target="#changepass" href="#"><i class="fa-solid fa-gear"></i> Change password</a>
          <a class="wd-usermenu__item wd-usermenu__item--danger" href="login.php?logout"><i class="fa-solid fa-right-from-bracket"></i> Sign out</a>
        </div>
      </div>
    </header>
    <script>
      if(window.innerWidth<=900){var a=document.querySelector('.wd-app');if(a)a.classList.add('is-collapsed');}
      // full label as a hover tooltip, so ellipsis-truncated menu items stay readable
      document.querySelectorAll('.wd-sidebar .wd-nav').forEach(function(n){ if(!n.title) n.title = n.textContent.trim(); });
    </script>
    <main class="wd-content">
      <div class="wd-content-inner">
