<?php
/**
 * Credit Management - Outstanding Balances Report
 * 
 * This report shows all customers with outstanding balances,
 * including details about payment status, overdue amounts,
 * and other relevant credit metrics.
 */

// Set page title and include header
$page_title = "Outstanding Balances Report";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="../reports/index.php">Reports</a> / <span class="text-gray-700">Outstanding Balances</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has permission
if (!has_permission('view_reports')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize variables for filtering and pagination
$status = $_GET['status'] ?? 'all';
$overdue = isset($_GET['overdue']) ? filter_var($_GET['overdue'], FILTER_VALIDATE_BOOLEAN) : false;
$over_limit = isset($_GET['over_limit']) ? filter_var($_GET['over_limit'], FILTER_VALIDATE_BOOLEAN) : false;
$sort_by = $_GET['sort_by'] ?? 'balance_desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get date ranges for filtering
$date_range = $_GET['date_range'] ?? '30days';
$start_date = null;
$end_date = date('Y-m-d');

switch ($date_range) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'custom':
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
}

// Base query for customers with outstanding balances
$baseQuery = "
    SELECT c.*, 
           (SELECT MAX(s.due_date) FROM sales s WHERE s.credit_customer_id = c.customer_id AND s.credit_status != 'settled') as latest_due_date,
           (SELECT COUNT(*) FROM sales s WHERE s.credit_customer_id = c.customer_id AND s.credit_status != 'settled') as open_invoices,
           (SELECT COUNT(*) FROM sales s WHERE s.credit_customer_id = c.customer_id AND s.credit_status != 'settled' AND s.due_date < CURDATE()) as overdue_invoices,
           DATEDIFF(CURDATE(), (SELECT MIN(s.due_date) FROM sales s WHERE s.credit_customer_id = c.customer_id AND s.credit_status != 'settled' AND s.due_date < CURDATE())) as days_overdue,
           (SELECT SUM(s.net_amount) FROM sales s WHERE s.credit_customer_id = c.customer_id AND s.credit_status != 'settled' AND s.due_date < CURDATE()) as overdue_amount
    FROM credit_customers c
    WHERE c.current_balance > 0
";

$countQuery = "SELECT COUNT(*) FROM credit_customers c WHERE c.current_balance > 0";
$params = [];
$types = "";

// Apply filters
if ($status != 'all') {
    $baseQuery .= " AND c.status = ?";
    $countQuery .= " AND c.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($overdue) {
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
}

if ($over_limit) {
    $baseQuery .= " AND c.current_balance > c.credit_limit";
    $countQuery .= " AND c.current_balance > c.credit_limit";
}

// Add sorting
switch ($sort_by) {
    case 'balance_desc':
        $baseQuery .= " ORDER BY c.current_balance DESC";
        break;
    case 'balance_asc':
        $baseQuery .= " ORDER BY c.current_balance ASC";
        break;
    case 'overdue_desc':
        $baseQuery .= " ORDER BY days_overdue DESC";
        break;
    case 'name_asc':
        $baseQuery .= " ORDER BY c.customer_name ASC";
        break;
    default:
        $baseQuery .= " ORDER BY c.current_balance DESC";
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
try {
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
            SUM(current_balance) as total_outstanding,
            SUM(CASE WHEN current_balance > credit_limit THEN 1 ELSE 0 END) as over_limit_count,
            SUM(CASE WHEN current_balance > credit_limit THEN current_balance - credit_limit ELSE 0 END) as over_limit_amount,
            COUNT(DISTINCT 
                CASE WHEN EXISTS (
                    SELECT 1 FROM sales s 
                    WHERE s.credit_customer_id = credit_customers.customer_id 
                    AND s.credit_status != 'settled' 
                    AND s.due_date < CURDATE()
                ) THEN credit_customers.customer_id ELSE NULL END
            ) as overdue_customers,
            (
                SELECT SUM(s.net_amount) 
                FROM sales s 
                WHERE s.credit_status != 'settled' 
                AND s.due_date < CURDATE()
            ) as total_overdue_amount
        FROM credit_customers
        WHERE current_balance > 0
    ";
    
    if ($status != 'all') {
        $summaryQuery .= " AND status = ?";
        $summaryParams = [$status];
        $summaryTypes = "s";
    } else {
        $summaryParams = [];
        $summaryTypes = "";
    }
    
    $summaryStmt = $conn->prepare($summaryQuery);
    if ($summaryStmt) {
        if (!empty($summaryParams)) {
            $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
        }
        $summaryStmt->execute();
        $summary = $summaryStmt->get_result()->fetch_assoc();
        $summaryStmt->close();
    }
    
    // Get aging brackets
    $agingQuery = "
        SELECT
            SUM(CASE WHEN days_overdue IS NULL OR days_overdue <= 0 THEN current_balance ELSE 0 END) as current_amount,
            SUM(CASE WHEN days_overdue > 0 AND days_overdue <= 30 THEN current_balance ELSE 0 END) as days_1_30,
            SUM(CASE WHEN days_overdue > 30 AND days_overdue <= 60 THEN current_balance ELSE 0 END) as days_31_60,
            SUM(CASE WHEN days_overdue > 60 AND days_overdue <= 90 THEN current_balance ELSE 0 END) as days_61_90,
            SUM(CASE WHEN days_overdue > 90 THEN current_balance ELSE 0 END) as days_over_90
        FROM (
            SELECT 
                c.customer_id,
                c.current_balance,
                DATEDIFF(CURDATE(), (
                    SELECT MIN(s.due_date) 
                    FROM sales s 
                    WHERE s.credit_customer_id = c.customer_id 
                    AND s.credit_status != 'settled' 
                    AND s.due_date < CURDATE()
                )) as days_overdue
            FROM credit_customers c
            WHERE c.current_balance > 0
    ";
    
    if ($status != 'all') {
        $agingQuery .= " AND c.status = ?";
        $agingParams = [$status];
        $agingTypes = "s";
    } else {
        $agingParams = [];
        $agingTypes = "";
    }
    
    $agingQuery .= ") as aging_data";
    
    $agingStmt = $conn->prepare($agingQuery);
    if ($agingStmt) {
        if (!empty($agingParams)) {
            $agingStmt->bind_param($agingTypes, ...$agingParams);
        }
        $agingStmt->execute();
        $aging = $agingStmt->get_result()->fetch_assoc();
        $agingStmt->close();
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get currency symbol from settings
$currency_symbol = get_setting('currency_symbol', '$');

// Format numbers for display
if (isset($summary)) {
    $summary['total_outstanding'] = $summary['total_outstanding'] ?? 0;
    $summary['over_limit_amount'] = $summary['over_limit_amount'] ?? 0;
    $summary['total_overdue_amount'] = $summary['total_overdue_amount'] ?? 0;
}

if (isset($aging)) {
    $aging['current_amount'] = $aging['current_amount'] ?? 0;
    $aging['days_1_30'] = $aging['days_1_30'] ?? 0;
    $aging['days_31_60'] = $aging['days_31_60'] ?? 0;
    $aging['days_61_90'] = $aging['days_61_90'] ?? 0;
    $aging['days_over_90'] = $aging['days_over_90'] ?? 0;
}

// Handle report export if requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Remove pagination for export
    $exportQuery = preg_replace('/LIMIT.*$/', '', $baseQuery);
    $exportParams = array_slice($params, 0, -2);
    $exportTypes = substr($types, 0, -2);
    
    $exportStmt = $conn->prepare($exportQuery);
    if ($exportStmt) {
        if (!empty($exportParams)) {
            $exportStmt->bind_param($exportTypes, ...$exportParams);
        }
        $exportStmt->execute();
        $result = $exportStmt->get_result();
        $exportData = [];
        while ($row = $result->fetch_assoc()) {
            $exportData[] = $row;
        }
        $exportStmt->close();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="outstanding_balances_' . date('Y-m-d') . '.csv"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Customer ID',
            'Customer Name',
            'Status',
            'Phone Number',
            'Email',
            'Credit Limit',
            'Current Balance',
            'Available Credit',
            'Open Invoices',
            'Overdue Invoices',
            'Days Overdue',
            'Overdue Amount',
            'Latest Due Date'
        ]);
        
        // Add data rows
        foreach ($exportData as $row) {
            $availableCredit = $row['credit_limit'] - $row['current_balance'];
            
            fputcsv($output, [
                $row['customer_id'],
                $row['customer_name'],
                $row['status'],
                $row['phone_number'],
                $row['email'],
                $row['credit_limit'],
                $row['current_balance'],
                $availableCredit,
                $row['open_invoices'] ?? 0,
                $row['overdue_invoices'] ?? 0,
                $row['days_overdue'] ?? 0,
                $row['overdue_amount'] ?? 0,
                $row['latest_due_date'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    }
}
?>

<div class="container mx-auto pb-6">
    
    <!-- Page title and actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Outstanding Balances Report</h2>
        
        <div class="flex gap-2">
            <button id="printReportBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-print mr-2"></i>
                Print Report
            </button>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </a>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Status filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>
            </div>
            
            <!-- Date range filter -->
            <div>
                <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="7days" <?= $date_range === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="30days" <?= $date_range === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="90days" <?= $date_range === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                    <option value="custom" <?= $date_range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
            </div>
            
            <!-- Custom date range (initially hidden) -->
            <div id="customDateRange" class="md:col-span-2 grid grid-cols-2 gap-4" style="<?= $date_range === 'custom' ? '' : 'display: none;' ?>">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" 
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" 
                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>
            </div>
            
            <!-- Sort order -->
            <div class="md:col-span-<?= $date_range === 'custom' ? '1' : '2' ?>">
                <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select id="sort_by" name="sort_by" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="balance_desc" <?= $sort_by === 'balance_desc' ? 'selected' : '' ?>>Balance (High to Low)</option>
                    <option value="balance_asc" <?= $sort_by === 'balance_asc' ? 'selected' : '' ?>>Balance (Low to High)</option>
                    <option value="overdue_desc" <?= $sort_by === 'overdue_desc' ? 'selected' : '' ?>>Days Overdue (High to Low)</option>
                    <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Customer Name (A to Z)</option>
                </select>
            </div>
            
            <!-- Additional filters -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Filters</label>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="overdue" name="overdue" value="1" <?= $overdue ? 'checked' : '' ?> 
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="overdue" class="ml-2 block text-sm text-gray-900">
                            Show Only Overdue
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="over_limit" name="over_limit" value="1" <?= $over_limit ? 'checked' : '' ?> 
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="over_limit" class="ml-2 block text-sm text-gray-900">
                            Show Only Over Credit Limit
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Search button -->
            <div class="flex items-end md:col-span-2 justify-end space-x-3">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-search mr-2"></i> Generate Report
                </button>
                <a href="outstanding_balances_report.php" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Report content -->
    <div id="reportContent">
        <!-- Summary Cards -->
        <?php if (isset($summary)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total Outstanding -->
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Outstanding</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?><?= number_format($summary['total_outstanding'], 2) ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-blue-500"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    From <?= number_format($summary['total_customers']) ?> customers with balance
                </p>
            </div>
            
            <!-- Overdue Amount -->
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Overdue Amount</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?><?= number_format($summary['total_overdue_amount'] ?? 0, 2) ?></p>
                    </div>
                    <div class="bg-red-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">
                    From <?= number_format($summary['overdue_customers']) ?> customers with overdue invoices
                </p>
            </div>
            
            <!-- Over Credit Limit -->
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
    <div class="flex justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500">Over Credit Limit</p>
            <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?><?= number_format($summary['over_limit_amount'] ?? 0, 2) ?></p>
        </div>
        <div class="bg-yellow-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
            <i class="fas fa-exclamation-triangle text-yellow-500"></i>
        </div>
    </div>
    <p class="text-xs text-gray-500 mt-2">
        From <?= number_format($summary['over_limit_count'] ?? 0) ?> customers over their credit limit
    </p>
</div>
            
            <!-- Percentage Overdue -->
            <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Percentage Overdue</p>
                        <?php
                        $percentage_overdue = $summary['total_outstanding'] > 0 
                            ? ($summary['total_overdue_amount'] / $summary['total_outstanding'] * 100) 
                            : 0;
                        ?>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($percentage_overdue, 1) ?>%</p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                        <i class="fas fa-percentage text-purple-500"></i>
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                    <div class="bg-purple-500 h-2.5 rounded-full" style="width: <?= min(100, $percentage_overdue) ?>%"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Aging Analysis -->
        <?php if (isset($aging)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Aging Analysis</h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">1-30 Days</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">31-60 Days</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">61-90 Days</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Over 90 Days</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $currency_symbol ?><?= number_format($aging['current_amount'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $currency_symbol ?><?= number_format($aging['days_1_30'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $currency_symbol ?><?= number_format($aging['days_31_60'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $currency_symbol ?><?= number_format($aging['days_61_90'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $currency_symbol ?><?= number_format($aging['days_over_90'], 2) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                <?= $currency_symbol ?><?= number_format($aging['current_amount'] + $aging['days_1_30'] + $aging['days_31_60'] + $aging['days_61_90'] + $aging['days_over_90'], 2) ?>
                            </td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php
                                $total_aging = $aging['current_amount'] + $aging['days_1_30'] + $aging['days_31_60'] + $aging['days_61_90'] + $aging['days_over_90'];
                                $current_percent = $total_aging > 0 ? ($aging['current_amount'] / $total_aging * 100) : 0;
                                ?>
                                <?= number_format($current_percent, 1) ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php $days_1_30_percent = $total_aging > 0 ? ($aging['days_1_30'] / $total_aging * 100) : 0; ?>
                                <?= number_format($days_1_30_percent, 1) ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php $days_31_60_percent = $total_aging > 0 ? ($aging['days_31_60'] / $total_aging * 100) : 0; ?>
                                <?= number_format($days_31_60_percent, 1) ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php $days_61_90_percent = $total_aging > 0 ? ($aging['days_61_90'] / $total_aging * 100) : 0; ?>
                                <?= number_format($days_61_90_percent, 1) ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php $days_over_90_percent = $total_aging > 0 ? ($aging['days_over_90'] / $total_aging * 100) : 0; ?>
                                <?= number_format($days_over_90_percent, 1) ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-500">100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Aging visualization -->
            <div class="mt-6">
                <div class="flex w-full h-6 bg-gray-200 rounded-full overflow-hidden">
                    <?php if ($total_aging > 0): ?>
                    <div class="bg-green-500" style="width: <?= $current_percent ?>%"></div>
                    <div class="bg-yellow-400" style="width: <?= $days_1_30_percent ?>%"></div>
                    <div class="bg-orange-500" style="width: <?= $days_31_60_percent ?>%"></div>
                    <div class="bg-red-500" style="width: <?= $days_61_90_percent ?>%"></div>
                    <div class="bg-red-700" style="width: <?= $days_over_90_percent ?>%"></div>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between mt-2 text-xs text-gray-500">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-green-500 mr-1 rounded"></span>
                        <span>Current</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-yellow-400 mr-1 rounded"></span>
                        <span>1-30 Days</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-orange-500 mr-1 rounded"></span>
                        <span>31-60 Days</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-red-500 mr-1 rounded"></span>
                        <span>61-90 Days</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-red-700 mr-1 rounded"></span>
                        <span>Over 90 Days</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Customer List with Outstanding Balances -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">
                    Customers with Outstanding Balances
                    <?php if ($total_records > 0): ?>
                    <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format($total_records) ?> customers)</span>
                    <?php endif; ?>
                </h3>
            </div>
            
            <?php if (empty($customers)): ?>
                <div class="p-6 text-center text-gray-500">
                    No customers with outstanding balances found matching your criteria.
                    Try adjusting your filters or <a href="outstanding_balances_report.php" class="text-blue-600 hover:text-blue-800">reset the report</a>.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit Limit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Open</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Overdue</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($customers as $customer): ?>
                            <?php
                            // Determine row highlighting based on status
                            $rowClass = '';
                            if (isset($customer['days_overdue']) && $customer['days_overdue'] > 90) {
                                $rowClass = 'bg-red-50';
                            } elseif (isset($customer['days_overdue']) && $customer['days_overdue'] > 30) {
                                $rowClass = 'bg-yellow-50';
                            } elseif ($customer['current_balance'] > $customer['credit_limit']) {
                                $rowClass = 'bg-orange-50';
                            }
                            ?>
                            <tr class="<?= $rowClass ?> hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 font-bold">
                                                <?= strtoupper(substr($customer['customer_name'], 0, 1)) ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <a href="../credit_management/view_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="hover:underline">
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
                                    <?= $currency_symbol ?><?= number_format($customer['credit_limit'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold">
                                    <?php
                                    $balanceClass = 'text-gray-900';
                                    if ($customer['current_balance'] > $customer['credit_limit']) {
                                        $balanceClass = 'text-red-600';
                                    }
                                    ?>
                                    <span class="<?= $balanceClass ?>">
                                        <?= $currency_symbol ?><?= number_format($customer['current_balance'], 2) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                    <?= number_format($customer['open_invoices'] ?? 0) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <?php if (isset($customer['overdue_invoices']) && $customer['overdue_invoices'] > 0): ?>
                                    <span class="text-red-600 font-semibold">
                                        <?= number_format($customer['overdue_invoices']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-500">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <?php if (isset($customer['days_overdue']) && $customer['days_overdue'] > 0): ?>
                                    <span class="<?= $customer['days_overdue'] > 90 ? 'text-red-600 font-bold' : ($customer['days_overdue'] > 30 ? 'text-orange-600 font-semibold' : 'text-yellow-600') ?>">
                                        <?= number_format($customer['days_overdue']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-green-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="../credit_management/view_credit_customer.php?id=<?= $customer['customer_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-2">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../credit_settlement/add_settlement.php?customer_id=<?= $customer['customer_id'] ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-money-bill-wave"></i> Settle
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
                        Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $per_page, $total_records)) ?> of <?= number_format($total_records) ?> customers
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>" 
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>" 
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
</div>

<!-- Print stylesheet -->
<style type="text/css" media="print">
    @page {
        size: landscape;
    }
    
    body {
        font-size: 12pt;
    }
    
    .container {
        width: 100%;
        max-width: 100%;
    }
    
    nav, header, footer, button, a[href]:not(.print-show) {
        display: none !important;
    }
    
    .shadow-md {
        box-shadow: none !important;
    }
    
    .rounded-lg, .rounded-md, .rounded {
        border-radius: 0 !important;
    }
    
    .bg-white, .bg-gray-50 {
        background-color: white !important;
    }
    
    table {
        page-break-inside: auto;
        border-collapse: collapse;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    th, td {
        border: 1px solid #ddd;
    }
    
    thead {
        display: table-header-group;
    }
    
    tfoot {
        display: table-footer-group;
    }
</style>

<!-- JavaScript for interactive features -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle custom date range based on selection
    const dateRangeSelect = document.getElementById('date_range');
    const customDateRange = document.getElementById('customDateRange');
    
    if (dateRangeSelect && customDateRange) {
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateRange.style.display = 'grid';
            } else {
                customDateRange.style.display = 'none';
            }
        });
    }
    
    // Print report
    const printReportBtn = document.getElementById('printReportBtn');
    if (printReportBtn) {
        printReportBtn.addEventListener('click', function() {
            window.print();
        });
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>