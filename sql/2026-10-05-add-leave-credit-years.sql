-- ============================================================================
-- Migration: start each leave year from the Leave Credits screen
-- (leavecredit.php, includes/leave-credit-lib.php, query/leavecredit-action.php)
-- Run ONCE per environment, BEFORE deploying the code.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

-- 0) PRE-CHECK — must return no rows. Step 2 adds a unique key on EmpID and
--    fails if an employee has two credit rows; merge those by hand first.
SELECT EmpID, COUNT(*) FROM credit GROUP BY EmpID HAVING COUNT(*) > 1;

-- 1) Access right (default OFF): set leave credits and start a new leave year.
--    Granted to users who already hold both "Leave Credit Overview" and
--    "Update 201 Files" — the HR people who did this by hand before.
ALTER TABLE accessrights
  ADD COLUMN lcreditedit INT(11) NOT NULL DEFAULT 1 AFTER lcreaditview;

UPDATE accessrights SET lcreditedit = 2 WHERE lcreaditview = 2 AND updte = 2;

-- 2) credit: InnoDB so a new-year reset is all-or-nothing (MyISAM can't roll
--    back), and one row per employee.
ALTER TABLE credit ENGINE = InnoDB;
ALTER TABLE credit ADD UNIQUE KEY ux_credit_emp (EmpID);

-- 3) One row per leave year that has been started. The primary key is what
--    stops the same year being reset twice.
CREATE TABLE IF NOT EXISTS `credit_years` (
  `leave_year`  SMALLINT     NOT NULL,
  `started_by`  VARCHAR(50)  NOT NULL,          -- EmpID, or 'manual' for years done in the database
  `started_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `employees`   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`leave_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2026 credits were already set by hand in January; record that so the
-- "Start leave year" button first appears on 1 January 2027.
INSERT IGNORE INTO credit_years (leave_year, started_by, started_at, employees)
VALUES (2026, 'manual', '2026-01-01 00:00:00', 0);

-- 4) Every change to an employee's credit row made from the screen, with the
--    values before and after (enough to put a row back by hand).
CREATE TABLE IF NOT EXISTS `credit_log` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`       VARCHAR(50)  NOT NULL,
  `leave_year`  SMALLINT     NOT NULL,
  `action`      VARCHAR(10)  NOT NULL,          -- new_year | edit | add
  `old_ct`      DECIMAL(9,4) NULL,              -- NULL when the row was added
  `old_cth`     DECIMAL(9,4) NULL,
  `new_ct`      DECIMAL(9,4) NOT NULL,
  `new_cth`     DECIMAL(9,4) NOT NULL,
  `changed_by`  VARCHAR(50)  NOT NULL,
  `changed_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_credit_log_emp` (`EmpID`, `changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT lcreditedit, COUNT(*) FROM accessrights GROUP BY lcreditedit;
-- SELECT * FROM credit_years;
