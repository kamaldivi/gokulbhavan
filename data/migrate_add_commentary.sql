-- migrate_add_commentary.sql
-- Adds a commentary column to the sloka table.
-- Run once via phpMyAdmin before deploying the updated API and admin UI.

SET NAMES utf8mb4;

ALTER TABLE `sloka`
  ADD COLUMN `commentary` mediumtext DEFAULT NULL
    COMMENT 'Commentary by Gurudev or other acharyas — populated manually by admin'
  AFTER `translation`;
