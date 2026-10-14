-- ============================================================================
-- Migration: Employee lifecycle — separation (Resigned / Terminated / AWOL),
-- lifecycle history, COE requests and employment verification (CI) calls.
--   separation.php, coe.php, verify.php, includes/lifecycle-lib.php,
--   query/lifecycle-action.php, includes/account-gate.php (AWOL is blocked)
-- Run ONCE per environment, BEFORE deploying the code. Additive; the two
-- ALTERs are the only statements that fail on a second run (column exists).
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

-- 1) AWOL employment status (estatus.ID 5; 1 Employed, 2 Resigned, 4 Terminated
--    already exist). Blocked from signing in like Resigned/Terminated.
INSERT INTO estatus (ID, StatusEmpDesc)
SELECT 5, 'AWOL' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM estatus WHERE ID = 5 OR StatusEmpDesc = 'AWOL');

-- 2) Access rights (default OFF), granted to the HR users who already hold
--    "Update 201 Files" (updte = 2). COE approval reuses the existing
--    `coefunction` right — no new column for it.
--      separation : Management > Separation & Lifecycle
--      empverify  : Management > Employment Verification (CI)
ALTER TABLE accessrights
  ADD COLUMN separation INT(11) NOT NULL DEFAULT 1 AFTER updte;
ALTER TABLE accessrights
  ADD COLUMN empverify  INT(11) NOT NULL DEFAULT 1 AFTER separation;

UPDATE accessrights SET separation = 2, empverify = 2 WHERE updte = 2;

-- 3) Lifecycle history: one row per thing that happened to an employee
--    (hired, classification Trainee -> IC, status change, separation,
--    rehire flag change, COE issued). FromVal/ToVal hold readable labels.
CREATE TABLE IF NOT EXISTS `emp_lifecycle_events` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`         VARCHAR(50)  NOT NULL,
  `EventType`     VARCHAR(20)  NOT NULL,   -- hired | classification | status | separated | rehire_flag | coe_issued
  `FromVal`       VARCHAR(100) NULL,
  `ToVal`         VARCHAR(100) NULL,
  `EffectiveDate` DATE         NULL,
  `Notes`         VARCHAR(500) NULL,
  `ByEmpID`       VARCHAR(50)  NULL,
  `CreatedAt`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_lcy_events_emp` (`EmpID`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Separations: the source of truth for the last day (empdetails.
--    EmpDateResigned defaults to "today" on the enrol form, so it can't be
--    trusted) and for rehire eligibility. RehireEligible 'review' = HR still
--    has to decide (rows auto-created when the status is changed in Update
--    Employee Information).
CREATE TABLE IF NOT EXISTS `emp_separations` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`          VARCHAR(50)  NOT NULL,
  `Type`           VARCHAR(12)  NOT NULL,             -- resigned | terminated | awol
  `LastDay`        DATE         NOT NULL,
  `Reason`         VARCHAR(500) NULL,
  `RehireEligible` VARCHAR(8)   NOT NULL DEFAULT 'review',   -- yes | no | review
  `RehireNote`     VARCHAR(500) NULL,
  `ClearanceDone`  TINYINT(1)   NOT NULL DEFAULT 0,
  `RecordedBy`     VARCHAR(50)  NULL,
  `RecordedAt`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_lcy_sep_emp` (`EmpID`, `LastDay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) COE requests. Employees file their own (Channel 'self', pending until a
--    `coefunction` user approves); HR logs requests for people who can no
--    longer sign in (separated) as 'hr' — approved at once. ControlNo
--    (COE-2026-0001) is given on approval and printed on the certificate.
CREATE TABLE IF NOT EXISTS `coe_requests` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`         VARCHAR(50)  NOT NULL,
  `Purpose`       VARCHAR(200) NOT NULL,
  `IncludeSalary` TINYINT(1)   NOT NULL DEFAULT 0,
  `Channel`       VARCHAR(6)   NOT NULL DEFAULT 'self',     -- self | hr
  `Status`        VARCHAR(10)  NOT NULL DEFAULT 'pending',  -- pending | approved | rejected | cancelled
  `RequestedBy`   VARCHAR(50)  NOT NULL,
  `RequestedAt`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ProcessedBy`   VARCHAR(50)  NULL,
  `ProcessedAt`   DATETIME     NULL,
  `Remarks`       VARCHAR(300) NULL,
  `ControlNo`     VARCHAR(20)  NULL,
  `PrintedAt`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_coe_control` (`ControlNo`),
  KEY `ix_coe_emp` (`EmpID`, `RequestedAt`),
  KEY `ix_coe_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) Employment verification log: each call/email from another company
--    checking on a current or former employee (CI / background check).
--    Linked to the employee (EmpID) or to a legacy amsarchive row (AmsID).
CREATE TABLE IF NOT EXISTS `employment_verifications` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `EmpID`            VARCHAR(50)  NULL,
  `AmsID`            INT(11)      NULL,
  `SubjectName`      VARCHAR(150) NOT NULL,
  `CallerName`       VARCHAR(120) NOT NULL,
  `CallerCompany`    VARCHAR(150) NOT NULL,
  `CallerContact`    VARCHAR(120) NULL,
  `Purpose`          VARCHAR(12)  NOT NULL,             -- ci | background | loan | other
  `ConsentConfirmed` TINYINT(1)   NOT NULL DEFAULT 0,
  `Disclosed`        VARCHAR(200) NULL,                 -- comma list: dates,position,status,salary,rehire
  `Notes`            VARCHAR(500) NULL,
  `HandledBy`        VARCHAR(50)  NOT NULL,
  `CalledAt`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ver_emp` (`EmpID`),
  KEY `ix_ver_ams` (`AmsID`),
  KEY `ix_ver_called` (`CalledAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT * FROM estatus ORDER BY ID;
-- SELECT separation, empverify, COUNT(*) FROM accessrights GROUP BY separation, empverify;
-- SHOW TABLES LIKE 'emp_%'; SHOW TABLES LIKE 'coe_requests'; SHOW TABLES LIKE 'employment_verifications';
