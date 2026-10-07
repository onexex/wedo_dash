-- ============================================================================
-- Keep the WeDo mobile app signed in (includes/app-remember.php).
--
-- One row per phone that signed in to the app: a 30-day token (only its
-- SHA-256 is stored), extended each time the app uses it. Separate from the
-- website's one-per-employee "remember me" (empdetails.remember_hash), so
-- signing in on the website does not sign the phone out. Signing out of the
-- app deletes the row; expired rows are cleaned up on the next app login.
--
-- Safe to run more than once. Needed on local, staging and production.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `app_remember` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `EmpID`      VARCHAR(50) NOT NULL,
  `token_hash` CHAR(64)    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,   -- sha256 hex of the cookie value
  `expires_at` DATETIME    NOT NULL,
  `created_at` DATETIME    NOT NULL,
  `last_used`  DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_app_remember_token` (`token_hash`),
  KEY `ix_app_remember_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
