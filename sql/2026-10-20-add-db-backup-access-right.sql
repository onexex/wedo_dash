-- ============================================================================
-- Migration: Maintenance > Database Backup (dbbackup.php, includes/db-backup-lib.php)
--
-- Lets a user download a full backup of the WeDo database (every table's
-- structure + data) as a .sql.gz file, from the browser. Gated by a new access
-- right `dbbackup` — "Database Backup (download the whole database)"
-- (Access Rights > Maintenance). NOBODY is granted it here: an admin switches
-- it on per person. The file holds EVERYTHING (salaries, government IDs,
-- password hashes, messages), so grant it to as few people as possible.
--
-- Nothing is stored on the server: the backup is built and streamed straight to
-- the browser. Each download is written to the activity log (dars) with who ran it.
--
-- Run ONCE per environment (local, staging, production). The ALTER fails on a
-- second run. Until it runs the page stays hidden for everyone.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

ALTER TABLE accessrights
  ADD COLUMN dbbackup INT(11) NOT NULL DEFAULT 1;

-- Verify:
-- SELECT dbbackup, COUNT(*) FROM accessrights GROUP BY dbbackup;   -- all 1
--
-- Grant one person (or use Access Rights > Maintenance):
-- UPDATE accessrights SET dbbackup = 2
--  WHERE EmpID = (SELECT EmpID FROM empdetails WHERE EmpUN = '<username>');
--
-- Who downloaded backups:
-- SELECT EMPID, EmpActivity, DarDateTime FROM dars
--  WHERE EmpActivity LIKE 'Database backup%' ORDER BY DarDateTime DESC;
