-- Migration: add cover_image_path to program table
-- Run once against the live database.
-- Safe to re-run (IF NOT EXISTS guard via SHOW COLUMNS check).

SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'program'
      AND COLUMN_NAME  = 'cover_image_path'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE program ADD COLUMN cover_image_path VARCHAR(400) DEFAULT NULL AFTER event_time',
    'SELECT ''cover_image_path already exists, skipping'' AS migration_note'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
