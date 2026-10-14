-- ============================================================================
-- Migration: force a password change after a temporary / default password.
--   includes/account-gate.php (wd_password_gate), change-password.php,
--   query/force-password.php; the flag is set by Applicants > Hire
--   (includes/applicants-lib.php), Enroll Employee
--   (query/Query-InsertEmployeeInfo.php) and the admin password reset
--   (query/query-resetpassword.php), and cleared by any password change.
-- Run ONCE per environment, BEFORE deploying the code. The ALTER fails on a
-- second run (column already exists). Until it runs nobody is forced.
-- ============================================================================

ALTER TABLE empdetails
  ADD COLUMN must_change_pw TINYINT(1) NOT NULL DEFAULT 0;

-- Verify:
-- SELECT must_change_pw, COUNT(*) FROM empdetails GROUP BY must_change_pw;
