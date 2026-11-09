-- ==========================================================================
-- 2026-11-09  Subscriptions: the company's domains, hosting, hosting security
-- services and PhilGEPS registration, with daily renewal reminders.
--
-- Page:     subscriptions.php (right `subscriptions`)
-- Rules:    includes/subscriptions-lib.php
-- Actions:  query/subscriptions-action.php, query/subscriptions-file.php
-- Daily:    tools/subscriptions-daily.php (cPanel cron, 8:00 AM Manila); the
--           first page load after 8:00 AM runs it when the cron has not.
-- Files:    uploads/subscriptions/ (created on first upload, deny-all .htaccess)
--
-- Run ONCE per environment, BEFORE deploying the code. A second run stops at
-- the ALTER TABLE with #1060 (duplicate column) and changes nothing.
-- No one is granted either right here: switch them on per person in Access Rights.
-- The logins vault also needs 'sub_vault_key' in config.local.php (see there).
--
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- Times are written by PHP in Manila time; the servers' MySQL runs on UTC, so
-- the DEFAULT CURRENT_TIMESTAMP values below are only a safety net.
-- ==========================================================================

ALTER TABLE accessrights ADD COLUMN subscriptions INT(11) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `sub_items` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `CompanyID`   VARCHAR(20)   NOT NULL,                        -- companies.CompanyID
  `Category`    VARCHAR(10)   NOT NULL,                        -- domain | hosting | security | philgeps | infra
  `Name`        VARCHAR(150)  NOT NULL,                        -- wedo.ph, cPanel hosting, SiteLock, PhilGEPS Platinum
  `Provider`    VARCHAR(100)  NULL,                            -- GoDaddy, Sucuri, PhilGEPS ...
  `Plan`        VARCHAR(100)  NULL,
  `AccountRef`  VARCHAR(150)  NULL,                            -- account / login e-mail / reference no. (never a password)
  `ExpiresOn`   DATE          NULL,                            -- PhilGEPS: the certificate's own expiry
  `CycleMonths` INT(11)       NOT NULL DEFAULT 12,             -- 1, 3, 6, 12, 24, 36, 60, 120; 0 = does not renew on a cycle
  `Cost`        DECIMAL(12,2) NULL,                            -- per cycle, in pesos
  `AutoRenew`   TINYINT(1)    NOT NULL DEFAULT 0,
  `PaymentNote` VARCHAR(100)  NULL,                            -- "Visa ending 4411", "company GCash"
  `RenewUrl`    VARCHAR(255)  NULL,
  `OwnerEmpID`  VARCHAR(50)   NULL,                            -- the responsible person
  `NotifyDays`  INT(11)       NOT NULL DEFAULT 30,             -- daily reminders start this many days before expiry
  `ParentID`    INT(11)       NULL,                            -- depends on (security -> hosting -> domain)
  `Impact`      VARCHAR(255)  NULL,                            -- what breaks if it lapses
  `Notes`       VARCHAR(1000) NULL,
  `Status`      VARCHAR(10)   NOT NULL DEFAULT 'active',       -- active | cancelled
  `CancelledAt` DATETIME      NULL,
  `CreatedAt`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CreatedBy`   VARCHAR(50)   NULL,
  `UpdatedAt`   DATETIME      NULL,
  `UpdatedBy`   VARCHAR(50)   NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_items_due` (`Status`,`ExpiresOn`),
  KEY `ix_sub_items_company` (`CompanyID`,`Category`),
  KEY `ix_sub_items_owner` (`OwnerEmpID`),
  KEY `ix_sub_items_parent` (`ParentID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PhilGEPS eligibility documents: each with its own expiry (NULL = does not expire)
CREATE TABLE IF NOT EXISTS `sub_requirements` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `ItemID`     INT(11)      NOT NULL,
  `Name`       VARCHAR(120) NOT NULL,
  `IssuedOn`   DATE         NULL,
  `ExpiresOn`  DATE         NULL,
  `NotifyDays` INT(11)      NOT NULL DEFAULT 45,
  `Notes`      VARCHAR(500) NULL,
  `SortOrder`  INT(11)      NOT NULL DEFAULT 0,
  `UpdatedAt`  DATETIME     NULL,
  `UpdatedBy`  VARCHAR(50)  NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_req_item` (`ItemID`,`SortOrder`),
  KEY `ix_sub_req_due` (`ExpiresOn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- every renewal, of an item or of one PhilGEPS document
CREATE TABLE IF NOT EXISTS `sub_renewals` (
  `id`        INT(11)       NOT NULL AUTO_INCREMENT,
  `ItemID`    INT(11)       NOT NULL,
  `ReqID`     INT(11)       NULL,
  `OldExpiry` DATE          NULL,
  `NewExpiry` DATE          NOT NULL,
  `Amount`    DECIMAL(12,2) NULL,
  `PaidOn`    DATE          NULL,
  `Reference` VARCHAR(100)  NULL,                              -- invoice / OR / transaction no.
  `Notes`     VARCHAR(500)  NULL,
  `CreatedAt` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CreatedBy` VARCHAR(50)   NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_ren_item` (`ItemID`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sub_files` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `ItemID`    INT(11)      NOT NULL,
  `ReqID`     INT(11)      NULL,
  `RenewalID` INT(11)      NULL,
  `Kind`      VARCHAR(10)  NOT NULL DEFAULT 'document',         -- receipt | document | other
  `FileKey`   VARCHAR(80)  NOT NULL,                            -- YYYY/MM/<random>.<ext> under uploads/subscriptions/
  `Name`      VARCHAR(100) NULL,
  `Size`      INT(11)      NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CreatedBy` VARCHAR(50)  NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_files_item` (`ItemID`,`ReqID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- people reminded about an item besides its owner
CREATE TABLE IF NOT EXISTS `sub_watchers` (
  `ItemID` INT(11)     NOT NULL,
  `EmpID`  VARCHAR(50) NOT NULL,
  PRIMARY KEY (`ItemID`,`EmpID`),
  KEY `ix_sub_watch_emp` (`EmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- one row per day the reminders went out (cron or the page-load fallback)
CREATE TABLE IF NOT EXISTS `sub_runs` (
  `Day`        DATE        NOT NULL,
  `Source`     VARCHAR(10) NOT NULL,                            -- cron | page
  `StartedAt`  DATETIME    NOT NULL,
  `FinishedAt` DATETIME    NULL,
  `People`     INT(11)     NOT NULL DEFAULT 0,
  `Entries`    INT(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (`Day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- one digest per person per day: what they were reminded about
CREATE TABLE IF NOT EXISTS `sub_notify_log` (
  `id`      INT(11)      NOT NULL AUTO_INCREMENT,
  `Day`     DATE         NOT NULL,
  `EmpID`   VARCHAR(50)  NOT NULL,
  `Entries` INT(11)      NOT NULL DEFAULT 0,
  `Title`   VARCHAR(100) NOT NULL,
  `Body`    VARCHAR(255) NOT NULL,
  `SentAt`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_sub_notify_day_emp` (`Day`,`EmpID`),
  KEY `ix_sub_notify_emp` (`EmpID`,`Day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- logins vault (right `subcreds`, separate from `subscriptions`) ----
-- Username, password and notes are encrypted by PHP (AES-256-GCM) with the key
-- 'sub_vault_key' in config.local.php; only ciphertext is stored here, so a
-- database dump or backup does not give the passwords away. Without the key the
-- vault says it is locked. Do not change the key once logins are saved.
ALTER TABLE accessrights ADD COLUMN subcreds INT(11) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `sub_creds` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `ItemID`    INT(11)      NOT NULL,                             -- sub_items.id
  `Label`     VARCHAR(100) NOT NULL,                             -- Registrar account, cPanel, PhilGEPS portal ...
  `Url`       VARCHAR(255) NULL,                                 -- where to sign in
  `UserEnc`   TEXT         NULL,                                 -- encrypted username
  `SecretEnc` TEXT         NULL,                                 -- encrypted password
  `NotesEnc`  TEXT         NULL,                                 -- encrypted notes (2FA / recovery / security answers)
  `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `CreatedBy` VARCHAR(50)  NOT NULL,
  `UpdatedAt` DATETIME     NULL,
  `UpdatedBy` VARCHAR(50)  NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_creds_item` (`ItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- every reveal / copy / change, kept even after the login or item is deleted
CREATE TABLE IF NOT EXISTS `sub_cred_log` (
  `id`      INT(11)      NOT NULL AUTO_INCREMENT,
  `CredID`  INT(11)      NOT NULL,
  `ItemID`  INT(11)      NOT NULL,
  `Label`   VARCHAR(100) NOT NULL,                               -- the login's label at the time
  `EmpID`   VARCHAR(50)  NOT NULL,
  `Action`  VARCHAR(10)  NOT NULL,                               -- view | copy | add | edit | delete
  `Ip`      VARCHAR(45)  NULL,
  `At`      DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_cred_log_item` (`ItemID`,`At`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Check afterwards:
-- SHOW COLUMNS FROM accessrights LIKE 'sub%';   -- subscriptions + subcreds
-- SHOW TABLES LIKE 'sub\_%';
