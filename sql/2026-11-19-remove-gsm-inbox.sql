-- ============================================================================
-- Migration: REMOVE Management > SIM Inbox
--
-- Undoes sql/2026-11-19-add-gsm-inbox.sql. The SIM Inbox module was taken out
-- of the code; this drops what its migration added:
--   accessrights.gsminbox, gsm_sms, gsm_lines, gsm_collectors
--
-- Run ONLY on servers where the add-gsm-inbox migration was run (production
-- had it). Dropping the tables deletes any texts stored in them: check first.
-- The ALTER fails if the column is already gone; the DROPs never fail.
-- Order with the deploy does not matter: the code no longer uses any of it.
-- Afterwards remove the 'gsm' => [...] line from config.local.php.
-- ============================================================================

-- Check first (expect 0 rows if the collector never sent anything here):
-- SELECT COUNT(*) FROM gsm_sms;

DROP TABLE IF EXISTS `gsm_sms`;
DROP TABLE IF EXISTS `gsm_lines`;
DROP TABLE IF EXISTS `gsm_collectors`;

ALTER TABLE accessrights
  DROP COLUMN gsminbox;

-- Verify:
-- SHOW TABLES LIKE 'gsm\_%';                          -- nothing
-- SHOW COLUMNS FROM accessrights LIKE 'gsminbox';     -- nothing
