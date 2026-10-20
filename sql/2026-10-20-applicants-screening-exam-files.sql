-- ============================================================================
-- Migration: Applicant Pool — Screening first, exam result pictures, PH time
--   1) applicant_files: pictures of an applicant's exam result, uploaded by HR
--      on the profile and viewed later (query/applicant-file.php). The images
--      themselves are in uploads/applicants/ (no web access).
--   2) Applicant times were written by the database clock, which runs on UTC
--      on the servers, so they show 8 hours early. The code now writes
--      Manila time; this moves the existing applicant times to Manila time.
--      The shift is worked out from this server's own clock: 8 hours on a
--      UTC server, nothing on a server already on Manila time.
--   3) "New" is no longer a stage: applications start at Screening.
--
-- Run ONCE per environment, right BEFORE deploying the code (applicant times
-- written by the new code before this runs would be moved too). The CREATE
-- TABLE fails on a second run (#1050, table already exists), which stops the
-- script before the time shift can run twice.
-- ============================================================================

CREATE TABLE `applicant_files` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `ApplicantID` INT(11)       NOT NULL,
  `Kind`        VARCHAR(12)   NOT NULL DEFAULT 'exam',
  `FileKey`     VARCHAR(80)   NOT NULL,              -- YYYY/MM/<random>.jpg under uploads/applicants/
  `Caption`     VARCHAR(150)  NULL,
  `Width`       INT(11)       NOT NULL DEFAULT 0,
  `Height`      INT(11)       NOT NULL DEFAULT 0,
  `ByEmpID`     VARCHAR(50)   NULL,
  `CreatedAt`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_apl_files` (`ApplicantID`, `Kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) minutes this server's clock is behind Manila (UTC+8): 480 on UTC, 0 on Manila time
SET @apl_shift = 480 - ROUND(TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) / 60);

UPDATE applicants SET
  CreatedAt = CreatedAt + INTERVAL @apl_shift MINUTE,
  StageAt   = StageAt   + INTERVAL @apl_shift MINUTE,
  ConsentAt = ConsentAt + INTERVAL @apl_shift MINUTE,
  HiredAt   = HiredAt   + INTERVAL @apl_shift MINUTE;

UPDATE applicant_events SET CreatedAt = CreatedAt + INTERVAL @apl_shift MINUTE;

-- 3) Screening is the first stage
UPDATE applicants SET Stage = 'screening' WHERE Stage = 'new';
ALTER TABLE applicants ALTER COLUMN Stage SET DEFAULT 'screening';

-- Verify:
-- SHOW TABLES LIKE 'applicant_files';
-- SELECT Stage, COUNT(*) FROM applicants GROUP BY Stage;          -- no 'new'
-- SELECT id, CreatedAt FROM applicants ORDER BY id DESC LIMIT 5;   -- Manila time now
