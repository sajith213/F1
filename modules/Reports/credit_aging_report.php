<?php
/**
 * Credit Aging Report
 * 
 * This file generates a report showing the aging of credit account receivables,
 * categorized by timeframes (current, 1-30 days, 31-60 days, 61-90 days, and over 90 days)
 */

// Set page title and include header
$page_title = "Credit Aging Report";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="../reports/index.php">Reports</a> / <span class="text-gray-700">Credit Aging Report</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('view_reports')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize variables for filtering
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$status = $_GET['status'] ?? 'active';
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');
$min_amount = isset($_GET['min_amount']) ? (float)$_GET['min_amount'] : 0;
$group_by = $_GET['group_by'] ?? 'customer';

// Get all customers for filter dropdown
$customers = [];
$customerQuery = "SELECT customer_id, customer_name, phone_number FROM credit_customers ORDER BY customer_name";
$customerResult = $conn->query($customerQuery);
if ($customerResult) {
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Default query to get aging data
$baseQuery = "
    SELECT 
        c.customer_id,
        c.customer_name,
        c.phone_number,
        c.credit_limit,
        c.current_balance,
        s.sale_id,
        s.invoice_number,
        s.sale_date,
        s.due_date,
        s.net_amount,
        s.credit_status,
        DATEDIFF(?, s.due_date) as days_overdue,
        CASE
            WHEN s.due_date >= ? THEN s.net_amount
            ELSE 0
        END as current_amount,
        CASE
            WHEN DATEDIFF(?, s.due_date) BETWEEN 1 AND 30 THEN s.net_amount
            ELSE 0
        END as days_1_30,
        CASE
            WHEN DATEDIFF(?, s.due_date) BETWEEN 31 AND 60 THEN s.net_amount
            ELSE 0
        END as days_31_60,
        CASE
            WHEN DATEDIFF(?, s.due_date) BETWEEN 61 AND 90 THEN s.net_amount
            ELSE 0
        END as days_61_90,
        CASE
            WHEN DATEDIFF(?, s.due_date) > 90 THEN s.net_amount
            ELSE 0
        END as days_over_90
    FROM credit_customers c
    JOIN sales s ON c.customer_id = s.credit_customer_id
    WHERE s.credit_status != 'settled'
";

// Add filters to the query
$params = [$as_of_date, $as_of_date, $as_of_date, $as_of_date, $as_of_date, $as_of_date];
$types = "ssssss";

if ($customer_id > 0) {
    $baseQuery .= " AND c.customer_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

if (!empty($status) && $status !== 'all') {
    $baseQuery .= " AND c.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Group and order based on selection
if ($group_by === 'customer') {
    $baseQuery .= " ORDER BY c.customer_name, days_overdue DESC";
} else {
    $baseQuery .= " ORDER BY days_overdue DESC, c.customer_name";
}

// Initialize variables for results
$aging_data = [];
$totals = [
    'current' => 0,
    'days_1_30' => 0,
    'days_31_60' => 0,
    'days_61_90' => 0,
    'days_over_90' => 0,
    'total' => 0
];

// Execute database query
if (isset($conn) && $conn) {
    $stmt = $conn->prepare($baseQuery);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Skip rows where total amount is less than minimum (if set)
            $total_row = $row['current_amount'] + $row['days_1_30'] + $row['days_31_60'] + $row['days_61_90'] + $row['days_over_90'];
            if ($total_row < $min_amount) {
                continue;
            }
            
            // Skip already settled invoices (double-check)
            if ($row['credit_status'] === 'settled') {
                continue;
            }
            
            // Group by customer if selected
            if ($group_by === 'customer') {
                $customerId = $row['customer_id'];
                
                if (!isset($aging_data[$customerId])) {
                    $aging_data[$customerId] = [
                        'customer_id' => $row['customer_id'],
                        'customer_name' => $row['customer_name'],
                        'phone_number' => $row['phone_number'],
                        'credit_limit' => $row['credit_limit'],
                        'current_balance' => $row['current_balance'],
                        'current' => 0,
                        'days_1_30' => 0,
                        'days_31_60' => 0,
                        'days_61_90' => 0,
                        'days_over_90' => 0,
                        'invoices' => []
                    ];
                }
                
                // Add invoice details
                $aging_data[$customerId]['invoices'][] = [
                    'invoice_number' => $row['invoice_number'],
                    'sale_id' => $row['sale_id'],
                    'sale_date' => $row['sale_date'],
                    'due_date' => $row['due_date'],
                    'amount' => $row['net_amount'],
                    'days_overdue' => $row['days_overdue'],
                    'status' => $row['credit_status']
                ];
                
                // Add amounts to customer totals
                $aging_data[$customerId]['current'] += $row['current_amount'];
                $aging_data[$customerId]['days_1_30'] += $row['days_1_30'];
                $aging_data[$customerId]['days_31_60'] += $row['days_31_60'];
                $aging_data[$customerId]['days_61_90'] += $row['days_61_90'];
                $aging_data[$customerId]['days_over_90'] += $row['days_over_90'];
            } else {
                // List every invoice separately
                $aging_data[] = [
                    'customer_id' => $row['customer_id'],
                    'customer_name' => $row['customer_name'],
                    'phone_number' => $row['phone_number'],
                    'invoice_number' => $row['invoice_number'],
                    'sale_id' => $row['sale_id'],
                    'sale_date' => $row['sale_date'],
                    'due_date' => $row['due_date'],
                    'amount' => $row['net_amount'],
                    'days_overdue' => $row['days_overdue'],
                    'status' => $row['credit_status'],
                    'current' => $row['current_amount'],
                    'days_1_30' => $row['days_1_30'],
                    'days_31_60' => $row['days_31_60'],
                    'days_61_90' => $row['days_61_90'],
                    'days_over_90' => $row['days_over_90']
                ];
            }
            
            // Add to totals
            $totals['current'] += $row['current_amount'];
            $totals['days_1_30'] += $row['days_1_30'];
            $totals['days_31_60'] += $row['days_31_60'];
            $totals['days_61_90'] += $row['days_61_90'];
            $totals['days_over_90'] += $row['days_over_90'];
        }
        
        $stmt->close();
    }
}

// Calculate total
$totals['total'] = $totals['current'] + $totals['days_1_30'] + $totals['days_31_60'] + $totals['days_61_90'] + $totals['days_over_90'];

// Calculate percentages for chart
$percentages = [];
if ($totals['total'] > 0) {
    $percentages = [
        'current' => round(($totals['current'] / $totals['total']) * 100, 1),
        'days_1_30' => round(($totals['days_1_30'] / $totals['total']) * 100, 1),
        'days_31_60' => round(($totals['days_31_60'] / $totals['total']) * 100, 1),
        'days_61_90' => round(($totals['days_61_90'] / $totals['total']) * 100, 1),
        'days_over_90' => round(($totals['days_over_90'] / $totals['total']) * 100, 1)
    ];
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

// Calculate risk levels
$risk_levels = [
    'low' => $totals['current'] + $totals['days_1_30'],
    'medium' => $totals['days_31_60'] + $totals['days_61_90'],
    'high' => $totals['days_over_90']
];
?>

<div class="container mx-auto pb-6">
    <!-- Title and Print button -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Credit Aging Report</h2>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            <i class="fas fa-print mr-2"></i> Print Report
        </button>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6 print:hidden">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Customer filter -->
            <div>
                <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                <select id="customer_id" name="customer_id" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="0">All Customers</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['customer_id'] ?>" <?= $customer_id == $customer['customer_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($customer['customer_name']) ?> (<?= htmlspecialchars($customer['phone_number']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
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
            
            <!-- As of date -->
            <div>
                <label for="as_of_date" class="block text-sm font-medium text-gray-700 mb-1">As of Date</label>
                <input type="date" id="as_of_date" name="as_of_date" value="<?= $as_of_date ?>" 
                       class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
            </div>
            
            <!-- Minimum amount -->
            <div>
                <label for="min_amount" class="block text-sm font-medium text-gray-700 mb-1">Minimum Amount</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm"><?= $currency_symbol ?></span>
                    </div>
                    <input type="number" id="min_amount" name="min_amount" min="0" step="0.01"
                           value="<?= $min_amount ?>" 
                           class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-7 sm:text-sm border-gray-300 rounded-md"
                           placeholder="0.00">
                </div>
            </div>
            
            <!-- Group by -->
            <div>
                <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                <select id="group_by" name="group_by" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                    <option value="customer" <?= $group_by === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="invoice" <?= $group_by === 'invoice' ? 'selected' : '' ?>>Invoice</option>
                </select>
            </div>
            
            <!-- Filter buttons -->
            <div class="flex items-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="?<?= http_build_query(['as_of_date' => date('Y-m-d')]) ?>" class="ml-2 inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Report Header - only shown when printing -->
    <div class="hidden print:block mb-8 text-center">
        <h1 class="text-2xl font-bold"><?= htmlspecialchars(get_setting('company_name', 'Company Name')) ?></h1>
        <p class="text-gray-600"><?= htmlspecialchars(get_setting('company_address', 'Company Address')) ?></p>
        <p class="text-gray-600"><?= htmlspecialchars(get_setting('company_phone', 'Phone')) ?></p>
        <h2 class="text-xl font-bold mt-4">Credit Aging Report</h2>
        <p class="text-gray-600">As of: <?= date('F d, Y', strtotime($as_of_date)) ?></p>
        <p class="text-gray-600">Generated on: <?= date('F d, Y h:i A') ?></p>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Total Outstanding -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Outstanding</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($totals['total'], 2) ?></p>
                </div>
                <div class="bg-blue-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-blue-500"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Total receivables from all customers
            </p>
        </div>
        
        <!-- Current vs. Overdue -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Current vs. Overdue</p>
                    <div class="flex items-baseline mt-1">
                        <p class="text-xl font-bold text-green-600"><?= $currency_symbol ?> <?= number_format($totals['current'], 2) ?></p>
                        <p class="text-sm text-gray-500 ml-2">vs.</p>
                        <p class="text-xl font-bold text-red-600 ml-2"><?= $currency_symbol ?> <?= number_format($totals['total'] - $totals['current'], 2) ?></p>
                    </div>
                </div>
                <div class="bg-green-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-balance-scale text-green-500"></i>
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <?php $current_percentage = ($totals['total'] > 0) ? ($totals['current'] / $totals['total'] * 100) : 0; ?>
                <div class="bg-green-500 h-2.5 rounded-full" style="width: <?= min(100, $current_percentage) ?>%"></div>
            </div>
        </div>
        
        <!-- Risk Assessment -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Risk Assessment</p>
                    <div class="grid grid-cols-3 gap-2 mt-1">
                        <div class="text-center">
                            <p class="text-xs text-gray-500">Low</p>
                            <p class="text-sm font-bold text-green-600"><?= $currency_symbol ?> <?= number_format($risk_levels['low'], 2) ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-gray-500">Medium</p>
                            <p class="text-sm font-bold text-yellow-600"><?= $currency_symbol ?> <?= number_format($risk_levels['medium'], 2) ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-xs text-gray-500">High</p>
                            <p class="text-sm font-bold text-red-600"><?= $currency_symbol ?> <?= number_format($risk_levels['high'], 2) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-yellow-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Visual Chart -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h3 class="text-lg font-medium text-gray-800 mb-4">Aging Distribution</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Bar Chart -->
            <div>
                <canvas id="agingBarChart" height="250"></canvas>
            </div>
            
            <!-- Pie Chart -->
            <div>
                <canvas id="agingPieChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Aging Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">
                Aging Details
                <span class="text-sm font-normal text-gray-500 ml-2">
                    (As of <?= date('M d, Y', strtotime($as_of_date)) ?>)
                </span>
            </h3>
        </div>
        
        <?php if (empty($aging_data)): ?>
            <div class="p-6 text-center text-gray-500">
                No outstanding invoices found based on the selected filters.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <?php if ($group_by === 'customer'): ?>
                    <!-- Customer-grouped Aging Table -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">1-30 Days</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">31-60 Days</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">61-90 Days</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Over 90 Days</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider print:hidden">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($aging_data as $customer_data): ?>
                                <?php 
                                $customer_total = $customer_data['current'] + 
                                                 $customer_data['days_1_30'] + 
                                                 $customer_data['days_31_60'] + 
                                                 $customer_data['days_61_90'] + 
                                                 $customer_data['days_over_90'];
                                
                                // Skip if less than minimum amount
                                if ($customer_total < $min_amount) continue;
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-blue-100 rounded-full">
                                                <span class="text-blue-600 font-bold">
                                                    <?= strtoupper(substr($customer_data['customer_name'], 0, 1)) ?>
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($customer_data['customer_name']) ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <?= htmlspecialchars($customer_data['phone_number']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($customer_data['current'] > 0): ?>
                                            <span class="text-gray-900"><?= $currency_symbol ?> <?= number_format($customer_data['current'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($customer_data['days_1_30'] > 0): ?>
                                            <span class="text-yellow-600"><?= $currency_symbol ?> <?= number_format($customer_data['days_1_30'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($customer_data['days_31_60'] > 0): ?>
                                            <span class="text-orange-600"><?= $currency_symbol ?> <?= number_format($customer_data['days_31_60'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($customer_data['days_61_90'] > 0): ?>
                                            <span class="text-red-600"><?= $currency_symbol ?> <?= number_format($customer_data['days_61_90'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($customer_data['days_over_90'] > 0): ?>
                                            <span class="text-red-700 font-bold"><?= $currency_symbol ?> <?= number_format($customer_data['days_over_90'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-right">
                                        <?= $currency_symbol ?> <?= number_format($customer_total, 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right print:hidden">
                                        <a href="../credit_management/view_credit_customer.php?id=<?= $customer_data['customer_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($customer_total > 0): ?>
                                        <a href="../credit_settlement/add_settlement.php?customer_id=<?= $customer_data['customer_id'] ?>" class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-money-bill-wave"></i> Settle
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Invoice details (collapsed by default) -->
                                <?php if (!empty($customer_data['invoices'])): ?>
                                <tr class="invoice-details bg-gray-50 hidden">
                                    <td colspan="8" class="px-6 py-4">
                                        <div class="text-sm text-gray-700 mb-2 font-medium">Invoice Details</div>
                                        <table class="min-w-full divide-y divide-gray-200 border">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale Date</th>
                                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                                    <th scope="col" class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                                                    <th scope="col" class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($customer_data['invoices'] as $invoice): ?>
                                                <tr>
                                                    <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-blue-600">
                                                        <a href="../pos/receipt.php?id=<?= $invoice['sale_id'] ?>" target="_blank">
                                                            <?= $invoice['invoice_number'] ?>
                                                        </a>
                                                    </td>
                                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                                        <?= date('M d, Y', strtotime($invoice['sale_date'])) ?>
                                                    </td>
                                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                                        <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                                                    </td>
                                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-right">
                                                        <?= $currency_symbol ?> <?= number_format($invoice['amount'], 2) ?>
                                                    </td>
                                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-center">
                                                        <?php if ($invoice['days_overdue'] > 0): ?>
                                                            <span class="text-red-600"><?= $invoice['days_overdue'] ?> days</span>
                                                        <?php else: ?>
                                                            <span class="text-green-600">Not overdue</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-2 whitespace-nowrap text-xs text-center">
                                                        <?php 
                                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                                        if ($invoice['status'] === 'partial') {
                                                            $status_class = 'bg-blue-100 text-blue-800';
                                                        }
                                                        ?>
                                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_class ?>">
                                                            <?= ucfirst($invoice['status']) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-medium">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">Totals</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['current'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_1_30'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_31_60'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_61_90'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_over_90'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['total'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm print:hidden"></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <!-- Invoice-level Aging Table -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">1-30 Days</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">31-60 Days</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">61-90 Days</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Over 90 Days</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider print:hidden">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($aging_data as $invoice_data): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($invoice_data['customer_name']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= htmlspecialchars($invoice_data['phone_number']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                        <a href="../pos/receipt.php?id=<?= $invoice_data['sale_id'] ?>" target="_blank">
                                            <?= $invoice_data['invoice_number'] ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($invoice_data['due_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($invoice_data['current'] > 0): ?>
                                            <span class="text-gray-900"><?= $currency_symbol ?> <?= number_format($invoice_data['current'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($invoice_data['days_1_30'] > 0): ?>
                                            <span class="text-yellow-600"><?= $currency_symbol ?> <?= number_format($invoice_data['days_1_30'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($invoice_data['days_31_60'] > 0): ?>
                                            <span class="text-orange-600"><?= $currency_symbol ?> <?= number_format($invoice_data['days_31_60'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($invoice_data['days_61_90'] > 0): ?>
                                            <span class="text-red-600"><?= $currency_symbol ?> <?= number_format($invoice_data['days_61_90'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <?php if ($invoice_data['days_over_90'] > 0): ?>
                                            <span class="text-red-700 font-bold"><?= $currency_symbol ?> <?= number_format($invoice_data['days_over_90'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <?php if ($invoice_data['days_overdue'] > 0): ?>
                                            <span class="text-red-600 font-medium"><?= $invoice_data['days_overdue'] ?> days</span>
                                        <?php else: ?>
                                            <span class="text-green-600">Not overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right print:hidden">
                                        <a href="../credit_settlement/add_settlement.php?customer_id=<?= $invoice_data['customer_id'] ?>&invoice_id=<?= $invoice_data['sale_id'] ?>" class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-money-bill-wave"></i> Settle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-medium">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm" colspan="3">Totals</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['current'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_1_30'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_31_60'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_61_90'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?= $currency_symbol ?> <?= number_format($totals['days_over_90'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm" colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Data for charts
        const labels = ['Current', '1-30 Days', '31-60 Days', '61-90 Days', 'Over 90 Days'];
        const values = [
            <?= $totals['current'] ?>,
            <?= $totals['days_1_30'] ?>,
            <?= $totals['days_31_60'] ?>,
            <?= $totals['days_61_90'] ?>,
            <?= $totals['days_over_90'] ?>
        ];
        const backgroundColors = [
            'rgba(34, 197, 94, 0.7)', // Green for current
            'rgba(234, 179, 8, 0.7)',  // Yellow for 1-30 days
            'rgba(249, 115, 22, 0.7)', // Orange for 31-60 days
            'rgba(239, 68, 68, 0.7)',  // Red for 61-90 days
            'rgba(185, 28, 28, 0.7)'   // Dark red for over 90 days
        ];
        const borderColors = [
            'rgba(34, 197, 94, 1)',
            'rgba(234, 179, 8, 1)',
            'rgba(249, 115, 22, 1)',
            'rgba(239, 68, 68, 1)',
            'rgba(185, 28, 28, 1)'
        ];
        
        // Currency formatting for charts
        const currencySymbol = '<?= $currency_symbol ?>';
        const formatCurrency = (value) => {
            return currencySymbol + ' ' + new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        };
        
        // Create bar chart
        const barCtx = document.getElementById('agingBarChart').getContext('2d');
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Aging Amount',
                    data: values,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Accounts Receivable Aging'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return formatCurrency(context.raw);
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
        
        // Create pie chart
        const pieCtx = document.getElementById('agingPieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Aging Distribution'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + formatCurrency(context.raw) + 
                                       ' (' + Math.round(context.raw / context.dataset.data.reduce((a, b) => a + b, 0) * 100) + '%)';
                            }
                        }
                    }
                }
            }
        });
        
        // Toggle invoice details on customer row click (when grouped by customer)
        const customerRows = document.querySelectorAll('tr.hover\\:bg-gray-50');
        customerRows.forEach(row => {
            row.addEventListener('click', function() {
                const nextRow = this.nextElementSibling;
                if (nextRow && nextRow.classList.contains('invoice-details')) {
                    nextRow.classList.toggle('hidden');
                }
            });
        });
        
        // Print functionality
        document.querySelectorAll('.print-button').forEach(button => {
            button.addEventListener('click', function() {
                window.print();
            });
        });
    });
</script>

<!-- Print styles -->
<style media="print">
    @page {
        size: landscape;
        margin: 0.5cm;
    }
    
    body {
        font-size: 10pt;
    }
    
    .print\\:hidden {
        display: none !important;
    }
    
    .print\\:block {
        display: block !important;
    }
    
    .shadow-md {
        box-shadow: none !important;
    }
    
    table {
        width: 100% !important;
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    thead {
        display: table-header-group;
    }
    
    tfoot {
        display: table-footer-group;
    }
    
    button {
        display: none !important;
    }
    
    canvas {
        max-height: 2.5in !important;
    }
</style>

<?php include_once '../../includes/footer.php'; ?>