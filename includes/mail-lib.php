<?php
/* ==========================================================================
   mail-lib.php  —  sending email through Brevo (https://www.brevo.com).
   Brevo's HTTP API is called directly (no library), the same way
   bot-guard.php calls Cloudflare. Settings, in the untracked
   config.local.php (env vars win):
     brevo_api_key   BREVO_API_KEY    Brevo > SMTP & API > API keys
     mail_from       MAIL_FROM        a sender verified in Brevo (Senders & Domains)
     mail_from_name  MAIL_FROM_NAME   default "WeDo BPO Inc."
     mail_reply_to   MAIL_REPLY_TO    optional: where replies go
     site_url        SITE_URL         optional: links in emails (default: this host)
   Without a key and a sender, email is simply off: wd_mail_send() returns
   not-ok and nothing else changes. It never throws.
   Tests: with env WEDO_MAIL_LOG set, each message is appended to that file
   (one JSON line) instead of being sent.
   ========================================================================== */
require_once __DIR__ . '/bot-guard.php';   // wd_local_config()

/** Brevo settings, or null when email is not configured. */
function wd_mail_config(): ?array
{
    $cfg  = wd_local_config();
    $get  = function ($env, $key, $default = '') use ($cfg) {
        $v = getenv($env);
        return trim((string) ($v !== false && $v !== '' ? $v : ($cfg[$key] ?? $default)));
    };
    $key  = $get('BREVO_API_KEY', 'brevo_api_key');
    $from = $get('MAIL_FROM', 'mail_from');
    if ($key === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) { return null; }
    $reply = $get('MAIL_REPLY_TO', 'mail_reply_to');
    return [
        'key'      => $key,
        'from'     => $from,
        'fromName' => $get('MAIL_FROM_NAME', 'mail_from_name', 'WeDo BPO Inc.'),
        'replyTo'  => filter_var($reply, FILTER_VALIDATE_EMAIL) ? $reply : '',
    ];
}

function wd_mail_enabled(): bool { return wd_mail_config() !== null; }

/** The site's address for links in emails: config site_url, else this request's host and folder. */
function wd_site_url(): string
{
    $cfg = wd_local_config();
    $url = getenv('SITE_URL') ?: ($cfg['site_url'] ?? '');
    if ($url === '' && !empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $dir   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $dir   = preg_replace('#/query$#', '', $dir);   // endpoints live one folder down
        $url   = ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $dir;
    }
    return rtrim((string) $url, '/');
}

/**
 * Send one email. $html is the full HTML body (see wd_mail_layout), $text the
 * plain-text version.
 * @return array{ok:bool, error:string}
 */
function wd_mail_send(string $toEmail, string $toName, string $subject, string $html, string $text = ''): array
{
    $cfg = wd_mail_config();
    if (!$cfg) { return ['ok' => false, 'error' => 'Email is not set up on this server.']; }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'error' => 'The email address is not valid.']; }

    $msg = [
        'sender'      => ['email' => $cfg['from'], 'name' => $cfg['fromName']],
        'to'          => [['email' => $toEmail] + (trim($toName) !== '' ? ['name' => mb_substr(trim($toName), 0, 70)] : [])],   // Brevo refuses an empty name
        'subject'     => $subject,
        'htmlContent' => $html,
    ];
    if ($text !== '') { $msg['textContent'] = $text; }
    if ($cfg['replyTo'] !== '') { $msg['replyTo'] = ['email' => $cfg['replyTo']]; }

    $log = getenv('WEDO_MAIL_LOG');
    if ($log) {   // tests: write instead of sending
        @file_put_contents($log, json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
        return ['ok' => true, 'error' => ''];
    }

    $r = wd_brevo('POST', '/smtp/email', $cfg['key'], $msg);
    if ($r['code'] >= 200 && $r['code'] < 300) { return ['ok' => true, 'error' => '']; }
    error_log('[wedo mail] to ' . $toEmail . ': ' . $r['error']);
    return ['ok' => false, 'error' => $r['error']];
}

/**
 * One call to Brevo's API (https://api.brevo.com/v3 + $path).
 * @return array{code:int, json:?array, error:string} code 0 = Brevo not reached
 */
function wd_brevo(string $method, string $path, string $key, ?array $body = null): array
{
    $opts = ['method' => $method, 'timeout' => 8, 'ignore_errors' => true,
             'header' => "Accept: application/json\r\napi-key: " . $key . "\r\n"];
    if ($body !== null) {
        $opts['header'] .= "Content-Type: application/json\r\n";
        $opts['content'] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $res  = @file_get_contents('https://api.brevo.com/v3' . $path, false, stream_context_create(['http' => $opts]));
    $code = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int) $m[1]; }
    }
    $j = is_string($res) ? json_decode($res, true) : null;
    if ($code >= 200 && $code < 300) { return ['code' => $code, 'json' => is_array($j) ? $j : null, 'error' => '']; }
    if ($code === 0) {
        $last = error_get_last();
        $why = 'Brevo could not be reached' . ($last ? ' (' . $last['message'] . ')' : '');
    } else {
        $why = is_array($j) && !empty($j['message']) ? (string) $j['message'] : "HTTP $code";
    }
    return ['code' => $code, 'json' => is_array($j) ? $j : null, 'error' => $why];
}

/**
 * Why email does or doesn't work on this server, step by step — for the
 * "Email setup" check in the Applicant Pool. Asks Brevo whether the key is
 * accepted and whether the sender is verified. Never returns the key itself.
 * @return array{enabled:bool, from:string, checks:array<int, array{level:string, text:string}>}
 */
function wd_mail_diagnose(): array
{
    $checks = [];
    $add = function ($level, $text) use (&$checks) { $checks[] = ['level' => $level, 'text' => $text]; };
    $cfg  = wd_local_config();
    $env  = function ($e, $k) use ($cfg) { $v = getenv($e); return trim((string) ($v !== false && $v !== '' ? $v : ($cfg[$k] ?? ''))); };
    $file = dirname(__DIR__) . '/config.local.php';

    if (is_file($file)) { $add('ok', 'Settings file config.local.php found in ' . basename(dirname(__DIR__)) . '/.'); }
    else { $add('bad', 'No config.local.php in ' . basename(dirname(__DIR__)) . '/ — create it next to w_conn.php.'); }

    $key = $env('BREVO_API_KEY', 'brevo_api_key');
    if ($key === '') {
        $add('bad', "brevo_api_key is missing — add 'brevo_api_key' => 'xkeysib-…' inside the return [ … ] array.");
    } elseif (strpos($key, 'xsmtpsib-') === 0) {
        $add('bad', 'brevo_api_key is an SMTP key (xsmtpsib-…). Use an API key instead: Brevo > SMTP & API > API keys (it starts with xkeysib-).');
    } else {
        // only the public "xkeysib-" prefix and the last 4 characters of a full-length key are ever shown
        $isApi = strpos($key, 'xkeysib-') === 0;
        $hint  = ($isApi ? 'xkeysib-…' : '') . (strlen($key) >= 24 ? substr($key, -4) . ', ' : '') . strlen($key) . ' characters';
        $add($isApi ? 'ok' : 'warn', 'API key is set (' . $hint . ')' . ($isApi ? '.' : ' — Brevo API keys usually start with xkeysib-.'));
    }

    $from = $env('MAIL_FROM', 'mail_from');
    if ($from === '') { $add('bad', "mail_from is missing — add 'mail_from' => 'you@yourdomain' (a sender verified in Brevo)."); }
    elseif (!filter_var($from, FILTER_VALIDATE_EMAIL)) { $add('bad', 'mail_from "' . $from . '" is not a valid email address.'); }
    else { $add('ok', 'Sender (mail_from): ' . $from); }

    $canReach = ini_get('allow_url_fopen') && extension_loaded('openssl');
    if (!$canReach) { $add('bad', 'This server cannot call Brevo: PHP needs allow_url_fopen = On and the openssl extension.'); }

    $ready = $key !== '' && strpos($key, 'xsmtpsib-') !== 0 && $canReach;
    if ($ready && !getenv('WEDO_MAIL_LOG')) {   // tests never call Brevo
        $acct = wd_brevo('GET', '/account', $key);
        if ($acct['code'] === 200) {
            $plan = $acct['json']['plan'][0]['type'] ?? '';
            $add('ok', 'Brevo accepted the key — account ' . ($acct['json']['email'] ?? '?') . ($plan !== '' ? ' (' . $plan . ' plan)' : '') . '.');
            if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $s = wd_brevo('GET', '/senders', $key);
                $found = null;
                foreach (($s['json']['senders'] ?? []) as $x) {
                    if (strcasecmp((string) ($x['email'] ?? ''), $from) === 0) { $found = $x; }
                }
                if ($s['code'] !== 200) { $add('warn', 'Could not read the sender list from Brevo: ' . $s['error']); }
                elseif ($found === null) { $add('bad', $from . ' is not a sender in Brevo — add and verify it under Senders, Domains & IPs.'); }
                elseif (empty($found['active'])) { $add('bad', $from . ' is in Brevo but not verified yet — open the confirmation email Brevo sent to that address.'); }
                else { $add('ok', $from . ' is a verified sender in Brevo.'); }
            }
        } else {
            $add('bad', ($acct['code'] === 0 ? '' : 'Brevo rejected the key: ') . $acct['error']);
        }
    }
    return ['enabled' => wd_mail_enabled(), 'from' => $from, 'checks' => $checks];
}

/**
 * The shared look of every email: navy header with the sender's name, a white
 * card, and the Data Privacy footer. $bodyHtml must already be escaped.
 */
function wd_mail_layout(string $title, string $bodyHtml): string
{
    $cfg  = wd_mail_config();
    $name = htmlspecialchars($cfg['fromName'] ?? 'WeDo BPO Inc.', ENT_QUOTES, 'UTF-8');
    $t    = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $t . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f6fb;font-family:Segoe UI,Arial,sans-serif;color:#16203a">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:24px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">'
        . '<tr><td style="background:#09152e;border-radius:14px 14px 0 0;padding:20px 28px;color:#fff;font-size:18px;font-weight:700">' . $name . '</td></tr>'
        . '<tr><td style="background:#fff;border-radius:0 0 14px 14px;padding:28px;font-size:15px;line-height:1.6">'
        . '<h1 style="margin:0 0 14px;font-size:21px;color:#16203a">' . $t . '</h1>' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:16px 8px;color:#909aab;font-size:12px;text-align:center">'
        . 'You received this because you applied at ' . $name . '. Your information is protected under the Data Privacy Act of 2012 (RA 10173).'
        . '</td></tr></table></td></tr></table></body></html>';
}

/** Escape text for an email body; line breaks kept. */
function wd_mail_text(string $s): string
{
    return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'), false);
}
