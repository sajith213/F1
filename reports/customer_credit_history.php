<?php
/**
 * Customer Credit History Report
 * 
 * This file provides a detailed credit history report for a specific customer
 */

// Set page title and include header
$page_title = "Customer Credit History";
$breadcrumbs = '<a href="../index.php">Dashboard</a> / <a href="credit_reports.php">Credit Reports</a> / <span class="text-gray-700">Customer Credit History</span>';

include_once '../includes/header.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../index.php");
    exit;
}

// Initialize variables
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 year'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$transaction_type = isset($_GET['transaction_type']) ? $_GET['transaction_type'] : 'all';

// If customer ID is not provided, get all customers for selection
$customers = [];
if ($customer_id == 0) {
    $stmt = $conn->prepare("
        SELECT c.customer_id, c.customer_name, c.phone_number, c.current_balance,
               COUNT(DISTINCT t.transaction_id) as transaction_count
        FROM credit_customers c
        LEFT JOIN credit_transactions t ON c.customer_id = t.customer_id
        WHERE c.status = 'active'
        GROUP BY c.customer_id
        HAVING transaction_count > 0
        ORDER BY c.customer_name
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        
        $stmt->close();
    }
} else {
    // Get customer details
    $stmt = $conn->prepare("
        SELECT * FROM credit_customers
        WHERE customer_id = ?
    ");
    
    $customer = null;
    
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['error_message'] = "Customer not found.";
            header("Location: credit_reports.php");
            exit;
        }
        
        $customer = $result->fetch_assoc();
        $stmt->close();
    }
    
    // Get transactions
    $transactionQuery = "
        SELECT t.*, 
               s.invoice_number, s.due_date,
               u.full_name as recorded_by
        FROM credit_transactions t
        LEFT JOIN sales s ON t.sale_id = s.sale_id
        LEFT JOIN users u ON t.created_by = u.user_id
        WHERE t.customer_id = ?
        AND DATE(t.transaction_date) BETWEEN ? AND ?
    ";
    
    $params = [$customer_id, $start_date, $end_date];
    $types = "iss";
    
    if ($transaction_type !== 'all') {
        $transactionQuery .= " AND t.transaction_type = ?";
        $params[] = $transaction_type;
        $types .= "s";
    }
    
    $transactionQuery .= " ORDER BY t.transaction_date DESC";
    
    $transactions = [];
    $stmt = $conn->prepare($transactionQuery);
    
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        
        $stmt->close();
    }
    
    // Get settlements
    $settlementQuery = "
        SELECT s.*, u.full_name as recorded_by
        FROM credit_settlements s
        LEFT JOIN users u ON s.recorded_by = u.user_id
        WHERE s.customer_id = ?
        AND DATE(s.settlement_date) BETWEEN ? AND ?
        ORDER BY s.settlement_date DESC
    ";
    
    $settlements = [];
    $stmt = $conn->prepare($settlementQuery);
    
    if ($stmt) {
        $stmt->bind_param("iss", $customer_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $settlements[] = $row;
        }
        
        $stmt->close();
    }
    
    // Get outstanding invoices
    $invoiceQuery = "
        SELECT 
            s.sale_id,
            s.invoice_number,
            s.sale_date,
            s.net_amount,
            s.due_date,
            s.credit_status,
            DATEDIFF(CURRENT_DATE, s.due_date) as days_overdue,
            (SELECT COALESCE(SUM(t.amount), 0)
             FROM credit_transactions t
             WHERE t.sale_id = s.sale_id AND t.transaction_type = 'payment') as amount_paid,
            (s.net_amount - (SELECT COALESCE(SUM(t.amount), 0)
                            FROM credit_transactions t
                            WHERE t.sale_id = s.sale_id AND t.transaction_type = 'payment')) as balance_due
        FROM sales s
        WHERE s.credit_customer_id = ? AND s.credit_status != 'settled'
        ORDER BY s.due_date ASC
    ";
    
    $outstanding_invoices = [];
    $stmt = $conn->prepare($invoiceQuery);
    
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $outstanding_invoices[] = $row;
        }
        
        $stmt->close();
    }
    
    // Get summary statistics
    $summaryQuery = "
        SELECT 
            COUNT(CASE WHEN transaction_type = 'sale' THEN 1 END) as sale_count,
            COUNT(CASE WHEN transaction_type = 'payment' THEN 1 END) as payment_count,
            SUM(CASE WHEN transaction_type = 'sale' THEN amount ELSE 0 END) as total_sales,
            SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) as total_payments
        FROM credit_transactions
        WHERE customer_id = ?
        AND DATE(transaction_date) BETWEEN ? AND ?
    ";
    
    $summary = [
        'sale_count' => 0,
        'payment_count' => 0,
        'total_sales' => 0,
        'total_payments' => 0
    ];
    
    $stmt = $conn->prepare($summaryQuery);
    
    if ($stmt) {
        $stmt->bind_param("iss", $customer_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $summary = array_merge($summary, $row);
        }
        
        $stmt->close();
    }
    
    // Get monthly data for charts
    $monthlySalesQuery = "
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            SUM(CASE WHEN transaction_type = 'sale' THEN amount ELSE 0 END) as sales,
            SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) as payments
        FROM credit_transactions
        WHERE customer_id = ?
        AND DATE(transaction_date) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month ASC
    ";
    
    $monthly_data = [];
    $stmt = $conn->prepare($monthlySalesQuery);
    
    if ($stmt) {
        $stmt->bind_param("iss", $customer_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $monthly_data[] = $row;
        }
        
        $stmt->close();
    }
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
    
    <?php if ($customer_id == 0): ?>
    <!-- Customer Selection Screen -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Customer Credit History</h2>
            <p class="text-gray-600 mt-1">Select a customer to view detailed credit history</p>
        </div>
        
        <div>
            <a href="credit_reports.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Reports
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">Select a Customer</h3>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php if (empty($customers)): ?>
                    <div class="md:col-span-3 text-center py-6 text-gray-500">
                        <p>No customers with credit transactions found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <a href="?customer_id=<?= $c['customer_id'] ?>" class="block p-4 border rounded-lg hover:bg-gray-50 hover:border-blue-300 transition-colors duration-200">
                            <div class="flex items-center mb-2">
                                <div class="h-10 w-10 flex-shrink-0 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 font-bold">
                                        <?= strtoupper(substr($c['customer_name'], 0, 1)) ?>
                                    </span>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($c['customer_name']) ?></h4>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($c['phone_number']) ?></p>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-3 text-sm">
                                <span class="text-gray-500"><?= $c['transaction_count'] ?> transactions</span>
                                <span class="font-medium <?= $c['current_balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= $currency_symbol ?> <?= number_format($c['current_balance'], 2) ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Customer History Detail View -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">
                Credit History: <?= htmlspecialchars($customer['customer_name']) ?>
            </h2>
            <p class="text-gray-600 mt-1">
                Detailed credit transaction history and outstanding balances
            </p>
        </div>
        
        <div class="flex gap-2">
            <a href="?customer_id=0" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-exchange-alt mr-2"></i>
                Change Customer
            </a>
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
    
    <!-- Customer Information -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">Customer Information</h3>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Basic Info -->
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Contact Information</h4>
                    <div class="space-y-2">
                        <p class="flex items-center text-gray-700">
                            <i class="fas fa-id-card text-gray-400 w-5 mr-2"></i>
                            ID: <?= $customer['customer_id'] ?>
                        </p>
                        <p class="flex items-center text-gray-700">
                            <i class="fas fa-phone text-gray-400 w-5 mr-2"></i>
                            <?= htmlspecialchars($customer['phone_number']) ?>
                        </p>
                        <?php if (!empty($customer['email'])): ?>
                        <p class="flex items-center text-gray-700">
                            <i class="fas fa-envelope text-gray-400 w-5 mr-2"></i>
                            <?= htmlspecialchars($customer['email']) ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($customer['address'])): ?>
                        <p class="flex items-start text-gray-700">
                            <i class="fas fa-map-marker-alt text-gray-400 w-5 mr-2 mt-1"></i>
                            <span><?= nl2br(htmlspecialchars($customer['address'])) ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Credit Summary -->
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Credit Information</h4>
                    <div class="space-y-2">
                        <p class="flex justify-between">
                            <span class="text-gray-600">Credit Limit:</span>
                            <span class="font-medium text-gray-900">
                                <?= $currency_symbol ?> <?= number_format($customer['credit_limit'], 2) ?>
                            </span>
                        </p>
                        <p class="flex justify-between">
                            <span class="text-gray-600">Current Balance:</span>
                            <span class="font-medium <?= $customer['current_balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $currency_symbol ?> <?= number_format($customer['current_balance'], 2) ?>
                            </span>
                        </p>
                        <?php 
                            $available_credit = $customer['credit_limit'] - $customer['current_balance'];
                            $credit_class = 'text-green-600';
                            
                            if ($available_credit <= 0) {
                                $credit_class = 'text-red-600';
                            } elseif ($available_credit < ($customer['credit_limit'] * 0.1)) {
                                $credit_class = 'text-orange-600';
                            }
                        ?>
                        <p class="flex justify-between">
                            <span class="text-gray-600">Available Credit:</span>
                            <span class="font-medium <?= $credit_class ?>">
                                <?= $currency_symbol ?> <?= number_format($available_credit, 2) ?>
                            </span>
                        </p>
                        
                        <?php
                            $utilization = $customer['credit_limit'] > 0 
                                ? ($customer['current_balance'] / $customer['credit_limit'] * 100) 
                                : 0;
                        ?>
                        <p class="flex justify-between">
                            <span class="text-gray-600">Credit Utilization:</span>
                            <span class="font-medium <?= $utilization > 90 ? 'text-red-600' : 'text-gray-900' ?>">
                                <?= number_format($utilization, 1) ?>%
                            </span>
                        </p>
                    </div>
                </div>
                
                <!-- Account Status -->
                <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Account Status</h4>
                    <div class="space-y-2">
                        <p class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
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
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusBadge ?>">
                                <?= ucfirst($customer['status']) ?>
                            </span>
                        </p>
                        <p class="flex justify-between">
                            <span class="text-gray-600">Customer Since:</span>
                            <span class="font-medium text-gray-900">
                                <?= date('M d, Y', strtotime($customer['created_at'])) ?>
                            </span>
                        </p>
                        <p class="flex justify-between">
                            <span class="text-gray-600">Last Updated:</span>
                            <span class="font-medium text-gray-900">
                                <?= date('M d, Y', strtotime($customer['updated_at'])) ?>
                            </span>
                        </p>
                        <p class="flex justify-between">
                            <span class="text-gray-600">Outstanding Invoices:</span>
                            <span class="font-medium <?= count($outstanding_invoices) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= count($outstanding_invoices) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters and date range form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
            
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
                    <option value="all" <?= $transaction_type === 'all' ? 'selected' : '' ?>>All Types</option>
                    <option value="sale" <?= $transaction_type === 'sale' ? 'selected' : '' ?>>Sales Only</option>
                    <option value="payment" <?= $transaction_type === 'payment' ? 'selected' : '' ?>>Payments Only</option>
                    <option value="adjustment" <?= $transaction_type === 'adjustment' ? 'selected' : '' ?>>Adjustments Only</option>
                </select>
            </div>
            
            <div class="flex space-x-2">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="?customer_id=<?= $customer_id ?>" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Summary Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Sales -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Total Sales</p>
            <p class="text-xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['total_sales'], 2) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= number_format($summary['sale_count']) ?> transactions</p>
        </div>
        
        <!-- Total Payments -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Total Payments</p>
            <p class="text-xl font-bold text-gray-800"><?= $currency_symbol ?> <?= number_format($summary['total_payments'], 2) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= number_format($summary['payment_count']) ?> transactions</p>
        </div>
        
        <!-- Net Activity -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Net Activity</p>
            <?php $net_activity = $summary['total_sales'] - $summary['total_payments']; ?>
            <p class="text-xl font-bold <?= $net_activity > 0 ? 'text-red-600' : 'text-green-600' ?>">
                <?= $currency_symbol ?> <?= number_format(abs($net_activity), 2) ?>
                <?= $net_activity > 0 ? 'Deficit' : 'Surplus' ?>
            </p>
            <p class="text-xs text-gray-500 mt-1">
                <?= $net_activity > 0 ? 'More sales than payments' : 'More payments than sales' ?>
            </p>
        </div>
        
        <!-- Payment Ratio -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-yellow-500">
            <p class="text-sm font-medium text-gray-500 mb-1">Payment Ratio</p>
            <?php 
                $payment_ratio = $summary['total_sales'] > 0 
                    ? ($summary['total_payments'] / $summary['total_sales'] * 100) 
                    : 0;
            ?>
            <p class="text-xl font-bold text-gray-800"><?= number_format($payment_ratio, 1) ?>%</p>
            <p class="text-xs text-gray-500 mt-1">
                <?php if ($payment_ratio >= 100): ?>
                    All credit fully paid
                <?php elseif ($payment_ratio >= 80): ?>
                    Good payment history
                <?php elseif ($payment_ratio >= 50): ?>
                    Average payment history
                <?php else: ?>
                    Poor payment history
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <!-- Monthly Trend Chart -->
    <?php if (!empty($monthly_data)): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">Monthly Transaction Trend</h3>
        </div>
        
        <div class="p-6">
            <div style="height: 300px;">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Total Credit Issued</p>
                    <p class="text-lg font-bold text-blue-600">
                        <?= $currency_symbol ?> <?= number_format($summary['total_sales'], 2) ?>
                    </p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Total Credit Repaid</p>
                    <p class="text-lg font-bold text-green-600">
                        <?= $currency_symbol ?> <?= number_format($summary['total_payments'], 2) ?>
                    </p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Current Utilization</p>
                    <p class="text-lg font-bold <?= $utilization > 90 ? 'text-red-600' : 'text-blue-600' ?>">
                        <?= number_format($utilization, 1) ?>%
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Outstanding Invoices Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">
                Outstanding Invoices
                <?php if (count($outstanding_invoices) > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= count($outstanding_invoices) ?> invoices)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($outstanding_invoices)): ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                <p>No outstanding invoices. Customer's account is fully settled.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Due</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($outstanding_invoices as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                    <a href="../modules/pos/receipt.php?id=<?= $invoice['sale_id'] ?>" target="_blank">
                                        <?= $invoice['invoice_number'] ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($invoice['sale_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?= $currency_symbol ?> <?= number_format($invoice['net_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">
                                    <?= $currency_symbol ?> <?= number_format($invoice['amount_paid'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium text-right">
                                    <?= $currency_symbol ?> <?= number_format($invoice['balance_due'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <?php
                                    $statusBadge = 'bg-yellow-100 text-yellow-800';
                                    $statusText = 'Pending';
                                    
                                    if ($invoice['credit_status'] === 'partial') {
                                        $statusBadge = 'bg-blue-100 text-blue-800';
                                        $statusText = 'Partial';
                                    }
                                    
                                    if ($invoice['days_overdue'] > 0) {
                                        $statusBadge = 'bg-red-100 text-red-800';
                                        $statusText = 'Overdue';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusBadge ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <a href="../modules/credit_settlement/add_settlement.php?customer_id=<?= $customer_id ?>&invoice_id=<?= $invoice['sale_id'] ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-money-bill-wave mr-1"></i> Settle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Transactions History Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">
                Transaction History
                <?php if (count($transactions) > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= count($transactions) ?> transactions)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($transactions)): ?>
            <div class="p-6 text-center text-gray-500">
                <p>No transactions found for the selected period.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('M d, Y g:i A', strtotime($transaction['transaction_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if (!empty($transaction['invoice_number'])): ?>
                                        <a href="../modules/pos/receipt.php?id=<?= $transaction['sale_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                            <?= $transaction['invoice_number'] ?>
                                        </a>
                                    <?php else: ?>
                                        <?= $transaction['reference_no'] ?? '-' ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                    $typeBadge = '';
                                    $typeText = '';
                                    
                                    switch ($transaction['transaction_type']) {
                                        case 'sale':
                                            $typeBadge = 'bg-blue-100 text-blue-800';
                                            $typeText = 'Sale';
                                            break;
                                        case 'payment':
                                            $typeBadge = 'bg-green-100 text-green-800';
                                            $typeText = 'Payment';
                                            break;
                                        case 'adjustment':
                                            $typeBadge = 'bg-yellow-100 text-yellow-800';
                                            $typeText = 'Adjustment';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $typeBadge ?>">
                                        <?= $typeText ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <?php 
                                    $amountClass = 'text-gray-900';
                                    $amountPrefix = '';
                                    
                                    if ($transaction['transaction_type'] === 'sale') {
                                        $amountClass = 'text-red-600';
                                        $amountPrefix = '+';
                                    } elseif ($transaction['transaction_type'] === 'payment') {
                                        $amountClass = 'text-green-600';
                                        $amountPrefix = '-';
                                    }
                                    ?>
                                    <span class="<?= $amountClass ?> font-medium">
                                        <?= $amountPrefix ?><?= $currency_symbol ?> <?= number_format($transaction['amount'], 2) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?= $currency_symbol ?> <?= number_format($transaction['balance_after'], 2) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                    <?= $transaction['notes'] ?? '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $transaction['recorded_by'] ?? '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Settlement History Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">
                Settlement History
                <?php if (count($settlements) > 0): ?>
                <span class="text-sm font-normal text-gray-500 ml-2">(<?= count($settlements) ?> settlements)</span>
                <?php endif; ?>
            </h3>
        </div>
        
        <?php if (empty($settlements)): ?>
            <div class="p-6 text-center text-gray-500">
                <p>No settlements found for the selected period.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($settlements as $settlement): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('M d, Y g:i A', strtotime($settlement['settlement_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $settlement['reference_no'] ?? '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php
                                    $methodBadge = 'bg-blue-100 text-blue-800';
                                    $methodIcon = 'fa-money-bill';
                                    
                                    switch ($settlement['payment_method']) {
                                        case 'cash':
                                            $methodBadge = 'bg-green-100 text-green-800';
                                            $methodIcon = 'fa-money-bill';
                                            break;
                                        case 'bank_transfer':
                                            $methodBadge = 'bg-blue-100 text-blue-800';
                                            $methodIcon = 'fa-university';
                                            break;
                                        case 'check':
                                            $methodBadge = 'bg-purple-100 text-purple-800';
                                            $methodIcon = 'fa-money-check';
                                            break;
                                        case 'mobile_payment':
                                            $methodBadge = 'bg-indigo-100 text-indigo-800';
                                            $methodIcon = 'fa-mobile-alt';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?= $methodBadge ?>">
                                        <i class="fas <?= $methodIcon ?> mr-1"></i>
                                        <?= ucwords(str_replace('_', ' ', $settlement['payment_method'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium text-right">
                                    <?= $currency_symbol ?> <?= number_format($settlement['amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                    <?= $settlement['notes'] ?? '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $settlement['recorded_by'] ?? '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <a href="../modules/credit_settlement/settlement_receipt.php?id=<?= $settlement['settlement_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-receipt mr-1"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js for the visualization -->
<?php if ($customer_id > 0 && !empty($monthly_data)): ?>
<!-- Adding Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
    
    // Prepare data for the chart
    const monthlyData = <?= json_encode($monthly_data) ?>;
    const labels = monthlyData.map(item => {
        const date = new Date(item.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
    });
    const salesData = monthlyData.map(item => parseFloat(item.sales));
    const paymentsData = monthlyData.map(item => parseFloat(item.payments));
    const balanceData = monthlyData.map((item, index) => {
        let cumulativeSales = 0;
        let cumulativePayments = 0;
        
        // Calculate cumulative totals up to this month
        for (let i = 0; i <= index; i++) {
            cumulativeSales += parseFloat(monthlyData[i].sales);
            cumulativePayments += parseFloat(monthlyData[i].payments);
        }
        
        return cumulativeSales - cumulativePayments;
    });
    
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sales',
                    data: salesData,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Payments',
                    data: paymentsData,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Running Balance',
                    data: balanceData,
                    type: 'line',
                    fill: false,
                    borderColor: 'rgba(245, 158, 11, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(245, 158, 11, 1)',
                    pointRadius: 4,
                    tension: 0.1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (<?= $currency_symbol ?>)'
                    }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Balance (<?= $currency_symbol ?>)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '<?= $currency_symbol ?> ' + parseFloat(context.raw).toFixed(2);
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

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