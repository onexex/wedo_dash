-- ============================================================================
-- Migration: Management > Message Review (msgreview.php, includes/msg-review.php)
--
-- Lets HR / admin read EVERY conversation in Messages (1-to-1 and groups),
-- read-only, to investigate harassment or misuse. Gated by a new access right
-- `msgaudit` — "Messages: Review All Conversations" (Access Rights > Others).
-- NOBODY is granted it here: an admin switches it on per person.
--
-- Also from now on a message deleted by its sender ("Delete own message",
-- msgdel) is archived first: the chat still shows "This message was deleted",
-- but reviewers can read the original and open its picture/document.
-- Messages deleted BEFORE this migration cannot be recovered.
--
-- Every review action (opening a conversation, searching, opening a file) is
-- written to msg_review_log and shown on the Message Review page.
--
-- Run ONCE per environment (local, staging, production). The ALTER fails on a
-- second run; the CREATE TABLEs are safe to repeat. Until it runs the page
-- stays hidden and deleting works exactly as before.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

-- 1) The access right, OFF for everyone.
ALTER TABLE accessrights
  ADD COLUMN msgaudit INT(11) NOT NULL DEFAULT 1;

-- 2) Originals of deleted messages (one row per deleted message).
--    file_key = the attachment kept in uploads/messages for reviewers, if any.
CREATE TABLE IF NOT EXISTS `msg_deleted_archive` (
  `MSID`       INT(11)      NOT NULL,
  `MHID`       VARCHAR(100) NOT NULL,
  `SenderID`   VARCHAR(50)  NOT NULL,
  `Kind`       VARCHAR(10)  NOT NULL,
  `Message`    TEXT         NOT NULL,
  `file_key`   VARCHAR(120) DEFAULT NULL,
  `deleted_at` DATETIME     NOT NULL,
  PRIMARY KEY (`MSID`),
  KEY `ix_mda_mhid` (`MHID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Who looked at what.
CREATE TABLE IF NOT EXISTS `msg_review_log` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`     VARCHAR(50)  NOT NULL,
  `action`    VARCHAR(20)  NOT NULL,   -- open | search | file | print
  `MHID`      VARCHAR(100) DEFAULT NULL,
  `detail`    VARCHAR(255) NOT NULL DEFAULT '',
  `logged_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_mrl_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT msgaudit, COUNT(*) FROM accessrights GROUP BY msgaudit;   -- all 1
-- Grant one person (or use Access Rights > Others):
-- UPDATE accessrights SET msgaudit = 2 WHERE EmpID = '<EmpID>';
