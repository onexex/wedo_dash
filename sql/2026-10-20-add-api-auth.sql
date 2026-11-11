-- ============================================================================
-- Migration: sign-in API for other apps (api/auth-login.php, includes/api-auth-lib.php)
--   One row per request to the API: which client app (by the name given to its
--   key in config.local.php `api_clients`), the end user's address, the typed
--   username (never the password) and the result. Used to rate-limit each API
--   key (requests per minute) and to slow down wrong keys; also an audit trail.
--   Rows older than 30 days are cleaned up automatically.
-- Run ONCE per environment, BEFORE deploying the code. Safe to run again.
-- Until it runs, the API answers 503 "not_ready".
-- ============================================================================

CREATE TABLE IF NOT EXISTS `api_auth_requests` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `ClientID`  VARCHAR(50)  NOT NULL,            -- '' = no / wrong API key
  `IP`        VARCHAR(45)  NOT NULL,            -- the end user's address (or the caller's)
  `Username`  VARCHAR(150) NOT NULL DEFAULT '',
  `Result`    VARCHAR(30)  NOT NULL,            -- ok, invalid_credentials, locked, inactive, …
  `CreatedAt` DATETIME     NOT NULL,            -- Manila time
  PRIMARY KEY (`id`),
  KEY `ix_api_client` (`ClientID`, `CreatedAt`),
  KEY `ix_api_ip` (`IP`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SHOW TABLES LIKE 'api_auth_requests';
