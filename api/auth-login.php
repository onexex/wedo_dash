<?php
/* ==========================================================================
   api/auth-login.php  —  sign-in API for other apps (server to server).

   POST, JSON or form: { "username": "...", "password": "...", "ip": "<the person's address, optional>" }
   Header: X-WeDo-Key: <this app's key>   (or Authorization: Bearer <key>)
   Rules and answers: includes/api-auth-lib.php; guide: tools/api-auth-login.md
   ========================================================================== */
require_once __DIR__ . '/../w_conn.php';
require_once __DIR__ . '/../includes/api-auth-lib.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '" . (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('P') . "'");
    [$code, $body, $headers] = wd_api_auth_login($pdo);
    // a sign-in like any other: the first of the day posts the Corner birthday greetings
    if ($code === 200) {
        require_once __DIR__ . '/../includes/corner-lib.php';
        try { wd_post_birthday_greetings($pdo); } catch (Throwable $e) { error_log('[wedo birthday] ' . $e->getMessage()); }
    }
} catch (Throwable $e) {
    error_log('[wedo api-auth] ' . $e->getMessage());
    [$code, $body, $headers] = [500, ['ok' => false, 'error' => 'server_error', 'message' => 'Something went wrong. Try again.'], []];
}

http_response_code($code);
foreach ($headers as $name => $value) { header("$name: $value"); }
echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
