-- ============================================================================
-- Migration: online status + "typing…" for Messages (messages.php).
-- One row per employee, refreshed while they have Messages open:
--   last_seen  -> "Active now" (seen in the last minute) / "Active 5m ago"
--   typing_to  -> who they are typing to; typing_at keeps it fresh (6 s window)
-- Times are written by PHP in Asia/Manila, like the rest of the app.
-- Additive and safe to re-run. Until it is applied, Messages works as before,
-- just without online status and typing indicators.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `msg_presence` (
  `EmpID`     VARCHAR(50) NOT NULL,
  `last_seen` DATETIME    NOT NULL,
  `typing_to` VARCHAR(50) NULL,
  `typing_at` DATETIME    NULL,
  PRIMARY KEY (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
