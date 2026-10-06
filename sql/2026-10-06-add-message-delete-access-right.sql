-- ============================================================================
-- Migration: add the `msgdel` access right — "Messages: Delete Own Messages"
-- (Access Rights > Others). Holders get a delete (trash) button on messages
-- THEY sent; the others in the chat then see "This message was deleted", and a
-- deleted picture/document file is removed from the server.
-- Run ONCE per environment. Until it runs the delete button stays hidden for
-- everyone (nothing breaks). The ALTER fails on a second run.
-- Convention: accessrights values -> 2 = ON (granted), 1 = OFF (blocked).
-- ============================================================================

-- 1) Add the column (default OFF so nobody gains access implicitly).
ALTER TABLE accessrights
  ADD COLUMN msgdel INT(11) NOT NULL DEFAULT 1;

-- 2) Grant to super users (role 1). Everyone else is switched on individually
--    in Access Rights (Others > Messages: Delete Own Messages).
UPDATE accessrights a
  JOIN empdetails d ON d.EmpID = a.EmpID
   SET a.msgdel = 2
 WHERE d.EmpRoleID = 1;

-- Verify:
-- SELECT msgdel, COUNT(*) FROM accessrights GROUP BY msgdel;
