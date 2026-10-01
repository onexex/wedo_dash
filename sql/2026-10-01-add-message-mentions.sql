-- ============================================================================
-- Migration: @mentions in group chats (includes/msg-mentions.php).
-- One row per (message, person it mentions). "@everyone" records every other
-- member. Additive; run once per environment (after add-message-groups.sql).
-- Until it is applied, "@" still inserts names but nobody gets the highlight.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `msg_mentions` (
  `MSID`  INT(11)     NOT NULL,   -- messages.MSID
  `EmpID` VARCHAR(50) NOT NULL,   -- who is mentioned
  PRIMARY KEY (`MSID`, `EmpID`),
  KEY `ix_mention_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
