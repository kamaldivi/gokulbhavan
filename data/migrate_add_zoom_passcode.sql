-- Migration: add zoom_passcode to program table
ALTER TABLE `program`
  ADD COLUMN `zoom_passcode` varchar(50) DEFAULT NULL AFTER `zoom_url`;
