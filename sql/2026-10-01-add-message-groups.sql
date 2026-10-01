-- ============================================================================
-- Migration: group chats in Messages (includes/msg-groups.php).
-- Group messages live in the same `messages` table as 1-to-1 messages, with
-- MHID = 'grp:<group id>'. Each member's read position is msg_group_members.last_read.
-- messages.Kind: 'text' (normal) or 'event' (small centred line: "Ramon added Carlo",
-- call notes). Additive; run once per environment. Until it is applied, Messages
-- works 1-to-1 only and the "New group" button stays hidden.
-- ============================================================================

ALTER TABLE messages
  ADD COLUMN Kind VARCHAR(10) NOT NULL DEFAULT 'text' AFTER Message;

CREATE TABLE IF NOT EXISTS `msg_groups` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(60) NOT NULL,
  `created_by` VARCHAR(50) NOT NULL,
  `created_at` DATETIME    NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `msg_group_members` (
  `group_id`  INT(11)     NOT NULL,
  `EmpID`     VARCHAR(50) NOT NULL,
  `role`      VARCHAR(10) NOT NULL DEFAULT 'member',   -- admin | member
  `joined_at` DATETIME    NOT NULL,
  `last_read` INT(11)     NOT NULL DEFAULT 0,          -- newest MSID this member has seen
  PRIMARY KEY (`group_id`, `EmpID`),
  KEY `ix_group_member` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
