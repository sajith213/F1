<?php
// Start session
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Error message variable
$error_message = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include database connection
    require_once 'includes/db.php';
    
    // Get username and password from form
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password';
    } else {
        // Direct admin login bypass for troubleshooting
        if ($username === 'admin' && $password === 'admin123') {
            // Get admin user from database
            $query = "SELECT user_id, username, full_name, role FROM users WHERE username = 'admin' AND status = 'active'";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Start a new session
                session_regenerate_id(true);
                
                // Store user data in session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Try to log the successful login
                try {
                    $table_check = $conn->query("SHOW TABLES LIKE 'login_logs'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $login_time = date('Y-m-d H:i:s');
                        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, ?, ?, 'success')");
                        $log_stmt->bind_param("iss", $user['user_id'], $login_time, $ip_address);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                } catch (Exception $e) {
                    // Silently continue if logging fails
                }
                
                // Redirect to dashboard
                header("Location: index.php");
                exit;
            } else {
                $error_message = 'Admin account not found or inactive';
            }
        } else {
            // Regular login flow for non-admin users
            // Prepare SQL statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT user_id, username, password, full_name, role, status FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Check if account is active
                if ($user['status'] !== 'active') {
                    $error_message = 'This account has been deactivated. Please contact administrator.';
                } 
                // Verify password
                elseif (password_verify($password, $user['password'])) {
                    // Password is correct, start a new session
                    session_regenerate_id(true);
                    
                    // Store user data in session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    
                    // Check if login_logs table exists before trying to log
                    try {
                        $table_check = $conn->query("SHOW TABLES LIKE 'login_logs'");
                        if ($table_check && $table_check->num_rows > 0) {
                            // Log the successful login
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            $login_time = date('Y-m-d H:i:s');
                            $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, ?, ?, 'success')");
                            $log_stmt->bind_param("iss", $user['user_id'], $login_time, $ip_address);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                    } catch (Exception $e) {
                        // Silently continue if logging fails
                        // This won't affect the user's login experience
                    }
                    
                    // Redirect to dashboard
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = 'Invalid username or password';
                    
                    // Try to log the failed login
                    try {
                        $table_check = $conn->query("SHOW TABLES LIKE 'login_logs'");
                        if ($table_check && $table_check->num_rows > 0) {
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            $login_time = date('Y-m-d H:i:s');
                            $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, status) VALUES (?, ?, ?, 'failed')");
                            $log_stmt->bind_param("iss", $user['user_id'], $login_time, $ip_address);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                    } catch (Exception $e) {
                        // Silently continue if logging fails
                    }
                }
                
                $stmt->close();
            } else {
                $error_message = 'Invalid username or password';
            }
        }
    }
    
    if (isset($conn)) {
        $conn->close();
    }
}

// Get company information
$company_name = "Petrol Station";
$company_logo = "assets/images/logo.png";

// Get company info only if not processing login form to avoid DB reconnection issues
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && file_exists('includes/db.php')) {
    require_once 'includes/db.php';
    if (isset($conn) && $conn) {
        try {
            $settings_query = $conn->query("SELECT setting_name, setting_value FROM system_settings WHERE setting_name IN ('company_name')");
            if ($settings_query) {
                while ($setting = $settings_query->fetch_assoc()) {
                    if ($setting['setting_name'] == 'company_name') {
                        $company_name = $setting['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            // Silently fail, use default values
        }
        
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($company_name) ?></title>
    
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom styles -->
    <link rel="stylesheet" href="assets/css/login.css">
    
    <!-- Removed background image style to prevent 404 errors -->
</head>
<body class="bg-gray-100 h-screen">
    <div class="flex h-full">
        <!-- Left side - Blue background instead of image (to avoid missing image errors) -->
        <div class="hidden md:block md:w-1/2 bg-blue-800 relative">
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="text-white text-center p-10">
                    <h1 class="text-4xl font-bold mb-4"><?= htmlspecialchars($company_name) ?></h1>
                    <p class="text-xl mb-8">Fuel Management Solution</p>
                    <p class="text-sm opacity-80">Streamline your operations with our comprehensive management system</p>
                </div>
            </div>
        </div>
        
        <!-- Right side - Login form -->
        <div class="w-full md:w-1/2 flex items-center justify-center p-6">
            <div class="w-full max-w-md">
                <!-- Logo for mobile view (without image to prevent 404 errors) -->
                <div class="md:hidden text-center mb-8">
                    <!-- <img src="<?= $company_logo ?>" alt="Logo" class="h-16 mx-auto"> -->
                    <div class="h-16 w-16 bg-blue-600 rounded-full mx-auto flex items-center justify-center">
                        <span class="text-white text-2xl font-bold">PS</span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mt-2"><?= htmlspecialchars($company_name) ?></h1>
                </div>
                
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-gray-800">Welcome Back</h2>
                        <p class="text-gray-600 mt-1">Please sign in to your account</p>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="space-y-6">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" id="username" name="username" 
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                    required autofocus>
                            </div>
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="password" name="password" 
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                    required>
                            </div>
                        </div>
                        
                        <div>
                            <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Sign in
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="mt-6 text-center text-sm text-gray-500">
                    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?>. All rights reserved.</p>
                    <p class="mt-1">Powered by Cinex.lk</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide password functionality could be added here
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on username field
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>