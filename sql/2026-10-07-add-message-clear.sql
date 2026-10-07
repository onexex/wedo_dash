-- ============================================================================
-- Messages: "Delete conversation" for me only (includes/msg-clear.php).
--
-- One row per person per conversation they deleted: the newest message id at
-- that moment. Their list and history only show messages after it; the other
-- person's copy is untouched. MHID is the conversation id from messages.MHID
-- ("grp:<id>" for group chats).
--
-- Safe to run more than once. Needed on local, staging and production.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `msg_cleared` (
  `EmpID`        VARCHAR(50)  NOT NULL,
  `MHID`         VARCHAR(100) NOT NULL,
  `cleared_msid` INT(11)      NOT NULL,
  `cleared_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`EmpID`, `MHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
