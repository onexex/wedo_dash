-- ============================================================================
-- Migration: Company ID card generator (idcard.php, includes/idcard-lib.php)
-- Run ONCE per environment, BEFORE deploying the code. Additive; the ALTER is
-- the only statement that fails on a second run (column already exists).
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

-- 1) Access right (default OFF), granted to the HR users who already hold
--    "Update 201 Files" (updte = 2) — the same people who maintain 201 records.
ALTER TABLE accessrights
  ADD COLUMN idcard INT(11) NOT NULL DEFAULT 1 AFTER updte;

UPDATE accessrights SET idcard = 2 WHERE updte = 2;

-- 2) One row per employee: the issued ID number (NULL until first issue) and
--    how the photo sits inside the card's circle. A row may exist with only a
--    photo adjustment saved. (issue_year, seq) is unique so two HR users
--    issuing at the same moment can never get the same running number
--    (NULLs don't collide in a MySQL unique key).
CREATE TABLE IF NOT EXISTS `idcard_cards` (
  `EmpID`           VARCHAR(50)  NOT NULL,
  `id_number`       VARCHAR(20)  NULL,             -- e.g. 2026BNF008
  `issue_year`      SMALLINT     NULL,
  `seq`             INT          NULL,
  `photo_x`         DECIMAL(5,3) NOT NULL DEFAULT 0,   -- -1..1, share of the available pan
  `photo_y`         DECIMAL(5,3) NOT NULL DEFAULT 0,
  `photo_zoom`      DECIMAL(4,2) NOT NULL DEFAULT 1,   -- 1..3
  `first_issued_at` DATETIME     NULL,
  `updated_by`      VARCHAR(50)  NULL,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`EmpID`),
  UNIQUE KEY `ux_idcard_number` (`id_number`),
  UNIQUE KEY `ux_idcard_year_seq` (`issue_year`, `seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Audit trail of every print request (the card carries the GM's signature).
CREATE TABLE IF NOT EXISTS `idcard_print_log` (
  `id`                       INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`                    VARCHAR(50)  NOT NULL,
  `id_number`                VARCHAR(20)  NOT NULL,
  `action`                   VARCHAR(10)  NOT NULL,      -- issue | reprint
  `prev_employee_id_number`  VARCHAR(100) NULL,          -- employees.EmployeeIDNumber before the first issue
  `printed_by`               VARCHAR(50)  NOT NULL,
  `printed_at`               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_idcard_log_emp` (`EmpID`, `printed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Company details printed on the back of every card (phone, email, address,
--    signatory), editable by HR from the page. Missing keys fall back to the
--    defaults in idc_back_defaults().
CREATE TABLE IF NOT EXISTS `idcard_settings` (
  `setting_key`   VARCHAR(40)  NOT NULL,
  `setting_value` VARCHAR(200) NOT NULL DEFAULT '',
  `updated_by`    VARCHAR(50)  NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT idcard, COUNT(*) FROM accessrights GROUP BY idcard;
