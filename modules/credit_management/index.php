<?php
/**
 * Credit Management - Credit Customers Index
 * 
 * This file serves as the main entry point for the credit management module
 * and displays a list of credit customers with search, filter, and pagination
 */

// Set page title and include header
$page_title = "Credit Management";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <span class="text-gray-700">Credit Management</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize variables for filtering and pagination
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$balance = $_GET['balance'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Base query for customers
$baseQuery = "
    SELECT c.*, 
           COALESCE(SUM(CASE WHEN t.transaction_type = 'sale' THEN t.amount ELSE 0 END), 0) as total_sales,
           COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) as total_payments,
           COUNT(DISTINCT s.settlement_id) as payment_count
    FROM credit_customers c
    LEFT JOIN credit_transactions t ON c.customer_id = t.customer_id
    LEFT JOIN credit_settlements s ON c.customer_id = s.customer_id
    WHERE 1=1
";

$countQuery = "SELECT COUNT(*) FROM credit_customers c WHERE 1=1";
$params = [];
$types = "";

// Apply search filter
if (!empty($search)) {
    $searchTerm = "%{$search}%";
    $baseQuery .= " AND (c.customer_name LIKE ? OR c.phone_number LIKE ? OR c.email LIKE ?)";
    $countQuery .= " AND (c.customer_name LIKE ? OR c.phone_number LIKE ? OR c.email LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// Apply status filter
if (!empty($status)) {
    $baseQuery .= " AND c.status = ?";
    $countQuery .= " AND c.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Apply balance filter
if (!empty($balance)) {
    switch ($balance) {
        case 'overdue':
            $baseQuery .= " AND c.current_balance > 0 AND EXISTS (
                SELECT 1 FROM sales s 
                WHERE s.credit_customer_id = c.customer_id 
                AND s.credit_status != 'settled' 
                AND s.due_date < CURDATE()
            )";
            $countQuery .= " AND c.current_balance > 0 AND EXISTS (
                SELECT 1 FROM sales s 
                WHERE s.credit_customer_id = c.customer_id 
                AND s.credit_status != 'settled' 
                AND s.due_date < CURDATE()
            )";
            break;
        case 'with_balance':
            $baseQuery .= " AND c.current_balance > 0";
            $countQuery .= " AND c.current_balance > 0";
            break;
        case 'no_balance':
            $baseQuery .= " AND c.current_balance = 0";
            $countQuery .= " AND c.current_balance = 0";
            break;
        case 'over_limit':
            $baseQuery .= " AND c.current_balance > c.credit_limit";
            $countQuery .= " AND c.current_balance > c.credit_limit";
            break;
    }
}

// Group by customer and add sorting
$baseQuery .= " GROUP BY c.customer_id ORDER BY c.customer_name ASC";

// Add pagination
$baseQuery .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Initialize variables for results
$total_records = 0;
$total_pages = 1;
$customers = [];

// Execute database queries if connection exists
if (!isset($conn) || !$conn) {
    $error_message = "Database connection not available";
} else {
    // Prepare and execute query for total count
    $countStmt = $conn->prepare($countQuery);
    if ($countStmt) {
        // Remove the last two params which are for pagination
        $countParams = array_slice($params, 0, -2);
        $countTypes = substr($types, 0, -2);
        
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $result = $countStmt->get_result();
        $total_records = $result->fetch_row()[0];
        $total_pages = ceil($total_records / $per_page);
        $countStmt->close();
    }

    // Execute main query with pagination
    if ($total_records > 0) {
        $stmt = $conn->prepare($baseQuery);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $customers[] = $row;
            }
            $stmt->close();
        }
    }
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_customers,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_customers,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_customers,
            SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_customers,
            SUM(current_balance) as total_outstanding,
            SUM(credit_limit) as total_credit_limit
        FROM credit_customers
    ";
    
    $summaryResult = $conn->query($summaryQuery);
    $summary = $summaryResult ? $summaryResult->fetch_assoc() : null;
}

// Check if we need to show a success message
$success_message = $_SESSION['success_message'] ?? '';
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

// Get error message if exists
$error_message = $_SESSION['error_message'] ?? '';
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
}

// Get currency symbol from settings
$currency_symbol = '$';
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currency_symbol = $row['setting_value'];
    }
    $stmt->close();
}

// Ensure values are not null before formatting
if ($summary) {
    $summary['total_customers'] = $summary['total_customers'] ?? 0;
    $summary['active_customers'] = $summary['active_customers'] ?? 0;
    $summary['inactive_customers'] = $summary['inactive_customers'] ?? 0;
    $summary['blocked_customers'] = $summary['blocked_customers'] ?? 0;
    $summary['total_outstanding'] = $summary['total_outstanding'] ?? 0;
    $summary['total_credit_limit'] = $summary['total_credit_limit'] ?? 0;
}
?>

<div class="container mx-auto pb-6">
    
    <!-- Action buttons and title row -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Credit Management</h2>
        
        <div class="flex gap-2">
            <a href="add_credit_customer.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>
                Add New Customer
            </a>
            <a href="../credit_settlement/credit_settlements.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-money-bill-wave mr-2"></i>
                Settlements
            </a>
        </div>
    </div>
    
    <!-- Success Message -->
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Error Message -->
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
        <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <?php if ($summary): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Customers -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Customers</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_customers']) ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-users text-blue-500"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Active: <?= number_format($summary['active_customers']) ?> |
                Inactive: <?= number_format($summary['inactive_customers']) ?> |
                Blocked: <?= number_format($summary['blocked_customers']) ?>
            </p>
        </div>
        
        <!-- Total Outstanding Balance -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Outstanding Balance</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['total_outstanding'], 2) ?></p>
                </div>
                <div class="bg-red-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-red-500"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Total credit extended to customers
            </p>
        </div>
        
        <!-- Total Credit Limit -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Credit Limit</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['total_credit_limit'], 2) ?></p>
                </div>
                <div class="bg-green-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-credit-card text-green-500"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Maximum credit available to extend
            </p>
        </div>
        
        <!-- Credit Utilization -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Credit Utilization</p>
                    <?php
                    $utilization = $summary['total_credit_limit'] > 0 
                        ? ($summary['total_outstanding'] / $summary['total_credit_limit'] * 100) 
                        : 0;
                    ?>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($utilization, 1) ?>%</p>
                </div>
                <div class="bg-yellow-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-chart-pie text-yellow-500"></i>
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <div class="bg-yellow-500 h-2.5 rounded-full" style="width: <?= min(100, $utilization) ?>%"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters and search -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Search box -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                    placeholder="Search by name, phone, or email" 
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <!-- Status filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>
            </div>
            
            <!-- Balance filter -->
            <div>
                <label for="balance" class="block text-sm font-medium text-gray-700 mb-1">Balance</label>
                <select id="balance" name="balance" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="">All Balances</option>
                    <option value="with_balance" <?= $balance === 'with_balance' ? 'selected' : '' ?>>With Balance</option>
                    <option value="no_balance" <?= $balance === 'no_balance' ? 'selected' : '' ?>>No Balance</option>
                    <option value="overdue" <?= $balance === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <option value="over_limit" <?= $balance === 'over_limit' ? 'selected' : '' ?>>Over Credit Limit</option>
                </select>
            </div>
            
            <!-- Search button -->
            <div class="flex items-end md:col-span-3 justify-end space-x-3">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="index.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Customers table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <h3 class="text-xl font-semibold text-gray-800">
                Customer List
                <?php if ($total_records > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format($total_records) ?> customers)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($customers)): ?>
            <div class="p-6 text-center text-gray-500">
                No credit customers found. Try adjusting your search criteria or 
                <a href="add_credit_customer.php" class="text-blue-600 hover:text-blue-800">add a new customer</a>.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit Limit</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Balance</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Available Credit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($customers as $customer): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span class="text-blue-600 font-bold">
                                            <?= strtoupper(substr($customer['customer_name'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <a href="view_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="hover:underline">
                                                <?= htmlspecialchars($customer['customer_name']) ?>
                                            </a>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            ID: <?= $customer['customer_id'] ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <i class="fas fa-phone text-gray-400 mr-1"></i> <?= htmlspecialchars($customer['phone_number']) ?>
                                </div>
                                <?php if (!empty($customer['email'])): ?>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-envelope text-gray-400 mr-1"></i> <?= htmlspecialchars($customer['email']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                <?= $currency_symbol ?> <?= number_format($customer['credit_limit'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <?php
                                $balanceClass = 'text-gray-500';
                                if ($customer['current_balance'] > 0) {
                                    $balanceClass = 'text-red-600 font-semibold';
                                    if ($customer['current_balance'] > $customer['credit_limit']) {
                                        $balanceClass = 'text-red-600 font-bold';
                                    }
                                }
                                ?>
                                <span class="<?= $balanceClass ?>">
                                    <?= $currency_symbol ?> <?= number_format($customer['current_balance'], 2) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <?php
                                $availableCredit = $customer['credit_limit'] - $customer['current_balance'];
                                $creditClass = 'text-green-600 font-semibold';
                                if ($availableCredit <= 0) {
                                    $creditClass = 'text-red-600 font-semibold';
                                } elseif ($availableCredit < ($customer['credit_limit'] * 0.2)) {
                                    $creditClass = 'text-yellow-600 font-semibold';
                                }
                                ?>
                                <span class="<?= $creditClass ?>">
                                    <?= $currency_symbol ?> <?= number_format($availableCredit, 2) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php 
                                $statusBadge = '';
                                switch ($customer['status']) {
                                    case 'active':
                                        $statusBadge = 'bg-green-100 text-green-800';
                                        break;
                                    case 'inactive':
                                        $statusBadge = 'bg-gray-100 text-gray-800';
                                        break;
                                    case 'blocked':
                                        $statusBadge = 'bg-red-100 text-red-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusBadge ?>">
                                    <?= ucfirst($customer['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php if ($customer['current_balance'] > 0): ?>
                                <a href="../credit_settlement/add_settlement.php?customer_id=<?= $customer['customer_id'] ?>" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-money-bill-wave"></i> Settle
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $per_page, $total_records)) ?> of <?= number_format($total_records) ?> customers
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&balance=<?= $balance ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&balance=<?= $balance ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>