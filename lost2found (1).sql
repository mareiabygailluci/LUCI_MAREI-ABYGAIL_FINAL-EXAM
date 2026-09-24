-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 24, 2026 at 12:52 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lost2found`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `created_at`) VALUES
(1, 'Electronics', '2026-09-22 12:53:09'),
(2, 'Personal Items', '2026-09-22 12:53:09'),
(3, 'Documents', '2026-09-22 12:53:09'),
(4, 'Accessories', '2026-09-22 12:53:09'),
(5, 'Clothing', '2026-09-22 12:53:09'),
(6, 'Keys', '2026-09-22 12:53:09'),
(7, 'Bags', '2026-09-22 12:53:09'),
(8, 'Others', '2026-09-22 12:53:09');

-- --------------------------------------------------------

--
-- Table structure for table `claim_requests`
--

CREATE TABLE `claim_requests` (
  `id` int(11) NOT NULL,
  `found_item_id` int(11) NOT NULL,
  `claimant_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `proof` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `claim_requests`
--

INSERT INTO `claim_requests` (`id`, `found_item_id`, `claimant_id`, `reason`, `proof`, `status`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 1, 2, 'This is my wallet - it has my school ID inside and a small scratch on the back left corner.', NULL, 'pending', NULL, NULL, '2026-09-22 12:53:10');

-- --------------------------------------------------------

--
-- Table structure for table `found_items`
--

CREATE TABLE `found_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `color` varchar(40) NOT NULL,
  `brand` varchar(60) DEFAULT NULL,
  `date_found` date NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','matched','returned','closed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reporter_privacy` enum('name','anonymous') NOT NULL DEFAULT 'name'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `found_items`
--

INSERT INTO `found_items` (`id`, `user_id`, `category_id`, `location_id`, `item_name`, `description`, `color`, `brand`, `date_found`, `photo`, `status`, `created_at`, `updated_at`, `reporter_privacy`) VALUES
(1, 3, 2, 1, 'Black Wallet', 'Found near the barangay hall entrance. Small scratch on the back left corner.', 'Black', 'Seiko', '2026-09-14', NULL, 'matched', '2026-09-22 12:53:09', '2026-09-22 12:53:10', 'name'),
(2, 2, 4, 2, 'Blue Umbrella', 'Left on the bleachers of the covered court.', 'Blue', '', '2026-09-15', NULL, 'matched', '2026-09-22 12:53:10', '2026-09-22 12:53:10', 'name'),
(3, 3, 6, 3, 'Key Set with Blue Keychain', 'Three keys on a blue rubber keychain, turned over to our office.', 'Silver', '', '2026-09-19', NULL, 'pending', '2026-09-22 12:53:10', '2026-09-22 12:53:10', 'name');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_name`, `created_at`) VALUES
(1, 'Barangay Hall', '2026-09-22 12:53:09'),
(2, 'Barangay Covered Court', '2026-09-22 12:53:09'),
(3, 'Health Center', '2026-09-22 12:53:09'),
(4, 'Purok 1', '2026-09-22 12:53:09'),
(5, 'Purok 2', '2026-09-22 12:53:09'),
(6, 'Purok 3', '2026-09-22 12:53:09'),
(7, 'Chapel', '2026-09-22 12:53:09'),
(8, 'Other', '2026-09-22 12:53:09');

-- --------------------------------------------------------

--
-- Table structure for table `lost_items`
--

CREATE TABLE `lost_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `color` varchar(40) NOT NULL,
  `brand` varchar(60) DEFAULT NULL,
  `date_lost` date NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','matched','claimed','closed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reporter_privacy` enum('name','anonymous') NOT NULL DEFAULT 'name'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lost_items`
--

INSERT INTO `lost_items` (`id`, `user_id`, `category_id`, `location_id`, `item_name`, `description`, `color`, `brand`, `date_lost`, `photo`, `status`, `created_at`, `updated_at`, `reporter_privacy`) VALUES
(1, 2, 2, 1, 'Black Seiko Wallet', 'Lost near the barangay hall entrance. It has a small scratch on the back and contains my school ID.', 'Black', 'Seiko', '2026-09-14', NULL, 'matched', '2026-09-22 12:53:09', '2026-09-22 12:53:10', 'name'),
(2, 3, 4, 2, 'Blue Umbrella', 'Auto-open umbrella with a wooden handle. Left somewhere around the covered court.', 'Blue', '', '2026-09-15', NULL, 'matched', '2026-09-22 12:53:09', '2026-09-22 12:53:10', 'name'),
(3, 2, 3, 5, 'School ID - Libis NHS', 'Student ID attached to a blue lanyard. Lost somewhere along Purok 2.', 'White', '', '2026-09-18', NULL, 'pending', '2026-09-22 12:53:09', '2026-09-22 12:53:09', 'name');

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `lost_item_id` int(11) NOT NULL,
  `found_item_id` int(11) NOT NULL,
  `match_score` int(11) NOT NULL,
  `status` enum('pending','confirmed','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `lost_item_id`, `found_item_id`, `match_score`, `status`, `created_at`) VALUES
(1, 1, 1, 100, 'pending', '2026-09-22 12:53:10'),
(2, 2, 2, 100, 'pending', '2026-09-22 12:53:10');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `type` enum('match','claim','status','system') DEFAULT 'system',
  `link` varchar(150) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(1, 2, 'Welcome to Lost2Found! Report lost or found items and let the system find possible matches for you.', 'system', NULL, 1, '2026-09-22 12:53:10'),
(2, 3, 'Welcome to Lost2Found! Report lost or found items and let the system find possible matches for you.', 'system', NULL, 0, '2026-09-22 12:53:10'),
(3, 2, 'A found item may match your lost item report \"Black Seiko Wallet\" (match score: 100%).', 'match', 'resident/matches.php', 1, '2026-09-22 12:53:10'),
(4, 3, 'Your found item \"Black Wallet\" may match a lost item report (match score: 100%).', 'match', 'resident/matches.php', 0, '2026-09-22 12:53:10'),
(5, 3, 'A found item may match your lost item report \"Blue Umbrella\" (match score: 100%).', 'match', 'resident/matches.php', 0, '2026-09-22 12:53:10'),
(6, 2, 'Your found item \"Blue Umbrella\" may match a lost item report (match score: 100%).', 'match', 'resident/matches.php', 1, '2026-09-22 12:53:11'),
(13, 8, 'Welcome to Lost2Found! Your registration is being reviewed by the barangay admin. You can log in once your proof of residency is verified.', 'system', NULL, 0, '2026-09-23 10:35:19'),
(14, 1, 'New resident registration pending approval: Marei Abygail Luci (Purok 1). Please review the uploaded proof of residency.', 'system', 'admin/users.php', 0, '2026-09-23 10:35:19'),
(15, 8, 'Your registration has been approved! Your proof of residency was verified — you may now log in and start reporting lost or found items.', 'status', 'auth/login.php', 0, '2026-09-23 11:24:53'),
(16, 8, 'Your registration has been approved! Your proof of residency was verified — you may now log in and start reporting lost or found items.', 'status', 'auth/login.php', 0, '2026-09-23 11:35:26'),
(17, 8, 'Your registration was not approved. Reason: Ang upload na ID ay malabo. Mag-upload ng mas malinaw na litrato. — Please visit the barangay office or register again with a clearer photo of your valid ID or barangay certificate.', 'status', NULL, 0, '2026-09-23 11:35:51'),
(18, 8, 'Your registration was not approved. Reason: The uploaded proof of residency is unreadable or incomplete. — Please visit the barangay office or register again with a clearer photo of your valid ID or barangay certificate.', 'status', NULL, 0, '2026-09-23 11:39:08'),
(19, 8, 'Your registration was not approved. Reason: Hindi po tugma ang pangalan sa ID. — Please visit the barangay office or register again with a clearer photo of your valid ID or barangay certificate.', 'status', NULL, 0, '2026-09-23 11:39:09'),
(20, 8, 'Your registration was not approved. Reason: The name on your proof of residency does not match the name you registered. Please upload an ID under your own name. — Please visit the barangay office or register again with a clearer photo of your valid ID or ', 'status', NULL, 0, '2026-09-23 11:40:37'),
(21, 1, 'Marei Abygail Luci resubmitted their proof of residency. Please review the new upload.', 'system', 'admin/users.php', 0, '2026-09-23 11:51:01'),
(23, 8, 'Your registration was not approved. Reason: The name on your proof of residency does not match the name you registered. Please upload an ID under your own name. — Please visit the barangay office or register again with a clearer photo of your valid ID or ', 'status', NULL, 0, '2026-09-23 11:53:06'),
(24, 1, 'Marei Abygail Luci resubmitted their proof of residency. Please review the new upload.', 'system', 'admin/users.php', 0, '2026-09-23 12:10:03'),
(25, 8, 'Your registration was not approved. Reason: The submitted proof does not show residence within Barangay Libis. This system is exclusive to Libis residents. — Please visit the barangay office or register again with a clearer photo of your valid ID or baran', 'status', NULL, 0, '2026-09-23 12:10:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` varchar(150) NOT NULL,
  `role` enum('resident','admin') DEFAULT 'resident',
  `status` enum('pending','active','inactive','rejected') DEFAULT 'pending',
  `residency_proof` varchar(255) DEFAULT NULL,
  `review_remarks` varchar(255) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `show_name` tinyint(1) NOT NULL DEFAULT 1,
  `anonymous_name` varchar(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `contact_number`, `address`, `role`, `status`, `residency_proof`, `review_remarks`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`, `show_name`, `anonymous_name`) VALUES
(1, 'Barangay Admin', 'admin@lost2found.ph', '$2y$10$b.suEiJp.EYHOr3Oyq.Yo.yMdx5rDCYfzZ/iXZdINgztFrqLoPgp.', '0917 000 0000', 'Barangay Hall, Barangay Libis', 'admin', 'active', NULL, NULL, NULL, NULL, '2026-09-22 12:53:09', '2026-09-22 13:44:55', 1, 'Anonymous Quiet Dolphin 8919'),
(2, 'Juan Dela Cruz', 'juan@example.com', '$2y$10$uSGIFQ9lBtAmsTYTbHv0QOAgmW/ucR3ZkITUhv25mQwGqMp5vOWDe', '0917 111 2222', 'Purok 1, Barangay Libis', 'resident', 'active', NULL, NULL, NULL, NULL, '2026-09-22 12:53:09', '2026-09-22 13:44:55', 1, 'Anonymous Brave Penguin 7838'),
(3, 'Maria Santos', 'maria@example.com', '$2y$10$6eWM32XWMqL9luWoPSX1a.Mrd2QNcNIdQg2e.2CTHyHyjCQVLmNGu', '0917 333 4444', 'Purok 2, Barangay Libis', 'resident', 'active', NULL, NULL, NULL, NULL, '2026-09-22 12:53:09', '2026-09-22 13:44:55', 1, 'Anonymous Kind Falcon 6757'),
(8, 'Marei Abygail Luci', 'mareiabygail.luci@gmail.com', '$2y$10$chF9Q/oVN1zNtZ//LsvEounJPHg9hZ8r52DBG2nvMZKj2U.yus3Mq', '09456293903', 'Purok 1', 'resident', 'rejected', 'proof_20260923201003_c409699b3eae.jpg', 'The submitted proof does not show residence within Barangay Libis. This system is exclusive to Libis residents.', 1, '2026-09-23 20:10:36', '2026-09-23 10:35:18', '2026-09-23 12:10:36', 1, 'Anonymous Steady Seal 1352');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `claim_requests`
--
ALTER TABLE `claim_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `found_item_id` (`found_item_id`),
  ADD KEY `claimant_id` (`claimant_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `found_items`
--
ALTER TABLE `found_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_name` (`location_name`);

--
-- Indexes for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pair` (`lost_item_id`,`found_item_id`),
  ADD KEY `found_item_id` (`found_item_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `anonymous_name` (`anonymous_name`),
  ADD UNIQUE KEY `uq_users_anonymous_name` (`anonymous_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `claim_requests`
--
ALTER TABLE `claim_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `found_items`
--
ALTER TABLE `found_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `lost_items`
--
ALTER TABLE `lost_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `claim_requests`
--
ALTER TABLE `claim_requests`
  ADD CONSTRAINT `claim_requests_ibfk_1` FOREIGN KEY (`found_item_id`) REFERENCES `found_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `claim_requests_ibfk_2` FOREIGN KEY (`claimant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `claim_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `found_items`
--
ALTER TABLE `found_items`
  ADD CONSTRAINT `found_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `found_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `found_items_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD CONSTRAINT `lost_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lost_items_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `lost_items_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`lost_item_id`) REFERENCES `lost_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`found_item_id`) REFERENCES `found_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
