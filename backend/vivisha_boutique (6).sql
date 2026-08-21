-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 20, 2026 at 02:06 PM
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
(1, 'Vivisha Admin', 'admin@gmail.com', '9876543210', '$2y$10$O7sS/A2.XELVYBnTRMWfOusz92K/nUhChfVZ26qMpFY/XDuXAGWMC', 'admin', 'active', '2026-08-19 13:41:02', '2026-08-14 13:51:45', '2026-08-19 08:11:02');

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
(3, 1, 'active', '2026-08-19 09:43:19', '2026-08-19 09:43:19');

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
(2, 3, 1, 1, 5, 2250.00, '2026-08-19 09:43:19', '2026-08-19 09:43:19');

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
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
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

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `description`, `is_new_arrival`, `is_featured`, `is_best_seller`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'poonam-sarees', 'poonam-sarees', 'Premium Poonam saree collection', 1, 1, 0, 'active', '2026-08-18 12:38:36', '2026-08-19 06:16:05');

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

INSERT INTO `product_variants` (`id`, `product_id`, `size_id`, `color_id`, `sku`, `variant_name`, `original_price`, `discount_type`, `discount_value`, `selling_price`, `stock_quantity`, `reserved_quantity`, `low_stock_limit`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 1, 10, NULL, 'POONAM-MAROON-FREE', NULL, 2500.00, 'percentage', 10.00, 2250.00, 50, 0, 10, 1, '2026-08-19 08:11:17', '2026-08-19 09:00:06'),
(2, 1, 5, 4, 'POONAM-BLACK-FREE', 'Black- Free Size', 2500.00, 'percentage', 10.00, 2250.00, 30, 0, 10, 1, '2026-08-19 08:45:45', '2026-08-19 08:45:45');

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
(5, 1, 'uploads/products/product_1_variant_1_45682ef36b5030c0e9dec154.webp', 'Poonam Saree Back', 0, 2, 'active', '2026-08-19 09:09:24', '2026-08-19 09:09:24');

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
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
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
(1, 'Ragul Raj', '7418024669', 'ragulthulasi82@gmail.com', '$2y$10$cjWIm8pGTgvZhW5ZSePB1uqNSzu7oQh7fhsZOgLqe1cvCnwJ372Ua', '2000-05-15', 'active', '2026-08-20 17:34:07', '2026-08-14 13:56:26', '2026-08-20 12:04:07'),
(2, 'Ragul', '9876543111', 'ragul@gmail.com', '$2y$10$G3iJeLhNw4Os0.I8e9i6LOxX0eRTKVgvK1NM.RJT4OAGMN/UFlNYu', '2000-06-15', 'active', '2026-08-19 15:05:54', '2026-08-14 13:57:34', '2026-08-19 09:35:54'),
(3, 'Siva', '9876541111', 'siva@gmail.com', '$2y$10$oAxDrB5ZEf33LFosm12dR.9zXej5yEzf0Upf2DVJfwr.5jfcHsw7q', '2000-06-15', 'active', '2026-08-18 12:18:32', '2026-08-18 06:47:47', '2026-08-18 06:48:32');

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
(2, 1, 'work', '13', 'Thirukurippu Street', 'water colony', 'madurai', 'madurai', 'Tamil Nadu', '630102', 'Near Leader School', 0, 'active', '2026-08-18 07:36:46', '2026-08-18 07:48:27');

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
(2, 1, 1, 1, '2026-08-20 06:42:56', '2026-08-20 06:42:56');

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
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inventory_admin` (`created_by_admin_id`),
  ADD KEY `idx_inventory_variant_id` (`variant_id`),
  ADD KEY `idx_inventory_transaction_type` (`transaction_type`),
  ADD KEY `idx_inventory_reference` (`reference_type`,`reference_id`);

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
  ADD KEY `idx_products_best_seller` (`is_best_seller`);

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
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `email` (`email`);

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
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_variant_images`
--
ALTER TABLE `product_variant_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_inventory_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

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
