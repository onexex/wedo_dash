-- ============================================================================
-- Migration: Son and Daughter in the Family Details relationship list
--
-- The Relationship picker in Family Details (Update my info / Edit profile,
-- Enroll Employee) reads the `frelationship` table, which had no entry for a
-- child, so employees could not list a son or daughter. This adds both.
-- The picker also gains "Other — type it" for anything not in the list
-- (no database change needed for that).
--
-- Run ONCE per environment (local, staging, production). Safe to run again:
-- each row is only added when it is not there yet.
-- ============================================================================

INSERT INTO frelationship (FRelDesc)
SELECT 'Son' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM frelationship WHERE FRelDesc = 'Son');

INSERT INTO frelationship (FRelDesc)
SELECT 'Daughter' FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM frelationship WHERE FRelDesc = 'Daughter');

-- Verify:
-- SELECT * FROM frelationship WHERE FRelDesc IN ('Son', 'Daughter');   -- 2 rows
