<?php
/**
 * Credit Management - View Credit Customer Details
 * 
 * This file displays detailed information about a credit customer,
 * including transaction history and credit settlements
 */

// Set page title and include header
$page_title = "Customer Details";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="credit_customers.php">Credit Customers</a> / <span class="text-gray-700">Customer Details</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Initialize variables
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;
$transactions = [];
$settlements = [];
$sales = [];

// Define date range filter (default to last 3 months)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-3 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Check if customer ID is valid
if ($customer_id <= 0) {
    $_SESSION['error_message'] = "Invalid customer ID.";
    header("Location: credit_customers.php");
    exit;
}

// Fetch the customer data
$stmt = $conn->prepare("
    SELECT * FROM credit_customers 
    WHERE customer_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Customer not found.";
        header("Location: credit_customers.php");
        exit;
    }
    
    $customer = $result->fetch_assoc();
    $stmt->close();
} else {
    $_SESSION['error_message'] = "Database error: " . $conn->error;
    header("Location: credit_customers.php");
    exit;
}

// Fetch credit transactions
$stmt = $conn->prepare("
    SELECT ct.*, u.full_name as recorded_by
    FROM credit_transactions ct
    LEFT JOIN users u ON ct.created_by = u.user_id
    WHERE ct.customer_id = ?
    AND (DATE(ct.transaction_date) BETWEEN ? AND ?)
    ORDER BY ct.transaction_date DESC
");

if ($stmt) {
    $stmt->bind_param("iss", $customer_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    $stmt->close();
}

// Fetch credit settlements
$stmt = $conn->prepare("
    SELECT cs.*, u.full_name as recorded_by
    FROM credit_settlements cs
    LEFT JOIN users u ON cs.recorded_by = u.user_id
    WHERE cs.customer_id = ?
    AND (DATE(cs.settlement_date) BETWEEN ? AND ?)
    ORDER BY cs.settlement_date DESC
");

if ($stmt) {
    $stmt->bind_param("iss", $customer_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $settlements[] = $row;
    }
    
    $stmt->close();
}

// Fetch credit sales
$stmt = $conn->prepare("
    SELECT s.*, u.full_name as staff_name
    FROM sales s
    LEFT JOIN users u ON s.staff_id = u.user_id
    WHERE s.credit_customer_id = ?
    AND (DATE(s.sale_date) BETWEEN ? AND ?)
    ORDER BY s.sale_date DESC
");

if ($stmt) {
    $stmt->bind_param("iss", $customer_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    
    $stmt->close();
}

// Get summary statistics
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN transaction_type = 'sale' THEN amount ELSE 0 END) as total_sales,
        SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) as total_payments,
        SUM(CASE WHEN transaction_type = 'adjustment' THEN amount ELSE 0 END) as total_adjustments,
        COUNT(CASE WHEN transaction_type = 'sale' THEN 1 END) as sale_count,
        COUNT(CASE WHEN transaction_type = 'payment' THEN 1 END) as payment_count
    FROM credit_transactions
    WHERE customer_id = ?
    AND (DATE(transaction_date) BETWEEN ? AND ?)
");

$summary = [
    'total_sales' => 0,
    'total_payments' => 0,
    'total_adjustments' => 0,
    'sale_count' => 0,
    'payment_count' => 0
];

if ($stmt) {
    $stmt->bind_param("iss", $customer_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $summary = array_merge($summary, $row);
    }
    
    $stmt->close();
}

// Get outstanding invoices
$stmt = $conn->prepare("
    SELECT 
        s.sale_id,
        s.invoice_number,
        s.sale_date,
        s.total_amount,
        s.net_amount,
        s.payment_status,
        s.credit_status,
        s.due_date,
        DATEDIFF(CURRENT_DATE, s.due_date) as days_overdue
    FROM sales s
    WHERE s.credit_customer_id = ?
    AND s.credit_status != 'settled'
    ORDER BY s.due_date ASC
");

$outstanding_invoices = [];

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $outstanding_invoices[] = $row;
    }
    
    $stmt->close();
}

// Calculate aging of receivables
$aging = [
    'current' => 0,
    '1_30' => 0,
    '31_60' => 0,
    '61_90' => 0,
    'over_90' => 0
];

foreach ($outstanding_invoices as $invoice) {
    $days_overdue = max(0, $invoice['days_overdue']);
    
    if ($days_overdue <= 0) {
        $aging['current'] += $invoice['net_amount'];
    } elseif ($days_overdue <= 30) {
        $aging['1_30'] += $invoice['net_amount'];
    } elseif ($days_overdue <= 60) {
        $aging['31_60'] += $invoice['net_amount'];
    } elseif ($days_overdue <= 90) {
        $aging['61_90'] += $invoice['net_amount'];
    } else {
        $aging['over_90'] += $invoice['net_amount'];
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

// Check if success message exists
$success_message = $_SESSION['success_message'] ?? '';
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}

// Check if error message exists
$error_message = $_SESSION['error_message'] ?? '';
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
}
?>

<div class="container mx-auto pb-6">
    
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
    
    <!-- Customer Overview -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">Customer Profile</h3>
                <p class="text-sm text-gray-500 mt-1">ID: <?= $customer['customer_id'] ?></p>
            </div>
            <div class="flex space-x-2">
                <?php if ($customer['current_balance'] > 0): ?>
                <a href="../credit_settlement/add_settlement.php?customer_id=<?= $customer_id ?>" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    <i class="fas fa-money-bill-wave mr-1"></i> Record Payment
                </a>
                <?php endif; ?>
                <a href="edit_credit_customer.php?id=<?= $customer_id ?>" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-edit mr-1"></i> Edit Customer
                </a>
                <a href="credit_customers.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                    <i class="fas fa-arrow-left mr-1"></i> Back to List
                </a>
            </div>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Customer Details -->
                <div class="md:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-lg font-medium text-gray-800 mb-3"><?= htmlspecialchars($customer['customer_name']) ?></h4>
                            <div class="space-y-2">
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
                            
                            <?php if (!empty($customer['notes'])): ?>
                            <div class="mt-4 p-3 bg-gray-50 rounded-md">
                                <h5 class="text-sm font-medium text-gray-700 mb-1">Notes</h5>
                                <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($customer['notes'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <div class="bg-gray-50 rounded-md p-4">
                                <h5 class="text-sm font-medium text-gray-700 mb-2">Account Information</h5>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-xs text-gray-500">Status</p>
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
                                        <p class="text-sm font-medium mt-1">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusBadge ?>">
                                                <?= ucfirst($customer['status']) ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Customer Since</p>
                                        <p class="text-sm font-medium mt-1"><?= date('M d, Y', strtotime($customer['created_at'])) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Last Updated</p>
                                        <p class="text-sm font-medium mt-1"><?= date('M d, Y', strtotime($customer['updated_at'])) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Total Sales</p>
                                        <p class="text-sm font-medium mt-1"><?= count($sales) ?> orders</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Credit Summary -->
                <div class="bg-gray-50 rounded-md p-4">
                    <h4 class="text-md font-medium text-gray-800 mb-4">Credit Summary</h4>
                    
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-gray-500">Credit Limit</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?= $currency_symbol ?> <?= number_format(floatval($customer['credit_limit'] ?? 0), 2) ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-500">Current Balance</p>
                            <p class="text-xl font-bold <?= $customer['current_balance'] > 0 ? 'text-red-600' : 'text-gray-800' ?>">
                                <?= $currency_symbol ?> <?= number_format(floatval($customer['current_balance'] ?? 0), 2) ?>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-sm text-gray-500">Available Credit</p>
                            <?php 
                            $available_credit = floatval($customer['credit_limit'] ?? 0) - floatval($customer['current_balance'] ?? 0);
                            $credit_class = 'text-green-600';
                            
                            if ($available_credit <= 0) {
                                $credit_class = 'text-red-600';
                            } elseif ($available_credit < (floatval($customer['credit_limit'] ?? 0) * 0.1)) {
                                $credit_class = 'text-orange-600';
                            }
                            ?>
                            <p class="text-xl font-bold <?= $credit_class ?>">
                                <?= $currency_symbol ?> <?= number_format($available_credit, 2) ?>
                            </p>
                        </div>
                        
                        <!-- Credit Utilization Bar -->
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <p class="text-sm text-gray-500">Credit Utilization</p>
                                <?php 
                                $utilization = floatval($customer['credit_limit'] ?? 0) > 0 
                                    ? (floatval($customer['current_balance'] ?? 0) / floatval($customer['credit_limit']) * 100) 
                                    : 0;
                                ?>
                                <p class="text-sm font-medium <?= $utilization > 90 ? 'text-red-600' : 'text-gray-700' ?>">
                                    <?= number_format($utilization, 1) ?>%
                                </p>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <?php
                                $bar_color = 'bg-green-500';
                                
                                if ($utilization > 90) {
                                    $bar_color = 'bg-red-500';
                                } elseif ($utilization > 75) {
                                    $bar_color = 'bg-orange-500';
                                } elseif ($utilization > 50) {
                                    $bar_color = 'bg-yellow-500';
                                }
                                ?>
                                <div class="<?= $bar_color ?> h-2.5 rounded-full" style="width: <?= min(100, $utilization) ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">Filter Transactions</h3>
        </div>
        <div class="p-6">
            <form method="GET" action="" class="flex flex-wrap gap-4 items-end">
                <input type="hidden" name="id" value="<?= $customer_id ?>">
                
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
                    <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Apply Filter
                    </button>
                </div>
                
                <div class="ml-auto">
                    <a href="?id=<?= $customer_id ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-redo mr-2"></i> Reset
                    </a>
                    <a href="#" onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ml-2">
                        <i class="fas fa-print mr-2"></i> Print
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Activity Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Sale Transactions -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-blue-200 bg-blue-50">
                <h3 class="text-lg font-medium text-blue-800">Sale Activity</h3>
            </div>
            <div class="p-6">
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-800"><?= intval($summary['sale_count'] ?? 0) ?></p>
                    <p class="text-sm text-gray-500 mt-1">Total Sales</p>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-xl font-bold text-gray-800">
                        <?= $currency_symbol ?> <?= number_format(floatval($summary['total_sales'] ?? 0), 2) ?>
                    </p>
                    <p class="text-sm text-gray-500 mt-1">Total Sale Amount</p>
                </div>
            </div>
        </div>
        
        <!-- Payment Transactions -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-green-200 bg-green-50">
                <h3 class="text-lg font-medium text-green-800">Payment Activity</h3>
            </div>
            <div class="p-6">
                <div class="text-center">
                    <p class="text-3xl font-bold text-gray-800"><?= intval($summary['payment_count'] ?? 0) ?></p>
                    <p class="text-sm text-gray-500 mt-1">Total Payments</p>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-xl font-bold text-gray-800">
                        <?= $currency_symbol ?> <?= number_format(floatval($summary['total_payments'] ?? 0), 2) ?>
                    </p>
                    <p class="text-sm text-gray-500 mt-1">Total Payment Amount</p>
                </div>
            </div>
        </div>
        
        <!-- Aging of Receivables -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-yellow-200 bg-yellow-50">
                <h3 class="text-lg font-medium text-yellow-800">Aging of Receivables</h3>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Current</span>
                        <span class="text-sm font-medium text-gray-800">
                            <?= $currency_symbol ?> <?= number_format(floatval($aging['current'] ?? 0), 2) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">1-30 Days</span>
                        <span class="text-sm font-medium text-yellow-600">
                            <?= $currency_symbol ?> <?= number_format(floatval($aging['1_30'] ?? 0), 2) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">31-60 Days</span>
                        <span class="text-sm font-medium text-orange-600">
                            <?= $currency_symbol ?> <?= number_format(floatval($aging['31_60'] ?? 0), 2) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">61-90 Days</span>
                        <span class="text-sm font-medium text-red-600">
                            <?= $currency_symbol ?> <?= number_format(floatval($aging['61_90'] ?? 0), 2) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Over 90 Days</span>
                        <span class="text-sm font-medium text-red-700">
                            <?= $currency_symbol ?> <?= number_format(floatval($aging['over_90'] ?? 0), 2) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabs for Different Sections -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px" aria-label="Tabs">
                <button onclick="showTab('outstanding')" id="tab-btn-outstanding" class="tab-btn tab-active border-blue-500 text-blue-600 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                    Outstanding Invoices
                </button>
                <button onclick="showTab('transactions')" id="tab-btn-transactions" class="tab-btn text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 border-transparent font-medium text-sm">
                    Transaction History
                </button>
                <button onclick="showTab('settlements')" id="tab-btn-settlements" class="tab-btn text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 border-transparent font-medium text-sm">
                    Payment Settlements
                </button>
                <button onclick="showTab('sales')" id="tab-btn-sales" class="tab-btn text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 border-transparent font-medium text-sm">
                    Sales History
                </button>
            </nav>
        </div>
        
        <!-- Outstanding Invoices Tab -->
        <div id="tab-outstanding" class="tab-content p-6">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Outstanding Invoices</h3>
            
            <?php if (empty($outstanding_invoices)): ?>
                <div class="text-center py-6 text-gray-500">
                    <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                    <p>No outstanding invoices. Customer's account is fully settled.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Days Overdue</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($outstanding_invoices as $invoice): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                        <a href="../pos/receipt.php?id=<?= $invoice['sale_id'] ?>" target="_blank">
                                            <?= $invoice['invoice_number'] ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y', strtotime($invoice['sale_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        <?= date('M d, Y', strtotime($invoice['due_date'])) ?>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $currency_symbol ?> <?= number_format(floatval($invoice['net_amount'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <?php if ($invoice['days_overdue'] > 0): ?>
                                            <span class="text-red-600 font-medium"><?= $invoice['days_overdue'] ?> days</span>
                                        <?php else: ?>
                                            <span class="text-green-600">Not overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <a href="../credit_settlement/add_settlement.php?customer_id=<?= $customer_id ?>&invoice_id=<?= $invoice['sale_id'] ?>" class="text-green-600 hover:text-green-900">
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
        
        <!-- Transaction History Tab -->
        <div id="tab-transactions" class="tab-content p-6 hidden">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Transaction History</h3>
            
            <?php if (empty($transactions)): ?>
                <div class="text-center py-6 text-gray-500">
                    <p>No transactions found for the selected date range.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance After</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M d, Y g:i A', strtotime($transaction['transaction_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($transaction['sale_id']): ?>
                                            <a href="../pos/receipt.php?id=<?= $transaction['sale_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                                <?= $transaction['reference_no'] ?? 'View Sale' ?>
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
                                            <?= $amountPrefix ?><?= $currency_symbol ?> <?= number_format(floatval($transaction['amount'] ?? 0), 2) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $currency_symbol ?> <?= number_format(floatval($transaction['balance_after'] ?? 0), 2) ?>
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
        
        <!-- Payment Settlements Tab -->
        <div id="tab-settlements" class="tab-content p-6 hidden">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Payment Settlements</h3>
            
            <?php if (empty($settlements)): ?>
                <div class="text-center py-6 text-gray-500">
                    <p>No payment settlements found for the selected date range.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded By</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                        <?= $currency_symbol ?> <?= number_format(floatval($settlement['amount'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                        <?= $settlement['notes'] ?? '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $settlement['recorded_by'] ?? '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <a href="../credit_settlement/settlement_receipt.php?id=<?= $settlement['settlement_id'] ?>" class="text-blue-600 hover:text-blue-900">
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
        
        <!-- Sales History Tab -->
        <div id="tab-sales" class="tab-content p-6 hidden">
            <h3 class="text-lg font-medium text-gray-800 mb-4">Sales History</h3>
            
            <?php if (empty($sales)): ?>
                <div class="text-center py-6 text-gray-500">
                    <p>No sales found for the selected date range.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($sales as $sale): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                        <a href="../pos/receipt.php?id=<?= $sale['sale_id'] ?>" target="_blank">
                                            <?= $sale['invoice_number'] ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M d, Y g:i A', strtotime($sale['sale_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                        $typeBadge = '';
                                        
                                        switch ($sale['sale_type']) {
                                            case 'fuel':
                                                $typeBadge = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'product':
                                                $typeBadge = 'bg-green-100 text-green-800';
                                                break;
                                            case 'mixed':
                                                $typeBadge = 'bg-purple-100 text-purple-800';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $typeBadge ?>">
                                            <?= ucfirst($sale['sale_type']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $sale['staff_name'] ?? '-' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        <?= $currency_symbol ?> <?= number_format(floatval($sale['net_amount'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <?php
                                        $statusBadge = '';
                                        
                                        switch ($sale['credit_status']) {
                                            case 'pending':
                                                $statusBadge = 'bg-yellow-100 text-yellow-800';
                                                $statusText = 'Pending';
                                                break;
                                            case 'partial':
                                                $statusBadge = 'bg-blue-100 text-blue-800';
                                                $statusText = 'Partial';
                                                break;
                                            case 'settled':
                                                $statusBadge = 'bg-green-100 text-green-800';
                                                $statusText = 'Settled';
                                                break;
                                            default:
                                                $statusBadge = 'bg-gray-100 text-gray-800';
                                                $statusText = $sale['payment_status'];
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusBadge ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <a href="../pos/receipt.php?id=<?= $sale['sale_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function showTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Show the selected tab content
        document.getElementById('tab-' + tabName).classList.remove('hidden');
        
        // Update tab button styles
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('tab-active', 'border-blue-500', 'text-blue-600');
            btn.classList.add('text-gray-500', 'border-transparent');
        });
        
        // Add active styles to the clicked tab button
        document.getElementById('tab-btn-' + tabName).classList.remove('text-gray-500', 'border-transparent');
        document.getElementById('tab-btn-' + tabName).classList.add('tab-active', 'border-blue-500', 'text-blue-600');
    }
</script>

<?php include_once '../../includes/footer.php'; ?>