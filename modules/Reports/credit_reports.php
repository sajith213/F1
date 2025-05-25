<?php
/**
 * Credit Reports Dashboard
 * 
 * This file provides a central hub for accessing various credit reports
 */

// Set page title and include header
$page_title = "Credit Reports";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <span class="text-gray-700">Credit Reports</span>';

include_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission
if (!has_permission('manage_cash')) {
    $_SESSION['error_message'] = "You don't have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Get summary statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT c.customer_id) as total_customers,
        SUM(c.current_balance) as total_outstanding,
        (SELECT COUNT(*) FROM sales s WHERE s.credit_status != 'settled' AND s.due_date < CURDATE()) as overdue_invoices
    FROM credit_customers c
    WHERE c.status = 'active'
");

$summary = [
    'total_customers' => 0,
    'total_outstanding' => 0,
    'overdue_invoices' => 0
];

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $summary = array_merge($summary, $row);
    }
    
    $stmt->close();
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

// Helper function to safely format numbers, preventing null value deprecation warnings
function safe_number_format($value, $decimals = 0) {
    // Ensure the value is numeric and not null
    if (is_null($value)) {
        $value = 0;
    }
    return number_format((float)$value, $decimals);
}
?>

<div class="container mx-auto pb-6">
    
    <!-- Page title and actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Credit Reports</h2>
        
        <div class="flex gap-2">
            <a href="../credit_management/credit_customers.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-users mr-2"></i>
                Credit Customers
            </a>
            <a href="../credit_settlement/credit_settlements.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg flex items-center">
                <i class="fas fa-money-bill-wave mr-2"></i>
                Settlements
            </a>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Outstanding -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Total Outstanding</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= safe_number_format($summary['total_outstanding'], 2) ?></p>
                </div>
                <div class="p-3 bg-red-100 rounded-full w-12 h-12 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-red-500"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-sm text-gray-500">Total credit balance across all customers</p>
            </div>
        </div>
        
        <!-- Credit Customers -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Credit Customers</p>
                    <p class="text-2xl font-bold text-gray-800"><?= safe_number_format($summary['total_customers']) ?></p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full w-12 h-12 flex items-center justify-center">
                    <i class="fas fa-users text-blue-500"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-sm text-gray-500">Total active customers with credit accounts</p>
            </div>
        </div>
        
        <!-- Overdue Invoices -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 mb-1">Overdue Invoices</p>
                    <p class="text-2xl font-bold text-gray-800"><?= safe_number_format($summary['overdue_invoices']) ?></p>
                </div>
                <div class="p-3 bg-yellow-100 rounded-full w-12 h-12 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                </div>
            </div>
            <div class="mt-2">
                <p class="text-sm text-gray-500">Invoices past their due date</p>
            </div>
        </div>
    </div>
    
    <!-- Reports Grid -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">Available Reports</h3>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Outstanding Balances Report -->
                <a href="outstanding_balances_report.php" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <div class="p-2 mr-4 bg-blue-100 rounded-full">
                            <i class="fas fa-file-invoice-dollar text-blue-600 text-xl"></i>
                        </div>
                        <h5 class="text-lg font-semibold text-gray-900">Outstanding Balances</h5>
                    </div>
                    <p class="text-sm text-gray-600">View all customers with outstanding balances and their current credit status.</p>
                    <div class="mt-4 flex justify-end">
                        <span class="text-blue-600 inline-flex items-center text-sm">
                            View Report <i class="fas fa-arrow-right ml-2"></i>
                        </span>
                    </div>
                </a>
                
                <!-- Customer Credit History -->
                <a href="customer_credit_history.php" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <div class="p-2 mr-4 bg-green-100 rounded-full">
                            <i class="fas fa-history text-green-600 text-xl"></i>
                        </div>
                        <h5 class="text-lg font-semibold text-gray-900">Customer Credit History</h5>
                    </div>
                    <p class="text-sm text-gray-600">View detailed credit history and transactions for specific customers.</p>
                    <div class="mt-4 flex justify-end">
                        <span class="text-blue-600 inline-flex items-center text-sm">
                            View Report <i class="fas fa-arrow-right ml-2"></i>
                        </span>
                    </div>
                </a>
                
                <!-- Overdue Credits Report -->
                <a href="overdue_credits_report.php" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <div class="p-2 mr-4 bg-red-100 rounded-full">
                            <i class="fas fa-calendar-times text-red-600 text-xl"></i>
                        </div>
                        <h5 class="text-lg font-semibold text-gray-900">Overdue Credits</h5>
                    </div>
                    <p class="text-sm text-gray-600">View all overdue credit invoices that need attention or follow-up.</p>
                    <div class="mt-4 flex justify-end">
                        <span class="text-blue-600 inline-flex items-center text-sm">
                            View Report <i class="fas fa-arrow-right ml-2"></i>
                        </span>
                    </div>
                </a>
                
                <!-- Credit Aging Report -->
                <a href="credit_aging_report.php" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <div class="p-2 mr-4 bg-purple-100 rounded-full">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                        <h5 class="text-lg font-semibold text-gray-900">Credit Aging Report</h5>
                    </div>
                    <p class="text-sm text-gray-600">See the aging of receivables split into time periods (30, 60, 90+ days).</p>
                    <div class="mt-4 flex justify-end">
                        <span class="text-blue-600 inline-flex items-center text-sm">
                            View Report <i class="fas fa-arrow-right ml-2"></i>
                        </span>
                    </div>
                </a>
                
                <!-- Payment History Report -->
                <a href="../credit_settlement/credit_settlements.php" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <div class="p-2 mr-4 bg-indigo-100 rounded-full">
                            <i class="fas fa-money-check-alt text-indigo-600 text-xl"></i>
                        </div>
                        <h5 class="text-lg font-semibold text-gray-900">Payment History</h5>
                    </div>
                    <p class="text-sm text-gray-600">View history of all payments made by credit customers.</p>
                    <div class="mt-4 flex justify-end">
                        <span class="text-blue-600 inline-flex items-center text-sm">
                            View Report <i class="fas fa-arrow-right ml-2"></i>
                        </span>
                    </div>
                </a>
                
                <!-- Credit Limit Utilization -->
                <a href="#" onclick="alert('This report will be available in a future update.'); return false;" class="block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-50">
                    <div class="flex items-center mb-3">
                        <div class="p-2 mr-4 bg-yellow-100 rounded-full">
                            <i class="fas fa-chart-pie text-yellow-600 text-xl"></i>
                        </div>
                        <h5 class="text-lg font-semibold text-gray-900">Credit Limit Utilization</h5>
                    </div>
                    <p class="text-sm text-gray-600">View how customers are utilizing their available credit limits.</p>
                    <div class="mt-4 flex justify-end">
                        <span class="text-gray-500 inline-flex items-center text-sm">
                            Coming Soon <i class="fas fa-clock ml-2"></i>
                        </span>
                    </div>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Data Export Options -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-medium text-gray-800">Export Options</h3>
        </div>
        
        <div class="p-6">
            <p class="text-gray-600 mb-4">Export credit data for external analysis or reporting.</p>
            
            <div class="flex flex-wrap gap-4">
                <button onclick="alert('This feature will be available in a future update.')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-file-excel mr-2 text-green-600"></i> Export to Excel
                </button>
                <button onclick="alert('This feature will be available in a future update.')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-file-pdf mr-2 text-red-600"></i> Export to PDF
                </button>
                <button onclick="alert('This feature will be available in a future update.')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-file-csv mr-2 text-blue-600"></i> Export to CSV
                </button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>