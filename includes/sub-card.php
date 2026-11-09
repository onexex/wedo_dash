<?php
/* ==========================================================================
   sub-card.php  —  "Renewals due" card: what this person is reminded about
   today (Subscriptions). Shown on dashboard.php and notifications.php, after
   wd-header.php has filled $wdSubMine. Nothing when there is nothing due.
   ========================================================================== */
if (empty($wdSubMine)) { return; }
$wdScE = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$wdScExpired = count(array_filter($wdSubMine, function ($x) { return $x['days'] < 0; }));
?>
<section class="wd-card wd-subcard">
  <div class="wd-card__head">
    <h3><i class="fa-solid fa-bell" style="color:var(--warn-text)"></i> Renewals due</h3>
    <a class="wd-btn wd-btn--ghost wd-btn--sm" href="subscriptions">Open Subscriptions</a>
  </div>
  <p style="margin:0;padding:0 20px 8px;color:var(--text-2);font-size:13px">
    <?php echo count($wdSubMine) === 1 ? 'One renewal needs' : count($wdSubMine) . ' renewals need'; ?> attention<?php echo $wdScExpired ? ', ' . $wdScExpired . ' already expired' : ''; ?>. You'll be reminded every day until each one is marked renewed.
  </p>
  <ul style="list-style:none;margin:0;padding:0">
<?php foreach (array_slice($wdSubMine, 0, 6) as $wdScE_) {
        $d = $wdScE_['days'];
        $tone = $d < 0 ? 'danger' : ($d <= SUB_URGENT_DAYS ? 'warn' : 'info');
        $txt = $d < 0 ? ($d === -1 ? 'Expired yesterday' : 'Expired ' . (-$d) . ' days ago') : ($d === 0 ? 'Expires today' : ($d === 1 ? '1 day left' : $d . ' days left')); ?>
    <li style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:10px 20px;border-top:1px solid var(--border)">
      <a href="subscriptions?id=<?php echo (int) $wdScE_['itemId']; ?>" style="color:var(--text);min-width:0">
        <b><?php echo $wdScE($wdScE_['label']); ?></b><br>
        <span style="color:var(--text-3);font-size:12px"><?php echo $wdScE($wdScE_['categoryLabel'] . ' · ' . date('M j, Y', strtotime($wdScE_['expiresOn']))); ?></span>
      </a>
      <span class="wd-pill wd-pill--<?php echo $tone; ?>" style="white-space:nowrap"><?php echo $wdScE($txt); ?></span>
    </li>
<?php } ?>
<?php if (count($wdSubMine) > 6): ?>
    <li style="padding:10px 20px;border-top:1px solid var(--border);font-size:13px"><a href="subscriptions"><?php echo count($wdSubMine) - 6; ?> more</a></li>
<?php endif; ?>
  </ul>
</section>
