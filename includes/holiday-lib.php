<?php
/* =============================================================================
 * includes/holiday-lib.php — Maintenance > Holidays.
 *
 * Holidays are per company (holidays.HCompID = the signed-in user's CompID,
 * the same scope the Corner calendar reads) and drive holiday pay in payroll.
 * Htype 1 = Regular Holiday, 2 = Special Holiday.
 *
 * Who may manage them: the `hldy` access right (accessrights.hldy = 2), the
 * same switch that shows "Holiday Logger" in the Maintenance menu.
 *
 * Used by: includes/holiday.php (the page, via maintenance.php?holiday) and
 *          query/holiday-action.php (add / update / delete, JSON).
 * ========================================================================== */

const HLD_TYPES = [1 => 'Regular Holiday', 2 => 'Special Holiday'];
const HLD_DESC_MAX = 50;   // holidays.Hdescription varchar(50)

/** Whether $me holds the `hldy` right. */
function hld_can(PDO $pdo, string $me): bool
{
    if ($me === '' || $me === '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT hldy FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $me]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;
    }
}

/** Per-session token for the add / update / delete requests. */
function hld_csrf_token(): string
{
    if (empty($_SESSION['hld_csrf'])) { $_SESSION['hld_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['hld_csrf'];
}

function hld_today(): DateTimeImmutable
{
    return new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
}

/** Every holiday of one company, oldest first: [['id','date','type','desc'], ...] */
function hld_all(PDO $pdo, string $comp): array
{
    $st = $pdo->prepare("SELECT SID, Hdate, Htype, Hdescription FROM holidays
                          WHERE HCompID = :c ORDER BY Hdate ASC, SID ASC");
    $st->execute([':c' => $comp]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['SID'], 'date' => (string) $r['Hdate'],
                  'type' => (int) $r['Htype'] === 2 ? 2 : 1, 'desc' => trim((string) $r['Hdescription'])];
    }
    return $out;
}

/**
 * The answers the page leads with, from hld_all() rows:
 *   today  => the holiday on $today, or null
 *   next   => the first holiday after $today (+ 'days' until it), or null
 *   year   => ['total', 'regular', 'special', 'left'] for $today's year
 *   years  => every year that has a holiday (descending), always including this and next year
 */
function hld_answers(array $rows, ?DateTimeImmutable $today = null): array
{
    $today = $today ?? hld_today();
    $t = $today->format('Y-m-d');
    $y = (int) $today->format('Y');
    $out = ['today' => null, 'next' => null, 'year' => ['total' => 0, 'regular' => 0, 'special' => 0, 'left' => 0], 'years' => []];
    $years = [$y => true, $y + 1 => true];
    foreach ($rows as $r) {
        $years[(int) substr($r['date'], 0, 4)] = true;
        if ($r['date'] === $t && $out['today'] === null) { $out['today'] = $r; }
        if ($r['date'] > $t && $out['next'] === null) {
            $days = (int) $today->diff(new DateTimeImmutable($r['date'], $today->getTimezone()))->days;
            $out['next'] = $r + ['days' => $days];
        }
        if ((int) substr($r['date'], 0, 4) === $y) {
            $out['year']['total']++;
            $out['year'][$r['type'] === 2 ? 'special' : 'regular']++;
            if ($r['date'] > $t) { $out['year']['left']++; }
        }
    }
    $out['years'] = array_keys($years);
    rsort($out['years']);
    return $out;
}

/** Check the form fields; ['ok' => true, 'date', 'desc', 'type'] or ['ok' => false, 'msg']. */
function hld_validate(array $in): array
{
    $date = trim((string) ($in['hldate'] ?? ''));
    $desc = trim(preg_replace('/\s+/', ' ', (string) ($in['desc'] ?? '')));
    $type = (int) ($in['hldtype'] ?? 0);
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date || (int) $d->format('Y') < 2000 || (int) $d->format('Y') > 2100) {
        return ['ok' => false, 'msg' => 'Pick the holiday’s date.'];
    }
    if ($desc === '') { return ['ok' => false, 'msg' => 'Give the holiday a name (e.g. Independence Day).']; }
    if (mb_strlen($desc) > HLD_DESC_MAX) { return ['ok' => false, 'msg' => 'The name is too long (' . HLD_DESC_MAX . ' characters at most).']; }
    if (!isset(HLD_TYPES[$type])) { return ['ok' => false, 'msg' => 'Choose Regular or Special Holiday.']; }
    return ['ok' => true, 'date' => $date, 'desc' => $desc, 'type' => $type];
}

/** Another holiday of $comp on $date (not row $id)? Its name, or null. */
function hld_clash(PDO $pdo, string $comp, string $date, int $id = 0): ?string
{
    $st = $pdo->prepare("SELECT Hdescription FROM holidays WHERE HCompID = :c AND Hdate = :d AND SID <> :id LIMIT 1");
    $st->execute([':c' => $comp, ':d' => $date, ':id' => $id]);
    $v = $st->fetchColumn();
    return $v === false ? null : trim((string) $v);
}

/** Add ($id = 0) or update one holiday of $comp. ['ok' => true, 'id'] or ['ok' => false, 'msg']. */
function hld_save(PDO $pdo, string $comp, string $me, array $in, int $id = 0): array
{
    $v = hld_validate($in);
    if (!$v['ok']) { return $v; }
    if ($id > 0 && !hld_find($pdo, $comp, $id)) { return ['ok' => false, 'msg' => 'That holiday is no longer in the list.']; }
    $clash = hld_clash($pdo, $comp, $v['date'], $id);
    if ($clash !== null) {
        return ['ok' => false, 'msg' => date('M j, Y', strtotime($v['date'])) . ' already has a holiday: ' . $clash . '. Edit that one instead.'];
    }
    if ($id > 0) {
        $pdo->prepare("UPDATE holidays SET Hdate = :d, Htype = :t, Hdescription = :n WHERE SID = :id AND HCompID = :c")
            ->execute([':d' => $v['date'], ':t' => $v['type'], ':n' => $v['desc'], ':id' => $id, ':c' => $comp]);
        hld_log($pdo, $me, 'Holiday updated: ' . $v['desc'] . ' (' . $v['date'] . ', ' . HLD_TYPES[$v['type']] . ')');
    } else {
        $pdo->prepare("INSERT INTO holidays (Hdate, Htype, Hdescription, HCompID, HOffsetEmpID) VALUES (:d, :t, :n, :c, :me)")
            ->execute([':d' => $v['date'], ':t' => $v['type'], ':n' => $v['desc'], ':c' => $comp, ':me' => $me]);
        $id = (int) $pdo->lastInsertId();
        hld_log($pdo, $me, 'Holiday added: ' . $v['desc'] . ' (' . $v['date'] . ', ' . HLD_TYPES[$v['type']] . ')');
    }
    return ['ok' => true, 'id' => $id];
}

/** One holiday of $comp, or null. */
function hld_find(PDO $pdo, string $comp, int $id): ?array
{
    $st = $pdo->prepare("SELECT SID, Hdate, Htype, Hdescription FROM holidays WHERE SID = :id AND HCompID = :c");
    $st->execute([':id' => $id, ':c' => $comp]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? ['id' => (int) $r['SID'], 'date' => (string) $r['Hdate'], 'type' => (int) $r['Htype'] === 2 ? 2 : 1,
                 'desc' => trim((string) $r['Hdescription'])] : null;
}

/** Delete one holiday of $comp. ['ok' => true] or ['ok' => false, 'msg']. */
function hld_delete(PDO $pdo, string $comp, string $me, int $id): array
{
    $r = hld_find($pdo, $comp, $id);
    if (!$r) { return ['ok' => false, 'msg' => 'That holiday is no longer in the list.']; }
    $pdo->prepare("DELETE FROM holidays WHERE SID = :id AND HCompID = :c")->execute([':id' => $id, ':c' => $comp]);
    hld_log($pdo, $me, 'Holiday deleted: ' . $r['desc'] . ' (' . $r['date'] . ')');
    return ['ok' => true];
}

/** One line in the activity log (dars). */
function hld_log(PDO $pdo, string $me, string $activity): void
{
    try {
        $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :act, :at)")
            ->execute([':id' => $me, ':act' => mb_substr($activity, 0, 250),
                       ':at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s')]);
    } catch (Throwable $e) { /* the log never blocks the change */ }
}
