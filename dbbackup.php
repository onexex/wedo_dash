<?php
/* ==========================================================================
   dbbackup.php  —  Maintenance > Database Backup.
   Download the whole WeDo database (structure + data) as a .sql.gz file, and
   download again any of the last few backups kept on the server.
   Gated by the `dbbackup` access right; the dump itself is built by
   includes/db-backup-lib.php and streamed by query/dbbackup-download.php,
   kept copies are served by query/dbbackup-file.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/db-backup-lib.php';

/* access gate BEFORE any output so the redirect can fire (least privilege) */
try {
    $dbbpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $dbbpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!dbb_can_backup($dbbpdo, (string) $_SESSION['id'])) { header('location: 404?'); exit(); }

$summary = dbb_summary($dbbpdo);
$keep    = dbb_keep();
$recent  = dbb_recent($dbbpdo, max(10, $keep));
$token   = dbb_csrf_token();
$hasGz   = function_exists('deflate_init');
$example = dbb_filename($db);   // before wd-header.php, which re-includes w_conn.php

$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$mb = function ($bytes) {
    if ($bytes >= 1073741824) { return number_format($bytes / 1073741824, 1) . ' GB'; }
    if ($bytes >= 1048576)    { return number_format($bytes / 1048576, 1) . ' MB'; }
    return number_format(max(1, $bytes / 1024), 0) . ' KB';
};
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Database Backup" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <!-- Functional libs -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (after bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>

    <style>
      /* --- database-backup-scoped layout (built on wedo-theme tokens) --- */
      .dbb-run{display:flex;align-items:center;gap:18px;flex-wrap:wrap;padding:20px}
      .dbb-run__icon{width:56px;height:56px;border-radius:14px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;
        background:var(--brand-tint);color:var(--brand);font-size:24px}
      .dbb-run__copy{flex:1;min-width:220px}
      .dbb-run__copy h3{font-size:16px;margin:0 0 4px}
      .dbb-run__copy p{margin:0;font-size:13px;color:var(--text-2)}
      .dbb-run form{margin:0}
      .dbb-run .wd-btn[disabled]{opacity:.7;cursor:progress}
      .dbb-status{margin:0 20px 16px;font-size:13px;color:var(--text-2)}
      .dbb-status[hidden]{display:none}
      .dbb-notice{display:flex;gap:12px;align-items:flex-start;margin:16px 0;padding:14px 16px;border-radius:var(--radius);
        background:var(--warn-bg);color:var(--warn-text);font-size:13px;line-height:1.5}
      .dbb-notice i{font-size:18px;margin-top:2px}
      .dbb-notice--danger{background:var(--danger-bg);color:var(--danger-text)}
      .dbb-help{padding:14px 20px;font-size:13px;color:var(--text-2);line-height:1.6;border-top:1px solid var(--border)}
      .dbb-help ol{margin:6px 0 0;padding-left:20px}
      .dbb-empty{text-align:center;color:var(--text-3);padding:22px}
      .dbb-file{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;color:var(--text-2);word-break:break-all}
      .dbb-gone{font-size:12px;color:var(--text-3);white-space:nowrap}
      .wd-table td.dbb-dl{white-space:nowrap}
      @media (max-width:600px){ .dbb-hide-sm{display:none} .dbb-run .wd-btn{width:100%;justify-content:center} .dbb-run form{width:100%} }
    </style>
  </head>
  <body>
    <?php
      $wd_active = 'dbbackup';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>Database Backup</h1>
          <p>Download a complete copy of the WeDo database — every table’s structure and data — as one compressed file.</p>
        </div>
      </div>

      <div class="dbb-notice dbb-notice--danger" role="note">
        <i class="fa-solid fa-user-shield"></i>
        <div>
          <b>This file holds everything:</b> salaries, government IDs, addresses, messages and password hashes.
          Keep it in a secure, private place (not email or a shared chat), and delete copies you no longer need.
          The last <?php echo (int) $keep; ?> backups also stay on the server, out of the web’s reach, for people with this right to download again.
          Every download is recorded with your name.
        </div>
      </div>

      <div class="wd-stats">
        <div class="wd-stat"><div class="wd-stat__label"><i class="fa-solid fa-table"></i> Tables</div><div class="wd-stat__value"><?php echo number_format($summary['tables']); ?></div></div>
        <div class="wd-stat"><div class="wd-stat__label"><i class="fa-solid fa-list"></i> Rows (approx.)</div><div class="wd-stat__value"><?php echo number_format($summary['rows']); ?></div></div>
        <div class="wd-stat"><div class="wd-stat__label"><i class="fa-solid fa-hard-drive"></i> Size on server</div><div class="wd-stat__value"><?php echo $e($mb($summary['bytes'])); ?></div></div>
      </div>

      <section class="wd-card">
        <div class="dbb-run">
          <div class="dbb-run__icon"><i class="fa-solid fa-database"></i></div>
          <div class="dbb-run__copy">
            <h3>Back up now</h3>
            <p>Saves <span class="dbb-file"><?php echo $e($example); ?></span> (named with today’s date and time) to your computer. The compressed file is usually much smaller than the size on the server.</p>
          </div>
          <?php if ($hasGz): ?>
          <form method="post" action="query/dbbackup-download.php" id="dbbForm">
            <input type="hidden" name="token" value="<?php echo $e($token); ?>">
            <input type="hidden" name="dl" id="dbbNonce" value="">
            <button type="submit" class="wd-btn wd-btn--primary" id="dbbBtn"><i class="fa-solid fa-download"></i> <span>Back up now</span></button>
          </form>
          <?php endif; ?>
        </div>
        <?php if (!$hasGz): ?>
          <div class="dbb-notice" style="margin:0 20px 16px"><i class="fa-solid fa-triangle-exclamation"></i><div>This server’s PHP has no zlib support, so a compressed backup can’t be made here.</div></div>
        <?php endif; ?>
        <p class="dbb-status" id="dbbStatus" hidden role="status" aria-live="polite"></p>
        <div class="dbb-help">
          <b>To restore</b> (only when you mean to replace the data):
          <ol>
            <li>In phpMyAdmin, select an <b>empty</b> database — or one you are sure you want to overwrite.</li>
            <li>Import the <b>.sql.gz</b> file as it is (no need to unzip). Tables in the file replace tables of the same name.</li>
            <li>A complete file ends with a <code>-- Dump completed</code> line. If it says <b>BACKUP INCOMPLETE</b>, don’t use it.</li>
          </ol>
        </div>
      </section>

      <section class="wd-card" style="margin-top:16px">
        <div class="wd-card__head">
          <h3>Recent backups</h3>
          <span class="wd-muted" style="font-size:12.5px">The last <?php echo (int) $keep; ?> stay on the server and can be downloaded again</span>
        </div>
        <div style="overflow-x:auto">
          <table class="wd-table">
            <thead><tr><th>When</th><th>By</th><th class="dbb-hide-sm">File</th><th>Download</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?>
              <tr><td colspan="4" class="dbb-empty">No backups yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td><?php echo $r['DarDateTime'] ? $e(date('M j, Y g:i A', strtotime($r['DarDateTime']))) : '—'; ?></td>
                <td><?php echo $e($r['name'] !== '' ? $r['name'] : $r['EMPID']); ?><?php if ($r['failed']): ?> <span class="wd-pill wd-pill--danger">Failed</span><?php endif; ?></td>
                <td class="dbb-hide-sm dbb-file"><?php echo $e($r['file']); ?></td>
                <td class="dbb-dl">
                  <?php if ($r['kept']): ?>
                    <a class="wd-btn wd-btn--ghost wd-btn--sm" href="query/dbbackup-file.php?f=<?php echo rawurlencode($r['file']); ?>" download><i class="fa-solid fa-download"></i> <?php echo $e($mb($r['bytes'])); ?></a>
                  <?php elseif ($r['failed']): ?>
                    <span class="dbb-gone">Not kept</span>
                  <?php else: ?>
                    <span class="dbb-gone" title="Only the last <?php echo (int) $keep; ?> backups stay on the server">No longer on server</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

    <?php include 'includes/wd-footer.php'; ?>

    <script>
    (function () {
      var form = document.getElementById('dbbForm');
      if (!form) { return; }
      var btn = document.getElementById('dbbBtn'), label = btn.querySelector('span'), icon = btn.querySelector('i');
      var status = document.getElementById('dbbStatus'), timer = null;

      function reset(msg) {
        clearInterval(timer);
        btn.disabled = false; label.textContent = 'Back up now'; icon.className = 'fa-solid fa-download';
        if (msg) { status.textContent = msg; status.hidden = false; }
      }

      form.addEventListener('submit', function () {
        // the download endpoint sets cookie dbb_dl=<nonce> once the file starts arriving
        var nonce = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
        document.getElementById('dbbNonce').value = nonce;
        btn.disabled = true; label.textContent = 'Preparing backup…'; icon.className = 'fa-solid fa-spinner fa-spin';
        status.textContent = 'Building the backup. Large databases can take a minute — keep this tab open.'; status.hidden = false;
        var started = Date.now();
        timer = setInterval(function () {
          if (document.cookie.indexOf('dbb_dl=' + nonce) !== -1) {
            document.cookie = 'dbb_dl=; path=/; max-age=0';
            reset('Your backup is downloading. Check your browser’s downloads. Reload this page to see it in Recent backups.');
          } else if (Date.now() - started > 120000) {
            reset('Still working, or the download didn’t start. Check your browser’s downloads, then try again if needed.');
          }
        }, 500);
      });
    })();
    </script>
  </body>
</html>
