<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {} else { header('location: login.php'); exit; }

  include 'w_conn.php';

  /* Rows injected into #tbd-files on e201files.php. Markup mirrors the table in
     e201files.php (columns: #, Document, Action) and the .e201d-* styles there.
     EMPID is a string (e.g. "WeDoinc-0010") — escape it, never intval it. */
  $q = mysqli_real_escape_string($con, trim($_GET['q'] ?? ''));
  $result = mysqli_query($con, "SELECT * FROM empe201files WHERE EMPID='" . $q . "'");

  if (!$result || mysqli_num_rows($result) < 1) {
?>
  <tr><td colspan="3" class="e201d-empty">
    <i class="fa-solid fa-folder-open"></i>No 201 documents uploaded yet for this employee.
  </td></tr>
<?php
  } else {
    $r = 1;
    while ($row = mysqli_fetch_array($result)) {
      $name = htmlspecialchars($row['EmpfileN']);
      $path = htmlspecialchars($row['EmpProFPath']);
?>
  <tr>
    <td><?php echo $r; ?></td>
    <td>
      <div class="e201d-doc">
        <span class="e201d-doc__ico"><i class="fa-solid fa-file-pdf"></i></span>
        <div class="e201d-doc__meta">
          <span class="e201d-doc__name"><?php echo $name; ?></span>
          <span class="e201d-doc__path"><?php echo $path; ?></span>
        </div>
      </div>
    </td>
    <td class="e201d-actions">
      <a href="<?php echo $path; ?>" target="_blank" rel="noopener" class="wd-btn wd-btn--ghost wd-btn--sm">
        <i class="fa-solid fa-up-right-from-square"></i> Open
      </a>
    </td>
  </tr>
<?php
      $r++;
    }
  }
?>
