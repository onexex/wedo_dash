<?php
/**
 * Home "My year so far" summary.
 *
 * getYearSummary() counts, for one employee over a period (default: Jan 1 of
 * the year → today):
 *   - timedin   : days with a time-in (attendancelog); a day with approved
 *                 leave counts only its non-leave part (half-day leave → 0.5)
 *   - ob        : approved OB days (obshbd — the per-day table) with no time-in;
 *                 a day with a time-in already counts under timedin, so its OB is 0
 *   - regular   : Regular holidays (Htype 1) on a scheduled work day, Jan 1 – Dec 31
 *   - special   : Special holidays (Htype 2) on a scheduled work day, Jan 1 – Dec 31
 *   - leave     : approved leave in days (LDuration 600 = 1 day, 300 = 0.5)
 *   - leaveUnpaid : the part of leave that used no credits (non-credit type such
 *                 as Unpaid, or status 8 approved without pay)
 *   - scheduled / accounted / unaccounted : scheduled work days up to today,
 *     how many are covered by a time-in, OB, leave or holiday (each day once),
 *     and how many before today are not covered with nothing filed at all.
 *   - absent : days before today not covered, with a leave/OB filed that was
 *     disapproved or cancelled — a known absence, not an unaccounted gap.
 *     absentWhy breaks it down, e.g. ['leave disapproved' => 1].
 *   - pending : same, but the filing still awaits IS/HR approval.
 *   - scheduledYear : scheduled work days for the whole calendar year
 *     (Jan 1 – Dec 31), the denominator the card shows.
 *   - conflicts : dates with a time-in AND an approved whole-day leave. A
 *     half-day leave or an OB alongside a time-in is normal; a whole-day leave
 *     alongside one is a data error for HR to fix (cancel the leave or remove
 *     the time-in). Such a day counts 0 under timedin, 1 under leave.
 *
 * "Approved" = HR-approved or later: status 4, 8 (approved w/o pay), 9, 10.
 * Pending (1, 2), disapproved (3, 5, 6) and cancelled (7) never count.
 *
 * "Scheduled work day" is the same test as includes/loginabsencegate.php:
 * a workdays row for that day name with a real schedule (WorkSchedID <> 0)
 * whose schedeffectivity range covers the date.
 *
 * 'detail' holds, per tile/badge, the rows its drill-down modal lists
 * (newest first, already formatted for display).
 *
 * FAIL-OPEN: any error returns null and the card shows dashes.
 */
if (!function_exists('getYearSummary')) {
    function getYearSummary(PDO $pdo, $empID, $compID, $from, $to)
    {
        try {
            $APPROVED = '4,8,9,10';
            $today    = date('Y-m-d');
            $out = array(
                'timedin' => 0.0, 'ob' => 0.0,'regular' => 0, 'special' => 0,
                'leave' => 0.0, 'leaveUnpaid' => 0.0, 'leaveUpcoming' => 0.0,
                'scheduled' => 0, 'scheduledYear' => 0, 'accounted' => 0, 'unaccounted' => 0, 'absent' => 0, 'absentWhy' => array(), 'pending' => 0,
                'conflicts' => array(),
            );

            // Schedule rows for this employee (a handful), evaluated per date in PHP.
            $st = $pdo->prepare(
                "SELECT wd.Day_s, se.dfrom, se.dto
                 FROM workdays wd
                 INNER JOIN workschedule ws ON wd.SchedTime = ws.WorkSchedID
                 INNER JOIN schedeffectivity se ON wd.EFID = se.efids
                 WHERE wd.empid = :id AND ws.WorkSchedID <> 0"
            );
            $st->execute(array(':id' => $empID));
            $sched = array();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $sched[$r['Day_s']][] = array($r['dfrom'], $r['dto']);
            }
            $isScheduled = function ($ds) use ($sched) {
                $day = date('l', strtotime($ds));
                if (empty($sched[$day])) { return false; }
                foreach ($sched[$day] as $rg) {
                    if ($rg[1] !== null && $ds >= $rg[0] && $ds <= $rg[1]) { return true; }
                }
                return false;
            };

            $fd   = function ($d) { return date('M j', strtotime($d)); };
            $day  = function ($d) { return date('D', strtotime($d)); };
            $tm   = function ($dt) { return ($dt && strtotime($dt) > 0) ? date('g:i A', strtotime($dt)) : '—'; };
            $days = function ($v) { return ($v == floor($v)) ? (string)(int)$v : number_format($v, 1); };

            // Days timed in (first in / last out per day; a day can hold several logs).
            $st = $pdo->prepare(
                "SELECT WSFrom, MIN(TimeIn) AS tin, MAX(TimeOut) AS tout, MAX(MinsLack) AS late
                 FROM attendancelog
                 WHERE EmpID = :id AND TimeIn IS NOT NULL AND WSFrom BETWEEN :f AND :t
                 GROUP BY WSFrom ORDER BY WSFrom DESC"
            );
            $st->execute(array(':id' => $empID, ':f' => $from, ':t' => $to));
            $att = array();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $att[$r['WSFrom']] = $r; }
            // $out['timedin'] is summed below, once leave is known (half-day leave → 0.5).

            // Approved OB days (one obshbd row per scheduled day).
            $st = $pdo->prepare(
                "SELECT OBDateFrom, OBTimeFrom, OBTimeTo, OBDuration, OBPurpose FROM obshbd
                 WHERE EmpID = :id AND OBStatus IN ($APPROVED) AND OBDateFrom BETWEEN :f AND :t
                 ORDER BY OBDateFrom DESC, OBTimeFrom"
            );
            $st->execute(array(':id' => $empID, ':f' => $from, ':t' => $to));
            $ob = array(); $obRaw = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($obRaw as $r) { $ob[$r['OBDateFrom']] = true; }
            // $out['ob'] is summed below, once time-in and leave are known.

            // Approved leave (one hleavesbd row per day; LDuration in minutes).
            // For the current year, rows after today are the "upcoming" ones.
            $yearEnd = substr($to, 0, 4) . '-12-31';
            $upTo    = ($to === $today && $yearEnd > $today) ? $yearEnd : $to;
            $st = $pdo->prepare(
                "SELECT h.LStart, h.LDuration, h.am_pm, h.LType, h.LStatus, COALESCE(l.LeaveDesc, 'Leave') AS LDesc
                 FROM hleavesbd h LEFT JOIN leaves l ON l.LeaveID = h.LType
                 WHERE h.EmpID = :id AND h.LStatus IN ($APPROVED) AND h.LStart BETWEEN :f AND :t
                 ORDER BY h.LStart DESC"
            );
            $st->execute(array(':id' => $empID, ':f' => $from, ':t' => $upTo));
            $lvMins = array(); $lvType = array(); $lvRows = array(); $lvUpRows = array();
            // Unpaid = uses no leave credits: a type that never deducts (only Vacation 22,
            // Medical 30, Force 38 and Emergency 24 do — see Query-UpdateApp.php), or
            // status 8 (approved without pay because credits ran out).
            $CREDIT_TYPES = array(22, 30, 38, 24);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $d = $r['LStart']; $mins = (float)$r['LDuration'];
                $part = ($mins >= 600) ? 'Whole day'
                      : 'Half day' . ($r['am_pm'] === 'half_day_am' ? ' (AM)' : ($r['am_pm'] === 'half_day_pm' ? ' (PM)' : ''));
                $unpaid = (int)$r['LStatus'] === 8 || !in_array((int)$r['LType'], $CREDIT_TYPES);
                $row = array($fd($d), $day($d), $r['LDesc'], $part, $days(min(600, $mins) / 600), $unpaid ? 'Unpaid' : 'Paid');
                if ($d > $to) {
                    $out['leaveUpcoming'] += min(600, $mins) / 600;
                    $lvUpRows[] = $row;
                    continue;
                }
                $lvMins[$d] = (isset($lvMins[$d]) ? $lvMins[$d] : 0) + $mins;
                $lvType[$d] = $r['LDesc'];
                if ($unpaid) { $out['leaveUnpaid'] += min(600, $mins) / 600; }
                $lvRows[] = $row;
            }
            foreach ($lvMins as $mins) { $out['leave'] += min(600, $mins) / 600; }

            // Leave / OB filed for a day but not approved (pending, disapproved,
            // cancelled) — such a day counts under absent/pending, with the status/reason.
            $NOTE_ST = array(1 => 'Pending IS approval', 2 => 'Pending HR approval', 3 => 'Disapproved by IS',
                             5 => 'Disapproved by HR', 6 => 'Disapproved', 7 => 'Cancelled');
            $noteReason = function ($st, $isR, $hrR) {
                $r = trim((string)($st === 3 ? $isR : ($hrR !== null && trim($hrR) !== '' ? $hrR : $isR)));
                return $r !== '' ? ': ' . $r : '';
            };
            $notes = array(); $notePending = array(); $noteWhy = array(); // noteWhy: first 'leave disapproved' etc. per day
            $st = $pdo->prepare(
                "SELECT h.LStart AS d, h.LStatus AS st, COALESCE(l.LeaveDesc, 'Leave') AS LDesc,
                        hl.LISReason AS isr, hl.LHRReason AS hrr
                 FROM hleavesbd h
                 LEFT JOIN leaves l ON l.LeaveID = h.LType
                 LEFT JOIN hleaves hl ON hl.LeaveID = h.FID
                 WHERE h.EmpID = :id AND h.LStatus IN (1,2,3,5,6,7) AND h.LStart BETWEEN :f AND :t"
            );
            $st->execute(array(':id' => $empID, ':f' => $from, ':t' => $to));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $s = (int)$r['st'];
                if ($s <= 2) { $notePending[$r['d']] = true; }
                elseif (!isset($noteWhy[$r['d']])) { $noteWhy[$r['d']] = 'leave ' . ($s === 7 ? 'cancelled' : 'disapproved'); }
                $what = stripos($r['LDesc'], 'leave') === false ? $r['LDesc'] . ' leave' : $r['LDesc'];
                $notes[$r['d']][] = $what . ' — ' . $NOTE_ST[$s] . $noteReason($s, $r['isr'], $r['hrr']);
            }
            $st = $pdo->prepare(
                "SELECT OBDateFrom AS d, OBStatus AS st, OBTimeFrom, OBTimeTo, OBISReason AS isr, OBHRReason AS hrr
                 FROM obshbd
                 WHERE EmpID = :id AND OBStatus IN (1,2,3,5,6,7) AND OBDateFrom BETWEEN :f AND :t"
            );
            $st->execute(array(':id' => $empID, ':f' => $from, ':t' => $to));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $s = (int)$r['st'];
                if ($s <= 2) { $notePending[$r['d']] = true; }
                elseif (!isset($noteWhy[$r['d']])) { $noteWhy[$r['d']] = 'OB ' . ($s === 7 ? 'cancelled' : 'disapproved'); }
                $notes[$r['d']][] = 'OB ' . $r['OBTimeFrom'] . '–' . $r['OBTimeTo'] . ' — ' . $NOTE_ST[$s]
                                  . $noteReason($s, $r['isr'], $r['hrr']);
            }

            // Company holidays for the whole calendar year, keyed by date.
            $yStart = substr($to, 0, 4) . '-01-01';
            $st = $pdo->prepare(
                "SELECT Hdate, MIN(Htype) AS Htype, MIN(Hdescription) AS Hdesc FROM holidays
                 WHERE HCompID = :c AND Hdate BETWEEN :f AND :t GROUP BY Hdate"
            );
            $st->execute(array(':c' => $compID, ':f' => $yStart, ':t' => $yearEnd));
            $hol = array();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $hol[$r['Hdate']] = $r; }

            // Walk the year's scheduled days once (Dec 31 back to Jan 1, newest first):
            // scheduledYear + holidays over the full year, accounted/unaccounted up to $to.
            $regRows = array(); $spRows = array(); $gapRows = array(); $absRows = array(); $penRows = array();
            for ($ts = strtotime($yearEnd), $end = strtotime($yStart); $ts >= $end; $ts = strtotime('-1 day', $ts)) {
                $ds = date('Y-m-d', $ts);
                if (!$isScheduled($ds)) { continue; }
                $out['scheduledYear']++;
                if (isset($hol[$ds])) {
                    $hr = array($fd($ds), $day($ds), $hol[$ds]['Hdesc'] . ($ds > $to ? ' (upcoming)' : ''));
                    if ((int)$hol[$ds]['Htype'] === 1) { $out['regular']++; $regRows[] = $hr; }
                    else { $out['special']++; $spRows[] = $hr; }
                }
                if ($ds < $from || $ds > $to) { continue; }
                $out['scheduled']++;
                if (isset($att[$ds]) || isset($ob[$ds]) || isset($lvMins[$ds]) || isset($hol[$ds])) {
                    $out['accounted']++;
                } elseif ($ds < $today) {
                    // today is still open, never counted as a gap
                    if (isset($notes[$ds])) {
                        // Anything still awaiting approval keeps the day pending, not absent.
                        $k = isset($notePending[$ds]) ? 'pending' : 'absent';
                        $out[$k]++;
                        $row = array($fd($ds), $day($ds), implode('; ', array_unique($notes[$ds])));
                        if ($k === 'pending') { $penRows[] = $row; }
                        else {
                            $absRows[] = $row;
                            $w = $noteWhy[$ds];
                            $out['absentWhy'][$w] = (isset($out['absentWhy'][$w]) ? $out['absentWhy'][$w] : 0) + 1;
                        }
                    } else {
                        $out['unaccounted']++;
                        $gapRows[] = array($fd($ds), $day($ds));
                    }
                }
            }

            // Time-in rows, tagged with whatever else sits on the same day.
            $attRows = array(); $cfRows = array();
            foreach ($att as $d => $r) {
                $also = array();
                if (isset($ob[$d]))     { $also[] = 'OB'; }
                if (isset($lvMins[$d])) { $also[] = ($lvMins[$d] >= 600 ? 'Whole-day leave' : 'Half-day leave'); }
                if (isset($hol[$d]))    { $also[] = ((int)$hol[$d]['Htype'] === 1 ? 'Regular' : 'Special') . ' holiday'; }
                // A day is 1 in total: the leave portion goes to Leave, the rest to Days timed in.
                $worked = 1 - (isset($lvMins[$d]) ? min(600, $lvMins[$d]) / 600 : 0);
                $out['timedin'] += $worked;
                $late = (float)$r['late'];
                $attRows[] = array($fd($d), $day($d), $tm($r['tin']), $tm($r['tout']),
                    $late > 0 ? round($late) . ' min' : '—', $days($worked), implode(', ', $also));
                // Timed in on a whole-day approved leave: one of the two records is wrong.
                if (isset($lvMins[$d]) && $lvMins[$d] >= 600) {
                    $out['conflicts'][] = $d;
                    $cfRows[] = array($fd($d), $day($d), $tm($r['tin']), $tm($r['tout']), $lvType[$d]);
                }
            }
            $out['conflicts'] = array_reverse($out['conflicts']); // oldest first for the tooltip

            // OB counts only on days with no time-in (a time-in already counts the
            // day), and only the part not taken by leave. Several OBs on one day → once.
            $obPart = array();
            foreach (array_keys($ob) as $d) {
                $obPart[$d] = isset($att[$d]) ? 0 : 1 - (isset($lvMins[$d]) ? min(600, $lvMins[$d]) / 600 : 0);
                $out['ob'] += $obPart[$d];
            }
            $obRows = array(); $obSeen = array();
            foreach ($obRaw as $r) {
                $d = $r['OBDateFrom'];
                $cnt = isset($obSeen[$d]) ? '—' : (isset($att[$d]) ? '0 (timed in)' : $days($obPart[$d]));
                $obSeen[$d] = true;
                $obRows[] = array($fd($d), $day($d), $r['OBTimeFrom'] . '–' . $r['OBTimeTo'],
                    $days((float)$r['OBDuration']), $cnt, $r['OBPurpose']);
            }

            $cDate = array('Date', 'Day');
            $cLeave = array_merge($cDate, array('Type', 'Portion', 'Days', 'Pay'));
            $out['detail'] = array(
                'timedin' => array(array('title' => 'Days timed in', 'rows' => $attRows,
                    'cols' => array_merge($cDate, array('Time in', 'Time out', 'Late', 'Counted', 'Also on this day')))),
                'ob' => array(array('title' => 'Approved official business', 'rows' => $obRows,
                    'cols' => array_merge($cDate, array('Time', 'Hours', 'Counts as', 'Purpose')))),
                'regular' => array(array('title' => 'Regular holidays on scheduled days', 'rows' => $regRows,
                    'cols' => array_merge($cDate, array('Holiday')))),
                'special' => array(array('title' => 'Special holidays on scheduled days', 'rows' => $spRows,
                    'cols' => array_merge($cDate, array('Holiday')))),
                'leave' => array(
                    array('title' => 'Leave taken', 'rows' => $lvRows, 'cols' => $cLeave),
                    array('title' => 'Upcoming (approved)', 'rows' => $lvUpRows, 'cols' => $cLeave, 'hideEmpty' => true),
                ),
                'unaccounted' => array(array('title' => 'Scheduled days with no time-in, OB, leave or holiday — nothing filed',
                    'rows' => $gapRows, 'cols' => $cDate)),
                'absent' => array(array('title' => 'Absent — leave/OB filed but disapproved or cancelled, no time-in',
                    'rows' => $absRows, 'cols' => array_merge($cDate, array('Filed')))),
                'pending' => array(array('title' => 'No time-in — leave/OB filed, still awaiting approval',
                    'rows' => $penRows, 'cols' => array_merge($cDate, array('Filed')))),
                'conflicts' => array(array('title' => 'Timed in on a whole-day approved leave — ask HR to cancel the leave or remove the time-in',
                    'rows' => $cfRows, 'cols' => array_merge($cDate, array('Time in', 'Time out', 'Leave')))),
            );

            return $out;
        } catch (Exception $e) {
            error_log("home-yearsummary: " . $e->getMessage());
            return null;
        }
    }
}
