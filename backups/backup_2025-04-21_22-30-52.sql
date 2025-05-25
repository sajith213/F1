-- Petrol Pump Management System Database Backup
-- Generated on: 2025-04-21 22:30:52
-- Database: demo_fuel

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Table structure for table `attendance_records`

DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` enum('present','absent','late','half_day','leave') DEFAULT 'present',
  `hours_worked` decimal(5,2) GENERATED ALWAYS AS (case when `time_in` is not null and `time_out` is not null then round(timestampdiff(SECOND,`time_in`,`time_out`) / 3600,2) else 0 end) STORED,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  KEY `staff_id` (`staff_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `attendance_records`

INSERT INTO `attendance_records` (`attendance_id`, `staff_id`, `attendance_date`, `time_in`, `time_out`, `status`, `hours_worked`, `remarks`, `recorded_by`, `created_at`, `updated_at`) VALUES
('1', '3', '2025-04-09', '2025-04-09 06:00:00', '2025-04-09 22:00:00', 'present', '16.00', '', '1', '2025-04-10 08:47:09', '2025-04-10 08:48:44'),
('2', '1', '2025-04-09', '2025-04-09 14:30:00', '2025-04-09 22:30:00', 'present', '8.00', '', '1', '2025-04-10 11:10:35', '2025-04-10 11:10:35'),
('3', '2', '2025-04-09', '2025-04-09 06:00:00', '2025-04-09 14:00:00', 'present', '8.00', '', '1', '2025-04-10 11:12:59', '2025-04-10 11:19:43'),
('4', '7', '2025-04-09', '2025-04-09 06:00:00', '2025-04-09 22:00:00', 'present', '16.00', '', '1', '2025-04-10 11:21:41', '2025-04-10 11:21:41'),
('5', '4', '2025-04-09', '2025-04-09 06:00:00', '2025-04-09 21:00:00', 'present', '15.00', '', '1', '2025-04-10 11:22:25', '2025-04-10 11:24:04'),
('6', '9', '2025-04-09', '2025-04-09 06:00:00', '2025-04-09 22:00:00', 'present', '16.00', '', '1', '2025-04-10 11:25:35', '2025-04-10 11:25:35'),
('7', '8', '2025-04-09', '2025-04-09 06:00:00', '2025-04-09 21:00:00', 'present', '15.00', '', '1', '2025-04-10 11:26:13', '2025-04-10 11:26:13'),
('8', '10', '2025-04-09', '2025-04-09 06:00:00', '2025-04-09 22:00:00', 'present', '16.00', '', '1', '2025-04-10 11:26:46', '2025-04-10 11:26:46'),
('9', '5', '2025-04-09', NULL, NULL, 'absent', '0.00', '', '1', '2025-04-10 11:37:19', '2025-04-10 11:37:19'),
('10', '6', '2025-04-09', NULL, NULL, 'absent', '0.00', '', '1', '2025-04-10 11:37:53', '2025-04-10 11:37:53'),
('11', '3', '2025-04-10', NULL, NULL, 'leave', '0.00', '', '1', '2025-04-12 13:40:56', '2025-04-12 13:40:56'),
('12', '5', '2025-04-10', '2025-04-10 06:00:00', '2025-04-10 22:00:00', 'present', '16.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('13', '9', '2025-04-10', '2025-04-10 06:00:00', '2025-04-10 22:00:00', 'present', '16.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('14', '10', '2025-04-10', '2025-04-10 18:00:00', '2025-04-10 22:00:00', 'present', '4.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('15', '2', '2025-04-10', '2025-04-10 14:00:00', '2025-04-10 22:00:00', 'present', '8.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('16', '4', '2025-04-10', '2025-04-10 06:00:00', '2025-04-10 21:00:00', 'present', '15.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('17', '7', '2025-04-10', NULL, NULL, 'leave', '0.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('18', '6', '2025-04-10', '2025-04-10 18:00:00', '2025-04-10 21:00:00', 'present', '3.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('19', '1', '2025-04-10', '2025-04-10 06:00:00', '2025-04-10 14:00:00', 'present', '8.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('20', '8', '2025-04-10', NULL, NULL, 'leave', '0.00', NULL, '1', '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('21', '5', '2025-04-12', NULL, NULL, 'leave', '0.00', NULL, '1', '2025-04-12 15:24:01', '2025-04-12 15:24:01'),
('22', '9', '2025-04-12', '2025-04-12 06:00:00', '2025-04-12 21:00:00', 'present', '15.00', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/demo.kdj.lk/modules/attendance/record_attendance.php</b> on line <b>183</b><br />\r\n', '1', '2025-04-12 15:24:01', '2025-04-12 15:26:57'),
('23', '10', '2025-04-12', '2025-04-12 06:00:00', '2025-04-12 22:00:00', 'present', '16.00', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/demo.kdj.lk/modules/attendance/record_attendance.php</b> on line <b>183</b><br />\r\n', '1', '2025-04-12 15:24:01', '2025-04-12 15:27:49'),
('24', '3', '2025-04-12', '2025-04-12 06:00:00', '2025-04-12 21:00:00', 'present', '15.00', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/demo.kdj.lk/modules/attendance/record_attendance.php</b> on line <b>183</b><br />\r\n', '1', '2025-04-12 15:24:01', '2025-04-12 15:28:03'),
('25', '2', '2025-04-12', NULL, NULL, 'leave', '0.00', NULL, '1', '2025-04-12 15:24:01', '2025-04-12 15:24:01'),
('26', '4', '2025-04-12', '2025-04-12 06:00:00', '2025-04-12 22:00:00', 'present', '16.00', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/demo.kdj.lk/modules/attendance/record_attendance.php</b> on line <b>183</b><br />\r\n', '1', '2025-04-12 15:24:01', '2025-04-12 15:28:18'),
('27', '7', '2025-04-12', NULL, NULL, 'absent', '0.00', NULL, '1', '2025-04-12 15:24:01', '2025-04-12 15:24:01'),
('28', '6', '2025-04-12', NULL, NULL, 'leave', '0.00', NULL, '1', '2025-04-12 15:24:01', '2025-04-12 15:24:01'),
('29', '1', '2025-04-12', '2025-04-12 06:00:00', '2025-04-12 22:00:00', 'present', '16.00', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/demo.kdj.lk/modules/attendance/record_attendance.php</b> on line <b>183</b><br />\r\n', '1', '2025-04-12 15:24:01', '2025-04-12 15:28:38'),
('30', '8', '2025-04-12', '2025-04-12 06:00:00', '2025-04-12 22:00:00', 'present', '16.00', '<br />\r\n<b>Deprecated</b>:  htmlspecialchars(): Passing null to parameter #1 ($string) of type string is deprecated in <b>/www/wwwroot/demo.kdj.lk/modules/attendance/record_attendance.php</b> on line <b>183</b><br />\r\n', '1', '2025-04-12 15:24:01', '2025-04-12 15:29:11');

-- Table structure for table `backup_history`

DROP TABLE IF EXISTS `backup_history`;
CREATE TABLE `backup_history` (
  `backup_id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `is_restore` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`backup_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `cash_adjustments`

DROP TABLE IF EXISTS `cash_adjustments`;
CREATE TABLE `cash_adjustments` (
  `adjustment_id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` int(11) NOT NULL,
  `adjustment_date` datetime NOT NULL,
  `adjustment_type` enum('allowance','deduction','write-off') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `approved_by` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`adjustment_id`),
  KEY `record_id` (`record_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `cash_adjustments_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `daily_cash_records` (`record_id`),
  CONSTRAINT `cash_adjustments_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `daily_cash_records`

DROP TABLE IF EXISTS `daily_cash_records`;
CREATE TABLE `daily_cash_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `record_date` date NOT NULL,
  `staff_id` int(11) NOT NULL,
  `pump_id` int(11) NOT NULL,
  `shift` enum('morning','afternoon','evening','night') NOT NULL,
  `expected_amount` decimal(10,2) NOT NULL,
  `collected_amount` decimal(10,2) NOT NULL,
  `difference` decimal(10,2) GENERATED ALWAYS AS (`collected_amount` - `expected_amount`) STORED,
  `difference_type` enum('excess','shortage','balanced') GENERATED ALWAYS AS (case when `collected_amount` > `expected_amount` then _utf8mb4'excess' when `collected_amount` < `expected_amount` then _utf8mb4'shortage' else _utf8mb4'balanced' end) STORED,
  `status` enum('pending','verified','settled','disputed') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `settlement_status` enum('pending','processed','adjusted') DEFAULT 'pending',
  `settlement_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`record_id`),
  KEY `staff_id` (`staff_id`),
  KEY `pump_id` (`pump_id`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `daily_cash_records_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `daily_cash_records_ibfk_2` FOREIGN KEY (`pump_id`) REFERENCES `pumps` (`pump_id`),
  CONSTRAINT `daily_cash_records_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `daily_cash_records`

INSERT INTO `daily_cash_records` (`record_id`, `record_date`, `staff_id`, `pump_id`, `shift`, `expected_amount`, `collected_amount`, `difference`, `difference_type`, `status`, `verified_by`, `verification_date`, `settlement_status`, `settlement_notes`, `created_at`, `updated_at`) VALUES
('1', '2025-04-09', '3', '9', 'morning', '614729.26', '614730.00', '0.74', 'excess', 'pending', NULL, NULL, 'pending', NULL, '2025-04-12 10:26:53', '2025-04-12 10:26:53'),
('2', '2025-04-09', '3', '10', 'morning', '348975.48', '348979.00', '3.52', 'excess', 'pending', NULL, NULL, 'pending', NULL, '2025-04-12 10:31:49', '2025-04-12 10:31:49'),
('3', '2025-04-09', '4', '11', 'morning', '738689.95', '738701.80', '11.85', 'excess', 'pending', NULL, NULL, 'pending', NULL, '2025-04-12 10:33:34', '2025-04-12 10:33:34'),
('4', '2025-04-09', '4', '12', 'morning', '0.00', '0.00', '0.00', 'balanced', 'verified', '1', '2025-04-12 02:17:59', 'pending', NULL, '2025-04-12 10:34:11', '2025-04-12 11:47:59'),
('5', '2025-04-09', '4', '12', 'morning', '0.00', '0.00', '0.00', 'balanced', 'pending', NULL, NULL, 'pending', NULL, '2025-04-12 10:34:11', '2025-04-12 10:34:11'),
('6', '2025-04-09', '7', '13', 'morning', '0.00', '0.00', '0.00', 'balanced', 'verified', '1', '2025-04-12 02:18:13', 'pending', NULL, '2025-04-12 10:34:32', '2025-04-12 11:48:13'),
('7', '2025-04-09', '8', '1', 'morning', '339877.64', '339878.00', '0.36', 'excess', 'verified', '1', '2025-04-12 02:18:45', 'pending', NULL, '2025-04-12 11:36:35', '2025-04-12 11:48:45'),
('8', '2025-04-09', '8', '2', 'morning', '384707.03', '384723.00', '15.97', 'excess', 'verified', '1', '2025-04-12 02:18:38', 'pending', NULL, '2025-04-12 11:40:30', '2025-04-12 11:48:38'),
('9', '2025-04-09', '9', '3', 'morning', '317331.69', '317332.00', '0.31', 'excess', 'verified', '1', '2025-04-12 02:18:33', 'pending', NULL, '2025-04-12 11:41:32', '2025-04-12 11:48:33'),
('10', '2025-04-09', '9', '4', 'morning', '376994.15', '377045.00', '50.85', 'excess', 'verified', '1', '2025-04-12 02:18:28', 'pending', NULL, '2025-04-12 11:42:19', '2025-04-12 11:48:28'),
('11', '2025-04-09', '10', '5', 'morning', '738832.59', '738835.00', '2.41', 'excess', 'verified', '1', '2025-04-12 02:18:21', 'pending', NULL, '2025-04-12 11:43:30', '2025-04-12 11:48:21'),
('12', '2025-04-09', '3', '9', 'morning', '614729.26', '614730.00', '0.74', 'excess', 'verified', '1', '2025-04-12 02:14:49', 'pending', NULL, '2025-04-12 11:44:29', '2025-04-12 11:44:49'),
('13', '2025-04-09', '3', '10', 'morning', '348974.34', '348979.00', '4.66', 'excess', 'verified', '1', '2025-04-12 02:16:00', 'pending', NULL, '2025-04-12 11:45:57', '2025-04-12 11:46:00'),
('14', '2025-04-09', '4', '11', 'morning', '738689.38', '738701.80', '12.42', 'excess', 'verified', '1', '2025-04-12 02:17:27', 'pending', NULL, '2025-04-12 11:47:25', '2025-04-12 11:47:27');

-- Table structure for table `delivery_items`

DROP TABLE IF EXISTS `delivery_items`;
CREATE TABLE `delivery_items` (
  `delivery_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `tank_id` int(11) NOT NULL,
  `quantity_ordered` decimal(10,2) NOT NULL,
  `quantity_received` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`delivery_item_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  CONSTRAINT `delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `fuel_deliveries` (`delivery_id`),
  CONSTRAINT `delivery_items_ibfk_2` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `delivery_items`

INSERT INTO `delivery_items` (`delivery_item_id`, `delivery_id`, `fuel_type_id`, `tank_id`, `quantity_ordered`, `quantity_received`, `notes`) VALUES
('1', '1', '2', '1', '6600.00', '3300.00', ''),
('2', '1', '2', '2', '6600.00', '3300.00', ''),
('3', '2', '3', '3', '6600.00', '6600.00', ''),
('4', '3', '2', '1', '6600.00', '3300.00', ''),
('5', '3', '2', '2', '6600.00', '3300.00', ''),
('6', '4', '2', '1', '6600.00', '6600.00', ''),
('7', '5', '3', '3', '6600.00', '6600.00', ''),
('8', '6', '2', '1', '6600.00', '3300.00', ''),
('9', '6', '2', '2', '6600.00', '3300.00', ''),
('10', '7', '2', '2', '6600.00', '6600.00', ''),
('11', '8', '3', '4', '6600.00', '6600.00', ''),
('12', '9', '3', '3', '6600.00', '6600.00', '');

-- Table structure for table `fuel_deliveries`

DROP TABLE IF EXISTS `fuel_deliveries`;
CREATE TABLE `fuel_deliveries` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `delivery_reference` varchar(50) DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `status` enum('pending','partial','complete') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`delivery_id`),
  KEY `po_id` (`po_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `fuel_deliveries_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `fuel_deliveries_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `fuel_deliveries`

INSERT INTO `fuel_deliveries` (`delivery_id`, `po_id`, `delivery_date`, `delivery_reference`, `received_by`, `status`, `notes`, `created_at`, `updated_at`) VALUES
('1', '1', '2025-04-09', 'BO31071', '1', 'complete', '', '2025-04-09 14:32:19', '2025-04-09 14:32:19'),
('2', '4', '2025-04-10', 'BO033418', '1', 'complete', '', '2025-04-12 12:35:20', '2025-04-12 12:35:20'),
('3', '7', '2025-04-10', 'BO033493', '1', 'complete', '', '2025-04-12 12:46:39', '2025-04-12 12:46:39'),
('4', '9', '2025-04-11', 'BO033493', '1', 'complete', '', '2025-04-12 13:05:13', '2025-04-12 13:05:13'),
('5', '11', '2025-04-11', 'BO033418', '1', 'complete', '', '2025-04-12 13:15:04', '2025-04-12 13:15:04'),
('6', '12', '2025-04-12', 'BO34387', '1', 'complete', '', '2025-04-12 22:59:43', '2025-04-12 22:59:43'),
('7', '14', '2025-04-12', 'BO34386', '1', 'complete', '', '2025-04-12 23:03:37', '2025-04-12 23:03:37'),
('8', '15', '2025-04-12', 'BO34347', '1', 'complete', '', '2025-04-12 23:08:43', '2025-04-12 23:08:43'),
('9', '16', '2025-04-12', 'BO34346', '1', 'complete', '', '2025-04-12 23:12:12', '2025-04-12 23:12:12');

-- Table structure for table `fuel_prices`

DROP TABLE IF EXISTS `fuel_prices`;
CREATE TABLE `fuel_prices` (
  `price_id` int(11) NOT NULL AUTO_INCREMENT,
  `fuel_type_id` int(11) NOT NULL,
  `effective_date` date NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `profit_margin` decimal(10,2) GENERATED ALWAYS AS (`selling_price` - `purchase_price`) STORED,
  `profit_percentage` decimal(5,2) GENERATED ALWAYS AS ((`selling_price` - `purchase_price`) / `purchase_price` * 100) STORED,
  `status` enum('planned','active','expired') DEFAULT 'planned',
  `set_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`price_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  KEY `set_by` (`set_by`),
  CONSTRAINT `fuel_prices_ibfk_1` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`),
  CONSTRAINT `fuel_prices_ibfk_2` FOREIGN KEY (`set_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `fuel_prices`

INSERT INTO `fuel_prices` (`price_id`, `fuel_type_id`, `effective_date`, `purchase_price`, `selling_price`, `profit_margin`, `profit_percentage`, `status`, `set_by`, `notes`, `created_at`, `updated_at`) VALUES
('1', '3', '2025-04-09', '279.04', '286.00', '6.96', '2.49', 'active', '1', '', '2025-04-10 11:46:29', '2025-04-10 11:46:29'),
('2', '2', '2025-04-09', '291.26', '299.00', '7.74', '2.66', 'active', '1', '', '2025-04-10 11:49:09', '2025-04-10 11:49:09');

-- Table structure for table `fuel_types`

DROP TABLE IF EXISTS `fuel_types`;
CREATE TABLE `fuel_types` (
  `fuel_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `fuel_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`fuel_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `fuel_types`

INSERT INTO `fuel_types` (`fuel_type_id`, `fuel_name`, `description`, `created_at`, `updated_at`) VALUES
('1', 'Petrol 95', '', '2025-03-30 21:13:37', '2025-03-30 21:13:37'),
('2', 'Petral 92', '', '2025-03-30 21:56:38', '2025-03-30 21:56:38'),
('3', 'Auto Diesel', '', '2025-03-30 21:57:37', '2025-03-30 21:57:37'),
('4', 'Lanka Super Diesel', '', '2025-03-30 21:58:01', '2025-03-30 21:58:01'),
('5', 'Kerosene', '', '2025-03-30 22:17:49', '2025-03-30 22:17:49');

-- Table structure for table `inventory_transactions`

DROP TABLE IF EXISTS `inventory_transactions`;
CREATE TABLE `inventory_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','sale','adjustment','return','damaged') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `previous_quantity` int(11) NOT NULL,
  `change_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `conducted_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `product_id` (`product_id`),
  KEY `conducted_by` (`conducted_by`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `meter_readings`

DROP TABLE IF EXISTS `meter_readings`;
CREATE TABLE `meter_readings` (
  `reading_id` int(11) NOT NULL AUTO_INCREMENT,
  `nozzle_id` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `opening_reading` decimal(10,2) NOT NULL,
  `closing_reading` decimal(10,2) NOT NULL,
  `volume_dispensed` decimal(10,2) GENERATED ALWAYS AS (`closing_reading` - `opening_reading`) STORED,
  `recorded_by` int(11) NOT NULL,
  `verification_status` enum('pending','verified','disputed') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`reading_id`),
  KEY `nozzle_id` (`nozzle_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `meter_readings_ibfk_1` FOREIGN KEY (`nozzle_id`) REFERENCES `pump_nozzles` (`nozzle_id`),
  CONSTRAINT `meter_readings_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `meter_readings_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `meter_readings`

INSERT INTO `meter_readings` (`reading_id`, `nozzle_id`, `reading_date`, `opening_reading`, `closing_reading`, `volume_dispensed`, `recorded_by`, `verification_status`, `verified_by`, `verification_date`, `notes`, `created_at`, `updated_at`) VALUES
('1', '1', '2025-04-10', '35245.93', '36382.65', '1136.72', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('2', '2', '2025-04-10', '33703.41', '34990.06', '1286.65', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('3', '3', '2025-04-10', '362913.27', '363974.58', '1061.31', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('4', '4', '2025-04-10', '519161.14', '520421.99', '1260.85', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('5', '5', '2025-04-10', '6495497.85', '6497968.86', '2471.01', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('6', '6', '2025-04-10', '0.00', '0.00', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('7', '7', '2025-04-10', '0.00', '0.00', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('8', '8', '2025-04-10', '623422.34', '623422.34', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('9', '9', '2025-04-10', '392436.79', '394586.19', '2149.40', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('10', '10', '2025-04-10', '261638.07', '262858.26', '1220.19', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('11', '11', '2025-04-10', '903221.50', '905804.33', '2582.83', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('12', '12', '2025-04-10', '368388.83', '368388.83', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('13', '13', '2025-04-10', '0.00', '0.00', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('14', '14', '2025-04-10', '0.00', '0.00', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-10 10:47:37', '2025-04-10 10:47:37'),
('15', '1', '2025-04-09', '35245.93', '36382.65', '1136.72', '1', 'pending', NULL, NULL, 'BACKDATED: TESTING SYSTEM |', '2025-04-12 09:45:16', '2025-04-12 09:45:16'),
('16', '2', '2025-04-09', '33703.41', '34990.06', '1286.65', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 09:53:50', '2025-04-12 09:53:50'),
('17', '3', '2025-04-09', '362913.27', '363974.58', '1061.31', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 09:54:49', '2025-04-12 09:54:49'),
('18', '4', '2025-04-09', '519161.14', '520421.99', '1260.85', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 09:55:35', '2025-04-12 09:55:35'),
('19', '5', '2025-04-09', '6495497.85', '6497968.86', '2471.01', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 09:56:25', '2025-04-12 09:56:25'),
('20', '6', '2025-04-09', '3078995.34', '3078995.34', '0.00', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 10:01:55', '2025-04-12 10:01:55'),
('21', '7', '2025-04-09', '672691.88', '672691.88', '0.00', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 10:02:27', '2025-04-12 10:02:27'),
('22', '8', '2025-04-09', '623422.34', '623422.34', '0.00', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 10:03:42', '2025-04-12 10:03:42'),
('23', '9', '2025-04-09', '392436.79', '394586.20', '2149.41', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 10:04:19', '2025-04-12 10:04:19'),
('24', '10', '2025-04-09', '261638.08', '262858.27', '1220.19', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TESTT |', '2025-04-12 10:07:55', '2025-04-12 10:07:55'),
('25', '11', '2025-04-09', '903221.51', '905804.34', '2582.83', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TTESTT |', '2025-04-12 10:08:34', '2025-04-12 10:08:34'),
('26', '12', '2025-04-09', '368388.83', '368388.83', '0.00', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 10:09:04', '2025-04-12 10:09:04'),
('27', '13', '2025-04-09', '17403.86', '17403.86', '0.00', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TEST |', '2025-04-12 10:09:46', '2025-04-12 10:09:46'),
('28', '14', '2025-04-09', '0.00', '0.00', '0.00', '1', 'pending', NULL, NULL, 'BACKDATED: SYSTEM TTEST |', '2025-04-12 10:10:06', '2025-04-12 10:10:06'),
('29', '1', '2025-04-12', '39677.92', '41554.21', '1876.29', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('30', '2', '2025-04-12', '37570.12', '39416.66', '1846.54', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('31', '3', '2025-04-12', '366486.58', '367742.34', '1255.76', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('32', '4', '2025-04-12', '523311.86', '524982.75', '1670.89', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('33', '5', '2025-04-12', '6509204.13', '6509204.13', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('34', '6', '2025-04-12', '15.00', '150.00', '135.00', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('35', '7', '2025-04-12', '16.00', '160.00', '144.00', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('36', '8', '2025-04-12', '623422.34', '623422.34', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('37', '9', '2025-04-12', '397420.00', '398520.47', '1100.47', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('38', '10', '2025-04-12', '264720.36', '264950.00', '229.64', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('39', '11', '2025-04-12', '910025.84', '911535.40', '1509.56', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('40', '12', '2025-04-12', '368388.83', '368388.83', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('41', '13', '2025-04-12', '17928.34', '17928.34', '0.00', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15'),
('42', '14', '2025-04-12', '0.00', '0.02', '0.02', '1', 'pending', NULL, NULL, '', '2025-04-13 00:05:15', '2025-04-13 00:05:15');

-- Table structure for table `overtime_records`

DROP TABLE IF EXISTS `overtime_records`;
CREATE TABLE `overtime_records` (
  `overtime_id` int(11) NOT NULL AUTO_INCREMENT,
  `attendance_id` int(11) NOT NULL,
  `overtime_hours` decimal(5,2) NOT NULL,
  `overtime_rate` decimal(5,2) DEFAULT 1.50,
  `overtime_amount` decimal(10,2) GENERATED ALWAYS AS (`overtime_hours` * `overtime_rate`) STORED,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`overtime_id`),
  KEY `attendance_id` (`attendance_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `overtime_records_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance_records` (`attendance_id`),
  CONSTRAINT `overtime_records_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `overtime_records`

INSERT INTO `overtime_records` (`overtime_id`, `attendance_id`, `overtime_hours`, `overtime_rate`, `overtime_amount`, `status`, `approved_by`, `approval_date`, `notes`, `created_at`, `updated_at`) VALUES
('1', '1', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-10 08:47:09', '2025-04-10 08:48:44'),
('2', '3', '1.00', '1.50', '1.50', 'rejected', '1', '2025-04-10 02:10:10', '', '2025-04-10 11:12:59', '2025-04-10 11:40:10'),
('3', '4', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-10 11:21:41', '2025-04-10 11:21:41'),
('4', '5', '7.00', '1.50', '10.50', 'pending', NULL, NULL, NULL, '2025-04-10 11:22:25', '2025-04-10 11:24:04'),
('5', '6', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-10 11:25:35', '2025-04-10 11:25:35'),
('6', '7', '7.00', '1.50', '10.50', 'pending', NULL, NULL, NULL, '2025-04-10 11:26:13', '2025-04-10 11:26:13'),
('7', '8', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-10 11:26:46', '2025-04-10 11:26:46'),
('8', '12', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('9', '13', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('10', '16', '7.00', '1.50', '10.50', 'pending', NULL, NULL, NULL, '2025-04-12 13:51:08', '2025-04-12 13:51:08'),
('11', '22', '7.00', '1.50', '10.50', 'pending', NULL, NULL, NULL, '2025-04-12 15:26:26', '2025-04-12 15:26:58'),
('12', '23', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-12 15:27:49', '2025-04-12 15:27:49'),
('13', '24', '7.00', '1.50', '10.50', 'pending', NULL, NULL, NULL, '2025-04-12 15:28:03', '2025-04-12 15:28:03'),
('14', '26', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-12 15:28:18', '2025-04-12 15:28:18'),
('15', '29', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-12 15:28:38', '2025-04-12 15:28:38'),
('16', '30', '8.00', '1.50', '12.00', 'pending', NULL, NULL, NULL, '2025-04-12 15:29:11', '2025-04-12 15:29:11');

-- Table structure for table `payment_history`

DROP TABLE IF EXISTS `payment_history`;
CREATE TABLE `payment_history` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `po_id` (`po_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `payment_history_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `payment_history_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `payment_history`

INSERT INTO `payment_history` (`payment_id`, `po_id`, `payment_date`, `amount`, `payment_method`, `reference_number`, `notes`, `recorded_by`, `created_at`) VALUES
('1', '1', '2025-04-09', '1922292.24', 'cash', 'BO12566', '', '1', '2025-04-09 14:21:52'),
('2', '4', '2025-04-09', '1846152.00', 'cash', 'BO33418', '', '1', '2025-04-12 12:27:42'),
('3', '6', '2025-04-09', '1922292.24', 'cash', 'BO33458', '', '1', '2025-04-12 12:39:33'),
('4', '7', '2025-04-10', '1922292.24', 'cash', 'BO33493', '', '1', '2025-04-12 12:44:33'),
('5', '8', '2025-04-11', '1922292.24', 'cash', 'BO033671', '', '1', '2025-04-12 12:49:17'),
('6', '9', '2025-04-10', '1922292.24', 'cash', 'BO33493', '', '1', '2025-04-12 13:02:24'),
('7', '11', '2025-04-10', '1846152.00', 'cash', 'BO33594', '', '1', '2025-04-12 13:13:18'),
('8', '12', '2025-04-12', '1922292.24', 'cash', 'BO34387', '', '1', '2025-04-12 22:55:53'),
('9', '14', '2025-04-12', '1922292.24', 'cash', 'BO34386', '', '1', '2025-04-12 23:02:48'),
('10', '15', '2025-04-12', '1846152.00', 'cash', 'BO34347', '', '1', '2025-04-12 23:07:03'),
('11', '16', '2025-04-12', '1846152.00', 'cash', 'BO34346', '', '1', '2025-04-12 23:10:59');

-- Table structure for table `pending_topups`

DROP TABLE IF EXISTS `pending_topups`;
CREATE TABLE `pending_topups` (
  `topup_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `required_amount` decimal(12,2) NOT NULL,
  `deadline` datetime NOT NULL,
  `status` enum('pending','completed','expired') NOT NULL DEFAULT 'pending',
  `notification_sent` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`topup_id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `pending_topups_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `petroleum_account_transactions`

DROP TABLE IF EXISTS `petroleum_account_transactions`;
CREATE TABLE `petroleum_account_transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_date` datetime NOT NULL,
  `transaction_type` enum('deposit','withdrawal','adjustment') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `reference_type` enum('purchase_order','bank_transfer','refund','other') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'completed',
  `receipt_image` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `petroleum_account_transactions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `petroleum_account_transactions_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `petty_cash`

DROP TABLE IF EXISTS `petty_cash`;
CREATE TABLE `petty_cash` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `payment_method` enum('cash','card','bank_transfer') NOT NULL DEFAULT 'cash',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `receipt_image` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `petty_cash_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `petty_cash_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `petty_cash_categories` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `petty_cash`

INSERT INTO `petty_cash` (`transaction_id`, `transaction_date`, `amount`, `type`, `category_id`, `description`, `reference_no`, `payment_method`, `status`, `receipt_image`, `created_by`, `approved_by`, `created_at`, `updated_at`) VALUES
('1', '2025-04-21', '10000.00', 'income', '3', '', '10230', 'cash', 'approved', NULL, '1', '1', '2025-04-21 22:04:24', '2025-04-21 22:04:33');

-- Table structure for table `petty_cash_categories`

DROP TABLE IF EXISTS `petty_cash_categories`;
CREATE TABLE `petty_cash_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `category_name_type` (`category_name`,`type`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `petty_cash_categories`

INSERT INTO `petty_cash_categories` (`category_id`, `category_name`, `type`, `description`, `status`, `created_at`) VALUES
('1', 'Office Supplies', 'expense', 'Stationery, paper, pens, etc.', 'active', '2025-04-21 22:03:16'),
('2', 'Utilities', 'expense', 'Electricity, water, internet bills', 'active', '2025-04-21 22:03:16'),
('3', 'Maintenance', 'expense', 'Repairs and maintenance costs', 'active', '2025-04-21 22:03:16'),
('4', 'Transport', 'expense', 'Transportation and fuel reimbursements', 'active', '2025-04-21 22:03:16'),
('5', 'Miscellaneous', 'expense', 'Other small expenses', 'active', '2025-04-21 22:03:16'),
('6', 'Petty Cash Fund', 'income', 'Funds added to petty cash', 'active', '2025-04-21 22:03:16'),
('7', 'Sales Return', 'income', 'Cash from returned sales', 'active', '2025-04-21 22:03:16'),
('8', 'Other Income', 'income', 'Miscellaneous income sources', 'active', '2025-04-21 22:03:16');

-- Table structure for table `po_items`

DROP TABLE IF EXISTS `po_items`;
CREATE TABLE `po_items` (
  `po_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `line_total` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  PRIMARY KEY (`po_item_id`),
  KEY `po_id` (`po_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `po_items`

INSERT INTO `po_items` (`po_item_id`, `po_id`, `fuel_type_id`, `quantity`, `unit_price`, `line_total`) VALUES
('1', '1', '2', '6600.00', '291.26', '1922316.00'),
('2', '2', '2', '6600.00', '291.26', '1922316.00'),
('3', '3', '2', '6600.00', '291.26', '1922316.00'),
('4', '3', '3', '6600.00', '279.72', '1846152.00'),
('5', '4', '3', '6600.00', '279.72', '1846152.00'),
('6', '5', '2', '6600.00', '291.26', '1922316.00'),
('7', '6', '2', '6600.00', '291.26', '1922316.00'),
('8', '7', '2', '6600.00', '291.26', '1922316.00'),
('10', '9', '2', '6600.00', '291.26', '1922316.00'),
('11', '10', '2', '6600.00', '291.26', '1922316.00'),
('12', '11', '3', '6600.00', '279.72', '1846152.00'),
('13', '12', '2', '6600.00', '291.26', '1922316.00'),
('14', '13', '2', '6600.00', '291.26', '1922316.00'),
('15', '14', '2', '6600.00', '291.26', '1922316.00'),
('16', '15', '3', '6600.00', '279.72', '1846152.00'),
('17', '16', '3', '6600.00', '279.72', '1846152.00'),
('18', '17', '1', '60000.00', '150.00', '9000000.00');

-- Table structure for table `price_change_impact`

DROP TABLE IF EXISTS `price_change_impact`;
CREATE TABLE `price_change_impact` (
  `impact_id` int(11) NOT NULL AUTO_INCREMENT,
  `price_id` int(11) NOT NULL,
  `tank_id` int(11) NOT NULL,
  `old_price` decimal(10,2) NOT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `stock_volume` decimal(10,2) NOT NULL,
  `value_change` decimal(10,2) GENERATED ALWAYS AS ((`new_price` - `old_price`) * `stock_volume`) STORED,
  `calculated_by` int(11) NOT NULL,
  `calculation_date` datetime NOT NULL,
  PRIMARY KEY (`impact_id`),
  KEY `price_id` (`price_id`),
  KEY `tank_id` (`tank_id`),
  KEY `calculated_by` (`calculated_by`),
  CONSTRAINT `price_change_impact_ibfk_1` FOREIGN KEY (`price_id`) REFERENCES `fuel_prices` (`price_id`),
  CONSTRAINT `price_change_impact_ibfk_2` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`),
  CONSTRAINT `price_change_impact_ibfk_3` FOREIGN KEY (`calculated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `price_change_impact`

INSERT INTO `price_change_impact` (`impact_id`, `price_id`, `tank_id`, `old_price`, `new_price`, `stock_volume`, `value_change`, `calculated_by`, `calculation_date`) VALUES
('1', '1', '3', '0.00', '286.00', '6749.00', '1930214.00', '1', '2025-04-10 11:46:29'),
('2', '1', '4', '0.00', '286.00', '4195.00', '1199770.00', '1', '2025-04-10 11:46:29'),
('3', '2', '1', '0.00', '299.00', '4879.00', '1458821.00', '1', '2025-04-10 11:49:09'),
('4', '2', '2', '0.00', '299.00', '15411.00', '4607889.00', '1', '2025-04-10 11:49:09'),
('5', '2', '5', '0.00', '299.00', '0.00', '0.00', '1', '2025-04-10 11:49:09');

-- Table structure for table `product_categories`

DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `products`

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_code` varchar(20) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `profit_margin` decimal(10,2) GENERATED ALWAYS AS (`selling_price` - `purchase_price`) STORED,
  `profit_percentage` decimal(5,2) GENERATED ALWAYS AS ((`selling_price` - `purchase_price`) / `purchase_price` * 100) STORED,
  `current_stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 0,
  `barcode` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','out_of_stock') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `pump_nozzles`

DROP TABLE IF EXISTS `pump_nozzles`;
CREATE TABLE `pump_nozzles` (
  `nozzle_id` int(11) NOT NULL AUTO_INCREMENT,
  `pump_id` int(11) NOT NULL,
  `nozzle_number` int(11) NOT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  PRIMARY KEY (`nozzle_id`),
  KEY `pump_id` (`pump_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  CONSTRAINT `pump_nozzles_ibfk_1` FOREIGN KEY (`pump_id`) REFERENCES `pumps` (`pump_id`),
  CONSTRAINT `pump_nozzles_ibfk_2` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `pump_nozzles`

INSERT INTO `pump_nozzles` (`nozzle_id`, `pump_id`, `nozzle_number`, `fuel_type_id`, `status`) VALUES
('1', '1', '1', '2', 'active'),
('2', '2', '2', '2', 'active'),
('3', '3', '3', '2', 'active'),
('4', '4', '4', '2', 'active'),
('5', '5', '5', '2', 'active'),
('6', '6', '6', '2', 'active'),
('7', '7', '7', '2', 'active'),
('8', '8', '8', '2', 'active'),
('9', '9', '1', '3', 'active'),
('10', '10', '2', '3', 'active'),
('11', '11', '3', '3', 'active'),
('12', '12', '4', '3', 'active'),
('13', '13', '5', '3', 'active'),
('14', '14', '1', '5', 'active');

-- Table structure for table `pumps`

DROP TABLE IF EXISTS `pumps`;
CREATE TABLE `pumps` (
  `pump_id` int(11) NOT NULL AUTO_INCREMENT,
  `pump_name` varchar(50) NOT NULL,
  `tank_id` int(11) NOT NULL,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `model` varchar(50) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pump_id`),
  KEY `tank_id` (`tank_id`),
  CONSTRAINT `pumps_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `pumps`

INSERT INTO `pumps` (`pump_id`, `pump_name`, `tank_id`, `status`, `model`, `installation_date`, `last_maintenance_date`, `notes`, `created_at`, `updated_at`) VALUES
('1', 'P1', '1', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:19:20', '2025-04-09 12:19:20'),
('2', 'P2', '1', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:20:08', '2025-04-09 12:20:08'),
('3', 'P3', '2', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:20:33', '2025-04-09 12:20:33'),
('4', 'P4', '2', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:20:57', '2025-04-09 12:20:57'),
('5', 'P5', '2', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:21:18', '2025-04-09 12:21:18'),
('6', 'P6', '5', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:21:42', '2025-04-10 09:58:39'),
('7', 'P7', '5', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:24:12', '2025-04-09 12:24:12'),
('8', 'P8', '2', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:24:38', '2025-04-10 10:01:07'),
('9', 'D1', '3', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:25:29', '2025-04-09 12:25:29'),
('10', 'D2', '4', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:25:52', '2025-04-09 12:25:52'),
('11', 'D3', '3', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:28:43', '2025-04-09 12:28:43'),
('12', 'D4', '4', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:29:04', '2025-04-09 12:29:04'),
('13', 'D5', '4', 'active', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:29:29', '2025-04-09 12:29:29'),
('14', 'K1', '7', 'inactive', 'HONGYANG', '2025-04-09', NULL, '', '2025-04-09 12:29:54', '2025-04-09 13:03:23');

-- Table structure for table `purchase_orders`

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `po_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(20) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` enum('draft','submitted','approved','in_progress','delivered','cancelled') DEFAULT 'draft',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid') DEFAULT 'pending',
  `account_check_status` enum('sufficient','insufficient','pending') DEFAULT NULL,
  `topup_deadline` datetime DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_reference` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `purchase_orders`

INSERT INTO `purchase_orders` (`po_id`, `po_number`, `supplier_id`, `order_date`, `expected_delivery_date`, `status`, `total_amount`, `payment_status`, `account_check_status`, `topup_deadline`, `payment_date`, `payment_reference`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
('1', 'PO-20250409-001', '2', '2025-04-08', '2025-04-09', 'delivered', '1922316.00', 'paid', NULL, NULL, '2025-04-09', 'BO12566', '', '1', '2025-04-09 14:14:41', '2025-04-09 14:32:19'),
('2', 'PO-20250412-001', '2', '2025-04-09', '2025-04-10', 'cancelled', '1922292.24', 'pending', NULL, NULL, NULL, NULL, '', '1', '2025-04-12 12:19:10', '2025-04-12 12:25:06'),
('3', 'PO-20250412-002', '2', '2025-04-09', '2025-04-10', 'cancelled', '3768444.24', 'pending', NULL, NULL, NULL, NULL, '', '1', '2025-04-12 12:20:25', '2025-04-12 12:24:51'),
('4', 'PO-20250412-003', '2', '2025-04-10', '2025-04-10', 'delivered', '1846152.00', 'paid', NULL, NULL, '2025-04-09', 'BO33418', '', '1', '2025-04-12 12:26:20', '2025-04-12 12:35:20'),
('5', 'PO-20250412-004', '2', '2025-04-10', '2025-04-10', 'cancelled', '1922292.24', 'pending', NULL, NULL, NULL, NULL, '', '1', '2025-04-12 12:37:16', '2025-04-12 12:38:38'),
('6', 'PO-20250412-005', '2', '2025-04-10', '2025-04-10', 'cancelled', '1922316.00', 'paid', NULL, NULL, '2025-04-09', 'BO33458', '', '1', '2025-04-12 12:37:40', '2025-04-12 12:41:44'),
('7', 'PO-20250412-006', '2', '2025-04-09', '2025-04-10', 'delivered', '1922316.00', 'paid', NULL, NULL, '2025-04-10', 'BO33493', '', '1', '2025-04-12 12:42:46', '2025-04-12 12:46:39'),
('8', 'PO-20250412-007', '2', '2025-04-10', '2025-04-11', 'cancelled', '1922250.00', 'paid', NULL, NULL, '2025-04-11', 'BO033671', '', '1', '2025-04-12 12:48:11', '2025-04-12 12:58:36'),
('9', 'PO-20250412-008', '2', '2025-04-10', '2025-04-11', 'delivered', '1922316.00', 'paid', NULL, NULL, '2025-04-10', 'BO33493', '', '1', '2025-04-12 12:54:36', '2025-04-12 13:05:13'),
('10', 'PO-20250412-009', '2', '2025-04-10', '2025-04-11', 'cancelled', '1922292.24', 'pending', NULL, NULL, NULL, NULL, '', '1', '2025-04-12 12:54:55', '2025-04-12 12:56:02'),
('11', 'PO-20250412-010', '2', '2025-04-10', '2025-04-11', 'delivered', '1846152.00', 'paid', NULL, NULL, '2025-04-10', 'BO33594', '', '1', '2025-04-12 13:08:50', '2025-04-12 13:15:04'),
('12', 'PO-20250412-011', '2', '2025-04-11', '2025-04-12', 'delivered', '1922316.00', 'paid', NULL, NULL, '2025-04-12', 'BO34387', '', '1', '2025-04-12 22:52:55', '2025-04-12 22:59:43'),
('13', 'PO-20250412-012', '2', '2025-04-11', '2025-04-12', 'cancelled', '1922292.24', 'pending', NULL, NULL, NULL, NULL, '', '1', '2025-04-12 22:53:26', '2025-04-12 22:54:34'),
('14', 'PO-20250412-013', '2', '2025-04-11', '2025-04-12', 'delivered', '1922316.00', 'paid', NULL, NULL, '2025-04-12', 'BO34386', '', '1', '2025-04-12 23:01:18', '2025-04-12 23:03:37'),
('15', 'PO-20250412-014', '2', '2025-04-11', '2025-04-12', 'delivered', '1846152.00', 'paid', NULL, NULL, '2025-04-12', 'BO34347', '', '1', '2025-04-12 23:05:51', '2025-04-12 23:08:43'),
('16', 'PO-20250412-015', '2', '2025-04-11', '2025-04-12', 'delivered', '1846152.00', 'paid', NULL, NULL, '2025-04-12', 'BO34346', '', '1', '2025-04-12 23:09:43', '2025-04-12 23:12:12'),
('17', 'PO-20250421-001', '2', '2025-04-21', '2025-04-24', 'draft', '9000000.00', 'pending', NULL, NULL, NULL, NULL, '', '1', '2025-04-21 21:59:01', '2025-04-21 21:59:01');

-- Table structure for table `sale_items`

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `item_type` enum('fuel','product') NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `nozzle_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price` - `discount_amount`) STORED,
  PRIMARY KEY (`item_id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  KEY `nozzle_id` (`nozzle_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`),
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  CONSTRAINT `sale_items_ibfk_3` FOREIGN KEY (`nozzle_id`) REFERENCES `pump_nozzles` (`nozzle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `sales`

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(20) NOT NULL,
  `sale_date` datetime NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `sale_type` enum('fuel','product','mixed') NOT NULL,
  `staff_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','mobile_payment','credit','other') DEFAULT 'cash',
  `payment_status` enum('paid','pending','partial','cancelled') DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sale_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `staff`

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `staff_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `hire_date` date NOT NULL,
  `position` varchar(50) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive','on_leave','terminated') DEFAULT 'active',
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `staff_code` (`staff_code`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `staff`

INSERT INTO `staff` (`staff_id`, `user_id`, `staff_code`, `first_name`, `last_name`, `gender`, `date_of_birth`, `hire_date`, `position`, `department`, `phone`, `email`, `address`, `status`, `emergency_contact_name`, `emergency_contact_phone`, `notes`, `created_at`, `updated_at`) VALUES
('1', NULL, 'STF0001', 'PRASANNA	', 'K.V.A', 'male', NULL, '2025-04-08', 'SUPERVISOR		', 'MANGEMENT', '0777169647', 'amiprasanna5@gmail.com', 'NO.285,KANANWILA,HORANA.', 'active', NULL, NULL, '941634010V', '2025-04-08 08:45:14', '2025-04-08 08:45:14'),
('2', NULL, 'STF0002', 'MADUSHAN	', 'S.M.C', 'male', NULL, '2025-04-08', 'SUPERVISOR		', 'MANAGEMENT', '0703482240', NULL, 'HUMBAHAMADA,KENDAGOLLA,BADULLA.', 'active', NULL, NULL, '983531393V', '2025-04-08 08:49:02', '2025-04-08 08:49:02'),
('3', NULL, 'STF0003', 'KUMARA	', 'I.D.T.T', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR', 'SALE', '0723833363', NULL, 'NO.369/1,MADAKADAKANDA,KANANWILA,HORANA.', 'active', NULL, NULL, '723121078V', '2025-04-08 08:52:08', '2025-04-08 08:52:08'),
('4', NULL, 'STF0004', 'Mr.WEERASIRI	', 'L', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR', 'SALE', '0771699471', NULL, NULL, 'active', NULL, NULL, NULL, '2025-04-08 08:55:14', '2025-04-08 08:55:14'),
('5', NULL, 'STF0005', 'CHANDIMAL		', 'P.G.K', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR		', 'SALE', '0760404673', NULL, 'NO.232/3,PRATHIRAJA MAWATHA,WELIGAMPITIYA,POKUNUWITA.', 'active', NULL, NULL, '771291520V', '2025-04-08 08:57:54', '2025-04-08 08:57:54'),
('6', NULL, 'STF0006', 'PERERA', 'M.T', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR		', 'SALE', '0721403293', NULL, 'NO.5H,ARALIYAGAS JUNCTION,WEEDAGAMA,BANDARAGAMA.', 'active', NULL, NULL, '622831392V', '2025-04-08 09:00:17', '2025-04-08 09:00:17'),
('7', NULL, 'STF0007', 'PERERA', 'M.M.S', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR		', 'SALE', '0763807061', NULL, 'NO.99/2,UYANA ROAD,KOTALAWALA,BANDARAGAMA.', 'active', NULL, NULL, '911752980V', '2025-04-08 09:03:01', '2025-04-08 09:03:01'),
('8', NULL, 'STF0008', 'PUSHPAKUMARA', 'M.D.M.A.D', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR', 'SALE', '0770108636', NULL, 'NO.318/A,BODHIRUKKARAMA ROAD,PAMUNUGAMA,ALUBOMULLA.', 'active', NULL, NULL, '200510900786', '2025-04-08 09:09:36', '2025-04-08 09:09:36'),
('9', NULL, 'STF0009', 'DILSHAN 	', 'Y.B.S', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR		', 'SALE', '0710959725', NULL, NULL, 'active', NULL, NULL, NULL, '2025-04-08 09:12:55', '2025-04-08 09:12:55'),
('10', NULL, 'STF0010', 'DUSHAN MADUSHANKA', 'H.L', 'male', NULL, '2025-04-08', 'MACHINE OPERATOR		', 'SALE', '07765374188', NULL, 'NO.34,AKKARA 20,DOMBAGAMMANA,DELA.', 'active', NULL, NULL, '200324011560', '2025-04-08 09:32:06', '2025-04-08 09:32:06');

-- Table structure for table `staff_assignments`

DROP TABLE IF EXISTS `staff_assignments`;
CREATE TABLE `staff_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `pump_id` int(11) NOT NULL,
  `assignment_date` date NOT NULL,
  `shift` enum('morning','afternoon','evening','night') NOT NULL,
  `status` enum('assigned','completed','absent','reassigned') DEFAULT 'assigned',
  `assigned_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`assignment_id`),
  KEY `staff_id` (`staff_id`),
  KEY `pump_id` (`pump_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `staff_assignments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `staff_assignments_ibfk_2` FOREIGN KEY (`pump_id`) REFERENCES `pumps` (`pump_id`),
  CONSTRAINT `staff_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `staff_assignments`

INSERT INTO `staff_assignments` (`assignment_id`, `staff_id`, `pump_id`, `assignment_date`, `shift`, `status`, `assigned_by`, `notes`, `created_at`, `updated_at`) VALUES
('1', '3', '9', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('2', '3', '10', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('3', '4', '11', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('4', '4', '12', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('5', '7', '13', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('6', '8', '1', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('7', '8', '2', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('8', '9', '3', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('9', '9', '4', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('10', '10', '5', '2025-04-09', 'morning', 'assigned', '1', '', '2025-04-09 12:40:22', '2025-04-09 12:40:22'),
('11', '9', '9', '2025-04-11', 'morning', 'assigned', '1', '', '2025-04-11 19:20:44', '2025-04-11 19:20:44'),
('12', '9', '9', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('13', '9', '10', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('14', '6', '11', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('15', '6', '12', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('16', '6', '13', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('17', '4', '1', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('18', '4', '2', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('19', '10', '3', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('20', '10', '4', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('21', '5', '5', '2025-04-10', 'morning', 'assigned', '1', '', '2025-04-12 13:57:37', '2025-04-12 13:57:37'),
('22', '10', '9', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('23', '10', '10', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('24', '3', '11', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('25', '3', '12', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('26', '9', '1', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('27', '9', '2', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('28', '8', '3', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('29', '8', '4', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('30', '4', '5', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39'),
('31', '4', '8', '2025-04-12', 'morning', 'assigned', '1', '', '2025-04-12 15:32:39', '2025-04-12 15:32:39');

-- Table structure for table `staff_performance`

DROP TABLE IF EXISTS `staff_performance`;
CREATE TABLE `staff_performance` (
  `performance_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `sales_amount` decimal(10,2) DEFAULT 0.00,
  `customers_served` int(11) DEFAULT 0,
  `rating` int(11) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `evaluated_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`performance_id`),
  KEY `staff_id` (`staff_id`),
  KEY `evaluated_by` (`evaluated_by`),
  CONSTRAINT `staff_performance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `staff_performance_ibfk_2` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `suppliers`

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `suppliers`

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `status`, `created_at`, `updated_at`) VALUES
('2', 'CEYLON PETROLIUM CORPERATION', 'Mr.PRASANNA(AREA MANAGER)', '0714130666', 'secretariat@ceypetco.gov.lk', 'No.609, Dr. Danister de Silva Mawatha, Colombo 09.', 'active', '2025-04-07 14:51:58', '2025-04-07 14:51:58');

-- Table structure for table `system_settings`

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `system_settings`

INSERT INTO `system_settings` (`setting_id`, `setting_name`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
('1', 'company_name', 'Koralaima Filling Station ', 'Company name displayed on reports', '2025-03-28 04:34:13', '2025-03-31 18:22:44'),
('2', 'company_address', 'No 40, Colombo Road ,Koralaima ,Gonapala', 'Company address displayed on reports', '2025-03-28 04:34:13', '2025-03-31 18:22:44'),
('3', 'company_phone', '0342257210', 'Company phone displayed on reports', '2025-03-28 04:34:13', '2025-03-31 18:22:44'),
('4', 'company_email', 'rajithashedkoralaima@gmail.com', 'Company email displayed on reports', '2025-03-28 04:34:13', '2025-03-31 18:22:44'),
('5', 'receipt_footer', 'Thank you for your business!', 'Message displayed at the bottom of receipts', '2025-03-28 04:34:13', '2025-03-28 04:34:13'),
('6', 'currency_symbol', 'Rs.', 'Currency symbol used in the system', '2025-03-28 04:34:13', '2025-03-31 15:56:56'),
('7', 'allow_negative_stock', 'false', 'Allow products to have negative stock', '2025-03-28 04:34:13', '2025-03-28 04:34:13'),
('8', 'shortage_allowance_percentage', '100', 'Allowable percentage for cash shortages', '2025-03-28 04:34:13', '2025-04-03 07:15:07'),
('9', 'vat_percentage', '18', 'VAT percentage applied to sales', '2025-03-28 04:34:13', '2025-03-31 12:57:12'),
('10', 'min_account_balance', '0', 'Minimum balance to maintain in petroleum account', '2025-04-21 22:09:40', '2025-04-21 22:09:40');

-- Table structure for table `tank_dip_measurements`

DROP TABLE IF EXISTS `tank_dip_measurements`;
CREATE TABLE `tank_dip_measurements` (
  `dip_id` int(11) NOT NULL AUTO_INCREMENT,
  `tank_id` int(11) NOT NULL,
  `measurement_date` date NOT NULL,
  `dip_value` decimal(10,2) NOT NULL COMMENT 'Dip measurement in centimeters',
  `calculated_volume` decimal(10,2) NOT NULL COMMENT 'Calculated volume in liters',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`dip_id`),
  UNIQUE KEY `tank_date_unique` (`tank_id`,`measurement_date`),
  KEY `fk_tank_dip_tank_id` (`tank_id`),
  KEY `fk_tank_dip_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_tank_dip_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tank_dip_tank_id` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `tank_dip_measurements`

INSERT INTO `tank_dip_measurements` (`dip_id`, `tank_id`, `measurement_date`, `dip_value`, `calculated_volume`, `notes`, `recorded_by`, `created_at`, `updated_at`) VALUES
('1', '1', '2025-04-09', '1579.00', '157900.00', '', '1', '2025-04-09 12:42:02', '2025-04-09 12:42:02'),
('2', '2', '2025-04-09', '12111.00', '1211100.00', '', '1', '2025-04-09 12:42:28', '2025-04-09 12:42:28'),
('3', '3', '2025-04-09', '6749.00', '674900.00', '', '1', '2025-04-09 12:42:50', '2025-04-09 12:42:50'),
('4', '4', '2025-04-09', '4195.00', '419500.00', '', '1', '2025-04-09 12:43:14', '2025-04-09 12:43:14');

-- Table structure for table `tank_inventory`

DROP TABLE IF EXISTS `tank_inventory`;
CREATE TABLE `tank_inventory` (
  `inventory_id` int(11) NOT NULL AUTO_INCREMENT,
  `tank_id` int(11) NOT NULL,
  `operation_type` enum('delivery','sales','adjustment','leak','transfer','initial') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `previous_volume` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `new_volume` decimal(10,2) NOT NULL,
  `operation_date` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`inventory_id`),
  KEY `tank_id` (`tank_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `tank_inventory_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`),
  CONSTRAINT `tank_inventory_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `tank_inventory`

INSERT INTO `tank_inventory` (`inventory_id`, `tank_id`, `operation_type`, `reference_id`, `previous_volume`, `change_amount`, `new_volume`, `operation_date`, `notes`, `recorded_by`, `created_at`) VALUES
('2', '2', 'initial', NULL, '0.00', '12111.00', '12111.00', '2025-04-09 11:59:34', 'Initial tank setup with volume of 12111 liters', '1', '2025-04-09 11:59:34'),
('3', '3', 'initial', NULL, '0.00', '6749.00', '6749.00', '2025-04-09 12:03:37', 'Initial tank setup with volume of 6749 liters', '1', '2025-04-09 12:03:37'),
('4', '4', 'initial', NULL, '0.00', '4195.00', '4195.00', '2025-04-09 12:04:53', 'Initial tank setup with volume of 4195 liters', '1', '2025-04-09 12:04:53'),
('5', '1', 'delivery', '1', '1579.00', '3300.00', '4879.00', '2025-04-09 00:00:00', '', '1', '2025-04-09 14:32:19'),
('6', '2', 'delivery', '1', '12111.00', '3300.00', '15411.00', '2025-04-09 00:00:00', '', '1', '2025-04-09 14:32:19'),
('7', '3', 'delivery', '2', '6749.00', '6600.00', '13349.00', '2025-04-10 00:00:00', '', '1', '2025-04-12 12:35:20'),
('8', '1', 'delivery', '3', '4879.00', '3300.00', '8179.00', '2025-04-10 00:00:00', '', '1', '2025-04-12 12:46:39'),
('9', '2', 'delivery', '3', '15411.00', '3300.00', '18711.00', '2025-04-10 00:00:00', '', '1', '2025-04-12 12:46:39'),
('10', '1', 'delivery', '4', '8179.00', '6600.00', '14779.00', '2025-04-11 00:00:00', '', '1', '2025-04-12 13:05:13'),
('11', '3', 'delivery', '5', '13349.00', '6600.00', '19949.00', '2025-04-11 00:00:00', '', '1', '2025-04-12 13:15:04'),
('16', '1', 'delivery', '6', '3114.00', '3300.00', '6414.00', '2025-04-12 00:00:00', '', '1', '2025-04-12 22:59:43'),
('17', '2', 'delivery', '6', '3673.00', '3300.00', '6973.00', '2025-04-12 00:00:00', '', '1', '2025-04-12 22:59:43'),
('18', '2', 'delivery', '7', '6973.00', '6600.00', '13573.00', '2025-04-12 00:00:00', '', '1', '2025-04-12 23:03:37'),
('19', '4', 'delivery', '8', '620.00', '6600.00', '7220.00', '2025-04-12 00:00:00', '', '1', '2025-04-12 23:08:43'),
('20', '3', 'delivery', '9', '8202.00', '6600.00', '14802.00', '2025-04-12 00:00:00', '', '1', '2025-04-12 23:12:12');

-- Table structure for table `tanks`

DROP TABLE IF EXISTS `tanks`;
CREATE TABLE `tanks` (
  `tank_id` int(11) NOT NULL AUTO_INCREMENT,
  `tank_name` varchar(50) NOT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `capacity` decimal(10,2) NOT NULL,
  `current_volume` decimal(10,2) DEFAULT 0.00,
  `low_level_threshold` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tank_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  CONSTRAINT `tanks_ibfk_1` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `tanks`

INSERT INTO `tanks` (`tank_id`, `tank_name`, `fuel_type_id`, `capacity`, `current_volume`, `low_level_threshold`, `status`, `location`, `notes`, `created_at`, `updated_at`) VALUES
('1', 'TANK-01(92 OCTANE 3000-01)', '2', '14233.00', '6414.00', '1000.00', 'active', 'KORALAIMA', '', '2025-04-09 11:42:53', '2025-04-12 22:59:43'),
('2', 'TANK-02(92 OCTANE 5000)', '2', '24221.00', '13573.00', '3000.00', 'active', 'KORALAIMA', '', '2025-04-09 11:59:34', '2025-04-12 23:03:37'),
('3', 'TANK-03(AUTO DIESEL 3000)', '3', '14819.00', '14802.00', '3000.00', 'active', 'KORALAIMA', '', '2025-04-09 12:03:37', '2025-04-12 23:12:12'),
('4', 'TANK-04(AUTO DIESEL 5000)', '3', '24536.00', '7220.00', '3000.00', 'active', 'KORALAIMA', '', '2025-04-09 12:04:53', '2025-04-12 23:08:43'),
('5', 'TANK-05(92 OCTANE 3000-02)', '2', '14669.00', '0.00', '3000.00', 'active', 'KORALAIMA', '', '2025-04-09 12:08:07', '2025-04-09 12:17:36'),
('6', 'TANK-06(SUPER DISEL 3000)', '4', '14233.00', '0.00', '3000.00', 'active', 'KORALAIMA', '', '2025-04-09 12:09:53', '2025-04-09 12:09:53'),
('7', 'TANK-07(KEROSENE 2000)', '5', '9672.00', '0.00', '3000.00', 'active', 'KORALAIMA', '', '2025-04-09 12:10:46', '2025-04-09 12:10:46');

-- Table structure for table `users`

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','manager','cashier','attendant') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `created_at`, `updated_at`) VALUES
('1', 'admin', '$2y$10$/omvBTiuKWrunzfBiJ39mOmeDysr3mOQP1EaevrWltVsX0sRjGYY6', 'System Administrator', 'admin@petrostation.com', 'admin', 'active', '2025-03-28 00:34:13', '2025-03-28 00:36:42'),
('2', 'manager', '$2y$10$BtEgelJiYbD54CaUhSBDHuS0Mo3IiuBrDMD76bezCnZjZ.0LHs4M.', 'The Manager', 'manager@gmail.com', 'cashier', 'active', '2025-03-30 01:23:27', '2025-03-30 02:24:15');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
