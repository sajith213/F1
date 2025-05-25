<?php
ob_start();
/**
 * Customer Credit History
 * 
 * This file displays the detailed credit history for a specific customer,
 * showing all transactions, payments, and balance changes over time.
 */

// Set page title and include header
$page_title = "Customer Credit History";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="../credit_management/index.php">Credit Management</a> / <span class="text-gray-700">Customer Credit History</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if customer ID is valid
if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID.";
    header("Location: ../credit_management/index.php");
    exit;
}

// Initialize variables for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-3 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$transaction_type = isset($_GET['transaction_type']) ? $_GET['transaction_type'] : 'all';

// Get customer details
$customer = null;
$stmt = $conn->prepare("
    SELECT c.*, 
           COALESCE(SUM(CASE WHEN t.transaction_type = 'sale' THEN t.amount ELSE 0 END), 0) as total_sales,
           COALESCE(SUM(CASE WHEN t.transaction_type = 'payment' THEN t.amount ELSE 0 END), 0) as total_payments,
           COUNT(DISTINCT s.settlement_id) as payment_count
    FROM credit_customers c
    LEFT JOIN credit_transactions t ON c.customer_id = t.customer_id
    LEFT JOIN credit_settlements s ON c.customer_id = s.customer_id
    WHERE c.customer_id = ?
    GROUP BY c.customer_id
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: ../credit_management/index.php");
    exit;
}

$customer = $result->fetch_assoc();
$stmt->close();

// Get transaction history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Base query for transactions
$transactionQuery = "
    SELECT * FROM (
        -- Credit Sales
        SELECT 
            s.sale_id as transaction_id,
            s.sale_date as transaction_date,
            s.invoice_number as reference_no,
            s.net_amount as amount,
            'sale' as transaction_type,
            CONCAT('Invoice #', s.invoice_number) as description,
            NULL as payment_method,
            NULL as notes
        FROM sales s
        WHERE s.credit_customer_id = ? AND s.credit_status IS NOT NULL
        
        UNION ALL
        
        -- Credit Settlements
        SELECT 
            cs.settlement_id as transaction_id,
            cs.settlement_date as transaction_date,
            cs.reference_no,
            cs.amount,
            'payment' as transaction_type,
            CONCAT('Settlement #', cs.settlement_id) as description,
            cs.payment_method,
            cs.notes
        FROM credit_settlements cs
        WHERE cs.customer_id = ?
        
        UNION ALL
        
        -- Other Adjustments (if any)
        SELECT 
            ct.transaction_id,
            ct.transaction_date,
            ct.reference_no,
            ct.amount,
            ct.transaction_type,
            CASE 
                WHEN ct.transaction_type = 'adjustment' THEN CONCAT('Adjustment: ', COALESCE(ct.notes, 'N/A'))
                ELSE ct.notes
            END as description,
            NULL as payment_method,
            ct.notes
        FROM credit_transactions ct
        WHERE ct.customer_id = ? AND ct.transaction_type = 'adjustment'
    ) AS combined_transactions
    WHERE transaction_date BETWEEN ? AND ?
";

// Add transaction type filter if not "all"
if ($transaction_type !== 'all') {
    $transactionQuery .= " AND transaction_type = ?";
}

// Add sorting
$transactionQuery .= " ORDER BY transaction_date DESC, transaction_id DESC";

// Add pagination
$transactionQuery .= " LIMIT ?, ?";

// Prepare parameters
$params = [$customer_id, $customer_id, $customer_id, $start_date, $end_date];
$types = "iiiss";

if ($transaction_type !== 'all') {
    $params[] = $transaction_type;
    $types .= "s";
}

$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Execute query
$stmt = $conn->prepare($transactionQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total FROM (
        -- Credit Sales
        SELECT 
            s.sale_id as transaction_id
        FROM sales s
        WHERE s.credit_customer_id = ? AND s.credit_status IS NOT NULL
        
        UNION ALL
        
        -- Credit Settlements
        SELECT 
            cs.settlement_id as transaction_id
        FROM credit_settlements cs
        WHERE cs.customer_id = ?
        
        UNION ALL
        
        -- Other Adjustments (if any)
        SELECT 
            ct.transaction_id
        FROM credit_transactions ct
        WHERE ct.customer_id = ? AND ct.transaction_type = 'adjustment'
    ) AS combined_transactions
    WHERE transaction_date BETWEEN ? AND ?
";

// Add transaction type filter if not "all"
if ($transaction_type !== 'all') {
    $countQuery .= " AND transaction_type = ?";
}

// Prepare parameters for count query (without pagination)
$countParams = [$customer_id, $customer_id, $customer_id, $start_date, $end_date];
$countTypes = "iiiss";

if ($transaction_type !== 'all') {
    $countParams[] = $transaction_type;
    $countTypes .= "s";
}

// Execute count query
$stmt = $conn->prepare($countQuery);
$stmt->bind_param($countTypes, ...$countParams);
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = ceil($totalRecords / $per_page);

// Get monthly summary for the chart
$monthlySummaryQuery = "
    SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        SUM(CASE WHEN transaction_type = 'sale' THEN amount ELSE 0 END) as sales_amount,
        SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) as payment_amount
    FROM (
        -- Credit Sales
        SELECT 
            s.sale_date as transaction_date,
            s.net_amount as amount,
            'sale' as transaction_type
        FROM sales s
        WHERE s.credit_customer_id = ? AND s.credit_status IS NOT NULL
        
        UNION ALL
        
        -- Credit Settlements
        SELECT 
            cs.settlement_date as transaction_date,
            cs.amount,
            'payment' as transaction_type
        FROM credit_settlements cs
        WHERE cs.customer_id = ?
        
        UNION ALL
        
        -- Other Adjustments (if any)
        SELECT 
            ct.transaction_date,
            ct.amount,
            ct.transaction_type
        FROM credit_transactions ct
        WHERE ct.customer_id = ? AND ct.transaction_type = 'adjustment'
    ) AS combined_transactions
    WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
    ORDER BY month
";

$stmt = $conn->prepare($monthlySummaryQuery);
$stmt->bind_param("iii", $customer_id, $customer_id, $customer_id);
$stmt->execute();
$monthlySummary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get currency symbol
$currency_symbol = get_setting('currency_symbol', '$');

// Function to get transaction badge color based on type
function getTransactionBadgeClass($type) {
    switch ($type) {
        case 'sale':
            return 'bg-red-100 text-red-800';
        case 'payment':
            return 'bg-green-100 text-green-800';
        case 'adjustment':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Function to get transaction icon based on type
function getTransactionIcon($type) {
    switch ($type) {
        case 'sale':
            return 'fas fa-shopping-cart';
        case 'payment':
            return 'fas fa-money-bill-wave';
        case 'adjustment':
            return 'fas fa-balance-scale';
        default:
            return 'fas fa-exchange-alt';
    }
}

// Set export parameters for CSV download
$exportParams = $_GET;
$exportParams['export'] = 'csv';
$exportURL = '?' . http_build_query($exportParams);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Remove pagination for export
    $exportQuery = str_replace(" LIMIT ?, ?", "", $transactionQuery);
    $exportParams = array_slice($params, 0, -2);
    $exportTypes = substr($types, 0, -2);
    
    $stmt = $conn->prepare($exportQuery);
    $stmt->bind_param($exportTypes, ...$exportParams);
    $stmt->execute();
    $exportData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customer_credit_history_' . $customer['customer_name'] . '_' . date('Y-m-d') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'Date',
        'Type',
        'Reference',
        'Description',
        'Payment Method',
        'Amount',
        'Notes'
    ]);
    
    // Add data rows
    foreach ($exportData as $row) {
        $amount = $row['transaction_type'] === 'payment' ? -$row['amount'] : $row['amount'];
        
        fputcsv($output, [
            date('Y-m-d H:i', strtotime($row['transaction_date'])),
            ucfirst($row['transaction_type']),
            $row['reference_no'],
            $row['description'],
            $row['payment_method'] ?? 'N/A',
            $amount,
            $row['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<div class="container mx-auto pb-6">
    
    <!-- Page title and actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Customer Credit History</h2>
        
        <div class="flex gap-2">
            <a href="<?= $exportURL ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-download mr-2"></i>
                Export CSV
            </a>
            <button id="printReportBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-print mr-2"></i>
                Print
            </button>
            <a href="../credit_management/index.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Back
            </a>
        </div>
    </div>
    <!-- Add this below your page title -->
<?php if (isset($_SESSION['error_message'])): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 relative" role="alert">
    <strong class="font-bold">Error!</strong>
    <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error_message']) ?></span>
    <button class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
        <span class="text-red-500">×</span>
    </button>
</div>
<?php unset($_SESSION['error_message']); endif; ?>
    
    <!-- Customer Information Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">Customer Information</h3>
        </div>
        
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center">
                <div class="flex items-center mb-4 md:mb-0 md:mr-6">
                    <div class="h-16 w-16 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <span class="text-blue-600 font-bold text-xl">
                            <?= strtoupper(substr($customer['customer_name'], 0, 1)) ?>
                        </span>
                    </div>
                    <div>
                        <h4 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($customer['customer_name']) ?></h4>
                        <div class="flex flex-wrap items-center text-sm text-gray-600">
                            <div class="mr-4">
                                <i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($customer['phone_number']) ?>
                            </div>
                            <?php if (!empty($customer['email'])): ?>
                            <div>
                                <i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($customer['email']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-4 mt-4 md:mt-0 md:ml-auto">
                    <div class="px-4 py-2 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-600">Credit Limit</div>
                        <div class="text-lg font-bold text-gray-900"><?= $currency_symbol ?><?= number_format($customer['credit_limit'], 2) ?></div>
                    </div>
                    
                    <div class="px-4 py-2 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-600">Current Balance</div>
                        <div class="text-lg font-bold <?= $customer['current_balance'] > 0 ? 'text-red-600' : 'text-gray-900' ?>">
                            <?= $currency_symbol ?><?= number_format($customer['current_balance'], 2) ?>
                        </div>
                    </div>
                    
                    <div class="px-4 py-2 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-600">Status</div>
                        <?php 
                        $statusClass = 'bg-green-100 text-green-800';
                        if ($customer['status'] === 'inactive') {
                            $statusClass = 'bg-gray-100 text-gray-800';
                        } elseif ($customer['status'] === 'blocked') {
                            $statusClass = 'bg-red-100 text-red-800';
                        }
                        ?>
                        <div class="inline-flex px-2 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                            <?= ucfirst($customer['status']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter and Summary Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Filters -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Filter Transactions</h3>
            </div>
            
            <div class="p-6">
                <form method="GET" action="">
                    <input type="hidden" name="id" value="<?= $customer_id ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                        
                        <div>
                            <label for="transaction_type" class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                            <select id="transaction_type" name="transaction_type" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="all" <?= $transaction_type === 'all' ? 'selected' : '' ?>>All Transactions</option>
                                <option value="sale" <?= $transaction_type === 'sale' ? 'selected' : '' ?>>Sales Only</option>
                                <option value="payment" <?= $transaction_type === 'payment' ? 'selected' : '' ?>>Payments Only</option>
                                <option value="adjustment" <?= $transaction_type === 'adjustment' ? 'selected' : '' ?>>Adjustments Only</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex justify-end space-x-3">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                        <a href="?id=<?= $customer_id ?>" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-redo mr-2"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Stats -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Account Summary</h3>
            </div>
            
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Sales:</span>
                        <span class="font-semibold text-gray-900"><?= $currency_symbol ?><?= number_format($customer['total_sales'], 2) ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Payments:</span>
                        <span class="font-semibold text-green-600"><?= $currency_symbol ?><?= number_format($customer['total_payments'], 2) ?></span>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Current Balance:</span>
                            <span class="font-bold <?= $customer['current_balance'] > 0 ? 'text-red-600' : 'text-gray-900' ?>">
                                <?= $currency_symbol ?><?= number_format($customer['current_balance'], 2) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Available Credit:</span>
                        <?php 
                        $available_credit = $customer['credit_limit'] - $customer['current_balance'];
                        $credit_class = $available_credit >= 0 ? 'text-green-600' : 'text-red-600';
                        ?>
                        <span class="font-semibold <?= $credit_class ?>">
                            <?= $currency_symbol ?><?= number_format($available_credit, 2) ?>
                        </span>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Payment Count:</span>
                            <span class="font-semibold text-gray-900"><?= number_format($customer['payment_count']) ?></span>
                        </div>
                        
                        <?php if ($customer['current_balance'] > 0): ?>
                        <div class="mt-4">
                            <a href="../credit_settlement/add_settlement.php?customer_id=<?= $customer_id ?>" class="inline-flex w-full justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-money-bill-wave mr-2"></i> Add Settlement
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sales vs. Payments Chart -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Monthly Sales vs. Payments</h3>
        </div>
        
        <div class="p-6">
            <div id="monthly-chart" style="height: 300px;"></div>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">
                Transaction History
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= number_format($totalRecords) ?> transactions)</span>
            </h3>
        </div>
        
        <?php if (empty($transactions)): ?>
            <div class="p-6 text-center text-gray-500">
                No transactions found for the selected time period.
                Try adjusting your filter settings or <a href="?id=<?= $customer_id ?>" class="text-blue-600 hover:text-blue-800">reset filters</a>.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $running_balance = $customer['current_balance'];
                        
                        // Sort transactions by date, newest first
                        usort($transactions, function($a, $b) {
                            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
                        });
                        
                        foreach ($transactions as $transaction): 
                            // Adjust running balance
                            if ($transaction['transaction_type'] === 'payment') {
                                $running_balance += $transaction['amount'];
                            } else {
                                $running_balance -= $transaction['amount'];
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= date('M d, Y', strtotime($transaction['transaction_date'])) ?>
                                <div class="text-xs text-gray-500">
                                    <?= date('h:i A', strtotime($transaction['transaction_date'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= getTransactionBadgeClass($transaction['transaction_type']) ?>">
                                    <i class="<?= getTransactionIcon($transaction['transaction_type']) ?> mr-1"></i>
                                    <?= ucfirst($transaction['transaction_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($transaction['reference_no'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($transaction['description'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if (!empty($transaction['payment_method'])): ?>
                                    <?= ucfirst(str_replace('_', ' ', $transaction['payment_method'])) ?>
                                <?php else: ?>
                                    <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if ($transaction['transaction_type'] === 'payment'): ?>
                                    <span class="text-green-600">
                                        -<?= $currency_symbol ?><?= number_format($transaction['amount'], 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-900">
                                        <?= $currency_symbol ?><?= number_format($transaction['amount'], 2) ?>
                                    </span>
                                <?php endif; ?>
                                <div class="text-xs text-gray-500">
                                    Balance: <?= $currency_symbol ?><?= number_format($running_balance, 2) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                <?php if ($transaction['transaction_type'] === 'sale'): ?>
                                    <a href="../pos/view_invoice.php?id=<?= $transaction['transaction_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php elseif ($transaction['transaction_type'] === 'payment'): ?>
                                    <a href="../credit_settlement/view_settlement.php?id=<?= $transaction['transaction_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing page <?= $page ?> of <?= $totalPages ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?id=<?= $customer_id ?>&page=<?= $page - 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&transaction_type=<?= $transaction_type ?>" 
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?id=<?= $customer_id ?>&page=<?= $page + 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&transaction_type=<?= $transaction_type ?>" 
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

<!-- Include ApexCharts for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- Print stylesheet -->
<style type="text/css" media="print">
    @page {
        size: portrait;
    }
    
    body {
        font-size: 12pt;
    }
    
    header, nav, footer, button, a, #monthly-chart {
        display: none !important;
    }
    
    .container {
        width: 100%;
        max-width: 100%;
        padding: 0;
    }
    
    .shadow-md {
        box-shadow: none !important;
    }
    
    .rounded-lg, .rounded-md, .rounded {
        border-radius: 0 !important;
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Print functionality
    const printReportBtn = document.getElementById('printReportBtn');
    if (printReportBtn) {
        printReportBtn.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Monthly chart
    const chartData = <?= json_encode($monthlySummary) ?>;
    const months = chartData.map(item => {
        const [year, month] = item.month.split('-');
        return new Date(year, month - 1).toLocaleString('default', { month: 'short', year: 'numeric' });
    });
    
    const salesData = chartData.map(item => parseFloat(item.sales_amount));
    const paymentData = chartData.map(item => parseFloat(item.payment_amount));
    
    if (document.getElementById('monthly-chart')) {
        const options = {
            series: [
                {
                    name: 'Sales',
                    data: salesData,
                    color: '#EF4444'
                },
                {
                    name: 'Payments',
                    data: paymentData,
                    color: '#10B981'
                }
            ],
            chart: {
                type: 'bar',
                height: 300,
                stacked: false,
                toolbar: {
                    show: true
                },
                zoom: {
                    enabled: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded'
                },
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: months,
            },
            yaxis: {
                title: {
                    text: `Amount (${<?= json_encode($currency_symbol) ?>})`
                },
                labels: {
                    formatter: function (value) {
                        return <?= json_encode($currency_symbol) ?> + value.toFixed(2);
                    }
                }
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                y: {
                    formatter: function (value) {
                        return <?= json_encode($currency_symbol) ?> + value.toFixed(2);
                    }
                }
            },
            legend: {
                position: 'top'
            }
        };

        const chart = new ApexCharts(document.getElementById('monthly-chart'), options);
        chart.render();
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>