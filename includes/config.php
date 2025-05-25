<?php
/**
 * Database Configuration
 * 
 * This file contains database connection settings
 * and other application configurations
 */

// Database connection parameters
$db_host = "127.0.0.1:3306";     // Database host
$db_name = "calilpkt_demo_fuel";   // Database name
$db_user = "demo_fuel";          // Database username 
$db_pass = "2cjC3epNhLzFCKk2";              // Database password

// Time zone setting
date_default_timezone_set('Asia/Colombo');

// Session configuration
$session_lifetime = 60 * 60 * 2; // 2 hours

// Security settings
$salt = "fuel_management_system_salt"; // Used for additional security

// Initialize application settings
$app_settings = [];

// Default application settings (will be overridden by database settings)
$app_name = "Petrol Pump Management System";
$app_version = "1.0.0";
$app_url = "http://localhost/fuel/";

// File upload settings
$allowed_extensions = array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx');
$max_file_size = 5 * 1024 * 1024; // 5MB

// Error reporting settings
// Development environment: show all errors
// Uncomment the lines below for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Production environment: hide errors
// Uncomment the lines below for production
// error_reporting(0);
// ini_set('display_errors', 0);

// System log directory
$log_dir = __DIR__ . "/../logs/";

// Define constants
define('ADMIN_EMAIL', 'admin@petrolstation.com');
define('RECORDS_PER_PAGE', 10);
define('CURRENCY_SYMBOL', '$'); // Change the symbol as needed

// Note: After database connection is established, settings will be loaded from database
// This happens in db.php which includes this file