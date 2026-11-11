<?php
/* ==========================================================================
   door.php  —  Management > Door Control.
   Releases the office door's magnetic lock for a few seconds, shows whether
   the biometric device behind it is online, and lists who is enrolled on it
   (add a person, correct a name). Gated by the `doorctl` access right.
   Rules and the device conversation: includes/door-lib.php.
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
          'seconds' => $cfg ? $cfg['seconds'] : DOOR_SECONDS, 'nameMax' => door_name_max(),
          'employees' => $ready ? door_employee_names($doorpdo) : []];

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
      .door-top{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:16px;align-items:stretch;margin-top:14px}
      .door-card{padding:22px;display:flex;flex-direction:column}
      .door-card h2{font-size:16px;margin:0 0 4px}
      .door-sub{margin:0 0 16px;font-size:13px;color:var(--text-2);line-height:1.5}

      /* unlock */
      .door-note{width:100%;margin-bottom:12px}
      .door-unlock{width:100%;justify-content:center;padding:18px;font-size:16px;font-weight:700;border-radius:var(--radius-lg)}
      .door-unlock[disabled]{background:var(--surface-2);color:var(--text-3);cursor:not-allowed;box-shadow:none}
      .door-confirm{display:flex;gap:10px;flex-wrap:wrap}
      .door-confirm .wd-btn{flex:1;justify-content:center;padding:16px;font-size:15px;font-weight:700;border-radius:var(--radius-lg)}
      .door-confirm[hidden],.door-unlock[hidden]{display:none}
      .door-hint{margin:10px 0 0;font-size:12.5px;color:var(--text-3)}
      .door-hint[hidden]{display:none}
      .door-result{margin:12px 0 0;padding:12px 14px;border-radius:var(--radius);font-size:13.5px;line-height:1.5;display:flex;gap:10px;align-items:flex-start}
      .door-result[hidden]{display:none}
      .door-result i{margin-top:3px}
      .door-result--ok{background:var(--ok-bg);color:var(--ok-text)}
      .door-result--bad{background:var(--danger-bg);color:var(--danger-text)}

      /* device */
      .door-state{display:flex;align-items:center;gap:14px;margin-bottom:14px}
      .door-state__icon{width:52px;height:52px;border-radius:14px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;
        background:var(--surface-2);color:var(--text-3);font-size:22px}
      .door-state[data-state=online] .door-state__icon{background:var(--ok-bg);color:var(--ok-text)}
      .door-state[data-state=offline] .door-state__icon,.door-state[data-state=auth] .door-state__icon{background:var(--danger-bg);color:var(--danger-text)}
      .door-state[data-state=unset] .door-state__icon,.door-state[data-state=error] .door-state__icon{background:var(--warn-bg);color:var(--warn-text)}
      .door-state__msg{font-size:13px;color:var(--text-2);margin:6px 0 0;line-height:1.5}
      .door-facts{margin:0 0 14px;display:grid;grid-template-columns:auto 1fr;gap:6px 16px;font-size:13px}
      .door-facts dt{color:var(--text-3);font-weight:500}
      .door-facts dd{margin:0;color:var(--text);word-break:break-word}
      .door-facts:empty{display:none}
      .door-foot{margin-top:auto;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
      .door-checked{font-size:12px;color:var(--text-3)}

      /* tabs */
      .door-tabs{display:flex;gap:4px;padding:8px 12px 0;border-bottom:1px solid var(--border);overflow-x:auto}
      .door-tab{border:0;background:none;padding:12px 14px;font-size:13.5px;font-weight:600;color:var(--text-2);border-bottom:2px solid transparent;
        margin-bottom:-1px;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:8px}
      .door-tab:hover{color:var(--text)}
      .door-tab[aria-selected=true]{color:var(--brand);border-bottom-color:var(--brand)}
      .door-tab:focus-visible{outline:2px solid var(--brand);outline-offset:-2px;border-radius:6px}
      .door-tab__n{font-size:11.5px;font-weight:600;background:var(--surface-2);color:var(--text-2);border-radius:var(--radius-pill);padding:2px 8px}
      .door-tab__n:empty{display:none}
      .door-panel[hidden]{display:none}

      /* people */
      .door-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px 20px}
      .door-bar__q{flex:1;min-width:180px;max-width:340px}
      .door-bar__sum{font-size:12.5px;color:var(--text-2);margin-right:auto}
      .door-add{margin:0 20px 16px;padding:16px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-2)}
      .door-add[hidden]{display:none}
      .door-add h3{font-size:14px;margin:0 0 4px}
      .door-add p{font-size:12.5px;color:var(--text-2);margin:0 0 12px;line-height:1.5}
      .door-add__row{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:12px}
      .door-add label{display:block;font-size:12.5px;font-weight:600;color:var(--text-2);margin-bottom:6px}
      .door-add small{display:block;font-size:11.5px;color:var(--text-3);margin-top:5px;font-weight:400}
      .door-add__act{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap}
      .door-msg{margin:0 20px 16px}
      .door-empty{text-align:center;color:var(--text-3);padding:26px 16px;font-size:13px}
      .door-why{display:block;font-size:12px;color:var(--text-3);margin-top:3px}
      .door-id{font-variant-numeric:tabular-nums;color:var(--text-2)}
      .door-row__name{width:100%;min-width:160px;max-width:320px}
      .wd-table td.door-row__act{white-space:nowrap;text-align:right}

      @media (max-width:860px){
        .door-top{grid-template-columns:minmax(0,1fr)}
        .door-hide-sm{display:none}
        .door-add__row{grid-template-columns:minmax(0,1fr)}
        .door-bar__q{max-width:none;flex-basis:100%}
      }
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
          <p>Unlock the office door when a fingerprint won’t read, and manage who is enrolled on the biometric.</p>
        </div>
      </div>

    <?php if (!$ready): ?>
      <section class="wd-card"><div class="door-empty">This page is not set up on this server yet — run <code><?php echo $e(DOOR_SQL); ?></code>.</div></section>
    <?php else: ?>

      <div class="door-top">
        <section class="wd-card door-card" aria-labelledby="doorUnlockH">
          <h2 id="doorUnlockH">Unlock the door</h2>
          <p class="door-sub">Releases the lock for <b id="doorSecs"><?php echo (int) $boot['seconds']; ?></b> seconds, then it locks again by itself. Your name and the time are recorded.</p>
          <input type="text" class="wd-input door-note" id="doorNote" maxlength="150" placeholder="Reason (optional), e.g. fingerprint not reading" aria-label="Reason (optional)">
          <button type="button" class="wd-btn wd-btn--primary door-unlock" id="doorUnlock" disabled><i class="fa-solid fa-door-open"></i> <span>Unlock door</span></button>
          <div class="door-confirm" id="doorConfirm" hidden>
            <button type="button" class="wd-btn wd-btn--danger" id="doorYes"><i class="fa-solid fa-lock-open"></i> Yes, unlock now</button>
            <button type="button" class="wd-btn wd-btn--ghost" id="doorNo">Cancel</button>
          </div>
          <p class="door-hint" id="doorHint">Checking the device…</p>
          <p class="door-result" id="doorResult" hidden role="status" aria-live="assertive"></p>
        </section>

        <section class="wd-card door-card" aria-labelledby="doorDeviceH">
          <h2 id="doorDeviceH">Device</h2>
          <p class="door-sub"><?php echo $e($cfg ? $cfg['name'] : 'No device set up'); ?></p>
          <div class="door-state" id="doorState" data-state="checking">
            <div class="door-state__icon"><i class="fa-solid fa-spinner fa-spin" id="doorIcon"></i></div>
            <div>
              <span class="wd-pill" id="doorPill">Checking…</span>
              <p class="door-state__msg" id="doorMsg" role="status" aria-live="polite">Asking the device.</p>
            </div>
          </div>
          <dl class="door-facts" id="doorFacts"></dl>
          <div class="door-foot">
            <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="doorRefresh"><i class="fa-solid fa-rotate"></i> Check again</button>
            <span class="door-checked" id="doorChecked"></span>
          </div>
        </section>
      </div>

      <section class="wd-card" style="margin-top:16px">
        <div class="door-tabs" role="tablist" aria-label="Door Control sections">
          <button type="button" class="door-tab" role="tab" id="doorTabPeople" aria-controls="doorPanelPeople" aria-selected="true"><i class="fa-solid fa-users"></i> Enrolled people <span class="door-tab__n" id="doorPeopleN"></span></button>
          <button type="button" class="door-tab" role="tab" id="doorTabLog" aria-controls="doorPanelLog" aria-selected="false" tabindex="-1"><i class="fa-solid fa-clock-rotate-left"></i> Activity</button>
        </div>

        <div class="door-panel" id="doorPanelPeople" role="tabpanel" aria-labelledby="doorTabPeople">
          <div class="door-bar">
            <input type="search" class="wd-input door-bar__q" id="doorEnrQ" placeholder="Search name or ID&hellip;" aria-label="Search enrolled people" disabled>
            <span class="door-bar__sum" id="doorEnrCount"></span>
            <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="doorEnrLoad" disabled><i class="fa-solid fa-rotate"></i> <span>Refresh</span></button>
            <button type="button" class="wd-btn wd-btn--primary wd-btn--sm" id="doorAddOpen" disabled><i class="fa-solid fa-user-plus"></i> Add person</button>
          </div>

          <form class="door-add" id="doorAdd" hidden autocomplete="off">
            <h3>Add a person to the device</h3>
            <p>This puts their name and ID number on the biometric. Their fingerprint still has to be scanned at the device afterwards (device menu: User Mgt, pick the person, Enroll Fingerprint).</p>
            <div class="door-add__row">
              <div>
                <label for="doorAddName">Name</label>
                <input type="text" class="wd-input" id="doorAddName" list="doorAddNames" maxlength="60" placeholder="Start typing an employee’s name" required>
                <datalist id="doorAddNames"></datalist>
                <small>Up to <?php echo (int) $boot['nameMax']; ?> plain characters; the device cannot show Ñ or accents.</small>
              </div>
              <div>
                <label for="doorAddId">Device ID</label>
                <input type="text" class="wd-input" id="doorAddId" inputmode="numeric" pattern="[1-9][0-9]{0,8}" maxlength="9" required>
                <small>The next free number is filled in. Change it only if your IDs follow a scheme.</small>
              </div>
            </div>
            <div class="door-add__act">
              <button type="submit" class="wd-btn wd-btn--primary wd-btn--sm" id="doorAddSave"><i class="fa-solid fa-check"></i> <span>Add to device</span></button>
              <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="doorAddCancel">Cancel</button>
            </div>
          </form>

          <p class="door-result door-msg" id="doorEnrMsg" hidden role="status" aria-live="polite"></p>
          <div class="door-empty" id="doorEnrEmpty">The list appears once the device is online.</div>
          <div style="overflow-x:auto" id="doorEnrWrap" hidden>
            <table class="wd-table">
              <thead><tr><th>Device ID</th><th>Name</th><th class="door-hide-sm">Role</th><th><span class="sr-only">Edit</span></th></tr></thead>
              <tbody id="doorEnr"></tbody>
            </table>
          </div>
        </div>

        <div class="door-panel" id="doorPanelLog" role="tabpanel" aria-labelledby="doorTabLog" hidden>
          <div style="overflow-x:auto">
            <table class="wd-table">
              <thead><tr><th>When</th><th>Who</th><th>What happened</th><th class="door-hide-sm">Reason</th></tr></thead>
              <tbody id="doorLog"></tbody>
            </table>
          </div>
        </div>
      </section>

    <?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>

    <?php if ($ready): ?>
    <script>
    (function () {
      var boot = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
      var $ = function (id) { return document.getElementById(id); };
      var state = $('doorState'), pill = $('doorPill'), msg = $('doorMsg'), icon = $('doorIcon'), facts = $('doorFacts');
      var unlock = $('doorUnlock'), confirmBox = $('doorConfirm'), result = $('doorResult'), note = $('doorNote'), hint = $('doorHint');
      var online = false, busy = false, lastCheck = 0, countdown = null;

      function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML.replace(/"/g, '&quot;'); }

      function post(data, done) {
        var body = new URLSearchParams(data); body.set('token', boot.token);
        fetch(boot.endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'The server gave an unreadable answer. Reload the page and try again.' }; }); })
          .then(done)
          .catch(function () { done({ status: 'error', msg: 'Could not reach WeDo. Check your connection.' }); });
      }

      function say(el, ok, text) {
        el.hidden = false;
        el.className = el.className.replace(/ door-result--\w+/g, '') + (ok ? ' door-result--ok' : ' door-result--bad');
        el.innerHTML = '<i class="fa-solid ' + (ok ? 'fa-circle-check' : 'fa-circle-exclamation') + '"></i><span>' + esc(text) + '</span>';
      }

      /* ---------------------------------------------------------- device */
      var LOOK = {
        online:  ['Online', 'wd-pill--ok', 'fa-solid fa-circle-check'],
        offline: ['Offline', 'wd-pill--danger', 'fa-solid fa-plug-circle-xmark'],
        auth:    ['Comm Key rejected', 'wd-pill--danger', 'fa-solid fa-key'],
        unset:   ['Not set up', 'wd-pill--warn', 'fa-solid fa-triangle-exclamation'],
        error:   ['Could not check', 'wd-pill--warn', 'fa-solid fa-triangle-exclamation']
      };

      function showDevice(d) {
        var look = LOOK[d.state] || LOOK.error, was = online;
        online = d.state === 'online';
        state.setAttribute('data-state', LOOK[d.state] ? d.state : 'error');
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
        if (d.seconds) { boot.seconds = d.seconds; $('doorSecs').textContent = d.seconds; }
        $('doorChecked').textContent = 'Checked ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        unlock.disabled = !online || busy;
        hint.hidden = online;
        hint.textContent = 'Unlocking is available when the device is online.';
        enrBtn.disabled = !online; $('doorAddOpen').disabled = !online || !loaded;
        if (online && !was && !loaded) { loadEnrolled(); }
        if (!online && !loaded) { $('doorEnrEmpty').textContent = 'The list appears once the device is online.'; }
      }

      function check() {
        lastCheck = Date.now();
        state.setAttribute('data-state', 'checking');
        pill.className = 'wd-pill'; pill.textContent = 'Checking…';
        icon.className = 'fa-solid fa-spinner fa-spin'; msg.textContent = 'Asking the device.';
        unlock.disabled = true;
        post({ action: 'status' }, function (j) {
          if (j.status !== 'ok') { showDevice({ state: 'error', msg: j.msg }); return; }
          showDevice(j.device); showLog(j.log);
        });
      }
      $('doorRefresh').addEventListener('click', check);
      // coming back to the tab (phone unlocked at the door): what was true a minute ago may not be now
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !busy && Date.now() - lastCheck > 60000) { check(); }
      });

      /* ---------------------------------------------------------- unlock */
      function askConfirm(on) { confirmBox.hidden = !on; unlock.hidden = on; }

      unlock.addEventListener('click', function () { result.hidden = true; clearInterval(countdown); askConfirm(true); $('doorYes').focus(); });
      $('doorNo').addEventListener('click', function () { askConfirm(false); unlock.focus(); });
      $('doorYes').addEventListener('click', function () {
        if (busy) { return; }
        busy = true; askConfirm(false); unlock.disabled = true;
        unlock.querySelector('span').textContent = 'Unlocking…';
        post({ action: 'unlock', note: note.value }, function (j) {
          busy = false;
          unlock.querySelector('span').textContent = 'Unlock door';
          unlock.disabled = !online;
          if (j.log) { showLog(j.log); }
          if (j.status !== 'ok') { say(result, false, j.msg || 'The door was not unlocked.'); return; }
          note.value = '';
          var left = j.seconds || boot.seconds;
          say(result, true, 'Unlocked. Push the door now: it locks again in ' + left + ' seconds.');
          clearInterval(countdown);
          countdown = setInterval(function () {
            left--;
            if (left > 0) { say(result, true, 'Unlocked. Push the door now: it locks again in ' + left + (left === 1 ? ' second.' : ' seconds.')); }
            else { clearInterval(countdown); say(result, true, 'Time is up: the door has locked again.'); }
          }, 1000);
        });
      });

      /* ---------------------------------------------------------- tabs */
      var tabs = [['doorTabPeople', 'doorPanelPeople'], ['doorTabLog', 'doorPanelLog']];
      function pick(i, focus) {
        tabs.forEach(function (t, n) {
          var on = n === i, b = $(t[0]);
          b.setAttribute('aria-selected', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1;
          $(t[1]).hidden = !on;
          if (on && focus) { b.focus(); }
        });
      }
      tabs.forEach(function (t, i) {
        $(t[0]).addEventListener('click', function () { pick(i); });
        $(t[0]).addEventListener('keydown', function (ev) {
          if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') { ev.preventDefault(); pick((i + (ev.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length, true); }
        });
      });

      /* ---------------------------------------------------------- activity */
      var WHAT = { add: ['Person added', 'Person not added'], rename: ['Name changed', 'Name not changed'] };
      function showLog(rows) {
        $('doorLog').innerHTML = rows.length ? rows.map(function (r) {
          var what;
          if (WHAT[r.action]) {
            what = '<span class="wd-pill ' + (r.ok ? 'wd-pill--info">' + WHAT[r.action][0] : 'wd-pill--danger">' + WHAT[r.action][1]) + '</span><span class="door-why">' + esc(r.detail) + '</span>';
          } else {
            what = r.ok ? '<span class="wd-pill wd-pill--ok">Door unlocked ' + esc(r.seconds) + 's</span>'
                        : '<span class="wd-pill wd-pill--danger">Unlock failed</span><span class="door-why">' + esc(r.detail) + '</span>';
          }
          return '<tr><td>' + esc(r.at) + '</td><td>' + esc(r.who) + '</td><td>' + what + '</td><td class="door-hide-sm">' + esc(r.note) + '</td></tr>';
        }).join('') : '<tr><td colspan="4" class="door-empty">Nothing yet. Unlocks, added people and name changes will show here.</td></tr>';
      }

      /* ---------------------------------------------------------- enrolled people */
      var enrolled = [], loaded = false, loading = false, editing = null, saving = false;
      var enrBtn = $('doorEnrLoad'), enrQ = $('doorEnrQ'), enrMsg = $('doorEnrMsg');

      function showEnrolled() {
        var q = enrQ.value.trim().toLowerCase();
        var rows = enrolled.filter(function (u) { return !q || (u.name + ' ' + u.userId).toLowerCase().indexOf(q) !== -1; });
        $('doorEnr').innerHTML = rows.length ? rows.map(function (u) {
          var role = '<td class="door-hide-sm">' + (u.role === 'Admin' ? '<span class="wd-pill wd-pill--info">Device admin</span>' : esc(u.role)) + '</td>';
          if (u.uid === editing) {
            return '<tr><td class="door-id">' + esc(u.userId) + '</td><td><input type="text" class="wd-input door-row__name" id="doorEnrName" maxlength="' + boot.nameMax
              + '" value="' + esc(u.name) + '" aria-label="Name for ID ' + esc(u.userId) + '"><span class="door-why" id="doorEnrSaveErr" role="status"></span></td>' + role
              + '<td class="door-row__act"><button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-save="' + u.uid + '">Save</button> '
              + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-cancel="1">Cancel</button></td></tr>';
          }
          return '<tr><td class="door-id">' + esc(u.userId) + '</td><td>' + (u.name ? esc(u.name) : '<span class="wd-muted">(no name on the device)</span>') + '</td>' + role
            + '<td class="door-row__act"><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-edit="' + u.uid + '" aria-label="Edit the name of ID ' + esc(u.userId) + '"><i class="fa-solid fa-pen"></i> <span class="door-hide-sm">Edit name</span></button></td></tr>';
        }).join('') : '<tr><td colspan="4" class="door-empty">' + (enrolled.length ? 'Nobody matches that search.' : 'Nobody is enrolled on the device.') + '</td></tr>';
        $('doorPeopleN').textContent = enrolled.length || '';
        var box = $('doorEnrName');
        if (box) { box.focus(); box.select(); }
      }

      function loadEnrolled() {
        if (loading) { return; }
        loading = true; enrBtn.disabled = true;
        enrBtn.querySelector('span').textContent = 'Reading…';
        if (!loaded) { $('doorEnrEmpty').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Reading the list from the device…'; }
        post({ action: 'enrolled' }, function (j) {
          loading = false; enrBtn.disabled = !online;
          enrBtn.querySelector('span').textContent = 'Refresh';
          if (j.status !== 'ok') {
            say(enrMsg, false, j.msg || 'Could not read the list.');
            if (!loaded) { $('doorEnrEmpty').textContent = 'The list could not be read. Press Refresh to try again.'; }
            return;
          }
          if (!enrMsg.classList.contains('door-result--ok')) { enrMsg.hidden = true; }
          loaded = true; editing = null;
          enrolled = j.users || [];
          $('doorEnrCount').textContent = enrolled.length + (enrolled.length === 1 ? ' person' : ' people')
            + (j.fingers != null ? ' · ' + j.fingers + ' fingerprint' + (j.fingers === 1 ? '' : 's') : '');
          $('doorEnrEmpty').hidden = true; $('doorEnrWrap').hidden = false;
          enrQ.disabled = false; $('doorAddOpen').disabled = !online;
          showEnrolled();
        });
      }
      enrBtn.addEventListener('click', loadEnrolled);
      enrQ.addEventListener('input', showEnrolled);

      // correct a name: only the name is sent; the device keeps the person's ID, role and fingerprints
      function saveName(uid) {
        var u = enrolled.filter(function (x) { return x.uid === uid; })[0], box = $('doorEnrName'), err = $('doorEnrSaveErr');
        if (!u || !box || saving) { return; }
        if (box.value.trim() === u.name) { editing = null; showEnrolled(); return; }
        saving = true; err.textContent = 'Saving to the device…';
        post({ action: 'rename', uid: uid, userId: u.userId, name: box.value }, function (j) {
          saving = false;
          if (j.log) { showLog(j.log); }
          if (j.status !== 'ok') { err.textContent = j.msg || 'The name was not changed.'; return; }
          u.name = j.name; editing = null; showEnrolled();
          say(enrMsg, true, 'ID ' + u.userId + ' is now “' + j.name + '” on the device.');
        });
      }
      $('doorEnr').addEventListener('click', function (ev) {
        var b = ev.target.closest('button');
        if (!b || saving) { return; }
        if (b.hasAttribute('data-edit')) { editing = parseInt(b.getAttribute('data-edit'), 10); showEnrolled(); }
        else if (b.hasAttribute('data-cancel')) { editing = null; showEnrolled(); }
        else if (b.hasAttribute('data-save')) { saveName(parseInt(b.getAttribute('data-save'), 10)); }
      });
      $('doorEnr').addEventListener('keydown', function (ev) {
        if (ev.target.id !== 'doorEnrName') { return; }
        if (ev.key === 'Enter') { ev.preventDefault(); saveName(editing); }
        if (ev.key === 'Escape' && !saving) { editing = null; showEnrolled(); }
      });

      /* ---------------------------------------------------------- add a person */
      var addForm = $('doorAdd'), addName = $('doorAddName'), addId = $('doorAddId'), adding = false;
      $('doorAddNames').innerHTML = boot.employees.map(function (n) { return '<option value="' + esc(n) + '">'; }).join('');

      // what the device can hold: no accents, plain characters, at most nameMax
      function plain(s) {
        return s.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9 .,'-]/g, '').replace(/\s+/g, ' ').trim().slice(0, boot.nameMax).trim();
      }
      function nextId() {
        var max = 0;
        enrolled.forEach(function (u) { if (/^\d+$/.test(u.userId)) { max = Math.max(max, parseInt(u.userId, 10)); } });
        return String(max + 1);
      }
      function openAdd(on) {
        addForm.hidden = !on;
        if (on) { addName.value = ''; addId.value = nextId(); enrMsg.hidden = true; addName.focus(); }
      }
      $('doorAddOpen').addEventListener('click', function () { openAdd(addForm.hidden); });
      $('doorAddCancel').addEventListener('click', function () { openAdd(false); $('doorAddOpen').focus(); });
      addName.addEventListener('change', function () { addName.value = plain(addName.value); });
      addForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (adding) { return; }
        addName.value = plain(addName.value);
        adding = true; $('doorAddSave').disabled = true;
        $('doorAddSave').querySelector('span').textContent = 'Adding…';
        post({ action: 'add', userId: addId.value.trim(), name: addName.value }, function (j) {
          adding = false; $('doorAddSave').disabled = false;
          $('doorAddSave').querySelector('span').textContent = 'Add to device';
          if (j.log) { showLog(j.log); }
          if (j.status !== 'ok') { say(enrMsg, false, j.msg || 'Nobody was added.'); return; }
          openAdd(false);
          say(enrMsg, true, j.msg);
          loadEnrolled();
        });
      });

      showLog(boot.log);
      check();
    })();
    </script>
    <?php endif; ?>
  </body>
</html>
