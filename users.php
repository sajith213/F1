<?php
// Move session_start(), header(), or setcookie() calls to the very top of the file
ob_start();
session_start();

// Any other header modifications

// Then include your sidebar

// Rest of your code
?>
<?php
/**
 * User Management
 * 
 * This file handles the management of system users including:
 * - Viewing all users
 * - Adding new users
 * - Editing existing users
 * - Activating/deactivating users
 * - Changing user roles
 */

// Set page title
$page_title = "User Management";

// Include necessary files
require_once 'includes/header.php';
require_once 'includes/db.php';

// Check if user has admin permission
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access the user management system.</p>
          </div>';
    include 'includes/footer.php';
    exit;
}

// Define action types
$actions = ['list', 'add', 'edit', 'delete', 'status'];
$action = isset($_GET['action']) && in_array($_GET['action'], $actions) ? $_GET['action'] : 'list';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // Validate inputs
        $errors = [];
        
        // Username validation
        if (empty($username)) {
            $errors[] = "Username is required";
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Username already exists";
            }
            $stmt->close();
        }
        
        // Password validation
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        // Email validation
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $stmt->close();
        }
        
        // Full name validation
        if (empty($full_name)) {
            $errors[] = "Full name is required";
        }
        
        // If no errors, insert the new user
        if (empty($errors)) {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);
            
            if ($stmt->execute()) {
                $message = "User added successfully";
                $message_type = "success";
                // Redirect to user list to avoid form resubmission
                header("Location: users.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
                exit;
            } else {
                $message = "Error adding user: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Please correct the following errors:<br>" . implode("<br>", $errors);
            $message_type = "error";
        }
    }
    
    // Edit existing user
    elseif (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validate inputs
        $errors = [];
        
        // Username validation
        if (empty($username)) {
            $errors[] = "Username is required";
        } else {
            // Check if username already exists (excluding current user)
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Username already exists";
            }
            $stmt->close();
        }
        
        // Email validation
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if email already exists (excluding current user)
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $stmt->close();
        }
        
        // Full name validation
        if (empty($full_name)) {
            $errors[] = "Full name is required";
        }
        
        // Password validation (only if provided)
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $errors[] = "Password must be at least 6 characters";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "Passwords do not match";
            }
        }
        
        // If no errors, update the user
        if (empty($errors)) {
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ? WHERE user_id = ?");
                $stmt->bind_param("sssssi", $username, $hashed_password, $full_name, $email, $role, $user_id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $username, $full_name, $email, $role, $user_id);
            }
            
            if ($stmt->execute()) {
                $message = "User updated successfully";
                $message_type = "success";
                // Redirect to user list
                header("Location: users.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
                exit;
            } else {
                $message = "Error updating user: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Please correct the following errors:<br>" . implode("<br>", $errors);
            $message_type = "error";
        }
    }
}

// Handle GET actions
if ($action === 'status' && $user_id > 0) {
    $new_status = isset($_GET['status']) && $_GET['status'] === 'active' ? 'active' : 'inactive';
    
    // Don't allow deactivating your own account
    if ($user_id === $_SESSION['user_id'] && $new_status === 'inactive') {
        $message = "You cannot deactivate your own account";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        
        if ($stmt->execute()) {
            $message = "User status updated successfully";
            $message_type = "success";
        } else {
            $message = "Error updating user status: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    
    // Redirect to avoid resubmission
    header("Location: users.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit;
}

// Handle message from redirects
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $message_type = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// Fetch all users for listing
$all_users = [];
if ($action === 'list') {
    $sql = "SELECT user_id, username, full_name, email, role, status, created_at FROM users ORDER BY full_name";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
}

// Fetch single user for editing
$user_data = null;
if ($action === 'edit' && $user_id > 0) {
    $stmt = $conn->prepare("SELECT user_id, username, full_name, email, role, status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
    } else {
        $message = "User not found";
        $message_type = "error";
        $action = 'list'; // Fallback to list
    }
    
    $stmt->close();
}

// Display appropriate alert message if set
if (!empty($message)) {
    $alert_class = $message_type === 'success' ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700';
    echo "<div class=\"{$alert_class} border-l-4 p-4 mb-4\" role=\"alert\">
            <p>" . htmlspecialchars($message) . "</p>
          </div>";
}
?>

<!-- Action buttons -->
<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
    
    <?php if ($action === 'list'): ?>
    <a href="?action=add" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <i class="fas fa-user-plus mr-2"></i> Add New User
    </a>
    <?php elseif ($action !== 'list'): ?>
    <a href="?action=list" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
        <i class="fas fa-chevron-left mr-2"></i> Back to Users
    </a>
    <?php endif; ?>
</div>

<!-- User List -->
<?php if ($action === 'list'): ?>
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($all_users)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">No users found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($all_users as $user): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($user['username']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($user['full_name']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($user['email'] ?? '') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                            <?php
                            switch ($user['role']) {
                                case 'admin':
                                    echo 'bg-purple-100 text-purple-800';
                                    break;
                                case 'manager':
                                    echo 'bg-blue-100 text-blue-800';
                                    break;
                                case 'cashier':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'attendant':
                                    echo 'bg-yellow-100 text-yellow-800';
                                    break;
                                default:
                                    echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?= ucfirst(htmlspecialchars($user['role'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="?action=edit&id=<?= $user['user_id'] ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        
                        <?php if ($user['user_id'] !== $_SESSION['user_id']): // Can't change your own status ?>
                            <?php if ($user['status'] === 'active'): ?>
                                <a href="?action=status&id=<?= $user['user_id'] ?>&status=inactive" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to deactivate this user?')">
                                    <i class="fas fa-ban"></i> Deactivate
                                </a>
                            <?php else: ?>
                                <a href="?action=status&id=<?= $user['user_id'] ?>&status=active" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-check-circle"></i> Activate
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Form -->
<?php elseif ($action === 'add'): ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-medium text-gray-900 mb-4">Add New User</h2>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" id="username" required class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" id="full_name" required class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="email" required class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                <select name="role" id="role" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="cashier">Cashier</option>
                    <option value="attendant">Attendant</option>
                </select>
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" id="password" required minlength="6" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <p class="mt-1 text-xs text-gray-500">Password must be at least 6 characters</p>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" id="confirm_password" required minlength="6" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <a href="?action=list" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 mr-2">
                Cancel
            </a>
            <button type="submit" name="add_user" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Create User
            </button>
        </div>
    </form>
</div>

<!-- Edit User Form -->
<?php elseif ($action === 'edit' && $user_data): ?>
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-medium text-gray-900 mb-4">Edit User: <?= htmlspecialchars($user_data['username']) ?></h2>
    
    <form method="POST" action="">
        <input type="hidden" name="user_id" value="<?= $user_data['user_id'] ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" id="username" required value="<?= htmlspecialchars($user_data['username']) ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" id="full_name" required value="<?= htmlspecialchars($user_data['full_name']) ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="email" required value="<?= htmlspecialchars($user_data['email']) ?>" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                <select name="role" id="role" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="admin" <?= $user_data['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="manager" <?= $user_data['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="cashier" <?= $user_data['role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                    <option value="attendant" <?= $user_data['role'] === 'attendant' ? 'selected' : '' ?>>Attendant</option>
                </select>
            </div>
            
            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" name="new_password" id="new_password" minlength="6" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <p class="mt-1 text-xs text-gray-500">Leave blank to keep current password</p>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" minlength="6" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
            </div>
        </div>
        
        <div class="mt-6 flex justify-end">
            <a href="?action=list" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 mr-2">
                Cancel
            </a>
            <button type="submit" name="edit_user" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Update User
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Password strength validation -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password matching validation
    const passwordField = document.getElementById('password') || document.getElementById('new_password');
    const confirmField = document.getElementById('confirm_password');
    
    if (passwordField && confirmField) {
        confirmField.addEventListener('input', function() {
            if (passwordField.value !== this.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        passwordField.addEventListener('input', function() {
            if (confirmField.value !== '' && confirmField.value !== this.value) {
                confirmField.setCustomValidity('Passwords do not match');
            } else {
                confirmField.setCustomValidity('');
            }
        });
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
ob_end_flush();
?>