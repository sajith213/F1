<?php
/**
 * Petroleum Account Management Dashboard
 * 
 * Main dashboard for the petroleum account module
 */

// Set page title
$page_title = "Petroleum Account Dashboard";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Petroleum Account</a> / Dashboard';

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

// Get current account balance
$current_balance = 0;
$total_deposits = 0;
$total_withdrawals = 0;
$pending_deposits = 0;
$pending_topups_count = 0;
$pending_topups_amount = 0;

// Query for current balance (latest transaction balance_after)
$query = "SELECT balance_after FROM petroleum_account_transactions 
          WHERE status = 'completed' 
          ORDER BY transaction_id DESC LIMIT 1";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_balance = $row['balance_after'];
}

// Get total deposits and withdrawals for the current month
$current_month = date('Y-m');
$query = "SELECT 
            SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
            SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
          FROM petroleum_account_transactions 
          WHERE status = 'completed' 
          AND DATE_FORMAT(transaction_date, '%Y-%m') = '$current_month'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_deposits = $row['total_deposits'] ?: 0;
    $total_withdrawals = $row['total_withdrawals'] ?: 0;
}

// Get pending deposits
$query = "SELECT SUM(amount) as pending_amount FROM petroleum_account_transactions 
          WHERE status = 'pending' AND transaction_type = 'deposit'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $pending_deposits = $row['pending_amount'] ?: 0;
}

// Get pending top-ups
$query = "SELECT COUNT(*) as topup_count, SUM(required_amount) as topup_amount 
          FROM pending_topups 
          WHERE status = 'pending' AND deadline >= NOW()";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $pending_topups_count = $row['topup_count'] ?: 0;
    $pending_topups_amount = $row['topup_amount'] ?: 0;
}

// Get recent transactions (last 5)
$recent_transactions = [];
$query = "SELECT 
            t.transaction_id, 
            t.transaction_date, 
            t.transaction_type, 
            t.amount, 
            t.balance_after, 
            t.status, 
            t.reference_type,
            t.description,
            u.full_name as created_by
          FROM petroleum_account_transactions t
          LEFT JOIN users u ON t.created_by = u.user_id
          ORDER BY t.transaction_date DESC
          LIMIT 5";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}

// Get recent purchase orders (last 5)
$recent_orders = [];
// FIXED QUERY: Join with po_items to get fuel_type and quantity information
$query = "SELECT 
            po.po_id, 
            po.po_number, 
            po.order_date, 
            po.total_amount, 
            po.status,
            po.account_check_status, 
            po.topup_deadline,
            s.supplier_name,
            ft.fuel_name,
            pi.quantity
          FROM purchase_orders po
          JOIN suppliers s ON po.supplier_id = s.supplier_id
          JOIN po_items pi ON po.po_id = pi.po_id
          JOIN fuel_types ft ON pi.fuel_type_id = ft.fuel_type_id
          GROUP BY po.po_id
          ORDER BY po.order_date DESC
          LIMIT 5";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Get minimum required balance from settings
$min_balance = 0;
$query = "SELECT setting_value FROM system_settings WHERE setting_name = 'min_account_balance'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $min_balance = $row['setting_value'];
}

// Use the system's format_currency function or import from functions.php
require_once __DIR__ . '/../../includes/functions.php';

// If we need a different version of format_currency, rename it to avoid conflicts
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
?>

<!-- Dashboard Content -->
<div class="mb-6">
    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="add_transaction.php?type=deposit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
            <i class="fas fa-plus-circle mr-2"></i> Add Deposit
        </a>
        <a href="add_transaction.php?type=withdrawal" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
            <i class="fas fa-minus-circle mr-2"></i> Record Withdrawal
        </a>
        <a href="transactions.php" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
            <i class="fas fa-list mr-2"></i> View All Transactions
        </a>
        <a href="pending_topups.php" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-opacity-50">
            <i class="fas fa-exclamation-circle mr-2"></i> Pending Top-ups
            <?php if ($pending_topups_count > 0): ?>
            <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full ml-2"><?= $pending_topups_count ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Current Balance -->
        <div class="bg-white rounded-lg shadow p-5 border-l-4 <?= $current_balance >= $min_balance ? 'border-green-500' : 'border-red-500' ?>">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Current Balance</p>
                    <h3 class="text-2xl font-bold <?= $current_balance >= $min_balance ? 'text-green-600' : 'text-red-600' ?>">
                        <?= format_currency($current_balance) ?>
                    </h3>
                </div>
                <div class="p-3 <?= $current_balance >= $min_balance ? 'bg-green-100' : 'bg-red-100' ?> rounded-full">
                    <i class="fas fa-wallet <?= $current_balance >= $min_balance ? 'text-green-500' : 'text-red-500' ?>"></i>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-500">
                    Minimum Required: <?= format_currency($min_balance) ?>
                </p>
            </div>
        </div>
        
        <!-- Monthly Deposits -->
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Monthly Deposits</p>
                    <h3 class="text-2xl font-bold text-blue-600"><?= format_currency($total_deposits) ?></h3>
                </div>
                <div class="p-3 bg-blue-100 rounded-full">
                    <i class="fas fa-arrow-circle-up text-blue-500"></i>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-500">
                    This Month (<?= date('F Y') ?>)
                </p>
            </div>
        </div>
        
        <!-- Monthly Withdrawals -->
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-orange-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Monthly Withdrawals</p>
                    <h3 class="text-2xl font-bold text-orange-600"><?= format_currency($total_withdrawals) ?></h3>
                </div>
                <div class="p-3 bg-orange-100 rounded-full">
                    <i class="fas fa-arrow-circle-down text-orange-500"></i>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-500">
                    This Month (<?= date('F Y') ?>)
                </p>
            </div>
        </div>
        
        <!-- Pending Top-ups -->
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Pending Top-ups</p>
                    <h3 class="text-2xl font-bold text-yellow-600"><?= format_currency($pending_topups_amount) ?></h3>
                </div>
                <div class="p-3 bg-yellow-100 rounded-full">
                    <i class="fas fa-exclamation-circle text-yellow-500"></i>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-500">
                    <?= $pending_topups_count ?> top-ups required
                </p>
            </div>
        </div>
    </div>

    <!-- Recent Transactions and Purchase Orders -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Recent Transactions -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Recent Transactions</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_transactions)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No transactions found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($transaction['transaction_date'])) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($transaction['transaction_type'] == 'deposit'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Deposit
                                    </span>
                                    <?php elseif ($transaction['transaction_type'] == 'withdrawal'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Withdrawal
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Adjustment
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?= format_currency($transaction['amount']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?= format_currency($transaction['balance_after']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($transaction['status'] == 'completed'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Completed
                                    </span>
                                    <?php elseif ($transaction['status'] == 'pending'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Pending
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Cancelled
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="py-2 px-4 border-t border-gray-200 bg-gray-50">
                <a href="transactions.php" class="text-sm text-blue-600 hover:text-blue-800">
                    View all transactions <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Recent Purchase Orders -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-800">Recent Purchase Orders</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_orders)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No purchase orders found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?= $order['po_number'] ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($order['order_date'])) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?= format_currency($order['total_amount']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($order['status'] == 'pending'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Pending
                                    </span>
                                    <?php elseif ($order['status'] == 'delivered'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Delivered
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Cancelled
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php if ($order['account_check_status'] == 'sufficient'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Sufficient
                                    </span>
                                    <?php elseif ($order['account_check_status'] == 'insufficient'): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Insufficient
                                        <?php if (!empty($order['topup_deadline'])): ?>
                                        <i class="fas fa-clock ml-1" title="Top-up deadline: <?= date('M d, Y H:i', strtotime($order['topup_deadline'])) ?>"></i>
                                        <?php endif; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Not Checked
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="py-2 px-4 border-t border-gray-200 bg-gray-50">
                <a href="../fuel_ordering/index.php" class="text-sm text-blue-600 hover:text-blue-800">
                    View all purchase orders <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>