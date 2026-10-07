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
 * -------------------------------------------------------------------------- */
return [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'wedodb2020',
    // 'fcm_key' => 'C:/xampp/wedo-secrets/firebase-key.json',
];
