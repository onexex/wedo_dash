<?php
/* ==========================================================================
   app-remember.php  —  keeps the WeDo mobile app signed in.

   The website's session lasts 1 hour and its "remember me" (WeDoID cookie,
   one token per employee in empdetails) 8 hours. That is right for a shared
   office PC but not for a phone app, where people expect to stay signed in.

   App logins (User-Agent contains "WeDoApp/", added by the Capacitor app) also
   get their own token: one row per phone in app_remember (sql/2026-10-07-add-
   app-remember.sql), cookie WeDoApp, valid 30 days and extended each time it
   is used. Being per phone, signing in on the website (which replaces the
   WeDoID token) does not sign the phone out. Only a SHA-256 of the random
   token is stored. Signing out of the app deletes that phone's row.

   login.php restores the session from it; the app always opens on login.php
   and pages already send expired sessions there.
   ========================================================================== */

const APP_REMEMBER_COOKIE = 'WeDoApp';
const APP_REMEMBER_DAYS   = 30;

function app_is_app(): bool
{
    return stripos((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'WeDoApp/') !== false;
}

function app_remember_cookie(string $value, int $expires): void
{
    if (headers_sent()) { return; }
    setcookie(APP_REMEMBER_COOKIE, $value, ['expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')]);
}

/** After a successful app login: give this phone its own 30-day token. */
function app_remember_issue(PDO $pdo, string $empId): void
{
    try {
        $token = bin2hex(random_bytes(32));
        $exp   = time() + APP_REMEMBER_DAYS * 86400;
        $pdo->prepare("INSERT INTO app_remember (EmpID, token_hash, expires_at, created_at, last_used)
                       VALUES (:e, :h, :x, :n, :n2)")
            ->execute([':e' => $empId, ':h' => hash('sha256', $token), ':x' => date('Y-m-d H:i:s', $exp),
                       ':n' => date('Y-m-d H:i:s'), ':n2' => date('Y-m-d H:i:s')]);
        // the old token of this phone (if it signed in again) and anything expired
        if (!empty($_COOKIE[APP_REMEMBER_COOKIE])) {
            $pdo->prepare("DELETE FROM app_remember WHERE token_hash = :h")->execute([':h' => hash('sha256', (string) $_COOKIE[APP_REMEMBER_COOKIE])]);
        }
        $pdo->prepare("DELETE FROM app_remember WHERE expires_at < :n")->execute([':n' => date('Y-m-d H:i:s')]);
        app_remember_cookie($token, $exp);
    } catch (Throwable $e) {
        error_log('[wedo app-remember] issue: ' . $e->getMessage());   // e.g. table not created yet: plain web login still works
    }
}

/**
 * EmpID of the phone's valid token (and pushes its expiry 30 days out), or null.
 * Resigned employees (EmpStatusID 2, also refused by query-login.php) are not restored.
 */
function app_remember_restore(PDO $pdo): ?string
{
    $token = (string) ($_COOKIE[APP_REMEMBER_COOKIE] ?? '');
    if ($token === '' || !ctype_xdigit($token)) { return null; }
    try {
        $st = $pdo->prepare("SELECT ar.id, ar.EmpID FROM app_remember ar
                             JOIN employees e ON e.EmpID = ar.EmpID AND e.EmpStatusID <> 2
                             WHERE ar.token_hash = :h AND ar.expires_at > :n");
        $st->execute([':h' => hash('sha256', $token), ':n' => date('Y-m-d H:i:s')]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) { app_remember_cookie('', time() - 3600); return null; }

        $exp = time() + APP_REMEMBER_DAYS * 86400;
        $pdo->prepare("UPDATE app_remember SET expires_at = :x, last_used = :n WHERE id = :id")
            ->execute([':x' => date('Y-m-d H:i:s', $exp), ':n' => date('Y-m-d H:i:s'), ':id' => $r['id']]);
        app_remember_cookie($token, $exp);
        return (string) $r['EmpID'];
    } catch (Throwable $e) {
        error_log('[wedo app-remember] restore: ' . $e->getMessage());
        return null;
    }
}

/** Sign-out: forget this phone's token. */
function app_remember_revoke(?PDO $pdo): void
{
    $token = (string) ($_COOKIE[APP_REMEMBER_COOKIE] ?? '');
    if ($token !== '' && $pdo) {
        try {
            $pdo->prepare("DELETE FROM app_remember WHERE token_hash = :h")->execute([':h' => hash('sha256', $token)]);
        } catch (Throwable $e) { /* non-fatal */ }
    }
    if ($token !== '') { app_remember_cookie('', time() - 3600); }
}
