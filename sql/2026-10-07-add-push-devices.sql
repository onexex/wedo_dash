-- ============================================================================
-- Mobile push notifications (WeDo Android / iOS app, Firebase Cloud Messaging).
--
-- One row per phone that has signed in to the app. The app registers its FCM
-- token through query/Query-devicetoken.php after login; includes/push-lib.php
-- looks tokens up by EmpID to deliver approvals, chat messages, calls, etc.
-- A token belongs to one employee at a time: signing in as someone else on the
-- same phone moves the row (UNIQUE token). Tokens Firebase reports as no longer
-- valid are deleted automatically when a send fails.
--
-- Safe to run more than once. Needed on local, staging and production.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `push_devices` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`      VARCHAR(50)  NOT NULL,
  `token`      VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `platform`   VARCHAR(10)  NOT NULL DEFAULT 'android',   -- android | ios
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_devices_token` (`token`),
  KEY `ix_push_devices_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
