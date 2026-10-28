-- ============================================================================
-- Migration: "Create Corner polls" access right (Access Rights > Others)
--
-- Who may start a poll in the Corner (corner.php, query/corner-polls.php) is
-- now switched per person: super users always can, everyone else needs the
-- new `cpoll` right. Voting stays open to every employee.
--
-- The three accounts that could already start polls (the named Corner
-- posters) keep that ability: they are granted the right below.
-- Until this runs, polls fall back to the old rule (Corner posters only).
--
-- Run ONCE per environment (local, staging, production). The ALTER fails on a
-- second run.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

ALTER TABLE accessrights
  ADD COLUMN cpoll INT(11) NOT NULL DEFAULT 1;

UPDATE accessrights SET cpoll = 2
 WHERE EmpID IN ('WeDoinc-002', 'WeDoinc-003', 'WeDoinc-006');

-- Verify:
-- SELECT EmpID, cpoll FROM accessrights WHERE cpoll = 2;
