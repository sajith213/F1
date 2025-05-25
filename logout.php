<?php
/**
 * Logout Script
 * 
 * Handles user logout and session destruction for the Petrol Pump Management System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout action if logging is enabled
if (isset($_SESSION['user_id'])) {
    require_once 'includes/db.php';
    
    if (isset($conn)) {
        try {
            // Current time
            $logout_time = date('Y-m-d H:i:s');
            
            // IP address
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            // Log the logout if the login_logs table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'login_logs'");
            if ($check_table && $check_table->num_rows > 0) {
                $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, logout_time, ip_address, status) VALUES (?, ?, ?, 'logout')");
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $_SESSION['user_id'], $logout_time, $ip_address);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            }
        } catch (Exception $e) {
            // Silently continue with logout even if logging fails
        }
    }
}

// Clear all session variables
$_SESSION = array();

// If a session cookie is used, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>