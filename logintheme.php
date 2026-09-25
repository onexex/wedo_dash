<?php
/* ==========================================================================
   logintheme.php  —  Maintenance > Login Theme.
   Schedule seasonal looks for login.php. Each entry turns itself on and off on
   its dates (optionally every year). Which one shows today is decided by
   lt_pick() in includes/login-theme.php; writes go to
   query/logintheme-action.php. Gated by the `logintheme` access right.
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
date_default_timezone_set("Asia/Manila");
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit(); }

include 'w_conn.php';
require_once __DIR__ . '/includes/login-theme.php';

/* access gate BEFORE any output so the redirect can fire (least privilege) */
try {
    $ltpdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $ltpdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("ERROR: Could not connect."); }
if (!lt_can_manage($ltpdo)) { header('location: 404?'); exit(); }

$today   = lt_today();
$presets = lt_presets();
$rows    = $ltpdo->query("SELECT * FROM login_themes ORDER BY starts_on")->fetchAll(PDO::FETCH_ASSOC);
$live    = lt_pick($rows, $today);
foreach ($rows as &$r) {
    $r['status'] = lt_status($r, $today, $live);
    $next = lt_next_start($r, $today->setTime(0, 0));
    $r['sort'] = $r['status']['key'] === 'live' ? -1 : ($next ? $next->getTimestamp() : PHP_INT_MAX);
}
unset($r);
usort($rows, function ($a, $b) {
    return [empty($a['is_active']) ? 1 : 0, $a['sort']] <=> [empty($b['is_active']) ? 1 : 0, $b['sort']];
});

$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$range = function ($t) {
    $s = lt_date($t['starts_on']); $f = lt_date($t['ends_on']);
    return !empty($t['repeats_yearly'])
        ? $s->format('M j') . ' – ' . $f->format('M j')
        : $s->format('M j, Y') . ' – ' . $f->format('M j, Y');
};
$swatch = function ($key) use ($presets) {
    $c = $presets[$key]['colors'] ?? [];
    return [$c['panel1'] ?? '#f93627', $c['primary'] ?? '#bf2417', $c['accent'] ?? '#ffd76a'];
};
$pillClass = ['live' => 'ok', 'upcoming' => 'info', 'off' => 'off', 'ended' => 'warn'];
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <title><?php echo ($_SESSION['CompanyName'] ?? '') == "" ? "Login Theme" : $e($_SESSION['CompanyName']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?php echo (($_SESSION['CompanyLogo'] ?? '') != "") ? $e($_SESSION['CompanyLogo']) : "assets/images/logos/logo-2.png"; ?>" type="image/x-icon">

    <!-- Functional libs -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (after bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">
    <script type="text/javascript" src="assets/js/script.js"></script>

    <style>
      /* --- login-theme-scoped layout (built on wedo-theme tokens) --- */
      .lt-today{display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:16px 20px}
      .lt-thumb{width:56px;height:56px;border-radius:14px;flex:0 0 auto;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px}
      .lt-today__copy{flex:1;min-width:220px}
      .lt-eyebrow{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3)}
      .lt-today__copy h3{font-size:16px;margin:2px 0}
      .lt-today__copy p{margin:0;font-size:13px;color:var(--text-2)}
      .lt-sw{width:36px;height:36px;border-radius:10px;flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:14px}
      .lt-entry{display:flex;align-items:center;gap:12px}
      .lt-entry b{display:block}
      .lt-entry small{color:var(--text-3);font-size:12px}
      .lt-dates small{display:block;color:var(--text-3);font-size:11.5px}
      .lt-extras i{color:var(--border-2);margin-right:9px;font-size:14px}
      .lt-extras i.on{color:var(--brand)}
      tr.is-off td{opacity:.62}
      .lt-actions{display:flex;gap:6px;justify-content:flex-end}
      .lt-switch{position:relative;display:inline-block;width:42px;height:23px;margin:0;vertical-align:middle}
      .lt-switch input{opacity:0;width:0;height:0}
      .lt-switch span{position:absolute;inset:0;cursor:pointer;background:var(--border-2);border-radius:999px;transition:.2s}
      .lt-switch span::before{content:"";position:absolute;width:17px;height:17px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.3)}
      .lt-switch input:checked + span{background:var(--ok-text)}
      .lt-switch input:checked + span::before{transform:translateX(19px)}
      .lt-switch input:focus-visible + span{outline:3px solid var(--brand-tint);outline-offset:2px}
      .wd-pill--off{background:var(--surface-2);color:var(--text-2)} /* neutral: "off" is a choice, not a problem */
      .lt-help{padding:12px 20px;font-size:12.5px;color:var(--text-2);border-top:1px solid var(--border);background:var(--surface-2)}
      .lt-empty{padding:34px;text-align:center;color:var(--text-3)}

      /* modal: body scrolls, header/footer (Save) stay on screen */
      #mdlTheme .modal-dialog{width:min(980px, 96vw)}
      #mdlTheme .modal-content{border-radius:var(--radius-lg);overflow:hidden;border:0}
      #mdlTheme .modal-body{max-height:calc(100vh - 190px);overflow-y:auto;background:var(--bg)}
      .lt-sec{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:12px}
      .lt-sec__title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);margin-bottom:10px}
      .lt-sec__title span{text-transform:none;letter-spacing:0;font-weight:400}
      .lt-presets{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}
      .lt-preset{position:relative;display:flex;align-items:center;gap:10px;border:1.5px solid var(--border);border-radius:var(--radius);padding:8px 10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text);margin:0;background:#fff}
      .lt-preset input{position:absolute;opacity:0;pointer-events:none}
      .lt-preset .lt-sw{width:28px;height:28px;font-size:12px;border-radius:8px}
      .lt-preset.selected{border-color:var(--brand);background:var(--brand-tint)}
      .lt-preset:focus-within{outline:2px solid var(--brand);outline-offset:1px}
      .lt-row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
      .lt-check{display:flex;align-items:center;gap:9px;font-size:13px;color:var(--text);margin:6px 0 0;cursor:pointer;font-weight:400}
      .lt-check input{margin:0}
      .lt-hint{font-size:12px;color:var(--text-3);margin-top:4px}
      .lt-err{display:block;color:var(--danger-text);font-size:12px;margin-top:4px}
      .lt-mini{border-radius:12px;padding:22px 16px;text-align:center;color:#fff;min-height:220px;display:flex;flex-direction:column;align-items:center;justify-content:center;background-size:cover;background-position:center;position:relative;overflow:hidden}
      .lt-mini > *{position:relative}
      .lt-mini.with-banner::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.15),rgba(0,0,0,.6))}
      .lt-mini__pill{font-size:9.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:3px 9px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);margin-bottom:10px}
      .lt-mini__logo{position:relative;margin-bottom:10px}
      .lt-mini__logo img{height:34px;width:auto}
      .lt-mini__badge{position:absolute;top:-10px;right:-16px;width:24px;height:24px;border-radius:50%;border:2px solid #fff;display:flex;align-items:center;justify-content:center;font-size:10px}
      .lt-mini__title{font-family:var(--font-head);font-weight:800;font-size:18px;line-height:1.2;margin-bottom:4px}
      .lt-mini__msg{font-size:11.5px;opacity:.8;max-width:32ch;line-height:1.45}
      .lt-mini__ann{margin-top:10px;font-size:11px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:5px 8px}
      .lt-banner-cur{display:flex;align-items:center;gap:10px;margin-top:8px;font-size:12.5px}
      .lt-banner-cur img{width:84px;height:46px;object-fit:cover;border-radius:6px;border:1px solid var(--border)}
      .is-hidden{display:none !important}
      @media (max-width:767px){ .lt-row2{grid-template-columns:1fr} .lt-hide-sm{display:none} }
    </style>
  </head>
  <body>
    <?php
      $wd_active = 'logintheme';
      include 'includes/wd-header.php';   // provides $wdpdo, $ar, $wdName
    ?>

      <div class="wd-pagehead">
        <div>
          <h1>Login Theme</h1>
          <p>Give the sign-in page a seasonal look. Each entry turns itself on and off on its dates.</p>
        </div>
        <button type="button" class="wd-btn wd-btn--primary" id="btnNew"><i class="fa-solid fa-plus"></i> New entry</button>
      </div>

      <section class="wd-card" style="margin-top:16px">
        <div class="lt-today">
          <?php if ($live): [$c1, $c2, $c3] = $swatch($live['preset']); ?>
            <div class="lt-thumb" style="background:linear-gradient(135deg, <?php echo $c1; ?>, <?php echo $c2; ?>)"><i class="fa-solid <?php echo $presets[$live['preset']]['icon'] ?? 'fa-star'; ?>" style="color:<?php echo $c3; ?>"></i></div>
            <div class="lt-today__copy">
              <div class="lt-eyebrow">Showing on the login page today</div>
              <h3><?php echo $e($live['name']); ?></h3>
              <p><?php echo $e($presets[$live['preset']]['name'] ?? $live['preset']); ?> · <?php echo $e($range($live)); ?><?php echo !empty($live['repeats_yearly']) ? ' · every year' : ''; ?></p>
            </div>
            <a class="wd-btn wd-btn--ghost" href="login.php?lt_preview=<?php echo (int) $live['id']; ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> Preview</a>
          <?php else: ?>
            <div class="lt-thumb" style="background:linear-gradient(135deg, var(--navy-2), var(--navy))"><i class="fa-solid fa-circle-half-stroke"></i></div>
            <div class="lt-today__copy">
              <div class="lt-eyebrow">Showing on the login page today</div>
              <h3>Standard look</h3>
              <p>No entry is scheduled for today, so everyone sees the normal sign-in page.</p>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="wd-card" style="margin-top:16px">
        <div class="wd-card__head">
          <h3>Schedule</h3>
          <span class="wd-muted" style="font-size:12.5px"><?php echo count(array_filter($rows, function ($r) { return !empty($r['is_active']); })); ?> on · <?php echo count($rows); ?> total</span>
        </div>
        <div style="overflow-x:auto">
          <table class="wd-table">
            <thead><tr><th>Entry</th><th>Dates</th><th class="lt-hide-sm">Includes</th><th>Status</th><th>On</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="6" class="lt-empty">No entries yet. Click <b>New entry</b> to schedule a seasonal look.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $t): [$c1, $c2, $c3] = $swatch($t['preset']); $plain = $t['preset'] === 'plain'; ?>
              <tr class="<?php echo empty($t['is_active']) ? 'is-off' : ''; ?>">
                <td>
                  <div class="lt-entry">
                    <span class="lt-sw" style="background:linear-gradient(135deg, <?php echo $c1; ?>, <?php echo $c2; ?>)"><i class="fa-solid <?php echo $presets[$t['preset']]['icon'] ?? 'fa-star'; ?>" style="color:<?php echo $c3; ?>"></i></span>
                    <div><b><?php echo $e($t['name']); ?></b><small><?php echo $e($presets[$t['preset']]['name'] ?? $t['preset']); ?></small></div>
                  </div>
                </td>
                <td class="lt-dates"><?php echo $e($range($t)); ?><small><?php echo !empty($t['repeats_yearly']) ? 'Every year' : 'One time'; ?></small></td>
                <td class="lt-extras lt-hide-sm">
                  <i class="fa-solid fa-snowflake<?php echo (!empty($t['show_effects']) && !$plain) ? ' on' : ''; ?>" title="<?php echo (!empty($t['show_effects']) && !$plain) ? 'Falling effect on' : 'No falling effect'; ?>"></i>
                  <i class="fa-regular fa-image<?php echo ($t['banner_path'] && !$plain) ? ' on' : ''; ?>" title="<?php echo $t['banner_path'] ? 'Has a banner image' : 'No banner image'; ?>"></i>
                  <i class="fa-solid fa-bullhorn<?php echo $t['announcement'] ? ' on' : ''; ?>" title="<?php echo $t['announcement'] ? 'Has an announcement' : 'No announcement'; ?>"></i>
                </td>
                <td><span class="wd-pill wd-pill--<?php echo $pillClass[$t['status']['key']]; ?>"><?php echo $e($t['status']['label']); ?></span></td>
                <td>
                  <label class="lt-switch" title="<?php echo !empty($t['is_active']) ? 'Turn off' : 'Turn on'; ?>">
                    <input type="checkbox" class="js-toggle" data-id="<?php echo (int) $t['id']; ?>" <?php echo !empty($t['is_active']) ? 'checked' : ''; ?> aria-label="Turn <?php echo $e($t['name']); ?> on or off">
                    <span></span>
                  </label>
                </td>
                <td>
                  <div class="lt-actions">
                    <a class="wd-btn wd-btn--ghost wd-btn--sm" href="login.php?lt_preview=<?php echo (int) $t['id']; ?>" target="_blank" rel="noopener" title="Preview the login page"><i class="fa-solid fa-eye"></i></a>
                    <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm js-edit" title="Edit"
                      data-theme="<?php echo $e(json_encode([
                          'id' => (int) $t['id'], 'name' => $t['name'], 'preset' => $t['preset'],
                          'season_label' => $t['season_label'], 'headline' => $t['headline'],
                          'headline_accent' => $t['headline_accent'], 'message' => $t['message'],
                          'announcement' => $t['announcement'], 'show_effects' => (bool) $t['show_effects'],
                          'repeats_yearly' => (bool) $t['repeats_yearly'], 'is_active' => (bool) $t['is_active'],
                          'starts_on' => lt_date($t['starts_on'])->format('Y-m-d'), 'ends_on' => lt_date($t['ends_on'])->format('Y-m-d'),
                          'banner_url' => lt_banner_url($t['banner_path']),
                      ])); ?>"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm js-delete" title="Delete" data-id="<?php echo (int) $t['id']; ?>" data-name="<?php echo $e($t['name']); ?>"><i class="fa-solid fa-trash"></i></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="lt-help"><i class="fa-solid fa-circle-info"></i>
          If two entries fall on the same day, a one-time entry beats an every-year one, then the shorter date range wins.
          People only see an entry that is switched <b>On</b> and inside its dates.
        </div>
      </section>

      <!-- Create / edit (Bootstrap 3 modal, like the rest of the app) -->
      <div class="modal fade" id="mdlTheme" tabindex="-1" role="dialog" aria-labelledby="mdlThemeTitle">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <form id="frmTheme" enctype="multipart/form-data" novalidate>
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="token" value="<?php echo $e(lt_csrf_token()); ?>">
              <input type="hidden" name="id" id="lt_id">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="mdlThemeTitle">New entry</h4>
              </div>
              <div class="modal-body">
                <div class="row">
                  <div class="col-md-7">
                    <div class="lt-sec">
                      <div class="lt-sec__title">Season</div>
                      <div class="lt-presets">
                        <?php foreach ($presets as $key => $p): [$c1, $c2, $c3] = $swatch($key); ?>
                          <label class="lt-preset" data-preset="<?php echo $key; ?>">
                            <input type="radio" name="preset" value="<?php echo $key; ?>">
                            <span class="lt-sw" style="background:linear-gradient(135deg, <?php echo $c1; ?>, <?php echo $c2; ?>)"><i class="fa-solid <?php echo $p['icon']; ?>" style="color:<?php echo $c3; ?>"></i></span>
                            <?php echo $e($p['name']); ?>
                          </label>
                        <?php endforeach; ?>
                      </div>
                      <span class="lt-err" data-err="preset"></span>
                    </div>

                    <div class="lt-sec">
                      <div class="lt-sec__title">When</div>
                      <div class="wd-field">
                        <label for="lt_name">Entry name</label>
                        <input type="text" class="wd-input" name="name" id="lt_name" maxlength="120" placeholder="e.g. Christmas">
                        <span class="lt-err" data-err="name"></span>
                      </div>
                      <div class="lt-row2">
                        <div class="wd-field" style="margin:0">
                          <label for="lt_start">Starts</label>
                          <input type="date" class="wd-input" name="starts_on" id="lt_start">
                          <span class="lt-err" data-err="starts_on"></span>
                        </div>
                        <div class="wd-field" style="margin:0">
                          <label for="lt_end">Ends (last day shown)</label>
                          <input type="date" class="wd-input" name="ends_on" id="lt_end">
                          <span class="lt-err" data-err="ends_on"></span>
                        </div>
                      </div>
                      <label class="lt-check" for="lt_yearly"><input type="checkbox" name="repeats_yearly" id="lt_yearly" value="1"> Repeat every year on the same dates <span class="lt-hint" style="margin:0">(the year is ignored)</span></label>
                      <label class="lt-check" for="lt_active"><input type="checkbox" name="is_active" id="lt_active" value="1"> Switched on</label>
                    </div>

                    <div class="lt-sec js-themed">
                      <div class="lt-sec__title">Greeting <span>— leave blank to use the suggested text</span></div>
                      <div class="wd-field">
                        <label for="lt_pill">Small label above the greeting</label>
                        <input type="text" class="wd-input js-live" name="season_label" id="lt_pill" maxlength="60">
                      </div>
                      <div class="lt-row2">
                        <div class="wd-field">
                          <label for="lt_headline">Headline</label>
                          <input type="text" class="wd-input js-live" name="headline" id="lt_headline" maxlength="80">
                        </div>
                        <div class="wd-field">
                          <label for="lt_accent">Highlighted words</label>
                          <input type="text" class="wd-input js-live" name="headline_accent" id="lt_accent" maxlength="60">
                          <div class="lt-hint">Shown after the headline, in the season's colour.</div>
                        </div>
                      </div>
                      <div class="wd-field" style="margin:0">
                        <label for="lt_message">Message</label>
                        <textarea class="wd-input js-live" name="message" id="lt_message" rows="2" maxlength="300"></textarea>
                      </div>
                    </div>

                    <div class="lt-sec">
                      <div class="lt-sec__title">Announcement bar <span>— optional, shown above the sign-in card</span></div>
                      <textarea class="wd-input js-live" name="announcement" id="lt_ann" rows="2" maxlength="300" placeholder="e.g. Office is closed Dec 24–25. Payroll releases on Dec 23."></textarea>
                      <span class="lt-err" data-err="announcement"></span>
                    </div>

                    <div class="lt-sec js-themed">
                      <div class="lt-sec__title">Extras</div>
                      <label class="lt-check" for="lt_fx" style="margin:0 0 12px"><input type="checkbox" name="show_effects" id="lt_fx" value="1"> <span id="lt_fx_label">Show falling effect</span></label>
                      <div class="wd-field" style="margin:0">
                        <label for="lt_banner">Banner image (optional)</label>
                        <input type="file" class="wd-input" name="banner" id="lt_banner" accept="image/jpeg,image/png,image/webp">
                        <div class="lt-hint">Shown across the top of the sign-in card. JPG, PNG or WEBP, up to 4 MB. A wide image around 800×320 px works best.</div>
                        <span class="lt-err" data-err="banner"></span>
                        <div class="lt-banner-cur is-hidden" id="lt_banner_cur">
                          <img src="" alt="Current banner" id="lt_banner_img">
                          <label class="lt-check" for="lt_remove_banner" style="margin:0"><input type="checkbox" name="remove_banner" id="lt_remove_banner" value="1"> Remove current banner</label>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-5">
                    <div class="lt-sec">
                      <div class="lt-sec__title">Quick look</div>
                      <div class="lt-mini" id="mini">
                        <span class="lt-mini__pill" id="miniPill"></span>
                        <div class="lt-mini__logo"><img src="assets/images/logos/wedo-logo.png" alt=""><span class="lt-mini__badge" id="miniBadge"></span></div>
                        <div class="lt-mini__title" id="miniTitle"></div>
                        <div class="lt-mini__msg" id="miniMsg"></div>
                        <div class="lt-mini__ann" id="miniAnn"></div>
                      </div>
                      <div class="lt-hint" style="text-align:center;margin-top:8px">Save, then use <i class="fa-solid fa-eye"></i> Preview to see the full login page.</div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Cancel</button>
                <button type="submit" class="wd-btn wd-btn--primary" id="btnSave"><i class="fa-solid fa-floppy-disk"></i> Save entry</button>
              </div>
            </form>
          </div>
        </div>
      </div>

    <?php include 'includes/wd-footer.php'; ?>

    <script>
      (function () {
        var PRESETS = <?php echo json_encode($presets, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
        var TOKEN   = <?php echo json_encode(lt_csrf_token()); ?>;
        var ENDPOINT = 'query/logintheme-action.php';
        var EFFECT_LABEL = { snow: 'Show falling snow and twinkling lights', confetti: 'Show falling confetti', hearts: 'Show floating hearts', glow: 'Show floating glowing lights' };
        var form = document.getElementById('frmTheme');
        function $id(id) { return document.getElementById(id); }
        var bannerObjectUrl = null, existingBannerUrl = null;

        function presetKey() { var r = form.querySelector('input[name="preset"]:checked'); return r ? r.value : null; }
        function clearErrors() { form.querySelectorAll('[data-err]').forEach(function (el) { el.textContent = ''; }); }

        function selectPreset(key) {
          form.querySelectorAll('.lt-preset').forEach(function (c) {
            var on = c.getAttribute('data-preset') === key;
            c.classList.toggle('selected', on);
            c.querySelector('input').checked = on;
          });
          var p = PRESETS[key] || {}, d = p.defaults || {};
          $id('lt_pill').placeholder = d.season_label || '';
          $id('lt_headline').placeholder = d.headline || '';
          $id('lt_accent').placeholder = d.headline_accent || '';
          $id('lt_message').placeholder = d.message || '';
          $id('lt_fx_label').textContent = EFFECT_LABEL[(p.effect || {}).kind] || 'Show falling effect';
          form.querySelectorAll('.js-themed').forEach(function (s) { s.classList.toggle('is-hidden', !!p.plain); });
          if (!$id('lt_name').value.trim() || $id('lt_name').getAttribute('data-auto') === '1') {
            $id('lt_name').value = p.name || '';
            $id('lt_name').setAttribute('data-auto', '1');
          }
          renderMini();
        }

        function renderMini() {
          var p = PRESETS[presetKey()] || PRESETS.plain, c = p.colors || {}, d = p.defaults || {};
          var val = function (id, def) { return $id(id).value.trim() || def || ''; };
          var mini = $id('mini');
          var bannerUrl = $id('lt_remove_banner').checked ? null : (bannerObjectUrl || existingBannerUrl);
          var bg = 'radial-gradient(120% 120% at 0% 0%, ' + (c.bg1 || '#0f2147') + ' 0%, ' + (c.bg2 || '#09152e') + ' 45%, ' + (c.bg3 || '#050b1a') + ' 100%)';
          mini.style.backgroundImage = (!p.plain && bannerUrl) ? 'url("' + bannerUrl + '")' : bg;
          mini.classList.toggle('with-banner', !p.plain && !!bannerUrl);
          var accent = c.accent || '#ffd76a';
          var pill = p.plain ? '' : val('lt_pill', d.season_label);
          $id('miniPill').textContent = pill; $id('miniPill').style.color = accent; $id('miniPill').classList.toggle('is-hidden', !pill);
          $id('miniBadge').classList.toggle('is-hidden', !p.badge);
          $id('miniBadge').style.background = accent; $id('miniBadge').style.color = c.primaryDeep || '#09152e';
          $id('miniBadge').innerHTML = p.badge ? '<i class="fa-solid ' + p.badge + '"></i>' : '';
          var t = $id('miniTitle'); t.textContent = '';
          if (p.plain) { t.textContent = 'Sign in'; }
          else {
            t.appendChild(document.createTextNode(val('lt_headline', d.headline) + ' '));
            var hl = document.createElement('span'); hl.style.color = accent; hl.textContent = val('lt_accent', d.headline_accent); t.appendChild(hl);
          }
          $id('miniMsg').textContent = p.plain ? 'Your standard sign-in page.' : val('lt_message', d.message);
          var ann = $id('lt_ann').value.trim();
          $id('miniAnn').textContent = ann; $id('miniAnn').classList.toggle('is-hidden', !ann);
        }

        function openModal(data) {
          form.reset(); clearErrors();
          if (bannerObjectUrl) { URL.revokeObjectURL(bannerObjectUrl); bannerObjectUrl = null; }
          existingBannerUrl = data ? data.banner_url : null;
          $id('mdlThemeTitle').textContent = data ? 'Edit entry' : 'New entry';
          $id('lt_id').value = data ? data.id : '';
          $id('lt_name').value = data ? data.name : '';
          $id('lt_name').setAttribute('data-auto', data ? '0' : '1');
          $id('lt_pill').value = (data && data.season_label) || '';
          $id('lt_headline').value = (data && data.headline) || '';
          $id('lt_accent').value = (data && data.headline_accent) || '';
          $id('lt_message').value = (data && data.message) || '';
          $id('lt_ann').value = (data && data.announcement) || '';
          $id('lt_start').value = (data && data.starts_on) || '';
          $id('lt_end').value = (data && data.ends_on) || '';
          $id('lt_yearly').checked = data ? !!data.repeats_yearly : true;
          $id('lt_active').checked = data ? !!data.is_active : true;
          $id('lt_fx').checked = data ? !!data.show_effects : true;
          $id('lt_banner_cur').classList.toggle('is-hidden', !existingBannerUrl);
          $id('lt_banner_img').src = existingBannerUrl || '';
          selectPreset(data ? data.preset : 'christmas');
          $('#mdlTheme').modal('show');
        }

        function post(fd) {
          fd.append('token', TOKEN);
          return fetch(ENDPOINT, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { status: 'error', msg: 'Unexpected response from the server.' }; }); });
        }
        function notify(msg, ok) { alert(msg); if (ok) { location.reload(); } }

        $id('btnNew').addEventListener('click', function () { openModal(null); });
        document.querySelectorAll('.js-edit').forEach(function (b) {
          b.addEventListener('click', function () { openModal(JSON.parse(b.getAttribute('data-theme'))); });
        });
        form.querySelectorAll('.lt-preset input').forEach(function (r) { r.addEventListener('change', function () { selectPreset(r.value); }); });
        $id('lt_name').addEventListener('input', function () { $id('lt_name').setAttribute('data-auto', '0'); });
        form.querySelectorAll('.js-live').forEach(function (el) { el.addEventListener('input', renderMini); });
        $id('lt_remove_banner').addEventListener('change', renderMini);
        $id('lt_banner').addEventListener('change', function () {
          if (bannerObjectUrl) { URL.revokeObjectURL(bannerObjectUrl); }
          bannerObjectUrl = this.files[0] ? URL.createObjectURL(this.files[0]) : null;
          renderMini();
        });

        form.addEventListener('submit', function (e) {
          e.preventDefault(); clearErrors();
          var fd = new FormData(form);
          fd.delete('token');
          // Unchecked boxes are absent from FormData; send an explicit 0.
          ['show_effects', 'repeats_yearly', 'is_active', 'remove_banner'].forEach(function (k) { if (!fd.has(k)) { fd.append(k, '0'); } });
          var btn = $id('btnSave'); btn.disabled = true;
          post(fd).then(function (d) {
            if (d.status === 'ok') { $('#mdlTheme').modal('hide'); notify(d.msg, true); }
            else if (d.status === 'invalid') {
              Object.keys(d.error || {}).forEach(function (k) { var el = form.querySelector('[data-err="' + k + '"]'); if (el) { el.textContent = d.error[k]; } });
            }
            else { notify(d.msg || 'Could not save.'); }
          }).catch(function () { notify('The server could not be reached. Try again.'); })
            .finally(function () { btn.disabled = false; });
        });

        document.querySelectorAll('.js-toggle').forEach(function (chk) {
          chk.addEventListener('change', function () {
            var fd = new FormData(); fd.append('action', 'toggle'); fd.append('id', chk.getAttribute('data-id'));
            chk.disabled = true;
            post(fd).then(function (d) {
              if (d.status === 'ok') { location.reload(); }
              else { chk.checked = !chk.checked; chk.disabled = false; notify(d.msg || 'Could not update.'); }
            }).catch(function () { chk.checked = !chk.checked; chk.disabled = false; notify('The server could not be reached. Try again.'); });
          });
        });

        document.querySelectorAll('.js-delete').forEach(function (b) {
          b.addEventListener('click', function () {
            if (!confirm('Delete "' + b.getAttribute('data-name') + '"? Its banner image is deleted too. To keep it for next year, switch it off instead.')) { return; }
            var fd = new FormData(); fd.append('action', 'delete'); fd.append('id', b.getAttribute('data-id'));
            post(fd).then(function (d) { notify(d.msg || 'Done.', d.status === 'ok'); })
              .catch(function () { notify('The server could not be reached. Try again.'); });
          });
        });
      })();
    </script>
  </body>
</html>
