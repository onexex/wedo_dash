<?php
/* ==========================================================================
   Holidays  —  themed maintenance module (wedo-theme.css).
   Rendered standalone by maintenance.php?holiday, which checks the session
   and the `hldy` right before including this file.

   Leads with the answers people come here for (is today a holiday, which is
   next, how many this year), lets you ask about any date, and filters the
   list by year / type / upcoming / name. Rows are rendered here with data-
   attributes; the filters only show and hide them (no reload). Add / edit /
   delete go to query/holiday-action.php (JSON) and then reload the page.
   ========================================================================== */
require_once __DIR__ . '/holiday-lib.php';

$comp    = (string) ($_SESSION['CompID'] ?? '');
$hldpdo  = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
$hldpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows    = hld_all($hldpdo, $comp);
$today   = hld_today();
$todayS  = $today->format('Y-m-d');
$ans     = hld_answers($rows, $today);
$token   = hld_csrf_token();
$year    = (int) $today->format('Y');

$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$long = function (string $ymd) { return date('D, M j, Y', strtotime($ymd)); };
/** "Today" / "Tomorrow" / "in N days" / "N days ago" */
$when = function (string $ymd) use ($today) {
    $d = new DateTimeImmutable($ymd, $today->getTimezone());
    $n = (int) $today->diff($d)->days;
    if ($n === 0) { return 'Today'; }
    if ($d > $today) { return $n === 1 ? 'Tomorrow' : "in $n days"; }
    return $n === 1 ? 'Yesterday' : "$n days ago";
};
$pill = function (int $type) {
    return $type === 2 ? '<span class="wd-pill wd-pill--warn">Special</span>' : '<span class="wd-pill wd-pill--info">Regular</span>';
};
// what the "Is this date a holiday?" box and the edit modal read (small: one entry per holiday)
$json = [];
foreach ($rows as $r) { $json[] = ['id' => $r['id'], 'date' => $r['date'], 'type' => $r['type'], 'desc' => $r['desc']]; }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Holidays" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <!-- Functional libs (Bootstrap modal) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (after bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">
    <script type="text/javascript" src="assets/js/script.js"></script>

    <style>
      /* --- holidays-scoped layout (built on wedo-theme tokens) --- */
      .hld-stat__sub{font-size:12.5px;color:var(--text-2);margin-top:4px;line-height:1.4}
      .hld-stat__value--text{font-size:17px;line-height:1.3}
      .wd-stat--today::before{background:var(--ok-text)}
      .hld-ask{display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;padding:18px 20px}
      .hld-ask .wd-field{margin:0;flex:0 0 200px}
      .hld-ask__answer{flex:1;min-width:220px;font-size:14px;color:var(--text);padding:11px 0}
      .hld-ask__answer b{font-family:var(--font-head)}
      .hld-ask__answer .wd-pill{margin-left:6px;vertical-align:middle}
      .hld-filters{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;padding:16px 20px;border-bottom:1px solid var(--border)}
      .hld-filters .wd-field{margin:0}
      .hld-filters .wd-field--grow{flex:1;min-width:200px}
      .hld-filters .wd-select,.hld-filters .wd-input{padding:9px 12px;font-size:13.5px}
      .hld-seg{display:inline-flex;border:1px solid var(--border-2);border-radius:var(--radius);overflow:hidden}
      .hld-seg button{border:none;background:var(--surface);color:var(--text-2);font:inherit;font-size:13px;font-weight:600;padding:9px 13px;cursor:pointer}
      .hld-seg button+button{border-left:1px solid var(--border-2)}
      .hld-seg button.is-on{background:var(--brand);color:#fff}
      .hld-count{padding:10px 20px 0;font-size:12.5px;color:var(--text-3)}
      .hld-date{white-space:nowrap}
      .hld-date small{display:block;color:var(--text-3);font-size:11.5px}
      .hld-when{white-space:nowrap;font-size:12.5px;color:var(--text-2)}
      tr.is-today td{background:var(--ok-bg)}
      tr.is-past td{color:var(--text-3)}
      tr.is-past td .wd-pill{opacity:.7}
      .hld-actions{white-space:nowrap;text-align:right}
      .hld-actions .wd-btn{padding:6px 9px}
      .hld-empty{text-align:center;color:var(--text-3);padding:26px 20px}
      .hld-empty[hidden]{display:none}
      .hld-msg{margin:0 0 12px;padding:10px 12px;border-radius:var(--radius);font-size:13px;background:var(--danger-bg);color:var(--danger-text)}
      .hld-msg[hidden]{display:none}
      .modal .wd-field:last-of-type{margin-bottom:0}
      .modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:14px 20px;border-top:1px solid var(--border)}
      @media (max-width:700px){ .hld-hide-sm{display:none} .hld-ask .wd-field{flex:1 1 100%} .wd-pagehead .wd-btn{width:100%;justify-content:center} }
    </style>
  </head>
  <body>
    <?php $wd_active = 'holiday'; include 'includes/wd-header.php'; ?>

      <div class="wd-pagehead">
        <div>
          <h1>Holidays</h1>
          <p>Regular and special holidays for <?php echo $e($_SESSION['CompanyName'] ?? 'the company'); ?>. They show on the Corner calendar and drive holiday pay.</p>
        </div>
        <button type="button" class="wd-btn wd-btn--primary" id="hldAdd"><i class="fa-solid fa-plus"></i> Add holiday</button>
      </div>

      <!-- the answers -->
      <div class="wd-stats">
        <div class="wd-stat wd-stat--today" id="hldToday">
          <div class="wd-stat__label"><i class="fa-solid fa-calendar-day"></i> Today, <?php echo $e(date('M j', strtotime($todayS))); ?></div>
          <?php if ($ans['today']): ?>
            <div class="wd-stat__value hld-stat__value--text"><?php echo $e($ans['today']['desc']); ?></div>
            <div class="hld-stat__sub"><?php echo $pill($ans['today']['type']); ?> holiday</div>
          <?php else: ?>
            <div class="wd-stat__value hld-stat__value--text">Not a holiday</div>
            <div class="hld-stat__sub">A regular work day</div>
          <?php endif; ?>
        </div>
        <div class="wd-stat wd-stat--next" id="hldNext">
          <div class="wd-stat__label"><i class="fa-solid fa-forward"></i> Next holiday</div>
          <?php if ($ans['next']): ?>
            <div class="wd-stat__value hld-stat__value--text"><?php echo $e($ans['next']['desc']); ?></div>
            <div class="hld-stat__sub"><?php echo $e($long($ans['next']['date'])); ?> · <?php echo $e($when($ans['next']['date'])); ?> <?php echo $pill($ans['next']['type']); ?></div>
          <?php else: ?>
            <div class="wd-stat__value hld-stat__value--text">None scheduled</div>
            <div class="hld-stat__sub">Add the coming holidays so the calendar and payroll know them</div>
          <?php endif; ?>
        </div>
        <div class="wd-stat" id="hldYear">
          <div class="wd-stat__label"><i class="fa-solid fa-calendar"></i> Holidays in <?php echo $year; ?></div>
          <div class="wd-stat__value"><?php echo (int) $ans['year']['total']; ?></div>
          <div class="hld-stat__sub"><?php echo (int) $ans['year']['regular']; ?> regular · <?php echo (int) $ans['year']['special']; ?> special</div>
        </div>
        <div class="wd-stat" id="hldLeft">
          <div class="wd-stat__label"><i class="fa-solid fa-hourglass-half"></i> Still to come in <?php echo $year; ?></div>
          <div class="wd-stat__value"><?php echo (int) $ans['year']['left']; ?></div>
          <div class="hld-stat__sub">after today</div>
        </div>
      </div>

      <!-- ask about a date -->
      <section class="wd-card">
        <div class="hld-ask">
          <div class="wd-field"><label for="hldAskDate">Is this date a holiday?</label><input type="date" class="wd-input" id="hldAskDate" value="<?php echo $todayS; ?>"></div>
          <div class="hld-ask__answer" id="hldAskAnswer" aria-live="polite"></div>
        </div>
      </section>

      <!-- the list -->
      <section class="wd-card" style="margin-top:16px">
        <form class="hld-filters" id="hldFilters" autocomplete="off" onsubmit="return false">
          <div class="wd-field"><label for="hldFYear">Year</label>
            <select class="wd-select" id="hldFYear">
              <option value="">All years</option>
              <?php foreach ($ans['years'] as $y): ?>
                <option value="<?php echo (int) $y; ?>"<?php echo $y === $year ? ' selected' : ''; ?>><?php echo (int) $y; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="wd-field"><label for="hldFType">Type</label>
            <select class="wd-select" id="hldFType">
              <option value="">All types</option>
              <option value="1">Regular</option>
              <option value="2">Special</option>
            </select>
          </div>
          <div class="wd-field"><label>Show</label>
            <div class="hld-seg" id="hldFWhen" role="group" aria-label="Show">
              <button type="button" data-when="" class="is-on">All</button>
              <button type="button" data-when="up">Upcoming</button>
              <button type="button" data-when="past">Past</button>
            </div>
          </div>
          <div class="wd-field wd-field--grow"><label for="hldFQ">Search</label><input type="search" class="wd-input" id="hldFQ" placeholder="Holiday name"></div>
        </form>
        <div class="hld-count" id="hldCount"></div>
        <div style="overflow-x:auto">
          <table class="wd-table" id="hldTable">
            <thead><tr><th>Date</th><th>Holiday</th><th>Type</th><th class="hld-hide-sm">When</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
              $cls = $r['date'] === $todayS ? 'is-today' : ($r['date'] < $todayS ? 'is-past' : 'is-up'); ?>
              <tr class="<?php echo $cls; ?>" data-id="<?php echo (int) $r['id']; ?>" data-date="<?php echo $e($r['date']); ?>"
                  data-year="<?php echo (int) substr($r['date'], 0, 4); ?>" data-type="<?php echo (int) $r['type']; ?>"
                  data-q="<?php echo $e(mb_strtolower($r['desc'])); ?>">
                <td class="hld-date"><?php echo $e(date('M j, Y', strtotime($r['date']))); ?><small><?php echo $e(date('l', strtotime($r['date']))); ?></small></td>
                <td><?php echo $e($r['desc']); ?></td>
                <td><?php echo $pill($r['type']); ?></td>
                <td class="hld-hide-sm hld-when"><?php echo $e($when($r['date'])); ?></td>
                <td class="hld-actions">
                  <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm hld-edit" title="Edit"><i class="fa-solid fa-pen"></i></button>
                  <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm hld-del" title="Delete"><i class="fa-solid fa-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <div class="hld-empty" id="hldEmpty"<?php echo $rows ? ' hidden' : ''; ?>>
            <?php echo $rows ? 'No holidays match these filters.' : 'No holidays yet. Add the first one with “Add holiday”.'; ?>
          </div>
        </div>
      </section>

      <!-- add / edit -->
      <div class="modal fade" id="hldModal" tabindex="-1" aria-labelledby="hldModalTitle">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
              <h4 class="modal-title" id="hldModalTitle">Add holiday</h4>
            </div>
            <form id="hldForm" class="modal-body" autocomplete="off">
              <input type="hidden" name="token" value="<?php echo $e($token); ?>">
              <input type="hidden" name="act" id="hldAct" value="add">
              <input type="hidden" name="id" id="hldId" value="0">
              <p class="hld-msg" id="hldMsg" hidden></p>
              <div class="wd-field"><label for="hldDate">Date</label><input type="date" class="wd-input" id="hldDate" name="hldate" required></div>
              <div class="wd-field"><label for="hldDesc">Holiday</label><input class="wd-input" id="hldDesc" name="desc" maxlength="<?php echo HLD_DESC_MAX; ?>" placeholder="e.g. Independence Day" required></div>
              <div class="wd-field"><label for="hldType">Type</label>
                <select class="wd-select" id="hldType" name="hldtype">
                  <?php foreach (HLD_TYPES as $k => $label): ?><option value="<?php echo $k; ?>"><?php echo $e($label); ?></option><?php endforeach; ?>
                </select>
              </div>
            </form>
            <div class="modal-footer">
              <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
              <button type="button" class="wd-btn wd-btn--primary" id="hldSave"><i class="fa-solid fa-check"></i> Save</button>
            </div>
          </div>
        </div>
      </div>

    <?php include 'includes/wd-footer.php'; ?>

    <script>
    window.HLD = { rows: <?php echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>,
                   today: <?php echo json_encode($todayS); ?>, token: <?php echo json_encode($token); ?>,
                   types: <?php echo json_encode(HLD_TYPES); ?> };
    (function () {
      var $ = jQuery, H = window.HLD, byDate = {};
      H.rows.forEach(function (r) { byDate[r.date] = r; });
      var MS = 86400000;
      function parse(ymd) { var p = ymd.split('-'); return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2])); }
      function daysFromToday(ymd) { return Math.round((parse(ymd) - parse(H.today)) / MS); }
      function fmt(ymd) { return parse(ymd).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }); }
      function when(ymd) { var n = daysFromToday(ymd); return n === 0 ? 'today' : n === 1 ? 'tomorrow' : n > 0 ? 'in ' + n + ' days' : n === -1 ? 'yesterday' : (-n) + ' days ago'; }
      function esc(s) { return $('<i>').text(s).html(); }
      function pill(t) { return t == 2 ? '<span class="wd-pill wd-pill--warn">Special</span>' : '<span class="wd-pill wd-pill--info">Regular</span>'; }

      /* ---- Is this date a holiday? ---- */
      var askDate = $('#hldAskDate'), askOut = $('#hldAskAnswer');
      function ask() {
        var d = askDate.val();
        if (!d) { askOut.html('Pick a date.'); return; }
        var r = byDate[d];
        if (r) {
          askOut.html('<b>Yes.</b> ' + esc(fmt(d)) + ' is <b>' + esc(r.desc) + '</b>' + pill(r.type) + ' <span class="wd-muted">(' + esc(when(d)) + ')</span>');
          return;
        }
        var next = null;
        H.rows.forEach(function (x) { if (x.date > d && !next) { next = x; } });
        askOut.html('<b>No.</b> ' + esc(fmt(d)) + ' is a regular work day' +
          (next ? '. The next holiday after it is <b>' + esc(next.desc) + '</b> on ' + esc(fmt(next.date)) + '.' : ', and no holiday is listed after it.'));
      }
      askDate.on('change input', ask); ask();

      /* ---- filters (show / hide the rendered rows) ---- */
      var fYear = $('#hldFYear'), fType = $('#hldFType'), fQ = $('#hldFQ'), fWhen = '', rows = $('#hldTable tbody tr');
      var KEY = 'wd-holiday-filters';
      try { var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
            if (saved) { fYear.val(saved.year); if (fYear.val() === null) { fYear.val(''); } fType.val(saved.type); fQ.val(saved.q); fWhen = saved.when || '';
                         $('#hldFWhen button').removeClass('is-on').filter('[data-when="' + fWhen + '"]').addClass('is-on'); } } catch (e) {}
      function apply() {
        var y = fYear.val(), t = fType.val(), q = $.trim(fQ.val()).toLowerCase(), shown = 0;
        rows.each(function () {
          var r = $(this), d = r.data('date') + '';
          var ok = (!y || r.data('year') + '' === y) && (!t || r.data('type') + '' === t)
                && (!q || (r.data('q') + '').indexOf(q) !== -1)
                && (!fWhen || (fWhen === 'up' ? d >= H.today : d < H.today));
          r.toggle(ok); if (ok) { shown++; }
        });
        $('#hldEmpty').prop('hidden', shown > 0 || rows.length === 0);
        $('#hldCount').text(rows.length ? 'Showing ' + shown + ' of ' + rows.length + ' holiday' + (rows.length === 1 ? '' : 's') : '');
        try { sessionStorage.setItem(KEY, JSON.stringify({ year: y, type: t, q: fQ.val(), when: fWhen })); } catch (e) {}
      }
      fYear.add(fType).on('change', apply); fQ.on('input', apply);
      $('#hldFWhen').on('click', 'button', function () { fWhen = $(this).data('when') + ''; $('#hldFWhen button').removeClass('is-on'); $(this).addClass('is-on'); apply(); });
      apply();

      /* ---- add / edit / delete ---- */
      var modal = $('#hldModal'), msg = $('#hldMsg');
      function open(r) {
        msg.prop('hidden', true).text('');
        $('#hldModalTitle').text(r ? 'Edit holiday' : 'Add holiday');
        $('#hldAct').val(r ? 'update' : 'add'); $('#hldId').val(r ? r.id : 0);
        $('#hldDate').val(r ? r.date : ''); $('#hldDesc').val(r ? r.desc : ''); $('#hldType').val(r ? r.type : 1);
        modal.modal('show');
        modal.one('shown.bs.modal', function () { $(r ? '#hldDesc' : '#hldDate').trigger('focus'); });
      }
      function send(data, done) {
        $.ajax({ url: 'query/holiday-action.php', type: 'post', data: data, dataType: 'json' })
          .done(done)
          .fail(function (x) { var j = x.responseJSON; done(j && j.msg ? j : { ok: false, msg: 'The change could not be saved. Please try again.' }); });
      }
      $('#hldAdd').on('click', function () { open(null); });
      $('#hldTable').on('click', '.hld-edit', function () {
        var id = $(this).closest('tr').data('id'), r = null;
        H.rows.forEach(function (x) { if (x.id == id) { r = x; } });
        if (r) { open(r); }
      });
      $('#hldTable').on('click', '.hld-del', function () {
        var tr = $(this).closest('tr'), id = tr.data('id'), name = tr.find('td').eq(1).text();
        if (!window.confirm('Delete "' + name + '" (' + tr.data('date') + ')?\nIt will leave the calendar and holiday pay will no longer apply to that day.')) { return; }
        send({ token: H.token, act: 'delete', id: id }, function (j) { if (j.ok) { location.reload(); } else { window.alert(j.msg); } });
      });
      $('#hldSave').on('click', function () {
        var btn = $(this).prop('disabled', true);
        send($('#hldForm').serialize(), function (j) {
          btn.prop('disabled', false);
          if (j.ok) { location.reload(); } else { msg.text(j.msg).prop('hidden', false); }
        });
      });
      $('#hldForm').on('submit', function (ev) { ev.preventDefault(); $('#hldSave').trigger('click'); });
    })();
    </script>
  </body>
</html>
