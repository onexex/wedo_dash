# VPS testing server (CloudPanel)

A test copy of the dashboard on our own VPS, **https://wedo.kmds.systems**, separate from staging (.xyz on cPanel) and
production (.ph). Its own branch, server and database, so testing can never touch the live system.

    push to `vps`  ->  .github/workflows/deploy-vps.yml  ->  lint + tests  ->  deploy to the VPS

The workflow only copies the code. The steps below are done once, by hand.

## 1. Create the site (CloudPanel)

- **Sites → Add Site → Create a PHP Site**, `wedo.kmds.systems` (DNS A record → the VPS, 187.127.104.224). Done.
- **PHP version 8.2**, the same as CI. A newer PHP can break code that 8.2 accepts.
- **SSL/TLS → Actions → New Let's Encrypt Certificate.** The test app only opens a site with a valid certificate. Done (renews automatically).
- Note the **site user** and its **SSH password** (Site → Settings), and the site root
  (`/home/<site-user>/htdocs/wedo.kmds.systems`).

## 2. Database

- **Databases → Add Database** (e.g. `devdashboard`) with its own user.
- Import a dump of `devdashboard` (phpMyAdmin from CloudPanel, or `mysql` over SSH).
- Then run the SQL files in `sql/` that the dump doesn't include yet (push_devices, app_remember,
  msg_cleared, notif_seen...), the same ones run by hand on production.

## 3. `config.local.php` in the site root

The deploy refuses to run until this file exists. It is never in git and the deploy never overwrites it:

```php
<?php
return [
    'host' => '127.0.0.1',
    'user' => 'devdashboard_user',
    'pass' => '...',
    'name' => 'devdashboard',
    // 'fcm_key' => '/home/<site-user>/wedo-secrets/firebase-key.json',   // only if push is tested here
];
```

## 4. nginx rules (Site → Vhost)

nginx ignores `.htaccess`, so the rules the site relies on go in the vhost. Inside the `server { ... }`
block that serves HTTPS, **replace** CloudPanel's default `location / { ... }` with:

```nginx
  # --- WeDo dashboard (what .htaccess does on cPanel) ---
  error_page 404 /404.php;

  # never served: config, backups, dumps, logs, git, tests, .user.ini
  location ~* (^|/)(config\.local\.php|\.user\.ini|\.git|tests/) { deny all; return 404; }
  location ~* \.(bak|secbak|orig|save|swp|swo|sql|log)$ { deny all; return 404; }

  # Android app download page
  location = /app/WeDo.apk {
    types { application/vnd.android.package-archive apk; }
    add_header Content-Disposition 'attachment; filename="WeDo.apk"';
    add_header Cache-Control "no-cache";
  }
  location = /app/version.json { add_header Cache-Control "no-store, max-age=0"; }

  # links without .php (alas, payslip, Familydetails...) -> alas.php
  location / {
    try_files $uri $uri/ $uri.php$is_args$args $uri.html =404;
  }
```

CloudPanel's own `location ~ \.php$ { ... }` block (fastcgi to PHP-FPM) stays as it is. With
`$uri.php` in `try_files`, nginx hands the file to that block.

**Difference from cPanel:** Linux paths are case-sensitive and nginx has no `mod_speling`. A link
whose case doesn't match the file (e.g. `familydetails` for `Familydetails.php`) gives a 404 here,
where cPanel corrected it. That's a real bug worth fixing in the link, not on the server.

## 5. GitHub secrets (repo → Settings → Secrets and variables → Actions)

| Secret | Value |
|---|---|
| `VPS_HOST` | VPS IP or hostname |
| `VPS_PORT` | SSH port (leave unset for 22) |
| `VPS_USERNAME` | the CloudPanel site user |
| `VPS_PASSWORD` | that user's SSH password |
| `VPS_PATH` | `/home/<site-user>/htdocs/wedo.kmds.systems` |
| `VPS_URL` | `https://wedo.kmds.systems` |

Until these are set, pushes to `vps` still run lint + tests and skip the deploy with a warning.

## 6. Optional: opcache reload after each deploy

As root, `visudo -f /etc/sudoers.d/wedo-vps` with:

    <site-user> ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload php8.2-fpm

Without it the deploy skips the reload and PHP picks up changed files on its own (opcache timestamp check).

## Day to day

- Test a change: merge it into `vps` (`git checkout vps && git merge staging && git push`).
- The **WeDo Test** app (`gradlew assembleVps` in wedo-app) opens this site, on the VPS database.
- Happy with it: staging → production as usual. `vps` never deploys anywhere else.
