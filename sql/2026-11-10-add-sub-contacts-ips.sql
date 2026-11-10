-- ==========================================================================
-- 2026-11-10  Subscriptions & Logins: contacts and IP addresses on items.
--
-- Contacts: who to call about an item (ISP support, building admin, vendor).
-- IP addresses: an item's IPv4 blocks (e.g. 126.209.8.32/28 from the ISP) and
-- what each usable address is used for. Free addresses are not stored: the
-- page works them out from the block.
--
-- Page:     subscriptions.php (right `subscriptions`; no new right, no passwords here)
-- Rules:    includes/subscriptions-lib.php
--
-- Run ONCE per environment, BEFORE deploying the code. Safe to run again
-- (CREATE TABLE IF NOT EXISTS).
-- ==========================================================================

CREATE TABLE IF NOT EXISTS `sub_contacts` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `ItemID`    INT(11)      NOT NULL,                             -- sub_items.id
  `Name`      VARCHAR(100) NOT NULL,                             -- Jen, Ma'am Maricel, Infinivan support
  `Role`      VARCHAR(100) NULL,                                 -- Account manager, Building admin
  `Phone`     VARCHAR(50)  NULL,
  `Email`     VARCHAR(150) NULL,
  `Notes`     VARCHAR(255) NULL,
  `SortOrder` INT(11)      NOT NULL DEFAULT 0,
  `UpdatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedBy` VARCHAR(50)  NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_contacts_item` (`ItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `sub_ipblocks` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `ItemID`    INT(11)      NOT NULL,                             -- sub_items.id
  `Network`   VARCHAR(15)  NOT NULL,                             -- 126.209.8.32 (the block's first address)
  `Prefix`    TINYINT(4)   NOT NULL,                             -- 28  (/22 .. /32)
  `Gateway`   VARCHAR(15)  NULL,
  `Dns`       VARCHAR(100) NULL,                                 -- one or two servers, as given by the ISP
  `Label`     VARCHAR(100) NULL,                                 -- Infinivan public block
  `UpdatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedBy` VARCHAR(50)  NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sub_ipblocks_item` (`ItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- only addresses that are used, reserved or in conflict; the rest are free
CREATE TABLE IF NOT EXISTS `sub_ips` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `BlockID`   INT(11)      NOT NULL,                             -- sub_ipblocks.id
  `Ip`        VARCHAR(15)  NOT NULL,
  `State`     VARCHAR(10)  NOT NULL DEFAULT 'used',              -- used | reserved | conflict
  `Note`      VARCHAR(150) NULL,                                 -- CCTV, WiFi, Dialer (VICIDIAL)
  `CredID`    INT(11)      NULL,                                 -- sub_creds.id: the login for what is on it
  `UpdatedAt` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedBy` VARCHAR(50)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_sub_ips_block_ip` (`BlockID`,`Ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Check afterwards:
-- SHOW TABLES LIKE 'sub\_%';   -- sub_contacts, sub_ipblocks, sub_ips among them
