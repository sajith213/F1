<?php
/**
 * Pending Top-ups
 * 
 * Lists all pending top-ups required for purchase orders
 */

// Set page title
$page_title = "Pending Account Top-ups";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Petroleum Account</a> / Pending Top-ups';

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

// Process actions (mark as completed)
if (isset($_GET['action']) && $_GET['action'] === 'complete' && isset($_GET['id'])) {
    $topup_id = (int)$_GET['id'];
    
    // Begin transaction for data consistency
    $conn->begin_transaction();
    
    try {
        // Get the required amount and PO details before updating status
        $sql = "SELECT pt.topup_id, pt.po_id, pt.required_amount, po.po_number 
                FROM pending_topups pt
                JOIN purchase_orders po ON pt.po_id = po.po_id 
                WHERE pt.topup_id = ? AND pt.status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $topup_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Top-up not found or already completed.");
        }
        
        $topup_data = $result->fetch_assoc();
        $required_amount = $topup_data['required_amount'];
        $po_id = $topup_data['po_id'];
        $po_number = $topup_data['po_number'];
        
        // Get current account balance
        $sql = "SELECT balance_after FROM petroleum_account_transactions 
                WHERE status = 'completed' 
                ORDER BY transaction_id DESC LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            throw new Exception("Unable to determine current account balance.");
        }
        
        $row = $result->fetch_assoc();
        $current_balance = $row['balance_after'];
        
        // Check if balance is sufficient
        if ($current_balance < $required_amount) {
            throw new Exception("Insufficient account balance to complete this top-up.");
        }
        
        // Calculate new balance
        $new_balance = $current_balance - $required_amount;
        
        // Insert withdrawal transaction
        $transaction_date = date('Y-m-d H:i:s');
        $description = "Withdrawal for Purchase Order #$po_number";
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
                ) VALUES (?, 'withdrawal', ?, ?, ?, 'purchase_order', ?, 'completed', ?)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'sddsis',
            $transaction_date,
            $required_amount,
            $new_balance,
            $po_number,
            $description,
            $_SESSION['user_id']
        );
        $stmt->execute();
        
        // Update the topup status
        $sql = "UPDATE pending_topups 
                SET status = 'completed', completed_at = NOW() 
                WHERE topup_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $topup_id);
        $stmt->execute();
        
        // Update the purchase order account status
        $sql = "UPDATE purchase_orders 
                SET account_check_status = 'sufficient' 
                WHERE po_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $po_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p>Top-up marked as completed and ' . format_currency($required_amount) . ' has been deducted from the account.</p>
              </div>';
        
    } catch (Exception $e) {
        // Roll back on error
        $conn->rollback();
        
        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>Error: ' . $e->getMessage() . '</p>
              </div>';
    }
}
// Get all pending top-ups
$pending_topups = [];
// FIXED QUERY: Join with po_items to get fuel_type and quantity information
$sql = "SELECT 
            pt.topup_id,
            pt.po_id,
            pt.required_amount,
            pt.deadline,
            pt.status,
            pt.created_at,
            po.po_number,
            po.total_amount,
            s.supplier_name,
            ft.fuel_name,
            pi.quantity,
            po.status as po_status
        FROM pending_topups pt
        JOIN purchase_orders po ON pt.po_id = po.po_id
        JOIN suppliers s ON po.supplier_id = s.supplier_id
        JOIN po_items pi ON po.po_id = pi.po_id
        JOIN fuel_types ft ON pi.fuel_type_id = ft.fuel_type_id
        WHERE pt.status = 'pending'
        GROUP BY pt.topup_id
        ORDER BY pt.deadline ASC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pending_topups[] = $row;
    }
}

// If you need a custom version, use a different name
function format_account_currency($amount) {
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

// Get current account balance
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
    <!-- Quick action buttons -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="add_transaction.php?type=deposit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
            <i class="fas fa-plus-circle mr-2"></i> Add Deposit
        </a>
        <a href="index.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>

    <!-- Current balance -->
    <div class="bg-white rounded-lg shadow-md p-5 mb-6 border-l-4 <?= $current_balance > 0 ? 'border-green-500' : 'border-red-500' ?>">
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Current Account Balance</h3>
                <p class="text-2xl font-bold <?= $current_balance > 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= format_currency($current_balance) ?>
                </p>
            </div>
            <div>
                <a href="add_transaction.php?type=deposit" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-plus-circle mr-2"></i> Top-up Account
                </a>
            </div>
        </div>
    </div>

    <!-- Pending top-ups table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Pending Account Top-ups</h3>
            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                <?= count($pending_topups) ?> Pending
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required Top-up</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($pending_topups)): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-4 text-center text-sm text-gray-500">No pending top-ups found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($pending_topups as $topup): ?>
                        <tr class="<?= strtotime($topup['deadline']) < time() ? 'bg-red-50' : '' ?>">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="../fuel_ordering/view_order.php?id=<?= $topup['po_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <?= htmlspecialchars($topup['po_number']) ?>
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($topup['supplier_name']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($topup['fuel_name']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?= number_format($topup['quantity'], 2) ?> L
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= format_currency($topup['total_amount']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-red-600">
                                <?= format_currency($topup['required_amount']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm <?= strtotime($topup['deadline']) < time() ? 'text-red-600 font-medium' : 'text-gray-500' ?>">
                                <?= date('M d, Y H:i', strtotime($topup['deadline'])) ?>
                                <?php if (strtotime($topup['deadline']) < time()): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Expired
                                </span>
                                <?php else: ?>
                                <span class="text-xs text-gray-500">
                                    (<?= ceil((strtotime($topup['deadline']) - time()) / 3600) ?> hours left)
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if ($topup['po_status'] === 'pending'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    Order Pending
                                </span>
                                <?php elseif ($topup['po_status'] === 'delivered'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Order Delivered
                                </span>
                                <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Order Cancelled
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($topup['po_status'] !== 'cancelled'): ?>
                                <a href="?action=complete&id=<?= $topup['topup_id'] ?>" class="text-green-600 hover:text-green-900 mr-3" onclick="return confirm('Mark this top-up as completed? This will update the purchase order status.');">
                                    <i class="fas fa-check mr-1"></i> Mark Completed
                                </a>
                                <?php endif; ?>
                                
                                <a href="add_transaction.php?type=deposit" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-plus-circle mr-1"></i> Add Deposit
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>