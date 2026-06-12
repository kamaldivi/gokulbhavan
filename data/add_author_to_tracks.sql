-- Migration: add author column to audio_track
-- Run once on the live MariaDB database (dbs15655922)
-- Safe to re-run: uses IF NOT EXISTS guard

-- 1. Add author column
ALTER TABLE `audio_track`
  ADD COLUMN IF NOT EXISTS `author` varchar(150) NULL AFTER `track_name`;

-- 2. Seed initial author data from CSV (track_id, author)
--    Replace the VALUES below with rows from your CSV, e.g.:
--
--    UPDATE `audio_track` SET `author` = 'Narottama Dasa Thakura' WHERE `track_id` = 'A-01';
--    UPDATE `audio_track` SET `author` = 'Bhaktivinoda Thakura'   WHERE `track_id` = 'A-02';
--
--    Or bulk-load with INSERT … ON DUPLICATE KEY UPDATE:
--
--    INSERT INTO `audio_track` (track_id, author)
--    VALUES
--      ('A-01', 'Narottama Dasa Thakura'),
--      ('A-02', 'Bhaktivinoda Thakura')
--    ON DUPLICATE KEY UPDATE author = VALUES(author);
