-- ============================================================================
-- Notifications: "seen" marker for the bell / app Alerts badge
-- (includes/wd-header.php, notifications.php).
--
-- One row per person: when they last opened the Notifications page. The badge
-- only counts filing updates after that moment (and never before 2026), so it
-- clears once read instead of counting every update since 2022.
--
-- Safe to run more than once. Needed on local, staging and production.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `notif_seen` (
  `EmpID`   VARCHAR(50) NOT NULL,
  `seen_at` DATETIME    NOT NULL,
  PRIMARY KEY (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
