-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 23, 2026 at 09:39 AM
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
-- Database: `vivisha_boutique`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `mobile`, `password`, `role`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Vivisha Admin', 'admin@gmail.com', '9876543210', '$2y$10$O7sS/A2.XELVYBnTRMWfOusz92K/nUhChfVZ26qMpFY/XDuXAGWMC', 'admin', 'active', '2026-09-23 11:56:14', '2026-08-14 13:51:45', '2026-09-23 06:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `auth_refresh_tokens`
--

CREATE TABLE `auth_refresh_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `account_type` enum('user','admin') NOT NULL,
  `token_hash` char(64) NOT NULL,
  `family_id` char(32) NOT NULL,
  `replaced_by_hash` char(64) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auth_refresh_tokens`
--

INSERT INTO `auth_refresh_tokens` (`id`, `account_id`, `account_type`, `token_hash`, `family_id`, `replaced_by_hash`, `expires_at`, `last_used_at`, `revoked_at`, `user_agent`, `ip_address`, `created_at`) VALUES
(1, 3, 'user', 'b3c796c3659497370dbaff22b7f6e8ff3ba6e0e47f3e0480f3464e30d0da2ec5', 'ef8f344ef6caaaeec345e8d0be26fed8', '105f15d0fac0729b0ba1fb6025c9942cd79e5aa9e6cc417fce7e579fe2a8b548', '2026-10-20 10:31:33', '2026-09-20 14:04:52', '2026-09-20 14:04:52', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:31:33'),
(2, 3, 'user', '105f15d0fac0729b0ba1fb6025c9942cd79e5aa9e6cc417fce7e579fe2a8b548', 'ef8f344ef6caaaeec345e8d0be26fed8', '2c282ab7a91487c60b3304eb2983660a4d418d982143af01352a6cf331a8f67f', '2026-10-20 10:34:52', '2026-09-20 14:05:46', '2026-09-20 14:05:46', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:34:52'),
(3, 1, 'admin', 'eb9a3fae5d851e10969a5d561c9cea63b63993d32831626bb52526afa175c2df', 'fb4f1142338895fd63d7b8423063ed27', 'cf0d61a4b45e86416158c88f88325dbb0494e3c390f39a4c04d5d0eef8f80aec', '2026-09-27 10:35:20', '2026-09-20 14:05:54', '2026-09-20 14:05:54', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:35:20'),
(4, 3, 'user', '2c282ab7a91487c60b3304eb2983660a4d418d982143af01352a6cf331a8f67f', 'ef8f344ef6caaaeec345e8d0be26fed8', NULL, '2026-10-20 10:35:46', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:35:46'),
(5, 1, 'admin', 'cf0d61a4b45e86416158c88f88325dbb0494e3c390f39a4c04d5d0eef8f80aec', 'fb4f1142338895fd63d7b8423063ed27', '24a30d4372ab1ba727fc9cf69bb5a1540d1b227128e6be43dfae63c51447c4ec', '2026-09-27 10:35:54', '2026-09-20 14:09:21', '2026-09-20 14:09:21', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:35:54'),
(6, 1, 'admin', '24a30d4372ab1ba727fc9cf69bb5a1540d1b227128e6be43dfae63c51447c4ec', 'fb4f1142338895fd63d7b8423063ed27', NULL, '2026-09-27 10:39:21', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:39:21'),
(7, 6, 'user', '9e29499ce71a6ac92c6df9d04fcbe7090802a012d66834ccdcf292df78aab437', '0f51eeb6d6aeff22035b26ceba0f098d', NULL, '2026-10-20 10:43:00', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:43:00'),
(8, 3, 'user', '125769922c4dee394477e6deb955de74ad10d166b2bcdddde983870de0dfae48', '42b0acbe704cf33d4f8c4063391ac9da', NULL, '2026-10-20 10:54:55', NULL, '2026-09-20 14:36:07', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 08:54:55'),
(9, 3, 'user', '8577e9fe8d9801f811aa25784677c3b5a52db67011ca3fa9896ebd332bbcb8c7', 'bcbb8446622fd96bace8176a5cf54d8a', '80c2ed666b332c73a1a9dbbb9e42d205795b3be5c64a455cb1d5686f4e277195', '2026-10-20 11:09:50', '2026-09-20 14:41:11', '2026-09-20 14:41:11', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 09:09:50'),
(10, 3, 'user', '80c2ed666b332c73a1a9dbbb9e42d205795b3be5c64a455cb1d5686f4e277195', 'bcbb8446622fd96bace8176a5cf54d8a', NULL, '2026-10-20 11:11:11', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-20 09:11:11'),
(11, 1, 'user', '1d8018839fe3be545e44549b243f1840382284fbf9727b068e0a45c4e19089e9', '60ee03a19c4dd9e4f6314687dc7418f7', 'e8b6c8128f7dae4e91dec4e554696d28f7a9accba0ff69f82dbfe60366ec8f1e', '2026-10-20 11:11:43', '2026-09-20 14:41:51', '2026-09-20 14:41:51', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 09:11:43'),
(12, 1, 'user', 'e8b6c8128f7dae4e91dec4e554696d28f7a9accba0ff69f82dbfe60366ec8f1e', '60ee03a19c4dd9e4f6314687dc7418f7', NULL, '2026-10-20 11:11:50', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-20 09:11:50'),
(13, 1, 'user', '16987a508965ed811ebc6983a2d829d406da178fd3d46585a25686bfe4a1942c', 'c2bc95cb88cfa595796cbb37bb13c4a8', NULL, '2026-10-20 11:24:19', NULL, '2026-09-20 14:55:01', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 09:24:19'),
(14, 1, 'user', '71ef8aa4835304a910b539838005ea4ca9a25a1a239f1ee414620065b3197656', '2899f41c14632b40c0e9e37ddb5b381c', NULL, '2026-10-20 11:40:01', NULL, '2026-09-20 15:10:22', 'PostmanRuntime/2.7.0', '::1', '2026-09-20 09:40:01'),
(15, 1, 'user', '209e723cc465f1d70407fe4a0997a6886f5f2365ff97fa4b3388ccf17442f5f6', 'bd8751554949e5858edd79394351b7a4', NULL, '2026-10-21 11:22:45', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-21 09:22:45'),
(16, 1, 'user', '28e9600adc7015a62207c6c9d2c79f857e1c843791c5e9a5f017ec30a93d1cde', '835adf44454c0d7160efc774fe1a9afc', NULL, '2026-10-21 13:04:14', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-21 11:04:14'),
(17, 1, 'user', 'c3f7eefeb88b7ea1cbddcd0ac51ec0fb49792dbe909d16c2937179af50946df5', 'f59eb18fa952e7f2e32c2e7848116f0e', NULL, '2026-10-22 10:13:50', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-22 08:13:50'),
(18, 1, 'admin', '1992ed60e3fcde4d671daee31eaea3311449b7276dfc131758fcc36f30579662', '329726aed51b5053eb8e1e2a865cfe14', NULL, '2026-09-29 13:51:12', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-22 11:51:12'),
(19, 1, 'admin', 'c45cf1c0b1448739f85f2cca9e899f8ad9896af88f47b32a7aad15bd1a7876ef', 'b8c3cf69c17efd4ac424b397ff2eba00', NULL, '2026-09-30 08:05:29', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-23 06:05:29'),
(20, 3, 'user', '5dc626679d83986d77d3da9c123832c1230a1fe5daed7d842b4ae50bff623904', 'e9110328086da8a519e2423357e7d8cd', NULL, '2026-10-23 08:17:17', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-23 06:17:17'),
(21, 1, 'admin', 'b2562823aad2a862cfaec38655459bdda6107ea0dc08ae1befcd43629c0649ab', '656e1d94104665f33a7d42de2b31840d', NULL, '2026-09-30 08:26:14', NULL, NULL, 'PostmanRuntime/2.7.0', '::1', '2026-09-23 06:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `birthday_coupons`
--

CREATE TABLE `birthday_coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `valid_before_days` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `valid_after_days` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `usage_limit_per_birthday` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `birthday_coupons`
--

INSERT INTO `birthday_coupons` (`id`, `coupon_code`, `title`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `valid_before_days`, `valid_after_days`, `usage_limit_per_birthday`, `status`, `created_by_admin_id`, `created_at`, `updated_at`) VALUES
(1, 'BDAY23', 'Birthday Special 20% Off', 'Special birthday discount for Vivisha customers', 'percentage', 20.00, 1000.00, 500.00, 0, 0, 1, 'active', 1, '2026-08-28 09:57:18', '2026-08-28 10:24:46');

-- --------------------------------------------------------

--
-- Table structure for table `birthday_coupon_usage`
--

CREATE TABLE `birthday_coupon_usage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `birthday_coupon_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `birthday_month` tinyint(3) UNSIGNED NOT NULL,
  `birthday_day` tinyint(3) UNSIGNED NOT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_amount_before_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `final_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `birthday_coupon_usage`
--

INSERT INTO `birthday_coupon_usage` (`id`, `birthday_coupon_id`, `user_id`, `order_id`, `coupon_code`, `birthday_month`, `birthday_day`, `discount_type`, `discount_value`, `order_amount_before_discount`, `discount_amount`, `final_order_amount`, `used_at`, `created_at`) VALUES
(1, 1, 1, 3, 'BDAY23', 9, 19, 'percentage', 20.00, 5545.00, 500.00, 5045.00, '2026-09-19 11:39:57', '2026-09-19 06:09:57'),
(2, 1, 3, 6, 'BDAY23', 9, 5, 'percentage', 20.00, 5545.00, 500.00, 5045.00, '2026-09-19 13:41:34', '2026-09-19 08:11:34');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','converted','abandoned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `status`, `created_at`, `updated_at`) VALUES
(3, 1, 'active', '2026-08-19 09:43:19', '2026-09-19 05:12:54'),
(4, 1, 'active', '2026-09-19 03:55:34', '2026-09-19 03:55:34'),
(5, 3, 'active', '2026-09-20 09:06:41', '2026-09-20 09:06:41');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `variant_id`, `quantity`, `unit_price`, `created_at`, `updated_at`) VALUES
(2, 3, 1, 1, 5, 2250.00, '2026-08-19 09:43:19', '2026-08-19 09:43:19'),
(3, 4, 2, 3, 13, 1045.00, '2026-09-19 03:55:34', '2026-09-20 09:26:40'),
(4, 5, 2, 3, 12, 1045.00, '2026-09-20 09:06:41', '2026-09-20 09:10:57');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Sarees', 'sarees', 'Women\'s Sarees', 'uploads/categories/2f20ce68beb0317dc19cf3ce26fc9c44.jpg', 1, 'active', '2026-08-18 11:55:37', '2026-08-18 12:04:27'),
(2, 'Kurti', 'kurti', 'Women\'s Kurti', 'uploads/categories/4ca37bf6a2fb6b22269e65d556dad184.webp', 2, 'active', '2026-08-18 12:06:59', '2026-08-18 12:06:59'),
(3, 'Chuditar', 'chuditar', 'Women\'s chuditar', 'uploads/categories/c6bb732f7b3169c25eda63d7019cebb4.jpg', 3, 'active', '2026-08-18 12:11:32', '2026-08-18 12:11:32');

-- --------------------------------------------------------

--
-- Table structure for table `colors`
--

CREATE TABLE `colors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `hex_code` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `colors`
--

INSERT INTO `colors` (`id`, `name`, `hex_code`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Black', '#000000', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(2, 'White', '#FFFFFF', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(3, 'Red', '#FF0000', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(4, 'Maroon', '#800000', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(5, 'Rose Pink', '#FF66CC', 'active', '2026-08-18 08:28:44', '2026-08-18 08:50:33'),
(6, 'Hot Pink', '#FF69B4', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(7, 'Rose', '#FF007F', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(8, 'Peach', '#FFDAB9', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(9, 'Orange', '#FFA500', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(10, 'Yellow', '#FFFF00', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(11, 'Mustard', '#FFDB58', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(12, 'Green', '#008000', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(13, 'Dark Green', '#006400', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(14, 'Olive Green', '#808000', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(15, 'Mint Green', '#98FF98', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(16, 'Blue', '#0000FF', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(17, 'Navy Blue', '#000080', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(18, 'Sky Blue', '#87CEEB', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(19, 'Royal Blue', '#4169E1', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(20, 'Teal', '#008080', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(21, 'Purple', '#800080', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(22, 'Lavender', '#E6E6FA', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(23, 'Violet', '#EE82EE', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(24, 'Magenta', '#FF00FF', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(25, 'Brown', '#A52A2A', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(26, 'Beige', '#F5F5DC', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(27, 'Cream', '#FFFDD0', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(28, 'Grey', '#808080', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(29, 'Silver', '#C0C0C0', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(30, 'Gold', '#FFD700', 'active', '2026-08-18 08:28:44', '2026-08-18 08:28:44'),
(31, 'Dark Red', '#8B0000', 'active', '2026-08-18 08:48:27', '2026-08-18 08:48:27');

-- --------------------------------------------------------

--
-- Table structure for table `email_password_reset_otps`
--

CREATE TABLE `email_password_reset_otps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `status` enum('pending','verified','used','expired','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_password_reset_otps`
--

INSERT INTO `email_password_reset_otps` (`id`, `user_id`, `email`, `otp_hash`, `attempts`, `max_attempts`, `expires_at`, `verified_at`, `used_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'ragulthulasi82@gmail.com', '$2y$10$q0cgVw1UC98JrY/Bx/BeW.d88cmNtoeobpKbcHoJd2DtKkm6eUbTC', 0, 5, '2026-08-20 13:50:51', '2026-08-20 17:20:04', NULL, 'expired', '2026-08-20 11:45:51', '2026-08-20 11:57:24'),
(2, 1, 'ragulthulasi82@gmail.com', '$2y$10$OY7hTC6bKvSrmMiYvmUp0OncsTRmvlJjJuk7hTnmORz4kImb/ttLm', 0, 5, '2026-08-20 14:02:58', NULL, NULL, 'expired', '2026-08-20 11:57:58', '2026-08-20 11:59:32'),
(3, 1, 'ragulthulasi82@gmail.com', '$2y$10$/1hNz1Si7kLm5cMpWn8hkOZqHhPlhnaIP27ukQbeA.HDd6RloW8GK', 0, 5, '2026-08-20 14:04:32', '2026-08-20 17:31:58', '2026-08-20 17:32:23', 'used', '2026-08-20 11:59:32', '2026-08-20 12:02:23');

-- --------------------------------------------------------

--
-- Table structure for table `festival_coupons`
--

CREATE TABLE `festival_coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `festival_name` varchar(100) NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(10) UNSIGNED DEFAULT NULL,
  `usage_limit_per_user` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `start_at` datetime NOT NULL,
  `end_at` datetime NOT NULL,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `festival_coupons`
--

INSERT INTO `festival_coupons` (`id`, `festival_name`, `coupon_code`, `title`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `usage_limit`, `usage_limit_per_user`, `start_at`, `end_at`, `status`, `created_by_admin_id`, `created_at`, `updated_at`) VALUES
(1, 'Diwali', 'DIWALI20', 'Mega Diwali Offer 2026', 'Special Diwali offer for all customers', 'percentage', 20.00, 1500.00, 500.00, 1000, 1, '2026-09-18 00:00:00', '2026-11-10 23:59:59', 'active', 1, '2026-08-28 10:38:56', '2026-09-19 07:52:41');

-- --------------------------------------------------------

--
-- Table structure for table `festival_coupon_usage`
--

CREATE TABLE `festival_coupon_usage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `festival_coupon_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_amount_before_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `final_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `festival_coupon_usage`
--

INSERT INTO `festival_coupon_usage` (`id`, `festival_coupon_id`, `user_id`, `order_id`, `coupon_code`, `discount_type`, `discount_value`, `order_amount_before_discount`, `discount_amount`, `final_order_amount`, `used_at`, `created_at`) VALUES
(1, 1, 1, 4, 'DIWALI20', 'percentage', 20.00, 5545.00, 500.00, 5045.00, '2026-09-19 12:30:06', '2026-09-19 07:00:06'),
(2, 1, 3, 5, 'DIWALI20', 'percentage', 20.00, 5545.00, 500.00, 5045.00, '2026-09-19 13:19:39', '2026-09-19 07:49:39');

-- --------------------------------------------------------

--
-- Table structure for table `first_order_coupons`
--

CREATE TABLE `first_order_coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `first_order_coupons`
--

INSERT INTO `first_order_coupons` (`id`, `coupon_code`, `title`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `start_at`, `end_at`, `status`, `created_by_admin_id`, `created_at`, `updated_at`) VALUES
(1, 'WELCOME10', 'Welcome 10% Off', NULL, 'percentage', 25.00, 500.00, 600.00, NULL, NULL, 'active', 1, '2026-08-28 11:38:29', '2026-08-28 11:43:08');

-- --------------------------------------------------------

--
-- Table structure for table `first_order_coupon_usage`
--

CREATE TABLE `first_order_coupon_usage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `first_order_coupon_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_amount_before_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `final_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `first_order_coupon_usage`
--

INSERT INTO `first_order_coupon_usage` (`id`, `first_order_coupon_id`, `user_id`, `order_id`, `coupon_code`, `discount_type`, `discount_value`, `order_amount_before_discount`, `discount_amount`, `final_order_amount`, `used_at`, `created_at`) VALUES
(1, 1, 1, 2, 'WELCOME10', 'percentage', 25.00, 5545.00, 600.00, 4945.00, '2026-09-19 10:45:47', '2026-09-19 05:15:47');

-- --------------------------------------------------------

--
-- Table structure for table `hsn_profiles`
--

CREATE TABLE `hsn_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `hsn_code` varchar(10) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hsn_profiles`
--

INSERT INTO `hsn_profiles` (`id`, `category_id`, `name`, `hsn_code`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Pure Silk', '5007', 'Silk Sarees', 'active', '2026-09-11 11:39:03', '2026-09-16 08:55:58'),
(2, 1, 'Synthetic', '5407', 'Synthetic / Man-made fibre Sarees', 'active', '2026-09-11 11:39:03', '2026-09-11 11:39:03'),
(3, 2, 'Women Garment', '610429', 'Women Kurti', 'active', '2026-09-11 11:39:03', '2026-09-11 11:39:03'),
(4, 3, 'Women Garment', '610429', 'Women Chuditar', 'active', '2026-09-11 11:39:03', '2026-09-11 11:39:03'),
(5, 1, 'Cotton', '5208', 'Cotton Sarees', 'active', '2026-09-16 08:35:05', '2026-09-16 08:35:05');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_type` enum('stock_in','sale','cancel','return','adjustment','reservation','reservation_release') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_refunds`
--

CREATE TABLE `manual_refunds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `refund_type` enum('full','partial') NOT NULL DEFAULT 'full',
  `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refund_method` enum('upi','bank_transfer','cash','razorpay','other') NOT NULL,
  `refund_reference` varchar(150) DEFAULT NULL,
  `reason` varchar(500) NOT NULL,
  `admin_note` varchar(1000) DEFAULT NULL,
  `customer_mobile` varchar(20) DEFAULT NULL,
  `contact_status` enum('not_contacted','contacted','no_response','confirmed') NOT NULL DEFAULT 'not_contacted',
  `status` enum('pending','processing','refunded','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_by_admin_id` bigint(20) UNSIGNED NOT NULL,
  `handled_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `contacted_at` datetime DEFAULT NULL,
  `processing_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `new_arrival_variants`
--

CREATE TABLE `new_arrival_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `new_arrival_variants`
--

INSERT INTO `new_arrival_variants` (`id`, `product_variant_id`, `sort_order`, `status`, `created_by_admin_id`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'active', 1, '2026-09-23 06:06:00', '2026-09-23 06:20:56');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `product_discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coupon_type` enum('birthday','festival','referral','first_order') DEFAULT NULL,
  `coupon_id` bigint(20) UNSIGNED DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cod_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cod','razorpay','upi','card','netbanking') DEFAULT NULL,
  `payment_status` enum('pending','initiated','paid','failed','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
  `order_status` enum('pending','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
  `customer_note` varchar(500) DEFAULT NULL,
  `cancel_reason` varchar(500) DEFAULT NULL,
  `placed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `cart_id`, `subtotal`, `product_discount_amount`, `coupon_type`, `coupon_id`, `coupon_code`, `coupon_discount_amount`, `shipping_charge`, `cod_charge`, `tax_amount`, `grand_total`, `payment_method`, `payment_status`, `order_status`, `customer_note`, `cancel_reason`, `placed_at`, `confirmed_at`, `delivered_at`, `cancelled_at`, `created_at`, `updated_at`) VALUES
(2, 'ORD26091700002', 1, NULL, 6000.00, 555.00, 'first_order', 1, 'WELCOME10', 600.00, 0.00, 0.00, 100.00, 4945.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-17 18:48:15', NULL, NULL, NULL, '2026-09-17 13:18:15', '2026-09-19 05:15:47'),
(3, 'ORD26091900003', 1, NULL, 6000.00, 555.00, 'birthday', 1, 'BDAY23', 500.00, 0.00, 0.00, 100.00, 5045.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-19 11:38:11', NULL, NULL, NULL, '2026-09-19 06:08:11', '2026-09-19 06:09:57'),
(4, 'ORD26091900004', 1, NULL, 6000.00, 555.00, 'festival', 1, 'DIWALI20', 500.00, 0.00, 0.00, 100.00, 5045.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-19 12:29:48', NULL, NULL, NULL, '2026-09-19 06:59:48', '2026-09-19 07:00:06'),
(5, 'ORD26091900005', 3, NULL, 6000.00, 555.00, 'festival', 1, 'DIWALI20', 500.00, 0.00, 0.00, 100.00, 5045.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-19 13:17:45', NULL, NULL, NULL, '2026-09-19 07:47:45', '2026-09-19 07:49:39'),
(6, 'ORD26091900006', 3, NULL, 6000.00, 555.00, 'birthday', 1, 'BDAY23', 500.00, 0.00, 0.00, 100.00, 5045.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-19 13:39:01', NULL, NULL, NULL, '2026-09-19 08:09:01', '2026-09-19 08:11:34'),
(7, 'ORD26091900007', 3, NULL, 6000.00, 555.00, 'referral', 1, 'REFER26', 500.00, 0.00, 0.00, 100.00, 5045.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-19 13:57:06', NULL, NULL, NULL, '2026-09-19 08:27:06', '2026-09-19 08:28:39'),
(8, 'ORD26092000008', 3, NULL, 6000.00, 555.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, 100.00, 5545.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-20 14:25:27', NULL, NULL, NULL, '2026-09-20 08:55:27', '2026-09-20 08:55:27'),
(9, 'ORD26092100009', 1, NULL, 6000.00, 605.00, NULL, NULL, NULL, 0.00, 0.00, 0.00, 600.00, 5995.00, NULL, 'pending', 'pending', 'Handle with care', NULL, '2026-09-21 14:53:40', NULL, NULL, NULL, '2026-09-21 09:23:40', '2026-09-21 09:23:40');

-- --------------------------------------------------------

--
-- Table structure for table `order_addresses`
--

CREATE TABLE `order_addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_address_id` bigint(20) UNSIGNED DEFAULT NULL,
  `address_type` enum('home','work','other') NOT NULL DEFAULT 'home',
  `door_no` varchar(100) NOT NULL,
  `street` varchar(150) NOT NULL,
  `area` varchar(150) NOT NULL,
  `city` varchar(100) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `landmark` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_addresses`
--

INSERT INTO `order_addresses` (`id`, `order_id`, `user_address_id`, `address_type`, `door_no`, `street`, `area`, `city`, `district`, `state`, `pincode`, `landmark`, `created_at`) VALUES
(2, 2, 2, 'work', '13', 'Thirukurippu Street', 'water colony', 'madurai', 'madurai', 'Tamil Nadu', '630102', 'Near Leader School', '2026-09-19 05:11:01'),
(3, 3, 2, 'work', '13', 'Thirukurippu Street', 'water colony', 'madurai', 'madurai', 'Tamil Nadu', '630102', 'Near Leader School', '2026-09-19 06:09:50'),
(4, 4, 2, 'work', '13', 'Thirukurippu Street', 'water colony', 'madurai', 'madurai', 'Tamil Nadu', '630102', 'Near Leader School', '2026-09-19 06:59:56'),
(5, 5, 3, 'home', '12/5', 'Kamarajar Street', 'Anna Nagar', 'Chennai', 'Chennai', 'Tamil Nadu', '600040', 'Near Anna Nagar Tower', '2026-09-19 07:48:10'),
(6, 6, 3, 'home', '12/5', 'Kamarajar Street', 'Anna Nagar', 'Chennai', 'Chennai', 'Tamil Nadu', '600040', 'Near Anna Nagar Tower', '2026-09-19 08:09:15'),
(7, 7, 3, 'home', '12/5', 'Kamarajar Street', 'Anna Nagar', 'Chennai', 'Chennai', 'Tamil Nadu', '600040', 'Near Anna Nagar Tower', '2026-09-19 08:27:15'),
(8, 9, 1, 'home', '21', 'Thirukurippu Street', 'Burma colony', 'Karaikudi', 'Sivagangai', 'Tamil Nadu', '630005', 'Near Leader School', '2026-09-21 09:30:39');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `variant_name` varchar(150) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `hsn_code` varchar(10) DEFAULT NULL,
  `size_name` varchar(100) DEFAULT NULL,
  `color_name` varchar(100) DEFAULT NULL,
  `original_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `product_discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `item_status` enum('active','cancelled','return_requested','returned','refunded') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variant_id`, `product_name`, `variant_name`, `sku`, `hsn_code`, `size_name`, `color_name`, `original_price`, `selling_price`, `quantity`, `product_discount_amount`, `line_subtotal`, `taxable_amount`, `gst_rate`, `cgst_rate`, `cgst_amount`, `sgst_rate`, `sgst_amount`, `igst_rate`, `igst_amount`, `tax_amount`, `line_total`, `item_status`, `created_at`, `updated_at`) VALUES
(2, 2, 1, 2, 'poonam-sarees', 'Black- Free Size', 'POONAM-BLACK-FREE', NULL, 'XL', 'Maroon', 2500.00, 2250.00, 2, 500.00, 5000.00, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500.00, 'active', '2026-09-17 13:18:15', '2026-09-17 13:18:15'),
(3, 2, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 5.00, 50.00, 5.00, 50.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-17 13:18:15', '2026-09-19 05:11:01'),
(4, 3, 1, 2, 'poonam-sarees', 'Black- Free Size', 'POONAM-BLACK-FREE', NULL, 'XL', 'Maroon', 2500.00, 2250.00, 2, 500.00, 5000.00, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500.00, 'active', '2026-09-19 06:08:11', '2026-09-19 06:08:11'),
(5, 3, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 5.00, 50.00, 5.00, 50.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-19 06:08:11', '2026-09-19 06:09:50'),
(6, 4, 1, 2, 'poonam-sarees', 'Black- Free Size', 'POONAM-BLACK-FREE', NULL, 'XL', 'Maroon', 2500.00, 2250.00, 2, 500.00, 5000.00, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500.00, 'active', '2026-09-19 06:59:48', '2026-09-19 06:59:48'),
(7, 4, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 5.00, 50.00, 5.00, 50.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-19 06:59:48', '2026-09-19 06:59:56'),
(8, 5, 1, 2, 'poonam-sarees', 'Black- Free Size', 'POONAM-BLACK-FREE', NULL, 'XL', 'Maroon', 2500.00, 2250.00, 2, 500.00, 5000.00, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500.00, 'active', '2026-09-19 07:47:45', '2026-09-19 07:47:45'),
(9, 5, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 5.00, 50.00, 5.00, 50.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-19 07:47:45', '2026-09-19 07:48:10'),
(10, 6, 1, 2, 'poonam-sarees', 'Black- Free Size', 'POONAM-BLACK-FREE', NULL, 'XL', 'Maroon', 2500.00, 2250.00, 2, 500.00, 5000.00, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500.00, 'active', '2026-09-19 08:09:01', '2026-09-19 08:09:01'),
(11, 6, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 5.00, 50.00, 5.00, 50.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-19 08:09:01', '2026-09-19 08:09:15'),
(12, 7, 1, 2, 'poonam-sarees', 'Black- Free Size', 'POONAM-BLACK-FREE', NULL, 'XL', 'Maroon', 2500.00, 2250.00, 2, 500.00, 5000.00, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500.00, 'active', '2026-09-19 08:27:06', '2026-09-19 08:27:06'),
(13, 7, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 5.00, 50.00, 5.00, 50.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-19 08:27:06', '2026-09-19 08:27:15'),
(14, 8, 1, 2, 'poonam-sarees', 'Black- Free Size', 'POONAM-BLACK-FREE', NULL, 'XL', 'Maroon', 2500.00, 2250.00, 2, 500.00, 5000.00, 5000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4500.00, 'active', '2026-09-20 08:55:27', '2026-09-20 08:55:27'),
(15, 8, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-20 08:55:27', '2026-09-20 08:55:27'),
(16, 9, 1, 1, 'poonam-sarees', NULL, 'POONAM-MAROON-FREE', NULL, 'Free Size', NULL, 2500.00, 2475.00, 2, 550.00, 5000.00, 5000.00, 10.00, 5.00, 250.00, 5.00, 250.00, 0.00, 0.00, 500.00, 4950.00, 'active', '2026-09-21 09:23:40', '2026-09-21 09:30:39'),
(17, 9, 2, 3, 'Premium Silk Saree', 'Red Silk', 'SKU26003', '5007', 'S', 'Red', 1000.00, 1045.00, 1, 55.00, 1000.00, 1000.00, 10.00, 5.00, 50.00, 5.00, 50.00, 0.00, 0.00, 100.00, 1045.00, 'active', '2026-09-21 09:23:40', '2026-09-21 09:30:39');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `provider` enum('cod','razorpay') NOT NULL,
  `attempt_no` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `razorpay_signature` varchar(255) DEFAULT NULL,
  `receipt` varchar(100) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paise` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT 'INR',
  `payment_method` enum('cod','upi','card','netbanking','wallet','emi','paylater','other') DEFAULT NULL,
  `status` enum('pending','initiated','created','authorized','paid','failed','cancelled','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
  `gateway_status` varchar(50) DEFAULT NULL,
  `bank` varchar(100) DEFAULT NULL,
  `wallet` varchar(100) DEFAULT NULL,
  `vpa` varchar(150) DEFAULT NULL,
  `card_id` varchar(100) DEFAULT NULL,
  `card_network` varchar(50) DEFAULT NULL,
  `card_type` varchar(50) DEFAULT NULL,
  `card_last4` varchar(4) DEFAULT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `error_code` varchar(100) DEFAULT NULL,
  `error_description` varchar(500) DEFAULT NULL,
  `error_source` varchar(100) DEFAULT NULL,
  `error_step` varchar(100) DEFAULT NULL,
  `error_reason` varchar(100) DEFAULT NULL,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notes`)),
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `initiated_at` datetime DEFAULT NULL,
  `authorized_at` datetime DEFAULT NULL,
  `captured_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_status_history`
--

CREATE TABLE `payment_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) NOT NULL,
  `source` enum('create_api','verify_api','webhook','admin','system') NOT NULL DEFAULT 'system',
  `message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_webhook_events`
--

CREATE TABLE `payment_webhook_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `process_status` enum('pending','processed','ignored','failed') NOT NULL DEFAULT 'pending',
  `error_message` varchar(500) DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `hsn_profile_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `description` text DEFAULT NULL,
  `is_new_arrival` tinyint(1) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_best_seller` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `hsn_profile_id`, `name`, `slug`, `description`, `is_new_arrival`, `is_featured`, `is_best_seller`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'poonam-sarees', 'poonam-sarees', 'Premium Poonam saree collection', 1, 1, 0, 'active', '2026-08-18 12:38:36', '2026-08-19 06:16:05'),
(2, 1, 1, 'Premium Silk Saree', 'premium-silk-saree-4', 'Premium silk saree collection', 1, 1, 0, 'active', '2026-09-16 11:32:34', '2026-09-23 07:03:22'),
(3, 1, 5, 'kaanchipuram Silk Saree', 'kaanchipuram-silk-saree-4', 'Premium silk saree collection', 1, 1, 0, 'active', '2026-09-23 06:56:34', '2026-09-23 07:02:58'),
(4, 1, 2, 'kaanchipuram cotton Saree', 'kaanchipuram-cotton-saree-4', 'Premium silk saree collection', 1, 1, 0, 'active', '2026-09-23 07:01:13', '2026-09-23 07:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `size_id` bigint(20) UNSIGNED DEFAULT NULL,
  `color_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sku` varchar(100) NOT NULL,
  `variant_name` varchar(150) DEFAULT NULL,
  `original_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('none','percentage','flat') NOT NULL DEFAULT 'none',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_with_tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reserved_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `low_stock_limit` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `variant_name`, `original_price`, `discount_type`, `discount_value`, `selling_price`, `gst_rate`, `gst_amount`, `price_with_tax`, `stock_quantity`, `reserved_quantity`, `low_stock_limit`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 1, 10, NULL, 'POONAM-MAROON-FREE', NULL, 2500.00, 'percentage', 10.00, 2475.00, 10.00, 250.00, 2750.00, 50, 7, 10, 1, '2026-08-19 08:11:17', '2026-09-21 09:23:40'),
(2, 1, 5, 4, 'POONAM-BLACK-FREE', 'Black- Free Size', 2500.00, 'percentage', 10.00, 2250.00, 0.00, 0.00, 0.00, 30, 14, 10, 1, '2026-08-19 08:45:45', '2026-09-20 08:55:27'),
(3, 2, 2, 3, 'SKU26003', 'Red Silk', 1000.00, 'percentage', 5.00, 1045.00, 10.00, 100.00, 1100.00, 30, 8, 10, 1, '2026-09-16 13:25:21', '2026-09-21 09:23:40'),
(4, 2, 3, 4, 'VIV26004', 'Red Silk', 1000.00, 'percentage', 5.00, 1045.00, 10.00, 100.00, 1100.00, 30, 0, 10, 1, '2026-09-22 11:52:45', '2026-09-22 11:52:45'),
(5, 2, 4, 4, 'VIV2026005', 'Red Silk', 1000.00, 'percentage', 5.00, 1045.00, 10.00, 100.00, 1100.00, 30, 0, 10, 1, '2026-09-23 06:53:27', '2026-09-23 06:53:27');

-- --------------------------------------------------------

--
-- Table structure for table `product_variant_images`
--

CREATE TABLE `product_variant_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED NOT NULL,
  `image` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variant_images`
--

INSERT INTO `product_variant_images` (`id`, `variant_id`, `image`, `alt_text`, `is_primary`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(2, 2, 'uploads/products/product_1_variant_2_58ffddce0ffdc7634331e517.webp', 'Poonam Saree Front', 1, 1, 'active', '2026-08-19 08:45:45', '2026-08-19 08:45:45'),
(3, 2, 'uploads/products/product_1_variant_2_0e351303f65cb64f94f2f709.jpg', 'Poonam Saree Back', 0, 2, 'active', '2026-08-19 08:45:45', '2026-08-19 08:45:45'),
(4, 1, 'uploads/products/product_1_variant_1_e9ed0ceee8ea34c307cd5eef.jpg', 'Poonam Saree Front', 1, 1, 'active', '2026-08-19 09:09:24', '2026-08-19 09:09:24'),
(5, 1, 'uploads/products/product_1_variant_1_45682ef36b5030c0e9dec154.webp', 'Poonam Saree Back', 0, 2, 'active', '2026-08-19 09:09:24', '2026-08-19 09:09:24'),
(6, 3, 'uploads/products/product_2_variant_3_e693e840a30bcd33ec5234ef.jpg', 'front view', 1, 1, 'active', '2026-09-16 13:25:22', '2026-09-17 06:39:01'),
(7, 3, 'uploads/products/product_2_variant_3_2fb57d4f9f6ac0bc3da650d3.webp', 'back view', 0, 2, 'active', '2026-09-16 13:25:22', '2026-09-16 13:25:22'),
(8, 4, 'uploads/products/product_2_variant_4_05328bbfcb68b057530c1a35.jpg', 'front view', 0, 1, 'active', '2026-09-22 11:52:45', '2026-09-22 11:52:45'),
(9, 4, 'uploads/products/product_2_variant_4_ed9c22e59007eb888b9e6df3.webp', 'back view', 0, 2, 'active', '2026-09-22 11:52:45', '2026-09-22 11:52:45'),
(10, 5, 'uploads/products/product_2_variant_5_76b436eac0e747059fa25e97.jpg', 'front view', 0, 1, 'active', '2026-09-23 06:53:27', '2026-09-23 06:53:27'),
(11, 5, 'uploads/products/product_2_variant_5_1f598454725cccaef690d43d.webp', 'back view', 0, 2, 'active', '2026-09-23 06:53:27', '2026-09-23 06:53:27');

-- --------------------------------------------------------

--
-- Table structure for table `referral_coupons`
--

CREATE TABLE `referral_coupons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `referral_code` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(10) UNSIGNED DEFAULT NULL,
  `usage_limit_per_user` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_coupons`
--

INSERT INTO `referral_coupons` (`id`, `referral_code`, `title`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `usage_limit`, `usage_limit_per_user`, `start_at`, `end_at`, `status`, `created_by_admin_id`, `created_at`, `updated_at`) VALUES
(1, 'REFER26', 'Referral 20% Off', 'Special discount using referral code', 'percentage', 20.00, 1500.00, 500.00, 500, 1, '2026-08-28 00:00:00', '2026-12-31 23:59:59', 'active', 1, '2026-08-28 11:54:59', '2026-08-28 11:59:09');

-- --------------------------------------------------------

--
-- Table structure for table `referral_coupon_usage`
--

CREATE TABLE `referral_coupon_usage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `referral_coupon_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `referral_code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','flat','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `order_amount_before_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `final_order_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `used_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_coupon_usage`
--

INSERT INTO `referral_coupon_usage` (`id`, `referral_coupon_id`, `user_id`, `order_id`, `referral_code`, `discount_type`, `discount_value`, `order_amount_before_discount`, `discount_amount`, `final_order_amount`, `used_at`, `created_at`) VALUES
(1, 1, 3, 7, 'REFER26', 'percentage', 20.00, 5545.00, 500.00, 5045.00, '2026-09-19 13:58:39', '2026-09-19 08:28:39');

-- --------------------------------------------------------

--
-- Table structure for table `revoked_access_tokens`
--

CREATE TABLE `revoked_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `jti` varchar(64) NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `account_type` enum('user','admin') NOT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `revoked_access_tokens`
--

INSERT INTO `revoked_access_tokens` (`id`, `jti`, `account_id`, `account_type`, `expires_at`, `revoked_at`) VALUES
(2, '51142c32b858d9120cf2ee3409436632', 1, 'user', '2026-09-20 15:40:01', '2026-09-20 09:40:22');

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`id`, `name`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'XS', 1, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(2, 'S', 2, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(3, 'M', 3, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(4, 'L', 4, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(5, 'XL', 5, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(6, 'XXL', 6, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(7, '3XL', 7, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(8, '4XL', 8, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(9, '5XL', 9, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(10, 'Free Size', 10, 'active', '2026-08-18 08:17:41', '2026-08-18 08:17:41'),
(11, '28', 11, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(12, '30', 12, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(13, '32', 13, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(14, '34', 14, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(15, '36', 15, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(16, '38', 16, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(17, '40', 17, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(18, '42', 18, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(19, '44', 19, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(20, '46', 20, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53'),
(21, '48', 21, 'active', '2026-08-18 08:17:53', '2026-08-18 08:17:53');

-- --------------------------------------------------------

--
-- Table structure for table `top_selling_variants`
--

CREATE TABLE `top_selling_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `top_selling_variants`
--

INSERT INTO `top_selling_variants` (`id`, `product_variant_id`, `sort_order`, `status`, `created_by_admin_id`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'inactive', 1, '2026-09-23 06:26:43', '2026-09-23 06:29:40');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `mobile`, `email`, `password`, `date_of_birth`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Thulasi Ragul', '8825920268', 'ragulthulasi82@gmail.com', '$2y$10$cjWIm8pGTgvZhW5ZSePB1uqNSzu7oQh7fhsZOgLqe1cvCnwJ372Ua', '2000-09-19', 'active', '2026-09-22 13:43:50', '2026-08-14 13:56:26', '2026-09-22 08:13:50'),
(2, 'Ragul', '9876543111', 'ragul@gmail.com', '$2y$10$G3iJeLhNw4Os0.I8e9i6LOxX0eRTKVgvK1NM.RJT4OAGMN/UFlNYu', '2000-09-19', 'active', '2026-08-19 15:05:54', '2026-08-14 13:57:34', '2026-09-19 05:42:10'),
(3, 'Siva', '9876541111', 'siva@gmail.com', '$2y$10$oAxDrB5ZEf33LFosm12dR.9zXej5yEzf0Upf2DVJfwr.5jfcHsw7q', '2000-09-05', 'active', '2026-09-23 11:47:17', '2026-08-18 06:47:47', '2026-09-23 06:17:17'),
(4, 'Bala', '9872541111', 'bala@gmail.com', '$2y$10$2VWvYFPQi68lHHL8lBggrOknQGdw5o7bcuS.7h3c1RxgovwVmjfcO', '2000-06-15', 'active', NULL, '2026-08-21 06:26:37', '2026-08-21 06:26:37'),
(5, 'sriram', '8825986742', 'sriram@gmail.com', '$2y$10$UWC2Nl6hvcYihc32aaZ7iObz8zv5dhmcq5aGDCrXFQGuU8oacna/G', '2003-05-10', 'active', NULL, '2026-09-20 07:07:17', '2026-09-20 07:07:17'),
(6, 'kumar', '8822986742', 'kumar@gmail.com', '$2y$10$5b8vSOZNyDBojOGzJd6JBuj0uF1E.5LWONcDp7deqe.fJOx7SOGDS', '2003-05-10', 'active', NULL, '2026-09-20 08:43:00', '2026-09-20 08:43:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `address_type` enum('home','work','other') NOT NULL DEFAULT 'home',
  `door_no` varchar(100) NOT NULL,
  `street` varchar(150) NOT NULL,
  `area` varchar(150) NOT NULL,
  `city` varchar(100) NOT NULL,
  `district` varchar(100) DEFAULT NULL,
  `state` varchar(100) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `landmark` varchar(150) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `address_type`, `door_no`, `street`, `area`, `city`, `district`, `state`, `pincode`, `landmark`, `is_default`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'home', '21', 'Thirukurippu Street', 'Burma colony', 'Karaikudi', 'Sivagangai', 'Tamil Nadu', '630005', 'Near Leader School', 1, 'active', '2026-08-18 07:27:31', '2026-08-18 07:47:24'),
(2, 1, 'work', '13', 'Thirukurippu Street', 'water colony', 'madurai', 'madurai', 'Tamil Nadu', '630102', 'Near Leader School', 0, 'active', '2026-08-18 07:36:46', '2026-08-18 07:48:27'),
(3, 3, 'home', '12/5', 'Kamarajar Street', 'Anna Nagar', 'Chennai', 'Chennai', 'Tamil Nadu', '600040', 'Near Anna Nagar Tower', 1, 'active', '2026-09-19 07:47:11', '2026-09-19 07:47:11');

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `product_id`, `variant_id`, `created_at`, `updated_at`) VALUES
(2, 1, 1, 1, '2026-08-20 06:42:56', '2026-08-20 06:42:56'),
(3, 1, 1, 2, '2026-09-17 04:11:17', '2026-09-17 04:11:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `mobile` (`mobile`);

--
-- Indexes for table `auth_refresh_tokens`
--
ALTER TABLE `auth_refresh_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_refresh_token_hash` (`token_hash`),
  ADD KEY `idx_refresh_account` (`account_id`,`account_type`),
  ADD KEY `idx_refresh_family` (`family_id`,`account_type`),
  ADD KEY `idx_refresh_expiry` (`expires_at`),
  ADD KEY `idx_refresh_revoked` (`revoked_at`);

--
-- Indexes for table `birthday_coupons`
--
ALTER TABLE `birthday_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_birthday_coupon_code` (`coupon_code`),
  ADD KEY `idx_birthday_coupon_status` (`status`),
  ADD KEY `fk_birthday_coupon_admin` (`created_by_admin_id`);

--
-- Indexes for table `birthday_coupon_usage`
--
ALTER TABLE `birthday_coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_birthday_usage_order` (`order_id`),
  ADD KEY `idx_birthday_usage_coupon` (`birthday_coupon_id`),
  ADD KEY `idx_birthday_usage_user` (`user_id`),
  ADD KEY `idx_birthday_usage_date` (`birthday_month`,`birthday_day`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_carts_user_status` (`user_id`,`status`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_variant` (`cart_id`,`variant_id`),
  ADD KEY `idx_cart_items_product` (`product_id`),
  ADD KEY `idx_cart_items_variant` (`variant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_categories_status` (`status`);

--
-- Indexes for table `colors`
--
ALTER TABLE `colors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `email_password_reset_otps`
--
ALTER TABLE `email_password_reset_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_reset_user` (`user_id`),
  ADD KEY `idx_email_reset_email` (`email`),
  ADD KEY `idx_email_reset_status` (`status`),
  ADD KEY `idx_email_reset_expiry` (`expires_at`);

--
-- Indexes for table `festival_coupons`
--
ALTER TABLE `festival_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_festival_coupon_code` (`coupon_code`),
  ADD KEY `idx_festival_name` (`festival_name`),
  ADD KEY `idx_festival_status` (`status`),
  ADD KEY `idx_festival_dates` (`start_at`,`end_at`),
  ADD KEY `fk_festival_coupon_admin` (`created_by_admin_id`);

--
-- Indexes for table `festival_coupon_usage`
--
ALTER TABLE `festival_coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_festival_usage_order` (`order_id`),
  ADD KEY `idx_festival_usage_coupon` (`festival_coupon_id`),
  ADD KEY `idx_festival_usage_user` (`user_id`),
  ADD KEY `idx_festival_coupon_user` (`festival_coupon_id`,`user_id`);

--
-- Indexes for table `first_order_coupons`
--
ALTER TABLE `first_order_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_first_order_coupon_code` (`coupon_code`),
  ADD KEY `idx_first_order_status` (`status`),
  ADD KEY `fk_first_order_coupon_admin` (`created_by_admin_id`);

--
-- Indexes for table `first_order_coupon_usage`
--
ALTER TABLE `first_order_coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_first_order_usage_user` (`user_id`),
  ADD UNIQUE KEY `uq_first_order_usage_order` (`order_id`),
  ADD UNIQUE KEY `uq_first_order_coupon_user` (`user_id`),
  ADD UNIQUE KEY `uq_first_order_coupon_order` (`order_id`),
  ADD KEY `idx_first_order_usage_coupon` (`first_order_coupon_id`);

--
-- Indexes for table `hsn_profiles`
--
ALTER TABLE `hsn_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hsn_profiles_category` (`category_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inventory_admin` (`created_by_admin_id`),
  ADD KEY `idx_inventory_variant_id` (`variant_id`),
  ADD KEY `idx_inventory_transaction_type` (`transaction_type`),
  ADD KEY `idx_inventory_reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `manual_refunds`
--
ALTER TABLE `manual_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_manual_refund_order` (`order_id`),
  ADD KEY `idx_manual_refund_payment` (`payment_id`),
  ADD KEY `idx_manual_refund_user` (`user_id`),
  ADD KEY `idx_manual_refund_status` (`status`),
  ADD KEY `idx_manual_refund_contact_status` (`contact_status`),
  ADD KEY `idx_manual_refund_created_admin` (`created_by_admin_id`),
  ADD KEY `idx_manual_refund_handled_admin` (`handled_by_admin_id`),
  ADD KEY `idx_manual_refund_created_at` (`created_at`);

--
-- Indexes for table `new_arrival_variants`
--
ALTER TABLE `new_arrival_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_new_arrival_variant` (`product_variant_id`),
  ADD KEY `idx_new_arrival_status` (`status`),
  ADD KEY `idx_new_arrival_sort_order` (`sort_order`),
  ADD KEY `idx_new_arrival_admin` (`created_by_admin_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_order_number` (`order_number`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_cart` (`cart_id`),
  ADD KEY `idx_orders_order_status` (`order_status`),
  ADD KEY `idx_orders_payment_status` (`payment_status`),
  ADD KEY `idx_orders_created_at` (`created_at`),
  ADD KEY `idx_orders_coupon` (`coupon_type`,`coupon_id`);

--
-- Indexes for table `order_addresses`
--
ALTER TABLE `order_addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_address_order` (`order_id`),
  ADD KEY `idx_order_address_user_address` (`user_address_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`),
  ADD KEY `idx_order_items_variant` (`variant_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_attempt` (`order_id`,`attempt_no`),
  ADD UNIQUE KEY `uq_razorpay_order_id` (`razorpay_order_id`),
  ADD UNIQUE KEY `uq_razorpay_payment_id` (`razorpay_payment_id`),
  ADD KEY `idx_payment_order` (`order_id`),
  ADD KEY `idx_payment_user` (`user_id`),
  ADD KEY `idx_payment_status` (`status`);

--
-- Indexes for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_history_payment` (`payment_id`),
  ADD KEY `idx_payment_history_order` (`order_id`);

--
-- Indexes for table `payment_webhook_events`
--
ALTER TABLE `payment_webhook_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_webhook_event_id` (`event_id`),
  ADD KEY `idx_webhook_order` (`razorpay_order_id`),
  ADD KEY `idx_webhook_payment` (`razorpay_payment_id`),
  ADD KEY `idx_webhook_type` (`event_type`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_products_category_id` (`category_id`),
  ADD KEY `idx_products_status` (`status`),
  ADD KEY `idx_products_new_arrival` (`is_new_arrival`),
  ADD KEY `idx_products_featured` (`is_featured`),
  ADD KEY `idx_products_best_seller` (`is_best_seller`),
  ADD KEY `fk_products_hsn_profile` (`hsn_profile_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_variants_product_id` (`product_id`),
  ADD KEY `idx_variants_size_id` (`size_id`),
  ADD KEY `idx_variants_color_id` (`color_id`),
  ADD KEY `idx_variants_stock` (`stock_quantity`);

--
-- Indexes for table `product_variant_images`
--
ALTER TABLE `product_variant_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_variant_id` (`variant_id`),
  ADD KEY `idx_variant_primary` (`variant_id`,`is_primary`),
  ADD KEY `idx_variant_sort` (`variant_id`,`sort_order`);

--
-- Indexes for table `referral_coupons`
--
ALTER TABLE `referral_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_referral_code` (`referral_code`),
  ADD KEY `idx_referral_coupon_status` (`status`),
  ADD KEY `idx_referral_coupon_dates` (`start_at`,`end_at`),
  ADD KEY `fk_referral_coupon_admin` (`created_by_admin_id`);

--
-- Indexes for table `referral_coupon_usage`
--
ALTER TABLE `referral_coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_referral_usage_user` (`user_id`),
  ADD UNIQUE KEY `uq_referral_usage_order` (`order_id`),
  ADD KEY `idx_referral_usage_coupon` (`referral_coupon_id`),
  ADD KEY `idx_referral_usage_code` (`referral_code`);

--
-- Indexes for table `revoked_access_tokens`
--
ALTER TABLE `revoked_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_revoked_jti` (`jti`),
  ADD KEY `idx_revoked_account` (`account_id`,`account_type`),
  ADD KEY `idx_revoked_expires` (`expires_at`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `top_selling_variants`
--
ALTER TABLE `top_selling_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_top_selling_variant` (`product_variant_id`),
  ADD KEY `idx_top_selling_status` (`status`),
  ADD KEY `idx_top_selling_sort_order` (`sort_order`),
  ADD KEY `idx_top_selling_admin` (`created_by_admin_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_mobile` (`mobile`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_addresses_user_id` (`user_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wishlist_user_variant` (`user_id`,`variant_id`),
  ADD KEY `idx_wishlist_user_id` (`user_id`),
  ADD KEY `idx_wishlist_product_id` (`product_id`),
  ADD KEY `idx_wishlist_variant_id` (`variant_id`),
  ADD KEY `idx_wishlist_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `auth_refresh_tokens`
--
ALTER TABLE `auth_refresh_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `birthday_coupons`
--
ALTER TABLE `birthday_coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `birthday_coupon_usage`
--
ALTER TABLE `birthday_coupon_usage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `colors`
--
ALTER TABLE `colors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `email_password_reset_otps`
--
ALTER TABLE `email_password_reset_otps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `festival_coupons`
--
ALTER TABLE `festival_coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `festival_coupon_usage`
--
ALTER TABLE `festival_coupon_usage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `first_order_coupons`
--
ALTER TABLE `first_order_coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `first_order_coupon_usage`
--
ALTER TABLE `first_order_coupon_usage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hsn_profiles`
--
ALTER TABLE `hsn_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_refunds`
--
ALTER TABLE `manual_refunds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `new_arrival_variants`
--
ALTER TABLE `new_arrival_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_addresses`
--
ALTER TABLE `order_addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_webhook_events`
--
ALTER TABLE `payment_webhook_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_variant_images`
--
ALTER TABLE `product_variant_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `referral_coupons`
--
ALTER TABLE `referral_coupons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `referral_coupon_usage`
--
ALTER TABLE `referral_coupon_usage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `revoked_access_tokens`
--
ALTER TABLE `revoked_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `top_selling_variants`
--
ALTER TABLE `top_selling_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `birthday_coupons`
--
ALTER TABLE `birthday_coupons`
  ADD CONSTRAINT `fk_birthday_coupon_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `birthday_coupon_usage`
--
ALTER TABLE `birthday_coupon_usage`
  ADD CONSTRAINT `fk_birthday_usage_coupon` FOREIGN KEY (`birthday_coupon_id`) REFERENCES `birthday_coupons` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_birthday_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_birthday_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `email_password_reset_otps`
--
ALTER TABLE `email_password_reset_otps`
  ADD CONSTRAINT `fk_email_reset_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `festival_coupons`
--
ALTER TABLE `festival_coupons`
  ADD CONSTRAINT `fk_festival_coupon_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `festival_coupon_usage`
--
ALTER TABLE `festival_coupon_usage`
  ADD CONSTRAINT `fk_festival_usage_coupon` FOREIGN KEY (`festival_coupon_id`) REFERENCES `festival_coupons` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_festival_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_festival_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `first_order_coupons`
--
ALTER TABLE `first_order_coupons`
  ADD CONSTRAINT `fk_first_order_coupon_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `first_order_coupon_usage`
--
ALTER TABLE `first_order_coupon_usage`
  ADD CONSTRAINT `fk_first_order_usage_coupon` FOREIGN KEY (`first_order_coupon_id`) REFERENCES `first_order_coupons` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_first_order_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_first_order_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `hsn_profiles`
--
ALTER TABLE `hsn_profiles`
  ADD CONSTRAINT `fk_hsn_profiles_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inventory_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `manual_refunds`
--
ALTER TABLE `manual_refunds`
  ADD CONSTRAINT `fk_manual_refund_created_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_manual_refund_handled_admin` FOREIGN KEY (`handled_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_manual_refund_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_manual_refund_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_manual_refund_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `new_arrival_variants`
--
ALTER TABLE `new_arrival_variants`
  ADD CONSTRAINT `fk_new_arrival_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_new_arrival_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_addresses`
--
ALTER TABLE `order_addresses`
  ADD CONSTRAINT `fk_order_address_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_address_user_address` FOREIGN KEY (`user_address_id`) REFERENCES `user_addresses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `payment_status_history`
--
ALTER TABLE `payment_status_history`
  ADD CONSTRAINT `fk_payment_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_payment_history_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_hsn_profile` FOREIGN KEY (`hsn_profile_id`) REFERENCES `hsn_profiles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_variants_color` FOREIGN KEY (`color_id`) REFERENCES `colors` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_variants_size` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `product_variant_images`
--
ALTER TABLE `product_variant_images`
  ADD CONSTRAINT `fk_product_variant_images_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `referral_coupons`
--
ALTER TABLE `referral_coupons`
  ADD CONSTRAINT `fk_referral_coupon_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `referral_coupon_usage`
--
ALTER TABLE `referral_coupon_usage`
  ADD CONSTRAINT `fk_referral_usage_coupon` FOREIGN KEY (`referral_coupon_id`) REFERENCES `referral_coupons` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_referral_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_referral_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `top_selling_variants`
--
ALTER TABLE `top_selling_variants`
  ADD CONSTRAINT `fk_top_selling_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_top_selling_variant` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_user_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `fk_wishlists_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wishlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wishlists_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
