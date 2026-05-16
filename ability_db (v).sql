-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 14, 2026 at 05:39 PM
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

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'AUD', 'AUDIO', 'Audio equipment department - speakers, mixers, microphones, etc.', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(2, 'VID', 'VIDEO', 'Video equipment department - cameras, projectors, screens, etc.', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(3, 'LGT', 'LIGHTING', 'Lighting equipment department - stage lights, controllers, etc.', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(4, 'INT', 'INTERPRETATION', 'Interpretation equipment - headsets, transmitters, etc.', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(5, 'RIG', 'RIGGING', 'Rigging equipment - trusses, motors, safety gear, etc.', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(6, 'WRH', 'WAREHOUSE', 'Warehouse management and storage', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(7, 'DES', 'DESIGNING', 'Design department - CAD, planning, visualization', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(8, 'FUR', 'FURNITURE', 'Furniture and fixtures', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(9, 'GEN', 'GENERAL', 'General equipment and tools', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54'),
(10, 'ADM', 'ADMINISTRATION', 'Administrative equipment', 1, '2026-01-12 16:22:54', '2026-01-12 16:22:54');

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
(1, 'TV Screen - Neiitec', 'N15615512225090023', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768381515_69675c4b32309.png', 'qrcodes/qr_1.png', '2026-01-14 11:05:15', '2026-01-14 11:51:16', '', NULL, NULL),
(2, 'TV Screen - Neiitec', 'N15115542147250256', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768382915_696761c3b51c2.png', 'qrcodes/qr_2.png', '2026-01-14 11:28:35', '2026-01-14 11:28:58', '', NULL, NULL),
(3, 'TV Screen - Neiitec', 'N15615512224110494', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768383057_696762517de86.png', 'qrcodes/qr_3.png', '2026-01-14 11:30:57', '2026-01-14 11:31:19', '', NULL, NULL),
(4, 'TV Screen - Neiitec', 'N15115542147250269', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768383668_696764b4c347d.png', 'qrcodes/qr_4.png', '2026-01-14 11:41:08', '2026-01-14 11:41:31', '', NULL, NULL),
(5, 'TV Screen - Neiitec', 'N15615512225090019', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768383783_6967652770211.png', 'qrcodes/qr_5.png', '2026-01-14 11:43:03', '2026-01-14 11:43:26', '', NULL, NULL),
(6, 'TV Screen - Neiitec', 'N15115542147250264', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768383848_696765685030a.png', 'qrcodes/qr_6.png', '2026-01-14 11:44:08', '2026-01-14 11:44:30', '', NULL, NULL),
(8, 'TV Screen - Neiitec', 'N15115542147250223', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768384430_696767ae478d0.png', 'qrcodes/qr_8.png', '2026-01-14 11:53:50', '2026-01-14 11:54:14', '', NULL, NULL),
(9, 'TV Screen - Neiitec', 'N1511554214720259', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768385612_69676c4c984c5.png', 'qrcodes/qr_9.png', '2026-01-14 12:13:32', '2026-01-14 12:13:40', '', NULL, NULL),
(10, 'TV Screen - Neiitec', 'N15115542147250267', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768385924_69676d84a2c3a.png', 'qrcodes/qr_10.png', '2026-01-14 12:18:44', '2026-01-14 12:18:52', '', NULL, NULL),
(11, 'TV Screen - Neiitec', 'N15115542147250315', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768385971_69676db361d02.png', 'qrcodes/qr_11.png', '2026-01-14 12:19:31', '2026-01-14 12:19:39', '', NULL, NULL),
(12, 'TV Screen - Neiitec', 'N15115542147250268', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768386070_69676e16bcadf.png', 'qrcodes/qr_12.png', '2026-01-14 12:21:10', '2026-01-14 12:21:18', '', NULL, NULL),
(13, 'TV Screen - Neiitec', 'N15615512225090022', 'Video', 'Neiitec 55\' inch', '55\' inch', 'Video', '', '', 'Neiitec 55\' inch 55\' inch', 'good', '', '', '', 1, 'available', 'uploads/items/item_1768388894_6967791e5b2ec.png', 'pending', '2026-01-14 13:08:14', '2026-01-14 18:01:27', '', NULL, NULL),
(14, 'TV Screen - Neiitec', 'N15115542147250274', 'Video', 'Neiitec 55\'', '55\' inch', 'VID', '', '', 'Neiitec 55\' 55\' inch', 'good', 'Ndera', '', '', 1, 'available', 'uploads/items/item_1768388955_6967795b5652f.png', 'qrcodes/qr_14.png', '2026-01-14 13:09:15', '2026-01-14 13:09:22', '', NULL, NULL),
(55, 'TV Screen - Neiitec', 'N15115542147250280', 'Video', 'Neiitec 55\' inch', '55\' inch', 'Video', '', NULL, NULL, 'good', '', NULL, '', 1, 'available', NULL, 'qrcodes/qr_55.png', '2026-01-14 18:01:47', '2026-01-14 18:01:48', NULL, NULL, NULL),
(56, 'TV Screen - Neiitec', 'N15115542147250262', 'Video', 'Neiitec 55\' inch', '55\' inch', 'Video', '', NULL, NULL, 'good', '', NULL, '', 1, 'available', NULL, 'qrcodes/qr_56.png', '2026-01-14 18:01:48', '2026-01-14 18:01:50', NULL, NULL, NULL),
(57, 'Laptop Dell XPS 15', 'DLXPS202400001', 'IT', 'Dell', 'XPS 15', 'IT', 'CPU: i7, RAM: 16GB, SSD: 512GB, Screen: 15.6\"', NULL, NULL, 'excellent', 'IT Store', NULL, 'New arrival', 1, 'available', NULL, 'qrcodes/qr_57.png', '2026-01-14 18:15:24', '2026-01-14 18:15:25', NULL, NULL, NULL),
(58, 'Projector Epson', 'EPSPRJ20240001', 'AV', 'Epson', 'EB-710', 'AV', 'Brightness: 4000 lumens, Resolution: 1920x1080', NULL, NULL, 'good', 'AV Room', NULL, 'Needs calibration', 1, 'in_use', NULL, 'qrcodes/qr_58.png', '2026-01-14 18:15:25', '2026-01-14 18:15:27', NULL, NULL, NULL),
(59, 'TV Screen 55\"\"', 'NEITV202400001', 'Video', 'Neiitec', '55\"\"', 'Video', 'Size: 55\", Resolution: 4K, Refresh: 60Hz', NULL, NULL, 'good', 'Ndera', NULL, '', 1, 'available', NULL, 'qrcodes/qr_59.png', '2026-01-14 18:15:27', '2026-01-14 18:15:28', NULL, NULL, NULL),
(60, 'Microphone Shure', 'SHURMIC2024001', 'Audio', 'Shure', 'SM58', 'Audio', 'Type: Dynamic, Pattern: Cardioid, Impedance: 150Î©', NULL, NULL, 'excellent', 'Studio', NULL, '', 1, 'available', NULL, 'qrcodes/qr_60.png', '2026-01-14 18:15:28', '2026-01-14 18:15:30', NULL, NULL, NULL),
(61, 'Camera Canon', 'CANCAM20240001', 'Video', 'Canon', 'EOS R6', 'Video', 'Sensor: Full-frame, MP: 20, Video: 4K60', NULL, NULL, 'new', 'Photo Room', NULL, 'With lens kit', 1, 'reserved', NULL, 'qrcodes/qr_61.png', '2026-01-14 18:15:30', '2026-01-14 18:15:31', NULL, NULL, NULL);

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

--
-- Dumping data for table `scans`
--

INSERT INTO `scans` (`id`, `item_id`, `user_id`, `scan_type`, `from_location`, `to_location`, `destination_address`, `transport_user`, `user_contact`, `user_department`, `user_id_number`, `vehicle_plate`, `vehicle_type`, `vehicle_description`, `transport_notes`, `expected_return`, `priority`, `notes`, `scan_timestamp`) VALUES
(2, 1, 1, 'check_out', 'Ndera', 'MovenPick', '', 'Kayonga', '', '', '', 'RAD461', 'van', '', '', '2026-01-25 00:00:00', '', '', '2026-01-11 09:54:53');

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

--
-- Dumping data for table `scans_backup`
--

INSERT INTO `scans_backup` (`id`, `item_id`, `user_id`, `scan_type`, `from_location`, `to_location`, `destination_address`, `transport_user`, `user_contact`, `user_department`, `user_id_number`, `vehicle_plate`, `vehicle_type`, `vehicle_description`, `transport_notes`, `expected_return`, `priority`, `notes`, `scan_timestamp`) VALUES
(1, 18, 1, 'check_in', 'BK Arena', 'Stock', '', 'Kayonga', '', '', '', 'RAD467', '', '', '', NULL, '', '', '2026-01-11 09:53:21'),
(2, 1, 1, 'check_out', 'Ndera', 'MovenPick', '', 'Kayonga', '', '', '', 'RAD461', 'van', '', '', '2026-01-25 00:00:00', '', '', '2026-01-11 09:54:53');

-- --------------------------------------------------------

--
-- Table structure for table `scan_logs`
--

CREATE TABLE `scan_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
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

--
-- Dumping data for table `scan_logs`
--

INSERT INTO `scan_logs` (`id`, `user_id`, `item_id`, `scan_type`, `scan_method`, `scanned_data`, `location`, `notes`, `scan_timestamp`, `created_at`, `from_location`, `to_location`, `destination_address`, `transport_user`, `user_contact`, `user_department`, `user_id_number`, `vehicle_plate`, `vehicle_type`, `vehicle_description`, `transport_notes`, `expected_return`, `priority`) VALUES
(1, 6, 1, 'check_out', 'qrcode', '{\"timestamp\":\"2026-01-14T10:20:49.000Z\",\"action\":\"check_out\",\"scanned_at\":\"14\\/01\\/2026, 10:20:49\"}', 'KCC', '', '2026-01-14 09:20:49', '2026-01-14 09:20:49', 'Ndera', 'KCC', '', 'Patrick', '', 'VID', '', 'RAD467', 'van', '', '', '2026-01-17 00:00:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `department`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@ability.com', '$2y$10$YourHashedPasswordHere', 'IT', 'admin', 1, '2026-01-01 16:53:20', '2026-01-01 16:53:20'),
(2, 'kayonga', 'kayonga70@gmail.com', '$2y$10$dWw.WnWntwyuk.yyUAPw1.mrQeCUTHMBr6U6Se5cjVYbHjTxImIlu', 'IT', 'user', 1, '2026-01-01 16:59:20', '2026-01-01 16:59:20'),
(3, 'Prince_Lorenzo', 'princen@gmail.com', '$2y$10$MqvXseI7Pu6nC.EN981tOOJHBNe2ZjWU1o31swpIHqTB8tIPloHlK', 'Operations', 'user', 1, '2026-01-03 13:46:54', '2026-01-03 13:46:54'),
(4, 'mirene', 'mudacumurai@gmail.com', '$2y$10$D5IBesIEvaS5YpixWQQuZe9Y8EB/iMD.KiMjGJ5vNk3IbG8vLS2Ge', 'WAREHOUSE', 'user', 1, '2026-01-06 12:29:50', '2026-01-06 12:29:50'),
(5, 'qumuratwa', 'qumuratwa@gmail.com', '$2y$10$B1eupNNBqSl8weSbp3uecuWT0/svmW3El/RnfMRxbR5d3Mi9vhJcm', 'IT', 'user', 1, '2026-01-06 12:42:06', '2026-01-06 12:42:06'),
(6, 'patricn', 'patrickn26@gmail.com', '$2y$10$XSCS9iKZf219rIkGbiHZ7eACOvVGWEQqTYh/zg9DFnbfipgra5V6C', 'VIDEO', 'user', 1, '2026-01-14 08:55:42', '2026-01-14 08:55:42');

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
-- Dumping data for table `users_backup`
--

INSERT INTO `users_backup` (`id`, `username`, `email`, `password`, `department`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@ability.com', '$2y$10$YourHashedPasswordHere', 'IT', 'admin', 1, '2026-01-01 16:53:20', '2026-01-01 16:53:20'),
(2, 'kayonga', 'kayonga70@gmail.com', '$2y$10$dWw.WnWntwyuk.yyUAPw1.mrQeCUTHMBr6U6Se5cjVYbHjTxImIlu', 'IT', 'user', 1, '2026-01-01 16:59:20', '2026-01-01 16:59:20'),
(3, 'Prince_Lorenzo', 'princen@gmail.com', '$2y$10$MqvXseI7Pu6nC.EN981tOOJHBNe2ZjWU1o31swpIHqTB8tIPloHlK', 'Operations', 'user', 1, '2026-01-03 13:46:54', '2026-01-03 13:46:54'),
(4, 'mirene', 'mudacumurai@gmail.com', '$2y$10$D5IBesIEvaS5YpixWQQuZe9Y8EB/iMD.KiMjGJ5vNk3IbG8vLS2Ge', 'WAREHOUSE', 'user', 1, '2026-01-06 12:29:50', '2026-01-06 12:29:50'),
(5, 'qumuratwa', 'qumuratwa@gmail.com', '$2y$10$B1eupNNBqSl8weSbp3uecuWT0/svmW3El/RnfMRxbR5d3Mi9vhJcm', 'IT', 'user', 1, '2026-01-06 12:42:06', '2026-01-06 12:42:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `scan_id` (`scan_id`);

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
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_location` (`stock_location`);

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
-- Indexes for table `scan_logs`
--
ALTER TABLE `scan_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_scan_timestamp` (`scan_timestamp`);

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
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scans`
--
ALTER TABLE `scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `scan_logs`
--
ALTER TABLE `scan_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- Constraints for table `scans`
--
ALTER TABLE `scans`
  ADD CONSTRAINT `scans_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `scan_logs`
--
ALTER TABLE `scan_logs`
  ADD CONSTRAINT `scan_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `scan_logs_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
