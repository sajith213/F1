<?php
// Add debugging to login process
file_put_contents('login_debug.txt', date('Y-m-d H:i:s') . " - Login Debug Start\n", FILE_APPEND);

// Add the admin user directly with SQL
require_once 'includes/db.php';

if (isset($conn) && $conn) {
    // Get current admin user
    $result = $conn->query("SELECT user_id, username, password FROM users WHERE username = 'admin'");
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        file_put_contents('login_debug.txt', "Admin user exists. ID: {$row['user_id']}, Password hash: {$row['password']}\n", FILE_APPEND);
    } else {
        file_put_contents('login_debug.txt', "Admin user not found!\n", FILE_APPEND);
    }
    
    // Test password verification
    $test_pass = "admin123";
    $hash = '$2y$10$2/mVlqjbF3tAIiIXjUg2e.IYCQbXB5Kc1FSPBUbDsNFch2lVl.2lS';
    $verify_result = password_verify($test_pass, $hash);
    file_put_contents('login_debug.txt', "Password verification test: " . ($verify_result ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
    
    $conn->close();
}

file_put_contents('login_debug.txt', "Debug End\n\n", FILE_APPEND);
echo "Debug log written. Check login_debug.txt file.";
?><?php
// Add debugging to login process
$debug_log = date('Y-m-d H:i:s') . " - Login Debug Start\n";

// Add the admin user directly with SQL
require_once 'includes/db.php';

if (isset($conn) && $conn) {
    // Get current admin user
    $result = $conn->query("SELECT user_id, username, password FROM users WHERE username = 'admin'");
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $debug_log .= "Admin user exists. ID: {$row['user_id']}, Password hash: {$row['password']}\n";
    } else {
        $debug_log .= "Admin user not found!\n";
    }
    
    // Test password verification
    $test_pass = "admin123";
    $hash = '$2y$10$2/mVlqjbF3tAIiIXjUg2e.IYCQbXB5Kc1FSPBUbDsNFch2lVl.2lS';
    $verify_result = password_verify($test_pass, $hash);
    $debug_log .= "Password verification test: " . ($verify_result ? "SUCCESS" : "FAILED") . "\n";
    
    $conn->close();
}

$debug_log .= "Debug End\n\n";
file_put_contents('login_debug.txt', $debug_log, FILE_APPEND);

// Display the debug information on screen
echo "<pre>" . htmlspecialchars($debug_log) . "</pre>";
?>