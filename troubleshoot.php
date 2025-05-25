<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Petrol Pump Management System Troubleshooter</h2>";

// Step 1: Check if includes folder exists
echo "<h3>Step 1: Checking file structure</h3>";
if (file_exists('includes')) {
    echo "✅ 'includes' folder exists<br>";
    
    if (file_exists('includes/config.php')) {
        echo "✅ 'includes/config.php' exists<br>";
    } else {
        echo "❌ 'includes/config.php' does not exist!<br>";
    }
    
    if (file_exists('includes/db.php')) {
        echo "✅ 'includes/db.php' exists<br>";
    } else {
        echo "❌ 'includes/db.php' does not exist!<br>";
    }
    
    if (file_exists('includes/functions.php')) {
        echo "✅ 'includes/functions.php' exists<br>";
    } else {
        echo "❌ 'includes/functions.php' does not exist!<br>";
    }
} else {
    echo "❌ 'includes' folder does not exist!<br>";
}

// Step 2: Check database connection
echo "<h3>Step 2: Testing database connection</h3>";
if (file_exists('includes/config.php')) {
    // Load the config file manually to get credentials
    include 'includes/config.php';
    echo "Database settings:<br>";
    echo "- Host: $db_host<br>";
    echo "- Database: $db_name<br>";
    echo "- Username: $db_user<br>";
    echo "- Password: " . (empty($db_pass) ? "EMPTY!" : "[HIDDEN]") . "<br>";
    
    // Try to connect to the database
    try {
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        
        if ($conn->connect_error) {
            echo "❌ Connection failed: " . $conn->connect_error . "<br>";
        } else {
            echo "✅ Database connection successful<br>";
            
            // Step 3: Check if users table exists
            echo "<h3>Step 3: Checking database structure</h3>";
            $result = $conn->query("SHOW TABLES LIKE 'users'");
            if ($result && $result->num_rows > 0) {
                echo "✅ 'users' table exists<br>";
                
                // Check users table structure
                $result = $conn->query("DESCRIBE users");
                if ($result) {
                    echo "Users table structure:<br><pre>";
                    while ($row = $result->fetch_assoc()) {
                        echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . "\n";
                    }
                    echo "</pre>";
                }
                
                // Step 4: Check if admin user exists
                echo "<h3>Step 4: Checking admin user</h3>";
                $result = $conn->query("SELECT user_id, username, status, role FROM users WHERE username = 'admin'");
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    echo "✅ Admin user exists:<br>";
                    echo "- ID: " . $user['user_id'] . "<br>";
                    echo "- Username: " . $user['username'] . "<br>";
                    echo "- Status: " . $user['status'] . "<br>";
                    echo "- Role: " . $user['role'] . "<br>";
                    
                    // Step 5: Check admin password
                    echo "<h3>Step 5: Checking admin password</h3>";
                    $result = $conn->query("SELECT password FROM users WHERE username = 'admin'");
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $stored_hash = $row['password'];
                        echo "Stored password hash for admin: " . $stored_hash . "<br>";
                        
                        // Test password_verify function
                        if (function_exists('password_verify')) {
                            echo "✅ password_verify function exists<br>";
                            
                            // Test with known good hash and password
                            $test_hash = '$2y$10$2/mVlqjbF3tAIiIXjUg2e.IYCQbXB5Kc1FSPBUbDsNFch2lVl.2lS';
                            $test_pass = 'admin123';
                            
                            if (password_verify($test_pass, $test_hash)) {
                                echo "✅ password_verify works correctly with test data<br>";
                            } else {
                                echo "❌ password_verify FAILED with test data!<br>";
                            }
                            
                            // Test with actual stored hash
                            if (password_verify($test_pass, $stored_hash)) {
                                echo "✅ Admin password ('admin123') verifies successfully against stored hash<br>";
                            } else {
                                echo "❌ Admin password ('admin123') verification FAILED against stored hash!<br>";
                            }
                        } else {
                            echo "❌ password_verify function doesn't exist! PHP version may be too old.<br>";
                        }
                    } else {
                        echo "❌ Couldn't retrieve admin password hash!<br>";
                    }
                } else {
                    echo "❌ Admin user does not exist!<br>";
                }
            } else {
                echo "❌ 'users' table does not exist!<br>";
            }
            
            // Step 6: Testing login manually
            echo "<h3>Step 6: Testing login function manually</h3>";
            
            // Include the db.php file to ensure all functions are loaded
            if (file_exists('includes/db.php')) {
                include_once 'includes/functions.php';
                include_once 'includes/db.php';
                
                if (function_exists('authenticate_user')) {
                    echo "✅ authenticate_user function exists<br>";
                    $result = authenticate_user('admin', 'admin123');
                    if ($result !== false) {
                        echo "✅ Manual authentication successful!<br>";
                    } else {
                        echo "❌ Manual authentication FAILED!<br>";
                    }
                } else {
                    echo "❌ authenticate_user function doesn't exist!<br>";
                }
            } else {
                echo "❌ Can't test login function because db.php doesn't exist<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Can't test database connection because config.php doesn't exist<br>";
}

echo "<h3>Troubleshooting complete</h3>";
?>