<?php
/**
 * Reports Module - Main Index
 * 
 * This file serves as the main entry point for the reports module
 * and displays links to various available reports.
 */

// Set page title and include header
$page_title = "Reports Dashboard";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <span class="text-gray-700">Reports</span>';

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

// Get currency symbol from settings
$currency_symbol = get_setting('currency_symbol', 'Rs.');

// Get report statistics for dashboard
$stats = [];

// Total fuel sales query
$fuelSalesQuery = "SELECT 
    COALESCE(SUM(si.total_price), 0) as total_fuel_sales
FROM sales s
JOIN sale_items si ON s.sale_id = si.sale_id
WHERE si.item_type = 'fuel'
AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

$result = $conn->query($fuelSalesQuery);
if ($result && $row = $result->fetch_assoc()) {
    $stats['fuel_sales'] = $row['total_fuel_sales'];
} else {
    $stats['fuel_sales'] = 0;
}

// Total product sales query
$productSalesQuery = "SELECT 
    COALESCE(SUM(si.total_price), 0) as total_product_sales
FROM sales s
JOIN sale_items si ON s.sale_id = si.sale_id
WHERE si.item_type = 'product'
AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

$result = $conn->query($productSalesQuery);
if ($result && $row = $result->fetch_assoc()) {
    $stats['product_sales'] = $row['total_product_sales'];
} else {
    $stats['product_sales'] = 0;
}

// Get tank utilization
$tankUtilizationQuery = "SELECT 
    COALESCE(SUM(current_volume), 0) as current_volume,
    COALESCE(SUM(capacity), 0) as total_capacity
FROM tanks
WHERE status = 'active'";

$result = $conn->query($tankUtilizationQuery);
if ($result && $row = $result->fetch_assoc()) {
    $stats['tank_usage'] = $row['current_volume'];
    $stats['tank_capacity'] = $row['total_capacity'];
    $stats['tank_utilization'] = $row['total_capacity'] > 0 ? 
        ($row['current_volume'] / $row['total_capacity'] * 100) : 0;
} else {
    $stats['tank_usage'] = 0;
    $stats['tank_capacity'] = 0;
    $stats['tank_utilization'] = 0;
}

// Get staff attendance stats
$attendanceQuery = "SELECT 
    COUNT(*) as total_staff,
    COALESCE(SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END), 0) as present_today
FROM staff s
LEFT JOIN (
    SELECT staff_id, status FROM attendance_records 
    WHERE attendance_date = CURDATE()
) a ON s.staff_id = a.staff_id
WHERE s.status = 'active'";

$result = $conn->query($attendanceQuery);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_staff'] = $row['total_staff'];
    $stats['present_today'] = $row['present_today'];
    $stats['attendance_percentage'] = $row['total_staff'] > 0 ? 
        ($row['present_today'] / $row['total_staff'] * 100) : 0;
} else {
    $stats['total_staff'] = 0;
    $stats['present_today'] = 0;
    $stats['attendance_percentage'] = 0;
}

// Get credit customers stats
$creditQuery = "SELECT 
    COUNT(*) as total_customers,
    COALESCE(SUM(current_balance), 0) as total_balance,
    COALESCE(SUM(CASE WHEN current_balance > 0 THEN 1 ELSE 0 END), 0) as customers_with_balance
FROM credit_customers
WHERE status = 'active'";

$result = $conn->query($creditQuery);
if ($result && $row = $result->fetch_assoc()) {
    $stats['credit_customers'] = $row['total_customers'];
    $stats['credit_balance'] = $row['total_balance'];
    $stats['customers_with_balance'] = $row['customers_with_balance'];
} else {
    $stats['credit_customers'] = 0;
    $stats['credit_balance'] = 0;
    $stats['customers_with_balance'] = 0;
}
?>

<div class="container mx-auto pb-6">
    
    <!-- Page title and actions -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800">Reports Dashboard</h2>
    </div>
    
    <!-- Dashboard Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Sales -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-blue-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Total Sales (30 Days)</p>
                    <p class="text-2xl font-bold text-gray-800">
                        <?= $currency_symbol ?><?= number_format($stats['fuel_sales'] + $stats['product_sales'], 2) ?>
                    </p>
                </div>
                <div class="bg-blue-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-chart-line text-blue-500"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Fuel: <?= $currency_symbol ?><?= number_format($stats['fuel_sales'], 2) ?> | 
                Products: <?= $currency_symbol ?><?= number_format($stats['product_sales'], 2) ?>
            </p>
        </div>
        
        <!-- Tank Utilization -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-green-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Tank Utilization</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($stats['tank_utilization'], 1) ?>%</p>
                </div>
                <div class="bg-green-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-gas-pump text-green-500"></i>
                </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                <div class="bg-green-500 h-2.5 rounded-full" style="width: <?= min(100, $stats['tank_utilization']) ?>%"></div>
            </div>
        </div>
        
        <!-- Staff Attendance -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-purple-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Staff Attendance Today</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($stats['attendance_percentage'], 1) ?>%</p>
                </div>
                <div class="bg-purple-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-user-check text-purple-500"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                <?= $stats['present_today'] ?> present out of <?= $stats['total_staff'] ?> staff members
            </p>
        </div>
        
        <!-- Credit Balance -->
        <div class="bg-white rounded-lg shadow-md p-4 border-l-4 border-red-500">
            <div class="flex justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500">Outstanding Credit</p>
                    <p class="text-2xl font-bold text-gray-800">
                        <?= $currency_symbol ?><?= number_format($stats['credit_balance'], 2) ?>
                    </p>
                </div>
                <div class="bg-red-100 rounded-full p-2 h-10 w-10 flex items-center justify-center">
                    <i class="fas fa-credit-card text-red-500"></i>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                <?= $stats['customers_with_balance'] ?> customers with outstanding balances
            </p>
        </div>
    </div>
    
    <!-- Reports Categories -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- Sales Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-blue-500 p-4">
                <h3 class="text-lg font-semibold text-white">Sales Reports</h3>
            </div>
            <div class="p-4">
                <ul class="space-y-2 mt-2">
                    <li>
                        <a href="daily_sales_report.php" class="flex items-center text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-md">
                            <i class="fas fa-chart-bar w-6 text-center mr-2"></i>
                            <span>Daily Sales Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="monthly_sales_report.php" class="flex items-center text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-md">
                            <i class="fas fa-calendar-alt w-6 text-center mr-2"></i>
                            <span>Monthly Sales Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="product_sales_report.php" class="flex items-center text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-md">
                            <i class="fas fa-shopping-basket w-6 text-center mr-2"></i>
                            <span>Product Sales Analysis</span>
                        </a>
                    </li>
                    <li>
                        <a href="sales_by_customer.php" class="flex items-center text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-md">
                            <i class="fas fa-users w-6 text-center mr-2"></i>
                            <span>Sales by Customer</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Inventory Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-green-500 p-4">
                <h3 class="text-lg font-semibold text-white">Inventory Reports</h3>
            </div>
            <div class="p-4">
                <ul class="space-y-2 mt-2">
                    <li>
                        <a href="fuel_capacity_report.php" class="flex items-center text-green-600 hover:text-green-800 hover:bg-green-50 p-2 rounded-md">
                            <i class="fas fa-gas-pump w-6 text-center mr-2"></i>
                            <span>Fuel Capacity Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="tank_dip_report.php" class="flex items-center text-green-600 hover:text-green-800 hover:bg-green-50 p-2 rounded-md">
                            <i class="fas fa-ruler w-6 text-center mr-2"></i>
                            <span>Tank Dip Readings</span>
                        </a>
                    </li>
                    <li>
                        <a href="product_inventory.php" class="flex items-center text-green-600 hover:text-green-800 hover:bg-green-50 p-2 rounded-md">
                            <i class="fas fa-boxes w-6 text-center mr-2"></i>
                            <span>Product Inventory Status</span>
                        </a>
                    </li>
                    <li>
                        <a href="low_stock_report.php" class="flex items-center text-green-600 hover:text-green-800 hover:bg-green-50 p-2 rounded-md">
                            <i class="fas fa-exclamation-triangle w-6 text-center mr-2"></i>
                            <span>Low Stock Alert Report</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Financial Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-yellow-500 p-4">
                <h3 class="text-lg font-semibold text-white">Financial Reports</h3>
            </div>
            <div class="p-4">
                <ul class="space-y-2 mt-2">
                    <li>
                        <a href="profit_loss_report.php" class="flex items-center text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 p-2 rounded-md">
                            <i class="fas fa-chart-line w-6 text-center mr-2"></i>
                            <span>Profit & Loss Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="cash_flow_report.php" class="flex items-center text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 p-2 rounded-md">
                            <i class="fas fa-money-bill-wave w-6 text-center mr-2"></i>
                            <span>Cash Flow Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="expense_report.php" class="flex items-center text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 p-2 rounded-md">
                            <i class="fas fa-file-invoice-dollar w-6 text-center mr-2"></i>
                            <span>Expense Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="petroleum_account_report.php" class="flex items-center text-yellow-600 hover:text-yellow-800 hover:bg-yellow-50 p-2 rounded-md">
                            <i class="fas fa-piggy-bank w-6 text-center mr-2"></i>
                            <span>Petroleum Account Report</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Staff Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-purple-500 p-4">
                <h3 class="text-lg font-semibold text-white">Staff Reports</h3>
            </div>
            <div class="p-4">
                <ul class="space-y-2 mt-2">
                    <li>
                        <a href="staff_attendance_report.php" class="flex items-center text-purple-600 hover:text-purple-800 hover:bg-purple-50 p-2 rounded-md">
                            <i class="fas fa-user-clock w-6 text-center mr-2"></i>
                            <span>Staff Attendance Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="staff_performance_report.php" class="flex items-center text-purple-600 hover:text-purple-800 hover:bg-purple-50 p-2 rounded-md">
                            <i class="fas fa-user-check w-6 text-center mr-2"></i>
                            <span>Staff Performance Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="../salary/salary_report.php" class="flex items-center text-purple-600 hover:text-purple-800 hover:bg-purple-50 p-2 rounded-md">
                            <i class="fas fa-money-check-alt w-6 text-center mr-2"></i>
                            <span>Salary Reports</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Credit Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-red-500 p-4">
                <h3 class="text-lg font-semibold text-white">Credit Reports</h3>
            </div>
            <div class="p-4">
                <ul class="space-y-2 mt-2">
                    <li>
                        <a href="credit_summary_report.php" class="flex items-center text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-md">
                            <i class="fas fa-credit-card w-6 text-center mr-2"></i>
                            <span>Credit Summary Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="outstanding_balances_report.php" class="flex items-center text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-md">
                            <i class="fas fa-exclamation-circle w-6 text-center mr-2"></i>
                            <span>Outstanding Balances Report</span>
                        </a>
                    </li>
                    <li>
                        <a href="credit_settlements_report.php" class="flex items-center text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-md">
                            <i class="fas fa-money-bill-wave w-6 text-center mr-2"></i>
                            <span>Credit Settlements Report</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Miscellaneous Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-600 p-4">
                <h3 class="text-lg font-semibold text-white">Other Reports</h3>
            </div>
            <div class="p-4">
                <ul class="space-y-2 mt-2">
                    <li>
                        <a href="pump_meter_readings.php" class="flex items-center text-gray-600 hover:text-gray-800 hover:bg-gray-50 p-2 rounded-md">
                            <i class="fas fa-tachometer-alt w-6 text-center mr-2"></i>
                            <span>Pump Meter Readings</span>
                        </a>
                    </li>
                    <li>
                        <a href="supplier_performance.php" class="flex items-center text-gray-600 hover:text-gray-800 hover:bg-gray-50 p-2 rounded-md">
                            <i class="fas fa-building w-6 text-center mr-2"></i>
                            <span>Supplier Performance</span>
                        </a>
                    </li>
                    <li>
                        <a href="system_logs.php" class="flex items-center text-gray-600 hover:text-gray-800 hover:bg-gray-50 p-2 rounded-md">
                            <i class="fas fa-clipboard-list w-6 text-center mr-2"></i>
                            <span>System Activity Logs</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Custom Reports Section -->
    <div class="mt-8">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">Custom Reports</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">
                    Need a specific report that's not listed above? Use our custom report builder to create exactly what you need.
                </p>
                <a href="custom_report_builder.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-tools mr-2"></i>
                    Launch Custom Report Builder
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>