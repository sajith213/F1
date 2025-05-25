<?php
/**
 * Credit Management - Edit Credit Customer
 * 
 * This file provides a form to edit an existing credit customer
 */

// Set page title and include header
$page_title = "Edit Credit Customer";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="credit_customers.php">Credit Customers</a> / <span class="text-gray-700">Edit Customer</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize variables
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;
$errors = [];

// Check if customer ID is valid
if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID.";
    header("Location: credit_customers.php");
    exit;
}

// Fetch the customer data
$stmt = $conn->prepare("
    SELECT * FROM credit_customers 
    WHERE customer_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Customer not found.";
        header("Location: credit_customers.php");
        exit;
    }
    
    $customer = $result->fetch_assoc();
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    header("Location: credit_customers.php");
    exit;
}

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
    
    // Check if another customer with same phone exists (excluding current customer)
    $stmt = $conn->prepare("
        SELECT customer_id FROM credit_customers 
        WHERE phone_number = ? AND customer_id != ?
    ");
    
    if ($stmt) {
        $stmt->bind_param("si", $phone_number, $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "Another customer with this phone number already exists";
        }
        
        $stmt->close();
    }
    
    // Check if lowering credit limit below current balance
    if ($credit_limit < $customer['current_balance']) {
        $errors[] = "Cannot set credit limit below current balance (₹" . number_format($customer['current_balance'], 2) . ")";
    }
    
    // If no errors, update the customer
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Update customer record
            $stmt = $conn->prepare("
                UPDATE credit_customers SET 
                    customer_name = ?,
                    phone_number = ?,
                    address = ?,
                    email = ?,
                    credit_limit = ?,
                    status = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE customer_id = ?
            ");
            
            $stmt->bind_param(
                "sssdssis",
                $customer_name, $phone_number, $address, $email,
                $credit_limit, $status, $notes, $customer_id
            );
            
            if ($stmt->execute()) {
                $stmt->close();
                
                // Check if credit limit was changed
                $limit_changed = $credit_limit != $customer['credit_limit'];
                
                // Log the customer update with meaningful details
                $log_details = [];
                if ($customer_name != $customer['customer_name']) {
                    $log_details[] = "name from '{$customer['customer_name']}' to '{$customer_name}'";
                }
                if ($phone_number != $customer['phone_number']) {
                    $log_details[] = "phone from '{$customer['phone_number']}' to '{$phone_number}'";
                }
                if ($email != $customer['email']) {
                    $log_details[] = "email from '{$customer['email']}' to '{$email}'";
                }
                if ($limit_changed) {
                    $log_details[] = "credit limit from '" . number_format($customer['credit_limit'], 2) . "' to '" . number_format($credit_limit, 2) . "'";
                }
                if ($status != $customer['status']) {
                    $log_details[] = "status from '{$customer['status']}' to '{$status}'";
                }
                
                $log_detail_text = !empty($log_details) ? " (Changed " . implode(", ", $log_details) . ")" : "";
                $log_message = "Updated credit customer: $customer_name" . $log_detail_text;
                
                $stmt = $conn->prepare("
                    INSERT INTO activity_log (
                        user_id, activity_type, description, entity_id, entity_type
                    ) VALUES (?, 'update', ?, ?, 'credit_customer')
                ");
                if ($stmt) {
                    $user_id = $_SESSION['user_id'];
                    $stmt->bind_param("isi", $user_id, $log_message, $customer_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // If credit limit was increased, log it separately for financial auditing
                if ($limit_changed && $credit_limit > $customer['credit_limit']) {
                    $limit_change = $credit_limit - $customer['credit_limit'];
                    $limit_note = "Increased credit limit by " . number_format($limit_change, 2) . " for customer: $customer_name";
                    
                    $stmt = $conn->prepare("
                        INSERT INTO activity_log (
                            user_id, activity_type, description, entity_id, entity_type
                        ) VALUES (?, 'credit_limit_change', ?, ?, 'credit_customer')
                    ");
                    if ($stmt) {
                        $user_id = $_SESSION['user_id'];
                        $stmt->bind_param("isi", $user_id, $limit_note, $customer_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $conn->commit();
                
                // Set success message and redirect
                $_SESSION['success_message'] = "Customer updated successfully";
                header("Location: credit_customers.php");
                exit;
            } else {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error updating customer: " . $e->getMessage();
        }
    }
}

// Pre-populate form with existing customer data for GET requests
// or form values for POST requests that had errors
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $form_data = $customer;
} else {
    $form_data = $_POST;
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
                <h3 class="text-xl font-semibold text-gray-800">Edit Credit Customer</h3>
                <div>
                    <a href="view_credit_customer.php?id=<?= $customer_id ?>" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 mr-2">
                        <i class="fas fa-eye mr-1"></i> View Details
                    </a>
                    <a href="credit_customers.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>
                </div>
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
                           value="<?= htmlspecialchars($form_data['customer_name'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="Enter customer name">
                </div>
                
                <!-- Phone Number -->
                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">
                        Phone Number <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="phone_number" id="phone_number" required
                           value="<?= htmlspecialchars($form_data['phone_number'] ?? '') ?>"
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                           placeholder="e.g. 123-456-7890">
                </div>
                
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email (Optional)
                    </label>
                    <input type="email" name="email" id="email"
                           value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
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
                               value="<?= htmlspecialchars($form_data['credit_limit'] ?? '') ?>"
                               min="<?= $customer['current_balance'] ?>" step="0.01"
                               class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md"
                               placeholder="0.00">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Current Balance: <?= $currency_symbol ?> <?= number_format($customer['current_balance'], 2) ?>
                        (Cannot set limit below current balance)
                    </p>
                </div>
                
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">
                        Status <span class="text-red-600">*</span>
                    </label>
                    <select name="status" id="status" required
                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="active" <?= ($form_data['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($form_data['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="blocked" <?= ($form_data['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>
                
                <!-- Customer Info (Read-only) -->
                <div>
                    <div class="bg-gray-50 rounded-md p-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Customer Information</h4>
                        <div class="text-sm">
                            <p><span class="font-medium">Customer ID:</span> <?= $customer['customer_id'] ?></p>
                            <p><span class="font-medium">Created:</span> <?= date('M d, Y', strtotime($customer['created_at'])) ?></p>
                            <p><span class="font-medium">Last Updated:</span> <?= date('M d, Y', strtotime($customer['updated_at'])) ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Address field spans both columns -->
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
                        Address
                    </label>
                    <textarea name="address" id="address" rows="2"
                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                              placeholder="Customer address"><?= htmlspecialchars($form_data['address'] ?? '') ?></textarea>
                </div>
                
                <!-- Notes field spans both columns -->
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>
                    <textarea name="notes" id="notes" rows="3"
                              class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                              placeholder="Additional notes about this customer"><?= htmlspecialchars($form_data['notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <a href="credit_customers.php" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Update Customer
                </button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>