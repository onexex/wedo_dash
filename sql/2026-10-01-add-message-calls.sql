-- ============================================================================
-- Migration: video calls from Messages — 1-to-1 and group calls up to 4 people
-- (includes/msg-calls.php). Audio/video go browser-to-browser (WebRTC); these
-- tables only carry each call's state, who is in it, and the connection set-up
-- messages ("signals") between each pair of people.
-- Run AFTER sql/2026-10-01-add-message-groups.sql. Times are Asia/Manila.
-- Additive and safe to re-run. Until it is applied the call buttons and the
-- incoming-call ringer stay hidden; nothing else is affected.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `msg_calls` (
  `id`           INT(11)     NOT NULL AUTO_INCREMENT,
  `starter`      VARCHAR(50) NOT NULL,
  `group_id`     INT(11)     NULL,                       -- NULL = 1-to-1 call
  `status`       VARCHAR(10) NOT NULL DEFAULT 'ringing', -- ringing | active | ended
  `end_reason`   VARCHAR(10) NULL,                       -- ended | missed | declined | cancelled
  `created_at`   DATETIME    NOT NULL,
  `connected_at` DATETIME    NULL,                       -- when a second person joined
  `ended_at`     DATETIME    NULL,
  PRIMARY KEY (`id`),
  KEY `ix_calls_group` (`group_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `msg_call_members` (
  `call_id`   INT(11)     NOT NULL,
  `EmpID`     VARCHAR(50) NOT NULL,
  `state`     VARCHAR(10) NOT NULL,   -- invited | joined | declined | missed | left
  `joined_at` DATETIME    NULL,
  `ping`      DATETIME    NULL,       -- last time this person's page checked in
  PRIMARY KEY (`call_id`, `EmpID`),
  KEY `ix_call_member` (`EmpID`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `msg_call_signals` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `call_id`    INT(11)     NOT NULL,
  `sender`     VARCHAR(50) NOT NULL,
  `recipient`  VARCHAR(50) NOT NULL,
  `kind`       VARCHAR(10) NOT NULL,   -- offer | answer | ice
  `payload`    MEDIUMTEXT  NOT NULL,   -- JSON from the browser
  `created_at` DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_signals_to` (`call_id`, `recipient`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
