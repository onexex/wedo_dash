-- ============================================================================
-- Migration: polls in the Corner (corner.php, includes/corner-polls-lib.php,
-- query/corner-polls.php).
--
-- Whoever may post announcements can start a poll ("Where do we eat Friday?",
-- "Team outing place"); every signed-in employee can vote. Options may be
-- single or multiple choice, people may be allowed to suggest more options,
-- votes may be anonymous, and a poll may close at a set time or be closed by
-- hand. utf8mb4 so options like "🍕 Pizza" keep their emoji.
--
-- Additive; run once per environment (local, staging, production). Until it
-- runs the Corner simply shows no Polls card.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `corner_polls` (
  `PollID`      INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`       VARCHAR(50)  NOT NULL,                 -- who started it
  `Question`    VARCHAR(200) NOT NULL,
  `Details`     VARCHAR(500) NOT NULL DEFAULT '',
  `MultiChoice` TINYINT(1)   NOT NULL DEFAULT 0,       -- 1 = may pick several options
  `AllowAdd`    TINYINT(1)   NOT NULL DEFAULT 0,       -- 1 = voters may suggest options
  `Anonymous`   TINYINT(1)   NOT NULL DEFAULT 0,       -- 1 = nobody sees who voted for what
  `ClosesAt`    DATETIME     NULL,                     -- NULL = open until closed by hand
  `ClosedAt`    DATETIME     NULL,                     -- set when closed by hand
  `CreatedAt`   DATETIME     NOT NULL,
  PRIMARY KEY (`PollID`),
  KEY `ix_corner_polls_created` (`CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `corner_poll_options` (
  `OptionID`  INT(11)      NOT NULL AUTO_INCREMENT,
  `PollID`    INT(11)      NOT NULL,
  `Label`     VARCHAR(120) NOT NULL,
  `AddedBy`   VARCHAR(50)  NOT NULL,                   -- the poll's author, or a voter's suggestion
  `SortOrder` INT(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`OptionID`),
  KEY `ix_corner_poll_options_poll` (`PollID`, `SortOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `corner_poll_votes` (
  `PollID`   INT(11)     NOT NULL,
  `OptionID` INT(11)     NOT NULL,
  `EmpID`    VARCHAR(50) NOT NULL,
  `VotedAt`  DATETIME    NOT NULL,
  PRIMARY KEY (`OptionID`, `EmpID`),
  KEY `ix_corner_poll_votes_poll_emp` (`PollID`, `EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT p.Question, o.Label, COUNT(v.EmpID) AS votes
--   FROM corner_polls p JOIN corner_poll_options o ON o.PollID = p.PollID
--   LEFT JOIN corner_poll_votes v ON v.OptionID = o.OptionID
--  GROUP BY o.OptionID ORDER BY p.PollID DESC, o.SortOrder;
