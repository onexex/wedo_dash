-- ============================================================================
-- FIX for servers where an early draft of the video-call tables was created.
-- Symptom: pressing the video-call button shows "Unexpected server response";
-- the PHP error is "Unknown column 'starter' in 'field list'".
--
-- Cause: msg_calls / msg_call_signals were first created with the draft design
-- (caller/callee, no recipient). The final sql/2026-10-01-add-message-calls.sql
-- uses CREATE TABLE IF NOT EXISTS, so it skipped them.
--
-- CHECK FIRST — this shows which design the server has:
--   SHOW COLUMNS FROM msg_calls LIKE 'starter';
--   -> one row  : already the right design, you don't need this file.
--   -> no rows  : run the statements below.
--
-- Safe: these tables only hold calls in progress and their connection set-up
-- (finished calls are already noted in the conversations), and on production
-- they were empty (0 rows) on 2026-10-01. Production and staging both need it
-- if they show the symptom; local was fixed 2026-10-01.
-- ============================================================================

DROP TABLE IF EXISTS `msg_call_signals`;
DROP TABLE IF EXISTS `msg_call_members`;
DROP TABLE IF EXISTS `msg_calls`;

CREATE TABLE `msg_calls` (
  `id`           INT(11)     NOT NULL AUTO_INCREMENT,
  `starter`      VARCHAR(50) NOT NULL,
  `group_id`     INT(11)     NULL,                       -- NULL = 1-to-1 call
  `status`       VARCHAR(10) NOT NULL DEFAULT 'ringing', -- ringing | active | ended
  `end_reason`   VARCHAR(10) NULL,                       -- ended | missed | declined | cancelled
  `created_at`   DATETIME    NOT NULL,
  `connected_at` DATETIME    NULL,
  `ended_at`     DATETIME    NULL,
  PRIMARY KEY (`id`),
  KEY `ix_calls_group` (`group_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `msg_call_members` (
  `call_id`   INT(11)     NOT NULL,
  `EmpID`     VARCHAR(50) NOT NULL,
  `state`     VARCHAR(10) NOT NULL,   -- invited | joined | declined | missed | left
  `joined_at` DATETIME    NULL,
  `ping`      DATETIME    NULL,
  PRIMARY KEY (`call_id`, `EmpID`),
  KEY `ix_call_member` (`EmpID`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `msg_call_signals` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `call_id`    INT(11)     NOT NULL,
  `sender`     VARCHAR(50) NOT NULL,
  `recipient`  VARCHAR(50) NOT NULL,
  `kind`       VARCHAR(10) NOT NULL,   -- offer | answer | ice
  `payload`    MEDIUMTEXT  NOT NULL,
  `created_at` DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_signals_to` (`call_id`, `recipient`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify: should list starter, group_id, end_reason, connected_at …
-- SHOW COLUMNS FROM msg_calls;
