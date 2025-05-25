<?php
// Turn on error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the config file
require_once 'includes/config.php';

echo "<h1>Database Connection Test</h1>";

// Try to connect
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        die("<p style='color:red'>Connection failed: " . $conn->connect_error . "</p>");
    }
    
    echo "<p style='color:green'>Connection successful!</p>";
    echo "<p>Connected to: " . $db_host . "</p>";
    echo "<p>Database: " . $db_name . "</p>";
    echo "<p>User: " . $db_user . "</p>";
    
    // Check if users table exists
    $result = $conn->query("SELECT 1 FROM users LIMIT 1");
    if ($result) {
        echo "<p style='color:green'>Users table exists and is accessible.</p>";
    } else {
        echo "<p style='color:red'>Could not access users table: " . $conn->error . "</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    die("<p style='color:red'>Exception: " . $e->getMessage() . "</p>");
}
?>