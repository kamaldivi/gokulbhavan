-- ── Sloka standalone tables ──────────────────────────────────────────────────
-- Run once against dbs15655922. IF NOT EXISTS guards make it safe to re-run.
-- Import with phpMyAdmin charset: utf8  (SET NAMES below handles the rest)

SET NAMES utf8mb4;

-- ── 1. Category lookup ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sloka_category` (
  `category_code` varchar(20)  NOT NULL,
  `category_name` varchar(200) NOT NULL COMMENT 'Full transliterated name with diacritics',
  `image_path`    varchar(400) DEFAULT NULL,
  PRIMARY KEY (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sloka categories — standalone, not linked to audio_category';

-- ── 2. Scripture reference lookup ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `scripture` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        varchar(300)     NOT NULL COMMENT 'Full canonical name, e.g. Śrīmad-Bhāgavatam',
  `short_title` varchar(30)      DEFAULT NULL COMMENT 'Abbreviation, e.g. SB, BG, CC — nullable until curated',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Scripture / source text reference lookup';

-- ── 3. Sloka core table ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sloka` (
  `id`              int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_code`   varchar(20)      NOT NULL,
  `slokamrtam_ref`  varchar(20)      DEFAULT NULL  COMMENT 'Chapter.verse in Slokamrtam book, e.g. 1.2',
  `title`           varchar(300)     DEFAULT NULL  COMMENT 'Contextual book title from Slokamrtam (often NULL)',
  `search_title`    varchar(300)     DEFAULT NULL  COMMENT 'First line of sloka_text, diacritics stripped — for search',
  `sloka_text`      text             NOT NULL      COMMENT 'Full transliterated Sanskrit, multiline',
  `scripture_id`    int(10) UNSIGNED DEFAULT NULL  COMMENT 'FK to scripture — NULL until curated',
  `scripture_ref`   varchar(300)     DEFAULT NULL  COMMENT 'Raw citation string, e.g. BRS 1.1.11, SB 11.3.21',
  `word_by_word`    mediumtext       DEFAULT NULL,
  `translation`     mediumtext       DEFAULT NULL,
  `audio_file_path` varchar(400)     DEFAULT NULL  COMMENT 'Populated when audio recording exists',
  `created_at`      datetime         NOT NULL DEFAULT current_timestamp(),
  `updated_at`      datetime         NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category`  (`category_code`),
  KEY `idx_scripture` (`scripture_id`),
  KEY `idx_search`    (`search_title`(100)),
  CONSTRAINT `fk_sloka_cat` FOREIGN KEY (`category_code`) REFERENCES `sloka_category` (`category_code`),
  CONSTRAINT `fk_sloka_scr` FOREIGN KEY (`scripture_id`)  REFERENCES `scripture` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sanskrit slokas — text, word-by-word, translation, optional audio';

-- ── Seed: sloka_category ─────────────────────────────────────────────────────
-- 24 categories derived from sloka_data.csv. Order matches CSV chapter sequence.
INSERT IGNORE INTO `sloka_category` (`category_code`, `category_name`) VALUES
  ('MANGAL',     'Maṅgalācaraṇa'),
  ('GURU',       'Guru-tattva'),
  ('SADHANA',    'Sādhana-bhakti-tattva'),
  ('NAMA',       'Nāma-tattva'),
  ('ABHIDHEYA',  'Abhidheya-tattva'),
  ('BHAGAVAT',   'Bhagavat-tattva'),
  ('KRISHNA',    'Kṛṣṇa-tattva'),
  ('GAURA',      'Gaura-tattva'),
  ('NITYANANDA', 'Nityānanda-tattva'),
  ('JIVA',       'Jīva-tattva'),
  ('SHAKTI',     'Śakti-tattva'),
  ('ACINTYA',    'Acintya-bhedābheda-tattva'),
  ('VAISHNAVA',  'Vaiṣṇava-tattva'),
  ('PRAMANA',    'Pramāṇa-tattva'),
  ('VARNASRAMA', 'Varṇāśrama-dharma-tattva'),
  ('BHAVA',      'Bhāva-bhakti'),
  ('PREMA',      'Prayojana-tattva – Prema'),
  ('RASA',       'Bhagavat-rasa-tattva'),
  ('VIPRALAMBHA','Vipralambha Rasa'),
  ('SAMBHOGA',   'Sambhoga Rasa'),
  ('RADHA',      'Rādhā-tattva'),
  ('RADHA-DASYA','Rādhā Dāsyam'),
  ('MADHURENA',  'Madhureṇa Samāpayet'),
  ('OTHER',      'Other Ślokas');
