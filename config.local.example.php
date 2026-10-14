<?php
/* -----------------------------------------------------------------------------
 * config.local.example.php  —  TEMPLATE (safe to commit; contains no secrets).
 *
 * To set this environment's DB credentials:
 *   copy this file to  config.local.php   (which is gitignored, never committed)
 *   and fill in the values below. w_conn.php loads it automatically and it wins
 *   over the built-in fallback.
 *
 * Alternatively, set environment variables (these win over config.local.php):
 *   DB_HOST  DB_USER  DB_PASS  DB_NAME
 *
 * Example production config.local.php:
 *   <?php return ['user' => 'toor', 'pass' => '<the real password>'];
 *
 * Mobile push notifications (optional, see includes/push-lib.php): 'fcm_key' is
 * the path to the Firebase service-account key JSON. Keep that file OUTSIDE the
 * web root (e.g. /home/<account>/wedo-secrets/firebase-key.json). Env var
 * FCM_KEY_FILE wins over this. Leave it out and push is simply switched off.
 *
 * Cloudflare Turnstile on the public Apply page (optional, see
 * includes/applicants-lib.php apl_turnstile): BOTH keys from Cloudflare >
 * Turnstile > the widget. The widget's hostname list must include this site's
 * domain. Env vars TURNSTILE_SITE / TURNSTILE_SECRET win over these. Leave them
 * out and apply.php relies on its other spam guards.
 * -------------------------------------------------------------------------- */
return [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'wedodb2020',
    // 'fcm_key' => 'C:/xampp/wedo-secrets/firebase-key.json',
    // 'turnstile_site'   => '0x4AAAA...',   // public site key
    // 'turnstile_secret' => '0x4AAAA...',   // secret key — never commit it
];
