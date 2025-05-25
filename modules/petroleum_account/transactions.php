<?php
/**
 * Petroleum Account Transactions
 * 
 * Lists all transactions with filtering options
 */

// Set page title
$page_title = "Petroleum Account Transactions";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Petroleum Account</a> / Transactions';

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

// Initialize filters
$where_clauses = [];
$params = [];
$param_types = '';

// Process filter inputs
$filter_type = $_GET['filter_type'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_date_from = $_GET['filter_date_from'] ?? '';
$filter_date_to = $_GET['filter_date_to'] ?? '';

if (!empty($filter_type)) {
    $where_clauses[] = 'transaction_type = ?';
    $params[] = $filter_type;
    $param_types .= 's';
}

if (!empty($filter_status)) {
    $where_clauses[] = 'status = ?';
    $params[] = $filter_status;
    $param_types .= 's';
}

if (!empty($filter_date_from)) {
    $where_clauses[] = 'transaction_date >= ?';
    $params[] = $filter_date_from . ' 00:00:00';
    $param_types .= 's';
}

if (!empty($filter_date_to)) {
    $where_clauses[] = 'transaction_date <= ?';
    $params[] = $filter_date_to . ' 23:59:59';
    $param_types .= 's';
}

// Build WHERE clause
$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM petroleum_account_transactions $where_sql";
$total_records = 0;

if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $count_result = $stmt->get_result();
    if ($row = $count_result->fetch_assoc()) {
        $total_records = $row['total'];
    }
    $stmt->close();
} else {
    $count_result = $conn->query($count_sql);
    if ($row = $count_result->fetch_assoc()) {
        $total_records = $row['total'];
    }
}

$total_pages = ceil($total_records / $per_page);

// Get transactions
$transactions = [];
$sql = "SELECT 
            t.transaction_id, 
            t.transaction_date, 
            t.transaction_type, 
            t.amount, 
            t.balance_after, 
            t.reference_no, 
            t.reference_type, 
            t.description, 
            t.status,
            u1.full_name as created_by,
            u2.full_name as approved_by,
            t.created_at
        FROM petroleum_account_transactions t
        LEFT JOIN users u1 ON t.created_by = u1.user_id
        LEFT JOIN users u2 ON t.approved_by = u2.user_id
        $where_sql
        ORDER BY t.transaction_date DESC
        LIMIT ?, ?";

$param_types .= 'ii';
$params[] = $offset;
$params[] = $per_page;

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

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
?>

<!-- Page content -->
<div class="mb-6">
    <!-- Action buttons -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="add_transaction.php?type=deposit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
            <i class="fas fa-plus-circle mr-2"></i> Add Deposit
        </a>
        <a href="add_transaction.php?type=withdrawal" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
            <i class="fas fa-minus-circle mr-2"></i> Record Withdrawal
        </a>
        <a href="index.php" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
        </a>
    </div>

    <!-- Filter form -->
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Filter Transactions</h3>
        <form action="" method="GET" class="space-y-4 md:space-y-0 md:flex md:flex-wrap md:gap-4">
            <div class="md:w-auto">
                <label for="filter_type" class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                <select id="filter_type" name="filter_type" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Types</option>
                    <option value="deposit" <?= $filter_type === 'deposit' ? 'selected' : '' ?>>Deposits</option>
                    <option value="withdrawal" <?= $filter_type === 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                    <option value="adjustment" <?= $filter_type === 'adjustment' ? 'selected' : '' ?>>Adjustments</option>
                </select>
            </div>
            <div class="md:w-auto">
                <label for="filter_status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="filter_status" name="filter_status" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Statuses</option>
                    <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="md:w-auto">
                <label for="filter_date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" id="filter_date_from" name="filter_date_from" value="<?= htmlspecialchars($filter_date_from) ?>" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            <div class="md:w-auto">
                <label for="filter_date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" id="filter_date_to" name="filter_date_to" value="<?= htmlspecialchars($filter_date_to) ?>" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            <div class="md:flex md:items-end">
                <button type="submit" class="w-full md:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="transactions.php" class="inline-block mt-2 md:mt-0 md:ml-2 w-full md:w-auto px-4 py-2 bg-gray-600 text-white text-center rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Transactions table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Transaction History</h3>
            <span class="text-sm text-gray-600">Total: <?= $total_records ?> transactions</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-4 text-center text-sm text-gray-500">No transactions found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= $transaction['transaction_id'] ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y H:i', strtotime($transaction['transaction_date'])) ?>
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
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium 
                                <?= $transaction['transaction_type'] == 'deposit' ? 'text-green-600' : 
                                   ($transaction['transaction_type'] == 'withdrawal' ? 'text-red-600' : 'text-gray-900') ?>">
                                <?= format_currency($transaction['amount']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?= format_currency($transaction['balance_after']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($transaction['reference_no'])): ?>
                                <span title="<?= htmlspecialchars($transaction['description'] ?? '') ?>">
                                    <?= htmlspecialchars($transaction['reference_type'] ?? '') ?>: <?= htmlspecialchars($transaction['reference_no']) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
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
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($transaction['created_by'] ?? 'System') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <a href="view_transaction.php?id=<?= $transaction['transaction_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($transaction['status'] == 'pending' && has_permission('approve_petroleum_account')): ?>
                                <a href="approve_transaction.php?id=<?= $transaction['transaction_id'] ?>" class="text-green-600 hover:text-green-900 mr-3">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($transaction['status'] == 'pending'): ?>
                                <a href="cancel_transaction.php?id=<?= $transaction['transaction_id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to cancel this transaction?');">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&filter_type=<?= urlencode($filter_type) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_date_from=<?= urlencode($filter_date_from) ?>&filter_date_to=<?= urlencode($filter_date_to) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&filter_type=<?= urlencode($filter_type) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_date_from=<?= urlencode($filter_date_from) ?>&filter_date_to=<?= urlencode($filter_date_to) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?= min(($page - 1) * $per_page + 1, $total_records) ?></span> to <span class="font-medium"><?= min($page * $per_page, $total_records) ?></span> of <span class="font-medium"><?= $total_records ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&filter_type=<?= urlencode($filter_type) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_date_from=<?= urlencode($filter_date_from) ?>&filter_date_to=<?= urlencode($filter_date_to) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, min($page - 2, $total_pages - 4));
                            $end_page = min($total_pages, max(5, $page + 2));
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <a href="?page=<?= $i ?>&filter_type=<?= urlencode($filter_type) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_date_from=<?= urlencode($filter_date_from) ?>&filter_date_to=<?= urlencode($filter_date_to) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i === $page ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&filter_type=<?= urlencode($filter_type) ?>&filter_status=<?= urlencode($filter_status) ?>&filter_date_from=<?= urlencode($filter_date_from) ?>&filter_date_to=<?= urlencode($filter_date_to) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>