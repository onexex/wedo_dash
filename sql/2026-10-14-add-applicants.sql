-- ============================================================================
-- Migration: Applicants — public registration (full 201 profile, no login)
-- and the HR pipeline. On "Hire", WeDo copies the applicant's data into the
-- employee tables (employees, empdetails, empdetails2, empprofiles,
-- empeducationalbackground, fdetails, accessrights) as a Trainee.
--   apply.php (public, no sign-in), applicants.php (HR), includes/applicants-lib.php,
--   query/apply-action.php, query/applicants-action.php, query/applicant-resume.php
-- Run ONCE per environment, AFTER sql/2026-10-14-add-employee-lifecycle.sql and
-- BEFORE deploying the code. Additive; the ALTER is the only statement that
-- fails on a second run (column already exists).
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- Resumes are stored in uploads/applicants/ (created on first upload, web
-- access denied by its own .htaccess; served only through
-- query/applicant-resume.php to holders of the `applicants` right).
-- ============================================================================

-- 1) Access right (default OFF): Management > Applicants. Granted to the HR
--    users who already hold "Update 201 Files" (updte = 2). Hiring also needs
--    "Enroll Employee" (eemployee = 2).
ALTER TABLE accessrights
  ADD COLUMN applicants INT(11) NOT NULL DEFAULT 1 AFTER empverify;

UPDATE accessrights SET applicants = 2 WHERE updte = 2;

-- 2) Job openings shown on the public Apply page while IsOpen = 1.
CREATE TABLE IF NOT EXISTS `job_openings` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `Title`       VARCHAR(120)  NOT NULL,
  `Location`    VARCHAR(120)  NULL,
  `Description` VARCHAR(2000) NULL,
  `IsOpen`      TINYINT(1)    NOT NULL DEFAULT 1,
  `CreatedBy`   VARCHAR(50)   NULL,
  `CreatedAt`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) One row per application = the applicant's 201 profile. Column comments
--    name the employee column each value is copied to on hire.
--    Stage: new | screening | exam | interview (initial) | final (interview) | offer | hired | rejected | withdrawn.
CREATE TABLE IF NOT EXISTS `applicants` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `OpeningID`      INT(11)      NULL,                 -- NULL = "any open position"
  `FirstName`      VARCHAR(80)  NOT NULL,             -- employees.EmpFN
  `MiddleName`     VARCHAR(80)  NULL,                 -- employees.EmpMN
  `LastName`       VARCHAR(80)  NOT NULL,             -- employees.EmpLN
  `Suffix`         VARCHAR(10)  NULL,                 -- employees.EmpSuffix
  `BirthDate`      DATE         NOT NULL,             -- empprofiles.EmpDOB
  `Gender`         VARCHAR(6)   NOT NULL,             -- empprofiles.EmpGender
  `CivilStatus`    VARCHAR(10)  NOT NULL,             -- empprofiles.EmpCS
  `Citizenship`    VARCHAR(60)  NULL,                 -- empprofiles.EmpCitezen
  `Religion`       VARCHAR(60)  NULL,                 -- empprofiles.EmpReligion
  `Email`          VARCHAR(150) NOT NULL,             -- empprofiles.EmpEmail
  `Mobile`         VARCHAR(30)  NOT NULL,             -- empprofiles.EmpPhone (mobile)
  `HomePhone`      VARCHAR(30)  NULL,                 -- empprofiles.EmpMobile (home phone)
  `Street`         VARCHAR(150) NOT NULL,             -- empprofiles.EmpAddress1
  `Barangay`       VARCHAR(100) NOT NULL,             -- empprofiles.EmpAddDis
  `City`           VARCHAR(100) NOT NULL,             -- empprofiles.EmpAddCity
  `Province`       VARCHAR(100) NOT NULL,             -- empprofiles.EmpAddProv
  `Zip`            VARCHAR(10)  NULL,                 -- empprofiles.EmpAddZip
  `Country`        VARCHAR(60)  NOT NULL DEFAULT 'Philippines',   -- empprofiles.EmpAddCountry
  `SSS`            VARCHAR(30)  NULL,                 -- empprofiles.EmpSSS
  `TIN`            VARCHAR(30)  NULL,                 -- empprofiles.EmpTIN
  `PhilHealth`     VARCHAR(30)  NULL,                 -- empprofiles.EmpPHNo
  `PagIbig`        VARCHAR(30)  NULL,                 -- empprofiles.EmpPINo
  `UMID`           VARCHAR(30)  NULL,                 -- empprofiles.EmpUMIDNo
  `PassportNo`     VARCHAR(30)  NULL,                 -- empprofiles.EmpPPNo
  `PassportExpiry` DATE         NULL,                 -- empprofiles.EmpPPED
  `PassportIssuer` VARCHAR(80)  NULL,                 -- empprofiles.EmpPPIA
  `PrevCompany`    VARCHAR(150) NULL,                 -- empprofiles.EmpPP
  `PrevStart`      DATE         NULL,                 -- empprofiles.EmpPPSD
  `PrevDept`       VARCHAR(100) NULL,                 -- empprofiles.EmpPPDept
  `PrevPosition`   VARCHAR(100) NULL,                 -- empprofiles.EmpPPPos
  `Source`         VARCHAR(80)  NULL,                 -- how they heard about the job
  `ResumeKey`      VARCHAR(80)  NULL,                 -- YYYY/MM/<32 hex>.pdf under uploads/applicants/
  `ResumeName`     VARCHAR(120) NULL,                 -- the applicant's file name (display only)
  `Stage`          VARCHAR(12)  NOT NULL DEFAULT 'new',
  `StageAt`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ConsentAt`      DATETIME     NOT NULL,             -- Data Privacy consent ticked
  `IP`             VARCHAR(45)  NULL,
  `CreatedAt`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `EmpID`          VARCHAR(50)  NULL,                 -- the WeDo account created on hire
  `HiredAt`        DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `ix_apl_stage` (`Stage`, `CreatedAt`),
  KEY `ix_apl_opening` (`OpeningID`),
  KEY `ix_apl_email` (`Email`),
  KEY `ix_apl_ip` (`IP`, `CreatedAt`),
  KEY `ix_apl_name` (`LastName`, `FirstName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Education -> empeducationalbackground (Program Primary / Secondary / Tertiary).
CREATE TABLE IF NOT EXISTS `applicant_education` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `ApplicantID` INT(11)      NOT NULL,
  `Program`     VARCHAR(10)  NOT NULL,             -- Primary | Secondary | Tertiary
  `School`      VARCHAR(100) NULL,
  `YearStarted` VARCHAR(4)   NULL,
  `YearEnded`   VARCHAR(4)   NULL,
  `Address`     VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  KEY `ix_apl_edu` (`ApplicantID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Family / emergency contacts -> fdetails (FICE = Yes for "in case of emergency").
CREATE TABLE IF NOT EXISTS `applicant_family` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `ApplicantID`  INT(11)      NOT NULL,
  `Name`         VARCHAR(150) NOT NULL,
  `Relationship` VARCHAR(50)  NOT NULL,
  `Address`      VARCHAR(250) NULL,
  `Contact`      VARCHAR(50)  NULL,
  `Emergency`    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_apl_fam` (`ApplicantID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) Stage history and HR notes (FromStage = ToStage for a plain note).
CREATE TABLE IF NOT EXISTS `applicant_events` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `ApplicantID` INT(11)       NOT NULL,
  `FromStage`   VARCHAR(12)   NULL,
  `ToStage`     VARCHAR(12)   NOT NULL,
  `Notes`       VARCHAR(1000) NULL,
  `ByEmpID`     VARCHAR(50)   NULL,                -- NULL = the applicant (public form)
  `CreatedAt`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_apl_ev` (`ApplicantID`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT applicants, COUNT(*) FROM accessrights GROUP BY applicants;
-- SHOW TABLES LIKE 'job_openings'; SHOW TABLES LIKE 'applicant%';
