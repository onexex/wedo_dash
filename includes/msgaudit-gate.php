<?php
/* --------------------------------------------------------------------------
   Who may grant / revoke the `msgaudit` access right ("Messages: Review All
   Conversations", Management > Message Review): only the login named below.
   For everyone else Access Rights hides the switch (searchaccessrights.php)
   and the save endpoint refuses it (updateaccess.php).
   -------------------------------------------------------------------------- */

const MSGAUDIT_GATEKEEPER = 'rgemana';   // empdetails.EmpUN

function msgaudit_can_grant(PDO $pdo, $empId): bool
{
    if ($empId === null || $empId === '' || $empId === '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT EmpUN FROM empdetails WHERE EmpID = :id");
        $st->execute([':id' => $empId]);
        return strcasecmp(trim((string) $st->fetchColumn()), MSGAUDIT_GATEKEEPER) === 0;
    } catch (PDOException $e) {
        return false;
    }
}
