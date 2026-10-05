-- ============================================================================
-- Migration: add the `pfam` access right for Maintenance > Family Details for
-- Parental (maintenance?parentalfamilydetails). The page, its menu item and its
-- save/list endpoints were previously open to every logged-in user.
-- Run ONCE per environment, BEFORE deploying the code (without the column the
-- page is hidden and blocked for everyone). The ALTER fails on a second run.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

-- 1) Add the column (default OFF so nobody gains access implicitly).
ALTER TABLE accessrights
  ADD COLUMN pfam INT(11) NOT NULL DEFAULT 1 AFTER eoval;

-- 2) Grant to the HR users who already hold "Update 201 Files" (updte = 2) —
--    the same people who maintain employee family records. Anyone else can be
--    granted individually in Access Rights (Maintenance > Family Details for Parental).
UPDATE accessrights SET pfam = 2 WHERE updte = 2;

-- Verify:
-- SELECT pfam, COUNT(*) FROM accessrights GROUP BY pfam;
