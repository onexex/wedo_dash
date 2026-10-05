<?php
/* ==========================================================================
   idcard.php  —  Management > ID Card Generator.
   Preview, adjust and print company ID cards (CR80, front + back) for a PVC
   card printer. Card artwork: assets/js/idcard-render.js. Rules (name format,
   ID number, back text): includes/idcard-lib.php. Writes go to
   query/idcard-action.php. Gated by the `idcard` access right.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/idcard-lib.php';

/* access gate BEFORE any output so the redirect can fire (least privilege) */
try {
    $idcpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $idcpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!idc_can_manage($idcpdo)) { header('location: 404?'); exit(); }

$year  = (int) date('Y');
$cards = idc_employees($idcpdo);
$boot  = [
    'cards'   => $cards,
    'back'    => idc_back_config($idcpdo),
    'backValues' => idc_back_values($idcpdo),
    'backSet' => idc_back_is_set($idcpdo),
    'year'    => $year,
    'nextSeq' => idc_next_seq($idcpdo, $year),
    'token'   => idc_csrf_token(),
];
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: 0; };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "ID Card Generator" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <!-- Functional libs -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- card typeface -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;700&display=swap">

    <!-- WeDo design system (after bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <link rel="stylesheet" href="assets/css/idcard.css?v=<?php echo $v('assets/css/idcard.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>
    <script src="assets/js/idcard-render.js?v=<?php echo $v('assets/js/idcard-render.js'); ?>"></script>
  </head>
  <body>
    <?php
      $wd_active = 'idcard';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>ID Card Generator</h1>
          <p>Preview, adjust and print company ID cards — front and back, CR80 size for the card printer.</p>
        </div>
        <div class="idc-headactions">
          <button type="button" class="wd-btn wd-btn--ghost" id="btnCalib" title="Prints a test card with edge and safe-zone marks"><i class="fa-solid fa-ruler-combined"></i> Calibration card</button>
          <button type="button" class="wd-btn wd-btn--primary" id="btnPrintSel" disabled><i class="fa-solid fa-print"></i> Print selected <span class="idc-count" id="selCount" hidden>0</span></button>
        </div>
      </div>

      <div class="idc-layout">
        <!-- ------------------------------------------------ employee list -->
        <section class="wd-card idc-listcard">
          <div class="idc-filters">
            <input type="search" class="wd-input" id="fSearch" placeholder="Search name, ID or position&hellip;" autocomplete="off">
            <div class="idc-filters__row">
              <select class="wd-select" id="fDept"><option value="">All departments</option></select>
              <select class="wd-select" id="fType"><option value="">All types</option></select>
            </div>
            <div class="idc-filters__row idc-filters__row--meta">
              <label class="idc-check"><input type="checkbox" id="fAll"> Select all shown</label>
              <label class="idc-check"><input type="checkbox" id="fResigned"> Include resigned</label>
            </div>
          </div>
          <div class="idc-list" id="empList" role="listbox" aria-label="Employees"></div>
          <div class="idc-listfoot wd-muted" id="listFoot"></div>
        </section>

        <!-- ------------------------------------------------ preview -->
        <section class="wd-card idc-previewcard">
          <div class="wd-card__head">
            <h3 id="pvTitle">Select an employee</h3>
            <span id="pvStatus"></span>
          </div>
          <div class="idc-preview" id="pvBody">
            <div class="idc-sides">
              <figure class="idc-side">
                <div class="idc-cardframe idc-cardframe--front" id="pvFront"></div>
                <figcaption>Front <span class="wd-muted">· drag the photo to move it</span></figcaption>
              </figure>
              <figure class="idc-side">
                <div class="idc-cardframe" id="pvBack"></div>
                <figcaption>Back <span class="wd-muted">·</span> <button type="button" class="idc-linkbtn" id="btnBack"><i class="fa-solid fa-pen"></i> Edit company details</button></figcaption>
              </figure>
            </div>

            <div class="idc-panel">
              <div class="idc-panel__block">
                <div class="idc-eyebrow">Photo</div>
                <div class="idc-zoom">
                  <i class="fa-solid fa-magnifying-glass-minus"></i>
                  <input type="range" id="pvZoom" min="1" max="3" step="0.01" value="1" aria-label="Photo zoom">
                  <i class="fa-solid fa-magnifying-glass-plus"></i>
                </div>
                <div class="idc-btnrow">
                  <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="pvReset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                  <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="pvSaveCrop" disabled><i class="fa-solid fa-floppy-disk"></i> Save position</button>
                  <a class="wd-btn wd-btn--ghost wd-btn--sm" id="pvPhotoLink" href="#" title="Replace the photo on the employee's 201 record"><i class="fa-solid fa-image"></i> Change photo</a>
                </div>
              </div>

              <div class="idc-panel__block">
                <div class="idc-eyebrow">Employee signature</div>
                <div class="idc-signrow">
                  <div class="idc-signbox" id="pvSignBox"></div>
                  <div class="idc-btnrow">
                    <label class="wd-btn wd-btn--ghost wd-btn--sm idc-filebtn" id="pvSignUpload"><i class="fa-solid fa-upload"></i> <span>Upload</span>
                      <input type="file" id="pvSignFile" accept="image/png,image/jpeg"></label>
                    <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="pvSignRemove"><i class="fa-solid fa-trash"></i> Remove</button>
                  </div>
                </div>
                <div class="wd-muted idc-small">Black ink on white paper, scanned or photographed. The paper is removed automatically.</div>
              </div>

              <div class="idc-panel__block">
                <div class="idc-eyebrow">ID number</div>
                <div class="idc-number" id="pvNumber"></div>
                <div class="wd-muted idc-small" id="pvNumberNote"></div>
              </div>

              <ul class="idc-warnings" id="pvWarnings"></ul>

              <button type="button" class="wd-btn wd-btn--primary idc-printone" id="pvPrint"><i class="fa-solid fa-print"></i> <span>Issue &amp; print</span></button>
              <p class="wd-muted idc-small idc-printhint">
                In the print dialog choose <b>Badgy100</b>, <b>Margins: None</b>, <b>Scale: 100</b>.
                The printer does one side at a time: the fronts print first, then you turn the cards over,
                put them back in the feeder and print the backs.
              </p>
            </div>
          </div>
        </section>
      </div>

      <!-- ------------------------------------------------ card back settings -->
      <div class="modal fade" id="mdlBack" tabindex="-1" role="dialog" aria-labelledby="mdlBackTitle">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <form id="frmBack" novalidate>
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="mdlBackTitle">Card back — company details</h4>
              </div>
              <div class="modal-body">
                <div class="idc-backedit">
                  <div class="idc-backedit__form">
                    <div class="idc-notice" id="bkFirstTime" hidden><i class="fa-solid fa-circle-info"></i><span>Before the first card is printed, check these details and click <b>Save</b>. They are filled in from the sample card and may be out of date.</span></div>
                    <p class="wd-muted idc-small" style="margin-bottom:14px">Printed on the back of <b>every</b> card. Type it exactly as it should appear.</p>
                    <?php foreach (idc_back_fields() as $key => [$label, $max, $required]): ?>
                      <div class="wd-field">
                        <label for="bk_<?php echo $key; ?>"><?php echo $e($label); ?><?php echo $required ? '' : ' <span class="wd-muted">(optional)</span>'; ?></label>
                        <input class="wd-input" id="bk_<?php echo $key; ?>" name="<?php echo $key; ?>" maxlength="<?php echo (int) $max; ?>"
                               type="<?php echo $key === 'email' ? 'email' : 'text'; ?>" autocomplete="off">
                        <span class="idc-err" data-err="<?php echo $key; ?>"></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="idc-backedit__preview">
                    <div class="idc-cardframe idc-cardframe--sm" id="bkPreview"></div>
                    <div class="wd-muted idc-small" style="text-align:center;margin-top:8px">Live preview</div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="wd-btn wd-btn--ghost" id="bkDefaults" title="Fill in the original card values">Use original values</button>
                <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
                <button type="submit" class="wd-btn wd-btn--primary" id="bkSave"><i class="fa-solid fa-floppy-disk"></i> Save</button>
              </div>
            </form>
          </div>
        </div>
      </div>

    <?php include 'includes/wd-footer.php'; ?>

    <!-- print source: filled just before window.print(); the only thing printed -->
    <div id="idcPrint" aria-hidden="true"></div>

    <script>
      window.IDC_BOOT = <?php $boot['backDefaults'] = idc_back_defaults(); echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="assets/js/idcard.js?v=<?php echo $v('assets/js/idcard.js'); ?>"></script>
  </body>
</html>
