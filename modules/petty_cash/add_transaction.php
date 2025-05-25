<?php
ob_start();
/**
 * Petty Cash Management - Add Transaction
 * 
 * Form to add a new petty cash transaction
 */

// Set page title
$page_title = "Add Petty Cash Transaction";
$breadcrumbs = '<a href="../../index.php">Home</a> > <a href="index.php">Petty Cash</a> > Add Transaction';

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
$errors = [];
$transaction = [
    'transaction_date' => date('Y-m-d'),
    'type' => '',
    'category_id' => '',
    'amount' => '',
    'description' => '',
    'reference_no' => '',
    'payment_method' => 'cash'
];

// Only fetch data if database connection is successful
if (isset($conn) && $conn) {
    try {
        // Get categories
        $query = "SELECT category_id, category_name, type FROM petty_cash_categories WHERE status = 'active' ORDER BY type, category_name";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get and sanitize input
            $transaction = [
                'transaction_date' => $_POST['transaction_date'] ?? date('Y-m-d'),
                'type' => $_POST['type'] ?? '',
                'category_id' => $_POST['category_id'] ?? '',
                'amount' => $_POST['amount'] ?? '',
                'description' => $_POST['description'] ?? '',
                'reference_no' => $_POST['reference_no'] ?? '',
                'payment_method' => $_POST['payment_method'] ?? 'cash'
            ];
            
            // Validate input
            if (empty($transaction['transaction_date'])) {
                $errors[] = "Transaction date is required";
            }
            
            if (empty($transaction['type'])) {
                $errors[] = "Transaction type is required";
            } elseif (!in_array($transaction['type'], ['income', 'expense'])) {
                $errors[] = "Invalid transaction type";
            }
            
            if (empty($transaction['category_id'])) {
                $errors[] = "Category is required";
            } else {
                // Verify category exists and matches the transaction type
                $valid_category = false;
                foreach ($categories as $category) {
                    if ($category['category_id'] == $transaction['category_id'] && $category['type'] == $transaction['type']) {
                        $valid_category = true;
                        break;
                    }
                }
                
                if (!$valid_category) {
                    $errors[] = "Selected category does not match the transaction type";
                }
            }
            
            if (empty($transaction['amount'])) {
                $errors[] = "Amount is required";
            } elseif (!is_numeric($transaction['amount']) || $transaction['amount'] <= 0) {
                $errors[] = "Amount must be a positive number";
            }
            
            if (empty($transaction['payment_method'])) {
                $errors[] = "Payment method is required";
            } elseif (!in_array($transaction['payment_method'], ['cash', 'card', 'bank_transfer'])) {
                $errors[] = "Invalid payment method";
            }
            
            // If no errors, save the transaction
            if (empty($errors)) {
                // Prepare image upload if provided
                $receipt_image = null;
                if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['size'] > 0) {
                    $upload_dir = '../../uploads/receipts/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'receipt_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Validate file type
                    $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
                    if (!in_array(strtolower($file_extension), $allowed_types)) {
                        $errors[] = "Invalid file type. Allowed types: " . implode(', ', $allowed_types);
                    } 
                    // Validate file size (max 5MB)
                    elseif ($_FILES['receipt_image']['size'] > 5 * 1024 * 1024) {
                        $errors[] = "File is too large. Maximum size is 5MB";
                    } 
                    // Move the uploaded file
                    elseif (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $upload_path)) {
                        $receipt_image = $new_filename;
                    } else {
                        $errors[] = "Failed to upload receipt image";
                    }
                }
                
                // If still no errors, insert into database
                if (empty($errors)) {
                    $query = "INSERT INTO petty_cash (
                                transaction_date, 
                                amount, 
                                type, 
                                category_id, 
                                description, 
                                reference_no, 
                                payment_method, 
                                receipt_image, 
                                status, 
                                created_by, 
                                created_at
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())";
                    
                    $stmt = $conn->prepare($query);
                    
                    if ($stmt) {
                        $user_id = get_current_user_id();
                        // Change it to this:
$stmt->bind_param(
    "sdssisssi", // Added an extra 's' for receipt_image
    $transaction['transaction_date'],
    $transaction['amount'],
    $transaction['type'],
    $transaction['category_id'],
    $transaction['description'],
    $transaction['reference_no'],
    $transaction['payment_method'],
    $receipt_image,
    $user_id
);

                        
                        if ($stmt->execute()) {
                            // Set flash message and redirect
                            set_flash_message('success', 'Transaction added successfully');
                            header('Location: index.php');
                            exit;
                        } else {
                            $errors[] = "Database error: " . $stmt->error;
                        }
                    } else {
                        $errors[] = "Database error: " . $conn->error;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "An error occurred: " . $e->getMessage();
    }
}
?>

<!-- Page Content -->
<div class="mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-800">Add Petty Cash Transaction</h2>
        <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <h3 class="font-bold">Please correct the following errors:</h3>
        <ul class="list-disc ml-5">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow p-6">
        <form action="" method="POST" enctype="multipart/form-data">
            <!-- Transaction Type -->
            <div class="mb-6">
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Transaction Type<span class="text-red-600">*</span></label>
                <div class="flex gap-4 mt-2">
                    <label class="inline-flex items-center">
                        <input type="radio" name="type" value="income" class="form-radio h-5 w-5 text-blue-600" <?= $transaction['type'] === 'income' ? 'checked' : '' ?> required>
                        <span class="ml-2 text-gray-700">Income</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="type" value="expense" class="form-radio h-5 w-5 text-red-600" <?= $transaction['type'] === 'expense' ? 'checked' : '' ?> required>
                        <span class="ml-2 text-gray-700">Expense</span>
                    </label>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Transaction Date -->
                <div>
                    <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-1">Transaction Date<span class="text-red-600">*</span></label>
                    <input type="date" id="transaction_date" name="transaction_date" value="<?= htmlspecialchars($transaction['transaction_date']) ?>" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                </div>
                
                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount<span class="text-red-600">*</span></label>
                    <div class="relative mt-1 rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm"><?= get_setting('currency_symbol', '$') ?></span>
                        </div>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="<?= htmlspecialchars($transaction['amount']) ?>" required
                               class="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                               placeholder="0.00">
                    </div>
                </div>
                
                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category<span class="text-red-600">*</span></label>
                    <select id="category_id" name="category_id" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="">-- Select Category --</option>
                        <optgroup label="Income" id="income_categories" <?= $transaction['type'] !== 'income' ? 'disabled' : '' ?>>
                            <?php foreach ($categories as $category): ?>
                                <?php if ($category['type'] === 'income'): ?>
                                    <option value="<?= $category['category_id'] ?>" <?= $transaction['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expense" id="expense_categories" <?= $transaction['type'] !== 'expense' ? 'disabled' : '' ?>>
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
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           placeholder="Invoice / Receipt #">
                </div>
                
                <!-- Payment Method -->
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method<span class="text-red-600">*</span></label>
                    <select id="payment_method" name="payment_method" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="cash" <?= $transaction['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="card" <?= $transaction['payment_method'] === 'card' ? 'selected' : '' ?>>Card</option>
                        <option value="bank_transfer" <?= $transaction['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    </select>
                </div>
                
                <!-- Receipt Image -->
                <div>
                    <label for="receipt_image" class="block text-sm font-medium text-gray-700 mb-1">Receipt Image</label>
                    <input type="file" id="receipt_image" name="receipt_image" accept=".jpg,.jpeg,.png,.pdf"
                           class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="mt-1 text-sm text-gray-500">Max 5MB. Accepted formats: JPG, PNG, PDF</p>
                </div>
                
                <!-- Description -->
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                              placeholder="Add details about this transaction"><?= htmlspecialchars($transaction['description']) ?></textarea>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-6 rounded-md shadow-sm">
                    <i class="fas fa-save mr-2"></i> Save Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript to handle category selection based on transaction type -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const categorySelect = document.getElementById('category_id');
    const incomeCategories = document.getElementById('income_categories');
    const expenseCategories = document.getElementById('expense_categories');
    
    function updateCategoryOptions() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        
        if (selectedType === 'income') {
            incomeCategories.disabled = false;
            expenseCategories.disabled = true;
            
            // Select first income category if none is selected
            if (categorySelect.value === '') {
                const firstIncomeOption = incomeCategories.querySelector('option');
                if (firstIncomeOption) {
                    firstIncomeOption.selected = true;
                }
            }
        } else {
            incomeCategories.disabled = true;
            expenseCategories.disabled = false;
            
            // Select first expense category if none is selected
            if (categorySelect.value === '') {
                const firstExpenseOption = expenseCategories.querySelector('option');
                if (firstExpenseOption) {
                    firstExpenseOption.selected = true;
                }
            }
        }
    }
    
    // Initial update
    if (document.querySelector('input[name="type"]:checked')) {
        updateCategoryOptions();
    }
    
    // Update on change
    typeRadios.forEach(radio => {
        radio.addEventListener('change', updateCategoryOptions);
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>