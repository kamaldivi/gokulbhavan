-- Migration: extend `question` table for Ask Guruji workflow
-- Run once against the production database.
-- Safe to re-run (uses IF NOT EXISTS / MODIFY with defaults).

-- 1. Change status enum from (new, read) → (submitted, accepted, rejected, responded)
--    Map old 'new' → 'submitted', old 'read' → 'accepted'
ALTER TABLE `question`
  MODIFY `status` enum('submitted','accepted','rejected','responded')
    NOT NULL DEFAULT 'submitted';

UPDATE `question` SET `status` = 'submitted' WHERE `status` NOT IN ('submitted','accepted','rejected','responded');

-- 2. Add visibility column (default private so nothing is accidentally exposed)
ALTER TABLE `question`
  ADD COLUMN IF NOT EXISTS `visibility` enum('public','private')
    NOT NULL DEFAULT 'private' AFTER `status`;

-- 3. Add response column
ALTER TABLE `question`
  ADD COLUMN IF NOT EXISTS `response` mediumtext DEFAULT NULL AFTER `visibility`;

-- 4. Add index for public Q&A query
ALTER TABLE `question`
  ADD INDEX IF NOT EXISTS `idx_public_qa` (`status`, `visibility`);
