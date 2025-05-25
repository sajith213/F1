<?php
/**
 * Overdue Credits Report
 * 
 * This file displays a report of all customers with overdue credit balances
 */

// Set page title and include header
$page_title = "Overdue Credits Report";
$breadcrumbs = '<a href="../index.php">Dashboard</a> / <a href="credit_reports.php">Credit Reports</a> / <span class="text-gray-700">Overdue Credits</span>';

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
$days_overdue = $_GET['days_overdue'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'days_desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Base query for overdue invoices
$baseQuery = "
    SELECT 
        s.sale_id, 
        s.invoice_number, 
        s.sale_date, 
        s.due_date, 
        s.net_amount,
        DATEDIFF(CURRENT_DATE, s.due_date) as days_overdue,
        c.customer_id, 
        c.customer_name, 
        c.phone_number, 
        c.credit_limit,
        c.current_balance,
        (SELECT COALESCE(SUM(t.amount), 0)
         FROM credit_transactions t
         WHERE t.sale_id = s.sale_id AND t.transaction_type = 'payment') as amount_paid,
        (s.net_amount - (SELECT COALESCE(SUM(t.amount), 0)
                         FROM credit_transactions t
                         WHERE t.sale_id = s.sale_id AND t.transaction_type = 'payment')) as balance_due
    FROM sales s
    JOIN credit_customers c ON s.credit_customer_id = c.customer_id
    WHERE s.credit_status != 'settled' 
    AND s.due_date < CURRENT_DATE
    AND c.status = 'active'
";

$countQuery = "
    SELECT COUNT(*) 
    FROM sales s
    JOIN credit_customers c ON s.credit_customer_id = c.customer_id
    WHERE s.credit_status != 'settled' 
    AND s.due_date < CURRENT_DATE
    AND c.status = 'active'
";

$params = [];
$types = "";

// Apply search filter
if (!empty($search)) {
    $searchTerm = "%{$search}%";
    $baseQuery .= " AND (c.customer_name LIKE ? OR c.phone_number LIKE ? OR s.invoice_number LIKE ?)";
    $countQuery .= " AND (c.customer_name LIKE ? OR c.phone_number LIKE ? OR s.invoice_number LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
}

// Apply days overdue filter
if ($days_overdue !== 'all') {
    switch ($days_overdue) {
        case '1-7':
            $baseQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 1 AND 7";
            $countQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 1 AND 7";
            break;
        case '8-15':
            $baseQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 8 AND 15";
            $countQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 8 AND 15";
            break;
        case '16-30':
            $baseQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 16 AND 30";
            $countQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 16 AND 30";
            break;
        case '31-60':
            $baseQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 31 AND 60";
            $countQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 31 AND 60";
            break;
        case '61-90':
            $baseQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 61 AND 90";
            $countQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 61 AND 90";
            break;
        case '90+':
            $baseQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) > 90";
            $countQuery .= " AND DATEDIFF(CURRENT_DATE, s.due_date) > 90";
            break;
    }
}

// Apply sorting
switch ($sort_by) {
    case 'days_asc':
        $baseQuery .= " ORDER BY days_overdue ASC";
        break;
    case 'days_desc':
        $baseQuery .= " ORDER BY days_overdue DESC";
        break;
    case 'due_date_asc':
        $baseQuery .= " ORDER BY s.due_date ASC";
        break;
    case 'due_date_desc':
        $baseQuery .= " ORDER BY s.due_date DESC";
        break;
    case 'amount_desc':
        $baseQuery .= " ORDER BY balance_due DESC";
        break;
    case 'amount_asc':
        $baseQuery .= " ORDER BY balance_due ASC";
        break;
    case 'customer_asc':
        $baseQuery .= " ORDER BY c.customer_name ASC";
        break;
    case 'customer_desc':
        $baseQuery .= " ORDER BY c.customer_name DESC";
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
$overdue_invoices = [];

// Execute database queries
if (!isset($conn) || !$conn) {
    $error_message = "Database connection not available";
} else {
    // Prepare and execute query for total count
    $countStmt = $conn->prepare($countQuery);
    if ($countStmt) {
        // Prepare statement parameters for count query
        if (!empty($params) && $days_overdue === 'all' && empty($search)) {
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
            $overdue_invoices[] = $row;
        }
        $stmt->close();
    }
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(DISTINCT s.sale_id) as total_invoices,
            COUNT(DISTINCT s.credit_customer_id) as total_customers,
            SUM(s.net_amount - (SELECT COALESCE(SUM(t.amount), 0)
                               FROM credit_transactions t
                               WHERE t.sale_id = s.sale_id AND t.transaction_type = 'payment')) as total_overdue,
            MIN(DATEDIFF(CURRENT_DATE, s.due_date)) as min_days_overdue,
            MAX(DATEDIFF(CURRENT_DATE, s.due_date)) as max_days_overdue,
            AVG(DATEDIFF(CURRENT_DATE, s.due_date)) as avg_days_overdue,
            SUM(CASE WHEN DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as days_1_7,
            SUM(CASE WHEN DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 8 AND 15 THEN 1 ELSE 0 END) as days_8_15,
            SUM(CASE WHEN DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 16 AND 30 THEN 1 ELSE 0 END) as days_16_30,
            SUM(CASE WHEN DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) as days_31_60,
            SUM(CASE WHEN DATEDIFF(CURRENT_DATE, s.due_date) BETWEEN 61 AND 90 THEN 1 ELSE 0 END) as days_61_90,
            SUM(CASE WHEN DATEDIFF(CURRENT_DATE, s.due_date) > 90 THEN 1 ELSE 0 END) as days_90_plus
        FROM sales s
        JOIN credit_customers c ON s.credit_customer_id = c.customer_id
        WHERE s.credit_status != 'settled' 
        AND s.due_date < CURRENT_DATE
        AND c.status = 'active'
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
            <h2 class="text-2xl font-bold text-gray-800">Overdue Credits Report</h2>
            <p class="text-gray-600 mt-1">View all overdue invoices and follow up with customers</p>
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Overdue Invoices -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Overdue Invoices</p>
            <p class="text-2xl font-bold text-gray-800"><?= number_format($summary['total_invoices']) ?></p>
            <p class="text-xs text-gray-500 mt-1">From <?= number_format($summary['total_customers']) ?> customers</p>
        </div>
        
        <!-- Total Overdue Amount -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Total Overdue Amount</p>
            <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['total_overdue'], 2) ?></p>
            <p class="text-xs text-gray-500 mt-1">Outstanding credit payments</p>
        </div>
        
        <!-- Overdue Days Range -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Overdue Days Range</p>
            <p class="text-2xl font-bold text-gray-800"><?= number_format($summary['min_days_overdue']) ?> - <?= number_format($summary['max_days_overdue']) ?></p>
            <p class="text-xs text-gray-500 mt-1">Average: <?= number_format($summary['avg_days_overdue'], 1) ?> days</p>
        </div>
        
        <!-- Aging Distribution -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Aging Distribution</p>
            <div class="mt-2 space-y-1">
                <div class="flex justify-between text-xs">
                    <span>1-7 days:</span>
                    <span class="font-medium"><?= number_format($summary['days_1_7']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span>8-15 days:</span>
                    <span class="font-medium"><?= number_format($summary['days_8_15']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span>16-30 days:</span>
                    <span class="font-medium"><?= number_format($summary['days_16_30']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span>31-60 days:</span>
                    <span class="font-medium"><?= number_format($summary['days_31_60']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span>61-90 days:</span>
                    <span class="font-medium"><?= number_format($summary['days_61_90']) ?></span>
                </div>
                <div class="flex justify-between text-xs">
                    <span>90+ days:</span>
                    <span class="font-medium"><?= number_format($summary['days_90_plus']) ?></span>
                </div>
            </div>
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
                    placeholder="Customer name, phone, invoice..." 
                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <!-- Days Overdue filter -->
            <div>
                <label for="days_overdue" class="block text-sm font-medium text-gray-700 mb-1">Days Overdue</label>
                <select id="days_overdue" name="days_overdue" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="all" <?= $days_overdue === 'all' ? 'selected' : '' ?>>All Overdue</option>
                    <option value="1-7" <?= $days_overdue === '1-7' ? 'selected' : '' ?>>1-7 days</option>
                    <option value="8-15" <?= $days_overdue === '8-15' ? 'selected' : '' ?>>8-15 days</option>
                    <option value="16-30" <?= $days_overdue === '16-30' ? 'selected' : '' ?>>16-30 days</option>
                    <option value="31-60" <?= $days_overdue === '31-60' ? 'selected' : '' ?>>31-60 days</option>
                    <option value="61-90" <?= $days_overdue === '61-90' ? 'selected' : '' ?>>61-90 days</option>
                    <option value="90+" <?= $days_overdue === '90+' ? 'selected' : '' ?>>Over 90 days</option>
                </select>
            </div>
            
            <!-- Sort by -->
            <div>
                <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select id="sort_by" name="sort_by" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="days_desc" <?= $sort_by === 'days_desc' ? 'selected' : '' ?>>Days Overdue (High to Low)</option>
                    <option value="days_asc" <?= $sort_by === 'days_asc' ? 'selected' : '' ?>>Days Overdue (Low to High)</option>
                    <option value="due_date_asc" <?= $sort_by === 'due_date_asc' ? 'selected' : '' ?>>Due Date (Oldest First)</option>
                    <option value="due_date_desc" <?= $sort_by === 'due_date_desc' ? 'selected' : '' ?>>Due Date (Newest First)</option>
                    <option value="amount_desc" <?= $sort_by === 'amount_desc' ? 'selected' : '' ?>>Amount (High to Low)</option>
                    <option value="amount_asc" <?= $sort_by === 'amount_asc' ? 'selected' : '' ?>>Amount (Low to High)</option>
                    <option value="customer_asc" <?= $sort_by === 'customer_asc' ? 'selected' : '' ?>>Customer Name (A-Z)</option>
                    <option value="customer_desc" <?= $sort_by === 'customer_desc' ? 'selected' : '' ?>>Customer Name (Z-A)</option>
                </select>
            </div>
            
            <!-- Filter buttons -->
            <div class="flex space-x-2">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
                <a href="overdue_credits_report.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Overdue Invoices Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">
                Overdue Credit Invoices
                <?php if ($total_records > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format($total_records) ?> invoices)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($overdue_invoices)): ?>
            <div class="p-6 text-center text-gray-500">
                No overdue invoices found with the selected filters. Try adjusting your criteria.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Due</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($overdue_invoices as $invoice): ?>
                        <tr class="<?= $invoice['days_overdue'] > 30 ? 'bg-red-50' : '' ?> hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-blue-600">
                                    <a href="../modules/pos/receipt.php?id=<?= $invoice['sale_id'] ?>" class="hover:underline">
                                        <?= $invoice['invoice_number'] ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?= date('M d, Y', strtotime($invoice['sale_date'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span class="text-blue-600 font-bold">
                                            <?= strtoupper(substr($invoice['customer_name'], 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <a href="../modules/credit_management/view_credit_customer.php?id=<?= $invoice['customer_id'] ?>" class="hover:underline">
                                                <?= htmlspecialchars($invoice['customer_name']) ?>
                                            </a>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= htmlspecialchars($invoice['phone_number']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php
                                $days_class = 'text-yellow-600';
                                $days_bg = 'bg-yellow-100';
                                
                                if ($invoice['days_overdue'] > 90) {
                                    $days_class = 'text-red-800';
                                    $days_bg = 'bg-red-100';
                                } elseif ($invoice['days_overdue'] > 60) {
                                    $days_class = 'text-red-600';
                                    $days_bg = 'bg-red-100';
                                } elseif ($invoice['days_overdue'] > 30) {
                                    $days_class = 'text-orange-600';
                                    $days_bg = 'bg-orange-100';
                                } elseif ($invoice['days_overdue'] > 15) {
                                    $days_class = 'text-amber-600';
                                    $days_bg = 'bg-amber-100';
                                }
                                ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-bold rounded-full <?= $days_bg ?> <?= $days_class ?>">
                                    <?= $invoice['days_overdue'] ?> days
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?= $currency_symbol ?> <?= number_format($invoice['net_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">
                                <?= $currency_symbol ?> <?= number_format($invoice['amount_paid'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold text-right">
                                <?= $currency_symbol ?> <?= number_format($invoice['balance_due'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right space-x-2">
                                <a href="../modules/credit_settlement/add_settlement.php?customer_id=<?= $invoice['customer_id'] ?>&invoice_id=<?= $invoice['sale_id'] ?>" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-money-bill-wave"></i> Settle
                                </a>
                                <a href="../modules/pos/receipt.php?id=<?= $invoice['sale_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-receipt"></i> Receipt
                                </a>
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
                    Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $per_page, $total_records)) ?> of <?= number_format($total_records) ?> invoices
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&days_overdue=<?= $days_overdue ?>&sort_by=<?= $sort_by ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&days_overdue=<?= $days_overdue ?>&sort_by=<?= $sort_by ?>" 
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