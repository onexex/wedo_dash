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
 *
 * Email to applicants through Brevo (optional, see includes/mail-lib.php):
 *   brevo_api_key   Brevo > SMTP & API > API keys > Generate (keep it secret)
 *   mail_from       a sender verified in Brevo > Senders, Domains & IPs. Verify
 *                   the whole domain (add Brevo's SPF/DKIM/DMARC DNS records) so
 *                   mail doesn't land in spam. Free plan: 300 emails a day.
 *   mail_from_name  shown as the sender (default "WeDo BPO Inc.")
 *   mail_reply_to   optional: where applicants' replies go (e.g. hr@...)
 *   site_url        optional: this site's address for links in emails,
 *                   e.g. https://wedoinc.ph/dashboard (default: the current host)
 * Env vars BREVO_API_KEY / MAIL_FROM / MAIL_FROM_NAME / MAIL_REPLY_TO / SITE_URL
 * win over these. Leave the key out and no email is sent (nothing else changes).
 *
 * Database Backup (Maintenance, see includes/db-backup-lib.php): the last
 * 'backup_keep' backups (default 10) stay in uploads/backups/ so they can be
 * downloaded again from the page. Env var WEDO_BACKUP_KEEP wins over this.
 * -------------------------------------------------------------------------- */
return [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'wedodb2020',
    // 'fcm_key' => 'C:/xampp/wedo-secrets/firebase-key.json',
    // 'turnstile_site'   => '0x4AAAA...',   // public site key
    // 'turnstile_secret' => '0x4AAAA...',   // secret key — never commit it
    // 'brevo_api_key'   => 'xkeysib-...',  // secret — never commit it
    // 'mail_from'      => 'careers@wedoinc.ph',
    // 'mail_from_name' => 'WeDo BPO Inc.',
    // 'mail_reply_to'  => 'hr@wedoinc.ph',
    // 'backup_keep' => 10,               // kept database backups (uploads/backups/)
];
