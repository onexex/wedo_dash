<?php
/* =============================================================================
 * username.php — helpers to build a UNIQUE login username (empdetails.EmpUN).
 *
 * EmpUN is the login handle (query-login.php: WHERE EmpUN=:un), so it must be
 * unique. The classic "first initial + last name" scheme collides badly
 * (Ramon Gemana + Rio Gemana → both "RGemana"). These helpers resolve that by
 * lengthening the first-name prefix before ever falling back to a number:
 *     Ramon Gemana → RGemana
 *     Rio   Gemana → RiGemana   (RGemana taken)
 *     Rio   Gemana → RioGemana  (RiGemana also taken) … then RioGemana2, …
 * ========================================================================== */

/* is this exact username already in use? */
function wd_username_taken(PDO $pdo, $u) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM empdetails WHERE EmpUN = :u");
    $s->execute([':u' => $u]);
    return (int) $s->fetchColumn() > 0;
}

/* return $desired, or $desired2 / $desired3 / … — the first that is free */
function wd_free_username(PDO $pdo, $desired) {
    $desired = trim($desired);
    if ($desired === '') { return ''; }
    if (!wd_username_taken($pdo, $desired)) { return $desired; }
    for ($n = 2; $n < 1000; $n++) {
        if (!wd_username_taken($pdo, $desired . $n)) { return $desired . $n; }
    }
    return $desired . uniqid();   // pathological fallback, still unique
}

/* build a unique username from a first + last name */
function wd_unique_username(PDO $pdo, $fn, $ln) {
    $fn = preg_replace('/\s+/', '', (string) $fn);   // drop spaces
    $ln = preg_replace('/\s+/', '', (string) $ln);
    if ($fn === '' || $ln === '') { return ''; }

    $lnCap = ucfirst(strtolower($ln));               // "Gemana"
    $len   = function_exists('mb_strlen') ? mb_strlen($fn) : strlen($fn);

    /* try growing first-name prefixes: R…, Ri…, Rio… + last name */
    for ($i = 1; $i <= $len; $i++) {
        $sub  = function_exists('mb_substr') ? mb_substr($fn, 0, $i) : substr($fn, 0, $i);
        $cand = ucfirst(strtolower($sub)) . $lnCap;
        if (!wd_username_taken($pdo, $cand)) { return $cand; }
    }
    /* identical first+last names → numeric suffix on the full form */
    return wd_free_username($pdo, ucfirst(strtolower($fn)) . $lnCap);
}
