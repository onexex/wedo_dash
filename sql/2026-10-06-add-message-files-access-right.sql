-- ============================================================================
-- Migration: add the `msgfile` access right — "Messages: Send Pictures &
-- Documents" (Access Rights > Others). Holders get a paperclip in Messages to
-- send pictures (JPG/PNG/GIF/WebP) and documents (PDF, Word, Excel, PowerPoint,
-- TXT, CSV), 10 MB each. Everyone in the chat can open what was sent there.
-- Run ONCE per environment. Until it runs the paperclip stays hidden for
-- everyone (nothing breaks). The ALTER fails on a second run.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- Files are stored in uploads/messages/ (created automatically, web access
-- denied by its own .htaccess); the web user must be able to write uploads/.
-- ============================================================================

-- 1) Add the column (default OFF so nobody gains access implicitly).
ALTER TABLE accessrights
  ADD COLUMN msgfile INT(11) NOT NULL DEFAULT 1;

-- 2) Grant to super users (role 1). Everyone else is switched on individually
--    in Access Rights (Others > Messages: Send Pictures & Documents).
UPDATE accessrights a
  JOIN empdetails d ON d.EmpID = a.EmpID
   SET a.msgfile = 2
 WHERE d.EmpRoleID = 1;

-- Verify:
-- SELECT msgfile, COUNT(*) FROM accessrights GROUP BY msgfile;
