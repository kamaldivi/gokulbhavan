-- Migration: Daily Highlights feature
-- Run on live DB: dbs15655922
-- Note: author column was already added to audio_track separately (not repeated here)

-- Daily highlight selection — one row per content type, auto-refreshed at 3am server time
CREATE TABLE IF NOT EXISTS `daily_highlight` (
  `content_type` enum('bhajan','sloka','sankirtan','video') NOT NULL,
  `ref_id`        varchar(100) NOT NULL COMMENT 'track_id for audio types, video_id for video',
  `selected_date` date NOT NULL,
  PRIMARY KEY (`content_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Current daily curated selection — one row per content type';

-- History of daily selections — used to enforce 7-day non-repetition per content type
CREATE TABLE IF NOT EXISTS `highlight_history` (
  `id`           int(11) NOT NULL AUTO_INCREMENT,
  `content_type` enum('bhajan','sloka','sankirtan','video') NOT NULL,
  `ref_id`       varchar(100) NOT NULL,
  `shown_on`     date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_date` (`content_type`, `shown_on`),
  KEY `idx_type_date` (`content_type`, `shown_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='History of daily highlight selections for 7-day non-repetition window';
