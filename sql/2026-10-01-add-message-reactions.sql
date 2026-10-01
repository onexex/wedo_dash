-- ============================================================================
-- Migration: emoji reactions on messages (includes/msg-reactions.php).
-- One reaction per person per message (picking another emoji replaces it,
-- picking the same one again removes it). Works for 1-to-1 and group messages.
-- utf8mb4_bin: the default *_ci collations treat many different emoji as equal.
-- Additive; run once per environment. Until it is applied the react button
-- simply doesn't appear.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `msg_reactions` (
  `MSID`        INT(11)     NOT NULL,                  -- messages.MSID
  `EmpID`       VARCHAR(50) NOT NULL,                  -- who reacted
  `Emoji`       VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `DateReacted` DATETIME    NOT NULL,
  PRIMARY KEY (`MSID`, `EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
