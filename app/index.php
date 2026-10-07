<?php
/* WeDo Android app — public download page (linked from the login page).
   To publish a new version: replace app/WeDo.apk and update app/version.json
   (the app checks version.json once a day and offers the update). */
$info = json_decode(@file_get_contents(__DIR__ . '/version.json'), true) ?: [];
$h    = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$ver  = $info['version'] ?? '';
$size = $info['size'] ?? '';
$date = !empty($info['released']) ? date('F j, Y', strtotime($info['released'])) : '';
$apk  = 'WeDo.apk' . ($ver !== '' ? '?v=' . rawurlencode($ver) : '');
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
$inApp  = strpos($ua, 'WeDoApp/') !== false;
$iPhone = (bool) preg_match('/iPhone|iPad|iPod/i', $ua);
$css  = '../assets/css/wedo-theme.css?v=' . @filemtime(__DIR__ . '/../assets/css/wedo-theme.css');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WeDo app for Android</title>
    <link rel="icon" type="image/png" href="icon.png">
    <link rel="stylesheet" href="<?php echo $h($css); ?>">
    <style>
        body { margin: 0; background: var(--surface-2, #f5f6f8); font-family: var(--font-body, system-ui, sans-serif); color: var(--text, #16202e); }
        .ap { max-width: 560px; margin: 0 auto; padding: 28px 18px 48px; }
        .ap-logo { display: block; height: 44px; width: auto; margin: 0 auto 22px; }
        .ap-card { background: var(--surface, #fff); border: 1px solid var(--border, #e6e8ec); border-radius: 20px; padding: 24px 22px; box-shadow: var(--shadow-sm, 0 1px 3px rgba(16,24,40,.06)); margin-bottom: 16px; }
        .ap-hero { display: flex; align-items: center; gap: 16px; }
        .ap-hero img { width: 72px; height: 72px; border-radius: 18px; box-shadow: 0 4px 14px rgba(16,24,40,.15); }
        .ap-hero h1 { font-family: var(--font-head, inherit); font-size: 22px; margin: 0 0 4px; }
        .ap-hero p { margin: 0; color: var(--text-2, #6b7480); font-size: 14px; }
        .ap-dl { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; min-height: 54px; margin-top: 20px; border-radius: 14px;
                 background: var(--brand, #f93627); color: #fff !important; font-weight: 700; font-size: 17px; text-decoration: none; box-shadow: 0 6px 18px rgba(249,54,39,.28); }
        .ap-dl:hover { filter: brightness(.95); }
        .ap-meta { margin: 12px 0 0; text-align: center; color: var(--text-3, #98a2b3); font-size: 12.5px; }
        .ap-only { margin: 6px 0 0; text-align: center; color: var(--text-2, #6b7480); font-size: 13px; font-weight: 600; }
        .ap-card h2 { font-family: var(--font-head, inherit); font-size: 16px; margin: 0 0 12px; }
        .ap-steps { margin: 0; padding: 0; list-style: none; counter-reset: s; }
        .ap-steps li { counter-increment: s; position: relative; padding: 0 0 14px 40px; font-size: 14.5px; line-height: 1.5; color: var(--text-2, #475467); }
        .ap-steps li::before { content: counter(s); position: absolute; left: 0; top: -1px; width: 26px; height: 26px; border-radius: 50%;
                               background: var(--brand-tint, #fdecea); color: var(--brand, #f93627); font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center; }
        .ap-steps b { color: var(--text, #16202e); }
        .ap-note { font-size: 13.5px; color: var(--text-2, #6b7480); line-height: 1.5; margin: 0 0 8px; }
        .ap-note:last-child { margin: 0; }
        .ap-alert { border-radius: 14px; padding: 14px 16px; font-size: 14px; line-height: 1.5; margin-bottom: 16px; background: #fff4e0; color: #7a4500; }
        .ap-back { display: block; text-align: center; margin-top: 18px; color: var(--brand, #f93627); font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
<main class="ap">
    <img src="../assets/images/logos/wedo-logo.png" class="ap-logo" alt="WeDo BPO Inc.">

    <?php if ($inApp) { ?>
        <div class="ap-alert">You're already using the WeDo app. It tells you when a new version is ready
            (or open <b>Menu</b> and tap <b>Check for updates</b> at the bottom).</div>
    <?php } elseif ($iPhone) { ?>
        <div class="ap-alert">The WeDo app is for <b>Android phones only</b>. There is no iPhone (iOS) version.
            On an iPhone, keep using <b>dashboard.wedoinc.ph</b> in Safari. Tip: tap Share, then <b>Add to Home Screen</b>.</div>
    <?php } ?>

    <section class="ap-card">
        <div class="ap-hero">
            <img src="icon.png" alt="">
            <div>
                <h1>WeDo for Android</h1>
                <p>Time in and out, file leave, overtime and OB trips, payslips, messages and alerts, in one app.</p>
            </div>
        </div>
        <a class="ap-dl" href="<?php echo $h($apk); ?>" download="WeDo.apk">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
            Download the app
        </a>
        <p class="ap-meta"><?php echo $h(implode(' · ', array_filter([
            $ver !== '' ? 'Version ' . $ver : '', $size, $date ? 'Updated ' . $date : '', 'Android ' . ($info['minAndroid'] ?? '6.0') . ' or newer']))); ?></p>
        <p class="ap-only">Android only. Not available for iPhone or iPad (iOS).</p>
    </section>

    <section class="ap-card">
        <h2>How to install</h2>
        <ol class="ap-steps">
            <li>Tap <b>Download the app</b>. If Chrome warns that the file might be harmful, tap <b>Download anyway</b>.</li>
            <li>When it finishes, tap <b>Open</b> (or open <b>WeDo.apk</b> from your Downloads).</li>
            <li>If your phone asks, allow your browser to install apps: tap <b>Settings</b>, turn on <b>Allow from this source</b>, then go back.</li>
            <li>Tap <b>Install</b>. If Google Play Protect says it doesn't recognise the app, tap <b>More details</b>, then <b>Install anyway</b>. The app isn't on the Play Store, so this is expected.</li>
            <li>Open <b>WeDo</b> and sign in with your dashboard username and password. You stay signed in for 30 days.</li>
        </ol>
    </section>

    <section class="ap-card">
        <h2>Good to know</h2>
        <p class="ap-note"><b>Updates:</b> the app shows an <b>Update</b> button when a new version is ready. You don't need to come back to this page.</p>
        <p class="ap-note"><b>Notifications:</b> allow notifications when the app asks, to get alerts for approvals and messages.</p>
        <p class="ap-note"><b>Trouble installing?</b> Contact the IT department.</p>
    </section>

    <a class="ap-back" href="../login">Back to sign in</a>
</main>
</body>
</html>
