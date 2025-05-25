<?php
/**
 * Authentication Functions
 * 
 * This file contains functions related to user authentication, session management,
 * and permissions control.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Authenticate a user with username and password
 * 
 * @param string $username Username
 * @param string $password Password (plain text)
 * @return bool|array False on failure, user data on success
 */
function authenticate_user($username, $password) {
    global $conn;
    
    // Sanitize inputs
    $username = mysqli_real_escape_string($conn, $username);
    
    // Get user from database
    $sql = "SELECT * FROM users WHERE username = '$username' AND status = 'active'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Password is correct
            return $user;
        }
    }
    
    // Authentication failed
    return false;
}

/**
 * Log in a user and set session variables
 * 
 * @param array $user User data
 * @return void
 */
function login_user($user) {
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name']; // Make sure this matches what's used elsewhere
    $_SESSION['role'] = $user['role']; // Make sure this matches what's used elsewhere
    
    // Update last login time in the database
    global $conn;
    $user_id = $user['user_id'];
    
    $sql = "UPDATE users SET last_login = NOW() WHERE user_id = '$user_id'";
    mysqli_query($conn, $sql);
}

/**
 * Log out the current user
 * 
 * @return void
 */
function logout_user() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

/**
 * Check if the current user has a specific permission
 * 
 * @param string $permission_name Name of the permission to check
 * @return bool True if user has permission, false otherwise
 */
function has_permission($permission_name) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Get user role - check if it exists
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $role = $_SESSION['role'];
    
  // Define role-based permissions
$permissions = [
    'admin' => [
        'create_purchase_order',
        'update_purchase_order',
        'view_purchase_orders',
        'delete_purchase_order',
        'record_delivery',
        'manage_suppliers',
        'manage_tanks',
        'manage_pumps',
        'manage_staff',
        'manage_prices',
        'manage_cash',
        'manage_attendance',
        'manage_pos',
        'view_reports',
        'manage_users',
        'view_dashboard', 
        'manage_petty_cash',
        'approve_petty_cash',
        'admin_petty_cash',
        // Petroleum Account Management permissions
        'manage_petroleum_account',
        'approve_petroleum_account',
        // Salary Management permissions
        'manage_salaries',
        'calculate_salaries',
        'view_salary_reports',
        'approve_payments',
        'manage_loans'
    ],
    'manager' => [
        'create_purchase_order',
        'update_purchase_order',
        'view_purchase_orders',
        'record_delivery',
        'manage_suppliers',
        'manage_tanks',
        'manage_pumps',
        'manage_staff',
        'manage_prices',
        'manage_cash',
        'manage_attendance',
        'manage_pos',
        'view_reports',
        'view_dashboard',
        'manage_petty_cash',
        'approve_petty_cash',
        // Petroleum Account Management permissions
        'manage_petroleum_account',
        'approve_petroleum_account',
        // Salary Management permissions
        'manage_salaries',
        'calculate_salaries',
        'view_salary_reports',
        'approve_payments',
        'manage_loans'
    ],
    'cashier' => [
        'view_purchase_orders',
        'record_delivery',
        'manage_cash',
        'manage_pos',
        'view_dashboard',
        'manage_petty_cash',
        // Petroleum Account permission for cashiers (view only)
        'manage_petroleum_account',
        // Limited Salary Management permissions
        'view_salary_reports'
    ],
    'attendant' => [
        'view_dashboard'
    ]
];
    
    // Check if the role exists and the permission is granted to that role
    return isset($permissions[$role]) && in_array($permission_name, $permissions[$role]);
}

/**
 * Get current user's full name
 * 
 * @return string User's full name or empty string if not logged in
 */
function get_current_user_name() {
    return $_SESSION['full_name'] ?? '';
}

/**
 * Get current user's ID
 * 
 * @return int|null User ID or null if not logged in
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's role
 * 
 * @return string|null User role or null if not logged in
 */
function get_current_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Set a flash message to be displayed on the next page load
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message content
 * @return void
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $type;
}

/**
 * Get and clear the current flash message
 * 
 * @return array|null Array with 'type' and 'message' keys, or null if no message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'type' => $_SESSION['flash_message_type'],
            'message' => $_SESSION['flash_message']
        ];
        
        // Clear the message
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_message_type']);
        
        return $message;
    }
    
    return null;
}