<?php
/* =============================================================================
 * includes/e201-profile.php — the Electronic 201 profile body.
 *
 * Shared by e201.php (your own profile, rendered with the page) and
 * query/Query-e201.php (another employee, injected into #e201 by live search),
 * so both views stay identical.
 *
 * Expects from the caller:
 *   $con      mysqli connection        $q       EmpID to render
 *   $rowbtn   accessrights row         $isSelf  true when $q is the viewer
 *   $mnum     unread message count (only used when $isSelf)
 *   $servername/$db/$username/$password  (from w_conn.php)
 * Leaves $res, $ISname and $eid set for the caller (chat panel / JD modal).
 * ========================================================================== */

if (!function_exists('e2h')) {
  function e2h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

  // value or an em-dash placeholder
  function e2v($v) {
    $v = trim((string)$v);
    return $v === '' ? '<span class="e2-empty">&mdash;</span>' : e2h($v);
  }

  // DB dates are Y-m-d with 0000-00-00 meaning "unset"
  function e2date($d) {
    $d = trim((string)$d);
    if ($d === '' || strpos($d, '0000') === 0 || !strtotime($d)) { return null; }
    return date_create($d);
  }

  function e2datefmt($d) {
    $dt = e2date($d);
    return $dt ? $dt->format('M j, Y') : '<span class="e2-empty">&mdash;</span>';
  }

  // "3 yrs 4 mos" between two dates (or until today)
  function e2span($from, $to = null) {
    $a = e2date($from);
    if (!$a) { return ''; }
    $b = e2date($to) ?: date_create('today');
    $i = date_diff($a, $b);
    $parts = [];
    if ($i->y) { $parts[] = $i->y . ' yr' . ($i->y > 1 ? 's' : ''); }
    if ($i->m) { $parts[] = $i->m . ' mo' . ($i->m > 1 ? 's' : ''); }
    return $parts ? implode(' ', $parts) : 'Less than a month';
  }

  function e2money($n) { return number_format((float)$n, 2); }
}

$sql = "SELECT empdetails.Seq_ID,hmo.HMO_PROVIDER,agency.AgencyName,empdetails.EmpID,empdetails.EmpDateHired,empdetails.EmpDateResigned,empstatus.EmpStatDesc,
  workschedule.TimeFrom,workschedule.TimeTo,workdays.WDesc,companies.CompanyDesc,empdetails2.EmpBasic,
  empdetails2.EmpHRate,empdetails2.EmpAllowance,employees.EmpFN AS fn,employees.EmpLN AS ln,employees.EmpMN AS mn,
  empprofiles.EmpAddress1,empprofiles.EmpDOB,empprofiles.EmpGender,empprofiles.EmpEmail,empprofiles.EmpMobile,
  empprofiles.EmpPPNo,empprofiles.EmpPINo,empprofiles.EmpPHNo,empprofiles.EmpSSS,empprofiles.EmpTIN,
  empprofiles.EmpUMIDNo,empprofiles.EmpCitezen,empprofiles.EmpReligion,empprofiles.EmpPhone,
  empprofiles.EmpPP,empprofiles.EmpPPSD,empprofiles.EmpPPDept,empprofiles.EmpPPPos,empprofiles.EmpCS,
  empprofiles.EmpHMONumber,empdetails.EmpUN,
  empprofiles.EmpAddDis,empprofiles.EmpAddCity,empprofiles.EmpAddProv,empprofiles.EmpAddZip,empprofiles.EmpAddCountry,empprofiles.EmpPPED,empprofiles.EmpPPIA,
  empprofiles.EmpPPAth,estatus.StatusEmpDesc,t.EmployeeIDNumber,t.EmpFN,t.EmpLN,t.EmpMN,positions.PositionDesc,positions.PSID,departments.DepartmentDesc,joblevel.jobLevelDesc,employees.EmpSuffix
FROM empdetails
LEFT JOIN empstatus ON empdetails.EmpStatID=empstatus.EmpStatID
LEFT JOIN hmo ON hmo.HMO_ID=empdetails.HMO_ID
LEFT JOIN agency ON agency.AgencyID=empdetails.AgencyID
LEFT JOIN workschedule ON empdetails.EmpWSID=workschedule.WorkSchedID
LEFT JOIN workdays ON empdetails.EmpRDID=workdays.WID
LEFT JOIN companies ON empdetails.EmpCompID=companies.CompanyID
LEFT JOIN empdetails2 ON empdetails2.EmpID = empdetails.EmpID
LEFT JOIN employees ON empdetails.EmpISID=employees.EmpID
LEFT JOIN empprofiles ON empprofiles.EmpID=empdetails.EmpID
LEFT JOIN employees AS t ON empprofiles.EmpID=t.EmpID
LEFT JOIN estatus ON estatus.ID=t.EmpStatusID
LEFT JOIN positions ON positions.PSID=t.PosID
LEFT JOIN joblevel ON positions.EmpJobLevelID=joblevel.jobLevelID
LEFT JOIN departments ON empdetails.EmpdepID=departments.DepartmentID
WHERE empdetails.EmpID = '" . mysqli_real_escape_string($con, $q) . "'";

$result = mysqli_query($con, $sql);
$res    = $result ? mysqli_fetch_array($result) : null;

if (!$res) { ?>
  <div class="e2-notfound">
    <i class="fa-solid fa-user-slash"></i>
    <h3>No employee found</h3>
    <p>Try searching by last name or employee ID.</p>
  </div>
<?php
  return;
}

$eid    = $res['EmpID'];
$ISname = trim(preg_replace('/\s+/', ' ', $res['fn'] . ' ' . $res['mn'] . ' ' . $res['ln']));
$profile = trim(preg_replace('/\s+/', ' ',
  ucfirst($res['EmpFN']) . ' ' . ucfirst($res['EmpMN']) . ' ' . ucfirst($res['EmpLN']) . ' ' . ucfirst($res['EmpSuffix'])));
$canUpdate = isset($rowbtn['updte']) && $rowbtn['updte'] == 2;

// Photo — file_exists() is resolved against the app root, because this partial
// also runs from query/ where a relative check would always miss.
$photo = trim((string)$res['EmpPPAth']);
$hasPhoto = $photo !== '' && is_file(__DIR__ . '/../' . $photo);
$initials = strtoupper(substr(trim($res['EmpFN']), 0, 1) . substr(trim($res['EmpLN']), 0, 1));

// Employment status → pill tone
$status = trim((string)$res['StatusEmpDesc']);
$statusTone = ['Employed' => 'ok', 'Resigned' => 'warn', 'Terminated' => 'danger'][$status] ?? 'info';
// a stale resigned date on an active employee shouldn't cut their tenure short
$resigned = $status !== 'Employed' ? e2date($res['EmpDateResigned']) : null;
$tenure   = e2span($res['EmpDateHired'], $resigned ? $res['EmpDateResigned'] : null);

$dob = e2date($res['EmpDOB']);
$age = $dob ? date_diff($dob, date_create('today'))->y : null;

$address = trim(preg_replace('/\s+/', ' ', implode(' ', [
  $res['EmpAddress1'], $res['EmpAddDis'], $res['EmpAddCity'], $res['EmpAddProv'], $res['EmpAddZip'], $res['EmpAddCountry'],
])));

$sched = '';
if (trim((string)$res['TimeFrom']) !== '') {
  $sched = date('g:i A', strtotime($res['TimeFrom'])) . ' – ' . date('g:i A', strtotime($res['TimeTo']));
}

$pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>

<!-- ============================== HERO ============================== -->
<section class="e2-hero">
  <div class="e2-hero__cover"></div>

  <div class="e2-hero__body">
    <?php if ($hasPhoto) { ?>
      <img class="e2-avatar" id="prof-img" src="<?php echo e2h($photo); ?>" alt="<?php echo e2h($profile); ?>">
    <?php } else { ?>
      <div class="e2-avatar e2-avatar--initials" id="prof-img"><?php echo e2h($initials ?: '?'); ?></div>
    <?php } ?>

    <div class="e2-ident">
      <h2 class="e2-name" id="emp-name"><?php echo e2h($profile); ?></h2>
      <div class="e2-role" id="emp-pos">
        <?php echo e2v($res['PositionDesc']); ?>
        <?php if (trim((string)$res['DepartmentDesc']) !== '') { ?>
          <span class="e2-dot"></span><?php echo e2h($res['DepartmentDesc']); ?>
        <?php } ?>
      </div>
      <div class="e2-tags">
        <?php if ($status !== '') { ?>
          <span class="wd-pill wd-pill--<?php echo $statusTone; ?>"><i class="fa-solid fa-circle"></i><?php echo e2h($status); ?></span>
        <?php } ?>
        <?php if (trim((string)$res['EmpStatDesc']) !== '') { ?>
          <span class="e2-tag"><i class="fa-solid fa-id-badge"></i><?php echo e2h($res['EmpStatDesc']); ?></span>
        <?php } ?>
        <?php if (trim((string)$res['CompanyDesc']) !== '') { ?>
          <span class="e2-tag" id="emp-company"><i class="fa-solid fa-building"></i><?php echo e2h($res['CompanyDesc']); ?></span>
        <?php } ?>
        <?php if (trim((string)$res['AgencyName']) !== '') { ?>
          <span class="e2-tag" id="emp-company2"><i class="fa-solid fa-handshake"></i><?php echo e2h($res['AgencyName']); ?></span>
        <?php } ?>
      </div>
    </div>

    <div class="e2-actions">
      <?php if ($isSelf) { ?>
        <a class="e2-act" id="msg" href="messages" title="Messages">
          <i class="fa-regular fa-envelope"></i><span>Messages</span>
          <?php if ($mnum > 0) { ?><b class="e2-act__badge"><?php echo (int)$mnum; ?></b><?php } ?>
        </a>
        <button type="button" class="e2-act" id="sndmessage" title="Message your immediate superior">
          <i class="fa-regular fa-comment"></i><span>Message IS</span>
        </button>
      <?php } ?>
      <?php if ($canUpdate) { ?>
        <button type="button" class="e2-act" id="btnprint" onclick="printDiv();" title="Print 201 file">
          <i class="fa-solid fa-print"></i><span>Print</span>
        </button>
        <a class="e2-act e2-act--primary" id="Updateinfo" href="UpdateEmployeeInfo?sid=<?php echo e2h($eid); ?>" title="Update information">
          <i class="fa-solid fa-pen-to-square"></i><span>Edit profile</span>
        </a>
      <?php } ?>
      <?php if ($_SESSION['UserType'] != 3) { ?>
        <button type="button" class="e2-act e2-act--icon" id="changepasskey" data-toggle="modal" data-target="#e201ResetPass" title="Reset password">
          <i class="fa-solid fa-key"></i>
        </button>
      <?php } ?>
    </div>
  </div>

  <!-- quick facts strip -->
  <div class="e2-facts">
    <div class="e2-fact">
      <span class="e2-fact__icon"><i class="fa-solid fa-hashtag"></i></span>
      <div><small>Employee ID</small><strong><?php echo e2v($eid); ?></strong></div>
    </div>
    <div class="e2-fact">
      <span class="e2-fact__icon"><i class="fa-solid fa-calendar-check"></i></span>
      <div><small>Date hired</small><strong><?php echo e2datefmt($res['EmpDateHired']); ?></strong>
        <?php if ($tenure) { ?><em><?php echo e2h($tenure); ?><?php echo $resigned ? ' tenure' : ' with us'; ?></em><?php } ?></div>
    </div>
    <div class="e2-fact">
      <span class="e2-fact__icon"><i class="fa-solid fa-user-tie"></i></span>
      <div><small>Immediate superior</small><strong><?php echo e2v($ISname); ?></strong></div>
    </div>
    <div class="e2-fact">
      <span class="e2-fact__icon"><i class="fa-solid fa-clock"></i></span>
      <div><small>Work schedule</small><strong><?php echo e2v($sched); ?></strong>
        <?php if (trim((string)$res['WDesc']) !== '') { ?><em>Rest day: <?php echo e2h($res['WDesc']); ?></em><?php } ?></div>
    </div>
  </div>
</section>

<div class="e2-grid">
  <!-- ============================== MAIN ============================== -->
  <div class="e2-col">

    <section class="e2-card">
      <header class="e2-card__head"><h3><i class="fa-solid fa-address-card"></i>Personal &amp; contact</h3></header>
      <dl class="e2-dl">
        <div class="e2-dl__row e2-dl__row--full"><dt>Address</dt><dd><?php echo e2v($address); ?></dd></div>
        <div class="e2-dl__row"><dt>Date of birth</dt><dd><?php echo e2datefmt($res['EmpDOB']); ?>
          <?php if ($age !== null) { ?><span class="e2-sub"><?php echo $age; ?> years old</span><?php } ?></dd></div>
        <div class="e2-dl__row"><dt>Gender</dt><dd><?php echo e2v($res['EmpGender']); ?></dd></div>
        <div class="e2-dl__row"><dt>Civil status</dt><dd><?php echo e2v($res['EmpCS']); ?></dd></div>
        <div class="e2-dl__row"><dt>Citizenship</dt><dd><?php echo e2v($res['EmpCitezen']); ?></dd></div>
        <div class="e2-dl__row"><dt>Religion</dt><dd><?php echo e2v($res['EmpReligion']); ?></dd></div>
        <div class="e2-dl__row"><dt>Email</dt><dd>
          <?php if (trim((string)$res['EmpEmail']) !== '') { ?>
            <a href="mailto:<?php echo e2h(trim($res['EmpEmail'])); ?>"><?php echo e2h($res['EmpEmail']); ?></a>
          <?php } else { echo e2v(''); } ?></dd></div>
        <div class="e2-dl__row"><dt>Mobile</dt><dd><?php echo e2v($res['EmpPhone']); ?></dd></div>
        <div class="e2-dl__row"><dt>Home no.</dt><dd><?php echo e2v($res['EmpMobile']); ?></dd></div>
      </dl>
    </section>

    <section class="e2-card">
      <header class="e2-card__head"><h3><i class="fa-solid fa-briefcase"></i>Employment status</h3></header>
      <dl class="e2-dl">
        <div class="e2-dl__row"><dt>Username</dt><dd class="empidjd"><?php echo e2h($res['EmpUN']); ?></dd></div>
        <div class="e2-dl__row"><dt>Status</dt><dd><?php echo e2v($status); ?></dd></div>
        <div class="e2-dl__row"><dt>Classification</dt><dd><?php echo e2v($res['EmpStatDesc']); ?></dd></div>
        <div class="e2-dl__row"><dt>Department</dt><dd><?php echo e2v($res['DepartmentDesc']); ?></dd></div>
        <div class="e2-dl__row"><dt>Position</dt><dd><?php echo e2v($res['PositionDesc']); ?></dd></div>
        <div class="e2-dl__row"><dt>Job level</dt><dd><?php echo e2v($res['jobLevelDesc']); ?></dd></div>
        <div class="e2-dl__row"><dt>Date hired</dt><dd><?php echo e2datefmt($res['EmpDateHired']); ?></dd></div>
        <div class="e2-dl__row"><dt>Date resigned</dt><dd><?php echo e2datefmt($res['EmpDateResigned']); ?></dd></div>
        <div class="e2-dl__row"><dt>HMO provider</dt><dd><?php echo e2v($res['HMO_PROVIDER']); ?></dd></div>
        <div class="e2-dl__row"><dt>HMO number</dt><dd><?php echo e2v($res['EmpHMONumber']); ?></dd></div>
      </dl>

      <div class="e2-salary">
        <div class="e2-salary__head">
          <span><i class="fa-solid fa-wallet"></i>Salary details</span>
          <button type="button" class="e2-salary__toggle">
            <i class="fa-regular fa-eye"></i><span class="e2-salary__show">Show</span><span class="e2-salary__hide">Hide</span>
          </button>
        </div>
        <div class="e2-salary__grid">
          <div><small>Basic</small><strong>₱ <?php echo e2money($res['EmpBasic']); ?></strong></div>
          <div><small>Allowance</small><strong>₱ <?php echo e2money($res['EmpAllowance']); ?></strong></div>
          <div><small>Hourly rate</small><strong>₱ <?php echo e2money($res['EmpHRate']); ?></strong></div>
        </div>
      </div>
    </section>

    <section class="e2-card">
      <header class="e2-card__head"><h3><i class="fa-solid fa-graduation-cap"></i>Educational background</h3></header>
      <ol class="e2-timeline">
        <?php
        $stmt = $pdo->prepare("select * from empeducationalbackground where Program = :p and EmpID = :id");
        foreach (['Tertiary' => 'College', 'Secondary' => 'High school', 'Primary' => 'Elementary'] as $prog => $label) {
          $stmt->execute([':p' => $prog, ':id' => $q]);
          $ed = $stmt->fetch() ?: [];
          $school = trim((string)($ed['Name_of_School'] ?? ''));
          $years  = trim(($ed['Year_Started'] ?? '') . ((($ed['Year_End'] ?? '') !== '') ? ' – ' . $ed['Year_End'] : ''));
        ?>
          <li class="e2-timeline__item<?php echo $school === '' ? ' is-empty' : ''; ?>">
            <span class="e2-timeline__label"><?php echo $label; ?></span>
            <strong><?php echo $school !== '' ? e2h($school) : 'Not provided'; ?></strong>
            <?php if ($years !== '' && $years !== '–') { ?><span class="e2-sub"><?php echo e2h($years); ?></span><?php } ?>
            <?php if (trim((string)($ed['School_Address'] ?? '')) !== '') { ?>
              <span class="e2-sub"><i class="fa-solid fa-location-dot"></i><?php echo e2h($ed['School_Address']); ?></span>
            <?php } ?>
          </li>
        <?php } ?>
      </ol>
    </section>

    <section class="e2-card">
      <header class="e2-card__head"><h3><i class="fa-solid fa-heart-pulse"></i>Emergency contacts</h3></header>
      <?php
      $stmt = $pdo->prepare("select * from fdetails where FDetID = :id");
      $stmt->execute([':id' => $q]);
      $ice = $stmt->fetchAll();
      if (!$ice) { ?>
        <p class="e2-none">No emergency contacts on file.</p>
      <?php } else { ?>
        <div class="e2-contacts">
          <?php foreach ($ice as $f) {
            $isIce = strcasecmp(trim((string)$f['FICE']), 'Yes') === 0;
            $fi = strtoupper(substr(trim((string)$f['FName']), 0, 1)); ?>
            <div class="e2-contact<?php echo $isIce ? ' is-ice' : ''; ?>">
              <span class="e2-contact__avatar"><?php echo e2h($fi ?: '?'); ?></span>
              <div class="e2-contact__body">
                <strong><?php echo e2v($f['FName']); ?></strong>
                <span class="e2-sub"><?php echo e2v($f['FRel']); ?>
                  <?php if ($isIce) { ?><b class="e2-ice">ICE</b><?php } ?></span>
                <?php if (trim((string)$f['FContact']) !== '') { ?>
                  <span class="e2-sub"><i class="fa-solid fa-phone"></i><?php echo e2h($f['FContact']); ?></span>
                <?php } ?>
                <?php if (trim((string)$f['FAdd']) !== '') { ?>
                  <span class="e2-sub"><i class="fa-solid fa-location-dot"></i><?php echo e2h($f['FAdd']); ?></span>
                <?php } ?>
              </div>
            </div>
          <?php } ?>
        </div>
      <?php } ?>
    </section>
  </div>

  <!-- ============================== SIDE ============================== -->
  <div class="e2-col">

    <section class="e2-card">
      <header class="e2-card__head"><h3><i class="fa-solid fa-shield-halved"></i>Government IDs</h3></header>
      <ul class="e2-ids">
        <?php
        $ids = [
          ['SSS',        $res['EmpSSS'],    'fa-landmark'],
          ['PhilHealth', $res['EmpPHNo'],   'fa-notes-medical'],
          ['Pag-IBIG',   $res['EmpPINo'],   'fa-house-chimney'],
          ['TIN',        $res['EmpTIN'],    'fa-file-invoice'],
          ['UMID',       $res['EmpUMIDNo'], 'fa-id-card'],
        ];
        foreach ($ids as $id) { ?>
          <li>
            <span class="e2-ids__icon"><i class="fa-solid <?php echo $id[2]; ?>"></i></span>
            <span class="e2-ids__label"><?php echo $id[0]; ?></span>
            <span class="e2-ids__val"><?php echo e2v($id[1]); ?></span>
          </li>
        <?php } ?>
        <li>
          <span class="e2-ids__icon"><i class="fa-solid fa-passport"></i></span>
          <span class="e2-ids__label">Passport
            <?php if (e2date($res['EmpPPED']) || trim((string)$res['EmpPPIA']) !== '') { ?>
              <span class="e2-sub">
                <?php if (e2date($res['EmpPPED'])) { ?>Expires <?php echo e2datefmt($res['EmpPPED']); ?><?php } ?>
                <?php if (trim((string)$res['EmpPPIA']) !== '') { ?> &middot; <?php echo e2h($res['EmpPPIA']); ?><?php } ?>
              </span>
            <?php } ?>
          </span>
          <span class="e2-ids__val"><?php echo e2v($res['EmpPPNo']); ?></span>
        </li>
      </ul>
    </section>

    <section class="e2-card">
      <header class="e2-card__head"><h3><i class="fa-solid fa-list-check"></i>Job description</h3></header>
      <ul class="e2-jd" id="jdview">
        <?php
        $jd = mysqli_query($con, "select * from jobdescription inner join empjobdesc on jobdescription.JD_ID=empjobdesc.JID where empjobdesc.EmpID='" . mysqli_real_escape_string($con, $eid) . "'");
        $jdn = 0;
        while ($row3 = mysqli_fetch_array($jd)) { $jdn++; ?>
          <li><?php echo e2h($row3['JDescription']); ?></li>
        <?php }
        if (!$jdn) { ?><li class="e2-none">No job description assigned.</li><?php } ?>
      </ul>
    </section>

    <section class="e2-card">
      <header class="e2-card__head"><h3><i class="fa-solid fa-route"></i>Work history</h3></header>
      <ol class="e2-timeline">
        <li class="e2-timeline__item is-current">
          <span class="e2-timeline__label">Current</span>
          <strong><?php echo e2v($res['PositionDesc']); ?></strong>
          <span class="e2-sub"><?php echo e2h(trim((string)$res['CompanyDesc'])); ?><?php
            if (trim((string)$res['DepartmentDesc']) !== '') { echo ' &middot; ' . e2h($res['DepartmentDesc']); } ?></span>
          <span class="e2-sub">Since <?php echo e2datefmt($res['EmpDateHired']); ?></span>
        </li>
        <?php $hasPrev = trim($res['EmpPP'] . $res['EmpPPPos'] . $res['EmpPPDept']) !== ''; ?>
        <li class="e2-timeline__item<?php echo $hasPrev ? '' : ' is-empty'; ?>">
          <span class="e2-timeline__label">Previous</span>
          <?php if ($hasPrev) { ?>
            <strong><?php echo e2v($res['EmpPPPos']); ?></strong>
            <span class="e2-sub"><?php echo e2h($res['EmpPP']); ?><?php
              if (trim((string)$res['EmpPPDept']) !== '') { echo ' &middot; ' . e2h($res['EmpPPDept']); } ?></span>
            <?php if (trim((string)$res['EmpPPSD']) !== '') { ?><span class="e2-sub"><?php echo e2h($res['EmpPPSD']); ?></span><?php } ?>
          <?php } else { ?>
            <strong>No previous employment on file</strong>
          <?php } ?>
        </li>
      </ol>
    </section>

    <section class="e2-card">
      <?php
      $files = mysqli_query($con, "select * from empe201files where EMPID='" . mysqli_real_escape_string($con, $q) . "'");
      $fileCount = $files ? mysqli_num_rows($files) : 0;
      ?>
      <header class="e2-card__head">
        <h3><i class="fa-solid fa-folder-open"></i>201 files</h3>
        <span class="e2-count"><?php echo $fileCount; ?></span>
      </header>
      <?php if (!$fileCount) { ?>
        <p class="e2-none">No documents uploaded yet.</p>
      <?php } else { ?>
        <div class="e2-files">
          <?php while ($r = mysqli_fetch_array($files)) {
            // stored as "<label>_<EmpID>_<stamp>.pdf" — show just the label
            $label = preg_replace('/_[^_]+_\d+\.pdf$/i', '', $r['EmpfileN']);
            $label = preg_replace('/\.pdf$/i', '', $label); ?>
            <!-- opens in the shared #e201PdfViewer (e201.php); href keeps middle-click / new tab working -->
            <a href="<?php echo e2h($r['EmpProFPath']); ?>" class="e2-file" target="_blank" rel="noopener"
               data-pdf="<?php echo e2h($r['EmpProFPath']); ?>" data-title="<?php echo e2h($label); ?>"
               title="<?php echo e2h($r['EmpfileN']); ?>">
              <i class="fa-solid fa-file-pdf"></i>
              <span><?php echo e2h($label); ?><small>PDF document</small></span>
            </a>
          <?php } ?>
        </div>
      <?php } ?>
    </section>
  </div>
</div>

<?php if ($_SESSION['UserType'] != 3) { ?>
  <!-- reset password confirm -->
  <div class="modal mdlsc" id="e201ResetPass">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title">Reset password</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          Reset the password of <strong><?php echo e2h($profile); ?></strong> to the default?
        </div>
        <div class="modal-footer">
          <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-dismiss="modal">Cancel</button>
          <button type="button" id="<?php echo e2h($eid); ?>" class="wd-btn wd-btn--primary wd-btn--sm btnyespass">Reset password</button>
        </div>
      </div>
    </div>
  </div>
<?php } ?>
