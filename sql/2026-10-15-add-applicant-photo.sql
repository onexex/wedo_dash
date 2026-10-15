-- ============================================================================
-- Migration: applicant photo (apply.php -> includes/applicants-lib.php)
--   Applicants take their photo live with the camera on the Apply page; it is
--   required. Stored re-drawn as a 600x600 JPEG in uploads/applicants/ (no web
--   access), shown to HR through query/applicant-photo.php, and copied to
--   assets/images/profiles/<EmpID>.jpg when the applicant is hired.
-- Run ONCE per environment, BEFORE deploying the code. The ALTER fails on a
-- second run (column already exists). Until it runs, the Apply page has no
-- photo step and nothing asks for one.
-- ============================================================================

ALTER TABLE applicants
  ADD COLUMN PhotoKey VARCHAR(80) NULL AFTER ResumeName;

-- Verify:
-- SHOW COLUMNS FROM applicants LIKE 'PhotoKey';
