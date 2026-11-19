<?php
/* ==========================================================================
   gsm.php  —  Management > SIM Inbox.
   Texts received by the office GoIP GSM gateway, each SIM's load balance and
   expiry, and whether the office-PC collector is still sending. Gated by the
   `gsminbox` access right. Rules: includes/gsm-lib.php.
   Reads and edits: query/gsm-action.php. Texts arrive via api/gsm-ingest.php.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/gsm-lib.php';

/* access gate BEFORE any output so the redirect can fire (least privilege) */
try {
    $gsmpdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $gsmpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!gsm_can($gsmpdo)) { header('location: 404?'); exit(); }

$ready = gsm_ready($gsmpdo);
$cfg   = gsm_config();
$boot  = ['endpoint' => 'query/gsm-action.php', 'token' => gsm_csrf_token(), 'warnDays' => $cfg['warn_days'],
          'keySet' => $cfg['key'] !== '',
          'lines' => $ready ? gsm_lines_view($gsmpdo) : [], 'collectors' => $ready ? gsm_collectors_view($gsmpdo) : [],
          'messages' => $ready ? gsm_messages($gsmpdo) : []];

$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "SIM Inbox" : $e($_SESSION['CompanyName']); ?></title>
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
      /* --- SIM-inbox-scoped layout (built on wedo-theme tokens) --- */
      .gsm-col{display:flex;align-items:center;gap:14px;padding:14px 18px;margin-top:14px}
      .gsm-col__icon{width:40px;height:40px;border-radius:12px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;
        background:var(--surface-2);color:var(--text-3);font-size:17px}
      .gsm-col[data-state=ok] .gsm-col__icon{background:var(--ok-bg);color:var(--ok-text)}
      .gsm-col[data-state=gateway] .gsm-col__icon{background:var(--warn-bg);color:var(--warn-text)}
      .gsm-col[data-state=stale] .gsm-col__icon,.gsm-col[data-state=none] .gsm-col__icon{background:var(--danger-bg);color:var(--danger-text)}
      .gsm-col__body{flex:1;min-width:0}
      .gsm-col__title{font-weight:600;font-size:14px;color:var(--text)}
      .gsm-col__msg{font-size:12.5px;color:var(--text-2);margin:2px 0 0;line-height:1.5}
      .gsm-col__more{font-size:12px;color:var(--text-3);white-space:nowrap}

      .gsm-lines{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-top:14px}
      .gsm-line{padding:16px 18px;display:flex;flex-direction:column;gap:10px;position:relative;overflow:hidden}
      .gsm-line::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--border-2)}
      .gsm-line[data-state=ok]::before{background:var(--ok-text)}
      .gsm-line[data-state=soon]::before{background:var(--warn-text)}
      .gsm-line[data-state=expired]::before{background:var(--danger-text)}
      .gsm-line__head{display:flex;align-items:flex-start;gap:10px}
      .gsm-line__no{font-size:11.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--text-3)}
      .gsm-line__name{font-family:var(--font-head);font-weight:700;font-size:16px;color:var(--text);margin:2px 0 0;word-break:break-word}
      .gsm-line__num{font-size:12.5px;color:var(--text-2);font-variant-numeric:tabular-nums}
      .gsm-line__edit{margin-left:auto}
      .gsm-line__bal{font-family:var(--font-head);font-weight:700;font-size:24px;color:var(--text);font-variant-numeric:tabular-nums;line-height:1.1}
      .gsm-line__bal small{display:block;font-family:var(--font-body);font-weight:400;font-size:12px;color:var(--text-3);margin-top:3px}
      .gsm-line__meta{font-size:12px;color:var(--text-3);line-height:1.5}
      .gsm-line__meta button{border:0;background:none;padding:0;color:var(--brand);font-weight:600;cursor:pointer;font-size:12px}
      .gsm-line__meta button:hover{text-decoration:underline}
      .gsm-form{display:grid;gap:10px}
      .gsm-form[hidden]{display:none}
      .gsm-form label{display:block;font-size:12px;font-weight:600;color:var(--text-2);margin-bottom:4px}
      .gsm-form__two{display:grid;grid-template-columns:1fr 1fr;gap:10px}
      .gsm-form__act{display:flex;gap:8px;flex-wrap:wrap}
      .gsm-form__err{font-size:12px;color:var(--danger-text);margin:0}
      .gsm-form__err:empty{display:none}

      .gsm-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)}
      .gsm-bar h2{font-size:16px;margin:0 auto 0 0}
      .gsm-bar .wd-select{width:auto;min-width:150px}
      .gsm-bar .wd-input{width:220px}
      .gsm-only{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--text-2);cursor:pointer;margin:0;font-weight:500}
      .gsm-only input{margin:0;accent-color:var(--brand)}
      .gsm-empty{text-align:center;color:var(--text-3);padding:26px 16px;font-size:13px}
      .gsm-at{white-space:nowrap;font-size:12.5px;color:var(--text-2)}
      .gsm-at small{display:block;color:var(--text-3);font-size:11px}
      .gsm-from{white-space:nowrap;font-weight:600}
      .gsm-text{white-space:pre-wrap;word-break:break-word;max-width:640px;line-height:1.5}
      .wd-table tr.gsm-hit td{background:var(--info-bg)}
      .wd-table tr.gsm-new td{animation:gsmNew 2.5s ease-out}
      @keyframes gsmNew{from{background:var(--ok-bg)}to{background:transparent}}
      .gsm-more{display:flex;justify-content:center;padding:14px}
      .gsm-more[hidden]{display:none}
      .gsm-msg{margin:12px 18px 0;padding:10px 14px;border-radius:var(--radius);font-size:13px;background:var(--danger-bg);color:var(--danger-text)}
      .gsm-msg[hidden]{display:none}

      @media (max-width:860px){
        .gsm-hide-sm{display:none}
        .gsm-bar .wd-input,.gsm-bar .wd-select{width:100%;flex:1 1 100%}
        .gsm-col{flex-wrap:wrap}
      }
      @media (prefers-reduced-motion:reduce){ .wd-table tr.gsm-new td{animation:none} }
    </style>
  </head>
  <body>
    <?php
      $wd_active = 'gsm';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>SIM Inbox</h1>
          <p>Texts received by the office GSM gateway, and how much load each SIM has left.</p>
        </div>
      </div>

    <?php if (!$ready): ?>
      <section class="wd-card"><div class="gsm-empty">This page is not set up on this server yet — run <code><?php echo $e(GSM_SQL); ?></code>.</div></section>
    <?php else: ?>

      <div id="gsmCols" aria-live="polite"></div>

      <div class="gsm-lines" id="gsmLines"></div>

      <section class="wd-card" style="margin-top:16px" aria-labelledby="gsmInboxH">
        <div class="gsm-bar">
          <h2 id="gsmInboxH">Texts</h2>
          <select class="wd-select" id="gsmLine" aria-label="Line"><option value="0">All lines</option></select>
          <label class="gsm-only"><input type="checkbox" id="gsmCarrier"> Carrier texts only</label>
          <input type="search" class="wd-input" id="gsmQ" placeholder="Search texts&hellip;" aria-label="Search texts" maxlength="80">
        </div>
        <p class="gsm-msg" id="gsmErr" hidden role="alert"></p>
        <div style="overflow-x:auto">
          <table class="wd-table">
            <thead><tr><th>Received</th><th>Line</th><th>From</th><th>Text</th></tr></thead>
            <tbody id="gsmList"></tbody>
          </table>
        </div>
        <div class="gsm-more" id="gsmMore" hidden><button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="gsmMoreBtn"><span>Show older texts</span></button></div>
      </section>

    <?php endif; ?>

    <?php include 'includes/wd-footer.php'; ?>

    <?php if ($ready): ?>
    <script>
    (function () {
      var boot = <?php echo json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
      var $ = function (id) { return document.getElementById(id); };
      var lines = boot.lines, msgs = boot.messages, editing = 0, hit = 0, loading = false;
      var PAGE = 50;

      function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML.replace(/"/g, '&quot;'); }
      function peso(n) { return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
      function lineName(l) { return l.label || ('Line ' + l.line); }

      function post(data, done) {
        var body = new URLSearchParams(data); body.set('token', boot.token);
        fetch(boot.endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'The server gave an unreadable answer. Reload the page and try again.' }; }); })
          .then(done)
          .catch(function () { done({ status: 'error', msg: 'Could not reach WeDo. Check your connection.' }); });
      }

      /* ---------------------------------------------------------- collector */
      var COL = { ok: ['fa-solid fa-tower-broadcast', 'wd-pill--ok', 'Running'],
                  gateway: ['fa-solid fa-triangle-exclamation', 'wd-pill--warn', 'Gateway problem'],
                  stale: ['fa-solid fa-plug-circle-xmark', 'wd-pill--danger', 'Stopped'] };
      function ago(s) {
        if (s < 90) { return 'just now'; }
        if (s < 3600) { return Math.round(s / 60) + ' min ago'; }
        if (s < 86400) { return Math.round(s / 3600) + ' h ago'; }
        return Math.round(s / 86400) + ' days ago';
      }
      function showCollectors(cols) {
        if (!cols.length) {
          $('gsmCols').innerHTML = '<section class="wd-card gsm-col" data-state="none"><div class="gsm-col__icon"><i class="fa-solid fa-plug-circle-xmark"></i></div>'
            + '<div class="gsm-col__body"><div class="gsm-col__title">No collector has checked in yet</div><p class="gsm-col__msg">'
            + (boot.keySet ? 'Texts appear here once the collector on an office PC starts sending (guide: tools/gsm-collector.md).'
                           : 'This server has no collector key yet: add \'gsm\' =&gt; [\'ingest_key\' =&gt; ...] to config.local.php, then set up the collector on an office PC (tools/gsm-collector.md).')
            + '</p></div></section>';
          return;
        }
        $('gsmCols').innerHTML = cols.map(function (c) {
          var k = COL[c.state] || COL.stale;
          return '<section class="wd-card gsm-col" data-state="' + esc(c.state) + '"><div class="gsm-col__icon"><i class="' + k[0] + '"></i></div>'
            + '<div class="gsm-col__body"><div class="gsm-col__title">Collector “' + esc(c.name) + '” <span class="wd-pill ' + k[1] + '">' + k[2] + '</span></div>'
            + '<p class="gsm-col__msg">' + esc(c.msg) + '</p></div>'
            + '<span class="gsm-col__more" title="' + esc(c.seen) + '">Checked in ' + ago(c.ago) + '</span></section>';
        }).join('');
      }

      /* ---------------------------------------------------------- lines */
      var PILL = { ok: 'wd-pill--ok', soon: 'wd-pill--warn', expired: 'wd-pill--danger', unknown: '' };
      function showLines() {
        if (!lines.length) {
          $('gsmLines').innerHTML = '<section class="wd-card gsm-line" style="grid-column:1/-1"><div class="gsm-empty">No SIM lines yet. They appear when the collector first checks in.</div></section>';
        } else {
          $('gsmLines').innerHTML = lines.map(function (l) {
            var x = l.expiry, src = '';
            if (l.source === 'text' && l.sourceSms) { src = 'From a carrier text · <button type="button" data-show="' + l.sourceSms + '" data-line="' + l.line + '">see it</button>'; }
            else if (l.source === 'manual') { src = 'Set by hand'; }
            var body = editing === l.line ? form(l) :
              '<div class="gsm-line__bal">' + (l.balance != null ? peso(l.balance) : '—')
                + '<small>' + (l.balance != null ? 'Balance as of ' + esc(l.balanceAt) : 'Balance not known yet') + '</small></div>'
              + '<div><span class="wd-pill ' + PILL[x.state] + '">' + esc(x.text) + '</span></div>'
              + '<div class="gsm-line__meta">' + (src ? src + '<br>' : '')
                + (l.texts ? l.texts + (l.texts === 1 ? ' text' : ' texts') + ' · last ' + esc(l.lastText) : 'No texts yet') + '</div>';
            return '<section class="wd-card gsm-line" data-state="' + esc(x.state) + '" aria-label="' + esc(lineName(l)) + '">'
              + '<div class="gsm-line__head"><div><div class="gsm-line__no">Line ' + l.line + '</div>'
              + '<div class="gsm-line__name">' + esc(l.label || 'Unnamed SIM') + '</div>'
              + (l.number ? '<div class="gsm-line__num">' + esc(l.number) + '</div>' : '') + '</div>'
              + (editing === l.line ? '' : '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm gsm-line__edit" data-edit="' + l.line + '" aria-label="Edit line ' + l.line + '"><i class="fa-solid fa-pen"></i></button>')
              + '</div>' + body + '</section>';
          }).join('');
        }
        var sel = $('gsmLine'), cur = sel.value;
        sel.innerHTML = '<option value="0">All lines</option>' + lines.map(function (l) {
          return '<option value="' + l.line + '">Line ' + l.line + (l.label ? ' · ' + esc(l.label) : '') + '</option>';
        }).join('');
        sel.value = cur;
        if (sel.value !== cur) { sel.value = '0'; }
        var f = $('gsmF_label'); if (f) { f.focus(); }
      }
      function form(l) {
        return '<form class="gsm-form" data-form="' + l.line + '" autocomplete="off">'
          + '<div><label for="gsmF_label">Name</label><input class="wd-input" id="gsmF_label" maxlength="60" value="' + esc(l.label) + '" placeholder="e.g. HR hotline"></div>'
          + '<div><label for="gsmF_number">SIM number</label><input class="wd-input" id="gsmF_number" maxlength="20" inputmode="tel" value="' + esc(l.number) + '" placeholder="0917 123 4567"></div>'
          + '<div class="gsm-form__two"><div><label for="gsmF_expires">Load expires</label><input type="date" class="wd-input" id="gsmF_expires" value="' + esc(l.expiresOn) + '"></div>'
          + '<div><label for="gsmF_balance">Balance (₱)</label><input class="wd-input" id="gsmF_balance" inputmode="decimal" value="' + (l.balance != null ? esc(l.balance.toFixed(2)) : '') + '"></div></div>'
          + '<p class="gsm-form__err" id="gsmF_err" role="status"></p>'
          + '<div class="gsm-form__act"><button type="submit" class="wd-btn wd-btn--primary wd-btn--sm">Save</button>'
          + '<button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" data-cancel="1">Cancel</button></div></form>';
      }
      $('gsmLines').addEventListener('click', function (ev) {
        var b = ev.target.closest('button');
        if (!b) { return; }
        if (b.hasAttribute('data-edit')) { editing = parseInt(b.getAttribute('data-edit'), 10); showLines(); }
        else if (b.hasAttribute('data-cancel')) { var n = editing; editing = 0; showLines(); focusEdit(n); }
        else if (b.hasAttribute('data-show')) { showText(parseInt(b.getAttribute('data-show'), 10), parseInt(b.getAttribute('data-line'), 10)); }
      });
      $('gsmLines').addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && editing) { var n = editing; editing = 0; showLines(); focusEdit(n); }
      });
      function focusEdit(n) { var b = document.querySelector('[data-edit="' + n + '"]'); if (b) { b.focus(); } }
      $('gsmLines').addEventListener('submit', function (ev) {
        ev.preventDefault();
        var f = ev.target, n = parseInt(f.getAttribute('data-form'), 10), btn = f.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        post({ action: 'save_line', line: n, label: $('gsmF_label').value, number: $('gsmF_number').value,
               expires: $('gsmF_expires').value, balance: $('gsmF_balance').value }, function (j) {
          if (j.status !== 'ok') { btn.disabled = false; btn.textContent = 'Save'; $('gsmF_err').textContent = j.msg || 'Not saved.'; return; }
          lines = j.lines; editing = 0; showLines(); focusEdit(n);
        });
      });

      /* ---------------------------------------------------------- texts */
      function row(m, fresh) {
        var l = lines.filter(function (x) { return x.line === m.line; })[0];
        return '<tr data-id="' + m.id + '"' + (m.id === hit ? ' class="gsm-hit"' : (fresh ? ' class="gsm-new"' : '')) + '>'
          + '<td class="gsm-at">' + esc(m.at) + (m.deviceTime ? '<small class="gsm-hide-sm" title="Time on the gateway (its clock may be off)">gateway ' + esc(m.deviceTime) + '</small>' : '') + '</td>'
          + '<td>' + m.line + (l && l.label ? '<span class="wd-muted gsm-hide-sm"> · ' + esc(l.label) + '</span>' : '') + '</td>'
          + '<td class="gsm-from">' + esc(m.sender) + (m.carrier ? ' <span class="wd-pill wd-pill--info">carrier</span>' : '') + '</td>'
          + '<td class="gsm-text">' + esc(m.text) + '</td></tr>';
      }
      function showList(newIds) {
        $('gsmList').innerHTML = msgs.length ? msgs.map(function (m) { return row(m, newIds && newIds[m.id]); }).join('')
          : '<tr><td colspan="4" class="gsm-empty">' + (filtered() ? 'No texts match.' : 'No texts yet.') + '</td></tr>';
      }
      function filtered() { return $('gsmLine').value !== '0' || $('gsmCarrier').checked || $('gsmQ').value.trim() !== ''; }
      function filters(extra) {
        var f = { line: $('gsmLine').value, carrier: $('gsmCarrier').checked ? '1' : '', q: $('gsmQ').value.trim() };
        for (var k in extra) { f[k] = extra[k]; }
        return f;
      }
      function err(text) { $('gsmErr').hidden = !text; $('gsmErr').textContent = text || ''; }

      function load(more) {
        if (loading) { return; }
        loading = true;
        var btn = $('gsmMoreBtn');
        if (more) { btn.disabled = true; btn.querySelector('span').textContent = 'Loading…'; }
        post(filters({ action: 'messages', before: more && msgs.length ? msgs[msgs.length - 1].id : 0 }), function (j) {
          loading = false; btn.disabled = false; btn.querySelector('span').textContent = 'Show older texts';
          if (j.status !== 'ok') { err(j.msg); return; }
          err('');
          msgs = more ? msgs.concat(j.messages) : j.messages;
          $('gsmMore').hidden = j.messages.length < PAGE;
          showList();
          if (hit) { var r = document.querySelector('tr[data-id="' + hit + '"]'); if (r) { r.scrollIntoView({ block: 'center', behavior: 'smooth' }); } }
        });
      }
      function showText(id, line) {
        hit = id; $('gsmLine').value = String(line); $('gsmCarrier').checked = true; $('gsmQ').value = '';
        load(false);
      }
      var qTimer = null;
      $('gsmLine').addEventListener('change', function () { hit = 0; load(false); });
      $('gsmCarrier').addEventListener('change', function () { hit = 0; load(false); });
      $('gsmQ').addEventListener('input', function () { clearTimeout(qTimer); qTimer = setTimeout(function () { hit = 0; load(false); }, 300); });
      $('gsmMoreBtn').addEventListener('click', function () { load(true); });

      /* ---------------------------------------------------------- every minute */
      function refresh() {
        if (document.hidden || editing || loading) { return; }
        post(filters({ action: 'refresh', after: msgs.length ? msgs[0].id : 0 }), function (j) {
          if (j.status !== 'ok') { return; }
          lines = j.lines; showLines(); showCollectors(j.collectors);
          if (!msgs.length) { load(false); return; }   // nothing listed yet: nothing to be "newer than"
          if (j.messages.length) {
            var ids = {}; j.messages.forEach(function (m) { ids[m.id] = true; });
            msgs = j.messages.concat(msgs); showList(ids);
          }
        });
      }
      setInterval(refresh, 60000);
      document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });

      showCollectors(boot.collectors);
      showLines();
      showList();
      $('gsmMore').hidden = msgs.length < PAGE;
    })();
    </script>
    <?php endif; ?>
  </body>
</html>
