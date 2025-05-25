<?php
/**
 * Petty Cash Management - View Transaction
 * 
 * View details of a specific petty cash transaction
 */

// Set page title
$page_title = "View Petty Cash Transaction";
$breadcrumbs = "Home > Finance > Petty Cash > View Transaction";

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
        // Get transaction data with related information
        $query = "SELECT t.*, 
                  c.category_name, 
                  creator.full_name as created_by_name, 
                  approver.full_name as approved_by_name 
                  FROM petty_cash t
                  LEFT JOIN petty_cash_categories c ON t.category_id = c.category_id
                  LEFT JOIN users creator ON t.created_by = creator.user_id
                  LEFT JOIN users approver ON t.approved_by = approver.user_id
                  WHERE t.transaction_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
        } else {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
            echo '<p>Transaction not found. <a href="index.php" class="font-semibold underline">Go back to transactions</a></p>';
            echo '</div>';
            include '../../includes/footer.php';
            exit;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // Log error
        error_log("Petty cash transaction fetch error: " . $e->getMessage());
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
        echo '<p>Error fetching transaction: ' . $e->getMessage() . '</p>';
        echo '</div>';
    }
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('approve_petty_cash')) {
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve' || $action === 'reject') {
        try {
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            $user_id = get_current_user_id();
            
            $stmt = $conn->prepare("UPDATE petty_cash SET status = ?, approved_by = ?, updated_at = NOW() WHERE transaction_id = ?");
            $stmt->bind_param("sii", $status, $user_id, $transaction_id);
            
            if ($stmt->execute()) {
                // Refresh transaction data
                $refresh_stmt = $conn->prepare($query);
                $refresh_stmt->bind_param("i", $transaction_id);
                $refresh_stmt->execute();
                $result = $refresh_stmt->get_result();
                if ($result) {
                    $transaction = $result->fetch_assoc();
                }
                $refresh_stmt->close();
                
                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">';
                echo '<p>Transaction successfully ' . ($status === 'approved' ? 'approved' : 'rejected') . '.</p>';
                echo '</div>';
            } else {
                echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
                echo '<p>Error updating transaction status: ' . $stmt->error . '</p>';
                echo '</div>';
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Petty cash approval error: " . $e->getMessage());
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
            echo '<p>Error: ' . $e->getMessage() . '</p>';
            echo '</div>';
        }
    }
}
?>

<!-- Page Content -->
<div class="mb-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4">
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0">View Petty Cash Transaction</h2>
        <div class="flex flex-wrap gap-2">
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
            </a>
            
            <?php if ($transaction['status'] === 'pending' || has_permission('admin_petty_cash')): ?>
            <a href="edit_transaction.php?id=<?= $transaction_id ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded">
                <i class="fas fa-edit mr-2"></i> Edit Transaction
            </a>
            <?php endif; ?>
            
            <?php if (has_permission('approve_petty_cash') && $transaction['status'] === 'pending'): ?>
            <button type="button" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded" id="approveBtn">
                <i class="fas fa-check mr-2"></i> Approve
            </button>
            <button type="button" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded" id="rejectBtn">
                <i class="fas fa-times mr-2"></i> Reject
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Transaction Details -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <!-- Status Badge Header -->
        <div class="p-4 border-b flex justify-between items-center
                   <?php 
                   if ($transaction['status'] === 'approved') echo 'bg-green-50';
                   elseif ($transaction['status'] === 'rejected') echo 'bg-red-50';
                   else echo 'bg-yellow-50';
                   ?>">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">
                    Transaction #<?= $transaction_id ?>
                </h3>
                <p class="text-sm text-gray-600">
                    <?= date('F j, Y', strtotime($transaction['transaction_date'])) ?>
                </p>
            </div>
            <div>
                <?php 
                if ($transaction['status'] === 'approved') {
                    echo '<span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>';
                } elseif ($transaction['status'] === 'rejected') {
                    echo '<span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>';
                } else {
                    echo '<span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>';
                }
                ?>
            </div>
        </div>
        
        <!-- Transaction Details -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div>
                    <h4 class="text-base font-semibold text-gray-700 mb-4">Transaction Information</h4>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Type:</span>
                            <span class="font-medium <?= $transaction['type'] === 'income' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= ucfirst($transaction['type']) ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Amount:</span>
                            <span class="font-medium <?= $transaction['type'] === 'income' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= format_currency($transaction['amount']) ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Category:</span>
                            <span class="font-medium text-gray-800">
                                <?= htmlspecialchars($transaction['category_name']) ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Payment Method:</span>
                            <span class="font-medium text-gray-800">
                                <?= ucfirst(str_replace('_', ' ', $transaction['payment_method'])) ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Reference No:</span>
                            <span class="font-medium text-gray-800">
                                <?= htmlspecialchars($transaction['reference_no'] ?: 'N/A') ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <h4 class="text-base font-semibold text-gray-700 mb-4">Additional Information</h4>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Created By:</span>
                            <span class="font-medium text-gray-800">
                                <?= htmlspecialchars($transaction['created_by_name'] ?: 'N/A') ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Created At:</span>
                            <span class="font-medium text-gray-800">
                                <?= date('M j, Y g:i A', strtotime($transaction['created_at'])) ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Approved By:</span>
                            <span class="font-medium text-gray-800">
                                <?= htmlspecialchars($transaction['approved_by_name'] ?: 'Not approved yet') ?>
                            </span>
                        </div>
                        
                        <?php if ($transaction['status'] !== 'pending'): ?>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Approval Date:</span>
                            <span class="font-medium text-gray-800">
                                <?= date('M j, Y g:i A', strtotime($transaction['updated_at'])) ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-600">Last Updated:</span>
                            <span class="font-medium text-gray-800">
                                <?= date('M j, Y g:i A', strtotime($transaction['updated_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Description -->
            <div class="mt-6">
                <h4 class="text-base font-semibold text-gray-700 mb-2">Description</h4>
                <div class="bg-gray-50 p-4 rounded-md">
                    <p class="text-gray-800">
                        <?= nl2br(htmlspecialchars($transaction['description'] ?: 'No description provided.')) ?>
                    </p>
                </div>
            </div>
            
            <!-- Receipt Image (if available) -->
            <?php if (!empty($transaction['receipt_image'])): ?>
            <div class="mt-6">
                <h4 class="text-base font-semibold text-gray-700 mb-2">Receipt</h4>
                <div class="mt-2">
                    <img src="../../uploads/receipts/<?= htmlspecialchars($transaction['receipt_image']) ?>" 
                         alt="Receipt" class="max-w-full h-auto border rounded-lg shadow-sm">
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approve/Reject Modals -->
<?php if (has_permission('approve_petty_cash') && $transaction['status'] === 'pending'): ?>
<!-- Approve Modal -->
<div id="approveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Approve Transaction</h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeApproveModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <p class="mb-4">Are you sure you want to approve this transaction?</p>
        
        <form action="" method="POST">
            <input type="hidden" name="action" value="approve">
            
            <div class="mb-4">
                <label for="approve_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                <textarea id="approve_notes" name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-md" id="cancelApproveBtn">
                    Cancel
                </button>
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md">
                    Approve Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Reject Transaction</h3>
            <button type="button" class="text-gray-400 hover:text-gray-500" id="closeRejectModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <p class="mb-4">Are you sure you want to reject this transaction?</p>
        
        <form action="" method="POST">
            <input type="hidden" name="action" value="reject">
            
            <div class="mb-4">
                <label for="reject_notes" class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason (Optional)</label>
                <textarea id="reject_notes" name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"></textarea>
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-md" id="cancelRejectBtn">
                    Cancel
                </button>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-md">
                    Reject Transaction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Approve Modal
    const approveModal = document.getElementById('approveModal');
    const approveBtn = document.getElementById('approveBtn');
    const closeApproveModal = document.getElementById('closeApproveModal');
    const cancelApproveBtn = document.getElementById('cancelApproveBtn');
    
    // Reject Modal
    const rejectModal = document.getElementById('rejectModal');
    const rejectBtn = document.getElementById('rejectBtn');
    const closeRejectModal = document.getElementById('closeRejectModal');
    const cancelRejectBtn = document.getElementById('cancelRejectBtn');
    
    // Open Approve Modal
    if (approveBtn) {
        approveBtn.addEventListener('click', function() {
            approveModal.classList.remove('hidden');
        });
    }
    
    // Close Approve Modal
    if (closeApproveModal) {
        closeApproveModal.addEventListener('click', function() {
            approveModal.classList.add('hidden');
        });
    }
    
    if (cancelApproveBtn) {
        cancelApproveBtn.addEventListener('click', function() {
            approveModal.classList.add('hidden');
        });
    }
    
    // Open Reject Modal
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function() {
            rejectModal.classList.remove('hidden');
        });
    }
    
    // Close Reject Modal
    if (closeRejectModal) {
        closeRejectModal.addEventListener('click', function() {
            rejectModal.classList.add('hidden');
        });
    }
    
    if (cancelRejectBtn) {
        cancelRejectBtn.addEventListener('click', function() {
            rejectModal.classList.add('hidden');
        });
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === approveModal) {
            approveModal.classList.add('hidden');
        }
        if (e.target === rejectModal) {
            rejectModal.classList.add('hidden');
        }
    });
});
</script>
<?php endif; ?>

<?php
// Include footer
include '../../includes/footer.php';
?>