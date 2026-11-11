-- ============================================================================
-- Migration: Management > Door Control (door.php, includes/door-lib.php)
--
-- Shows whether the office biometric device is online and lets a user release
-- the door's magnetic lock for a few seconds from WeDo, for the days someone's
-- fingerprint won't read. Gated by a new access right `doorctl` — "Door
-- Control" (Access Rights > Management). NOBODY is granted it here: an admin
-- switches it on per person. It opens a real door, so grant it to as few
-- people as possible.
--
-- Every try, opened or not, is written to door_log with who, when and from
-- which address.
--
-- The device's address and Comm Key are NOT in the database: they go in this
-- server's config.local.php ('door' => [...], see config.local.example.php).
--
-- Run ONCE per environment (local, staging, production), BEFORE deploying the
-- code. The ALTER fails on a second run. Until it runs the page stays hidden
-- for everyone.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

ALTER TABLE accessrights
  ADD COLUMN doorctl INT(11) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `door_log` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`     VARCHAR(50)  NOT NULL,                             -- who pressed Unlock
  `Action`    VARCHAR(20)  NOT NULL DEFAULT 'unlock',
  `Result`    VARCHAR(10)  NOT NULL,                             -- ok | failed
  `Detail`    VARCHAR(200) NULL,                                 -- why it failed (device offline, Comm Key rejected ...)
  `Note`      VARCHAR(150) NULL,                                 -- optional reason typed by the person
  `Seconds`   TINYINT(4)   NOT NULL DEFAULT 5,                   -- how long the lock was released
  `IP`        VARCHAR(45)  NOT NULL DEFAULT '',
  `CreatedAt` DATETIME     NOT NULL,                             -- Manila time
  PRIMARY KEY (`id`),
  KEY `ix_door_log_emp` (`EmpID`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT doorctl, COUNT(*) FROM accessrights GROUP BY doorctl;   -- all 1
-- SHOW TABLES LIKE 'door_log';
--
-- Grant one person (or use Access Rights > Management):
-- UPDATE accessrights SET doorctl = 2
--  WHERE EmpID = (SELECT EmpID FROM empdetails WHERE EmpUN = '<username>');
--
-- Who opened the door:
-- SELECT EmpID, Result, Detail, Note, IP, CreatedAt FROM door_log ORDER BY id DESC;
