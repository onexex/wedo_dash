# SIM Inbox: setting up the collector

The office GoIP16 GSM gateway (at `http://192.168.35.51` on the office network) holds
the company SIMs. WeDo is on the internet and cannot reach it, and the office has two
internet lines with separate networks. So a small **collector** runs on a PC that is on
the **same network as the gateway**. It reads the gateway's inbox every minute and sends
new texts out to WeDo. Nothing on the office network is opened to the internet.

```
GoIP gateway ──(office LAN)──> office PC: gsm-collector.php ──(https, secret key)──> WeDo api/gsm-ingest.php
```

## 1. On the WeDo server (once per server)

1. Run `sql/2026-11-19-add-gsm-inbox.sql` on that server's database.
2. Make a key: `php -r "echo bin2hex(random_bytes(32));"`
3. Add it to that server's `config.local.php`:
   ```php
   'gsm' => ['ingest_key' => '<the key>', 'warn_days' => 3],
   ```
4. Give yourself the **SIM Inbox** right (Access Rights > Management). It is off for
   everyone by default. The SIMs can receive one-time codes, so give it to few people.

## 2. On the gateway

1. **Change the default password** (the gateway's web page, under Configurations).
   With the default password, anyone on the office Wi-Fi can send texts from the SIMs.
2. Optional: under Configurations, set a time server (NTP) so the gateway's clock is
   no longer in 1970. WeDo records when the collector first saw each text, so this
   isn't required.

## 3. On the office PC (must stay on, and be on the gateway's network)

The PC needs PHP with curl. XAMPP's `C:\xampp\php\php.exe` is enough. Without XAMPP,
install PHP for Windows and copy only `tools/gsm-collector.php` and the config.

1. Check the PC can see the gateway: open `http://192.168.35.51` in its browser.
2. Copy `gsm-collector.config.example.php` to `gsm-collector.config.php` (same folder)
   and fill in the gateway password, the lines with SIMs, and each WeDo's URL + key.
   This file holds passwords. Git ignores it; never send it to anyone.
3. Test reading the gateway. This sends nothing:
   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\wedo\tools\gsm-collector.php --test
   ```
   If it says the inbox page has an unexpected layout, save the page with `--dump=inbox.html`
   and pass it to whoever looks after WeDo. Delete it afterwards, because it holds texts.
4. Test the WeDo key. This tells WeDo the collector exists, without sending texts:
   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\wedo\tools\gsm-collector.php --check
   ```
5. Run it every minute with Task Scheduler. "Run whether user is logged on or not"
   keeps it going after sign-out:
   ```powershell
   schtasks /Create /TN "WeDo SIM Inbox collector" /SC MINUTE /MO 1 /RU SYSTEM /TR "C:\xampp\php\php.exe C:\xampp\htdocs\wedo\tools\gsm-collector.php"
   ```
   Runs never overlap: a run that finds the previous one still going skips itself.

The SIM Inbox page shows the collector as **Stopped** if it hasn't checked in for 10
minutes (`stale_minutes`). It shows **Gateway problem** if it runs but can't read the
gateway (wrong password, gateway off, PC on the other network).

## Load balance and expiry

WeDo reads a SIM's balance and expiry from texts sent by short codes or named senders
such as `222` or `Globe`. Texts from phone numbers are never used for this. It only
uses what a text plainly states, for example "balance is P12.50" or "valid until
12/31/2026". Promos like "Load P50 and get 5GB" are ignored. If a carrier's wording
isn't recognised, set the expiry and balance by hand with the pencil on the line's
card. The next carrier text that states them takes over again.

Optional daily check: `'balance_check' => ['number' => '222', 'text' => 'BAL', 'at' => '08:00']`
in the collector config. After 8:00 each day, every line in `'lines'` texts the keyword,
and the carrier's reply updates the page. Each inquiry is a real text from the SIM, so
check the carrier's keyword and charges first. To try it once now, use `--balance`.

## Commands

| | |
|---|---|
| `gsm-collector.php` | read once, send what is new (what Task Scheduler runs) |
| `--loop` | keep running, every `'interval'` seconds |
| `--test` | read the gateway and print the texts; sends nothing |
| `--check` | read the gateway, then tell WeDo the result (tests key and URL) |
| `--dump=FILE` | save the gateway's raw inbox page |
| `--balance` | send the balance-inquiry text now |
| `--config=PATH` | use another config file |
