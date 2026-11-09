# Subscriptions: the daily reminder job

Renewal reminders go out once a day at 8:00 AM Manila. A cron job sends them on time. If the cron is missing or misses a day, the first WeDo page anyone opens after 8:00 AM sends them instead, so nothing is lost. The difference is that with no cron, reminders on a quiet day (a weekend with no visits) wait until someone opens WeDo.

Set it up once on **each** server (staging and production) after running `sql/2026-11-09-add-subscriptions.sql`.

## cPanel (GoDaddy)

1. cPanel → **Advanced** → **Cron Jobs**.
2. Check the server's time zone. cPanel cron uses the server clock, which is often **not** Manila. Run `date` in cPanel → Terminal, or look at the time shown on the Cron Jobs page.
   - If the server is on Manila time (UTC+8): minute `0`, hour `8`.
   - If the server is on UTC: minute `0`, hour `0` (00:00 UTC = 8:00 AM Manila).
   - If it's on US Arizona time (MST, UTC−7, common on GoDaddy): minute `0`, hour `17` (5:00 PM the day before).
3. Day, month and weekday: `*`.
4. Command (change the path to the site's folder):

```
/usr/local/bin/php /home/ACCOUNT/public_html/tools/subscriptions-daily.php >> /home/ACCOUNT/subscriptions-daily.log 2>&1
```

`which php` in Terminal gives the right PHP path. If cPanel lists several PHP versions, use the same one as the site, e.g. `/opt/cpanel/ea-php82/root/usr/bin/php`.

## Checking it

- `php tools/subscriptions-daily.php --dry-run` shows who would be reminded and what about, and sends nothing.
- On the WeDo **Subscriptions** page, the **Reminders** tab lists every day's run, whether the scheduled job or the page-load fallback sent it, and who got what.
- Running the script twice in a day is harmless: the second run says "today's reminders already went out".

Phone notifications need the Firebase key (`fcm_key` in `config.local.php`, see `includes/push-lib.php`). Without it the job still runs and logs the reminders, and people see them on the bell, the dashboard and the desktop pop-up, but no phone notification is sent.
