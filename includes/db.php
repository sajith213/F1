<?php
/**
 * Database Connection
 *
 * This file establishes connection to the MySQL database
 */

// Include configuration file (Ensure this contains correct LIVE credentials on host)
// It should define $db_host, $db_user, $db_pass, $db_name
require_once __DIR__ . '/config.php';

// Include functions file (Required for load_settings, ensure path is correct)
// Consider if this is truly needed here or can be included later.
require_once __DIR__ . '/functions.php';

// Initialize connection variable
$conn = null;

try {
    // --- Check if DB configuration variables exist ---
    if (!isset($db_host)) {
        throw new Exception("Database configuration error: \$db_host is not defined (check config.php).");
    }
    if (!isset($db_user)) {
        throw new Exception("Database configuration error: \$db_user is not defined (check config.php).");
    }
    if (!isset($db_pass)) {
        // Password might be empty, which is okay sometimes, but variable should exist.
        // Add check if needed: throw new Exception("Database configuration error: \$db_pass is not defined (check config.php).");
    }
    if (!isset($db_name)) {
        throw new Exception("Database configuration error: \$db_name is not defined (check config.php).");
    }

    // --- Attempt to create connection ---
    // Use @ to suppress default PHP warning; we handle errors with connect_error check
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

    // --- Check connection ---
    // Use the connect_error property which is reliable
    if ($conn->connect_error) {
        // Provide specific error details in the exception
        throw new Exception("Connection failed: " . $conn->connect_error . " [Host: {$db_host}, User: {$db_user}]");
    }

    // --- Set charset ---
    // Set charset to ensure proper encoding (recommended)
    if (!$conn->set_charset("utf8mb4")) {
         // Log a warning if setting charset fails, but don't stop execution
         error_log("Warning: Failed to set database charset to utf8mb4: " . $conn->error);
    }

    // --- Load settings (optional, consider moving) ---
    // Ensure the function exists and handle potential errors within load_settings itself
    if (function_exists('load_settings')) {
       load_settings($conn); // Assumes load_settings has its own error handling
    } else {
       // Log if the required function isn't found (might indicate include issues)
       error_log("Warning: function 'load_settings' not found after including functions.php in db.php.");
    }

    // --- Currency Symbol ---
    // CURRENCY_SYMBOL should ideally be defined elsewhere (e.g., in config.php or after load_settings)
    // Example check if needed here:
    // if (!defined('CURRENCY_SYMBOL') && function_exists('get_setting')) {
    //     define('CURRENCY_SYMBOL', get_setting('currency_symbol', 'Rs. ')); // Default 'Rs. '
    // }


} catch (Exception $e) {
    // --- CRITICAL ERROR HANDLING ---
    // An exception occurred during connection or setup.

    // 1. Log the detailed error for the administrator/developer
    // (Make sure PHP error logging is enabled on the server)
    error_log("Database setup error: " . $e->getMessage());

    // 2. Stop script execution and show a user-friendly message
    // This prevents subsequent code from running with an invalid $conn object
    // Customize the HTML/message as desired
    echo "<!DOCTYPE html><html><head><title>Database Error</title></head><body style='font-family: sans-serif;'>";
    echo "<div style='border: 2px solid #cc0000; padding: 20px; margin: 30px auto; max-width: 800px; background-color: #fff0f0;'>";
    echo "<h2 style='color: #cc0000;'>Database Connection Error</h2>";
    echo "<p>We encountered a problem connecting to the database. This may be temporary.</p>";
    echo "<p>Please notify the system administrator if this problem persists.</p>";
    // Optional: Display simplified error for admins if logged in?
    // if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    //     echo "<p><small><strong>Error detail:</strong> " . htmlspecialchars($e->getMessage()) . "</small></p>";
    // }
    echo "</div>";
    echo "</body></html>";
    exit; // Stop script execution completely
}

// If the script reaches this point without exiting in the catch block,
// $conn should be a valid mysqli connection object.

?>