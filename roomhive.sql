-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 29, 2026 at 03:30 AM
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
-- Database: `roomhive`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `host_payout_amount` decimal(10,2) DEFAULT NULL,
  `platform_fee_amount` decimal(10,2) DEFAULT NULL,
  `payout_at` datetime DEFAULT NULL,
  `refunded_amount` decimal(10,2) DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed','rejected') NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `booked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checkin_date` date DEFAULT NULL,
  `checkout_date` date DEFAULT NULL,
  `guests` varchar(10) DEFAULT NULL,
  `payment_status` enum('pending','paid','refunded','failed') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `user_id`, `first_name`, `last_name`, `email`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 15, 'kim', 'divino', 'mik@gmail.com', 'ajjflakjslfk', 'nkfahsfkjsahkfska', 'open', '2026-09-23 14:37:42'),
(2, 15, 'kim', 'divino', 'mik@gmail.com', 'ajjflakjslfk', 'nkfahsfkjsahkfska', 'open', '2026-09-23 14:51:59');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `host_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `host_applications`
--

CREATE TABLE `host_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `id_type` varchar(50) NOT NULL,
  `id_number` varchar(100) NOT NULL,
  `id_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `host_applications`
--

INSERT INTO `host_applications` (`id`, `user_id`, `full_name`, `email`, `phone`, `age`, `location`, `id_type`, `id_number`, `id_file`, `status`, `created_at`, `updated_at`) VALUES
(11, 20, 'host1', 'host1@gmail.com', '09275693574', 20, 'negros oriental, dauin', 'national-id', '2414124124', '/webprogg/uploads/host_ids/host_1790310482_6ab5f85222b47.png', 'approved', '2026-09-25 04:28:02', '2026-09-25 04:37:02'),
(12, 21, 'user1', 'user1@gmail.com', '09121212121', 19, 'negros dauin', 'drivers-license', 'ijfosijdfshfhdskhfk', '/webprogg/uploads/host_ids/host_1790429539_6ab7c963f0f39.png', 'pending', '2026-09-26 13:32:20', '2026-09-26 13:32:20');

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

CREATE TABLE `listings` (
  `id` int(11) NOT NULL,
  `host_application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `property_type` varchar(50) NOT NULL,
  `location` varchar(255) NOT NULL,
  `exact_address` varchar(500) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `capacity` varchar(20) NOT NULL,
  `bedrooms` int(11) NOT NULL DEFAULT 1,
  `bathrooms` int(11) NOT NULL DEFAULT 1,
  `size_sqm` decimal(6,2) NOT NULL DEFAULT 0.00,
  `floor` varchar(50) DEFAULT NULL,
  `parking` enum('Yes','No') NOT NULL DEFAULT 'No',
  `description` text NOT NULL,
  `house_rules` text DEFAULT NULL,
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenities`)),
  `status` enum('draft','pending','approved','rejected','unlisted') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`id`, `host_application_id`, `user_id`, `title`, `category`, `property_type`, `location`, `exact_address`, `latitude`, `longitude`, `price`, `capacity`, `bedrooms`, `bathrooms`, `size_sqm`, `floor`, `parking`, `description`, `house_rules`, `amenities`, `status`, `created_at`, `updated_at`) VALUES
(15, 11, 20, 'Divino\'s House', 'entire-house', 'house', 'negros oriental, dauin', 'Brgy, Tugawe, Dauin, Negros, Oriental', 9.2096455, 123.2666619, 3000.00, '5', 1, 1, 100.00, '1st floor', 'No', 'Welcome to our cozy and comfortable home, perfect for small families, couples, or guests looking for a peaceful place to stay. The house has 1 bedrooms, 1 bathroom, a living room, kitchen, dining area, and a small garden. Guests can enjoy a clean and relaxing space with natural light, comfortable furniture, and a quiet residential environment. The kitchen is equipped with basic cooking essentials, while the living room provides a comfortable area for relaxing and watching TV.', 'Please dispose of trash in the designated bins, No smoking inside the house, Please keep the house clean and tidy, Respect the neighbors and keep noise at a reasonable level.', '[\"aircon\",\"pet-friendly\",\"free-water\",\"free-electricity\"]', 'approved', '2026-09-25 04:34:27', '2026-09-25 04:37:32');

-- --------------------------------------------------------

--
-- Table structure for table `listing_photos`
--

CREATE TABLE `listing_photos` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `photo_type` enum('cover','additional') NOT NULL DEFAULT 'additional',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `listing_photos`
--

INSERT INTO `listing_photos` (`id`, `listing_id`, `photo_path`, `photo_type`, `sort_order`, `created_at`) VALUES
(20, 15, 'uploads/listing_photos/cover/cover_1790310989_6ab5fa4da7ed5.png', 'cover', 0, '2026-09-25 04:36:29'),
(21, 15, 'uploads/listing_photos/additional/photo_1790310989_6ab5fa4db2fae.png', 'additional', 1, '2026-09-25 04:36:29'),
(22, 15, 'uploads/listing_photos/additional/photo_1790310989_6ab5fa4dc0ffb.png', 'additional', 2, '2026-09-25 04:36:29'),
(23, 15, 'uploads/listing_photos/additional/photo_1790310989_6ab5fa4dcbc7f.png', 'additional', 3, '2026-09-25 04:36:29'),
(24, 15, 'uploads/listing_photos/additional/photo_1790310989_6ab5fa4dd83ca.png', 'additional', 4, '2026-09-25 04:36:29'),
(25, 15, 'uploads/listing_photos/additional/photo_1790310989_6ab5fa4ddfbfc.png', 'additional', 5, '2026-09-25 04:36:29'),
(26, 15, 'uploads/listing_photos/additional/photo_1790310989_6ab5fa4deb418.png', 'additional', 6, '2026-09-25 04:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `link`, `is_read`, `created_at`) VALUES
(45, 20, 'Congratulations! Your host application was approved. You can now list your space on RoomHive.', '/webprogg/host/hostprofile.php', 0, '2026-09-25 04:37:02'),
(46, 20, 'Your listing \"Divino\'s House\" was approved and is now live on RoomHive.', '/webprogg/Listings/listing-detail.php?id=15', 0, '2026-09-25 04:37:32'),
(47, 21, 'You\'re verified! Your Verified badge is now active and you can list a space.', '/webprogg/user/userprofile.php', 0, '2026-09-25 05:14:20');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `user_id` int(11) NOT NULL,
  `email_booking_updates` tinyint(1) NOT NULL DEFAULT 1,
  `email_messages` tinyint(1) NOT NULL DEFAULT 1,
  `email_promotions` tinyint(1) NOT NULL DEFAULT 1,
  `email_reviews` tinyint(1) NOT NULL DEFAULT 1,
  `push_booking_updates` tinyint(1) NOT NULL DEFAULT 1,
  `push_messages` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `user_id` int(11) NOT NULL,
  `email_bookings` tinyint(1) NOT NULL DEFAULT 1,
  `email_messages` tinyint(1) NOT NULL DEFAULT 1,
  `email_promotions` tinyint(1) NOT NULL DEFAULT 0,
  `push_bookings` tinyint(1) NOT NULL DEFAULT 1,
  `push_messages` tinyint(1) NOT NULL DEFAULT 1,
  `push_promotions` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payouts`
--

CREATE TABLE `payouts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(30) NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payout_methods`
--

CREATE TABLE `payout_methods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('gcash','paymaya','bank') NOT NULL,
  `account_name` varchar(120) NOT NULL,
  `account_number` varchar(60) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('maintenance_mode', '0', '2026-09-12 15:24:38'),
('site_name', 'RoomHive', '2026-09-12 15:24:38'),
('support_email', 'hello@roomhive.ph', '2026-09-12 15:24:38'),
('support_phone', '0927 569 3574', '2026-09-12 15:24:38');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `rating` decimal(2,1) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_searches`
--

CREATE TABLE `saved_searches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label` varchar(150) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `min_price` decimal(10,2) DEFAULT NULL,
  `max_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_host` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `currency` varchar(10) NOT NULL DEFAULT 'PHP',
  `status` enum('active','deactivated') NOT NULL DEFAULT 'active',
  `wallet_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `personal_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `age`, `avatar_path`, `location`, `password`, `created_at`, `is_host`, `two_factor_enabled`, `language`, `currency`, `status`, `wallet_balance`, `personal_photo`) VALUES
(18, 'RoomHive Admin', 'support@roomhive.local', NULL, NULL, NULL, NULL, '$2y$10$GCfVk4oIXACxVeobg0GCmOqshCnq9WGPlPHhAXJ5Hbjx82kkBeFqy', '2026-09-23 06:48:26', 0, 0, 'en', 'PHP', 'active', 0.00, NULL),
(20, 'host1', 'host1@gmail.com', '09275693574', 20, '/webprogg/uploads/avatars/avatar_20_1790310432_2ab3b1e6.jpg', 'negros oriental, dauin', '$2y$10$KNn6jQf48mTLT48Ijue87.8dYXw.3RmgImFm4CltYzzU1LMVxLaHG', '2026-09-25 04:26:47', 1, 0, 'en', 'PHP', 'active', 0.00, NULL),
(21, 'user1', 'user1@gmail.com', '09121212121', 19, NULL, 'negros dauin', '$2y$10$vTXmhwL1sr.HDo8nkghcoubPRQMs6VolfosZVkX7huRllNm7TR5Bm', '2026-09-25 05:08:53', 0, 0, 'en', 'PHP', 'active', 0.00, '/webprogg/uploads/personal_photos/pp21_01857444bb48c7f9.jpg'),
(22, 'user3', 'user3@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$vvNeB7XuAKq453a7qjoBeedhYNatohFFmuwEUO2wUjTPQzBgfDgcS', '2026-09-28 13:16:31', 0, 0, 'en', 'PHP', 'active', 0.00, NULL),
(23, 'user4', 'user4@gmail.com', NULL, NULL, NULL, NULL, '$2y$10$L8n73dIqzQ5h3lnIIRRpyeFmK7egzWhZ/IARTC1yoj/D2u5k/QYzW', '2026-09-28 14:24:06', 0, 0, 'en', 'PHP', 'active', 0.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_id_documents`
--

CREATE TABLE `user_id_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `id_type` varchar(60) NOT NULL,
  `id_number` varchar(80) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_id_documents`
--

INSERT INTO `user_id_documents` (`id`, `user_id`, `id_type`, `id_number`, `file_path`, `status`, `admin_note`, `uploaded_at`, `reviewed_at`) VALUES
(3, 21, 'student-id', 'ijfosijdfshfhdskhfk', '/webprogg/uploads/id_documents/uid21_38ee2c0813c4b69d.png', 'verified', NULL, '2026-09-25 13:14:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_user` (`user_id`),
  ADD KEY `bookings_ibfk_1` (`listing_id`),
  ADD KEY `idx_bookings_expiry_sweep` (`status`,`paid_at`,`booked_at`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_conv_host` (`host_id`),
  ADD KEY `fk_conv_listing` (`listing_id`),
  ADD KEY `idx_conv_user` (`user_id`,`last_message_at`);

--
-- Indexes for table `host_applications`
--
ALTER TABLE `host_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hostapp_user` (`user_id`);

--
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_listing_hostapp` (`host_application_id`),
  ADD KEY `fk_listing_user` (`user_id`);

--
-- Indexes for table `listing_photos`
--
ALTER TABLE `listing_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_photo_listing` (`listing_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_msg_sender` (`sender_id`),
  ADD KEY `idx_msg_conv_created` (`conversation_id`,`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `payouts`
--
ALTER TABLE `payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payout_methods`
--
ALTER TABLE `payout_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD UNIQUE KEY `uq_setting_key` (`setting_key`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_review_listing` (`listing_id`),
  ADD KEY `fk_review_user` (`user_id`);

--
-- Indexes for table `saved_searches`
--
ALTER TABLE `saved_searches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_savedsearch_user` (`user_id`,`created_at`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_id_documents`
--
ALTER TABLE `user_id_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uid` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_listing` (`user_id`,`listing_id`),
  ADD KEY `fk_wishlist_listing` (`listing_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `host_applications`
--
ALTER TABLE `host_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `listing_photos`
--
ALTER TABLE `listing_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `payouts`
--
ALTER TABLE `payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payout_methods`
--
ALTER TABLE `payout_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `saved_searches`
--
ALTER TABLE `saved_searches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user_id_documents`
--
ALTER TABLE `user_id_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_booking_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conv_host` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_conv_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`),
  ADD CONSTRAINT `fk_conv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `host_applications`
--
ALTER TABLE `host_applications`
  ADD CONSTRAINT `fk_host_application_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hostapp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `listings`
--
ALTER TABLE `listings`
  ADD CONSTRAINT `fk_listing_host_application` FOREIGN KEY (`host_application_id`) REFERENCES `host_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_listing_hostapp` FOREIGN KEY (`host_application_id`) REFERENCES `host_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_listing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `listing_photos`
--
ALTER TABLE `listing_photos`
  ADD CONSTRAINT `fk_listing_photo_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_photo_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD CONSTRAINT `fk_notifprefs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payouts`
--
ALTER TABLE `payouts`
  ADD CONSTRAINT `payouts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payout_methods`
--
ALTER TABLE `payout_methods`
  ADD CONSTRAINT `payout_methods_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_review_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`);

--
-- Constraints for table `saved_searches`
--
ALTER TABLE `saved_searches`
  ADD CONSTRAINT `fk_savedsearch_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `fk_wishlist_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
