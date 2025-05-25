<?php
/**
 * Petty Cash Management - Edit Transaction
 * 
 * Edit an existing petty cash transaction
 */

// Set page title
$page_title = "Edit Petty Cash Transaction";
$breadcrumbs = "Home > Finance > Petty Cash > Edit Transaction";

// Include header
include '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';
// Include auth functions
require_once '../../includes/auth.php';

// Check permission
if (!has_permission('manage_petty_cash')) {
    // Redirect to dashboard or show error
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
    echo '<p>You do not have permission to access this module.</p>';
    echo '</div>';
    include '../../includes/footer.php';
    exit;
}

// Initialize variables
$categories = [];
$error_message = '';
$success_message = '';
$transaction = null;
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if transaction exists
if ($transaction_id <= 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
    echo '<p>Invalid transaction ID. <a href="index.php" class="font-semibold underline">Go back to transactions</a></p>';
    echo '</div>';
    include '../../includes/footer.php';
    exit;
}

// Only fetch data if database connection is successful
if (isset($conn) && $conn) {
    try {
        // Get transaction data
        $stmt = $conn->prepare("SELECT * FROM petty_cash WHERE transaction_id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
            
            // Check if user has permissions to edit this transaction
            $current_user_id = get_current_user_id();
            $is_admin = has_permission('admin_petty_cash');
            
            // Only allow editing if the transaction is still pending or user is admin
            if ($transaction['status'] !== 'pending' && !$is_admin) {
                echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
                echo '<p>This transaction cannot be edited because it has already been processed. <a href="index.php" class="font-semibold underline">Go back to transactions</a></p>';
                echo '</div>';
                include '../../includes/footer.php';
                exit;
            }
        } else {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
            echo '<p>Transaction not found. <a href="index.php" class="font-semibold underline">Go back to transactions</a></p>';
            echo '</div>';
            include '../../includes/footer.php';
            exit;
        }
        
        $stmt->close();
        
        // Get categories
        $query = "SELECT category_id, category_name, type FROM petty_cash_categories WHERE status = 'active' ORDER BY type, category_name";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
    } catch (Exception $e) {
        // Log error
        error_log("Petty cash transaction fetch error: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data = [
        'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
        'amount' => $_POST['amount'] ?? '',
        'type' => $_POST['type'] ?? 'expense',
        'category_id' => $_POST['category_id'] ?? '',
        'description' => $_POST['description'] ?? '',
        'reference_no' => $_POST['reference_no'] ?? '',
        'payment_method' => $_POST['payment_method'] ?? 'cash',
        'status' => $_POST['status'] ?? 'pending'
    ];
    
    // Validate form data
    $errors = [];
    
    if (empty($form_data['transaction_date'])) {
        $errors[] = "Transaction date is required";
    }
    
    if (empty($form_data['amount']) || !is_numeric($form_data['amount']) || $form_data['amount'] <= 0) {
        $errors[] = "Valid amount is required";
    }
    
    if (empty($form_data['category_id'])) {
        $errors[] = "Category is required";
    }
    
    if (empty($form_data['type']) || !in_array($form_data['type'], ['income', 'expense'])) {
        $errors[] = "Valid transaction type is required";
    }
    
    if (empty($form_data['payment_method']) || !in_array($form_data['payment_method'], ['cash', 'card', 'bank_transfer'])) {
        $errors[] = "Valid payment method is required";
    }
    
    if (empty($form_data['status']) || !in_array($form_data['status'], ['pending', 'approved', 'rejected'])) {
        $errors[] = "Valid status is required";
    }
    
    // If no errors, proceed with database update
    if (empty($errors)) {
        try {
            // Prepare query
            $query = "UPDATE petty_cash SET 
                        transaction_date = ?, 
                        amount = ?, 
                        type = ?, 
                        category_id = ?, 
                        description = ?, 
                        reference_no = ?, 
                        payment_method = ?,
                        status = ?,
                        updated_at = NOW()
                      WHERE transaction_id = ?";
            
            $stmt = $conn->prepare($query);
            
            // Bind parameters
            $stmt->bind_param(
                "sdsissssi",
                $form_data['transaction_date'],
                $form_data['amount'],
                $form_data['type'],
                $form_data['category_id'],
                $form_data['description'],
                $form_data['reference_no'],
                $form_data['payment_method'],
                $form_data['status'],
                $transaction_id
            );
            
            // Execute query
            if ($stmt->execute()) {
                // If status changed to approved, update the approved_by field
                if ($form_data['status'] === 'approved' && $transaction['status'] !== 'approved') {
                    $user_id = get_current_user_id();
                    $approve_stmt = $conn->prepare("UPDATE petty_cash SET approved_by = ? WHERE transaction_id = ?");
                    $approve_stmt->bind_param("ii", $user_id, $transaction_id);
                    $approve_stmt->execute();
                    $approve_stmt->close();
                }
                
                // Set success message
                $success_message = "Transaction updated successfully!";
                
                // Refresh transaction data
                $refresh_stmt = $conn->prepare("SELECT * FROM petty_cash WHERE transaction_id = ?");
                $refresh_stmt->bind_param("i", $transaction_id);
                $refresh_stmt->execute();
                $result = $refresh_stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $transaction = $result->fetch_assoc();
                }
                $refresh_stmt->close();
            } else {
                $error_message = "Error updating transaction: " . $stmt->error;
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
            error_log("Petty cash update error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!-- Page Content -->
<div class="mb-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4">
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0">Edit Petty Cash Transaction</h2>
        <div class="flex flex-wrap gap-2">
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
            </a>
            <a href="view_transaction.php?id=<?= $transaction_id ?>" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                <i class="fas fa-eye mr-2"></i> View Transaction
            </a>
        </div>
    </div>
    
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= $success_message ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?= $error_message ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Transaction Form -->
    <div class="bg-white rounded-lg shadow p-6">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Transaction Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="expense" class="form-radio text-blue-600" 
                                   <?= $transaction['type'] === 'expense' ? 'checked' : '' ?> required>
                            <span class="ml-2">Expense</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="type" value="income" class="form-radio text-blue-600" 
                                   <?= $transaction['type'] === 'income' ? 'checked' : '' ?> required>
                            <span class="ml-2">Income</span>
                        </label>
                    </div>
                </div>
                
                <!-- Transaction Date -->
                <div>
                    <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-1">Transaction Date <span class="text-red-500">*</span></label>
                    <input type="date" id="transaction_date" name="transaction_date" value="<?= htmlspecialchars($transaction['transaction_date']) ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                </div>
                
                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">
                                <?= get_setting('currency_symbol', '$') ?>
                            </span>
                        </div>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="<?= htmlspecialchars($transaction['amount']) ?>" 
                               class="w-full pl-8 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    </div>
                </div>
                
                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                    <select id="category_id" name="category_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                        <option value="">Select Category</option>
                        <!-- Income Categories (hidden when type is expense) -->
                        <optgroup label="Income" id="income-categories" <?= $transaction['type'] === 'expense' ? 'hidden' : '' ?>>
                            <?php foreach ($categories as $category): ?>
                                <?php if ($category['type'] === 'income'): ?>
                                <option value="<?= $category['category_id'] ?>" <?= $transaction['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                        
                        <!-- Expense Categories (hidden when type is income) -->
                        <optgroup label="Expense" id="expense-categories" <?= $transaction['type'] === 'income' ? 'hidden' : '' ?>>
                            <?php foreach ($categories as $category): ?>
                                <?php if ($category['type'] === 'expense'): ?>
                                <option value="<?= $category['category_id'] ?>" <?= $transaction['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category_name']) ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <!-- Reference Number -->
                <div>
                    <label for="reference_no" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                    <input type="text" id="reference_no" name="reference_no" value="<?= htmlspecialchars($transaction['reference_no']) ?>" 
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <!-- Payment Method -->
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                    <select id="payment_method" name="payment_method" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                        <option value="cash" <?= $transaction['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="card" <?= $transaction['payment_method'] === 'card' ? 'selected' : '' ?>>Card</option>
                        <option value="bank_transfer" <?= $transaction['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
                
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                        <option value="pending" <?= $transaction['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <?php if (has_permission('approve_petty_cash')): ?>
                        <option value="approved" <?= $transaction['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $transaction['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Description -->
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="4" 
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= htmlspecialchars($transaction['description']) ?></textarea>
                </div>
                
                <!-- Receipt Image (Commented out for now, add functionality later) -->
                <!--
                <div class="md:col-span-2">
                    <label for="receipt_image" class="block text-sm font-medium text-gray-700 mb-1">Receipt Image</label>
                    <input type="file" id="receipt_image" name="receipt_image" accept="image/*" 
                           class="w-full border border-gray-300 rounded-md py-2 px-3">
                    <p class="mt-1 text-sm text-gray-500">Upload a photo or scan of the receipt (optional).</p>
                </div>
                -->
            </div>
            
            <!-- Submit Button -->
            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-6 rounded-md shadow-sm">
                    <i class="fas fa-save mr-2"></i> Update Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript to toggle category options based on transaction type -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const incomeCategories = document.getElementById('income-categories');
    const expenseCategories = document.getElementById('expense-categories');
    const categorySelect = document.getElementById('category_id');
    
    function updateCategories() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        
        if (selectedType === 'income') {
            incomeCategories.hidden = false;
            expenseCategories.hidden = true;
            
            // Select the first income category if none is selected
            const currentOption = categorySelect.options[categorySelect.selectedIndex];
            if (currentOption.parentElement !== incomeCategories) {
                if (incomeCategories.options.length > 0) {
                    incomeCategories.options[0].selected = true;
                } else {
                    // If no income categories exist, reset selection
                    categorySelect.selectedIndex = 0;
                }
            }
        } else {
            incomeCategories.hidden = true;
            expenseCategories.hidden = false;
            
            // Select the first expense category if none is selected
            const currentOption = categorySelect.options[categorySelect.selectedIndex];
            if (currentOption.parentElement !== expenseCategories) {
                if (expenseCategories.options.length > 0) {
                    expenseCategories.options[0].selected = true;
                } else {
                    // If no expense categories exist, reset selection
                    categorySelect.selectedIndex = 0;
                }
            }
        }
    }
    
    // Initial setup
    updateCategories();
    
    // Add event listeners to radio buttons
    typeRadios.forEach(radio => {
        radio.addEventListener('change', updateCategories);
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>