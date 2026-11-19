<?php
/* ==========================================================================
   api/gsm-ingest.php  —  where the SIM Inbox collector sends new texts.

   POST, JSON: { "collector": "office-pc", "version": "1.0", "ok": true,
                 "lines": [1, 6, 7, 8],
                 "messages": [{ "line": 1, "sender": "222", "text": "...",
                                "device_time": "11-19 10:15:07", "seen_at": "2026-11-19T10:15:30+08:00" }] }
   Header: X-WeDo-Key: <config.local.php 'gsm' => ['ingest_key' => ...]>   (or Authorization: Bearer <key>)
   HTTPS only (plain http only from this machine, for local development).

   Answers { ok, stored, duplicates, skipped, accepted: [fingerprints] }.
   Rules: includes/gsm-lib.php; the collector: tools/gsm-collector.php;
   setup guide: tools/gsm-collector.md
   ========================================================================== */
require_once __DIR__ . '/../w_conn.php';
require_once __DIR__ . '/../includes/api-auth-lib.php';   // wd_api_request_key(), wd_api_is_https(), wd_api_body()
require_once __DIR__ . '/../includes/gsm-lib.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function gsm_api_out(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    gsm_api_out(405, ['ok' => false, 'error' => 'method', 'message' => 'POST only.']);
}
if (!wd_api_is_https()) {
    gsm_api_out(403, ['ok' => false, 'error' => 'https_required', 'message' => 'Use https.']);
}

$cfg = gsm_config();
$key = wd_api_request_key();
if ($cfg['key'] === '') {
    gsm_api_out(503, ['ok' => false, 'error' => 'not_configured', 'message' => "No collector key is set on this server: add 'gsm' => ['ingest_key' => ...] to config.local.php."]);
}
if ($key === '' || !hash_equals($cfg['key'], $key)) {
    gsm_api_out(401, ['ok' => false, 'error' => 'bad_key', 'message' => 'Wrong or missing key.']);
}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!gsm_ready($pdo)) {
        gsm_api_out(503, ['ok' => false, 'error' => 'not_set_up', 'message' => 'SIM Inbox is not set up on this server yet: run ' . GSM_SQL . '.']);
    }
    [$code, $body] = gsm_ingest($pdo, wd_api_body(), wd_client_ip());
} catch (Throwable $e) {
    error_log('[wedo gsm-ingest] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    [$code, $body] = [500, ['ok' => false, 'error' => 'server_error', 'message' => 'Something went wrong. The collector will send these again.']];
}
gsm_api_out($code, $body);
