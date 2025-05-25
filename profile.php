<?php
/**
 * User Profile Page
 * 
 * This page allows users to view and update their profile information
 */

// Set page title
$page_title = "My Profile";

// Include header
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get database connection
require_once 'includes/db.php';

// Process form submission for profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get user inputs
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    if (empty($full_name)) {
        $error_message = "Full name is required";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Valid email address is required";
    } elseif (!empty($new_password) && strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = "New passwords do not match";
    } else {
        // Check if current password is correct if user is trying to change password
        if (!empty($new_password)) {
            // Get current password hash from database
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify current password
                if (!password_verify($current_password, $user['password'])) {
                    $error_message = "Current password is incorrect";
                    $stmt->close();
                } else {
                    $stmt->close();
                    
                    // Update user profile with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE user_id = ?");
                    $update_stmt->bind_param("sssi", $full_name, $email, $hashed_password, $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Profile updated successfully including password";
                        
                        // Update session data
                        $_SESSION['full_name'] = $full_name;
                    } else {
                        $error_message = "Error updating profile: " . $conn->error;
                    }
                    
                    $update_stmt->close();
                }
            } else {
                $error_message = "User not found";
                $stmt->close();
            }
        } else {
            // Update user profile without changing password
            $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
            $update_stmt->bind_param("ssi", $full_name, $email, $_SESSION['user_id']);
            
            if ($update_stmt->execute()) {
                $success_message = "Profile updated successfully";
                
                // Update session data
                $_SESSION['full_name'] = $full_name;
            } else {
                $error_message = "Error updating profile: " . $conn->error;
            }
            
            $update_stmt->close();
        }
    }
}

// Get user data
$stmt = $conn->prepare("SELECT username, full_name, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    $error_message = "User not found";
    $user = [
        'username' => '',
        'full_name' => '',
        'email' => '',
        'role' => ''
    ];
}

$stmt->close();
?>

<!-- Main Content -->
<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <strong>Success!</strong> <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <strong>Error!</strong> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Profile Summary Card -->
        <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
            <div class="flex flex-col items-center text-center">
                <div class="w-24 h-24 mb-3 rounded-full bg-blue-600 flex items-center justify-center">
                    <span class="text-white text-3xl font-bold">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($user['full_name']) ?></h2>
                <p class="text-sm text-gray-500 mt-1 capitalize"><?= htmlspecialchars($user['role']) ?></p>
                <p class="mt-4 text-sm text-gray-600">
                    <i class="fas fa-envelope mr-2"></i> <?= htmlspecialchars($user['email']) ?>
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    <i class="fas fa-user mr-2"></i> <?= htmlspecialchars($user['username']) ?>
                </p>
                
                <div class="mt-6 border-t border-gray-200 pt-4 w-full">
                    <div class="flex items-center justify-center">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                            Account active
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profile Edit Form -->
        <div class="md:col-span-2">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Edit Profile</h3>
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-100" 
                               disabled>
                        <p class="mt-1 text-xs text-gray-500">Username cannot be changed</p>
                    </div>
                    
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               required>
                    </div>
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                           required>
                </div>
                
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-lg font-medium text-gray-800 mb-3">Change Password</h4>
                    <p class="text-sm text-gray-600 mb-4">Leave the fields below empty if you don't want to change your password</p>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button type="submit" name="update_profile" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Recent Activity Section (optional - can be expanded in future) -->
    <div class="mt-8 border-t border-gray-200 pt-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Activity</h3>
        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-center">
            <p class="text-gray-500">Activity tracking will be available in a future update.</p>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>