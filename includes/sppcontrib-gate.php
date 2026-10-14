<?php
/* --------------------------------------------------------------------------
   SSS / Pag-IBIG / PhilHealth contribution tables and SIL Loan are gated by
   the `SPPContrib` access right (==2) — the same column the sidebar uses.
   Shared by the page router (maintenance.php) and its AJAX/POST endpoints.
   -------------------------------------------------------------------------- */

// Request keys (?sss, ?updatesss, ...) that belong to these four pages.
const SPP_CONTRIB_KEYS = ['sss', 'pagibig', 'philhealth', 'silloan',
                          'updatesss', 'updatepagibig', 'updatephilhealth', 'updatesilloan'];

function spp_contrib_requested() {
    foreach (SPP_CONTRIB_KEYS as $k) {
        if (isset($_GET[$k])) { return true; }
    }
    return false;
}

function spp_contrib_allowed() {
    if (empty($_SESSION['id']) || $_SESSION['id'] == "0") { return false; }
    include __DIR__ . '/../w_conn.php';
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $st = $pdo->prepare("SELECT SPPContrib FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $_SESSION['id']]);
        return (int) $st->fetchColumn() === 2;
    } catch (PDOException $e) {
        return false;
    }
}
