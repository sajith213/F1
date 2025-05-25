-- Petrol Pump Management System Database Backup
-- Generated on: 2025-04-17 22:43:02
-- Database: demo_fuel

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Table structure for table `attendance`

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','half_day','leave') NOT NULL DEFAULT 'present',
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  KEY `staff_id` (`staff_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `attendance_records`

DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','half_day','leave') NOT NULL DEFAULT 'present',
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attendance_id`),
  KEY `staff_id` (`staff_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `attendance_records_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `backup_history`

INSERT INTO `backup_history` (`backup_id`, `filename`, `created_by`, `file_size`, `is_restore`, `created_at`) VALUES
('1', 'backup_2025-04-17_22-31-43.sql', '1', '36263', '0', '2025-04-17 22:31:43');

-- Table structure for table `daily_cash_records`

DROP TABLE IF EXISTS `daily_cash_records`;
CREATE TABLE `daily_cash_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `pump_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `shift` enum('morning','evening','night') DEFAULT NULL,
  `record_date` date NOT NULL,
  `opening_meter` decimal(10,2) DEFAULT 0.00,
  `closing_meter` decimal(10,2) DEFAULT 0.00,
  `expected_amount` decimal(10,2) DEFAULT 0.00,
  `collected_amount` decimal(10,2) DEFAULT 0.00,
  `difference` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','verified','disputed','settled') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`record_id`),
  KEY `staff_id` (`staff_id`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `daily_cash_records_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL,
  CONSTRAINT `daily_cash_records_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `fuel_deliveries`

DROP TABLE IF EXISTS `fuel_deliveries`;
CREATE TABLE `fuel_deliveries` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `tank_id` int(11) NOT NULL,
  `delivery_date` date NOT NULL,
  `delivered_quantity` decimal(10,2) NOT NULL,
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','partial','complete') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`delivery_id`),
  KEY `po_id` (`po_id`),
  KEY `tank_id` (`tank_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `fuel_deliveries_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`),
  CONSTRAINT `fuel_deliveries_ibfk_2` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`),
  CONSTRAINT `fuel_deliveries_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `fuel_price_change_impact`

DROP TABLE IF EXISTS `fuel_price_change_impact`;
CREATE TABLE `fuel_price_change_impact` (
  `impact_id` int(11) NOT NULL AUTO_INCREMENT,
  `fuel_type_id` int(11) NOT NULL,
  `tank_id` int(11) NOT NULL,
  `old_price` decimal(10,2) NOT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `volume` decimal(10,2) NOT NULL,
  `value_change` decimal(12,2) NOT NULL,
  `calculation_date` date NOT NULL,
  `price_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`impact_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  KEY `tank_id` (`tank_id`),
  KEY `price_id` (`price_id`),
  CONSTRAINT `fuel_price_change_impact_ibfk_1` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`),
  CONSTRAINT `fuel_price_change_impact_ibfk_2` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`),
  CONSTRAINT `fuel_price_change_impact_ibfk_3` FOREIGN KEY (`price_id`) REFERENCES `fuel_prices` (`price_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `fuel_prices`

DROP TABLE IF EXISTS `fuel_prices`;
CREATE TABLE `fuel_prices` (
  `price_id` int(11) NOT NULL AUTO_INCREMENT,
  `fuel_type_id` int(11) NOT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `status` enum('active','planned','expired') NOT NULL DEFAULT 'active',
  `end_date` date DEFAULT NULL,
  `set_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`price_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  KEY `set_by` (`set_by`),
  CONSTRAINT `fuel_prices_ibfk_1` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`),
  CONSTRAINT `fuel_prices_ibfk_2` FOREIGN KEY (`set_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `fuel_types`

DROP TABLE IF EXISTS `fuel_types`;
CREATE TABLE `fuel_types` (
  `fuel_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `fuel_name` varchar(50) NOT NULL,
  `fuel_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`fuel_type_id`),
  UNIQUE KEY `fuel_name` (`fuel_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `fuel_types`

INSERT INTO `fuel_types` (`fuel_type_id`, `fuel_name`, `fuel_code`, `description`, `created_at`) VALUES
('1', 'Petrol 92', 'P92', 'Regular unleaded petrol 92 octane', '2025-04-13 08:50:44'),
('2', 'Petrol 95', 'P95', 'Premium unleaded petrol 95 octane', '2025-04-13 08:50:44'),
('3', 'Diesel', 'DSL', 'Regular diesel fuel', '2025-04-13 08:50:44'),
('4', 'Super Diesel', 'SDSL', 'Premium diesel fuel with additives', '2025-04-13 08:50:44');

-- Table structure for table `login_logs`

DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `login_time` datetime DEFAULT NULL,
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `status` enum('success','failed','logout') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `login_logs`

INSERT INTO `login_logs` (`log_id`, `user_id`, `login_time`, `logout_time`, `ip_address`, `status`, `created_at`) VALUES
('2', '1', '2025-04-13 08:53:50', NULL, '::1', 'failed', '2025-04-13 08:53:50'),
('3', '1', '2025-04-13 08:54:28', NULL, '::1', 'failed', '2025-04-13 08:54:28'),
('4', '1', '2025-04-13 08:54:36', NULL, '::1', 'failed', '2025-04-13 08:54:36'),
('5', '1', '2025-04-13 08:55:06', NULL, '::1', 'failed', '2025-04-13 08:55:06'),
('6', '1', '2025-04-13 08:55:11', NULL, '::1', 'failed', '2025-04-13 08:55:11'),
('7', '1', '2025-04-13 08:58:43', NULL, '::1', 'failed', '2025-04-13 08:58:43'),
('8', '1', '2025-04-13 08:58:45', NULL, '::1', 'failed', '2025-04-13 08:58:45'),
('10', '1', '2025-04-13 08:59:08', NULL, '::1', 'failed', '2025-04-13 08:59:08'),
('11', '1', '2025-04-13 09:01:30', NULL, '::1', 'failed', '2025-04-13 09:01:30'),
('12', '1', '2025-04-13 09:01:35', NULL, '::1', 'failed', '2025-04-13 09:01:35'),
('13', '1', '2025-04-13 09:02:18', NULL, '::1', 'failed', '2025-04-13 09:02:18'),
('14', '1', '2025-04-13 09:06:46', NULL, '::1', 'failed', '2025-04-13 09:06:46'),
('15', '1', '2025-04-13 09:06:50', NULL, '::1', 'failed', '2025-04-13 09:06:50'),
('16', '1', '2025-04-13 09:06:51', NULL, '::1', 'failed', '2025-04-13 09:06:51'),
('17', '1', '2025-04-13 09:06:51', NULL, '::1', 'failed', '2025-04-13 09:06:51'),
('18', '1', '2025-04-13 09:06:52', NULL, '::1', 'failed', '2025-04-13 09:06:52'),
('19', '1', '2025-04-13 09:19:54', NULL, '::1', 'failed', '2025-04-13 09:19:54'),
('20', '1', '2025-04-13 09:20:07', NULL, '::1', 'failed', '2025-04-13 09:20:07'),
('21', '1', '2025-04-13 09:20:46', NULL, '::1', 'failed', '2025-04-13 09:20:46'),
('22', '1', '2025-04-13 09:20:55', NULL, '::1', 'failed', '2025-04-13 09:20:55'),
('23', '1', '2025-04-13 09:25:57', NULL, '::1', 'failed', '2025-04-13 09:25:57'),
('24', '1', '2025-04-13 09:26:06', NULL, '::1', 'failed', '2025-04-13 09:26:06'),
('25', '1', '2025-04-13 09:27:44', NULL, '::1', 'failed', '2025-04-13 09:27:44'),
('26', '1', '2025-04-13 09:27:46', NULL, '::1', 'failed', '2025-04-13 09:27:46'),
('27', '1', '2025-04-13 09:38:21', NULL, '::1', 'failed', '2025-04-13 09:38:21'),
('28', '1', '2025-04-13 09:40:22', NULL, '::1', 'failed', '2025-04-13 09:40:22'),
('29', '1', '2025-04-13 09:40:23', NULL, '::1', 'failed', '2025-04-13 09:40:23'),
('30', '1', '2025-04-13 09:44:47', NULL, '::1', 'failed', '2025-04-13 09:44:47'),
('31', '1', '2025-04-13 09:44:52', NULL, '::1', 'failed', '2025-04-13 09:44:52'),
('32', '1', '2025-04-13 09:44:54', NULL, '::1', 'failed', '2025-04-13 09:44:54'),
('33', '1', '2025-04-13 09:47:20', NULL, '::1', 'failed', '2025-04-13 09:47:20'),
('34', '1', '2025-04-13 09:48:13', NULL, '::1', 'failed', '2025-04-13 09:48:13'),
('35', '1', '2025-04-13 09:48:18', NULL, '::1', 'failed', '2025-04-13 09:48:18'),
('36', '1', '2025-04-13 09:52:55', NULL, '::1', 'success', '2025-04-13 09:52:55'),
('37', '1', NULL, '2025-04-13 09:53:58', '::1', 'logout', '2025-04-13 09:53:58'),
('38', '1', '2025-04-13 09:54:04', NULL, '::1', 'success', '2025-04-13 09:54:04'),
('39', '1', NULL, '2025-04-14 21:25:03', '::1', 'logout', '2025-04-14 21:25:03'),
('40', '1', '2025-04-14 21:26:41', NULL, '::1', 'failed', '2025-04-14 21:26:41'),
('41', '1', '2025-04-14 21:26:47', NULL, '::1', 'failed', '2025-04-14 21:26:47'),
('42', '1', '2025-04-14 21:26:59', NULL, '::1', 'success', '2025-04-14 21:26:59'),
('43', '1', '2025-04-17 10:38:20', NULL, '::1', 'success', '2025-04-17 10:38:20'),
('44', '1', NULL, '2025-04-17 22:14:46', '::1', 'logout', '2025-04-17 22:14:46'),
('45', '1', '2025-04-17 22:14:47', NULL, '::1', 'failed', '2025-04-17 22:14:47'),
('46', '1', '2025-04-17 22:14:53', NULL, '::1', 'success', '2025-04-17 22:14:53');

-- Table structure for table `meter_readings`

DROP TABLE IF EXISTS `meter_readings`;
CREATE TABLE `meter_readings` (
  `reading_id` int(11) NOT NULL AUTO_INCREMENT,
  `nozzle_id` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `opening_reading` decimal(12,2) NOT NULL,
  `closing_reading` decimal(12,2) NOT NULL,
  `volume_dispensed` decimal(12,2) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `verification_status` enum('pending','verified','disputed') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verification_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reading_id`),
  KEY `nozzle_id` (`nozzle_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `overtime_records`

DROP TABLE IF EXISTS `overtime_records`;
CREATE TABLE `overtime_records` (
  `overtime_id` int(11) NOT NULL AUTO_INCREMENT,
  `attendance_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `overtime_date` date NOT NULL,
  `overtime_hours` decimal(5,2) NOT NULL,
  `rate_multiplier` decimal(3,2) DEFAULT 1.50,
  `reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`overtime_id`),
  KEY `attendance_id` (`attendance_id`),
  KEY `staff_id` (`staff_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `overtime_records_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`),
  CONSTRAINT `overtime_records_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `overtime_records_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
('1', 'Office Supplies', 'expense', 'Stationery, paper, pens, etc.', 'active', '2025-04-14 05:11:48'),
('2', 'Utilities', 'expense', 'Electricity, water, internet bills', 'active', '2025-04-14 05:11:48'),
('3', 'Maintenance', 'expense', 'Repairs and maintenance costs', 'active', '2025-04-14 05:11:48'),
('4', 'Transport', 'expense', 'Transportation and fuel reimbursements', 'active', '2025-04-14 05:11:48'),
('5', 'Miscellaneous', 'expense', 'Other small expenses', 'active', '2025-04-14 05:11:48'),
('6', 'Petty Cash Fund', 'income', 'Funds added to petty cash', 'active', '2025-04-14 05:11:48'),
('7', 'Sales Return', 'income', 'Cash from returned sales', 'active', '2025-04-14 05:11:48'),
('8', 'Other Income', 'income', 'Miscellaneous income sources', 'active', '2025-04-14 05:11:48');

-- Table structure for table `product_categories`

DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `products`

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `product_code` (`product_code`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pump_nozzles`

DROP TABLE IF EXISTS `pump_nozzles`;
CREATE TABLE `pump_nozzles` (
  `nozzle_id` int(11) NOT NULL AUTO_INCREMENT,
  `pump_id` int(11) NOT NULL,
  `nozzle_number` int(11) NOT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `tank_id` int(11) NOT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`nozzle_id`),
  UNIQUE KEY `pump_nozzle` (`pump_id`,`nozzle_number`),
  KEY `fuel_type_id` (`fuel_type_id`),
  KEY `tank_id` (`tank_id`),
  CONSTRAINT `pump_nozzles_ibfk_1` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`),
  CONSTRAINT `pump_nozzles_ibfk_2` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `pumps`

DROP TABLE IF EXISTS `pumps`;
CREATE TABLE `pumps` (
  `pump_id` int(11) NOT NULL AUTO_INCREMENT,
  `pump_name` varchar(50) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `tank_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `installation_date` date DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pump_id`),
  KEY `tank_id` (`tank_id`),
  CONSTRAINT `pumps_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `purchase_orders`

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `po_id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `status` enum('pending','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `ordered_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `account_check_status` enum('sufficient','insufficient','pending') DEFAULT NULL,
  `topup_deadline` datetime DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `vat_amount` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  KEY `ordered_by` (`ordered_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`),
  CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `sale_items`

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `sale_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `item_type` enum('fuel','product','service') NOT NULL DEFAULT 'fuel',
  `nozzle_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sale_item_id`),
  KEY `sale_id` (`sale_id`),
  KEY `nozzle_id` (`nozzle_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`sale_id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`nozzle_id`) REFERENCES `pump_nozzles` (`nozzle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `sales`

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) DEFAULT NULL,
  `sale_date` datetime NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_vehicle` varchar(50) DEFAULT NULL,
  `sale_type` enum('fuel','product','mixed') NOT NULL DEFAULT 'fuel',
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','credit') NOT NULL DEFAULT 'cash',
  `payment_status` enum('paid','pending','cancelled') NOT NULL DEFAULT 'paid',
  `staff_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sale_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `staff`

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `staff_code` varchar(20) DEFAULT NULL,
  `staff_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `position` varchar(50) NOT NULL,
  `joining_date` date NOT NULL,
  `status` enum('active','inactive','terminated') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`staff_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `staff_assignments`

DROP TABLE IF EXISTS `staff_assignments`;
CREATE TABLE `staff_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `pump_id` int(11) DEFAULT NULL,
  `shift` enum('morning','evening','night') DEFAULT NULL,
  `assignment_date` date NOT NULL,
  `status` enum('assigned','completed','cancelled') NOT NULL DEFAULT 'assigned',
  `assigned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assignment_id`),
  KEY `staff_id` (`staff_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `staff_assignments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`),
  CONSTRAINT `staff_assignments_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `suppliers`

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `suppliers`

INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_person`, `contact_number`, `email`, `address`, `status`, `created_at`, `updated_at`, `phone`) VALUES
('1', 'lakshtiha', 'Lakshitha sajith', NULL, 'lakshithasajith3@gmail.com', '1566/9/ 11 sirimalwaththa kottawa pannipitiya\r\nH.M.L.S.Gunawardana lak', 'active', '2025-04-14 21:59:44', '2025-04-14 22:00:09', '0717536481');

-- Table structure for table `system_settings`

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `description` text DEFAULT NULL,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_settings`

INSERT INTO `system_settings` (`setting_id`, `setting_name`, `setting_value`, `created_at`, `updated_at`, `description`) VALUES
('1', 'company_name', 'Petrol Station', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('2', 'currency_symbol', 'Rs.', '2025-04-13 08:50:44', '2025-04-14 08:00:34', 'Configuration parameter'),
('3', 'system_email', 'admin@petrolstation.com', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('4', 'contact_number', '+1234567890', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('5', 'address', '123 Fuel Street, Gas City, ST 12345', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('6', 'tax_rate', '5', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('7', 'theme_color', 'blue', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('8', 'session_timeout', '7200', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('9', 'low_stock_threshold', '1000', '2025-04-13 08:50:44', '2025-04-13 22:35:19', 'Configuration parameter'),
('10', 'footer_text', 'Powered By Cinex.lk', '2025-04-13 08:50:44', '2025-04-14 21:24:57', 'Configuration parameter'),
('11', 'min_account_balance', '0', '2025-04-17 10:39:02', '2025-04-17 10:39:02', 'Minimum balance to maintain in petroleum account');

-- Table structure for table `tank_dip_measurements`

DROP TABLE IF EXISTS `tank_dip_measurements`;
CREATE TABLE `tank_dip_measurements` (
  `dip_id` int(11) NOT NULL AUTO_INCREMENT,
  `tank_id` int(11) NOT NULL,
  `measurement_date` date NOT NULL,
  `dip_value` decimal(10,2) NOT NULL,
  `calculated_volume` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`dip_id`),
  KEY `tank_id` (`tank_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `tank_dip_measurements_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`),
  CONSTRAINT `tank_dip_measurements_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `tank_inventory`

DROP TABLE IF EXISTS `tank_inventory`;
CREATE TABLE `tank_inventory` (
  `operation_id` int(11) NOT NULL AUTO_INCREMENT,
  `tank_id` int(11) NOT NULL,
  `operation_type` enum('delivery','sales','adjustment','leak','transfer','initial') NOT NULL,
  `operation_date` datetime NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `previous_volume` decimal(10,2) NOT NULL,
  `new_volume` decimal(10,2) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`operation_id`),
  KEY `tank_id` (`tank_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `tank_inventory_ibfk_1` FOREIGN KEY (`tank_id`) REFERENCES `tanks` (`tank_id`),
  CONSTRAINT `tank_inventory_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `tank_inventory`

INSERT INTO `tank_inventory` (`operation_id`, `tank_id`, `operation_type`, `operation_date`, `change_amount`, `previous_volume`, `new_volume`, `recorded_by`, `notes`, `created_at`) VALUES
('1', '1', 'initial', '2025-04-14 21:57:24', '3500.00', '0.00', '3500.00', '1', 'Initial tank setup with volume of 3500 liters', '2025-04-14 21:57:24');

-- Table structure for table `tanks`

DROP TABLE IF EXISTS `tanks`;
CREATE TABLE `tanks` (
  `tank_id` int(11) NOT NULL AUTO_INCREMENT,
  `tank_name` varchar(50) NOT NULL,
  `fuel_type_id` int(11) NOT NULL,
  `capacity` decimal(10,2) NOT NULL,
  `current_volume` decimal(10,2) NOT NULL DEFAULT 0.00,
  `low_level_threshold` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`tank_id`),
  KEY `fuel_type_id` (`fuel_type_id`),
  CONSTRAINT `tanks_ibfk_1` FOREIGN KEY (`fuel_type_id`) REFERENCES `fuel_types` (`fuel_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `tanks`

INSERT INTO `tanks` (`tank_id`, `tank_name`, `fuel_type_id`, `capacity`, `current_volume`, `low_level_threshold`, `status`, `location`, `created_at`, `updated_at`, `notes`) VALUES
('1', 'Test', '3', '35000.00', '3500.00', '2000.00', 'active', '', '2025-04-14 21:57:24', '2025-04-14 21:57:24', '');

-- Table structure for table `users`

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','manager','cashier','attendant') NOT NULL DEFAULT 'attendant',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `role`, `status`, `last_login`, `created_at`, `email`) VALUES
('1', 'admin', '$2y$10$2/mVlqjbF3tAIiIXjUg2e.IYCQbXB5Kc1FSPBUbDsNFch2lVl.2lS', 'System Administrator', 'admin', 'active', NULL, '2025-04-13 22:37:39', NULL);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
