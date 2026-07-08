-- ============================================================================
-- Migration: add the `dashboard` access right (for dashboard.php overview page)
-- Run ONCE per environment (local already applied 2026-07-07).
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- wd_can()/dashboard.php treat anything other than 2 as no-access (least privilege).
-- The .sql extension is blocked from HTTP by .htaccess, so this file is safe in-repo.
-- ============================================================================

-- 1) Add the column (default OFF so nobody gains access implicitly).
ALTER TABLE accessrights
  ADD COLUMN dashboard INT(11) NOT NULL DEFAULT 1 AFTER notif;

-- 2) Grant to the intended audience out of the box:
--    Super Users / HR (EmpRoleID = 1) and Immediate Superiors / approvers (EmpRoleID = 2).
--    Regular employees (EmpRoleID = 3) stay OFF; grant them individually in the
--    Access Rights manager (Others > Dashboard) if a personal view is wanted.
UPDATE accessrights a
  JOIN empdetails d ON a.EmpID = d.EmpID
  SET a.dashboard = 2
  WHERE d.EmpRoleID IN (1, 2);

-- Verify:
-- SELECT dashboard, COUNT(*) FROM accessrights GROUP BY dashboard;
