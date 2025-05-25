<?php
/**
 * Update Staff
 * 
 * Form for updating an existing staff member
 */
ob_start();
// Set page title and load header
$page_title = "Update Staff";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check if staff ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Staff ID is required.";
    header("Location: index.php");
    exit;
}

$staff_id = (int)$_GET['id'];
$staff = getStaffById($conn, $staff_id);

// If staff not found, redirect to staff list
if (!$staff) {
    $_SESSION['error_message'] = "Staff member not found.";
    header("Location: index.php");
    exit;
}

// Update breadcrumbs
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Staff Management</a> / " . 
               "<a href='staff_details.php?id={$staff_id}'>" . htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) . "</a> / Update";

// Get user info if this staff has a user account
$user_info = null;
if (!empty($staff['user_id'])) {
    $stmt = $conn->prepare("SELECT username, role, status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $staff['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_info = $result->fetch_assoc();
    }
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $error_messages = [];
    
    // Required fields
    $required_fields = [
        'first_name' => 'First Name', 
        'last_name' => 'Last Name',
        'gender' => 'Gender',
        'position' => 'Position',
        'phone' => 'Phone Number',
        'status' => 'Status'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $error_messages[] = "$label is required.";
        }
    }
    
    // Email validation
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error_messages[] = "Please enter a valid email address.";
    }
    
    // Phone validation
    if (!empty($_POST['phone']) && !preg_match('/^[0-9\s\(\)\-\+]{7,15}$/', $_POST['phone'])) {
        $error_messages[] = "Please enter a valid phone number.";
    }
    
    // If no errors, proceed with updating the staff
    if (empty($error_messages)) {
        $staff_data = [
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'gender' => $_POST['gender'],
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'position' => $_POST['position'],
            'department' => $_POST['department'] ?? null,
            'phone' => $_POST['phone'],
            'email' => $_POST['email'] ?? null,
            'address' => $_POST['address'] ?? null,
            'status' => $_POST['status'],
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'notes' => $_POST['notes'] ?? null
        ];
        
        // Add user_id if exists
        if (!empty($staff['user_id'])) {
            $staff_data['user_id'] = $staff['user_id'];
        }
        
        $result = updateStaff($conn, $staff_id, $staff_data);
        
        if ($result) {
            $_SESSION['success_message'] = "Staff updated successfully.";
            header("Location: staff_details.php?id=$staff_id");
            exit;
        } else {
            $error_messages[] = "Error updating staff. Please try again.";
        }
    }
}
?>

<!-- Notification Messages -->
<?php if (!empty($error_messages)): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
    <ul class="list-disc pl-5">
        <?php foreach ($error_messages as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Update Staff Form -->
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-6">Update Staff Member</h2>
    
    <form action="update_staff.php?id=<?= $staff_id ?>" method="post">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div>
                <h3 class="text-lg font-medium text-gray-700 mb-4">Basic Information</h3>
                
                <!-- Staff Code (read-only) -->
                <div class="mb-4">
                    <label for="staff_code" class="block text-sm font-medium text-gray-700 mb-1">Staff Code</label>
                    <input type="text" id="staff_code" value="<?= htmlspecialchars($staff['staff_code']) ?>" 
                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" readonly>
                </div>
                
                <!-- First Name -->
                <div class="mb-4">
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name*</label>
                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? $staff['first_name']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Last Name -->
                <div class="mb-4">
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name*</label>
                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? $staff['last_name']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Gender -->
                <div class="mb-4">
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender*</label>
                    <select id="gender" name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Gender</option>
                        <option value="male" <?= (isset($_POST['gender']) && $_POST['gender'] === 'male') || (!isset($_POST['gender']) && $staff['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= (isset($_POST['gender']) && $_POST['gender'] === 'female') || (!isset($_POST['gender']) && $staff['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= (isset($_POST['gender']) && $_POST['gender'] === 'other') || (!isset($_POST['gender']) && $staff['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <!-- Date of Birth -->
                <div class="mb-4">
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? $staff['date_of_birth']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Hire Date (read-only) -->
                <div class="mb-4">
                    <label for="hire_date" class="block text-sm font-medium text-gray-700 mb-1">Hire Date</label>
                    <input type="date" id="hire_date" value="<?= htmlspecialchars($staff['hire_date']) ?>" 
                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" readonly>
                </div>
                
                <!-- Position -->
                <div class="mb-4">
                    <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position*</label>
                    <input type="text" id="position" name="position" value="<?= htmlspecialchars($_POST['position'] ?? $staff['position']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Department -->
                <div class="mb-4">
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input type="text" id="department" name="department" value="<?= htmlspecialchars($_POST['department'] ?? $staff['department']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Status -->
                <div class="mb-4">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status*</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="active" <?= (isset($_POST['status']) && $_POST['status'] === 'active') || (!isset($_POST['status']) && $staff['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (isset($_POST['status']) && $_POST['status'] === 'inactive') || (!isset($_POST['status']) && $staff['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        <option value="on_leave" <?= (isset($_POST['status']) && $_POST['status'] === 'on_leave') || (!isset($_POST['status']) && $staff['status'] === 'on_leave') ? 'selected' : '' ?>>On Leave</option>
                        <option value="terminated" <?= (isset($_POST['status']) && $_POST['status'] === 'terminated') || (!isset($_POST['status']) && $staff['status'] === 'terminated') ? 'selected' : '' ?>>Terminated</option>
                    </select>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <h3 class="text-lg font-medium text-gray-700 mb-4">Contact Information</h3>
                
                <!-- Phone -->
                <div class="mb-4">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number*</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $staff['phone']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $staff['email']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Address -->
                <div class="mb-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="address" name="address" rows="3" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($_POST['address'] ?? $staff['address']) ?></textarea>
                </div>
                
                <h3 class="text-lg font-medium text-gray-700 mb-4 mt-6">Emergency Contact</h3>
                
                <!-- Emergency Contact Name -->
                <div class="mb-4">
                    <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? $staff['emergency_contact_name']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Emergency Contact Phone -->
                <div class="mb-4">
                    <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Phone</label>
                    <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= htmlspecialchars($_POST['emergency_contact_phone'] ?? $staff['emergency_contact_phone']) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- User Account Info (read-only) -->
                <?php if ($user_info): ?>
                <h3 class="text-lg font-medium text-gray-700 mb-4 mt-6">User Account</h3>
                
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="username" value="<?= htmlspecialchars($user_info['username']) ?>" 
                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" readonly>
                    <p class="text-xs text-gray-500 mt-1">
                        To update user account settings, please use the User Management module.
                    </p>
                </div>
                
                <div class="mb-4">
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <input type="text" id="role" value="<?= ucfirst(htmlspecialchars($user_info['role'])) ?>" 
                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" readonly>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notes -->
        <div class="mt-6">
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($_POST['notes'] ?? $staff['notes']) ?></textarea>
        </div>
        
        <!-- Submit Button -->
        <div class="mt-8 flex justify-end">
            <a href="staff_details.php?id=<?= $staff_id ?>" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 mr-4 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Update Staff
            </button>
        </div>
    </form>
</div>

<?php
// Include the footer
require_once '../../includes/footer.php';

ob_end_flush();
?>