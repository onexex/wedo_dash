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

/* conversations with unread messages (topbar envelope) */
$wdUnreadMsg = 0;
try {
    require_once __DIR__ . '/messages-lib.php';
    $wdUnreadMsg = msg_unread_threads($wdpdo, (string) $_SESSION['id']);
} catch (Exception $e) { $wdUnreadMsg = 0; }

/* access rights */
$wdar = $wdpdo->prepare("SELECT * FROM accessrights WHERE EmpID = :id");
$wdar->execute([':id' => $_SESSION['id']]);
$ar = $wdar->fetch();
if (!$ar) { $ar = []; }

/* pending profile change requests (reviewers only) */
$wdPcrCount = 0;
if (isset($ar['updte']) && $ar['updte'] == 2) {
    require_once __DIR__ . '/profile-change.php';
    $wdPcrCount = pcr_pending_count($wdpdo);
}

$wd_active = $wd_active ?? '';
function wd_on($k, $a) { return $k === $a ? ' is-active' : ''; }
function wd_can($ar, $k) { return isset($ar[$k]) && $ar[$k] == 2; }
?>
<?php
  /* --------------------------------------------------------------------------
     Sidebar menu. One list drives the markup: each page = [access, active key,
     link, icon, label(, badge)]. access = an accessrights column (shown when
     == 2), a list of columns (any of them), or true (everyone). Icons are
     Lucide symbols bundled in assets/icons/wd-nav.svg (only the ones used).
     -------------------------------------------------------------------------- */
  function wd_icon($name, $cls = '') {
      static $v = null;
      if ($v === null) { $v = @filemtime(dirname(__DIR__) . '/assets/icons/wd-nav.svg') ?: 0; }   // cache-buster
      return '<svg class="wd-i' . ($cls ? ' ' . $cls : '') . '" aria-hidden="true"><use href="assets/icons/wd-nav.svg?v=' . $v . '#i-' . $name . '"/></svg>';
  }
  function wd_allowed($ar, $access) {
      if ($access === true) { return true; }
      foreach ((array) $access as $k) { if (wd_can($ar, $k)) { return true; } }
      return false;
  }
  $wdBadge = function ($n, $title = '') {
      if ($n <= 0) { return ''; }
      return '<span class="wd-nav__badge"' . ($title !== '' ? ' title="' . htmlspecialchars($title) . '"' : '') . '>' . ($n > 99 ? '99+' : (int) $n) . '</span>';
  };

  $wdSections = [
    ['key' => 'modules', 'label' => 'Modules', 'icon' => 'layout-grid', 'tone' => 'mod',
     'badge' => $wdPcrCount, 'badgeTitle' => 'Profile change requests awaiting review', 'items' => [
      ['alas',                'alas',            'alas',                     'calendar-check', 'Automated Leave Application'],
      ['checkregister',       'checkregister',   'checkregister',            'receipt-text',   'Check Register'],
      ['eo',                  'earlyout',        'earlyout',                 'door-open',      'Early Out Application'],
      ['e201',                'e201',            'e201',                     'folder-open',    'Electronic 201 File'],
      ['updte',               'profilerequests', 'profilerequests',          'user-check',     'Profile Change Requests', $wdPcrCount],
      ['memo',                'memo',            'memo',                     'file-pen-line',  'Memorandum Generator'],
      ['ob',                  'ob',              'ob',                       'route',          'Official Business Trip Tracker'],
      ['ot',                  'otfilling',       'otfilling',                'clock-plus',     'Overtime Filing'],
      ['payroll',             'payroll',         'payroll',                  'banknote',       'Payroll Management System'],
      ['debitadvise',         'debitadvise',     'debitadvise',              'file-text',      'PMS-Debit Advise (Letter)'],
      ['debitadvisesettings', 'debitsetting',    'maintenance?debitsetting', 'file-cog',       'PMS-Debit Advise (Settings)'],
      ['sob',                 'SendToOB',        'SendToOB',                 'send',           'Send to OBT Filing'],
    ]],
    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'tone' => 'rep', 'items' => [
      ['access_13_attachement', 'attachement_13', 'attachement_13', 'paperclip',       '13<sup>th</sup> Month Attachment'],
      ['alasv',                 'alasviewer',     'alasviewer',     'calendar-search', 'ALAS Viewer'],
      ['lilov',                 'liloviewer',     'liloviewer',     'clock',           'Attendance Viewer'],
      ['darv',                  'dar',            'dar',            'activity',        'Daily Activity Viewer'],
      ['eov',                   'earlyoutviewer', 'earlyoutviewer', 'log-out',         'Early Out Viewer'],
      ['fdetls',                'FamilyDetails',  'FamilyDetails',  'users',           'Family Details'],
      [['lcreaditview', 'lcreditedit'], 'leavecredit', 'leavecredit', 'wallet',      'Leave Credit Viewer'],
      ['coe',                   'coe',            'coe',            'file-badge',      'My Documents (COE)'],
      ['payslipt',              'payslip',        'payslip',        'receipt',         'My Documents (Payslip)'],
      ['obv',                   'obviewer',       'obviewer',       'map',             'Official Business Viewer'],
      ['atv',                   'overtimeviewer', 'overtimeviewer', 'timer',           'Overtime Viewer'],
      ['access_13',             'generalreport',  'generalreport',  'calendar-range',  'YTD 13<sup>th</sup> Month'],
    ]],
    ['key' => 'management', 'label' => 'Management', 'icon' => 'briefcase-business', 'tone' => 'mgt', 'items' => [
      ['arights',    'accessrights',    'accessrights.php', 'shield-check',   'Access Rights'],
      ['ams',        'ams',             'ams',              'archive',        'Archived Management System'],
      ['bookletreg', 'bookletregistry', 'bookletregistry',  'book-open',      'Booklet Management System'],
      ['e201d',      'e201files',       'e201files',        'file-stack',     'Electronic 201 Document'],
      ['EF',         'scheduler',       'scheduler',        'calendar-days',  'Employee Scheduler'],
      ['eemployee',  'newemployee',     'newemployee',      'user-plus',      'Enroll Employee'],
      ['idcard',     'idcard',          'idcard',           'id-card',        'ID Card Generator'],
      ['payeereg',   'payeereg',        'payeereg',         'book-user',      'Payee Management System'],
      ['schedv',     'schedviewer',     'schedviewer',      'calendar-clock', 'Schedule Viewer'],
    ]],
    ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'wrench', 'tone' => 'mnt', 'items' => [
      ['agncy',      'agency',                'maintenance?agency',                'building-2',      'Agencies'],
      ['classf',     'classification',        'maintenance?classification',        'tags',            'Classifications'],
      ['comp',       'company',               'maintenance?company',               'building',        'Companies'],
      ['dep',        'department',            'maintenance?department',            'network',         'Departments'],
      ['est',        'employeestatus',        'maintenance?employeestatus',        'user-cog',        'Employee Status'],
      ['eoval',      'eovalidation',          'maintenance?eovalidation',          'door-open',       'EO Validation'],
      ['pfam',       'parentalfamilydetails', 'maintenance?parentalfamilydetails', 'baby',            'Family Details for Parental'],
      ['hmo',        'hmo',                   'maintenance?hmo',                   'heart-pulse',     'HMOs'],
      ['hldy',       'holiday',               'maintenance?holiday',               'calendar-heart',  'Holiday Logger'],
      ['jl',         'joblevel',              'maintenance?joblevel',              'layers',          'Job Levels'],
      ['logintheme', 'logintheme',            'logintheme',                        'palette',         'Login Theme'],
      ['lval',       'leavevalidation',       'maintenance?leavevalidation',       'calendar-cog',    'Leave Validation'],
      ['gprdv',      'lilovalidation',        'maintenance?lilovalidation',        'fingerprint',     'Lilo Validation'],
      ['obval',      'obvalidation',          'maintenance?obvalidation',          'map-pin-check',   'OB Validation'],
      ['otfs',       'otfsm',                 'maintenance?otfsm',                 'clock-alert',     'OT Filing System Maintenance'],
      ['SPPContrib', 'pagibig',               'maintenance?pagibig',               'piggy-bank',      'Pagibig Contribution'],
      ['SPPContrib', 'philhealth',            'maintenance?philhealth',            'stethoscope',     'PhilHealth Contribution'],
      ['pos',        'position',              'maintenance?position',              'badge',           'Positions'],
      ['rel',        'relationship',          'maintenance?relationship',          'heart-handshake', 'Relationships'],
      ['SPPContrib', 'silloan',               'maintenance?silloan',               'hand-coins',      'SIL LOAN'],
      ['SPPContrib', 'sss',                   'maintenance?sss',                   'shield',          'SSS Contribution'],
      ['tlv',        'typesofleave',          'maintenance?typesofleave',          'list-checks',     'Types of Leaves'],
      ['ur',         'userrole',              'maintenance?userrole',              'key-round',       'User Roles'],
      ['wt',         'worktime',              'maintenance?worktime',              'sun-moon',        'Work Shifts'],
    ]],
  ];

  /* one top-level menu row: icon chip + label (+ badge) */
  $wdTop = function ($key, $href, $icon, $label, $tone = 'top', $badge = '', $extra = '') use ($wd_active) {
      return '<a class="wd-nav wd-nav--top' . wd_on($key, $wd_active) . '" href="' . $href . '"' . $extra . '>'
           . '<span class="wd-nav__chip wd-tone--' . $tone . '">' . wd_icon($icon) . '</span>'
           . '<span class="wd-nav__label">' . $label . '</span>' . $badge . '</a>';
  };
?>
<div class="wd-app">
  <script>try{if(localStorage.getItem('wd-rail')==='1'){document.currentScript.parentNode.classList.add('is-rail');}}catch(e){}</script>
  <aside class="wd-sidebar" id="wdSidebar">
    <div class="wd-brand">
      <img class="wd-brand__img" src="assets/images/logos/wedo-logo.png" alt="WeDo BPO Inc." style="height:40px;width:auto">
      <span class="wd-brand__mark" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['CompanyName'] ?: 'W', 0, 1))); ?></span>
      <button class="wd-railbtn" type="button" id="wdRailBtn" aria-label="Collapse menu" title="Collapse menu"><?php echo wd_icon('panel-left-close', 'wd-railbtn__close') . wd_icon('panel-left-open', 'wd-railbtn__open'); ?></button>
    </div>
    <div class="wd-brand__tag"><?php echo htmlspecialchars($_SESSION['CompanyName'] ?: 'WeDo BPO'); ?></div>

    <label class="wd-navsearch" title="Search pages (Ctrl+K)">
      <?php echo wd_icon('search'); ?>
      <input type="search" id="wdNavSearch" placeholder="Search pages" autocomplete="off" aria-label="Search pages" aria-controls="wdNavList">
      <kbd class="wd-navsearch__kbd">Ctrl K</kbd>
    </label>

    <nav class="wd-navlist" id="wdNavList" aria-label="Main menu">
      <?php if(wd_can($ar,'dashboard')) { echo $wdTop('dashboard', 'dashboard', 'chart-pie', 'Dashboard'); } ?>
      <?php echo $wdTop('index', 'index', 'house', 'Home'); ?>
      <?php echo $wdTop('messages', 'messages', 'message-circle', 'Messages', 'top',
                        '<span class="wd-nav__badge" data-wd-unread' . ($wdUnreadMsg > 0 ? '' : ' hidden') . '>' . ($wdUnreadMsg > 99 ? '99+' : (int) $wdUnreadMsg) . '</span>'); ?>

      <?php foreach ($wdSections as $sec):
        $items = array_filter($sec['items'], function ($it) use ($ar) { return wd_allowed($ar, $it[0]); });
        if (!$items) { continue; }
      ?>
      <div class="wd-navsection is-collapsed" data-sec="<?php echo $sec['key']; ?>">
        <button class="wd-navgroup" type="button" aria-expanded="false" title="<?php echo $sec['label']; ?>">
          <span class="wd-nav__chip wd-tone--<?php echo $sec['tone']; ?>"><?php echo wd_icon($sec['icon']); ?></span>
          <span class="wd-navgroup__label"><?php echo $sec['label']; ?><?php echo $wdBadge($sec['badge'] ?? 0, $sec['badgeTitle'] ?? ''); ?></span>
          <?php echo wd_icon('chevron-right', 'wd-navgroup__chev'); ?>
        </button>
        <div class="wd-navitems">
          <?php foreach ($items as $it): ?>
          <a class="wd-nav wd-nav--sub<?php echo wd_on($it[1], $wd_active); ?>" href="<?php echo $it[2]; ?>"><?php echo wd_icon($it[3]); ?><span class="wd-nav__label"><?php echo $it[4]; ?></span><?php echo $wdBadge($it[5] ?? 0); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if(wd_can($ar,'gcorner')):
        $wdCornerName = trim($_SESSION['CompanyName'] . ' Corner');
        $wdCornerTip  = $wdUnseenAnn > 0
          ? $wdCornerName . ' — ' . $wdUnseenAnn . ' new announcement' . ($wdUnseenAnn > 1 ? 's' : '')
          : $wdCornerName;
        echo $wdTop('corner', 'corner', 'megaphone', htmlspecialchars($wdCornerName), 'cor', $wdBadge($wdUnseenAnn), ' title="' . htmlspecialchars($wdCornerTip) . '"');
      endif; ?>
      <?php if($_SESSION['UserType']==5) { echo $wdTop('reset', 'Reset', 'triangle-alert', 'Reset Data', 'cor'); } ?>
      <p class="wd-navempty" id="wdNavEmpty" hidden>No pages match.</p>
    </nav>

    <div class="wd-account">
      <div class="wd-avatar wd-account__av" title="<?php echo htmlspecialchars($wdName); ?>"><?php echo htmlspecialchars($wdInitials); ?></div>
      <div class="wd-account__who">
        <div class="wd-account__name"><?php echo htmlspecialchars($wdName); ?></div>
        <div class="wd-account__role"><?php echo htmlspecialchars($wdPosition); ?></div>
      </div>
      <a class="wd-account__btn" data-toggle="modal" data-target="#changepass" href="#" title="Change password" aria-label="Change password"><?php echo wd_icon('settings'); ?></a>
      <a class="wd-account__btn" href="login.php?logout" title="Sign out" aria-label="Sign out"><?php echo wd_icon('log-out'); ?></a>
    </div>
  </aside>

  <div class="wd-main">
    <header class="wd-topbar">
      <button class="wd-iconbtn wd-menu-toggle" type="button" onclick="document.querySelector('.wd-app').classList.toggle('is-collapsed')" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
      <div style="flex:1"></div>
      <?php $wdMsgLabel = $wdUnreadMsg > 0 ? (int)$wdUnreadMsg . ' unread conversation' . ($wdUnreadMsg > 1 ? 's' : '') : 'Messages'; ?>
      <a href="messages" class="wd-iconbtn wd-inbox<?php echo $wdUnreadMsg > 0 ? ' has-unread' : ''; ?>" id="wdMsgBtn" data-unread="<?php echo (int)$wdUnreadMsg; ?>" title="<?php echo $wdMsgLabel; ?>" aria-label="<?php echo $wdMsgLabel; ?>"><i class="fa-regular fa-envelope"></i><span class="wd-inbox__badge"<?php echo $wdUnreadMsg > 0 ? '' : ' hidden'; ?>><?php echo $wdUnreadMsg > 99 ? '99+' : (int)$wdUnreadMsg; ?></span></a>
      <a href="notifications.php" class="wd-iconbtn" title="<?php echo (int)$nrow; ?> notification(s)" aria-label="Notifications"><i class="fa-solid fa-bell"></i><?php if($nrow>0): ?><span class="wd-iconbtn__dot"></span><?php endif; ?></a>
      <div class="wd-user" onclick="this.classList.toggle('is-open');event.stopPropagation();">
        <div class="wd-avatar"><?php echo htmlspecialchars($wdInitials); ?></div>
        <i class="fa-solid fa-chevron-down wd-user__caret"></i>
        <div class="wd-usermenu" onclick="event.stopPropagation();">
          <div class="wd-usermenu__head"><div class="n"><?php echo htmlspecialchars($wdName); ?></div><div class="r"><?php echo htmlspecialchars($wdPosition); ?></div></div>
          <a class="wd-usermenu__item" data-toggle="modal" data-target="#changepass" href="#"><i class="fa-solid fa-gear"></i> Change password</a>
          <a class="wd-usermenu__item wd-usermenu__item--danger" href="login.php?logout"><i class="fa-solid fa-right-from-bracket"></i> Sign out</a>
        </div>
      </div>
    </header>
    <script>
      /* Sidebar behaviour. Runs right after the sidebar is parsed, before first paint. */
      (function(){
        var app = document.querySelector('.wd-app');
        var side = document.getElementById('wdSidebar');
        if(!app || !side) return;
        if(window.innerWidth<=900){ app.classList.add('is-collapsed'); }
        function sGet(k){ try{ return localStorage.getItem(k); }catch(e){ return null; } }
        function sSet(k,v){ try{ localStorage.setItem(k,v); }catch(e){} }
        var sections = side.querySelectorAll('.wd-navsection');

        function setOpen(sec, open){
          sec.classList.toggle('is-collapsed', !open);
          var b = sec.querySelector('.wd-navgroup');
          if(b) b.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        // accordion: one section open at a time, so the menu never grows into a long scroll
        function openOnly(sec){
          sections.forEach(function(o){ setOpen(o, o === sec); });
          sSet('wd-nav-open', sec ? sec.getAttribute('data-sec') : '');
        }

        // open the section holding the current page and keep it lit ("you are in Reports");
        // otherwise reopen whichever section was open last
        var active = side.querySelector('.wd-nav.is-active');
        var cur = active && active.closest('.wd-navsection');
        if(cur){ cur.classList.add('is-current'); setOpen(cur, true); }
        else {
          var last = sGet('wd-nav-open');
          var sec = last && side.querySelector('.wd-navsection[data-sec="'+last+'"]');
          if(sec) setOpen(sec, true);
        }

        sections.forEach(function(sec){
          sec.querySelector('.wd-navgroup').addEventListener('click', function(){
            if(app.classList.contains('is-rail')){ setRail(false); openOnly(sec); return; }   // icon strip: expand and show it
            if(sec.classList.contains('is-collapsed')){ openOnly(sec); sec.scrollIntoView({block:'nearest'}); }
            else { setOpen(sec, false); sSet('wd-nav-open', ''); }
          });
        });

        // full label as a hover tooltip (labels can be ellipsised; the icon strip shows none)
        side.querySelectorAll('.wd-nav').forEach(function(n){ if(!n.title) n.title = n.textContent.trim(); });

        /* ---- collapse to an icon strip (desktop), remembered ---- */
        var railBtn = document.getElementById('wdRailBtn');
        function setRail(on){
          app.classList.toggle('is-rail', on);
          sSet('wd-rail', on ? '1' : '0');
          if(railBtn){ var t = on ? 'Expand menu' : 'Collapse menu'; railBtn.title = t; railBtn.setAttribute('aria-label', t); }
        }
        if(railBtn){
          if(app.classList.contains('is-rail')){ railBtn.title = 'Expand menu'; railBtn.setAttribute('aria-label', 'Expand menu'); }
          railBtn.addEventListener('click', function(){ setRail(!app.classList.contains('is-rail')); });
        }

        /* ---- search pages ---- */
        var q = document.getElementById('wdNavSearch'), empty = document.getElementById('wdNavEmpty');
        var links = side.querySelectorAll('.wd-navlist .wd-nav');
        var saved = null;   // which sections were open before searching
        function filter(){
          var term = q.value.trim().toLowerCase();
          side.classList.toggle('is-searching', term !== '');
          if(term && saved === null){ saved = []; sections.forEach(function(s){ if(!s.classList.contains('is-collapsed')) saved.push(s); }); }
          var hits = 0;
          links.forEach(function(a){
            var sec = a.closest('.wd-navsection');
            var hay = (a.textContent + ' ' + (sec ? sec.querySelector('.wd-navgroup__label').textContent : '')).toLowerCase();
            var show = !term || hay.indexOf(term) >= 0;
            a.classList.toggle('is-hidden', !show);
            if(show && term) hits++;
          });
          sections.forEach(function(s){
            var any = !!s.querySelector('.wd-nav:not(.is-hidden)');
            s.classList.toggle('is-hidden', term !== '' && !any);
            if(term) setOpen(s, any);
          });
          if(!term && saved !== null){ sections.forEach(function(s){ setOpen(s, saved.indexOf(s) >= 0); }); saved = null; }
          empty.hidden = !term || hits > 0;
        }
        if(q){
          // icon strip: the search icon expands the menu and puts the cursor in the box
          q.closest('.wd-navsearch').addEventListener('click', function(){
            if(app.classList.contains('is-rail')){ setRail(false); setTimeout(function(){ q.focus(); }, 0); }
          });
          q.addEventListener('input', filter);
          q.addEventListener('keydown', function(e){
            if(e.key === 'Enter'){
              var first = side.querySelector('.wd-navlist .wd-nav:not(.is-hidden)');
              if(first && q.value.trim()){ e.preventDefault(); window.location.href = first.href; }
            } else if(e.key === 'Escape'){
              q.value = ''; filter(); q.blur();
            } else if(e.key === 'ArrowDown'){
              var f = side.querySelector('.wd-navlist .wd-nav:not(.is-hidden)');
              if(f){ e.preventDefault(); f.focus(); }
            }
          });
        }
        // Ctrl+K / Cmd+K: jump to the page search from anywhere
        document.addEventListener('keydown', function(e){
          if((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K') && q){
            e.preventDefault();
            app.classList.remove('is-collapsed');
            if(app.classList.contains('is-rail')) setRail(false);
            q.focus(); q.select();
          }
        });
      })();
    </script>
    <main class="wd-content">
      <div class="wd-content-inner">
