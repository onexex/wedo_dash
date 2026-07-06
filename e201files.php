<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {} else { header('location: login.php'); exit; }

  include_once('w_conn.php');

  /* ---- Handle a new 201 document upload (AJAX) ----
     The upload modal posts here via FormData and expects a JSON reply, so the
     page never reloads and every failure mode gets an explicit message. Only a
     genuinely-uploaded PDF for a valid employee is stored. */
  if (isset($_GET['addfiles'])) {
    header('Content-Type: application/json');
    $resp = ['ok' => false, 'msg' => ''];

    /* A POST larger than post_max_size arrives with $_POST/$_FILES emptied by PHP,
       which would otherwise look like "no employee selected". Report it plainly. */
    if (empty($_POST) && empty($_FILES) &&
        isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
      $resp['msg'] = 'The file is too large for the server to accept (limit ' . ini_get('post_max_size') . '). Please upload a smaller PDF.';
      echo json_encode($resp);
      exit;
    }

    /* Employee IDs are strings (e.g. "WeDoinc-0010"), NOT integers — keep as a
       trimmed string and escape for SQL; never intval() them. */
    $id      = trim($_POST['txtcount'] ?? '');
    $docname = trim($_POST['txtfilename'] ?? '');
    $err     = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($id === '') {
      $resp['msg'] = 'Please select an employee first.';
    } elseif ($docname === '') {
      $resp['msg'] = 'Please enter a document name.';
    } elseif ($err === UPLOAD_ERR_NO_FILE) {
      $resp['msg'] = 'Please choose a PDF file to upload.';
    } elseif ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
      $resp['msg'] = 'The file is too large. The server limit is ' . ini_get('upload_max_filesize') . '.';
    } elseif ($err !== UPLOAD_ERR_OK) {
      $resp['msg'] = 'Upload failed (error code ' . intval($err) . '). Please try again.';
    } elseif (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
      $resp['msg'] = 'Only PDF files are allowed.';
    } else {
      if (!is_dir('assets/pdf')) { @mkdir('assets/pdf', 0777, true); }
      $td       = date('mdyGis');
      $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $docname);
      $idForName = preg_replace('/[^A-Za-z0-9_-]+/', '', $id);   // filesystem-safe id fragment
      $pdfname  = $safeName . "_" . $idForName . "_" . $td . ".pdf";
      $newpath  = "assets/pdf/" . $pdfname;

      if (move_uploaded_file($_FILES['file']['tmp_name'], $newpath)) {
        $safeId   = mysqli_real_escape_string($con, $id);
        $safeFile = mysqli_real_escape_string($con, $pdfname);
        $safePath = mysqli_real_escape_string($con, $newpath);
        if (mysqli_query($con, "INSERT INTO empe201files (EMPID, EmpfileN, EmpProFPath) VALUES ('$safeId', '$safeFile', '$safePath')")) {
          $resp['ok']  = true;
          $resp['msg'] = 'Document uploaded successfully.';
        } else {
          $resp['msg'] = 'The file was saved but could not be recorded: ' . mysqli_error($con);
        }
      } else {
        $resp['msg'] = 'Could not save the file. Please check that assets/pdf is writable.';
      }
    }

    echo json_encode($resp);
    exit;
  }

  /* ---- Prefill: when we land on ?emp=ID resolve the employee name so the search
     box and files card open already focused. EmpID is a string, so escape it. ---- */
  $prefillEmp  = isset($_GET['emp']) ? trim($_GET['emp']) : '';
  $prefillName = '';
  if ($prefillEmp !== '') {
    $safePrefill = mysqli_real_escape_string($con, $prefillEmp);
    $pf = mysqli_query($con, "SELECT EmpLN, EmpFN FROM employees WHERE EmpID='" . $safePrefill . "'");
    if ($pf && $pfrow = mysqli_fetch_array($pf)) {
      $prefillName = trim($pfrow['EmpLN'] . ' ' . $pfrow['EmpFN']);
    }
  }

  /* Never let the browser serve a stale copy of this dynamic page (that would run
     old inline JS against the current DOM). */
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">
  <title><?php echo ($_SESSION['CompanyName'] == "") ? "Dashboard" : "Electronic 201 Document"; ?></title>

  <!-- Functional libs (modals + change-password handler) -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
  <link rel="stylesheet" href="assets/css/wedo-theme.css">
  <script src="assets/js/script.js"></script>

  <style>
    /* the search card must not clip the absolute dropdown (theme sets .wd-card overflow:hidden) */
    .e201d-searchcard{overflow:visible}

    /* ---- Employee live-search ---- */
    .e201d-search{position:relative;max-width:480px}
    .e201d-search__box{position:relative}
    .e201d-search__box>i{position:absolute;left:13px;top:50%;transform:translateY(-50%);
      color:var(--text-3);font-size:14px;pointer-events:none}
    .e201d-search__box .wd-input{padding-left:36px}
    #empdetails.dv-livesearch{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:30;
      background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
      box-shadow:var(--shadow);overflow:hidden;max-height:320px;overflow-y:auto}
    #empdetails.dv-livesearch:empty{display:none}
    #empdetails.dv-livesearch a{display:block;width:100%;margin:0;padding:10px 14px;font-size:13px;
      color:var(--text);cursor:pointer;border:none;border-radius:0;background:none;text-align:left;
      white-space:normal;box-shadow:none}
    #empdetails.dv-livesearch a+a{border-top:1px solid var(--border)}
    #empdetails.dv-livesearch a:hover{background:var(--surface-2);color:var(--brand)}

    /* ---- Selected employee chip in the files card head ---- */
    .e201d-emp{display:inline-flex;align-items:center;gap:8px;font-family:var(--font-body);
      font-size:12.5px;font-weight:600;color:var(--text-2)}
    .e201d-emp i{color:var(--brand)}

    /* ---- Document rows (shared with query/searchpdffiles.php) ---- */
    .e201d-doc{display:flex;align-items:center;gap:12px;min-width:0}
    .e201d-doc__ico{width:38px;height:38px;flex:0 0 38px;border-radius:9px;background:var(--danger-bg);
      color:var(--danger-text);display:flex;align-items:center;justify-content:center;font-size:17px}
    .e201d-doc__meta{display:flex;flex-direction:column;min-width:0}
    .e201d-doc__name{color:var(--text);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .e201d-doc__path{font-size:11.5px;color:var(--text-3);white-space:nowrap;overflow:hidden;
      text-overflow:ellipsis;max-width:460px}
    .e201d-actions{text-align:right;white-space:nowrap}
    .e201d-empty{text-align:center;color:var(--text-3);padding:40px 20px !important;white-space:normal !important}
    .e201d-empty i{display:block;font-size:30px;margin-bottom:10px;color:var(--border-2)}

    /* ---- Upload modal file input + inline feedback ---- */
    .e201d-file{width:100%;border:1px dashed var(--border-2);border-radius:var(--radius);
      padding:12px 13px;font-size:13px;color:var(--text-2);background:var(--surface-2);cursor:pointer}
    .e201d-msg{margin-bottom:14px;padding:10px 13px;border-radius:var(--radius);font-size:12.5px;
      font-weight:600;display:flex;align-items:center;gap:8px}
    .e201d-msg--ok{background:var(--ok-bg);color:var(--ok-text)}
    .e201d-msg--err{background:var(--danger-bg);color:var(--danger-text)}

    /* ---- Toast (page-level confirmation after the modal closes) ---- */
    .e201d-toast{position:fixed;right:24px;bottom:24px;z-index:1080;display:flex;align-items:center;gap:10px;
      background:var(--ok-text);color:#fff;padding:13px 18px;border-radius:var(--radius);
      box-shadow:0 12px 32px rgba(16,24,40,.22);font-weight:600;font-size:13px;
      opacity:0;transform:translateY(12px);transition:opacity .25s,transform .25s;pointer-events:none}
    .e201d-toast.is-show{opacity:1;transform:translateY(0)}
  </style>
</head>
<body>
<?php $wd_active = 'e201files'; include 'includes/wd-header.php'; ?>

<div class="wd-pagehead">
  <div>
    <h1>Electronic 201 Document</h1>
    <p>Search an employee, then upload and manage their 201 file documents (PDF).</p>
  </div>
</div>

<!-- ===================== Find employee ===================== -->
<section class="wd-card e201d-searchcard">
  <div class="wd-card__head"><h3>Find Employee</h3></div>
  <div style="padding:20px">
    <div class="e201d-search">
      <label class="wd-field" style="display:block;font-size:12.5px;font-weight:600;color:var(--text-2);margin-bottom:6px">Search by lastname or employee ID</label>
      <div class="e201d-search__box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="txtemp" class="wd-input" autocomplete="off"
               placeholder="Start typing a lastname&hellip;"
               value="<?php echo htmlspecialchars($prefillName); ?>">
      </div>
      <div id="empdetails" class="dv-livesearch"></div>
    </div>
  </div>
</section>

<!-- ===================== 201 Documents ===================== -->
<section class="wd-card" id="filesCard" style="display:<?php echo $prefillEmp !== '' ? 'block' : 'none'; ?>">
  <div class="wd-card__head">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <h3>201 Documents</h3>
      <span class="e201d-emp" id="filesEmpName" style="<?php echo $prefillName === '' ? 'display:none' : ''; ?>">
        <i class="fa-solid fa-user"></i><span class="n"><?php echo htmlspecialchars($prefillName); ?></span>
      </span>
    </div>
    <button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-toggle="modal" data-target="#mdluploadfile">
      <i class="fa-solid fa-plus"></i> Add document
    </button>
  </div>
  <div class="wd-tablewrap">
    <table class="wd-table">
      <thead>
        <tr>
          <th style="width:64px">#</th>
          <th>Document</th>
          <th class="e201d-actions">Action</th>
        </tr>
      </thead>
      <tbody id="tbd-files">
        <tr><td colspan="3" class="e201d-empty">
          <i class="fa-solid fa-folder-open"></i>Select an employee to load their 201 documents.
        </td></tr>
      </tbody>
    </table>
  </div>
</section>

<!-- ===================== Upload modal (single instance) ===================== -->
<div class="modal fade" id="mdluploadfile">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="color:#fff;background:var(--brand)">
        <h4 class="modal-title">Upload 201 Document</h4>
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.9">&times;</button>
      </div>
      <div class="modal-body">
        <form id="up" method="post" action="e201files.php?addfiles" enctype="multipart/form-data">
          <input type="hidden" name="txtcount" id="employeeeid" value="<?php echo htmlspecialchars($prefillEmp); ?>">
          <div id="uploadMsg" class="e201d-msg" style="display:none"></div>
          <div class="wd-field">
            <label>Document name</label>
            <input type="text" class="wd-input" name="txtfilename" id="pdffilename" required placeholder="e.g. Employment Contract">
          </div>
          <div class="wd-field">
            <label>PDF file</label>
            <input type="file" class="e201d-file" name="file" id="file" accept="application/pdf" required>
          </div>
          <button class="wd-btn wd-btn--primary" type="submit" id="uploadpdf" style="width:100%;justify-content:center">
            <i class="fa-solid fa-cloud-arrow-up"></i> Upload document
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Page-level confirmation toast -->
<div class="e201d-toast" id="e201dToast"><i class="fa-solid fa-circle-check"></i><span></span></div>

<script>
  $(function () {
    var $search  = $('#txtemp');
    var $results = $('#empdetails');
    var filesCard = document.getElementById('filesCard');
    var tbody     = document.getElementById('tbd-files');
    var $upMsg    = $('#uploadMsg');
    var $upBtn    = $('#uploadpdf');

    // Source of truth for the chosen employee (a string id, e.g. "WeDoinc-0010").
    // Kept in JS so it can't be wiped by a form reset / attribute re-read.
    var selectedEmp = <?php echo $prefillEmp !== '' ? json_encode($prefillEmp) : 'null'; ?>;

    // Load an employee's 201 documents into the (single) files table.
    function loadFiles(id, name) {
      if (!id) { return; }
      selectedEmp = id;
      var hid = document.getElementById('employeeeid');
      if (hid) { hid.value = id; hid.setAttribute('value', id); }  // property + attribute
      filesCard.style.display = 'block';
      if (typeof name === 'string') {
        $('#filesEmpName').show().find('.n').text(name);
      }
      var xhr = new XMLHttpRequest();
      xhr.onreadystatechange = function () {
        if (this.readyState === 4 && this.status === 200) {
          tbody.innerHTML = this.responseText;
        }
      };
      xhr.open('GET', 'query/searchpdffiles.php?q=' + encodeURIComponent(id), true);
      xhr.send();
    }

    // Live employee search dropdown.
    $search.on('keyup input', function () {
      var term = $(this).val();
      if (term.length) {
        $.get('query/searchemp.php', { term: term }).done(function (data) {
          $results.html(data);
        });
      } else {
        $results.empty();
      }
    });

    // Pick an employee from the dropdown.
    $(document).on('click', '#empdetails a', function (e) {
      e.preventDefault();
      var id = $(this).attr('id');
      if (!id) { return; }                 // ignore the "No Data found" item
      var name = $(this).text().trim();
      $search.val(name);
      $results.empty();
      loadFiles(id, name);
    });

    // Inline modal message (ok=true -> green, ok=false -> red, ok=null -> hide).
    function showMsg(text, ok) {
      if (ok === null || !text) { $upMsg.hide().empty(); return; }
      $upMsg.removeClass('e201d-msg--ok e201d-msg--err')
            .addClass(ok ? 'e201d-msg--ok' : 'e201d-msg--err')
            .html('<i class="fa-solid ' + (ok ? 'fa-circle-check' : 'fa-circle-exclamation') + '"></i>' +
                  '<span></span>').show();
      $upMsg.find('span').text(text);
    }

    // Brief page-level toast after the modal closes.
    var toastTimer;
    function toast(text) {
      var $t = $('#e201dToast');
      $t.find('span').text(text);
      $t.addClass('is-show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () { $t.removeClass('is-show'); }, 2600);
    }

    // Reset the modal each time it opens so old messages/files don't linger.
    $('#mdluploadfile').on('show.bs.modal', function () {
      showMsg('', null);
      $('#pdffilename').val('');
      $('#file').val('');
    });

    // Upload without leaving the page.
    $('#up').on('submit', function (e) {
      e.preventDefault();
      var empId = selectedEmp || $('#employeeeid').val();
      if (!empId) { showMsg('Please select an employee first.', false); return; }

      $upBtn.prop('disabled', true)
            .html('<i class="fa-solid fa-spinner fa-spin"></i> Uploading&hellip;');
      showMsg('', null);

      var fd = new FormData(this);
      fd.set('txtcount', empId);            // guarantee the employee id is sent

      $.ajax({
        url: 'e201files.php?addfiles',
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        dataType: 'json'
      }).done(function (res) {
        if (res && res.ok) {
          showMsg(res.msg, true);
          loadFiles(empId);                 // refresh table in place, keep employee selected
          setTimeout(function () {
            $('#mdluploadfile').modal('hide');
            toast(res.msg);
          }, 700);
        } else {
          showMsg((res && res.msg) || 'Upload failed. Please try again.', false);
        }
      }).fail(function () {
        showMsg('Upload failed. Please try again.', false);
      }).always(function () {
        $upBtn.prop('disabled', false)
              .html('<i class="fa-solid fa-cloud-arrow-up"></i> Upload document');
      });
    });

    // If we arrived focused on an employee (?emp=ID), load their files now.
    <?php if ($prefillEmp !== ''): ?>
    loadFiles(<?php echo json_encode($prefillEmp); ?>, <?php echo json_encode($prefillName); ?>);
    <?php endif; ?>
  });
</script>

<?php include 'includes/wd-footer.php'; ?>
</body>
</html>
