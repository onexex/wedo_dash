-- ==========================================================================
-- 2026-11-05  Inventory: company property, documents and consumable supplies.
--
-- Pages:    inventory.php (right `inventory`), myitems.php (everyone)
-- Rules:    includes/inventory-lib.php
-- Actions:  query/inventory-action.php, query/inventory-file.php
-- Files:    uploads/inventory/ (created on first upload, deny-all .htaccess)
--
-- Run ONCE per environment, BEFORE deploying the code. A second run stops at
-- the ALTER TABLE with #1060 (duplicate column) and changes nothing.
-- No one is granted the right here: switch it on per person in Access Rights.
--
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- Times are written by PHP in Manila time; the servers' MySQL runs on UTC, so
-- the DEFAULT CURRENT_TIMESTAMP values below are only a safety net.
-- ==========================================================================

ALTER TABLE accessrights ADD COLUMN inventory INT(11) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `inv_categories` (
  `id`        INT(11)     NOT NULL AUTO_INCREMENT,
  `Code`      VARCHAR(6)  NOT NULL,                   -- tag prefix: APP FUR FIX EQP DOC SUP OTH
  `Name`      VARCHAR(60) NOT NULL,
  `Kind`      VARCHAR(12) NOT NULL DEFAULT 'asset',   -- asset | document | consumable
  `NextSeq`   INT(11)     NOT NULL DEFAULT 1,
  `Active`    TINYINT(1)  NOT NULL DEFAULT 1,
  `SortOrder` INT(11)     NOT NULL DEFAULT 0,
  `CreatedAt` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_inv_cat_code` (`Code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inv_locations` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `Name`      VARCHAR(80)  NOT NULL,
  `Notes`     VARCHAR(200) NULL,
  `Active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_inv_loc_name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inv_items` (
  `id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `TagNo`          VARCHAR(30)   NOT NULL,
  `CategoryID`     INT(11)       NOT NULL,
  `Name`           VARCHAR(150)  NOT NULL,
  `Description`    VARCHAR(500)  NULL,
  `Brand`          VARCHAR(80)   NULL,
  `Model`          VARCHAR(80)   NULL,
  `SerialNo`       VARCHAR(80)   NULL,
  `Qty`            INT(11)       NOT NULL DEFAULT 1,        -- lot size (asset) / on hand (consumable)
  `Unit`           VARCHAR(20)   NOT NULL DEFAULT 'pc',
  `ItemCondition`  VARCHAR(10)   NOT NULL DEFAULT 'good',   -- new | good | fair | poor | damaged
  `Status`         VARCHAR(10)   NOT NULL DEFAULT 'active', -- active | repair | lost | disposed | merged
  `LocationID`     INT(11)       NOT NULL,
  `Spot`           VARCHAR(100)  NULL,                      -- cabinet / shelf / bin inside the location
  `HolderEmpID`    VARCHAR(50)   NULL,                      -- filled = issued to this employee
  `IssuedOn`       DATE          NULL,
  `AckAt`          DATETIME      NULL,                      -- the holder confirmed receipt
  `CompanyID`      VARCHAR(10)   NULL,
  `AcquiredOn`     DATE          NULL,
  `Supplier`       VARCHAR(120)  NULL,
  `UnitCost`       DECIMAL(12,2) NULL,
  `ReferenceNo`    VARCHAR(60)   NULL,
  `WarrantyUntil`  DATE          NULL,
  `DocNumber`      VARCHAR(80)   NULL,
  `DocIssuer`      VARCHAR(120)  NULL,
  `DocIssuedOn`    DATE          NULL,
  `ExpiresOn`      DATE          NULL,
  `RemindDays`     INT(11)       NOT NULL DEFAULT 30,
  `ReorderLevel`   INT(11)       NULL,                      -- consumables; NULL = no low-stock alert
  `ParentID`       INT(11)       NULL,                      -- the ROOT lot this row was split from
  `SplitSeq`       INT(11)       NOT NULL DEFAULT 0,        -- on the root: last suffix handed out
  `MergedIntoID`   INT(11)       NULL,
  `DisposedOn`     DATE          NULL,
  `DisposalMethod` VARCHAR(12)   NULL,                      -- sold | donated | scrapped | returned | other
  `DisposalNote`   VARCHAR(300)  NULL,
  `LastCountedOn`  DATE          NULL,
  `Notes`          VARCHAR(500)  NULL,
  `CreatedBy`      VARCHAR(50)   NULL,
  `CreatedAt`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt`      DATETIME      NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_inv_tag` (`TagNo`),
  KEY `ix_inv_cat` (`CategoryID`, `Status`),
  KEY `ix_inv_loc` (`LocationID`, `Status`),
  KEY `ix_inv_holder` (`HolderEmpID`, `Status`),
  KEY `ix_inv_parent` (`ParentID`),
  KEY `ix_inv_expires` (`ExpiresOn`),
  KEY `ix_inv_warranty` (`WarrantyUntil`),
  KEY `ix_inv_serial` (`SerialNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- per-item history: every custody change
CREATE TABLE IF NOT EXISTS `inv_movements` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `ItemID`         INT(11)      NOT NULL,
  `Type`           VARCHAR(16)  NOT NULL,   -- added edited issued returned acknowledged transferred split merged
                                            -- repair_open repair_close counted lost found disposed
  `FromLocationID` INT(11)      NULL,
  `ToLocationID`   INT(11)      NULL,
  `FromEmpID`      VARCHAR(50)  NULL,
  `ToEmpID`        VARCHAR(50)  NULL,
  `Qty`            INT(11)      NULL,
  `RefItemID`      INT(11)      NULL,       -- the other row of a split / merge
  `RefID`          INT(11)      NULL,       -- repair id or count id
  `Notes`          VARCHAR(500) NULL,
  `ByEmpID`        VARCHAR(50)  NULL,
  `CreatedAt`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_inv_mov_item` (`ItemID`, `id`),
  KEY `ix_inv_mov_emp` (`ToEmpID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- quantity ledger, consumables only. Qty is a signed delta, rows are never edited
CREATE TABLE IF NOT EXISTS `inv_stock_moves` (
  `id`             INT(11)       NOT NULL AUTO_INCREMENT,
  `ItemID`         INT(11)       NOT NULL,
  `Type`           VARCHAR(8)    NOT NULL,                  -- in | out | adjust
  `Qty`            INT(11)       NOT NULL,                  -- + in, - out, +/- adjust
  `BalanceAfter`   INT(11)       NOT NULL,
  `UnitCost`       DECIMAL(12,2) NULL,
  `Supplier`       VARCHAR(120)  NULL,
  `ToEmpID`        VARCHAR(50)   NULL,
  `ToDepartmentID` INT(11)       NULL,
  `Reference`      VARCHAR(60)   NULL,
  `Notes`          VARCHAR(300)  NULL,                      -- purpose (out) / reason (adjust)
  `CountID`        INT(11)       NULL,
  `ByEmpID`        VARCHAR(50)   NULL,
  `CreatedAt`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_inv_stk_item` (`ItemID`, `id`),
  KEY `ix_inv_stk_type` (`Type`, `CreatedAt`),
  KEY `ix_inv_stk_dept` (`ToDepartmentID`, `CreatedAt`),
  KEY `ix_inv_stk_emp` (`ToEmpID`, `CreatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inv_repairs` (
  `id`         INT(11)       NOT NULL AUTO_INCREMENT,
  `ItemID`     INT(11)       NOT NULL,
  `Kind`       VARCHAR(8)    NOT NULL DEFAULT 'repair',     -- repair | service
  `Status`     VARCHAR(10)   NOT NULL DEFAULT 'open',       -- reported | open | done | cancelled
  `Problem`    VARCHAR(500)  NOT NULL,
  `Vendor`     VARCHAR(120)  NULL,
  `SentOn`     DATE          NULL,
  `ReturnedOn` DATE          NULL,
  `Cost`       DECIMAL(12,2) NULL,
  `Result`     VARCHAR(500)  NULL,
  `ReportedBy` VARCHAR(50)   NULL,
  `ByEmpID`    VARCHAR(50)   NULL,
  `CreatedAt`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ClosedAt`   DATETIME      NULL,
  PRIMARY KEY (`id`),
  KEY `ix_inv_rep_item` (`ItemID`, `Status`),
  KEY `ix_inv_rep_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inv_files` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `ItemID`    INT(11)      NOT NULL,
  `Kind`      VARCHAR(8)   NOT NULL DEFAULT 'photo',        -- photo | scan | receipt | other
  `FileKey`   VARCHAR(80)  NOT NULL,                        -- YYYY/MM/<random>.<ext> under uploads/inventory/
  `Name`      VARCHAR(100) NULL,
  `Size`      INT(11)      NOT NULL DEFAULT 0,
  `ByEmpID`   VARCHAR(50)  NULL,
  `CreatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_inv_files_item` (`ItemID`, `Kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inv_counts` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `LocationID` INT(11)      NOT NULL,
  `Title`      VARCHAR(120) NOT NULL,
  `Status`     VARCHAR(8)   NOT NULL DEFAULT 'open',        -- open | closed
  `Notes`      VARCHAR(500) NULL,
  `StartedBy`  VARCHAR(50)  NULL,
  `StartedAt`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ClosedBy`   VARCHAR(50)  NULL,
  `ClosedAt`   DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `ix_inv_counts_loc` (`LocationID`, `Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inv_count_lines` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `CountID`       INT(11)      NOT NULL,
  `ItemID`        INT(11)      NULL,                        -- NULL = unrecorded "extra"
  `ExtraName`     VARCHAR(150) NULL,
  `ExpectedQty`   INT(11)      NOT NULL DEFAULT 0,          -- re-read when the line is checked
  `FoundQty`      INT(11)      NULL,
  `Result`        VARCHAR(10)  NULL,                        -- found | missing | misplaced | extra | moved
  `ItemCondition` VARCHAR(10)  NULL,
  `Notes`         VARCHAR(300) NULL,
  `Applied`       TINYINT(1)   NOT NULL DEFAULT 0,
  `ByEmpID`       VARCHAR(50)  NULL,
  `CheckedAt`     DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_inv_line` (`CountID`, `ItemID`),
  KEY `ix_inv_line_item` (`ItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO inv_categories (Code, Name, Kind, SortOrder) VALUES
 ('APP', 'Appliances',            'asset',      1),
 ('FUR', 'Furniture',             'asset',      2),
 ('FIX', 'Fixtures',              'asset',      3),
 ('EQP', 'IT & Office Equipment', 'asset',      4),
 ('DOC', 'Documents',             'document',   5),
 ('SUP', 'Supplies',              'consumable', 6),
 ('OTH', 'Others',                'asset',      7);

-- Verify:
-- SHOW COLUMNS FROM accessrights LIKE 'inventory';
-- SHOW TABLES LIKE 'inv\_%';            -- 9 tables
-- SELECT Code, Name, Kind FROM inv_categories ORDER BY SortOrder;
