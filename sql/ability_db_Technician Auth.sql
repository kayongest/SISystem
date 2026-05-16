-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 04, 2026 at 11:49 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ability_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accessories`
--

CREATE TABLE `accessories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `total_quantity` int(11) DEFAULT 1,
  `available_quantity` int(11) DEFAULT 1,
  `minimum_stock` int(11) DEFAULT 5,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accessories`
--

INSERT INTO `accessories` (`id`, `name`, `description`, `total_quantity`, `available_quantity`, `minimum_stock`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'AC/DC Adaptor (12V)', '', 1000, 708, 5, 1, '2026-02-04 10:52:05', '2026-02-04 10:53:26'),
(2, 'AC/DC Adapter', '', 1000, 844, 5, 1, '2026-02-04 10:53:03', '2026-02-04 10:57:53');

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `scan_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action_type`, `description`, `item_id`, `scan_id`, `created_at`) VALUES
(1, 2, 'user_updated', 'Updated user: kayonga', NULL, NULL, '2026-02-02 18:36:00'),
(2, 2, 'user_created', 'Created user: Prince_Lorenzo', NULL, NULL, '2026-02-02 18:37:00'),
(3, 2, 'user_updated', 'Updated user: Prince_Lorenzo', NULL, NULL, '2026-02-02 18:37:20'),
(4, 2, 'user_updated', 'Updated user: Prince_Lorenzo', NULL, NULL, '2026-02-02 18:37:29'),
(5, 2, 'user_status_changed', 'Changed status for user ID: 3', NULL, NULL, '2026-02-02 18:47:25'),
(6, 2, 'user_updated', 'Updated user: Prince_Lorenzo', NULL, NULL, '2026-02-02 18:50:18'),
(7, 2, 'user_updated', 'Updated user: Prince_Lorenzo', NULL, NULL, '2026-02-02 18:51:46'),
(8, 2, 'user_updated', 'Updated user: Prince_Lorenzo', NULL, NULL, '2026-02-02 18:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batch_actions_log`
--

CREATE TABLE `batch_actions_log` (
  `id` int(11) NOT NULL,
  `batch_id` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_actions_log`
--

INSERT INTO `batch_actions_log` (`id`, `batch_id`, `user_id`, `action_type`, `action_details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'BATCH-20260204180104-3e6d4b85', 2, 'batch_submit', '{\"total_items\":18,\"unique_items\":14,\"action_applied\":\"\",\"location_applied\":\"KCC\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-04 17:01:04'),
(2, 'BATCH-20260204204958-6b5fa39e', 2, 'batch_submit', '{\"total_items\":5,\"unique_items\":1,\"action_applied\":\"\",\"location_applied\":\"KCC\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-04 19:49:58'),
(3, 'BATCH-20260204213336-1911cc1e', 2, 'batch_submit', '{\"total_items\":1,\"unique_items\":1,\"action_applied\":\"\",\"location_applied\":\"KCC\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-04 20:33:36'),
(4, 'BATCH-20260204213948-08c1b75b', 2, 'batch_submit', '{\"total_items\":2,\"unique_items\":1,\"action_applied\":\"\",\"location_applied\":\"KCC\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-04 20:39:48'),
(5, 'BATCH-20260204222819-e7d93073', 2, 'batch_submit', '{\"total_items\":2,\"unique_items\":1,\"action_applied\":null,\"location_applied\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-04 21:28:19'),
(6, 'BATCH-20260204223305-6d5827a7', 2, 'batch_submit', '{\"total_items\":1,\"unique_items\":1,\"action_applied\":null,\"location_applied\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '2026-02-04 21:33:05');

-- --------------------------------------------------------

--
-- Table structure for table `batch_items`
--

CREATE TABLE `batch_items` (
  `id` int(11) NOT NULL,
  `batch_id` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `original_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `original_location` varchar(255) DEFAULT NULL,
  `new_location` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `scanned_at` datetime DEFAULT NULL,
  `added_to_batch_at` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_items`
--

INSERT INTO `batch_items` (`id`, `batch_id`, `item_id`, `item_name`, `serial_number`, `category`, `original_status`, `new_status`, `original_location`, `new_location`, `quantity`, `scanned_at`, `added_to_batch_at`, `notes`) VALUES
(1, 'BATCH-20260204180104-3e6d4b85', 40, 'Mini-Converter', '10422296', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:59:46', '2026-02-04 19:01:04', NULL),
(2, 'BATCH-20260204180104-3e6d4b85', 13, 'Mini-Converter', '10422317', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 2, '2026-02-04 16:59:31', '2026-02-04 19:01:04', 'Latest upload'),
(3, 'BATCH-20260204180104-3e6d4b85', 38, 'Mini-Converter', '12677462', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:59:24', '2026-02-04 19:01:04', 'Latest upload'),
(4, 'BATCH-20260204180104-3e6d4b85', 23, 'Mini-Converter', '12574888', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:59:12', '2026-02-04 19:01:04', 'Latest upload'),
(5, 'BATCH-20260204180104-3e6d4b85', 47, 'AV Matric Cross Converter', '2030D57203022', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:58:43', '2026-02-04 19:01:04', 'Latest upload'),
(6, 'BATCH-20260204180104-3e6d4b85', 43, '3G HDMI to SDI Audio', 'N/A', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:58:37', '2026-02-04 19:01:04', 'Latest upload'),
(7, 'BATCH-20260204180104-3e6d4b85', 44, 'Blackmagic 6G HDMI to SDI', '5257505', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:58:35', '2026-02-04 19:01:04', 'Latest upload'),
(8, 'BATCH-20260204180104-3e6d4b85', 5, 'Mini-Converter', '12575056', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:58:23', '2026-02-04 19:01:04', 'Latest upload'),
(9, 'BATCH-20260204180104-3e6d4b85', 1, 'Mini-Converter', '12575054', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:58:13', '2026-02-04 19:01:04', 'Latest upload'),
(10, 'BATCH-20260204180104-3e6d4b85', 11, 'Mini-Converter', '12675922', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:57:58', '2026-02-04 19:01:04', 'Latest upload'),
(11, 'BATCH-20260204180104-3e6d4b85', 9, 'Mini-Converter', '10678788', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:57:36', '2026-02-04 19:01:04', 'Latest upload'),
(12, 'BATCH-20260204180104-3e6d4b85', 46, 'AV Matric Cross Converter', '2030J56172351', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 3, '2026-02-04 16:57:25', '2026-02-04 19:01:04', 'Latest upload'),
(13, 'BATCH-20260204180104-3e6d4b85', 42, 'Mini-Converter', '10277416', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 2, '2026-02-04 16:57:09', '2026-02-04 19:01:04', NULL),
(14, 'BATCH-20260204180104-3e6d4b85', 37, 'Mini-Converter', '12675802', 'Video', 'available', 'available', 'Ndera Stock', 'KCC', 1, '2026-02-04 16:57:05', '2026-02-04 19:01:04', 'Latest upload'),
(15, 'BATCH-20260204204958-6b5fa39e', 105, 'Safety Helmet', 'SH-2024-001', 'Safety Equipment', 'available', 'available', 'Storage Room', 'KCC', 5, '2026-02-04 19:49:41', '2026-02-04 21:49:58', NULL),
(16, 'BATCH-20260204213336-1911cc1e', 104, 'Projector', 'PROJ-4K-001', 'Electronics', 'maintenance', 'maintenance', 'Repair Room', 'KCC', 1, '2026-02-04 20:33:23', '2026-02-04 22:33:36', NULL),
(17, 'BATCH-20260204213948-08c1b75b', 102, 'Power Drill', 'PD-2024-001', 'Tools', 'in_use', 'in_use', 'Construction Site', 'KCC', 2, '2026-02-04 20:39:41', '2026-02-04 22:39:48', NULL),
(18, 'BATCH-20260204222819-e7d93073', 102, 'Power Drill', 'PD-2024-001', 'Tools', 'in_use', 'in_use', 'Construction Site', 'Construction Site', 2, '2026-02-04 21:10:50', '2026-02-04 23:28:19', NULL),
(19, 'BATCH-20260204223305-6d5827a7', 103, 'Office Chair', 'OC-ERG-001', 'Furniture', 'available', 'available', 'Warehouse B', 'Warehouse B', 1, '2026-02-04 21:32:59', '2026-02-04 23:33:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `batch_scans`
--

CREATE TABLE `batch_scans` (
  `id` int(11) NOT NULL,
  `batch_id` varchar(50) NOT NULL,
  `batch_name` varchar(255) DEFAULT NULL,
  `total_items` int(11) NOT NULL,
  `unique_items` int(11) NOT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','processing','completed','cancelled') DEFAULT 'pending',
  `action_applied` varchar(50) DEFAULT NULL,
  `location_applied` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_scans`
--

INSERT INTO `batch_scans` (`id`, `batch_id`, `batch_name`, `total_items`, `unique_items`, `submitted_by`, `submitted_at`, `status`, `action_applied`, `location_applied`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'BATCH-20260204180104-3e6d4b85', 'Batch 2026-02-04 18:01', 18, 14, 2, '2026-02-04 19:01:04', 'completed', '', 'KCC', '', '2026-02-04 17:01:04', '2026-02-04 17:01:04'),
(2, 'BATCH-20260204204958-6b5fa39e', 'Batch 2026-02-04 20:49', 5, 1, 2, '2026-02-04 21:49:58', 'completed', '', 'KCC', '', '2026-02-04 19:49:58', '2026-02-04 19:49:58'),
(15, 'BATCH-20260204213336-1911cc1e', 'Batch 2026-02-04 21:33', 1, 1, 2, '2026-02-04 22:33:36', 'completed', '', 'KCC', '', '2026-02-04 20:33:36', '2026-02-04 20:33:36'),
(16, 'BATCH-20260204213948-08c1b75b', 'Batch 2026-02-04 21:39', 2, 1, 2, '2026-02-04 22:39:48', 'completed', '', 'KCC', '', '2026-02-04 20:39:48', '2026-02-04 20:39:48'),
(17, 'BATCH-20260204222819-e7d93073', 'Batch 2026-02-04 22:28', 2, 1, 2, '2026-02-04 23:28:19', 'completed', NULL, NULL, NULL, '2026-02-04 21:28:19', '2026-02-04 21:28:19'),
(18, 'BATCH-20260204223305-6d5827a7', 'Batch 2026-02-04 22:33', 1, 1, 2, '2026-02-04 23:33:05', 'completed', NULL, NULL, NULL, '2026-02-04 21:33:05', '2026-02-04 21:33:05');

-- --------------------------------------------------------

--
-- Table structure for table `batch_statistics`
--

CREATE TABLE `batch_statistics` (
  `id` int(11) NOT NULL,
  `batch_id` varchar(50) NOT NULL,
  `total_items` int(11) DEFAULT NULL,
  `available_items` int(11) DEFAULT NULL,
  `in_use_items` int(11) DEFAULT NULL,
  `maintenance_items` int(11) DEFAULT NULL,
  `categories_count` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_statistics`
--

INSERT INTO `batch_statistics` (`id`, `batch_id`, `total_items`, `available_items`, `in_use_items`, `maintenance_items`, `categories_count`, `created_at`) VALUES
(1, 'BATCH-20260204180104-3e6d4b85', 18, 14, 0, 0, 1, '2026-02-04 17:01:04'),
(2, 'BATCH-20260204204958-6b5fa39e', 5, 1, 0, 0, 1, '2026-02-04 19:49:58'),
(3, 'BATCH-20260204213336-1911cc1e', 1, 0, 0, 1, 1, '2026-02-04 20:33:36'),
(4, 'BATCH-20260204213948-08c1b75b', 2, 0, 1, 0, 1, '2026-02-04 20:39:48'),
(5, 'BATCH-20260204222819-e7d93073', 2, 0, 1, 0, 1, '2026-02-04 21:28:19'),
(6, 'BATCH-20260204223305-6d5827a7', 1, 1, 0, 0, 1, '2026-02-04 21:33:05');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `checkout_requests`
--

CREATE TABLE `checkout_requests` (
  `id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `request_code` varchar(20) NOT NULL COMMENT 'e.g., CHK-001',
  `purpose` text DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL COMMENT 'Supervisor/Admin ID',
  `approval_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `value` decimal(10,2) DEFAULT NULL,
  `status` enum('available','in_use','maintenance','reserved','lost','damaged') DEFAULT 'available',
  `stock_location` varchar(255) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_until` date DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_scans`
--

CREATE TABLE `equipment_scans` (
  `id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `equipment_name` varchar(255) DEFAULT NULL,
  `equipment_description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `scanned_at` datetime DEFAULT current_timestamp(),
  `batch_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `event_type` enum('production','meeting','shoot','conference','training','other') DEFAULT 'other',
  `status` enum('planned','ongoing','completed','cancelled') DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_assignments`
--

CREATE TABLE `event_assignments` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `check_out_date` datetime DEFAULT NULL,
  `expected_return_date` datetime DEFAULT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `status` enum('assigned','checked_out','returned','overdue') DEFAULT 'assigned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `brand_model` varchar(255) DEFAULT NULL,
  `condition` varchar(20) DEFAULT 'good',
  `stock_location` varchar(255) DEFAULT NULL,
  `storage_location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `status` varchar(20) DEFAULT 'available',
  `image` varchar(500) DEFAULT NULL,
  `qr_code` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tags` varchar(500) DEFAULT NULL,
  `last_scanned` timestamp NULL DEFAULT NULL,
  `current_location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `item_name`, `serial_number`, `category`, `brand`, `model`, `department`, `description`, `specifications`, `brand_model`, `condition`, `stock_location`, `storage_location`, `notes`, `quantity`, `status`, `image`, `qr_code`, `created_at`, `updated_at`, `tags`, `last_scanned`, `current_location`) VALUES
(1, 'Mini-Converter', '12575054', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_1.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(2, 'Mini-Converter', '10678825', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_2.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(3, 'Mini-Converter', '10620964', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_3.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(4, 'Mini-Converter', '12675732', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_4.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(5, 'Mini-Converter', '12575056', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_5.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(6, 'Mini-Converter', '12575032', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_6.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(7, 'Mini-Converter', '10620234', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_7.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(8, 'Mini-Converter', '10422332', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_8.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(9, 'Mini-Converter', '10678788', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_9.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(10, 'Mini-Converter', '10678743', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_10.png', '2026-02-04 12:49:00', '2026-02-04 12:59:28', NULL, NULL, NULL),
(11, 'Mini-Converter', '12675922', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_11.png', '2026-02-04 12:49:02', '2026-02-04 12:59:28', NULL, NULL, NULL),
(12, 'Mini-Converter', '10678769', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_12.png', '2026-02-04 12:49:02', '2026-02-04 12:59:28', NULL, NULL, NULL),
(13, 'Mini-Converter', '10422317', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_13.png', '2026-02-04 12:49:02', '2026-02-04 12:56:44', NULL, NULL, NULL),
(14, 'Mini-Converter', '12575045', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_14.png', '2026-02-04 12:49:03', '2026-02-04 12:59:28', NULL, NULL, NULL),
(15, 'Mini-Converter', '10000077', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_15.png', '2026-02-04 12:49:03', '2026-02-04 12:59:28', NULL, NULL, NULL),
(16, 'Mini-Converter', '10926017', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_16.png', '2026-02-04 12:49:03', '2026-02-04 12:59:28', NULL, NULL, NULL),
(17, 'Mini-Converter', '12674086', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_17.png', '2026-02-04 12:49:03', '2026-02-04 12:59:28', NULL, NULL, NULL),
(18, 'Mini-Converter', '11022662', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_18.png', '2026-02-04 12:49:03', '2026-02-04 12:59:28', NULL, NULL, NULL),
(19, 'Mini-Converter', '10278210', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_19.png', '2026-02-04 12:49:03', '2026-02-04 12:59:28', NULL, NULL, NULL),
(20, 'Mini-Converter', '10620293', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_20.png', '2026-02-04 12:49:03', '2026-02-04 12:59:28', NULL, NULL, NULL),
(21, 'Mini-Converter', '10620724', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_21.png', '2026-02-04 12:49:04', '2026-02-04 12:59:28', NULL, NULL, NULL),
(22, 'Mini-Converter', '10620334', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_22.png', '2026-02-04 12:49:04', '2026-02-04 12:59:28', NULL, NULL, NULL),
(23, 'Mini-Converter', '12574888', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_23.png', '2026-02-04 12:49:04', '2026-02-04 12:59:28', NULL, NULL, NULL),
(24, 'Mini-Converter', '12677589', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_24.png', '2026-02-04 12:49:04', '2026-02-04 12:56:44', NULL, NULL, NULL),
(25, 'Mini-Converter', '12575066', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_25.png', '2026-02-04 12:49:05', '2026-02-04 12:56:44', NULL, NULL, NULL),
(26, 'Mini-Converter', '9999866', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_26.png', '2026-02-04 12:49:07', '2026-02-04 12:56:44', NULL, NULL, NULL),
(27, 'Mini-Converter', '10278662', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_27.png', '2026-02-04 12:49:09', '2026-02-04 12:56:44', NULL, NULL, NULL),
(28, 'Mini-Converter', '10000058', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_28.png', '2026-02-04 12:49:10', '2026-02-04 12:56:44', NULL, NULL, NULL),
(29, 'Mini-Converter', '12675898', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_29.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(30, 'Mini-Converter', '10678753', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_30.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(31, 'Mini-Converter', '12673757', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_31.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(32, 'Mini-Converter', '12675884', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_32.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(33, 'Mini-Converter', '10926040', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_33.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(34, 'Mini-Converter', '12575014', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_34.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(35, 'Mini-Converter', '12677549', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_35.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(36, 'Mini-Converter', '12675890', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_36.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(37, 'Mini-Converter', '12675802', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_37.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(38, 'Mini-Converter', '12677462', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_38.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(39, 'Mini-Converter', '12675866', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_39.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(40, 'Mini-Converter', '10422296', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_40.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(41, 'Mini-Converter', '12575136', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_41.png', '2026-02-04 12:49:12', '2026-02-04 12:59:28', NULL, NULL, NULL),
(42, 'Mini-Converter', '10277416', 'Video', 'Blackmagic', 'UpDownCross HD', 'Video', '', NULL, NULL, 'good', 'Ndera Stock', NULL, '', 1, 'available', NULL, 'qrcodes/qr_42.png', '2026-02-04 12:49:12', '2026-02-04 12:56:44', NULL, NULL, NULL),
(43, '3G HDMI to SDI Audio', 'N/A', 'Video', 'Blackmagic', '', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_43.png', '2026-02-04 12:49:13', '2026-02-04 12:59:29', NULL, NULL, NULL),
(44, 'Blackmagic 6G HDMI to SDI', '5257505', 'Video', 'Blackmagic', '', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_44.png', '2026-02-04 12:49:13', '2026-02-04 12:59:29', NULL, NULL, NULL),
(45, 'Blackmagic 6G SDI to HDMI', '5238961', 'Video', '', '', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_45.png', '2026-02-04 12:49:13', '2026-02-04 12:59:29', NULL, NULL, NULL),
(46, 'AV Matric Cross Converter', '2030J56172351', 'Video', '', '', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_46.png', '2026-02-04 12:49:13', '2026-02-04 12:59:29', NULL, NULL, NULL),
(47, 'AV Matric Cross Converter', '2030D57203022', 'Video', '', '', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_47.png', '2026-02-04 12:49:13', '2026-02-04 12:59:29', NULL, NULL, NULL),
(48, 'AV Matric Cross Converter', '2030D57203291', 'Video', '', '', 'Video', '', NULL, NULL, 'Working', 'Ndera Stock', NULL, 'Latest upload', 1, 'available', NULL, 'qrcodes/qr_48.png', '2026-02-04 12:49:13', '2026-02-04 12:58:20', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `item_accessories`
--

CREATE TABLE `item_accessories` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `accessory_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_accessories`
--

INSERT INTO `item_accessories` (`id`, `item_id`, `accessory_id`, `assigned_date`) VALUES
(1, 1, 1, '2026-02-04 10:53:26'),
(2, 2, 1, '2026-02-04 10:53:26'),
(3, 3, 1, '2026-02-04 10:53:26'),
(4, 4, 1, '2026-02-04 10:53:26'),
(5, 5, 1, '2026-02-04 10:53:26'),
(6, 6, 1, '2026-02-04 10:53:26'),
(7, 7, 1, '2026-02-04 10:53:26'),
(8, 8, 1, '2026-02-04 10:53:26'),
(9, 9, 1, '2026-02-04 10:53:26'),
(10, 10, 1, '2026-02-04 10:53:26'),
(11, 11, 1, '2026-02-04 10:53:26'),
(12, 12, 1, '2026-02-04 10:53:26'),
(13, 13, 1, '2026-02-04 10:53:26'),
(14, 14, 1, '2026-02-04 10:53:26'),
(15, 15, 1, '2026-02-04 10:53:26'),
(16, 16, 1, '2026-02-04 10:53:26'),
(17, 17, 1, '2026-02-04 10:53:26'),
(18, 18, 1, '2026-02-04 10:53:26'),
(19, 19, 1, '2026-02-04 10:53:26'),
(20, 20, 1, '2026-02-04 10:53:26'),
(21, 21, 1, '2026-02-04 10:53:26'),
(22, 22, 1, '2026-02-04 10:53:26'),
(23, 23, 1, '2026-02-04 10:53:26'),
(24, 24, 1, '2026-02-04 10:53:26'),
(25, 25, 1, '2026-02-04 10:53:26'),
(26, 26, 1, '2026-02-04 10:53:26'),
(27, 27, 1, '2026-02-04 10:53:26'),
(28, 28, 1, '2026-02-04 10:53:26'),
(29, 29, 1, '2026-02-04 10:53:26'),
(30, 30, 1, '2026-02-04 10:53:26'),
(31, 31, 1, '2026-02-04 10:53:26'),
(32, 32, 1, '2026-02-04 10:53:26'),
(33, 33, 1, '2026-02-04 10:53:26'),
(34, 34, 1, '2026-02-04 10:53:26'),
(35, 35, 1, '2026-02-04 10:53:26'),
(36, 36, 1, '2026-02-04 10:53:26'),
(37, 37, 1, '2026-02-04 10:53:26'),
(38, 38, 1, '2026-02-04 10:53:26'),
(39, 39, 1, '2026-02-04 10:53:26'),
(40, 40, 1, '2026-02-04 10:53:26'),
(41, 41, 1, '2026-02-04 10:53:26'),
(42, 42, 1, '2026-02-04 10:53:26'),
(43, 43, 2, '2026-02-04 10:57:53'),
(44, 46, 2, '2026-02-04 10:57:53'),
(45, 47, 2, '2026-02-04 10:57:53'),
(46, 48, 2, '2026-02-04 10:57:53'),
(47, 44, 2, '2026-02-04 10:57:53'),
(48, 45, 2, '2026-02-04 10:57:53');

--
-- Triggers `item_accessories`
--
DELIMITER $$
CREATE TRIGGER `after_item_accessory_delete` AFTER DELETE ON `item_accessories` FOR EACH ROW BEGIN
    UPDATE accessories 
    SET available_quantity = available_quantity + 1 
    WHERE id = OLD.accessory_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_item_accessory_insert` AFTER INSERT ON `item_accessories` FOR EACH ROW BEGIN
    UPDATE accessories 
    SET available_quantity = available_quantity - 1 
    WHERE id = NEW.accessory_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scans`
--

CREATE TABLE `scans` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scan_type` enum('check_in','check_out','maintenance','inventory') NOT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `transport_user` varchar(255) DEFAULT NULL,
  `user_contact` varchar(100) DEFAULT NULL,
  `user_department` varchar(100) DEFAULT NULL,
  `user_id_number` varchar(50) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_description` text DEFAULT NULL,
  `transport_notes` text DEFAULT NULL,
  `expected_return` datetime DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `scan_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scans_backup`
--

CREATE TABLE `scans_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scan_type` enum('check_in','check_out','maintenance','inventory') NOT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `transport_user` varchar(255) DEFAULT NULL,
  `user_contact` varchar(100) DEFAULT NULL,
  `user_department` varchar(100) DEFAULT NULL,
  `user_id_number` varchar(50) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_description` text DEFAULT NULL,
  `transport_notes` text DEFAULT NULL,
  `expected_return` datetime DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `scan_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scan_batches`
--

CREATE TABLE `scan_batches` (
  `id` int(11) NOT NULL,
  `batch_id` varchar(50) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `total_items` int(11) DEFAULT 0,
  `scan_date` datetime DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scan_logs`
--

CREATE TABLE `scan_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `scan_type` varchar(20) NOT NULL DEFAULT 'scan',
  `scan_method` varchar(20) DEFAULT 'qrcode',
  `scanned_data` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `scan_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `from_location` varchar(255) DEFAULT 'Stock',
  `to_location` varchar(255) DEFAULT NULL,
  `destination_address` text DEFAULT NULL,
  `transport_user` varchar(255) DEFAULT NULL,
  `user_contact` varchar(100) DEFAULT NULL,
  `user_department` varchar(100) DEFAULT NULL,
  `user_id_number` varchar(50) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_description` text DEFAULT NULL,
  `transport_notes` text DEFAULT NULL,
  `expected_return` datetime DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `role` enum('admin','manager','user','stock_manager','stock_controller','tech_lead','technician','driver') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `full_name`, `password`, `department`, `phone`, `hire_date`, `address`, `profile_image`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'kayonga', 'kayonga70@gmail.com', NULL, '$2y$10$VFy8JTumJ3YHXK4Psk2pFOTXdvndjNNn/oM5sGbdIFM1zaIFjlk9W', 'IT', NULL, NULL, NULL, NULL, 'technician', 0, '2026-02-02 14:02:24', '2026-02-02 20:55:04'),
(2, 'kayongest', 'admin@ab.com', NULL, '$2y$10$7egt8RVM04aEF88x4xjlb.zvnbc.r9cxykPet5vwgIAXewH2vksu2', 'IT', NULL, NULL, NULL, NULL, 'admin', 1, '2026-02-02 18:29:40', '2026-02-02 18:30:39'),
(3, 'Prince_Lorenzo', 'princelorenzo@gmail.com', NULL, '$2y$10$AacOP7Ogvbmv3K.uJx0/gOuVMuP6zuzD9m9ru6tLCWasCnD8yggiC', 'Stock', NULL, NULL, NULL, NULL, 'user', 0, '2026-02-02 18:37:00', '2026-02-02 20:55:21'),
(4, 'admin', 'admin@example.com', NULL, '482c811da5d5b4bc6d497ffa98491e38', NULL, NULL, NULL, NULL, NULL, 'admin', 1, '2026-02-04 20:30:03', '2026-02-04 20:30:03');

-- --------------------------------------------------------

--
-- Table structure for table `users_backup`
--

CREATE TABLE `users_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `role` enum('admin','manager','user') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accessories`
--
ALTER TABLE `accessories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `scan_id` (`scan_id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_logs_user` (`user_id`),
  ADD KEY `idx_activity_logs_created` (`created_at`);

--
-- Indexes for table `batch_actions_log`
--
ALTER TABLE `batch_actions_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_action_type` (`action_type`);

--
-- Indexes for table `batch_items`
--
ALTER TABLE `batch_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_serial_number` (`serial_number`);

--
-- Indexes for table `batch_scans`
--
ALTER TABLE `batch_scans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_id` (`batch_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_submitted_at` (`submitted_at`);

--
-- Indexes for table `batch_statistics`
--
ALTER TABLE `batch_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch` (`batch_id`),
  ADD KEY `idx_batch_id` (`batch_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `checkout_requests`
--
ALTER TABLE `checkout_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_code` (`request_code`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_technician_id` (`technician_id`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_request_code` (`request_code`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`);

--
-- Indexes for table `equipment_scans`
--
ALTER TABLE `equipment_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `event_assignments`
--
ALTER TABLE `event_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_location` (`stock_location`);

--
-- Indexes for table `item_accessories`
--
ALTER TABLE `item_accessories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_accessory` (`item_id`,`accessory_id`),
  ADD KEY `accessory_id` (`accessory_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scans`
--
ALTER TABLE `scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_scan_timestamp` (`scan_timestamp`),
  ADD KEY `idx_scan_type` (`scan_type`);

--
-- Indexes for table `scan_batches`
--
ALTER TABLE `scan_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_id` (`batch_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `scan_logs`
--
ALTER TABLE `scan_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_scan_timestamp` (`scan_timestamp`),
  ADD KEY `idx_request_id` (`request_id`),
  ADD KEY `fk_scan_logs_technician_id` (`technician_id`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accessories`
--
ALTER TABLE `accessories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_actions_log`
--
ALTER TABLE `batch_actions_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `batch_items`
--
ALTER TABLE `batch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `batch_scans`
--
ALTER TABLE `batch_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `batch_statistics`
--
ALTER TABLE `batch_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `checkout_requests`
--
ALTER TABLE `checkout_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_scans`
--
ALTER TABLE `equipment_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_assignments`
--
ALTER TABLE `event_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `item_accessories`
--
ALTER TABLE `item_accessories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scans`
--
ALTER TABLE `scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scan_batches`
--
ALTER TABLE `scan_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scan_logs`
--
ALTER TABLE `scan_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `technicians`
--
ALTER TABLE `technicians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `activity_log_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `activity_log_ibfk_3` FOREIGN KEY (`scan_id`) REFERENCES `scans` (`id`);

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_items`
--
ALTER TABLE `batch_items`
  ADD CONSTRAINT `batch_items_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batch_scans` (`batch_id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `checkout_requests`
--
ALTER TABLE `checkout_requests`
  ADD CONSTRAINT `checkout_requests_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `checkout_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `technicians` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equipment_scans`
--
ALTER TABLE `equipment_scans`
  ADD CONSTRAINT `equipment_scans_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_assignments`
--
ALTER TABLE `event_assignments`
  ADD CONSTRAINT `event_assignments_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_assignments_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `event_assignments_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);

--
-- Constraints for table `item_accessories`
--
ALTER TABLE `item_accessories`
  ADD CONSTRAINT `item_accessories_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_accessories_ibfk_2` FOREIGN KEY (`accessory_id`) REFERENCES `accessories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scans`
--
ALTER TABLE `scans`
  ADD CONSTRAINT `scans_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `scan_batches`
--
ALTER TABLE `scan_batches`
  ADD CONSTRAINT `scan_batches_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scan_logs`
--
ALTER TABLE `scan_logs`
  ADD CONSTRAINT `fk_request_id` FOREIGN KEY (`request_id`) REFERENCES `checkout_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scan_logs_technician_id` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `scan_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `scan_logs_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`),
  ADD CONSTRAINT `scan_logs_ibfk_3` FOREIGN KEY (`request_id`) REFERENCES `checkout_requests` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
