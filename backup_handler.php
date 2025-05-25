<?php
/**
 * Backup Handler
 * 
 * This file handles database backup and restore operations for the Petrol Pump Management System
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include required files
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Debug log
$debug_log = __DIR__ . '/backup_restore_debug.log';
file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);
file_put_contents($debug_log, "POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($debug_log, "FILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);

// Check if user is logged in
if (!is_logged_in()) {
    file_put_contents($debug_log, "Error: User not logged in\n", FILE_APPEND);
    header("Location: login.php");
    exit;
}

// Check if user has admin permissions
if (get_current_user_role() !== 'admin') {
    file_put_contents($debug_log, "Error: User not admin\n", FILE_APPEND);
    set_flash_message('error', 'You do not have permission to perform backup operations.');
    header("Location: settings.php");
    exit;
}

// Create backup directory if it doesn't exist
$backup_dir = __DIR__ . '/backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Create a .htaccess file to prevent direct access to the backups folder
$htaccess_file = $backup_dir . '/.htaccess';
if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, "Deny from all");
}

// Get the action parameter (from either POST or GET)
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
file_put_contents($debug_log, "Action: " . $action . "\n", FILE_APPEND);

if ($action === 'backup') {
    // Handle backup action
    createBackup();
} elseif ($action === 'restore') {
    // Handle restore action
    restoreBackup();
} else {
    file_put_contents($debug_log, "Error: Invalid action\n", FILE_APPEND);
    set_flash_message('error', 'Invalid action specified.');
    header("Location: settings.php");
    exit;
}

/**
 * Create a database backup
 */
function createBackup() {
    global $conn, $db_name, $db_user, $db_pass, $db_host, $debug_log;
    
    try {
        file_put_contents($debug_log, "Creating backup...\n", FILE_APPEND);
        
        // Generate a unique filename
        $date = date('Y-m-d_H-i-s');
        $backup_filename = 'backup_' . $date . '.sql';
        $backup_file = __DIR__ . '/backups/' . $backup_filename;
        
        // Get tables from the database
        $tables = array();
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        // Open the backup file for writing
        $handle = fopen($backup_file, 'w');
        
        // Add database selection command
        fwrite($handle, "-- Petrol Pump Management System Database Backup\n");
        fwrite($handle, "-- Generated on: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: " . $db_name . "\n\n");
        fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($handle, "SET time_zone = \"+00:00\";\n\n");
        fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
        fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
        fwrite($handle, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
        fwrite($handle, "/*!40101 SET NAMES utf8mb4 */;\n\n");
        
        // Process each table
        foreach ($tables as $table) {
            // Get table structure
            fwrite($handle, "-- Table structure for table `$table`\n\n");
            
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch_row();
            $create_table = $row[1];
            
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $create_table . ";\n\n");
            
            // Get table data
            $result = $conn->query("SELECT * FROM `$table`");
            
            if ($result && $result->num_rows > 0) {
                fwrite($handle, "-- Dumping data for table `$table`\n\n");
                
                // Get column names
                $columns = array();
                $num_fields = mysqli_num_fields($result);
                for ($i = 0; $i < $num_fields; $i++) {
                    $field_info = mysqli_fetch_field_direct($result, $i);
                    $columns[] = "`" . $field_info->name . "`";
                }
                
                // Write column names
                fwrite($handle, "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES\n");
                
                // Write data
                $first_row = true;
                while ($row = $result->fetch_row()) {
                    if (!$first_row) {
                        fwrite($handle, ",\n");
                    } else {
                        $first_row = false;
                    }
                    
                    fwrite($handle, "(");
                    for ($i = 0; $i < $num_fields; $i++) {
                        if ($i > 0) {
                            fwrite($handle, ", ");
                        }
                        
                        if ($row[$i] === null) {
                            fwrite($handle, "NULL");
                        } else {
                            fwrite($handle, "'" . $conn->real_escape_string($row[$i]) . "'");
                        }
                    }
                    fwrite($handle, ")");
                }
                
                if (!$first_row) {
                    fwrite($handle, ";\n\n");
                }
            }
        }
        
        // Add closing commands
        fwrite($handle, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
        fwrite($handle, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
        fwrite($handle, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
        
        // Close the file
        fclose($handle);
        
        // Check if the backup_history table exists, create it if not
        $check_table_query = "SHOW TABLES LIKE 'backup_history'";
        $table_exists = $conn->query($check_table_query);
        
        if ($table_exists->num_rows == 0) {
            // Create backup_history table
            $create_table_query = "CREATE TABLE `backup_history` (
                `backup_id` int(11) NOT NULL AUTO_INCREMENT,
                `filename` varchar(255) NOT NULL,
                `created_by` int(11) DEFAULT NULL,
                `file_size` int(11) DEFAULT NULL,
                `is_restore` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`backup_id`),
                KEY `created_by` (`created_by`),
                CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $conn->query($create_table_query);
        }
        
        // Record backup in the database
        $user_id = get_current_user_id();
        $file_size = filesize($backup_file);
        $conn->query("INSERT INTO backup_history (filename, created_by, file_size, created_at) 
                     VALUES ('$backup_filename', $user_id, $file_size, NOW())");
        
        file_put_contents($debug_log, "Backup created successfully: $backup_filename\n", FILE_APPEND);
        
        // Provide the file for download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $backup_filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file));
        readfile($backup_file);
        
        exit;
    } catch (Exception $e) {
        file_put_contents($debug_log, "Backup failed: " . $e->getMessage() . "\n", FILE_APPEND);
        set_flash_message('error', 'Backup failed: ' . $e->getMessage());
        header("Location: settings.php");
        exit;
    }
}

/**
 * Restore from a database backup
 */
function restoreBackup() {
    global $conn, $debug_log;
    
    file_put_contents($debug_log, "Starting restore process...\n", FILE_APPEND);
    
    // Check if file was uploaded
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        file_put_contents($debug_log, "Error: No backup file uploaded or upload error occurred.\n", FILE_APPEND);
        set_flash_message('error', 'No backup file uploaded or upload error occurred.');
        header("Location: settings.php?tab=backup");
        exit;
    }
    
    // Check if confirmation checkbox is checked
    if (!isset($_POST['confirm_restore']) || $_POST['confirm_restore'] !== 'on') {
        file_put_contents($debug_log, "Error: Confirmation checkbox not checked.\n", FILE_APPEND);
        set_flash_message('error', 'You must confirm that you understand the risks of database restoration.');
        header("Location: settings.php?tab=backup");
        exit;
    }
    
    $tmp_file = $_FILES['backup_file']['tmp_name'];
    $filename = $_FILES['backup_file']['name'];
    
    // Validate file type
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($file_ext !== 'sql') {
        file_put_contents($debug_log, "Error: Invalid file type.\n", FILE_APPEND);
        set_flash_message('error', 'Invalid file type. Only SQL files are allowed.');
        header("Location: settings.php?tab=backup");
        exit;
    }
    
    file_put_contents($debug_log, "File validated, beginning restore...\n", FILE_APPEND);
    
    try {
        // First, make sure the backup_history table exists
        $check_table_query = "SHOW TABLES LIKE 'backup_history'";
        $table_exists = $conn->query($check_table_query);
        
        if ($table_exists->num_rows == 0) {
            // Create backup_history table
            $create_table_query = "CREATE TABLE `backup_history` (
                `backup_id` int(11) NOT NULL AUTO_INCREMENT,
                `filename` varchar(255) NOT NULL,
                `created_by` int(11) DEFAULT NULL,
                `file_size` int(11) DEFAULT NULL,
                `is_restore` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`backup_id`),
                KEY `created_by` (`created_by`),
                CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $conn->query($create_table_query);
        }
        
        // Read the SQL file
        $sql_content = file_get_contents($tmp_file);
        if (!$sql_content) {
            throw new Exception("Failed to read SQL file.");
        }
        
        file_put_contents($debug_log, "SQL file read, size: " . strlen($sql_content) . " bytes\n", FILE_APPEND);
        
        // Split the SQL file into individual statements
        $sql_statements = preg_split('/;\s*[\r\n]+/', $sql_content);
        
        file_put_contents($debug_log, "Found " . count($sql_statements) . " SQL statements\n", FILE_APPEND);
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Execute each statement
        $statement_count = 0;
        foreach ($sql_statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                file_put_contents($debug_log, "Executing statement #" . ($statement_count+1) . "\n", FILE_APPEND);
                $result = $conn->query($statement);
                if ($result === false) {
                    throw new Exception("Error executing SQL statement: " . $conn->error . " in statement: " . substr($statement, 0, 100) . "...");
                }
                $statement_count++;
            }
        }
        
        file_put_contents($debug_log, "Successfully executed $statement_count statements\n", FILE_APPEND);
        
        // Commit transaction
        $conn->commit();
        
        // Record the restoration in the database
        $user_id = get_current_user_id();
        $file_size = $_FILES['backup_file']['size'];
        $safe_filename = $conn->real_escape_string($filename);
        $conn->query("INSERT INTO backup_history (filename, created_by, file_size, created_at, is_restore) 
                     VALUES ('$safe_filename', $user_id, $file_size, NOW(), 1)");
        
        // Create a success log file for additional confirmation
        $success_log = __DIR__ . '/restore_success.log';
        file_put_contents($success_log, 
            date('Y-m-d H:i:s') . " - Database restore completed successfully\n" .
            "File: " . $filename . "\n" .
            "User ID: " . $user_id . "\n" .
            "File size: " . $file_size . " bytes\n",
            FILE_APPEND
        );
        
        file_put_contents($debug_log, "Restore completed successfully\n", FILE_APPEND);
        
        set_flash_message('success', 'Database restored successfully from backup.');
        header("Location: settings.php?tab=backup&restore=success");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        file_put_contents($debug_log, "Restore failed: " . $e->getMessage() . "\n", FILE_APPEND);
        
        set_flash_message('error', 'Restore failed: ' . $e->getMessage());
        header("Location: settings.php?tab=backup");
        exit;
    }
}
?>