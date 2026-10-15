-- ============================================================================
-- Migration: protection against bots (includes/bot-guard.php)
--   1. Sign-in throttling (query/query-login.php): 5 wrong passwords for a
--      username, or 20 failed sign-ins from one address, within 15 minutes
--      lock it for the rest of those 15 minutes.
--   2. Apply page (includes/applicants-lib.php): the same resume file sent
--      again within 30 days is refused (SHA-1 of the file).
-- Run ONCE per environment, BEFORE deploying the code. The ALTER fails on a
-- second run (column already exists). Until it runs, sign-in works without
-- throttling and the resume check is skipped.
-- ============================================================================

-- 1) One row per sign-in attempt: the typed username (lower-case) and the
--    visitor's address — never the password. Rows older than a day are
--    cleaned up automatically.
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `Username`  VARCHAR(150) NOT NULL,
  `IP`        VARCHAR(45)  NOT NULL,
  `Success`   TINYINT(1)   NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_login_user` (`Username`, `CreatedAt`),
  KEY `ix_login_ip` (`IP`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Fingerprint of each applicant's resume file.
ALTER TABLE applicants
  ADD COLUMN ResumeHash CHAR(40) NULL AFTER ResumeName,
  ADD KEY ix_apl_resume_hash (ResumeHash);

-- Verify:
-- SHOW TABLES LIKE 'login_attempts';
-- SHOW COLUMNS FROM applicants LIKE 'ResumeHash';
