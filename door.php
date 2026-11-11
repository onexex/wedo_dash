<?php
/* ==========================================================================
   door.php  —  Management > Door Control.
   Shows whether the office biometric device is online and releases the
   door's magnetic lock for a few seconds. Gated by the `doorctl` access
   right. Rules and the device conversation: includes/door-lib.php.
   Reads and writes: query/door-action.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/door-lib.php';

/* access gate BEFORE any output so the redirect can fire (least privilege) */
try {
    $doorpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $doorpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!door_can($doorpdo)) { header('location: 404?'); exit(); }

$ready = door_ready($doorpdo);
$cfg   = door_config();
$log   = $ready ? door_log_rows($doorpdo) : [];
$boot  = ['endpoint' => 'query/door-action.php', 'token' => door_csrf_token(), 'log' => $log,
          'seconds' => $cfg ? $cfg['seconds'] : DOOR_SECONDS];

$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Door Control" : $e($_SESSION['CompanyName']); ?></title>
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
      /* --- door-control-scoped layout (built on wedo-theme tokens) --- */
      .door-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px}
      .door-card{padding:20px}
      .door-card h3{font-size:16px;margin:0 0 4px}
      .door-sub{margin:0 0 16px;font-size:13px;color:var(--text-2)}
      .door-state{display:flex;align-items:center;gap:14px;margin-bottom:14px}
      .door-state__icon{width:56px;height:56px;border-radius:14px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;
        background:var(--surface-2);color:var(--text-3);font-size:24px}
      .door-state[data-state=online] .door-state__icon{background:var(--ok-bg);color:var(--ok-text)}
      .door-state[data-state=offline] .door-state__icon,.door-state[data-state=auth] .door-state__icon{background:var(--danger-bg);color:var(--danger-text)}
      .door-state[data-state=unset] .door-state__icon{background:var(--warn-bg);color:var(--warn-text)}
      .door-state__msg{font-size:13px;color:var(--text-2);margin:4px 0 0;line-height:1.5}
      .door-facts{margin:0 0 16px;display:grid;grid-template-columns:auto 1fr;gap:6px 16px;font-size:13px}
      .door-facts dt{color:var(--text-3);font-weight:500}
      .door-facts dd{margin:0;color:var(--text);word-break:break-word}
      .door-facts:empty{display:none}
      .door-note{width:100%;margin-bottom:12px}
      .door-unlock{width:100%;justify-content:center;padding:16px;font-size:15px}
      .door-unlock[disabled]{opacity:.55;cursor:not-allowed}
      .door-confirm{display:flex;gap:10px;flex-wrap:wrap}
      .door-confirm .wd-btn{flex:1;justify-content:center;padding:14px}
      .door-confirm[hidden],.door-unlock[hidden]{display:none}
      .door-result{margin:12px 0 0;padding:12px 14px;border-radius:var(--radius);font-size:13px;line-height:1.5}
      .door-result[hidden]{display:none}
      .door-result--ok{background:var(--ok-bg);color:var(--ok-text)}
      .door-result--bad{background:var(--danger-bg);color:var(--danger-text)}
      .door-empty{text-align:center;color:var(--text-3);padding:22px}
      .door-why{display:block;font-size:12px;color:var(--text-3)}
      @media (max-width:800px){ .door-grid{grid-template-columns:minmax(0,1fr)} .door-hide-sm{display:none} }
    </style>
  </head>
  <body>
    <?php
      $wd_active = 'door';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>Door Control</h1>
          <p>See whether the office biometric is online, and unlock the door for a few seconds when a fingerprint won’t read.</p>
        </div>
      </div>

    <?php if (!$ready): ?>
      <section class="wd-card"><div class="door-empty">This page is not set up on this server yet — run <code><?php echo $e(DOOR_SQL); ?></code>.</div></section>
    <?php else: ?>

      <div class="door-grid">
        <section class="wd-card door-card">
          <h3>Device</h3>
          <p class="door-sub"><?php echo $e($cfg ? $cfg['name'] : 'No device set up'); ?></p>
          <div class="door-state" id="doorState" data-state="checking">
            <div class="door-state__icon"><i class="fa-solid fa-spinner fa-spin" id="doorIcon"></i></div>
            <div>
              <span class="wd-pill" id="doorPill">Checking…</span>
              <p class="door-state__msg" id="doorMsg" role="status" aria-live="polite">Asking the device.</p>
            </div>
          </div>
          <dl class="door-facts" id="doorFacts"></dl>
          <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="doorRefresh"><i class="fa-solid fa-rotate"></i> Check again</button>
        </section>

        <section class="wd-card door-card">
          <h3>Unlock the door</h3>
          <p class="door-sub">Releases the lock for <b id="doorSecs"><?php echo (int) $boot['seconds']; ?></b> seconds, then it locks again by itself. Your name and the time are recorded.</p>
          <input type="text" class="wd-input door-note" id="doorNote" maxlength="150" placeholder="Reason (optional), e.g. fingerprint not reading" aria-label="Reason (optional)">
          <button type="button" class="wd-btn wd-btn--primary door-unlock" id="doorUnlock" disabled><i class="fa-solid fa-door-open"></i> <span>Unlock door</span></button>
          <div class="door-confirm" id="doorConfirm" hidden>
            <button type="button" class="wd-btn wd-btn--danger" id="doorYes"><i class="fa-solid fa-lock-open"></i> Yes, unlock now</button>
            <button type="button" class="wd-btn wd-btn--ghost" id="doorNo">Cancel</button>
          </div>
          <p class="door-result" id="doorResult" hidden role="status" aria-live="assertive"></p>
        </section>
      </div>

      <section class="wd-card" style="margin-top:16px">
        <div class="wd-card__head">
          <h3>Recent activity</h3>
          <span class="wd-muted" style="font-size:12.5px">The last 30 tries, opened or not</span>
        </div>
        <div style="overflow-x:auto">
          <table class="wd-table">
            <thead><tr><th>When</th><th>Who</th><th>Result</th><th class="door-hide-sm">Reason</th></tr></thead>
            <tbody id="doorLog"></tbody>
          </table>
        </div>
      </section>

    <?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>

    <?php if ($ready): ?>
    <script>
    (function () {
      var boot = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
      var $ = function (id) { return document.getElementById(id); };
      var state = $('doorState'), pill = $('doorPill'), msg = $('doorMsg'), icon = $('doorIcon'), facts = $('doorFacts');
      var unlock = $('doorUnlock'), confirmBox = $('doorConfirm'), result = $('doorResult'), note = $('doorNote');
      var online = false, busy = false;

      function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

      function post(data, done) {
        var body = new URLSearchParams(data); body.set('token', boot.token);
        fetch(boot.endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'The server gave an unreadable answer.' }; }); })
          .then(done)
          .catch(function () { done({ status: 'error', msg: 'Could not reach WeDo. Check your connection.' }); });
      }

      var LOOK = {
        online:  ['Online', 'wd-pill--ok', 'fa-solid fa-circle-check'],
        offline: ['Offline', 'wd-pill--danger', 'fa-solid fa-plug-circle-xmark'],
        auth:    ['Comm Key rejected', 'wd-pill--danger', 'fa-solid fa-key'],
        unset:   ['Not set up', 'wd-pill--warn', 'fa-solid fa-triangle-exclamation'],
        error:   ['Could not check', 'wd-pill--warn', 'fa-solid fa-triangle-exclamation']
      };

      function showDevice(d) {
        var look = LOOK[d.state] || LOOK.error;
        online = d.state === 'online';
        state.setAttribute('data-state', d.state);
        pill.className = 'wd-pill ' + look[1]; pill.textContent = look[0];
        icon.className = look[2];
        msg.textContent = d.msg || '';
        var rows = [];
        if (online) {
          if (d.model)  { rows.push(['Model', d.model]); }
          if (d.serial) { rows.push(['Serial number', d.serial]); }
          if (d.clock)  { rows.push(['Device clock', d.clock]); }
          rows.push(['Answered in', d.ms + ' ms']);
        }
        facts.innerHTML = rows.map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; }).join('');
        if (d.seconds) { $('doorSecs').textContent = d.seconds; }
        unlock.disabled = !online || busy;
      }

      function showLog(rows) {
        $('doorLog').innerHTML = rows.length ? rows.map(function (r) {
          return '<tr><td>' + esc(r.at) + '</td><td>' + esc(r.who) + '</td><td>'
            + (r.ok ? '<span class="wd-pill wd-pill--ok">Unlocked ' + esc(r.seconds) + 's</span>'
                    : '<span class="wd-pill wd-pill--danger">Failed</span><span class="door-why">' + esc(r.detail) + '</span>')
            + '</td><td class="door-hide-sm">' + esc(r.note) + '</td></tr>';
        }).join('') : '<tr><td colspan="4" class="door-empty">Nobody has unlocked the door from WeDo yet.</td></tr>';
      }

      function check() {
        state.setAttribute('data-state', 'checking');
        pill.className = 'wd-pill'; pill.textContent = 'Checking…';
        icon.className = 'fa-solid fa-spinner fa-spin'; msg.textContent = 'Asking the device.';
        unlock.disabled = true;
        post({ action: 'status' }, function (j) {
          if (j.status !== 'ok') { showDevice({ state: 'error', msg: j.msg }); return; }
          showDevice(j.device); showLog(j.log);
        });
      }

      function askConfirm(on) { confirmBox.hidden = !on; unlock.hidden = on; }

      unlock.addEventListener('click', function () { result.hidden = true; askConfirm(true); $('doorYes').focus(); });
      $('doorNo').addEventListener('click', function () { askConfirm(false); });
      $('doorYes').addEventListener('click', function () {
        if (busy) { return; }
        busy = true; askConfirm(false); unlock.disabled = true;
        unlock.querySelector('span').textContent = 'Unlocking…';
        post({ action: 'unlock', note: note.value }, function (j) {
          busy = false;
          unlock.querySelector('span').textContent = 'Unlock door';
          unlock.disabled = !online;
          result.hidden = false;
          result.className = 'door-result ' + (j.status === 'ok' ? 'door-result--ok' : 'door-result--bad');
          result.textContent = j.msg || (j.status === 'ok' ? 'Unlocked.' : 'The door was not unlocked.');
          if (j.status === 'ok') { note.value = ''; }
          if (j.log) { showLog(j.log); }
        });
      });
      $('doorRefresh').addEventListener('click', check);

      showLog(boot.log);
      check();
    })();
    </script>
    <?php endif; ?>
  </body>
</html>
