<?php
/* =============================================================================
 * includes/profile-change.php — profile change requests.
 *
 * Employees may request changes to their OWN 201 record (general information,
 * education, compliance IDs, family list). Nothing is written to the record
 * until a user with "Update 201 Files" (accessrights.updte = 2) approves it.
 * Users with that right keep editing everyone directly.
 *
 * Used by: UpdateEmployeeInfo.php (request mode), query/Query-requestProfileChange.php,
 *          query/Query-reviewProfileChange.php, profilerequests.php, includes/wd-header.php
 * Table:   profile_change_requests (sql/2026-10-01-add-profile-change-requests.sql)
 * ========================================================================== */

const PCR_SECTIONS = [
    'general'   => 'General information',
    'education' => 'Educational background',
    'ids'       => 'Compliance documents',
    'family'    => 'Family details',
];

/** Fields an employee may request. form field => [section, table, column, label, type]. */
function pcr_fields(): array
{
    static $f = null;
    if ($f !== null) { return $f; }
    $f = [
        'empfn'          => ['general', 'employees',   'EmpFN',         'First name'],
        'empmn'          => ['general', 'employees',   'EmpMN',         'Middle name'],
        'empln'          => ['general', 'employees',   'EmpLN',         'Last name'],
        'empsuff'        => ['general', 'employees',   'EmpSuffix',     'Suffix'],
        'pempgender'     => ['general', 'empprofiles', 'EmpGender',     'Gender'],
        'pempdob'        => ['general', 'empprofiles', 'EmpDOB',        'Date of birth', 'date'],
        'pempcs'         => ['general', 'empprofiles', 'EmpCS',         'Civil status'],
        'empcitizen'     => ['general', 'empprofiles', 'EmpCitezen',    'Citizenship'],
        'empreligion'    => ['general', 'empprofiles', 'EmpReligion',   'Religion'],
        'pempn0'         => ['general', 'empprofiles', 'EmpPhone',      'Mobile number'],
        'emphomenumber'  => ['general', 'empprofiles', 'EmpMobile',     'Home phone'],
        'pempeadd'       => ['general', 'empprofiles', 'EmpEmail',      'Email address'],
        'pempstreetno'   => ['general', 'empprofiles', 'EmpAddress1',   'Street / subdivision'],
        'pempdistrict'   => ['general', 'empprofiles', 'EmpAddDis',     'Barangay'],
        'pempcity'       => ['general', 'empprofiles', 'EmpAddCity',    'City'],
        'pempprovince'   => ['general', 'empprofiles', 'EmpAddProv',    'Province'],
        'pempzipcode'    => ['general', 'empprofiles', 'EmpAddZip',     'Zip code'],
        'pempcountry'    => ['general', 'empprofiles', 'EmpAddCountry', 'Country'],
        'pempppno'       => ['ids',     'empprofiles', 'EmpPPNo',       'Passport number'],
        'pemppped'       => ['ids',     'empprofiles', 'EmpPPED',       'Passport expiry'],
        'pempppia'       => ['ids',     'empprofiles', 'EmpPPIA',       'Passport issuing authority'],
        'pemppagibig'    => ['ids',     'empprofiles', 'EmpPINo',       'Pag-IBIG'],
        'pempphno'       => ['ids',     'empprofiles', 'EmpPHNo',       'PhilHealth'],
        'pempsss'        => ['ids',     'empprofiles', 'EmpSSS',        'SSS'],
        'pemptin'        => ['ids',     'empprofiles', 'EmpTIN',        'TIN'],
        'pempumid'       => ['ids',     'empprofiles', 'EmpUMIDNo',     'UMID'],
    ];
    foreach (['p' => 'Primary', 's' => 'Secondary', 't' => 'Tertiary'] as $k => $prog) {
        $f["{$k}_nameofschool"]   = ['education', "edu:$prog", 'Name_of_School', "$prog school"];
        $f["{$k}_yearstarted"]    = ['education', "edu:$prog", 'Year_Started',   "$prog year started"];
        $f["{$k}_yeargraduated"]  = ['education', "edu:$prog", 'Year_End',       "$prog year graduated"];
        $f["{$k}_schooladdress"]  = ['education', "edu:$prog", 'School_Address', "$prog school address"];
    }
    return $f;
}

/** Fields that may not be blank in a request (same as the edit form's required fields). */
const PCR_REQUIRED = ['empfn', 'empln', 'pempgender', 'pempcs', 'pempdob',
                      'pempdistrict', 'pempcity', 'pempprovince', 'pempzipcode', 'pempcountry'];

function pcr_can_update(PDO $pdo, string $empId): bool
{
    $st = $pdo->prepare('SELECT updte FROM accessrights WHERE EmpID = ?');
    $st->execute([$empId]);
    return (string)$st->fetchColumn() === '2';
}

/** Compare-friendly value: trimmed, zero dates / zero years as blank. */
function pcr_norm($v): string
{
    $v = trim((string)$v);
    if ($v === '0' || strpos($v, '0000-00-00') === 0) { return ''; }
    return $v;
}

/** Current values of every requestable field for one employee, or null if not found. */
function pcr_current(PDO $pdo, string $empId): ?array
{
    $st = $pdo->prepare('SELECT e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, p.*
                           FROM employees e LEFT JOIN empprofiles p ON p.EmpID = e.EmpID
                          WHERE e.EmpID = ?');
    $st->execute([$empId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { return null; }

    $edu = [];
    $st = $pdo->prepare('SELECT * FROM empeducationalbackground WHERE EmpID = ? ORDER BY EB_ID');
    $st->execute([$empId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) { $edu[$e['Program']] = $edu[$e['Program']] ?? $e; }

    $cur = [];
    foreach (pcr_fields() as $field => $def) {
        [, $table, $col] = $def;
        $cur[$field] = pcr_norm(strpos($table, 'edu:') === 0 ? ($edu[substr($table, 4)][$col] ?? '') : ($row[$col] ?? ''));
    }
    return $cur;
}

/** Family / ICE list as [{name,address,relationship,contact,ice}]. */
function pcr_family(PDO $pdo, string $empId): array
{
    $st = $pdo->prepare('SELECT FName, FAdd, FRel, FContact, FICE FROM fdetails WHERE FDetID = ? ORDER BY FSID');
    $st->execute([$empId]);
    return array_map(fn($r) => ['name' => trim((string)$r['FName']), 'address' => trim((string)$r['FAdd']),
                                'relationship' => trim((string)$r['FRel']), 'contact' => trim((string)$r['FContact']),
                                'ice' => trim((string)$r['FICE'])], $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Normalize a posted family list (JSON string or array); rows without a name are dropped. */
function pcr_family_from_post($raw): array
{
    $list = is_array($raw) ? $raw : json_decode((string)$raw, true);
    if (!is_array($list)) { return []; }
    $out = [];
    foreach ($list as $r) {
        if (!is_array($r)) { continue; }
        $name = trim((string)($r['name'] ?? ''));
        if ($name === '') { continue; }
        $ice = trim((string)($r['ice'] ?? ''));
        $out[] = ['name' => $name, 'address' => trim((string)($r['address'] ?? '')),
                  'relationship' => trim((string)($r['relationship'] ?? '')), 'contact' => trim((string)($r['contact'] ?? '')),
                  'ice' => strcasecmp($ice, 'Yes') === 0 ? 'Yes' : 'No'];
    }
    return $out;
}

/** Changed fields between the current record and what was posted (only requestable fields). */
function pcr_diff(array $current, array $posted): array
{
    $changes = [];
    foreach (pcr_fields() as $field => $def) {
        if (!array_key_exists($field, $posted)) { continue; }
        $new = pcr_norm($posted[$field]);
        $old = $current[$field] ?? '';
        if ($new !== $old) {
            $changes[] = ['field' => $field, 'section' => $def[0], 'label' => $def[3], 'old' => $old, 'new' => $new];
        }
    }
    return $changes;
}

/** Most recent request of an employee (any status), with Changes decoded; null if none / table missing. */
function pcr_latest(PDO $pdo, string $empId): ?array
{
    try {
        $st = $pdo->prepare("SELECT * FROM profile_change_requests WHERE EmpID = ? AND Status <> 'cancelled'
                              ORDER BY id DESC LIMIT 1");
        $st->execute([$empId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;   // migration not applied yet
    }
    if (!$r) { return null; }
    $r['Changes'] = json_decode($r['Changes'], true) ?: ['fields' => [], 'family' => null];
    return $r;
}

function pcr_pending_count(PDO $pdo): int
{
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM profile_change_requests WHERE Status = 'pending'")->fetchColumn();
    } catch (PDOException $e) {
        return 0;      // migration not applied yet
    }
}

/**
 * Write an approved request into the employee's record. Columns come from
 * pcr_fields() (never from input); values are bound. Call inside a transaction.
 */
function pcr_apply(PDO $pdo, string $empId, array $changes): void
{
    $fields = pcr_fields();
    $byTable = [];
    foreach ($changes['fields'] ?? [] as $c) {
        if (!isset($fields[$c['field']])) { continue; }            // ignore anything not requestable
        [, $table, $col] = $fields[$c['field']];
        $type = $fields[$c['field']][4] ?? 'text';
        $byTable[$table][$col] = ($type === 'date' && $c['new'] === '') ? null : $c['new'];
    }

    foreach ($byTable as $table => $cols) {
        $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($cols)));
        if (strpos($table, 'edu:') === 0) {
            $prog = substr($table, 4);
            $st = $pdo->prepare("SELECT COUNT(*) FROM empeducationalbackground WHERE EmpID = ? AND Program = ?");
            $st->execute([$empId, $prog]);
            if ((int)$st->fetchColumn() > 0) {
                $pdo->prepare("UPDATE empeducationalbackground SET $set WHERE EmpID = ? AND Program = ?")
                    ->execute(array_merge(array_values($cols), [$empId, $prog]));
            } else {
                $names = implode(', ', array_map(fn($c) => "`$c`", array_keys($cols)));
                $ph    = implode(', ', array_fill(0, count($cols), '?'));
                $pdo->prepare("INSERT INTO empeducationalbackground (EmpID, Program, $names) VALUES (?, ?, $ph)")
                    ->execute(array_merge([$empId, $prog], array_values($cols)));
            }
        } else {
            $pdo->prepare("UPDATE `$table` SET $set WHERE EmpID = ?")
                ->execute(array_merge(array_values($cols), [$empId]));
        }
    }

    if (isset($changes['family']['new']) && is_array($changes['family']['new'])) {
        $pdo->prepare('DELETE FROM fdetails WHERE FDetID = ?')->execute([$empId]);
        $ins = $pdo->prepare('INSERT INTO fdetails (FDetID, FName, FAdd, FRel, FContact, FICE) VALUES (?, ?, ?, ?, ?, ?)');
        foreach (pcr_family_from_post($changes['family']['new']) as $r) {
            $ins->execute([$empId, $r['name'], $r['address'], $r['relationship'], $r['contact'], $r['ice']]);
        }
    }
}

/** JSON response helper for the two endpoints. */
function pcr_json(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}
