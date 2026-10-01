-- ============================================================================
-- Migration: let chat messages hold emoji and any language.
-- messages.Message was latin1, so 😀 (and the call notes "📹 …") were saved
-- as "?". Converting the column to utf8mb4 fixes that for new messages.
--
-- 1) CHECK FIRST — run this and look at the number:
--      SELECT COUNT(*) FROM messages WHERE Message <> CONVERT(Message USING ascii);
--    0  -> every stored message is plain text; step 2 is completely safe.
--    >0 -> some messages have accents/symbols. Run step 2, then look at a few of
--          them; if they now read like "Ã±" instead of "ñ", they had been stored
--          double-encoded by the old code — repair them with step 3.
--
-- Run ONCE per environment. Local was checked (96 messages, all plain) and applied 2026-10-01.
-- ============================================================================

-- 2) convert the column
ALTER TABLE messages
  MODIFY Message VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- 3) ONLY if step 1 found rows AND they look garbled afterwards:
-- UPDATE messages
--   SET Message = CONVERT(CAST(CONVERT(Message USING latin1) AS BINARY) USING utf8mb4)
--   WHERE Message <> CONVERT(Message USING ascii);
