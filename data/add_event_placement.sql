-- Migration: add event_placement column to post table
-- Run once on the live MariaDB database (dbs15655922)
-- Safe to re-run: uses IF NOT EXISTS guard via column check

ALTER TABLE `post`
  ADD COLUMN IF NOT EXISTS `event_placement`
    SET('home','tamil','programs') NULL DEFAULT NULL
    AFTER `event_location`;
