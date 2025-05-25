<?php
/**
 * Approve Petroleum Account Transaction
 * 
 * This file handles the approval of pending petroleum account transactions
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set page title
$page_title = "Approve Transaction";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Petroleum Account</a> / Approve Transaction';

// Include header
include '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Include authentication and permission check
require_once '../../includes/auth.php';

// Check if user has permission to approve transactions
if (!has_permission('approve_petroleum_account')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to approve petroleum account transactions.</p>
          </div>';
    include '../../includes/footer.php';
    exit;
}

// Check if transaction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>No transaction ID provided.</p>
          </div>';
    include '../../includes/footer.php';
    exit;
}

$transaction_id = (int)$_GET['id'];

// Get transaction details
$transaction = null;
$sql = "SELECT * FROM petroleum_account_transactions WHERE transaction_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Transaction not found.</p>
          </div>';
    include '../../includes/footer.php';
    exit;
}

$transaction = $result->fetch_assoc();

// Check if transaction is already approved or cancelled
if ($transaction['status'] !== 'pending') {
    echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
            <p>This transaction has already been ' . $transaction['status'] . ' and cannot be approved.</p>
          </div>';
    include '../../includes/footer.php';
    exit;
}

// Process form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve' || $action === 'reject') {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            if ($action === 'approve') {
                // 1. Get current account balance
                $current_balance = 0;
                $sql = "SELECT balance_after FROM petroleum_account_transactions 
                        WHERE status = 'completed' 
                        ORDER BY transaction_id DESC LIMIT 1";
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $current_balance = $row['balance_after'];
                }
                
                // 2. Calculate new balance
                $new_balance = $current_balance;
                if ($transaction['transaction_type'] === 'deposit') {
                    $new_balance += $transaction['amount'];
                } elseif ($transaction['transaction_type'] === 'withdrawal') {
                    $new_balance -= $transaction['amount'];
                    
                    // Check if withdrawal would result in negative balance
                    if ($new_balance < 0) {
                        throw new Exception("Approving this withdrawal would result in a negative balance. Current balance: " . format_currency($current_balance));
                    }
                } elseif ($transaction['transaction_type'] === 'adjustment') {
                    // For adjustment, we set the balance directly to the amount
                    $new_balance = $transaction['amount'];
                }
                
                // 3. Update transaction status and balance
                $sql = "UPDATE petroleum_account_transactions 
                        SET status = 'completed', 
                            balance_after = ?, 
                            approved_by = ?, 
                            description = CONCAT(description, '\n\nApproval Notes: ', ?)
                        WHERE transaction_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("diss", $new_balance, $_SESSION['user_id'], $notes, $transaction_id);
                $stmt->execute();
                
                // Check if this is related to a pending top-up
                if ($transaction['transaction_type'] === 'deposit' && $transaction['reference_type'] === 'purchase_order' && !empty($transaction['reference_no'])) {
                    // Check if there's a pending top-up for this PO
                    $po_number = $transaction['reference_no'];
                    $sql = "SELECT pt.topup_id, pt.po_id 
                            FROM pending_topups pt 
                            JOIN purchase_orders po ON pt.po_id = po.po_id 
                            WHERE po.po_number = ? AND pt.status = 'pending'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $po_number);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $topup_id = $row['topup_id'];
                        $po_id = $row['po_id'];
                        
                        // Update the top-up and PO status
                        $sql = "UPDATE pending_topups SET status = 'completed', completed_at = NOW() WHERE topup_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $topup_id);
                        $stmt->execute();
                        
                        $sql = "UPDATE purchase_orders SET account_check_status = 'sufficient' WHERE po_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $po_id);
                        $stmt->execute();
                    }
                }
                
                $success_message = "Transaction approved successfully. The account balance has been updated.";
            } else {
                // Reject the transaction
                $sql = "UPDATE petroleum_account_transactions 
                        SET status = 'cancelled', 
                            approved_by = ?, 
                            description = CONCAT(description, '\n\nRejection Notes: ', ?)
                        WHERE transaction_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isi", $_SESSION['user_id'], $notes, $transaction_id);
                $stmt->execute();
                
                $success_message = "Transaction has been rejected.";
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to transactions page with success message
            $_SESSION['flash_message'] = $success_message;
            $_SESSION['flash_type'] = 'success';
            header('Location: transactions.php');
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing transaction: " . $e->getMessage();
        }
    } else {
        $errors[] = "Invalid action. Please select either approve or reject.";
    }
}

// Format currency
function format_currency($amount) {
    global $conn;
    $currency_symbol = '';
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currency_symbol = $row['setting_value'];
    }
    
    return $currency_symbol . ' ' . number_format($amount, 2);
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
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-800">
                <?php if ($transaction['transaction_type'] === 'deposit'): ?>
                <i class="fas fa-arrow-circle-up text-green-500 mr-2"></i> Approve Deposit
                <?php elseif ($transaction['transaction_type'] === 'withdrawal'): ?>
                <i class="fas fa-arrow-circle-down text-red-500 mr-2"></i> Approve Withdrawal
                <?php else: ?>
                <i class="fas fa-sliders-h text-blue-500 mr-2"></i> Approve Balance Adjustment
                <?php endif; ?>
            </h2>
            <p class="text-sm text-gray-600 mt-1">Review the transaction details before approving or rejecting</p>
        </div>
        
        <div class="p-6 border-b border-gray-200">
            <!-- Transaction Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-md font-semibold text-gray-700 mb-3">Transaction Information</h3>
                    <table class="min-w-full">
                        <tr>
                            <td class="py-2 text-sm text-gray-600 font-medium">Transaction ID:</td>
                            <td class="py-2 text-sm text-gray-900"><?= $transaction['transaction_id'] ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-sm text-gray-600 font-medium">Date:</td>
                            <td class="py-2 text-sm text-gray-900"><?= date('M d, Y H:i', strtotime($transaction['transaction_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="py-2 text-sm text-gray-600 font-medium">Type:</td>
                            <td class="py-2 text-sm">
                                <?php if ($transaction['transaction_type'] === 'deposit'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Deposit
                                </span>
                                <?php elseif ($transaction['transaction_type'] === 'withdrawal'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Withdrawal
                                </span>
                                <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    Adjustment
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-2 text-sm text-gray-600 font-medium">Amount:</td>
                            <td class="py-2 text-sm font-semibold 
                                <?= $transaction['transaction_type'] === 'deposit' ? 'text-green-600' : 
                                   ($transaction['transaction_type'] === 'withdrawal' ? 'text-red-600' : 'text-blue-600') ?>">
                                <?= format_currency($transaction['amount']) ?>
                            </td>
                        </tr>
                        <?php if (!empty($transaction['reference_no'])): ?>
                        <tr>
                            <td class="py-2 text-sm text-gray-600 font-medium">Reference:</td>
                            <td class="py-2 text-sm text-gray-900">
                                <?= htmlspecialchars($transaction['reference_type']) ?>: <?= htmlspecialchars($transaction['reference_no']) ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <div>
                    <h3 class="text-md font-semibold text-gray-700 mb-3">Account Impact</h3>
                    <?php
                    // Get current balance
                    $current_balance = 0;
                    $sql = "SELECT balance_after FROM petroleum_account_transactions 
                            WHERE status = 'completed' 
                            ORDER BY transaction_id DESC LIMIT 1";
                    $result = $conn->query($sql);
                    
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $current_balance = $row['balance_after'];
                    }
                    
                    // Calculate new balance after this transaction
                    $new_balance = $current_balance;
                    if ($transaction['transaction_type'] === 'deposit') {
                        $new_balance += $transaction['amount'];
                    } elseif ($transaction['transaction_type'] === 'withdrawal') {
                        $new_balance -= $transaction['amount'];
                    } elseif ($transaction['transaction_type'] === 'adjustment') {
                        // For adjustment, we set the balance directly to the amount
                        $new_balance = $transaction['amount'];
                    }
                    ?>
                    <table class="min-w-full">
                        <tr>
                            <td class="py-2 text-sm text-gray-600 font-medium">Current Balance:</td>
                            <td class="py-2 text-sm font-semibold <?= $current_balance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= format_currency($current_balance) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-2 text-sm text-gray-600 font-medium">
                                <?php if ($transaction['transaction_type'] === 'deposit'): ?>
                                    Amount to Add:
                                <?php elseif ($transaction['transaction_type'] === 'withdrawal'): ?>
                                    Amount to Deduct:
                                <?php else: ?>
                                    New Balance Amount:
                                <?php endif; ?>
                            </td>
                            <td class="py-2 text-sm font-semibold 
                                <?= $transaction['transaction_type'] === 'deposit' ? 'text-green-600' : 
                                   ($transaction['transaction_type'] === 'withdrawal' ? 'text-red-600' : 'text-blue-600') ?>">
                                <?= format_currency($transaction['amount']) ?>
                            </td>
                        </tr>
                        <tr class="border-t border-gray-200">
                            <td class="py-2 text-sm text-gray-700 font-medium">New Balance After Approval:</td>
                            <td class="py-2 text-sm font-bold <?= $new_balance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= format_currency($new_balance) ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if ($transaction['transaction_type'] === 'withdrawal' && $new_balance < 0): ?>
                    <div class="mt-3 p-3 bg-red-100 rounded-md">
                        <p class="text-sm text-red-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Warning: Approving this transaction will result in a negative account balance.
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($transaction['description'])): ?>
                    <div class="mt-4">
                        <h4 class="text-sm font-semibold text-gray-700">Description:</h4>
                        <p class="text-sm text-gray-600 mt-1 whitespace-pre-wrap"><?= htmlspecialchars($transaction['description']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <form action="" method="POST" class="p-6">
            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                    Approval/Rejection Notes:
                </label>
                <textarea id="notes" name="notes" rows="3" 
                       class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
                <p class="mt-1 text-sm text-gray-500">Add any notes or comments about this transaction approval or rejection</p>
            </div>
            
            <div class="flex justify-end space-x-3">
                <a href="transactions.php" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </a>
                
                <button type="submit" name="action" value="reject" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        onclick="return confirm('Are you sure you want to reject this transaction?');">
                    Reject Transaction
                </button>
                
                <button type="submit" name="action" value="approve" 
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                        <?= ($transaction['transaction_type'] === 'withdrawal' && $new_balance < 0) ? 'onclick="return confirm(\'Warning: This will result in a negative balance. Are you sure you want to approve?\');"' : '' ?>>
                    Approve Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include '../../includes/footer.php';
?>