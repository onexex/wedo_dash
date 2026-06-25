<?php
/**
 * Login absence gate.
 *
 * Returns the list of scheduled work days (Y-m-d) on which an employee has an
 * UNACCOUNTED absence: a scheduled, non-holiday work day with no attendance,
 * no leave on file, and no OB on file. If the returned array is non-empty the
 * employee should be blocked from logging in until their immediate superior
 * files a Leave or OB covering the date.
 *
 * Rules (confirmed with the business):
 *  - Lookback: every scheduled work day within the last 15 days (a fixed
 *    rolling window, NOT anchored to last attendance, so an absence before a
 *    later attendance day is still caught) up to today.
 *  - A PENDING filing is enough to clear a day (leave LStatus IN (1,2,4,9),
 *    OB OBStatus IN (1,2,4)); only disapproved/deleted statuses don't count.
 *  - One-day grace: the single most recent scheduled work day before today is
 *    exempt; only an absence OLDER than that blocks.
 *  - Caller decides who to run this for. Intended for regular employees only
 *    (UserType / EmpRoleID == 3); admins (1) and supervisors (2) are exempt so
 *    they can still log in to file on a subordinate's behalf.
 *
 * FAIL-OPEN: any error returns an empty array (never block due to a bug).
 */
if (!function_exists('getUnaccountedAbsences')) {
    function getUnaccountedAbsences(PDO $pdo, $empID, $compID)
    {
        try {
            date_default_timezone_set("Asia/Manila");
            $WINDOW_DAYS = 15;

            // Scan a FIXED recent window (the last $WINDOW_DAYS days, ending today),
            // independent of last attendance. This is essential: anchoring on the
            // last attendance would hide an earlier absence whenever a LATER day has
            // attendance (e.g. absent Monday but present Tuesday). Dates outside the
            // employee's schedule effectivity are skipped by the work-day check
            // below, so a new hire is never blocked for dates before they started.
            $today = new DateTime('today');
            $start = (clone $today)->modify('-' . $WINDOW_DAYS . ' days');
            $floor = clone $start; // lower bound for the grace-day probe

            // Prepared statements reused across the date loop.
            $schedSt = $pdo->prepare(
                "SELECT 1 FROM workdays wd
                 INNER JOIN workschedule ws ON wd.SchedTime = ws.WorkSchedID
                 INNER JOIN schedeffectivity se ON wd.EFID = se.efids
                 WHERE wd.empid = :id AND wd.Day_s = :dayname
                   AND :d BETWEEN se.dfrom AND se.dto
                   AND ws.WorkSchedID <> 0
                 LIMIT 1"
            );
            $holSt = $pdo->prepare("SELECT 1 FROM holidays WHERE Hdate = :d AND HCompID = :cmp LIMIT 1");
            $attSt = $pdo->prepare("SELECT 1 FROM attendancelog WHERE EmpID = :id AND WSFrom = :d LIMIT 1");
            $lvSt  = $pdo->prepare("SELECT 1 FROM hleavesbd WHERE EmpID = :id AND :d BETWEEN LStart AND LEnd AND LStatus IN (1,2,4,8,9) LIMIT 1");
            $obSt  = $pdo->prepare("SELECT 1 FROM obshbd   WHERE EmpID = :id AND :d BETWEEN OBDateFrom AND OBDateTo AND OBStatus IN (1,2,4) LIMIT 1");

            // True only for a scheduled, non-holiday work day for this employee.
            $isWorkDay = function ($ds) use ($schedSt, $holSt, $empID, $compID) {
                $dayname = date('l', strtotime($ds)); // e.g. "Monday"
                $schedSt->execute(array(':id' => $empID, ':dayname' => $dayname, ':d' => $ds));
                if (!$schedSt->fetchColumn()) {
                    return false; // rest day / not scheduled
                }
                $holSt->execute(array(':d' => $ds, ':cmp' => $compID));
                if ($holSt->fetchColumn()) {
                    return false; // holiday
                }
                return true;
            };

            // B. Grace day = most recent scheduled work day strictly before today.
            $graceDay = null;
            $probe = (clone $today)->modify('-1 day');
            while ($probe >= $floor) {
                $ds = $probe->format('Y-m-d');
                if ($isWorkDay($ds)) {
                    $graceDay = $ds;
                    break;
                }
                $probe->modify('-1 day');
            }

            // C. Scan candidate days oldest -> day before today; skip the grace day.
            $blocking = array();
            $cur = clone $start;
            while ($cur < $today) {
                $ds = $cur->format('Y-m-d');
                if ($ds !== $graceDay && $isWorkDay($ds)) {
                    $attSt->execute(array(':id' => $empID, ':d' => $ds));
                    if (!$attSt->fetchColumn()) {
                        $lvSt->execute(array(':id' => $empID, ':d' => $ds));
                        $obSt->execute(array(':id' => $empID, ':d' => $ds));
                        if (!$lvSt->fetchColumn() && !$obSt->fetchColumn()) {
                            $blocking[] = $ds; // unaccounted absence
                        }
                    }
                }
                $cur->modify('+1 day');
            }

            return $blocking;
        } catch (Exception $e) {
            error_log("loginabsencegate: " . $e->getMessage());
            return array(); // fail open
        }
    }
}
