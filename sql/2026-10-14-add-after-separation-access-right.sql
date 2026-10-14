-- ============================================================================
-- Migration: "Sign in after resignation / termination" access right
--
-- Resigned (status 2) and Terminated (status 4) employees can no longer use
-- WeDo — website or app (includes/account-gate.php). This adds the exception:
-- the access right `aftersep` (Access Rights > Others) lets one person keep
-- signing in after their status changes, e.g. someone who still prepares
-- payroll. NOBODY is granted it here: an admin switches it on per person.
--
-- Run ONCE per environment (local, staging, production), BEFORE deploying the
-- code if anyone who must keep access is already marked Resigned/Terminated —
-- until it runs there are no exceptions. The ALTER fails on a second run.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

ALTER TABLE accessrights
  ADD COLUMN aftersep INT(11) NOT NULL DEFAULT 1;

-- Verify:
-- SELECT aftersep, COUNT(*) FROM accessrights GROUP BY aftersep;   -- all 1
--
-- Who would be blocked once the code is deployed (status Resigned/Terminated
-- but still holding a username):
-- SELECT d.EmpUN, e.EmpFN, e.EmpLN, s.StatusEmpDesc
--   FROM empdetails d JOIN employees e ON e.EmpID = d.EmpID
--   LEFT JOIN estatus s ON s.ID = e.EmpStatusID
--  WHERE e.EmpStatusID IN (2, 4) ORDER BY e.EmpLN;
--
-- Grant one person (or use Access Rights > Others):
-- UPDATE accessrights SET aftersep = 2
--  WHERE EmpID = (SELECT EmpID FROM empdetails WHERE EmpUN = '<username>');
