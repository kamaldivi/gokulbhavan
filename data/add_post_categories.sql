-- Migration: post_category table + category_id/episode_number on post
-- Run once on the live MariaDB database (dbs15655922)
-- Safe to re-run: uses IF NOT EXISTS guards throughout

-- 1. Create post_category table
CREATE TABLE IF NOT EXISTS `post_category` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `name`          varchar(200) NOT NULL,
  `slug`          varchar(200) NOT NULL,
  `description`   text DEFAULT NULL,
  `post_type`     enum('blog','event') NOT NULL DEFAULT 'blog',
  `is_sequential` tinyint(1) NOT NULL DEFAULT 0,   -- 0=date-sorted, 1=episode-ordered
  `placement`     varchar(50) DEFAULT NULL,          -- 'home','tamil','programs' for event categories
  `sort_order`    smallint(6) NOT NULL DEFAULT 0,
  `created_at`    datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categories for blog posts and events';

-- 2. Seed initial categories (INSERT IGNORE is safe to re-run)
INSERT IGNORE INTO `post_category` (name, slug, post_type, is_sequential, placement, sort_order) VALUES
  ('BK Blogs',            'bk-blogs',            'blog',  0, NULL,    1),
  ('Vaishnava Etiquette', 'vaishnava-etiquette',  'blog',  1, NULL,    2),
  ('Gaudiya Kantahaara',  'gaudiya-kantahaara',   'blog',  1, NULL,    3),
  ('Slokamrtam',          'slokamrtam',            'blog',  1, NULL,    4),
  ('Home Events',         'home-events',           'event', 0, 'home',  1),
  ('Tamil Events',        'tamil-events',          'event', 0, 'tamil', 2);

-- 3. Add category_id and episode_number columns to post
ALTER TABLE `post`
  ADD COLUMN IF NOT EXISTS `category_id`    int(11) NULL AFTER `post_type`,
  ADD COLUMN IF NOT EXISTS `episode_number` smallint(6) NULL AFTER `category_id`;

ALTER TABLE `post`
  ADD KEY IF NOT EXISTS `idx_category_id` (`category_id`);

-- 4. Migrate existing blog posts → BK Blogs category
UPDATE `post`
  SET `category_id` = (SELECT `id` FROM `post_category` WHERE `slug` = 'bk-blogs')
  WHERE `post_type` = 'blog' AND `category_id` IS NULL;

-- 5. Migrate existing events by event_placement → event category
--    Tamil placement → Tamil Events (only if not yet migrated)
UPDATE `post`
  SET `category_id` = (SELECT `id` FROM `post_category` WHERE `slug` = 'tamil-events')
  WHERE `post_type` = 'event'
    AND `category_id` IS NULL
    AND `event_placement` IS NOT NULL
    AND FIND_IN_SET('tamil', `event_placement`);

--    Home placement → Home Events (only if not yet migrated)
UPDATE `post`
  SET `category_id` = (SELECT `id` FROM `post_category` WHERE `slug` = 'home-events')
  WHERE `post_type` = 'event'
    AND `category_id` IS NULL
    AND `event_placement` IS NOT NULL
    AND FIND_IN_SET('home', `event_placement`);
