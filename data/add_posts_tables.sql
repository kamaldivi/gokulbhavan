-- Migration: add post and post_media tables
-- Run once on the live MariaDB database (dbs15655922)
-- Safe to re-run: uses CREATE TABLE IF NOT EXISTS

CREATE TABLE IF NOT EXISTS `post` (
  `id`               int(11) NOT NULL AUTO_INCREMENT,
  `post_type`        enum('blog','event') NOT NULL,
  `slug`             varchar(300) NOT NULL,
  `title`            varchar(300) NOT NULL,
  `extract`          text DEFAULT NULL,
  `body`             mediumtext DEFAULT NULL,
  `cover_image_path` varchar(400) DEFAULT NULL,
  `status`           enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at`     datetime DEFAULT NULL,
  `event_date`       date DEFAULT NULL,
  `event_end_date`   date DEFAULT NULL,
  `event_location`   varchar(300) DEFAULT NULL,
  `created_at`       datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at`       datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_type_status` (`post_type`, `status`),
  KEY `idx_published_at` (`published_at`),
  KEY `idx_event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Blog posts and event postings';

CREATE TABLE IF NOT EXISTS `post_media` (
  `id`         int(11) NOT NULL AUTO_INCREMENT,
  `post_id`    int(11) NOT NULL,
  `media_type` enum('image','youtube','playlist','harikatha','link') NOT NULL,
  `media_ref`  varchar(500) NOT NULL,
  -- image:     relative path e.g. media/posts/42/img-abc123.jpg
  -- youtube:   YouTube video ID (11 chars) or full URL
  -- playlist:  video_playlist.playlist_id value (YouTube PL ID string)
  -- harikatha: full URL of the audio page
  -- link:      any external URL
  `caption`    varchar(300) DEFAULT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  CONSTRAINT `fk_post_media_post`
    FOREIGN KEY (`post_id`) REFERENCES `post` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Media attachments for blog/event posts (images, YouTube, playlists, links)';
