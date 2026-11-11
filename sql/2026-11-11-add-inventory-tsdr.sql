-- ==========================================================================
-- 2026-11-11  Inventory: TSDR (Technical Services Data Report).
--
-- The IT service form that goes with equipment handed to an employee: which
-- items, which services (install, configure, ...), who approved it, and the
-- SysAd's result. Made from People > an employee > Make TSDR, listed in the
-- TSDR tab, printed on the same layout as the old projInventory form.
--
-- Pages:    inventory.php (right `inventory`)
-- Rules:    includes/inventory-tsdr-lib.php
-- Actions:  query/inventory-action.php (tsdr_*)
-- History:  tools/import-projinventory.php also brings over the old TSDRs.
--
-- Needs sql/2026-11-05-add-inventory.sql first. Run ONCE per environment,
-- BEFORE deploying the code. Safe to run again (IF NOT EXISTS).
-- ==========================================================================

CREATE TABLE IF NOT EXISTS `inv_tsdr` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `TsdrNo`          VARCHAR(20)   NOT NULL,                  -- YYMMDD_<running number>, e.g. 261111_229
  `Seq`             INT(11)       NOT NULL,                  -- the running number, continues the old app's count
  `EmpID`           VARCHAR(50)   NOT NULL,                  -- the requisitioner
  `Requisitioner`   VARCHAR(150)  NOT NULL,                  -- name as printed
  `AccountDept`     VARCHAR(120)  NULL,
  `DateNeeded`      DATE          NULL,
  `Services`        VARCHAR(250)  NULL,                      -- comma list: install,configure,... (see INV_TSDR_SERVICES)
  `OtherService`    VARCHAR(200)  NULL,
  `ApprovedByEmpID` VARCHAR(50)   NULL,
  `ApprovedBy`      VARCHAR(150)  NULL,                      -- name as printed
  `RequestedFrom`   VARCHAR(8)    NOT NULL DEFAULT 'it',     -- it | dev
  `ReceivedBy`      VARCHAR(150)  NULL,
  `SysadEmpID`      VARCHAR(50)   NULL,
  `Sysad`           VARCHAR(150)  NULL,                      -- SysAd personnel assigned, as printed
  `Status`          VARCHAR(8)    NOT NULL DEFAULT 'done',   -- done | pending | cannot | others
  `StatusDetails`   VARCHAR(300)  NULL,
  `Evaluation`      VARCHAR(500)  NULL,
  `Recommendation`  VARCHAR(500)  NULL,
  `AccomplishedAt`  DATETIME      NULL,
  `Source`          VARCHAR(16)   NOT NULL DEFAULT 'wedo',   -- wedo | projinventory
  `CreatedBy`       VARCHAR(50)   NULL,
  `CreatedAt`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt`       DATETIME      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_inv_tsdr_no` (`TsdrNo`),
  KEY `ix_inv_tsdr_emp` (`EmpID`, `CreatedAt`),
  KEY `ix_inv_tsdr_seq` (`Seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- the items on a TSDR; ItemText keeps what was printed even if the item changes later
CREATE TABLE IF NOT EXISTS `inv_tsdr_items` (
  `id`        INT(11)       NOT NULL AUTO_INCREMENT,
  `TsdrID`    INT(11)       NOT NULL,
  `ItemID`    INT(11)       NULL,
  `ItemText`  VARCHAR(250)  NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_inv_tsdr_items_tsdr` (`TsdrID`),
  KEY `ix_inv_tsdr_items_item` (`ItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SHOW TABLES LIKE 'inv\_tsdr%';        -- 2 tables
