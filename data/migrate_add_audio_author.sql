-- Migration: extract audio_track.author (free text) → audio_author reference table
-- Run once against an existing database that still has the varchar `author` column.
-- Safe to run in any order; uses IF NOT EXISTS / IF EXISTS guards.

-- 1. Create the canonical authors table
CREATE TABLE IF NOT EXISTS `audio_author` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `author_name` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_author_name` (`author_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Canonical list of audio track authors';

-- 2. Seed from distinct non-empty author strings already in audio_track
INSERT IGNORE INTO `audio_author` (`author_name`)
SELECT DISTINCT TRIM(`author`)
FROM   `audio_track`
WHERE  `author` IS NOT NULL
  AND  TRIM(`author`) <> '';

-- 3. Add the FK column (nullable, so unset tracks are fine)
ALTER TABLE `audio_track`
  ADD COLUMN IF NOT EXISTS `author_id` int(11) DEFAULT NULL
    COMMENT 'FK → audio_author.id' AFTER `singer`;

-- 4. Back-fill author_id from the canonical name match
UPDATE `audio_track` t
  JOIN `audio_author` a ON a.author_name = TRIM(t.author)
SET    t.author_id = a.id
WHERE  t.author IS NOT NULL AND TRIM(t.author) <> '';

-- 5. Add index and FK constraint
ALTER TABLE `audio_track`
  ADD KEY IF NOT EXISTS `idx_author_id` (`author_id`);

-- Add FK only if it doesn't already exist (MariaDB / MySQL 8+)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME        = 'audio_track'
    AND CONSTRAINT_NAME   = 'fk_audio_track_author'
    AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE `audio_track`
     ADD CONSTRAINT `fk_audio_track_author`
     FOREIGN KEY (`author_id`) REFERENCES `audio_author` (`id`) ON DELETE SET NULL',
  'SELECT "FK already exists — skipping"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Drop the old free-text column
ALTER TABLE `audio_track`
  DROP COLUMN IF EXISTS `author`;
