-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 20, 2026 at 10:55 AM
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
-- Database: `ecos_plus`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `weight` decimal(5,2) DEFAULT 0.00,
  `points_earned` int(11) DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `ai_verified` tinyint(1) DEFAULT 0,
  `ai_confidence` decimal(5,2) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `user_id`, `activity_type`, `description`, `quantity`, `weight`, `points_earned`, `image_path`, `location`, `latitude`, `longitude`, `status`, `ai_verified`, `ai_confidence`, `admin_notes`, `created_at`, `approved_at`, `approved_by`) VALUES
(1, 2, 'Plastic', 'bottle spritzer', 1, 0.00, 12, 'assets/uploads/activity_2_1775197069.jpg', 'KOLEJ KEDIAMAN 5', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-03 06:17:49', '2026-04-09 16:59:45', 5),
(2, 2, 'E-Waste', 'AI detected: E-Waste', 1, 0.00, 27, 'assets/uploads/camera_2_1775761172.jpg', '', NULL, NULL, 'rejected', 0, NULL, NULL, '2026-04-09 19:00:06', '2026-04-17 05:37:23', 5),
(3, 2, 'Plastic', 'AI detected: Plastic', 1, 0.00, 12, 'assets/uploads/camera_2_1775794695.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-10 04:18:36', '2026-04-10 16:49:11', 5),
(5, 2, 'Textile', 'AI detected: Textile', 1, 0.00, 14, 'assets/uploads/camera_2_1775913189.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-11 13:13:23', '2026-04-11 13:19:37', 5),
(6, 2, 'Glass', 'AI detected: Glass', 1, 0.00, 17, 'assets/uploads/camera_2_1775913667.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-11 13:21:31', '2026-04-11 14:04:50', 5),
(7, 2, 'Plastic', 'hgh', 1, 0.00, 12, 'assets/uploads/camera_2_1775917572.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-11 14:26:43', '2026-04-16 02:51:36', 5),
(8, 2, 'Textile', 'AI detected: Textile', 1, 0.00, 14, 'assets/uploads/camera_2_1775917609.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-11 14:27:08', '2026-04-16 02:51:33', 5),
(9, 2, 'Paper', 'AI detected: Paper', 1, 0.00, 7, 'assets/uploads/camera_2_1776307829.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 02:50:42', '2026-04-16 02:51:29', 5),
(10, 2, 'E-Waste', 'AI detected: E-Waste', 1, 0.00, 27, 'assets/uploads/camera_2_1776309706.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 03:22:06', '2026-04-16 03:22:43', 5),
(11, 2, 'Paper', 'AI detected: Paper', 1, 0.00, 7, 'assets/uploads/camera_2_1776310227.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 03:30:36', '2026-04-16 03:31:01', 5),
(12, 2, 'Metal', 'AI detected: Metal', 1, 0.00, 22, 'assets/uploads/camera_2_1776310647.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 03:37:42', '2026-04-16 03:37:56', 5),
(13, 6, 'E-Waste', 'AI detected: E-Waste', 1, 0.00, 27, 'assets/uploads/camera_6_1776312974.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 04:16:27', '2026-04-16 04:17:27', 5),
(14, 6, 'E-Waste', 'AI detected: E-Waste', 1, 0.00, 27, 'assets/uploads/camera_6_1776313021.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 04:17:13', '2026-04-16 04:17:23', 5),
(15, 6, 'Paper', 'AI detected: Paper', 1, 0.00, 7, 'assets/uploads/camera_6_1776313072.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 04:18:03', '2026-04-16 04:18:45', 5),
(16, 6, 'Plastic', 'AI detected: Plastic', 1, 0.00, 12, 'assets/uploads/camera_6_1776313093.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 04:18:24', '2026-04-16 04:18:42', 5),
(17, 7, 'Textile', 'AI detected: Textile', 1, 0.00, 14, 'assets/uploads/camera_7_1776314952.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-16 04:49:29', '2026-04-16 04:49:44', 5),
(18, 7, 'Cardboard', 'AI detected: Cardboard', 1, 0.00, 7, 'assets/uploads/camera_7_1776485900.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-18 04:19:59', '2026-04-18 04:20:19', 5),
(19, 7, 'E-Waste', 'AI detected: E-Waste', 1, 0.00, 27, 'assets/uploads/camera_7_1776501215.jpg', '', NULL, NULL, 'pending', 0, NULL, NULL, '2026-04-18 08:33:47', NULL, NULL),
(20, 7, 'E-Waste', 'AI detected: E-Waste', 1, 0.00, 27, 'assets/uploads/camera_7_1776501215.jpg', '', NULL, NULL, 'pending', 0, NULL, NULL, '2026-04-19 08:41:36', NULL, NULL),
(21, 2, 'Plastic', 'AI detected: Plastic', 1, 0.00, 12, 'assets/uploads/camera_2_1776658268.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-20 04:11:21', '2026-04-20 08:53:51', 5),
(22, 2, 'Plastic', 'AI detected: Plastic', 1, 0.00, 12, 'assets/uploads/camera_2_1776658268.jpg', '', NULL, NULL, 'rejected', 0, NULL, NULL, '2026-04-20 04:11:37', '2026-04-20 08:53:51', 5),
(23, 2, 'E-Waste', 'Recycled e-waste items detected by AI', 1, 0.00, 27, 'assets/uploads/camera_2_1776658997.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-20 04:23:39', '2026-04-20 08:39:48', 5),
(24, 8, 'Plastic', 'Recycled plastic items detected by AI', 1, 0.00, 12, 'assets/uploads/camera_8_1776660113_5798.jpg', '', NULL, NULL, 'rejected', 0, NULL, NULL, '2026-04-20 04:42:12', '2026-04-20 08:39:48', 5),
(25, 8, 'E-Waste', 'Recycled e-waste items detected by AI', 1, 0.00, 27, 'assets/uploads/camera_8_1776660169_7521.jpg', '', NULL, NULL, 'approved', 0, NULL, NULL, '2026-04-20 04:43:06', '2026-04-20 05:33:51', 5);

-- --------------------------------------------------------

--
-- Table structure for table `ai_recommendations`
--

CREATE TABLE `ai_recommendations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recommendation_type` varchar(50) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `was_helpful` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `points_required` int(11) DEFAULT 0,
  `activities_required` int(11) DEFAULT 0,
  `category` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `name`, `description`, `icon`, `points_required`, `activities_required`, `category`) VALUES
(1, 'Green Starter', 'First recycling activity completed', '🌱', 0, 1, 'beginner'),
(2, 'Eco Warrior', '100 points earned', '💪', 100, 0, 'points'),
(3, 'Plastic Buster', 'Recycled 10 plastic items', '♻️', 0, 10, 'recycling'),
(4, 'Paper Saver', 'Recycled 20 paper items', '📄', 0, 20, 'recycling'),
(5, 'E-Waste Expert', 'Recycled 5 electronic items', '💻', 0, 5, 'recycling'),
(6, 'Recycling Master', '500 points earned', '🏆', 500, 0, 'points'),
(7, 'Energy Saver', '10 energy-saving activities', '💡', 0, 10, 'energy'),
(8, 'Water Guardian', '10 water-saving activities', '💧', 0, 10, 'water'),
(9, 'Community Hero', 'Joined 5 campus events', '🎯', 0, 5, 'community'),
(10, 'Carbon Neutral', 'Saved 50kg CO2', '🌍', 0, 0, 'impact');

-- --------------------------------------------------------

--
-- Table structure for table `campus_events`
--

CREATE TABLE `campus_events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `max_participants` int(11) DEFAULT 100,
  `current_participants` int(11) DEFAULT 0,
  `points_reward` int(11) DEFAULT 10,
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campus_events`
--

INSERT INTO `campus_events` (`id`, `title`, `description`, `location`, `latitude`, `longitude`, `start_date`, `end_date`, `banner_image`, `max_participants`, `current_participants`, `points_reward`, `created_by`, `is_active`, `created_at`) VALUES
(1, 'UMPSA Green Week 2025', 'Join us for a week of sustainability activities and workshops', 'Main Campus', 0.00000000, 0.00000000, '2025-12-01 09:00:00', '2026-05-31 17:00:00', 'assets/uploads/events/event_1776359664_9372.jpg', 200, 0, 50, NULL, 1, '2026-04-03 05:36:32'),
(2, 'E-Waste Collection Day', 'Bring your electronic waste for proper disposal', 'Faculty of Engineering', NULL, NULL, '2025-11-25 10:00:00', '2025-11-25 16:00:00', NULL, 100, 0, 30, NULL, 1, '2026-04-03 05:36:32'),
(3, 'Plastic Free Campus Challenge', '30-day challenge to reduce plastic usage', 'Online', NULL, NULL, '2025-12-01 00:00:00', '2025-12-30 23:59:00', NULL, 500, 0, 100, NULL, 1, '2026-04-03 05:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `community_comments`
--

CREATE TABLE `community_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `likes_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_comments`
--

INSERT INTO `community_comments` (`id`, `post_id`, `user_id`, `content`, `likes_count`, `created_at`) VALUES
(1, 1, 2, 'niceeeee', 0, '2026-04-11 10:21:48'),
(2, 1, 2, 'hope this give good impression', 0, '2026-04-11 10:22:04'),
(3, 1, 5, 'hi', 0, '2026-04-11 10:22:51');

-- --------------------------------------------------------

--
-- Table structure for table `community_posts`
--

CREATE TABLE `community_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `likes_count` int(11) DEFAULT 0,
  `comments_count` int(11) DEFAULT 0,
  `is_reported` tinyint(1) DEFAULT 0,
  `is_hidden` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `privacy` enum('public','followers','private') DEFAULT 'public'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_posts`
--

INSERT INTO `community_posts` (`id`, `user_id`, `title`, `content`, `image_path`, `likes_count`, `comments_count`, `is_reported`, `is_hidden`, `created_at`, `updated_at`, `privacy`) VALUES
(1, 2, '', 'best experience using this', NULL, 1, 3, 0, 0, '2026-04-10 16:49:51', '2026-04-11 10:22:51', 'public'),
(5, 2, 'Againnn', 'nice try dearself', NULL, 0, 0, 0, 0, '2026-04-16 04:40:31', '2026-04-16 04:40:39', 'private'),
(6, 2, 'Trow the materials in the correct bins', 'this is very important for the process compose', 'assets/uploads/community/post_2_1776314592_3413.jpg', 0, 0, 0, 0, '2026-04-16 04:43:12', '2026-04-16 04:43:12', 'followers'),
(7, 6, 'title', 'kbhhbb', 'assets/uploads/community/post_6_1776315537_1571.jpg', 0, 0, 0, 0, '2026-04-16 04:58:57', '2026-04-16 04:58:57', 'followers');

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attended` tinyint(1) DEFAULT 0,
  `points_awarded` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_participants`
--

INSERT INTO `event_participants` (`id`, `event_id`, `user_id`, `joined_at`, `attended`, `points_awarded`) VALUES
(1, 1, 2, '2026-04-16 17:25:33', 0, 0),
(2, 1, 5, '2026-04-16 17:28:02', 0, 0),
(3, 1, 7, '2026-04-17 05:47:35', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `followers`
--

CREATE TABLE `followers` (
  `id` int(11) NOT NULL,
  `follower_id` int(11) NOT NULL,
  `following_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `followers`
--

INSERT INTO `followers` (`id`, `follower_id`, `following_id`, `created_at`) VALUES
(3, 6, 2, '2026-04-16 16:42:27'),
(4, 2, 6, '2026-04-17 04:05:16'),
(5, 2, 8, '2026-04-20 08:38:13');

-- --------------------------------------------------------

--
-- Table structure for table `post_likes`
--

CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_likes`
--

INSERT INTO `post_likes` (`id`, `post_id`, `user_id`, `created_at`) VALUES
(1, 1, 2, '2026-04-11 10:21:43');

-- --------------------------------------------------------

--
-- Table structure for table `recycling_locations`
--

CREATE TABLE `recycling_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `description` text DEFAULT NULL,
  `operating_hours` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recycling_locations`
--

INSERT INTO `recycling_locations` (`id`, `name`, `address`, `category`, `latitude`, `longitude`, `description`, `operating_hours`, `contact_phone`, `image_path`, `is_active`, `created_by`, `created_at`) VALUES
(1, 'Main Campus Recycling Center', 'Universiti Malaysia Pahang, Main Campus', 'mixed', 3.54620000, 103.42640000, 'Central recycling center accepting all types of recyclables', 'Mon-Fri: 8am-6pm, Sat: 9am-1pm', NULL, NULL, 1, NULL, '2026-04-03 05:36:32'),
(2, 'Student Residence Recycling Point', 'Student Hostel Area, UMP', 'plastic_paper', 3.54850000, 103.42480000, 'Recycling bins for plastic and paper', '24/7', NULL, NULL, 1, NULL, '2026-04-03 05:36:32'),
(3, 'Faculty of Engineering E-Waste Drop-off', 'Faculty of Engineering, UMP', 'ewaste', 3.54480000, 103.42820000, 'Electronic waste collection point', 'Mon-Fri: 9am-5pm', NULL, NULL, 1, NULL, '2026-04-03 05:36:32'),
(4, 'Green Campus Initiative Hub', 'Student Activity Center, UMP', 'mixed', 3.55010000, 103.42550000, 'Full recycling facilities', 'Mon-Sat: 10am-8pm', NULL, NULL, 1, NULL, '2026-04-03 05:36:32'),
(5, 'Library Recycling Corner', 'UMP Library', 'paper', 3.54750000, 103.42700000, 'Paper and book recycling', 'Mon-Sun: 8am-10pm', NULL, NULL, 1, NULL, '2026-04-03 05:36:32'),
(6, 'Cafeteria Waste Station', 'Main Cafeteria', 'organic', 3.54550000, 103.42580000, 'Food waste and compost collection', 'Mon-Fri: 7am-7pm', NULL, NULL, 1, NULL, '2026-04-03 05:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `points_cost` int(11) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `icon` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards`
--

INSERT INTO `rewards` (`id`, `name`, `description`, `points_cost`, `stock`, `icon`, `category`, `is_active`, `created_at`) VALUES
(1, 'Eco Water Bottle', 'Stainless steel water bottle - BPA free', 200, 50, '🚰', 'merchandise', 1, '2026-04-03 05:36:32'),
(2, 'Reusable Shopping Bag', 'Eco-friendly shopping bag', 100, 100, '🛍️', 'merchandise', 1, '2026-04-03 05:36:32'),
(3, 'Plant Seeds Kit', 'Grow your own plants at home', 150, 30, '🌻', 'garden', 1, '2026-04-03 05:36:32'),
(4, 'Eco T-Shirt', 'Sustainable cotton t-shirt', 300, 20, '👕', 'merchandise', 1, '2026-04-03 05:36:32'),
(5, 'Compost Bin', 'Small compost bin for home', 500, 10, '🗑️', 'garden', 1, '2026-04-03 05:36:32'),
(6, 'RM5 Voucher', 'Cash voucher for campus cafe', 50, 199, '🎫', 'voucher', 1, '2026-04-03 05:36:32'),
(7, 'RM10 Voucher', 'Cash voucher for campus cafe', 100, 100, '🎫', 'voucher', 1, '2026-04-03 05:36:32');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `bio` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `year_of_study` int(11) DEFAULT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `points` int(11) DEFAULT 0,
  `theme` enum('light','dark') DEFAULT 'light',
  `language` enum('en','ms') DEFAULT 'en',
  `email_verified` tinyint(1) DEFAULT 1,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `default_privacy` enum('public','followers','private') DEFAULT 'public'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `bio`, `profile_image`, `phone`, `faculty`, `year_of_study`, `student_id`, `role`, `points`, `theme`, `language`, `email_verified`, `reset_token`, `reset_expires`, `is_active`, `created_at`, `last_login`, `default_privacy`) VALUES
(2, 'HIDAYAH', 'ca23091@adab.umpsa.edu.my', '$2y$10$ttPVwA5lTwERrGeRUw1lzuwBWerCSgtEElBp6oxxiUDoJJPsUKpze', 'NUR HIDAYAH BINTI AZMI', '', 'assets/uploads/profiles/profile_2_1775916681.jpg', '', '', 0, 'CA23091', 'user', 275, 'light', 'en', 1, NULL, NULL, 1, '2026-04-03 06:15:42', '2026-04-03 06:15:57', 'followers'),
(5, 'admin', 'admin@adab.umpsa.edu.my', '$2y$10$T6gyz7cyFEwUN5lABZmyWuWzHhthhLBff9Gyu.Jm1B4xO7ZtIba5m', 'System Administrator', NULL, NULL, NULL, NULL, NULL, NULL, 'admin', 1050, 'light', 'en', 1, NULL, NULL, 1, '2026-04-09 16:50:41', NULL, 'public'),
(6, 'FATIN NAJWA', 'ca23102@adab.umpsa.edu.my', '$2y$10$NQlMfm2JAcVVlA6id7dOa.ih8DssWA7mnNx5NFZ7XbGjyUchvHlSu', 'FATIN NAJWA BINTI ABU HASSAN', NULL, NULL, NULL, NULL, NULL, 'CA23102', 'user', 73, 'light', 'en', 1, NULL, NULL, 1, '2026-04-16 04:15:52', NULL, 'followers'),
(7, 'ALIA AISYIKIN', 'ca23107@adab.umpsa.edu.my', '$2y$10$O4gC4TESugAfozYM1jd3H.wkxbIr.n.GbilK84ol/JED2.hm0aHqq', 'NUR ALIA AISYIKIN BINTI MOHAMMAD PADELI', NULL, NULL, NULL, NULL, NULL, 'CA23107', 'user', 71, 'light', 'en', 1, NULL, NULL, 1, '2026-04-16 04:45:16', NULL, 'public'),
(8, 'HANIS RAIHAN', 'ca23069@adab.umpsa.edu.my', '$2y$10$FHkTCfXBmHUFhb1ICSiuY.Dlx3e3jHfkjOtPURXEFTypXfgWpuU3W', 'HANIS RAIHAN BINTI MOHD HISHAM`', NULL, NULL, NULL, NULL, NULL, 'CA23069', 'user', 162, 'light', 'en', 1, NULL, NULL, 1, '2026-04-20 04:40:12', NULL, 'public');

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_badges`
--

INSERT INTO `user_badges` (`id`, `user_id`, `badge_id`, `earned_at`) VALUES
(1, 2, 1, '2026-04-09 16:59:45'),
(2, 2, 5, '2026-04-16 02:51:29'),
(3, 2, 9, '2026-04-16 02:51:29'),
(4, 2, 2, '2026-04-16 03:37:56'),
(5, 2, 3, '2026-04-16 03:37:56'),
(6, 2, 7, '2026-04-16 03:37:56'),
(7, 2, 8, '2026-04-16 03:37:56'),
(8, 6, 1, '2026-04-16 04:17:23'),
(9, 7, 1, '2026-04-16 04:49:44'),
(10, 8, 1, '2026-04-20 05:33:06'),
(11, 8, 2, '2026-04-20 05:33:20');

-- --------------------------------------------------------

--
-- Table structure for table `user_rewards`
--

CREATE TABLE `user_rewards` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_rewards`
--

INSERT INTO `user_rewards` (`id`, `user_id`, `reward_id`, `points_spent`, `status`, `redeemed_at`, `completed_at`) VALUES
(1, 2, 6, 50, 'pending', '2026-04-11 14:04:01', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `campus_events`
--
ALTER TABLE `campus_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_hidden` (`is_hidden`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_participation` (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `followers`
--
ALTER TABLE `followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follow` (`follower_id`,`following_id`),
  ADD KEY `following_id` (`following_id`);

--
-- Indexes for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recycling_locations`
--
ALTER TABLE `recycling_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location` (`latitude`,`longitude`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  ADD KEY `badge_id` (`badge_id`);

--
-- Indexes for table `user_rewards`
--
ALTER TABLE `user_rewards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reward_id` (`reward_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `campus_events`
--
ALTER TABLE `campus_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `community_comments`
--
ALTER TABLE `community_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `community_posts`
--
ALTER TABLE `community_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `followers`
--
ALTER TABLE `followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `post_likes`
--
ALTER TABLE `post_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recycling_locations`
--
ALTER TABLE `recycling_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rewards`
--
ALTER TABLE `rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_rewards`
--
ALTER TABLE `user_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activities_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `ai_recommendations`
--
ALTER TABLE `ai_recommendations`
  ADD CONSTRAINT `ai_recommendations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `campus_events`
--
ALTER TABLE `campus_events`
  ADD CONSTRAINT `campus_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `community_comments`
--
ALTER TABLE `community_comments`
  ADD CONSTRAINT `community_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_posts`
--
ALTER TABLE `community_posts`
  ADD CONSTRAINT `community_posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `campus_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `followers`
--
ALTER TABLE `followers`
  ADD CONSTRAINT `followers_ibfk_1` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `followers_ibfk_2` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_rewards`
--
ALTER TABLE `user_rewards`
  ADD CONSTRAINT `user_rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_rewards_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
