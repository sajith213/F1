<?php
/**
 * Petroleum Account Management - View Transaction
 * 
 * This page displays details of a specific transaction in the petroleum account
 */

// Set page title
$page_title = "View Transaction";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Petroleum Account</a> / <a href="transactions.php">Transactions</a> / View';

// Include header
include '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Include authentication and permission check
require_once '../../includes/auth.php';

// Check if user has permission to access this module
if (!has_permission('manage_petroleum_account')) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this page.</p>
          </div>';
    include '../../includes/footer.php';
    exit;
}

// Get transaction ID from URL
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($transaction_id <= 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Invalid transaction ID.</p>
          </div>';
    echo '<div class="mt-4">
            <a href="transactions.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
            </a>
          </div>';
    include '../../includes/footer.php';
    exit;
}

// Process approval/rejection if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Check if user has approval permission
    if (!has_permission('approve_petroleum_account')) {
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>You do not have permission to approve or reject transactions.</p>
              </div>';
    } else {
        $action = $_POST['action'];
        $new_status = ($action === 'approve') ? 'completed' : 'cancelled';
        $user_id = $_SESSION['user_id'];
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get current transaction data
            $check_query = "SELECT * FROM petroleum_account_transactions WHERE transaction_id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("i", $transaction_id);
            $check_stmt->execute();
            $transaction = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            if ($transaction['status'] !== 'pending') {
                throw new Exception("This transaction has already been processed");
            }
            
            // Update the transaction status
            $update_query = "UPDATE petroleum_account_transactions SET 
                             status = ?, 
                             approved_by = ?,
                             updated_at = NOW()
                             WHERE transaction_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sii", $new_status, $user_id, $transaction_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // If approving, update the balance for this and all subsequent transactions
            if ($new_status === 'completed') {
                // Get the last completed transaction's balance
                $balance_query = "SELECT balance_after FROM petroleum_account_transactions 
                                 WHERE status = 'completed' AND transaction_id < ? 
                                 ORDER BY transaction_id DESC LIMIT 1";
                $balance_stmt = $conn->prepare($balance_query);
                $balance_stmt->bind_param("i", $transaction_id);
                $balance_stmt->execute();
                $balance_result = $balance_stmt->get_result();
                
                if ($balance_result->num_rows > 0) {
                    $previous_balance = $balance_result->fetch_assoc()['balance_after'];
                } else {
                    // No prior transactions, start from 0
                    $previous_balance = 0;
                }
                $balance_stmt->close();
                
                // Calculate new balance
                if ($transaction['transaction_type'] === 'deposit' || $transaction['transaction_type'] === 'adjustment') {
                    $new_balance = $previous_balance + $transaction['amount'];
                } else {
                    $new_balance = $previous_balance - $transaction['amount'];
                }
                
                // Update this transaction's balance
                $update_balance_query = "UPDATE petroleum_account_transactions 
                                        SET balance_after = ? 
                                        WHERE transaction_id = ?";
                $update_balance_stmt = $conn->prepare($update_balance_query);
                $update_balance_stmt->bind_param("di", $new_balance, $transaction_id);
                $update_balance_stmt->execute();
                $update_balance_stmt->close();
                
                // Update subsequent transactions' balances
                $get_next_query = "SELECT transaction_id, transaction_type, amount 
                                  FROM petroleum_account_transactions 
                                  WHERE transaction_id > ? AND status = 'completed' 
                                  ORDER BY transaction_id ASC";
                $get_next_stmt = $conn->prepare($get_next_query);
                $get_next_stmt->bind_param("i", $transaction_id);
                $get_next_stmt->execute();
                $next_result = $get_next_stmt->get_result();
                
                $current_balance = $new_balance;
                while ($next = $next_result->fetch_assoc()) {
                    if ($next['transaction_type'] === 'deposit' || $next['transaction_type'] === 'adjustment') {
                        $current_balance += $next['amount'];
                    } else {
                        $current_balance -= $next['amount'];
                    }
                    
                    $update_next_query = "UPDATE petroleum_account_transactions 
                                         SET balance_after = ? 
                                         WHERE transaction_id = ?";
                    $update_next_stmt = $conn->prepare($update_next_query);
                    $update_next_stmt->bind_param("di", $current_balance, $next['transaction_id']);
                    $update_next_stmt->execute();
                    $update_next_stmt->close();
                }
                $get_next_stmt->close();
                
                // If the transaction is related to a purchase order, update it
                if ($transaction['reference_type'] === 'purchase_order' && !empty($transaction['reference_no'])) {
                    $po_update_query = "UPDATE purchase_orders 
                                       SET account_check_status = 'sufficient' 
                                       WHERE po_number = ?";
                    $po_update_stmt = $conn->prepare($po_update_query);
                    $po_update_stmt->bind_param("s", $transaction['reference_no']);
                    $po_update_stmt->execute();
                    $po_update_stmt->close();
                    
                    // Clear any pending topups related to this PO
                    $topup_update_query = "UPDATE pending_topups 
                                          SET status = 'completed', completed_at = NOW() 
                                          WHERE po_id = (SELECT po_id FROM purchase_orders WHERE po_number = ?)";
                    $topup_update_stmt = $conn->prepare($topup_update_query);
                    $topup_update_stmt->bind_param("s", $transaction['reference_no']);
                    $topup_update_stmt->execute();
                    $topup_update_stmt->close();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Success message
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>Transaction has been ' . ($new_status === 'completed' ? 'approved' : 'rejected') . ' successfully.</p>
                  </div>';
                  
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            
            // Error message
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p>Error: ' . $e->getMessage() . '</p>
                  </div>';
        }
    }
}

// Get transaction details
$query = "SELECT 
            t.*, 
            c.full_name as created_by_name,
            a.full_name as approved_by_name
          FROM petroleum_account_transactions t
          LEFT JOIN users c ON t.created_by = c.user_id
          LEFT JOIN users a ON t.approved_by = a.user_id
          WHERE t.transaction_id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>Transaction not found.</p>
          </div>';
    echo '<div class="mt-4">
            <a href="transactions.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
            </a>
          </div>';
    include '../../includes/footer.php';
    exit;
}

$transaction = $result->fetch_assoc();
$stmt->close();

// Format currency function
function format_transaction_currency($amount) {
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

// Get related purchase order details if applicable
$purchase_order = null;
if ($transaction['reference_type'] === 'purchase_order' && !empty($transaction['reference_no'])) {
    $po_query = "SELECT 
                  po.*, 
                  s.supplier_name,
                  u.full_name as created_by_name
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN users u ON po.created_by = u.user_id
                WHERE po.po_number = ?";
                
    $po_stmt = $conn->prepare($po_query);
    $po_stmt->bind_param("s", $transaction['reference_no']);
    $po_stmt->execute();
    $po_result = $po_stmt->get_result();
    
    if ($po_result->num_rows > 0) {
        $purchase_order = $po_result->fetch_assoc();
    }
    
    $po_stmt->close();
}

// Get related files if any
$files = [];
$files_query = "SELECT * FROM transaction_files WHERE transaction_id = ?";
$files_table_exists = false;

// Check if transaction_files table exists
$table_check = $conn->query("SHOW TABLES LIKE 'transaction_files'");
if ($table_check->num_rows > 0) {
    $files_table_exists = true;
    
    $files_stmt = $conn->prepare($files_query);
    $files_stmt->bind_param("i", $transaction_id);
    $files_stmt->execute();
    $files_result = $files_stmt->get_result();
    
    while ($file = $files_result->fetch_assoc()) {
        $files[] = $file;
    }
    
    $files_stmt->close();
}
?>

<div class="container mx-auto px-4 py-4">
    <!-- Page header with actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Transaction Details</h1>
            <p class="text-sm text-gray-600">
                Transaction ID: <?= $transaction_id ?>
            </p>
        </div>
        
        <div class="flex mt-4 md:mt-0 space-x-2">
            <a href="transactions.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to List
            </a>
            
            <?php if ($transaction['status'] === 'pending' && has_permission('approve_petroleum_account')): ?>
            <button type="button" id="approve-btn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-check mr-2"></i> Approve
            </button>
            <button type="button" id="reject-btn" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-times mr-2"></i> Reject
            </button>
            <?php endif; ?>
            
            <?php if ($transaction['status'] === 'completed'): ?>
            <a href="print_receipt.php?id=<?= $transaction_id ?>" target="_blank" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-print mr-2"></i> Print Receipt
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Transaction details card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Transaction Information</h2>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Basic Information</h3>
                    <div class="bg-gray-100 p-4 rounded-lg">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Transaction Type</p>
                                <p class="font-medium">
                                    <?php if ($transaction['transaction_type'] === 'deposit'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-arrow-circle-up mr-1"></i> Deposit
                                        </span>
                                    <?php elseif ($transaction['transaction_type'] === 'withdrawal'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-arrow-circle-down mr-1"></i> Withdrawal
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-exchange-alt mr-1"></i> Adjustment
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Transaction Date</p>
                                <p class="font-medium"><?= date('F d, Y H:i', strtotime($transaction['transaction_date'])) ?></p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Amount</p>
                                <p class="font-medium text-lg">
                                    <?= format_transaction_currency($transaction['amount']) ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Balance After Transaction</p>
                                <p class="font-medium">
                                    <?= format_transaction_currency($transaction['balance_after']) ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Status</p>
                                <p class="font-medium">
                                    <?php if ($transaction['status'] === 'completed'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i> Completed
                                        </span>
                                    <?php elseif ($transaction['status'] === 'pending'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i> Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i> Cancelled
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Additional Information</h3>
                    <div class="bg-gray-100 p-4 rounded-lg">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Reference Type</p>
                                <p class="font-medium">
                                    <?php if (!empty($transaction['reference_type'])): ?>
                                        <?= ucfirst(str_replace('_', ' ', $transaction['reference_type'])) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Reference Number</p>
                                <p class="font-medium">
                                    <?php if (!empty($transaction['reference_no'])): ?>
                                        <?= $transaction['reference_no'] ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Created By</p>
                                <p class="font-medium">
                                    <?= $transaction['created_by_name'] ?? 'Unknown' ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Created At</p>
                                <p class="font-medium">
                                    <?= date('F d, Y H:i', strtotime($transaction['created_at'])) ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-xs text-gray-500">Approved By</p>
                                <p class="font-medium">
                                    <?php if (!empty($transaction['approved_by_name'])): ?>
                                        <?= $transaction['approved_by_name'] ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Description -->
            <div class="mt-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Description</h3>
                <div class="bg-gray-100 p-4 rounded-lg">
                    <?php if (!empty($transaction['description'])): ?>
                        <p><?= nl2br(htmlspecialchars($transaction['description'])) ?></p>
                    <?php else: ?>
                        <p class="text-gray-400 italic">No description provided</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Attached files section (if any) -->
            <?php if ($files_table_exists && !empty($files)): ?>
            <div class="mt-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Attached Files</h3>
                <div class="bg-gray-100 p-4 rounded-lg">
                    <ul class="divide-y divide-gray-200">
                        <?php foreach ($files as $file): ?>
                        <li class="py-2 flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                                <span><?= htmlspecialchars($file['file_name']) ?></span>
                            </div>
                            <a href="download_file.php?id=<?= $file['file_id'] ?>" class="text-blue-500 hover:text-blue-700">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Related Purchase Order (if any) -->
            <?php if ($purchase_order): ?>
            <div class="mt-6">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Related Purchase Order</h3>
                <div class="bg-gray-100 p-4 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">PO Number</p>
                            <p class="font-medium"><?= $purchase_order['po_number'] ?></p>
                        </div>
                        
                        <div>
                            <p class="text-xs text-gray-500">Supplier</p>
                            <p class="font-medium"><?= $purchase_order['supplier_name'] ?></p>
                        </div>
                        
                        <div>
                            <p class="text-xs text-gray-500">Order Date</p>
                            <p class="font-medium"><?= date('F d, Y', strtotime($purchase_order['order_date'])) ?></p>
                        </div>
                        
                        <div>
                            <p class="text-xs text-gray-500">Total Amount</p>
                            <p class="font-medium"><?= format_transaction_currency($purchase_order['total_amount']) ?></p>
                        </div>
                        
                        <div>
                            <p class="text-xs text-gray-500">Status</p>
                            <p class="font-medium">
                                <?php if ($purchase_order['status'] === 'delivered'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Delivered
                                    </span>
                                <?php elseif ($purchase_order['status'] === 'cancelled'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Cancelled
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <?= ucfirst($purchase_order['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-xs text-gray-500">Account Check</p>
                            <p class="font-medium">
                                <?php if ($purchase_order['account_check_status'] === 'sufficient'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Sufficient
                                    </span>
                                <?php elseif ($purchase_order['account_check_status'] === 'insufficient'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Insufficient
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Not Checked
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="../fuel_ordering/view_order.php?id=<?= $purchase_order['po_id'] ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-external-link-alt mr-1"></i> View Purchase Order Details
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approval/Rejection Modal -->
<?php if ($transaction['status'] === 'pending' && has_permission('approve_petroleum_account')): ?>
<div id="confirmation-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Confirm Action</h3>
        </div>
        
        <div class="px-6 py-4">
            <p id="modal-message" class="text-gray-700">Are you sure you want to proceed with this action?</p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" id="action-input" value="">
            
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-2">
                <button type="button" id="cancel-btn" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    Cancel
                </button>
                <button type="submit" id="confirm-btn" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('confirmation-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalMessage = document.getElementById('modal-message');
        const actionInput = document.getElementById('action-input');
        const confirmBtn = document.getElementById('confirm-btn');
        
        // Approve button
        document.getElementById('approve-btn').addEventListener('click', function() {
            modalTitle.textContent = 'Confirm Approval';
            modalMessage.textContent = 'Are you sure you want to approve this transaction? This will update the account balance.';
            actionInput.value = 'approve';
            confirmBtn.classList.remove('bg-red-500', 'hover:bg-red-600');
            confirmBtn.classList.add('bg-green-500', 'hover:bg-green-600');
            modal.classList.remove('hidden');
        });
        
        // Reject button
        document.getElementById('reject-btn').addEventListener('click', function() {
            modalTitle.textContent = 'Confirm Rejection';
            modalMessage.textContent = 'Are you sure you want to reject this transaction? This action cannot be undone.';
            actionInput.value = 'reject';
            confirmBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
            confirmBtn.classList.add('bg-red-500', 'hover:bg-red-600');
            modal.classList.remove('hidden');
        });
        
        // Cancel button
        document.getElementById('cancel-btn').addEventListener('click', function() {
            modal.classList.add('hidden');
        });
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>