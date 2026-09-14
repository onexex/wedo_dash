-- ============================================================================
-- Migration: add secondary indexes on the join / lookup columns used by the
-- dashboard (dashboard.php, query/dashboard-drilldown.php), the login absence
-- gate (includes/loginabsencegate.php) and the attendance/leave/OB viewers.
--
-- Before this, NO table had any index beyond its auto-increment primary key —
-- not even employees.EmpID / empdetails.EmpID — so every JOIN was a full scan.
-- The YTD absence query alone took ~1.2 s on the local seed and grows with data.
--
-- Run ONCE per environment (local applied 2026-09-14). Plain CREATE INDEX;
-- MariaDB 10.4 has no IF NOT EXISTS for indexes on all paths, so if a name
-- already exists just skip that statement. Safe to run online — no data change.
-- The .sql extension is blocked from HTTP by .htaccess.
-- ============================================================================

-- employee identity (joined from every table by the EmpID string)
CREATE UNIQUE INDEX ix_employees_empid   ON employees  (EmpID);
CREATE UNIQUE INDEX ix_empdetails_empid  ON empdetails (EmpID);
CREATE INDEX        ix_empdetails_empisid ON empdetails (EmpISID);      -- team scope (immediate superior)

-- attendance: per-employee day lookups + date-range scans
CREATE INDEX ix_attendancelog_emp_day ON attendancelog (EmpID, WSFrom);
CREATE INDEX ix_attendancelog_day     ON attendancelog (WSFrom);

-- work schedule: per-employee weekday lookup, effectivity join
CREATE INDEX ix_workdays_emp_day ON workdays (empid, Day_s);
CREATE INDEX ix_workdays_efid    ON workdays (EFID);

-- leave / OB per-day breakdown tables: "is this employee covered on this date"
CREATE INDEX ix_hleavesbd_emp_range ON hleavesbd (EmpID, LStart, LEnd);
CREATE INDEX ix_obshbd_emp_range    ON obshbd    (EmpID, OBDateFrom, OBDateTo);

-- leave / OB headers: pending-approval lists and on-leave panels
CREATE INDEX ix_hleaves_emp    ON hleaves (EmpID);
CREATE INDEX ix_hleaves_status ON hleaves (LStatus, LStart);
CREATE INDEX ix_obs_emp        ON obs     (EmpID);
CREATE INDEX ix_obs_status     ON obs     (OBStatus);

-- holidays: per-company date lookup
CREATE INDEX ix_holidays_date_comp ON holidays (Hdate, HCompID);

-- access rights gate (one row per employee)
CREATE INDEX ix_accessrights_empid ON accessrights (EmpID);

-- Verify:
-- SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
--  WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME LIKE 'ix\_%' ORDER BY 1,2;
