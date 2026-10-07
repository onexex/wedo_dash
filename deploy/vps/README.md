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

## 3. The database login (`w_conn.php` on the server)

Deploys **never** ship `w_conn.php` (nor `includes/w_conn.php` and `query/w_conn.php`), the same as
the cPanel deploys. The server's own copies hold this server's database login and survive every
deploy. The deploy stops if they're missing, so copy all three from the repo once.

Put the login in either of these (both stay untouched by deploys):

- in `w_conn.php` itself (its fallback `$__cfg` values), or
- in `config.local.php` next to it, which `w_conn.php` reads when present:

```php
<?php
return [
    'host' => '127.0.0.1',
    'user' => 'devdashboard_user',
    'pass' => '...',
    'name' => 'devdashboard',
];
```

Keep the `return [ ... ];` form and these exact keys, or the file is ignored. The deploy sets both
files to `600` (readable only by the site user, which PHP runs as).

## 4. nginx rules (Site → Vhost)

nginx ignores `.htaccess`, so the rules the site relies on go in the vhost. CloudPanel's PHP vhost has
two `server` blocks: the first (ports 80/443) only forwards requests; the second (`listen 8080`)
serves the files and runs PHP. Leave the first block as it is and **replace the whole 8080 block** with:

```nginx
server {
  listen 8080;
  listen [::]:8080;
  server_name wedo.kmds.systems;
  {{root}}

  include /etc/nginx/global_settings;

  index index.php index.html;
  error_page 404 /404.php;

  # --- WeDo: never served (these must stay ABOVE the \.php$ block) ---
  location ~* (^|/)(config\.local\.php|\.user\.ini|\.git|tests/|vendor/) { deny all; }
  location ~* \.(bak|secbak|orig|save|swp|swo|sql|log)$ { deny all; }

  # --- Android app download page (WeDo.apk, and WeDo-Test.apk on this server) ---
  location ~* ^/app/[^/]+\.apk$ {
    types { application/vnd.android.package-archive apk; }
    add_header Content-Disposition 'attachment';
    add_header Cache-Control "no-cache";
  }
  location = /app/version.json { add_header Cache-Control "no-store, max-age=0"; }

  # --- links without .php (alas, payslip...) run alas.php through PHP ---
  location / {
    try_files $uri $uri/ @extensionless;
  }
  location @extensionless {
    if (-f $request_filename.php)  { rewrite ^(.*)$ $1.php last; }
    if (-f $request_filename.html) { rewrite ^(.*)$ $1.html last; }
    return 404;
  }

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_intercept_errors on;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    try_files $uri =404;
    fastcgi_read_timeout 3600;
    fastcgi_send_timeout 3600;
    fastcgi_param HTTPS "on";
    fastcgi_param SERVER_PORT 443;
    fastcgi_pass 127.0.0.1:{{php_fpm_port}};
    fastcgi_param PHP_VALUE "{{php_settings}}";
  }

  if (-f $request_filename) {
    break;
  }
}
```

**Never** put `$uri.php` in a `try_files` list before its last entry: nginx sends the earlier entries
as plain files, so `alas` would download the PHP source of `alas.php`. The `@extensionless` block
rewrites to `alas.php` instead, which then runs through the `\.php$` block.

**Turn Varnish Cache off** for this site (site → Varnish Cache). It's a page cache, and on a signed-in
dashboard it risks showing one employee's page to another.

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
  Download it on a phone from https://wedo.kmds.systems/app/WeDo-Test.apk (`app/WeDo-Test.apk`, on
  this branch only). The site's own download page (`/app/`) offers the REAL app, `WeDo.apk`, which opens production.
- Happy with it: staging → production as usual. `vps` never deploys anywhere else.
