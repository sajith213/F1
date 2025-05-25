<?php
// Add this at the very top of your file - before ANYTHING else
ob_start();

/**
 * Credit Management - Add New Credit Customer
 * 
 * This file provides a form to add a new credit customer
 */

// Set page title and include header
$page_title = "Add New Credit Customer";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="credit_customers.php">Credit Customers</a> / <span class="text-gray-700">Add New Customer</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize error and success messages
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $customer_name = trim($_POST['customer_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $credit_limit = (float)($_POST['credit_limit'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate form data
    if (empty($customer_name)) {
        $errors[] = "Customer name is required";
    }
    
    if (empty($phone_number)) {
        $errors[] = "Phone number is required";
    }
    
    if ($credit_limit <= 0) {
        $errors[] = "Credit limit must be greater than zero";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if customer with same phone already exists
    $stmt = $conn->prepare("SELECT customer_id FROM credit_customers WHERE phone_number = ?");
    if ($stmt) {
        $stmt->bind_param("s", $phone_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "A customer with this phone number already exists";
        }
        
        $stmt->close();
    }
    
    // If no errors, insert the customer
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Insert customer record
            $stmt = $conn->prepare("
                INSERT INTO credit_customers (
                    customer_name, phone_number, address, email, 
                    credit_limit, current_balance, status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
            ");
            
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param(
                "ssssdsis",
                $customer_name, $phone_number, $address, $email,
                $credit_limit, $status, $notes, $user_id
            );
            
            if ($stmt->execute()) {
                $customer_id = $conn->insert_id;
                $stmt->close();
                
                // Check if activity_log table exists before trying to log
                $table_exists = false;
                $result = $conn->query("SHOW TABLES LIKE 'activity_log'");
                if ($result && $result->num_rows > 0) {
                    $table_exists = true;
                }
                
                // Only try to log if the table exists
                if ($table_exists) {
                    // Log the customer creation
                    $log_message = "Created credit customer: $customer_name";
                    $stmt = $conn->prepare("
                        INSERT INTO activity_log (
                            user_id, activity_type, description, entity_id, entity_type
                        ) VALUES (?, 'create', ?, ?, 'credit_customer')
                    ");
                    if ($stmt) {
                        $stmt->bind_param("isi", $user_id, $log_message, $customer_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $conn->commit();
                
                // Set success message and redirect
                $_SESSION['success_message'] = "Credit customer added successfully";
                header("Location: credit_customers.php");
                exit;
            } else {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error adding customer: " . $e->getMessage();
        }
    }
}

// Get currency symbol from settings
$currency_symbol = '$';
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currency_symbol = $row['setting_value'];
    }
    $stmt->close();
}
?>

<div class="container mx-auto pb-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-800">Add New Credit Customer</h3>
                <a href="credit_customers.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Customers
                </a>
            </div>
        </div>
        
        <form method="POST" action="" class="p-6">
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Please fix the following errors:</strong>
                <ul class="mt-1 ml-4 list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Customer Name -->
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Customer Name <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="customer_name" id="customer_name" required
                           value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="Enter customer name">
                </div>
                
                <!-- Phone Number -->
                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">
                        Phone Number <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="phone_number" id="phone_number" required
                           value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="e.g. 123-456-7890">
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email (Optional)
                    </label>
                    <input type="email" name="email" id="email"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="customer@example.com">
                </div>
                
                <!-- Credit Limit -->
                <div>
                    <label for="credit_limit" class="block text-sm font-medium text-gray-700 mb-1">
                        Credit Limit <span class="text-red-600">*</span>
                    </label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                        </div>
                        <input type="number" name="credit_limit" id="credit_limit" required
                               value="<?= htmlspecialchars($_POST['credit_limit'] ?? '5000') ?>"
                               min="0" step="0.01"
                               class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md"
                               placeholder="0.00">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Maximum credit amount allowed for this customer</p>
                </div>
                
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                        Status <span class="text-red-600">*</span>
                    </label>
                    <select name="status" id="status" required
                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="active" <?= isset($_POST['status']) && $_POST['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= isset($_POST['status']) && $_POST['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="blocked" <?= isset($_POST['status']) && $_POST['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>
                
                <!-- Address field spans both columns -->
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                        Address
                    </label>
                    <textarea name="address" id="address" rows="2"
                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                              placeholder="Customer address"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                
                <!-- Notes field spans both columns -->
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                              placeholder="Additional notes about this customer"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <a href="credit_customers.php" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Customer
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
include_once '../../includes/footer.php'; 
// End output buffering and flush the output
ob_end_flush();
?>