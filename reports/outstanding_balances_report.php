<?php
/**
 * Outstanding Balances Report
 * 
 * This file displays a report of all customers with outstanding balances
 */

// Set page title and include header
$page_title = "Outstanding Balances Report";
$breadcrumbs = '<a href="../index.php">Dashboard</a> / <a href="credit_reports.php">Credit Reports</a> / <span class="text-gray-700">Outstanding Balances</span>';

include_once '../includes/header.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../index.php");
    exit;
}

// Initialize variables for filtering and pagination
$search = $_GET['search'] ?? '';
$balance_type = $_GET['balance_type'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'balance_desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Base query for customers with outstanding balances
$baseQuery = "
    SELECT c.*, 
           COUNT(DISTINCT s.sale_id) as invoice_count,
           SUM(CASE WHEN s.credit_status != 'settled' AND s.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
           c.credit_limit - c.current_balance as available_credit,
           CASE
               WHEN c.current_balance > c.credit_limit THEN 'over_limit'
               WHEN c.current_balance > 0 THEN 'normal'
               ELSE 'no_balance'
           END as balance_status
    FROM credit_customers c
    LEFT JOIN sales s ON c.customer_id = s.credit_customer_id AND s.credit_status != 'settled'
    WHERE c.status = 'active'
";

$countQuery = "
    SELECT COUNT(DISTINCT c.customer_id) 
    FROM credit_customers c
    WHERE c.status = 'active'
";

$params = [];
$types = "";

// Apply search filter
if (!empty($search)) {
    $searchTerm = "%{$search}%";
    $baseQuery .= " AND (c.customer_name LIKE ? OR c.phone_number LIKE ? OR c.email LIKE ?)";
    $countQuery .= " AND (c.customer_name LIKE ? OR c.phone_number LIKE ? OR c.email LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
}

// Apply balance type filter
if ($balance_type !== 'all') {
    switch ($balance_type) {
        case 'with_balance':
            $baseQuery .= " AND c.current_balance > 0";
            $countQuery .= " AND c.current_balance > 0";
            break;
        case 'over_limit':
            $baseQuery .= " AND c.current_balance > c.credit_limit";
            $countQuery .= " AND c.current_balance > c.credit_limit";
            break;
        case 'overdue':
            $baseQuery .= " AND EXISTS (
                SELECT 1 FROM sales s 
                WHERE s.credit_customer_id = c.customer_id 
                AND s.credit_status != 'settled' 
                AND s.due_date < CURDATE()
            )";
            $countQuery .= " AND EXISTS (
                SELECT 1 FROM sales s 
                WHERE s.credit_customer_id = c.customer_id 
                AND s.credit_status != 'settled' 
                AND s.due_date < CURDATE()
            )";
            break;
        case 'near_limit':
            $baseQuery .= " AND c.current_balance > (c.credit_limit * 0.8) AND c.current_balance <= c.credit_limit";
            $countQuery .= " AND c.current_balance > (c.credit_limit * 0.8) AND c.current_balance <= c.credit_limit";
            break;
    }
}

// Group by customer
$baseQuery .= " GROUP BY c.customer_id";

// Apply sorting
switch ($sort_by) {
    case 'name_asc':
        $baseQuery .= " ORDER BY c.customer_name ASC";
        break;
    case 'name_desc':
        $baseQuery .= " ORDER BY c.customer_name DESC";
        break;
    case 'balance_asc':
        $baseQuery .= " ORDER BY c.current_balance ASC";
        break;
    case 'balance_desc':
        $baseQuery .= " ORDER BY c.current_balance DESC";
        break;
    case 'overdue_desc':
        $baseQuery .= " ORDER BY overdue_count DESC, c.current_balance DESC";
        break;
    case 'limit_util_desc':
        $baseQuery .= " ORDER BY (c.current_balance / c.credit_limit) DESC";
        break;
}

// Add pagination
$baseQuery .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Initialize variables for results
$total_records = 0;
$total_pages = 1;
$customers = [];

// Execute database queries
if (!isset($conn) || !$conn) {
    $error_message = "Database connection not available";
} else {
    // Prepare and execute query for total count
    $countStmt = $conn->prepare($countQuery);
    if ($countStmt) {
        // Prepare statement parameters for count query
        if (!empty($params) && $balance_type === 'all' && empty($search)) {
            // No parameters needed for count query in this case
        } else if (!empty($params)) {
            // Use only search parameters for count query, not pagination
            $countParams = array_slice($params, 0, -2);
            $countTypes = substr($types, 0, -2);
            
            if (!empty($countParams)) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }
        }
        
        $countStmt->execute();
        $result = $countStmt->get_result();
        $total_records = $result->fetch_row()[0];
        $total_pages = ceil($total_records / $per_page);
        $countStmt->close();
    }

    // Execute main query with pagination
    $stmt = $conn->prepare($baseQuery);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        $stmt->close();
    }
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(DISTINCT c.customer_id) as total_customers,
            SUM(c.current_balance) as total_outstanding,
            SUM(CASE WHEN c.current_balance > c.credit_limit THEN 1 ELSE 0 END) as customers_over_limit,
            SUM(CASE WHEN c.current_balance > 0 THEN 1 ELSE 0 END) as customers_with_balance,
            (SELECT COUNT(DISTINCT s.credit_customer_id) 
             FROM sales s 
             WHERE s.credit_status != 'settled' AND s.due_date < CURDATE()) as customers_overdue
        FROM credit_customers c
        WHERE c.status = 'active'
    ";
    
    $summaryResult = $conn->query($summaryQuery);
    $summary = $summaryResult ? $summaryResult->fetch_assoc() : null;
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
?>

<div class="container mx-auto pb-6">
    
    <!-- Action buttons and title row -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Outstanding Balances Report</h2>
            <p class="text-gray-600 mt-1">View all customers with outstanding credit balances</p>
        </div>
        
        <div class="flex gap-2">
            <a href="credit_reports.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Reports
            </a>
            <button onclick="window.print();" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-print mr-2"></i>
                Print Report
            </button>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <?php if ($summary): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Outstanding -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Total Outstanding</p>
            <p class="text-xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['total_outstanding'], 2) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= number_format($summary['customers_with_balance']) ?> customers with balance</p>
        </div>
        
        <!-- Customers Over Limit -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Over Credit Limit</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($summary['customers_over_limit']) ?> customers</p>
            <p class="text-xs text-gray-500 mt-1">Customers exceeding their credit limit</p>
        </div>
        
        <!-- Customers with Overdue -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Overdue Balances</p>
            <p class="text-xl font-bold text-gray-800"><?= number_format($summary['customers_overdue']) ?> customers</p>
            <p class="text-xs text-gray-500 mt-1">Customers with invoices past due date</p>
        </div>
        
        <!-- Utilization -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Avg. Credit Utilization</p>
            <?php
                // Calculate average utilization
                $avg_utilization = 0;
                if ($summary['customers_with_balance'] > 0) {
                    // Calculate based on only customers with a positive balance
                    $stmt = $conn->prepare("
                        SELECT AVG(current_balance / credit_limit) * 100
                        FROM credit_customers 
                        WHERE status = 'active' AND current_balance > 0
                    ");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $avg_utilization = $result->fetch_row()[0] ?? 0;
                    $stmt->close();
                }
            ?>
            <p class="text-xl font-bold text-gray-800"><?= number_format($avg_utilization, 1) ?>%</p>
            <p class="text-xs text-gray-500 mt-1">Average credit limit usage</p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters and search -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <!-- Search box -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                    placeholder="Name, phone, or email" 
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <!-- Balance type filter -->
            <div>
                <label for="balance_type" class="block text-sm font-medium text-gray-700 mb-1">Balance Type</label>
                <select id="balance_type" name="balance_type" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="all" <?= $balance_type === 'all' ? 'selected' : '' ?>>All Balances</option>
                    <option value="with_balance" <?= $balance_type === 'with_balance' ? 'selected' : '' ?>>With Balance</option>
                    <option value="over_limit" <?= $balance_type === 'over_limit' ? 'selected' : '' ?>>Over Credit Limit</option>
                    <option value="near_limit" <?= $balance_type === 'near_limit' ? 'selected' : '' ?>>Near Credit Limit (>80%)</option>
                    <option value="overdue" <?= $balance_type === 'overdue' ? 'selected' : '' ?>>Overdue Invoices</option>
                </select>
            </div>
            
            <!-- Sort by -->
            <div>
                <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select id="sort_by" name="sort_by" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="balance_desc" <?= $sort_by === 'balance_desc' ? 'selected' : '' ?>>Balance (High to Low)</option>
                    <option value="balance_asc" <?= $sort_by === 'balance_asc' ? 'selected' : '' ?>>Balance (Low to High)</option>
                    <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Customer Name (A-Z)</option>
                    <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Customer Name (Z-A)</option>
                    <option value="overdue_desc" <?= $sort_by === 'overdue_desc' ? 'selected' : '' ?>>Overdue Count (High to Low)</option>
                    <option value="limit_util_desc" <?= $sort_by === 'limit_util_desc' ? 'selected' : '' ?>>Utilization % (High to Low)</option>
                </select>
            </div>
            
            <!-- Filter buttons -->
            <div class="flex space-x-2">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
                <a href="outstanding_balances_report.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Customers table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">
                Customers with Outstanding Balances
                <?php if ($total_records > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format($total_records) ?> customers)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($customers)): ?>
            <div class="p-6 text-center text-gray-500">
                No customers found with the selected filters. Try adjusting your criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Open Invoices</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Overdue</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit Limit</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Balance</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Available Credit</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Utilization</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($customers as $customer): 
                            // Calculate credit utilization percentage
                            $utilization = $customer['credit_limit'] > 0 
                                ? ($customer['current_balance'] / $customer['credit_limit'] * 100) 
                                : 0;
                        ?>
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
                                            <a href="../modules/credit_management/view_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="hover:underline">
                                                <?= htmlspecialchars($customer['customer_name']) ?>
                                            </a>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= htmlspecialchars($customer['phone_number']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <span class="font-medium <?= $customer['invoice_count'] > 0 ? 'text-blue-600' : 'text-gray-500' ?>">
                                    <?= $customer['invoice_count'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <span class="font-medium <?= $customer['overdue_count'] > 0 ? 'text-red-600' : 'text-gray-500' ?>">
                                    <?= $customer['overdue_count'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?= $currency_symbol ?> <?= number_format($customer['credit_limit'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right">
                                <?php 
                                    $balance_class = 'text-gray-900';
                                    if ($customer['current_balance'] > $customer['credit_limit']) {
                                        $balance_class = 'text-red-600';
                                    } elseif ($customer['current_balance'] > 0) {
                                        $balance_class = 'text-blue-600';
                                    }
                                ?>
                                <span class="<?= $balance_class ?>">
                                    <?= $currency_symbol ?> <?= number_format($customer['current_balance'], 2) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <?php 
                                    $available_class = 'text-green-600';
                                    if ($customer['available_credit'] <= 0) {
                                        $available_class = 'text-red-600';
                                    } elseif ($customer['available_credit'] < ($customer['credit_limit'] * 0.2)) {
                                        $available_class = 'text-yellow-600';
                                    }
                                ?>
                                <span class="<?= $available_class ?>">
                                    <?= $currency_symbol ?> <?= number_format($customer['available_credit'], 2) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                    $util_class = 'bg-green-100 text-green-800';
                                    if ($utilization > 90) {
                                        $util_class = 'bg-red-100 text-red-800';
                                    } elseif ($utilization > 75) {
                                        $util_class = 'bg-yellow-100 text-yellow-800';
                                    } elseif ($utilization > 50) {
                                        $util_class = 'bg-blue-100 text-blue-800';
                                    }
                                ?>
                                <div class="flex items-center justify-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $util_class ?>">
                                        <?= number_format($utilization, 1) ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                    $status_class = 'bg-green-100 text-green-800';
                                    $status_text = 'Good';
                                    
                                    if ($customer['balance_status'] === 'over_limit') {
                                        $status_class = 'bg-red-100 text-red-800';
                                        $status_text = 'Over Limit';
                                    } elseif ($customer['overdue_count'] > 0) {
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        $status_text = 'Overdue';
                                    } elseif ($utilization > 80) {
                                        $status_class = 'bg-blue-100 text-blue-800';
                                        $status_text = 'Near Limit';
                                    } elseif ($customer['balance_status'] === 'no_balance') {
                                        $status_class = 'bg-gray-100 text-gray-800';
                                        $status_text = 'No Balance';
                                    }
                                ?>
                                <div class="flex items-center justify-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                        <?= $status_text ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right space-x-2">
                                <a href="../modules/credit_management/view_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($customer['current_balance'] > 0): ?>
                                <a href="../modules/credit_settlement/add_settlement.php?customer_id=<?= $customer['customer_id'] ?>" class="text-green-600 hover:text-green-900">
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
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&balance_type=<?= $balance_type ?>&sort_by=<?= $sort_by ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&balance_type=<?= $balance_type ?>&sort_by=<?= $sort_by ?>" 
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

<!-- Print specific styles -->
<style media="print">
    .bg-white { background-color: white !important; }
    .shadow-md { box-shadow: none !important; }
    .container { max-width: 100% !important; }
    header, footer, form, button, a.bg-gray-100, a.bg-blue-600, .no-print { display: none !important; }
    th, td { padding: 8px !important; font-size: 12px !important; }
    h2 { font-size: 18px !important; margin-bottom: 10px !important; }
    h3 { font-size: 14px !important; }
    @page { margin: 1cm; }
    
    /* Make sure table content is visible */
    .overflow-x-auto { overflow: visible !important; }
    .min-w-full { width: auto !important; }
</style>

<?php include_once '../includes/footer.php'; ?>