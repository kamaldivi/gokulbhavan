-- migrate_restructure_category_scripture.sql
-- Run once against prod DB via phpMyAdmin (charset: utf8).
-- Adds surrogate id PK + image_path + sort_order to sloka_category.
-- Adds image_path + sort_order to scripture.
-- Recreates FK with ON UPDATE CASCADE so category code renames propagate automatically.

SET NAMES utf8mb4;

-- ── 1. Drop existing FK (references old PK on category_code) ─────────────────
ALTER TABLE `sloka` DROP FOREIGN KEY `fk_sloka_cat`;

-- ── 2. Restructure sloka_category ─────────────────────────────────────────────
ALTER TABLE `sloka_category`
  ADD COLUMN `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  ADD COLUMN `sort_order` SMALLINT(5) UNSIGNED      NOT NULL DEFAULT 0 AFTER `image_path`,
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_code` (`category_code`);

-- ── 3. Re-add FK with ON UPDATE CASCADE ───────────────────────────────────────
-- Now renaming category_code auto-cascades to all sloka rows.
ALTER TABLE `sloka`
  ADD CONSTRAINT `fk_sloka_cat`
  FOREIGN KEY (`category_code`) REFERENCES `sloka_category` (`category_code`)
  ON UPDATE CASCADE;

-- ── 4. Add image_path + sort_order to scripture ───────────────────────────────
ALTER TABLE `scripture`
  ADD COLUMN `image_path` VARCHAR(400) DEFAULT NULL   AFTER `short_title`,
  ADD COLUMN `sort_order` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0 AFTER `image_path`;

-- ── Done ──────────────────────────────────────────────────────────────────────
-- sloka_category now: id (PK), category_code (UNIQUE), category_name, image_path, sort_order
-- scripture now:      id (PK), name, short_title, image_path, sort_order
