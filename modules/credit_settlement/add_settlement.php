<?php
/**
 * Credit Management - Add Credit Settlement
 * 
 * This file allows adding a new settlement for a credit customer
 * and records the transaction in the appropriate tables.
 */

// Set page title and include header
$page_title = "Add Credit Settlement";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="../credit_management/index.php">Credit Management</a> / <span class="text-gray-700">Add Settlement</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Get currency symbol from settings
$currency_symbol = get_setting('currency_symbol', '$');

// Initialize variables
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$error_message = '';
$success_message = '';
$customer = null;
$current_date = date('Y-m-d');

// Fetch customer details if ID is provided
if ($customer_id > 0) {
    $stmt = $conn->prepare("
        SELECT c.*, 
               COALESCE(SUM(CASE WHEN t.transaction_type = 'sale' THEN t.amount ELSE 0 END), 0) as total_sales,
               COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) as total_payments
        FROM credit_customers c
        LEFT JOIN credit_transactions t ON c.customer_id = t.customer_id
        WHERE c.customer_id = ?
        GROUP BY c.customer_id
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
    } else {
        $error_message = "Customer not found.";
    }
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_settlement'])) {
    // Validate inputs
    $customer_id = (int)$_POST['customer_id'];
    $settlement_date = $_POST['settlement_date'];
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $reference_no = $_POST['reference_no'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    $errors = [];
    
    // Basic validation
    if ($customer_id <= 0) {
        $errors[] = "Invalid customer selected.";
    }
    
    if (empty($settlement_date)) {
        $errors[] = "Settlement date is required.";
    }
    
    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero.";
    }
    
    if (empty($payment_method)) {
        $errors[] = "Payment method is required.";
    }
    
    // Fetch customer current balance to check if amount is valid
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT customer_id, current_balance FROM credit_customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $customer_data = $result->fetch_assoc();
            
            // Check if amount is not greater than current balance
            if ($amount > $customer_data['current_balance']) {
                $errors[] = "Settlement amount cannot exceed the current balance of " . $currency_symbol . number_format($customer_data['current_balance'], 2);
            }
        } else {
            $errors[] = "Customer not found.";
        }
        $stmt->close();
    }
    
    // If no errors, insert settlement
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // 1. Insert into credit_settlements table
            $stmt = $conn->prepare("
                INSERT INTO credit_settlements 
                (customer_id, settlement_date, amount, payment_method, reference_no, notes, recorded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $user_id = get_current_user_id();
            $stmt->bind_param("isdssis", $customer_id, $settlement_date, $amount, $payment_method, $reference_no, $notes, $user_id);
            $stmt->execute();
            $settlement_id = $conn->insert_id;
            $stmt->close();
            
            // 2. Insert into credit_transactions table
            $stmt = $conn->prepare("
                INSERT INTO credit_transactions 
                (customer_id, transaction_date, amount, transaction_type, reference_no, notes, created_by, created_at)
                VALUES (?, ?, ?, 'payment', ?, ?, ?, NOW())
            ");
            $settlement_date_time = $settlement_date . ' ' . date('H:i:s');
            $transaction_ref = "Settlement #" . $settlement_id;
            if (!empty($reference_no)) {
                $transaction_ref .= " (Ref: " . $reference_no . ")";
            }
            $stmt->bind_param("idssis", $customer_id, $settlement_date_time, $amount, $transaction_ref, $notes, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // 3. Update customer balance
            $stmt = $conn->prepare("
                UPDATE credit_customers 
                SET current_balance = current_balance - ? 
                WHERE customer_id = ?
            ");
            $stmt->bind_param("di", $amount, $customer_id);
            $stmt->execute();
            $stmt->close();
            
            // 4. Get the remaining balance to store in transaction record
            $stmt = $conn->prepare("
                SELECT current_balance 
                FROM credit_customers 
                WHERE customer_id = ?
            ");
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $new_balance = $result->fetch_assoc()['current_balance'];
            $stmt->close();
            
            // 5. Update the balance_after field in the credit_transactions table
            $stmt = $conn->prepare("
                UPDATE credit_transactions 
                SET balance_after = ? 
                WHERE customer_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->bind_param("di", $new_balance, $customer_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $success_message = "Settlement of " . $currency_symbol . number_format($amount, 2) . " has been recorded successfully.";
            
            // Redirect to listing with success message
            $_SESSION['success_message'] = $success_message;
            header("Location: credit_settlements.php");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error recording settlement: " . $e->getMessage();
        }
    } else {
        $error_message = "Please correct the following errors:<br>" . implode("<br>", $errors);
    }
    
    // If we reached here with errors, reload customer data
    if (!empty($error_message) && $customer_id > 0) {
        $stmt = $conn->prepare("
            SELECT c.*, 
                   COALESCE(SUM(CASE WHEN t.transaction_type = 'sale' THEN t.amount ELSE 0 END), 0) as total_sales,
                   COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) as total_payments
            FROM credit_customers c
            LEFT JOIN credit_transactions t ON c.customer_id = t.customer_id
            WHERE c.customer_id = ?
            GROUP BY c.customer_id
        ");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $customer = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// If no specific customer is selected, get all active customers for dropdown
$customers = [];
if ($customer_id <= 0) {
    $stmt = $conn->prepare("
        SELECT c.customer_id, c.customer_name, c.current_balance
        FROM credit_customers c
        WHERE c.status = 'active' AND c.current_balance > 0
        ORDER BY c.customer_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $stmt->close();
}
?>

<div class="container mx-auto pb-6">
    
    <!-- Page title and actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Add Credit Settlement</h2>
        
        <div class="flex gap-2">
            <a href="credit_settlements.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Settlements
            </a>
        </div>
    </div>
    
    <!-- Error Message -->
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?= $error_message ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?= $success_message ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Settlement Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Settlement Details</h3>
                </div>
                
                <form method="POST" action="" class="p-6">
                    <?php if ($customer): ?>
                    <input type="hidden" name="customer_id" value="<?= $customer['customer_id'] ?>">
                    <?php endif; ?>
                    
                    <!-- Customer Selection (if no customer_id provided) -->
                    <?php if (!$customer): ?>
                    <div class="mb-6">
                        <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                        <?php if (empty($customers)): ?>
                        <p class="text-red-500">No customers with outstanding balance found.</p>
                        <?php else: ?>
                        <select id="customer_id" name="customer_id" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select a customer</option>
                            <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['customer_id'] ?>">
                                <?= htmlspecialchars($cust['customer_name']) ?> - 
                                Balance: <?= $currency_symbol ?><?= number_format($cust['current_balance'], 2) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Settlement Date -->
                    <div class="mb-6">
                        <label for="settlement_date" class="block text-sm font-medium text-gray-700 mb-1">Settlement Date <span class="text-red-500">*</span></label>
                        <input type="date" id="settlement_date" name="settlement_date" value="<?= $current_date ?>" required
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Amount -->
                    <div class="mb-6">
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                            </div>
                            <input type="number" step="0.01" min="0.01" id="amount" name="amount" 
                                   value="<?= $customer ? $customer['current_balance'] : '' ?>"
                                   required class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md">
                        </div>
                        <?php if ($customer): ?>
                        <p class="mt-1 text-sm text-gray-500">Maximum amount: <?= $currency_symbol ?><?= number_format($customer['current_balance'], 2) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="mb-6">
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                        <select id="payment_method" name="payment_method" required class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                            <option value="">Select payment method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="mobile_payment">Mobile Payment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <!-- Reference Number -->
                    <div class="mb-6">
                        <label for="reference_no" class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                        <input type="text" id="reference_no" name="reference_no" 
                               placeholder="Bank transfer reference, check number, etc." 
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Notes -->
                    <div class="mb-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea id="notes" name="notes" rows="3" 
                                  placeholder="Additional notes about this settlement" 
                                  class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3">
                        <a href="credit_settlements.php" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Cancel
                        </a>
                        <button type="submit" name="submit_settlement" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Submit Settlement
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Customer Information (if a customer is selected) -->
        <?php if ($customer): ?>
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Customer Information</h3>
                </div>
                
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                            <span class="text-blue-600 font-bold text-lg">
                                <?= strtoupper(substr($customer['customer_name'], 0, 1)) ?>
                            </span>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($customer['customer_name']) ?></h4>
                            <p class="text-gray-600"><?= htmlspecialchars($customer['phone_number']) ?></p>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Credit Limit:</span>
                            <span class="font-semibold"><?= $currency_symbol ?><?= number_format($customer['credit_limit'], 2) ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Current Balance:</span>
                            <span class="font-semibold text-red-600"><?= $currency_symbol ?><?= number_format($customer['current_balance'], 2) ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Available Credit:</span>
                            <?php 
                            $available_credit = $customer['credit_limit'] - $customer['current_balance'];
                            $credit_class = 'text-green-600';
                            if ($available_credit <= 0) {
                                $credit_class = 'text-red-600';
                            }
                            ?>
                            <span class="font-semibold <?= $credit_class ?>"><?= $currency_symbol ?><?= number_format($available_credit, 2) ?></span>
                        </div>
                        
                        <hr class="my-2">
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Sales:</span>
                            <span class="font-semibold"><?= $currency_symbol ?><?= number_format($customer['total_sales'], 2) ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Payments:</span>
                            <span class="font-semibold"><?= $currency_symbol ?><?= number_format($customer['total_payments'], 2) ?></span>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="mt-4">
                        <h5 class="font-medium text-gray-700 mb-2">Status</h5>
                        <?php 
                        $status_class = 'bg-green-100 text-green-800';
                        if ($customer['status'] === 'inactive') {
                            $status_class = 'bg-gray-100 text-gray-800';
                        } elseif ($customer['status'] === 'blocked') {
                            $status_class = 'bg-red-100 text-red-800';
                        }
                        ?>
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                            <?= ucfirst($customer['status']) ?>
                        </span>
                        
                        <?php if ($customer['current_balance'] > $customer['credit_limit']): ?>
                        <span class="px-2 py-1 ml-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                            Over Limit
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-6">
                        <a href="../credit_management/view_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fas fa-user mr-2"></i> View Customer Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Additional JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // When payment method changes, show/hide relevant fields
    const paymentMethodSelect = document.getElementById('payment_method');
    const referenceNoField = document.getElementById('reference_no');
    
    if (paymentMethodSelect && referenceNoField) {
        paymentMethodSelect.addEventListener('change', function() {
            const selectedMethod = this.value;
            if (selectedMethod === 'cash') {
                referenceNoField.placeholder = 'Receipt number (optional)';
            } else if (selectedMethod === 'bank_transfer') {
                referenceNoField.placeholder = 'Bank transfer reference number';
            } else if (selectedMethod === 'check') {
                referenceNoField.placeholder = 'Check number';
            } else if (selectedMethod === 'mobile_payment') {
                referenceNoField.placeholder = 'Mobile payment reference/confirmation code';
            } else {
                referenceNoField.placeholder = 'Reference number';
            }
        });
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>