<?php
ob_start();
/**
 * Add New Staff
 * 
 * Form for adding a new staff member
 */

// Set page title and load header
$page_title = "Add New Staff";
$breadcrumbs = "<a href='../../index.php'>Home</a> / <a href='index.php'>Staff Management</a> / Add New Staff";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $error_messages = [];
    
    // Required fields
    $required_fields = [
        'staff_code' => 'Staff Code', 
        'first_name' => 'First Name', 
        'last_name' => 'Last Name',
        'gender' => 'Gender',
        'hire_date' => 'Hire Date',
        'position' => 'Position',
        'phone' => 'Phone Number'
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
    
    // Check if staff code already exists
    if (!empty($_POST['staff_code'])) {
        $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE staff_code = ?");
        $stmt->bind_param("s", $_POST['staff_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_messages[] = "Staff code already exists. Please use a different code.";
        }
    }
    
    // If no errors, proceed with adding the staff
    if (empty($error_messages)) {
        $staff_data = [
            'staff_code' => $_POST['staff_code'],
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'gender' => $_POST['gender'],
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'hire_date' => $_POST['hire_date'],
            'position' => $_POST['position'],
            'department' => $_POST['department'] ?? null,
            'phone' => $_POST['phone'],
            'email' => $_POST['email'] ?? null,
            'address' => $_POST['address'] ?? null,
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'notes' => $_POST['notes'] ?? null
        ];
        
        // Check if a user account should be created
        $staff_data['create_account'] = isset($_POST['create_user_account']) ? 'yes' : 'no';
        
        if (isset($_POST['create_user_account'])) {
            $staff_data['role'] = $_POST['user_role'] ?? 'attendant';
        }
        
        $result = addStaff($conn, $staff_data);
        
        if ($result) {
            // Success message with temporary password if account was created
            $success_message = "Staff added successfully.";
            
            if (isset($staff_data['temp_password'])) {
                $success_message .= " A user account has been created with username: " . 
                    strtolower(substr($staff_data['first_name'], 0, 1) . $staff_data['last_name']) . 
                    " and temporary password: " . $staff_data['temp_password'] . 
                    ". Please share these credentials with the staff member.";
            }
            
            $_SESSION['success_message'] = $success_message;
            header("Location: index.php");
            exit;
        } else {
            $error_messages[] = "Error adding staff. Please try again.";
        }
    }
}

// Generate a new staff code
$new_staff_code = generateStaffCode($conn);
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

<!-- Add Staff Form -->
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold text-gray-800 mb-6">Add New Staff Member</h2>
    
    <form action="add_staff.php" method="post" autocomplete="off">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left Column -->
            <div>
                <h3 class="text-lg font-medium text-gray-700 mb-4">Basic Information</h3>
                
                <!-- Staff Code -->
                <div class="mb-4">
                    <label for="staff_code" class="block text-sm font-medium text-gray-700 mb-1">Staff Code*</label>
                    <input type="text" id="staff_code" name="staff_code" value="<?= htmlspecialchars($new_staff_code) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- First Name -->
                <div class="mb-4">
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name*</label>
                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Last Name -->
                <div class="mb-4">
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name*</label>
                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Gender -->
                <div class="mb-4">
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">Gender*</label>
                    <select id="gender" name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Gender</option>
                        <option value="male" <?= isset($_POST['gender']) && $_POST['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= isset($_POST['gender']) && $_POST['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= isset($_POST['gender']) && $_POST['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <!-- Date of Birth -->
                <div class="mb-4">
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Hire Date -->
                <div class="mb-4">
                    <label for="hire_date" class="block text-sm font-medium text-gray-700 mb-1">Hire Date*</label>
                    <input type="date" id="hire_date" name="hire_date" value="<?= htmlspecialchars($_POST['hire_date'] ?? date('Y-m-d')) ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Position -->
                <div class="mb-4">
                    <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position*</label>
                    <input type="text" id="position" name="position" value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Department -->
                <div class="mb-4">
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input type="text" id="department" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <h3 class="text-lg font-medium text-gray-700 mb-4">Contact Information</h3>
                
                <!-- Phone -->
                <div class="mb-4">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number*</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Address -->
                <div class="mb-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="address" name="address" rows="3" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                
                <h3 class="text-lg font-medium text-gray-700 mb-4 mt-6">Emergency Contact</h3>
                
                <!-- Emergency Contact Name -->
                <div class="mb-4">
                    <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                    <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= htmlspecialchars($_POST['emergency_contact_name'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <!-- Emergency Contact Phone -->
                <div class="mb-4">
                    <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Phone</label>
                    <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= htmlspecialchars($_POST['emergency_contact_phone'] ?? '') ?>" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <h3 class="text-lg font-medium text-gray-700 mb-4 mt-6">User Account</h3>
                
                <!-- Create User Account -->
                <div class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="create_user_account" name="create_user_account" 
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            <?= isset($_POST['create_user_account']) ? 'checked' : '' ?>>
                        <label for="create_user_account" class="ml-2 block text-sm text-gray-700">
                            Create user account for this staff member
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        A username and temporary password will be generated automatically.
                    </p>
                </div>
                
                <!-- User Role -->
                <div class="mb-4" id="user_role_div" style="<?= isset($_POST['create_user_account']) ? '' : 'display: none;' ?>">
                    <label for="user_role" class="block text-sm font-medium text-gray-700 mb-1">User Role</label>
                    <select id="user_role" name="user_role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="attendant" <?= isset($_POST['user_role']) && $_POST['user_role'] === 'attendant' ? 'selected' : '' ?>>Attendant</option>
                        <option value="cashier" <?= isset($_POST['user_role']) && $_POST['user_role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                        <option value="manager" <?= isset($_POST['user_role']) && $_POST['user_role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="admin" <?= isset($_POST['user_role']) && $_POST['user_role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Notes -->
        <div class="mt-6">
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>
        
        <!-- Submit Button -->
        <div class="mt-8 flex justify-end">
            <a href="index.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 mr-4 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Add Staff
            </button>
        </div>
    </form>
</div>

<!-- JavaScript for form interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide user role dropdown based on checkbox
    const createAccountCheckbox = document.getElementById('create_user_account');
    const userRoleDiv = document.getElementById('user_role_div');
    
    createAccountCheckbox.addEventListener('change', function() {
        if (this.checked) {
            userRoleDiv.style.display = 'block';
        } else {
            userRoleDiv.style.display = 'none';
        }
    });
});
</script>

<?php
// Include the footer
require_once '../../includes/footer.php';

ob_end_flush();
?>