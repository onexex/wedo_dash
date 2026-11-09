<?php
/* ==========================================================================
   sub-remind.php  —  the once-a-day desktop pop-up for renewal reminders
   (Subscriptions). Included by wd-footer.php; uses $wdSubMine from
   wd-header.php (what this person is reminded about today).

   The first page of the day shows it: as an OS notification when the browser
   allows them, otherwise as a card in the page offering to turn them on.
   Shown once per browser per day (localStorage). Not on the Subscriptions
   page itself, which already says all of it. The app gets phone push instead.
   ========================================================================== */
if (empty($wdSubMine) || ($wd_active ?? '') === 'subscriptions' || !function_exists('sub_digest_text')) { return; }
if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'WeDoApp/') !== false) { return; }
[$wdSrTitle, $wdSrBody] = sub_digest_text($wdSubMine);
$wdSr = ['day' => date('Y-m-d'), 'title' => $wdSrTitle, 'body' => $wdSrBody, 'count' => count($wdSubMine),
         'url' => 'subscriptions' . (count($wdSubMine) === 1 ? '?id=' . (int) $wdSubMine[0]['itemId'] : ''),
         'urgent' => $wdSubMine[0]['days'] <= SUB_URGENT_DAYS];
?>
<div class="wd-subpop" id="wdSubPop" role="status" aria-live="polite" hidden>
  <div class="wd-subpop__icon"><i class="fa-solid fa-bell"></i></div>
  <div class="wd-subpop__text"><b id="wdSubPopTitle"></b><span id="wdSubPopBody"></span>
    <div class="wd-subpop__btns">
      <a class="wd-btn wd-btn--primary wd-btn--sm" id="wdSubPopOpen" href="#">Open</a>
      <button type="button" class="wd-btn wd-btn--ghost wd-btn--sm" id="wdSubPopDesk" hidden>Turn on desktop pop-ups</button>
    </div>
  </div>
  <button type="button" class="wd-subpop__x" id="wdSubPopX" aria-label="Dismiss">&times;</button>
</div>
<style>
.wd-subpop{position:fixed;top:76px;right:20px;z-index:1040;width:min(380px,calc(100vw - 32px));display:flex;gap:12px;align-items:flex-start;
  background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--warn-text);border-radius:var(--radius-lg);
  box-shadow:var(--shadow);padding:14px 14px 14px 16px;font-size:13px;color:var(--text)}
.wd-subpop[hidden]{display:none}
.wd-subpop.is-urgent{border-left-color:var(--danger-text)}
.wd-subpop__icon{color:var(--warn-text);font-size:18px;margin-top:2px}
.wd-subpop.is-urgent .wd-subpop__icon{color:var(--danger-text)}
.wd-subpop__text{flex:1;min-width:0;display:flex;flex-direction:column;gap:3px}
.wd-subpop__text span{color:var(--text-2)}
.wd-subpop__btns{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.wd-subpop__x{border:0;background:none;color:var(--text-3);font-size:18px;line-height:1;cursor:pointer;padding:0 2px}
@media (max-width:640px){.wd-subpop{top:auto;bottom:16px;right:16px}}
</style>
<script>
(function () {
  var R = <?php echo json_encode($wdSr, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var KEY = 'wd-sub-pop';
  try { if (localStorage.getItem(KEY) === R.day) { return; } } catch (e) { /* private window: show every page */ }
  function done() { try { localStorage.setItem(KEY, R.day); } catch (e) {} }
  var canOs = 'Notification' in window && window.isSecureContext;
  if (canOs && Notification.permission === 'granted') {
    try {
      var n = new Notification(R.title, { body: R.body, tag: 'wedo-sub-' + R.day, icon: 'assets/images/logos/logo-2.png' });
      n.onclick = function () { window.focus(); location.href = R.url; n.close(); };
      done();
      return;
    } catch (e) { /* some browsers only allow it from a service worker: fall back to the card */ }
  }
  var box = document.getElementById('wdSubPop');
  document.getElementById('wdSubPopTitle').textContent = R.title;
  document.getElementById('wdSubPopBody').textContent = R.body;
  var open = document.getElementById('wdSubPopOpen');
  open.href = R.url;
  open.addEventListener('click', done);
  if (R.urgent) { box.classList.add('is-urgent'); }
  var desk = document.getElementById('wdSubPopDesk');
  if (canOs && Notification.permission === 'default') {
    desk.hidden = false;
    desk.addEventListener('click', function () {
      Notification.requestPermission().then(function (p) {
        desk.hidden = true;
        if (p === 'granted') { try { new Notification('Desktop pop-ups are on', { body: 'Renewal reminders will pop up here once a day.' }); } catch (e) {} }
      });
    });
  }
  document.getElementById('wdSubPopX').addEventListener('click', function () { box.hidden = true; done(); });
  box.hidden = false;
})();
</script>
