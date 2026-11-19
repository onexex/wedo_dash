-- ============================================================================
-- Migration: Management > SIM Inbox (gsm.php, includes/gsm-lib.php)
--
-- The office GoIP GSM gateway (16 SIM slots) receives texts on its SIMs. A
-- small collector on an office PC (tools/gsm-collector.php) reads the
-- gateway's inbox every minute and sends new texts here through
-- api/gsm-ingest.php. WeDo keeps every text, reads each SIM's load balance and
-- expiry from the carrier's texts, and warns before a load expires.
--
-- Gated by a new access right `gsminbox` — "SIM Inbox" (Access Rights >
-- Management). NOBODY is granted it here: the SIMs can receive one-time codes,
-- so grant it to as few people as possible.
--
-- The collector's key is NOT in the database: it goes in this server's
-- config.local.php ('gsm' => ['ingest_key' => ...], see config.local.example.php).
--
-- Run ONCE per environment (local, staging, production), BEFORE deploying the
-- code. The ALTER fails on a second run. Until it runs the page stays hidden
-- for everyone and the collector is told "not set up".
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

ALTER TABLE accessrights
  ADD COLUMN gsminbox INT(11) NOT NULL DEFAULT 1;

-- every text the gateway received, once
CREATE TABLE IF NOT EXISTS `gsm_sms` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `Line`       TINYINT(4)   NOT NULL,                     -- gateway slot 1-32
  `Sender`     VARCHAR(40)  NOT NULL,
  `Body`       TEXT         NOT NULL,
  `DeviceTime` VARCHAR(30)  NULL,                         -- as the gateway stamped it (its clock may be wrong)
  `ReceivedAt` DATETIME     NOT NULL,                     -- when the collector first saw it (Manila)
  `IsCarrier`  TINYINT(1)   NOT NULL DEFAULT 0,           -- from a short code / named sender
  `Hash`       CHAR(40)     NOT NULL,                     -- line + gateway time + sender + text: one row per text
  `Collector`  VARCHAR(40)  NOT NULL DEFAULT '',
  `CreatedAt`  DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_gsm_sms_hash` (`Hash`),
  KEY `ix_gsm_sms_line` (`Line`, `id`),
  KEY `ix_gsm_sms_carrier` (`IsCarrier`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- one row per SIM slot in use: its name, number, load balance and expiry
CREATE TABLE IF NOT EXISTS `gsm_lines` (
  `Line`         TINYINT(4)    NOT NULL,
  `Label`        VARCHAR(60)   NULL,
  `Number`       VARCHAR(20)   NULL,
  `Balance`      DECIMAL(10,2) NULL,
  `BalanceAt`    DATETIME      NULL,                      -- when that balance was true
  `ExpiresOn`    DATE          NULL,
  `ExpiryAt`     DATETIME      NULL,                      -- when that expiry was learned
  `ExpirySource` VARCHAR(10)   NULL,                      -- text | manual
  `SourceSmsID`  INT(11)       NULL,                      -- the text it was read from
  `UpdatedBy`    VARCHAR(50)   NULL,
  `UpdatedAt`    DATETIME      NULL,
  PRIMARY KEY (`Line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- the collector's last check-in, so the page can say when it stopped
CREATE TABLE IF NOT EXISTS `gsm_collectors` (
  `Name`       VARCHAR(40)  NOT NULL,
  `LastSeenAt` DATETIME     NOT NULL,
  `LastOkAt`   DATETIME     NULL,                         -- last time it could read the gateway
  `LastError`  VARCHAR(255) NULL,
  `IP`         VARCHAR(45)  NOT NULL DEFAULT '',
  `Version`    VARCHAR(20)  NULL,
  PRIMARY KEY (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify:
-- SELECT gsminbox, COUNT(*) FROM accessrights GROUP BY gsminbox;   -- all 1
-- SHOW TABLES LIKE 'gsm\_%';
--
-- Grant one person (or use Access Rights > Management):
-- UPDATE accessrights SET gsminbox = 2
--  WHERE EmpID = (SELECT EmpID FROM empdetails WHERE EmpUN = '<username>');
