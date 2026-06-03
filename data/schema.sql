-- phpMyAdmin SQL Dump
-- version 4.9.11
-- https://www.phpmyadmin.net/
--
-- Host: db5020426059.hosting-data.io
-- Generation Time: Jun 03, 2026 at 03:24 PM
-- Server version: 10.11.15-MariaDB-deb11-log
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbs15655922`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcement`
--

CREATE TABLE `announcement` (
  `id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL,
  `body` text DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audio_category`
--

CREATE TABLE `audio_category` (
  `category_code` varchar(20) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `audio_family` enum('bhajan','sankirtan','sloka','album') NOT NULL,
  `sort_order` smallint(6) DEFAULT NULL,
  `image_path` varchar(400) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audio content categories - bhajan, sankirtan, sloka, and album volumes';

-- --------------------------------------------------------

--
-- Table structure for table `audio_singer_version`
--

CREATE TABLE `audio_singer_version` (
  `id` int(11) NOT NULL,
  `track_id` varchar(20) NOT NULL,
  `singer` varchar(150) NOT NULL,
  `audio_file_path` varchar(400) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Singer variant recordings - one row per singer per bhajan track';

-- --------------------------------------------------------

--
-- Table structure for table `audio_track`
--

CREATE TABLE `audio_track` (
  `track_id` varchar(20) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `track_name` varchar(300) NOT NULL,
  `singer` varchar(150) DEFAULT NULL,
  `track_num` smallint(6) DEFAULT NULL,
  `audio_file_path` varchar(400) NOT NULL,
  `lyrics_file_path` varchar(400) DEFAULT NULL,
  `base_track_path` varchar(400) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `lyrics_source_track_id` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Primary audio tracks - bhajans, slokas, sankirtans, and album tracks';

-- --------------------------------------------------------

--
-- Table structure for table `global`
--

CREATE TABLE `global` (
  `id` int(11) NOT NULL,
  `current_semester_id` int(11) NOT NULL,
  `current_semester_name` varchar(15) NOT NULL,
  `egod` varchar(100) NOT NULL,
  `pgod` varchar(100) NOT NULL,
  `youtube_api_key` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lyrics`
--

CREATE TABLE `lyrics` (
  `id` int(10) UNSIGNED NOT NULL,
  `track_id` varchar(20) NOT NULL,
  `lang` varchar(10) NOT NULL DEFAULT 'en',
  `content_type` enum('lyrics','meaning') NOT NULL,
  `body` mediumtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `time_est` varchar(20) DEFAULT NULL,
  `zoom_url` varchar(300) DEFAULT NULL,
  `youtube_live_url` varchar(300) DEFAULT NULL,
  `video_playlist` varchar(255) DEFAULT NULL,
  `teacher` varchar(150) DEFAULT NULL,
  `duration_min` smallint(6) NOT NULL DEFAULT 90,
  `platform` varchar(50) NOT NULL DEFAULT 'Zoom',
  `language` varchar(50) NOT NULL DEFAULT 'English',
  `site_id` varchar(50) NOT NULL DEFAULT 'gokulbhavan',
  `order_num` smallint(6) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Weekly recurring program schedules for the gokulbhavan.org website';

-- --------------------------------------------------------

--
-- Table structure for table `question`
--

CREATE TABLE `question` (
  `id` int(10) UNSIGNED NOT NULL,
  `registration_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `question` text NOT NULL,
  `status` enum('new','read') NOT NULL DEFAULT 'new',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration`
--

CREATE TABLE `registration` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `spiritual_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `address1` varchar(100) DEFAULT NULL,
  `address2` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `language_pref` varchar(50) NOT NULL DEFAULT 'English',
  `location_id` int(11) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Devotee registrations from the new gokulbhavan.org website';

-- --------------------------------------------------------

--
-- Table structure for table `sanga`
--

CREATE TABLE `sanga` (
  `id` int(11) NOT NULL,
  `sanga_name` varchar(200) NOT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `region` varchar(200) DEFAULT NULL,
  `flag` varchar(10) DEFAULT NULL,
  `address_line1` varchar(300) DEFAULT NULL,
  `address_line2` varchar(300) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `contacts_list` text DEFAULT NULL,
  `phone` varchar(60) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `map_url` text DEFAULT NULL,
  `service_times` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video`
--

CREATE TABLE `video` (
  `video_id` varchar(50) NOT NULL,
  `video_title` varchar(255) NOT NULL,
  `thumbnail_url` varchar(400) NOT NULL,
  `published_date` date NOT NULL,
  `updated_date` date NOT NULL,
  `track_id` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_category`
--

CREATE TABLE `video_category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_playlist`
--

CREATE TABLE `video_playlist` (
  `playlist_id` varchar(100) NOT NULL,
  `playlist_name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `video_playlist_map`
--

CREATE TABLE `video_playlist_map` (
  `video_id` varchar(50) NOT NULL,
  `playlist_id` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcement`
--
ALTER TABLE `announcement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audio_category`
--
ALTER TABLE `audio_category`
  ADD PRIMARY KEY (`category_code`);

--
-- Indexes for table `audio_singer_version`
--
ALTER TABLE `audio_singer_version`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_track_id` (`track_id`);

--
-- Indexes for table `audio_track`
--
ALTER TABLE `audio_track`
  ADD PRIMARY KEY (`track_id`),
  ADD KEY `idx_category_code` (`category_code`);

--
-- Indexes for table `global`
--
ALTER TABLE `global`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lyrics`
--
ALTER TABLE `lyrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_track_lang_type` (`track_id`,`lang`,`content_type`),
  ADD KEY `idx_track` (`track_id`);

--
-- Indexes for table `program`
--
ALTER TABLE `program`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_day` (`day_of_week`);

--
-- Indexes for table `question`
--
ALTER TABLE `question`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted` (`submitted_at`),
  ADD KEY `idx_reg_id` (`registration_id`);

--
-- Indexes for table `registration`
--
ALTER TABLE `registration`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_location` (`location_id`),
  ADD KEY `idx_submitted` (`submitted_at`);

--
-- Indexes for table `sanga`
--
ALTER TABLE `sanga`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `video`
--
ALTER TABLE `video`
  ADD PRIMARY KEY (`video_id`);

--
-- Indexes for table `video_category`
--
ALTER TABLE `video_category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `video_playlist`
--
ALTER TABLE `video_playlist`
  ADD PRIMARY KEY (`playlist_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `video_playlist_map`
--
ALTER TABLE `video_playlist_map`
  ADD PRIMARY KEY (`video_id`,`playlist_id`),
  ADD KEY `playlist_id` (`playlist_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcement`
--
ALTER TABLE `announcement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audio_singer_version`
--
ALTER TABLE `audio_singer_version`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `global`
--
ALTER TABLE `global`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lyrics`
--
ALTER TABLE `lyrics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program`
--
ALTER TABLE `program`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question`
--
ALTER TABLE `question`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration`
--
ALTER TABLE `registration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sanga`
--
ALTER TABLE `sanga`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_category`
--
ALTER TABLE `video_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audio_singer_version`
--
ALTER TABLE `audio_singer_version`
  ADD CONSTRAINT `audio_singer_version_ibfk_1` FOREIGN KEY (`track_id`) REFERENCES `audio_track` (`track_id`) ON DELETE CASCADE;

--
-- Constraints for table `audio_track`
--
ALTER TABLE `audio_track`
  ADD CONSTRAINT `audio_track_ibfk_1` FOREIGN KEY (`category_code`) REFERENCES `audio_category` (`category_code`);

--
-- Constraints for table `video_playlist`
--
ALTER TABLE `video_playlist`
  ADD CONSTRAINT `video_playlist_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `video_category` (`category_id`);

--
-- Constraints for table `video_playlist_map`
--
ALTER TABLE `video_playlist_map`
  ADD CONSTRAINT `video_playlist_map_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `video` (`video_id`),
  ADD CONSTRAINT `video_playlist_map_ibfk_2` FOREIGN KEY (`playlist_id`) REFERENCES `video_playlist` (`playlist_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
