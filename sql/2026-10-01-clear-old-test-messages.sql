-- Clear the old test chatter from Messages (2020–2022: "test", "hi", "Hello :)" …)
-- before the rebuilt Messages goes into everyday use.
--
-- What it removes
--   * every message sent BEFORE 2026-10-01 (the day the new Messages went live)
--   * the conversation headers left with no messages, so they drop off everyone's list
-- What it keeps
--   * anything sent from 2026-10-01 on (real use of the new Messages, group chats)
--   * groups, presence, calls — untouched
--
-- Run it once, on each server, in phpMyAdmin (SQL tab) — staging first.
-- 1) Run the PREVIEW block alone and check the numbers.
-- 2) Then run the DELETE block.

-- ---------------------------------------------------------------- PREVIEW (changes nothing)
SELECT COUNT(*) AS messages_to_delete, MIN(DateSent) AS oldest, MAX(DateSent) AS newest
  FROM messages
 WHERE DateSent < '2026-10-01 00:00:00';

SELECT COUNT(*) AS messages_kept FROM messages WHERE DateSent >= '2026-10-01 00:00:00';

-- ---------------------------------------------------------------- DELETE
START TRANSACTION;

DELETE FROM messages
 WHERE DateSent < '2026-10-01 00:00:00';

-- Optional: also remove today's "test" pair sent while trying out the new Messages.
-- Un-comment only if those two are the only messages between these two people today.
-- DELETE FROM messages
--  WHERE MHID IN ('WeDoinc-0010_WeDoinc-0139', 'WeDoinc-0139_WeDoinc-0010')
--    AND Message = 'test';

-- conversations with nothing left in them
DELETE h FROM messageheader h
 WHERE NOT EXISTS (SELECT 1 FROM messages m WHERE m.MHID = h.MHID);

COMMIT;

-- Check: should show only conversations from 2026-10-01 on (or nothing)
SELECT COUNT(*) AS messages_left FROM messages;
SELECT COUNT(*) AS conversations_left FROM messageheader;
