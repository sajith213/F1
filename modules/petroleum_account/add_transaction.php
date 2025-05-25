<?php
/**
 * Add Petroleum Account Transaction
 * 
 * Form to add deposits or withdrawals to the petroleum account
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set page title
$transaction_type = $_GET['type'] ?? 'deposit';
$page_title = ucfirst($transaction_type) . " Transaction";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Petroleum Account</a> / Add ' . ucfirst($transaction_type);

// Include header
include '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Include authentication
require_once '../../includes/auth.php';

// Include functions with format_currency()
require_once '../../includes/functions.php';

// Check if user has permission to access this module
if (!has_permission('manage_petroleum_account')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this page.</p>
          </div>';
    include '../../includes/footer.php';
    exit;
}

// Validate transaction type
if ($transaction_type !== 'deposit' && $transaction_type !== 'withdrawal' && $transaction_type !== 'adjustment') {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Invalid transaction type. Please select a valid transaction type.</p>
          </div>';
    include '../../includes/footer.php';
    exit;
}

// Process form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $amount = $_POST['amount'] ?? '';
    $transaction_date = $_POST['transaction_date'] ?? '';
    $reference_no = $_POST['reference_no'] ?? '';
    $reference_type = $_POST['reference_type'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount greater than zero.";
    }
    
    if (empty($transaction_date)) {
        $errors[] = "Please select a transaction date.";
    }
    
    // If validation passes, process the transaction
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            $status = has_permission('approve_petroleum_account') ? 'completed' : 'pending';
            
            // Get current balance
            $current_balance = 0;
            $query = "SELECT balance_after FROM petroleum_account_transactions 
                      WHERE status = 'completed' 
                      ORDER BY transaction_id DESC LIMIT 1";
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $current_balance = $row['balance_after'];
            }
            
            // Calculate new balance
            $balance_after = $current_balance;
            if ($transaction_type === 'deposit') {
                $balance_after += $amount;
            } elseif ($transaction_type === 'withdrawal') {
                $balance_after -= $amount;
            } elseif ($transaction_type === 'adjustment') {
                // For adjustment, we set the balance directly to the amount
                $balance_after = $amount;
            }
            
            // Insert transaction
            $sql = "INSERT INTO petroleum_account_transactions (
                        transaction_date, 
                        transaction_type, 
                        amount, 
                        balance_after, 
                        reference_no, 
                        reference_type, 
                        description, 
                        status, 
                        created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssddssssi",
                $transaction_date,
                $transaction_type,
                $amount,
                $balance_after,
                $reference_no,
                $reference_type,
                $description,
                $status,
                $_SESSION['user_id']
            );
            
            $stmt->execute();
            $transaction_id = $stmt->insert_id;
            $stmt->close();
            
            // Handle file upload if exists
            if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/receipts/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
                $filename = 'receipt_' . $transaction_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $file_path)) {
                    // Update transaction with receipt image path
                    $sql = "UPDATE petroleum_account_transactions SET receipt_image = ? WHERE transaction_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $filename, $transaction_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $errors[] = "Failed to upload receipt image.";
                    $conn->rollback();
                    goto end_processing;
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message
            $success_message = ucfirst($transaction_type) . " transaction recorded successfully.";
            
            // If the status is pending, add additional message
            if ($status === 'pending') {
                $success_message .= " The transaction requires approval before it will affect the account balance.";
            }
            
            // Clear form data
            $amount = '';
            $transaction_date = '';
            $reference_no = '';
            $reference_type = '';
            $description = '';
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing transaction: " . $e->getMessage();
        }
        
        end_processing:
    }
}

// Get current date and time for the default value
$default_date = date('Y-m-d\TH:i');

// Get current balance for display
$current_balance = 0;
$query = "SELECT balance_after FROM petroleum_account_transactions 
          WHERE status = 'completed' 
          ORDER BY transaction_id DESC LIMIT 1";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_balance = $row['balance_after'];
}
?>

<!-- Page content -->
<div class="mb-6">
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">Please fix the following errors:</p>
        <ul class="list-disc ml-5">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($success_message) ?></p>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <?php if ($transaction_type === 'deposit'): ?>
                <i class="fas fa-arrow-circle-up text-green-500 mr-2"></i> Add Deposit
                <?php elseif ($transaction_type === 'withdrawal'): ?>
                <i class="fas fa-arrow-circle-down text-red-500 mr-2"></i> Record Withdrawal
                <?php else: ?>
                <i class="fas fa-sliders-h text-blue-500 mr-2"></i> Make Balance Adjustment
                <?php endif; ?>
            </h2>
            <p class="text-sm text-gray-600 mt-1">
                Current Account Balance: <span class="font-semibold"><?= format_currency($current_balance) ?></span>
            </p>
        </div>
        
        <form action="" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Amount -->
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">
                        <?php if ($transaction_type === 'adjustment'): ?>
                            New Balance Amount
                        <?php else: ?>
                            <?= ucfirst($transaction_type) ?> Amount
                        <?php endif; ?>
                        <span class="text-red-500">*</span>
                    </label>
                    <div class="relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">
                                <?= get_setting('currency_symbol', 'Rs.') ?>
                            </span>
                        </div>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="<?= htmlspecialchars($amount ?? '') ?>" 
                               class="block w-full pl-14 pr-12 border-gray-300 rounded-md focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" 
                               placeholder="0.00" required>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php if ($transaction_type === 'deposit'): ?>
                            Enter the amount to be added to the account
                        <?php elseif ($transaction_type === 'withdrawal'): ?>
                            Enter the amount to be deducted from the account
                        <?php else: ?>
                            Enter the new account balance (this will override the current balance)
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Transaction Date -->
                <div>
                    <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-1">
                        Transaction Date <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="transaction_date" name="transaction_date" value="<?= htmlspecialchars($transaction_date ?? $default_date) ?>" 
                           class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    <p class="mt-1 text-sm text-gray-500">Date and time of the transaction</p>
                </div>
                
                <!-- Reference Type -->
                <div>
                    <label for="reference_type" class="block text-sm font-medium text-gray-700 mb-1">
                        Reference Type
                    </label>
                    <select id="reference_type" name="reference_type" 
                           class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="">-- Select Reference Type --</option>
                        <option value="purchase_order" <?= isset($reference_type) && $reference_type === 'purchase_order' ? 'selected' : '' ?>>Purchase Order</option>
                        <option value="bank_transfer" <?= isset($reference_type) && $reference_type === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="refund" <?= isset($reference_type) && $reference_type === 'refund' ? 'selected' : '' ?>>Refund</option>
                        <option value="other" <?= isset($reference_type) && $reference_type === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <!-- Reference Number -->
                <div>
                    <label for="reference_no" class="block text-sm font-medium text-gray-700 mb-1">
                        Reference Number
                    </label>
                    <input type="text" id="reference_no" name="reference_no" value="<?= htmlspecialchars($reference_no ?? '') ?>" 
                           class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <p class="mt-1 text-sm text-gray-500">E.g., Bank transaction ID, PO number, etc.</p>
                </div>
                
                <!-- Description -->
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                        Description
                    </label>
                    <textarea id="description" name="description" rows="3" 
                           class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?= htmlspecialchars($description ?? '') ?></textarea>
                    <p class="mt-1 text-sm text-gray-500">Additional details about this transaction</p>
                </div>
                
                <!-- Receipt Image Upload -->
                <div class="md:col-span-2">
                    <label for="receipt_image" class="block text-sm font-medium text-gray-700 mb-1">
                        Receipt Image (if available)
                    </label>
                    <input type="file" id="receipt_image" name="receipt_image" accept="image/*,.pdf" 
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="mt-1 text-sm text-gray-500">Upload a scanned copy or photo of the receipt (JPG, PNG, PDF formats accepted)</p>
                </div>
            </div>
            
            <div class="pt-4 border-t border-gray-200 flex justify-end space-x-3">
                <a href="index.php" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white 
                    <?php if ($transaction_type === 'deposit'): ?>
                        bg-green-600 hover:bg-green-700 focus:ring-green-500
                    <?php elseif ($transaction_type === 'withdrawal'): ?>
                        bg-red-600 hover:bg-red-700 focus:ring-red-500
                    <?php else: ?>
                        bg-blue-600 hover:bg-blue-700 focus:ring-blue-500
                    <?php endif; ?>
                    focus:outline-none focus:ring-2 focus:ring-offset-2">
                    Save Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>