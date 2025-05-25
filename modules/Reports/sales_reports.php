<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Sales Report
 * 
 * Displays sales data with filtering options and summary statistics
 */
$page_title = "Sales Report";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <span class="text-gray-700">Sales Report</span>';

// Adjust these paths to match your actual directory structure
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Check if user has permission to view reports
if (!has_permission('view_reports')) {
    set_flash_message('error', 'You do not have permission to view reports');
    header('Location: ../../index.php');
    exit;
}

// Set default date range to current month
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');

// Handle filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $first_day_of_month;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
$fuel_type_id = isset($_GET['fuel_type_id']) ? intval($_GET['fuel_type_id']) : 0;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$sale_type = isset($_GET['sale_type']) ? $_GET['sale_type'] : ''; // New filter for fuel/product

// Sanitize inputs
$start_date = mysqli_real_escape_string($conn, $start_date);
$end_date = mysqli_real_escape_string($conn, $end_date);
$payment_method = mysqli_real_escape_string($conn, $payment_method);
$sale_type = mysqli_real_escape_string($conn, $sale_type);

// Get all fuel types for filter dropdown
$fuel_types = [];
$query = "SELECT fuel_type_id, fuel_name FROM fuel_types ORDER BY fuel_name";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fuel_types[] = $row;
    }
}

// Prepare the SQL query conditions
$conditions = ["s.sale_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'"];
if ($fuel_type_id > 0) {
    $conditions[] = "pn.fuel_type_id = $fuel_type_id";
}
if (!empty($payment_method)) {
    $conditions[] = "s.payment_method = '$payment_method'";
}
// Add sale type filter condition
if ($sale_type === 'fuel') {
    $conditions[] = "si.item_type = 'fuel'";
} elseif ($sale_type === 'product') {
    $conditions[] = "si.item_type = 'product'";
}

// Build the WHERE clause
$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get sales data
$sales_data = [];
$query = "
    SELECT 
        s.sale_id,
        s.invoice_number,
        s.sale_date,
        s.customer_name,
        s.total_amount,
        s.payment_method,
        s.payment_status,
        SUM(si.quantity) as total_quantity,
        ft.fuel_name,
        st.first_name,
        st.last_name
    FROM 
        sales s
    JOIN 
        sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN 
        pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
    LEFT JOIN 
        fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
    LEFT JOIN 
        staff st ON s.staff_id = st.staff_id
    $where_clause
    GROUP BY 
        s.sale_id
    ORDER BY 
        s.sale_date DESC
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Process staff name
        $staff_name = '';
        if (!empty($row['first_name']) || !empty($row['last_name'])) {
            $staff_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        }
        $row['staff_name'] = $staff_name;
        $sales_data[] = $row;
    }
}

// Get summary statistics
$summary = [
    'total_sales' => 0,
    'total_quantity' => 0,
    'total_revenue' => 0,
    'avg_sale_value' => 0
];

// Get total sales count
$query = "SELECT COUNT(*) as count FROM sales s $where_clause";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $summary['total_sales'] = $row['count'];
}

// Get total quantity sold and revenue
$query = "
    SELECT 
        SUM(si.quantity) as total_quantity,
        SUM(si.total_price) as total_revenue
    FROM 
        sales s
    JOIN 
        sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN 
        pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
    $where_clause
";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $summary['total_quantity'] = $row['total_quantity'] ?? 0;
    $summary['total_revenue'] = $row['total_revenue'] ?? 0;
}

// Calculate average sale value
if ($summary['total_sales'] > 0) {
    $summary['avg_sale_value'] = $summary['total_revenue'] / $summary['total_sales'];
}

// Get fuel type breakdown
$fuel_breakdown = [];
$query = "
    SELECT 
        ft.fuel_name,
        SUM(si.quantity) as total_quantity,
        SUM(si.total_price) as total_revenue
    FROM 
        sales s
    JOIN 
        sale_items si ON s.sale_id = si.sale_id
    JOIN 
        pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
    JOIN 
        fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
    $where_clause
    GROUP BY 
        ft.fuel_type_id
    ORDER BY 
        total_revenue DESC
";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fuel_breakdown[] = $row;
    }
}

// Get payment method breakdown
$payment_breakdown = [];
$query = "
    SELECT 
        s.payment_method,
        COUNT(*) as total_sales,
        SUM(s.total_amount) as total_amount
    FROM 
        sales s
    LEFT JOIN 
        sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN 
        pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
    $where_clause
    GROUP BY 
        s.payment_method
";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payment_breakdown[] = $row;
    }
}

// Get sale type breakdown
$sale_type_breakdown = [];
$query = "
    SELECT 
        si.item_type,
        COUNT(*) as total_items,
        SUM(si.quantity) as total_quantity,
        SUM(si.total_price) as total_revenue
    FROM 
        sales s
    JOIN 
        sale_items si ON s.sale_id = si.sale_id
    $where_clause
    GROUP BY 
        si.item_type
    ORDER BY 
        total_revenue DESC
";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sale_type_breakdown[] = $row;
    }
}

// Get currency symbol
$currency_symbol = get_setting('currency_symbol', 'Rs.');
?>

<!-- Main content -->
<div class="container mx-auto pb-6">
    
    <!-- Filter Form -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Filter Sales Report</h3>
        </div>
        <div class="p-6">
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-input rounded-md shadow-sm mt-1 block w-full">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-input rounded-md shadow-sm mt-1 block w-full">
                </div>
                <div>
                    <label for="fuel_type_id" class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                    <select id="fuel_type_id" name="fuel_type_id" class="form-select rounded-md shadow-sm mt-1 block w-full">
                        <option value="0">All Fuel Types</option>
                        <?php foreach ($fuel_types as $type): ?>
                            <option value="<?= $type['fuel_type_id'] ?>" <?= $fuel_type_id == $type['fuel_type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['fuel_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="form-select rounded-md shadow-sm mt-1 block w-full">
                        <option value="">All Payment Methods</option>
                        <option value="cash" <?= $payment_method == 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="credit_card" <?= $payment_method == 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
                        <option value="debit_card" <?= $payment_method == 'debit_card' ? 'selected' : '' ?>>Debit Card</option>
                        <option value="mobile_payment" <?= $payment_method == 'mobile_payment' ? 'selected' : '' ?>>Mobile Payment</option>
                        <option value="credit" <?= $payment_method == 'credit' ? 'selected' : '' ?>>Credit</option>
                        <option value="other" <?= $payment_method == 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label for="sale_type" class="block text-sm font-medium text-gray-700 mb-1">Sale Type</label>
                    <select id="sale_type" name="sale_type" class="form-select rounded-md shadow-sm mt-1 block w-full">
                        <option value="">All Sale Types</option>
                        <option value="fuel" <?= $sale_type == 'fuel' ? 'selected' : '' ?>>Fuel Only</option>
                        <option value="product" <?= $sale_type == 'product' ? 'selected' : '' ?>>Products Only</option>
                    </select>
                </div>
                <div class="flex items-end md:col-span-2 lg:col-span-5">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg mr-2">
                        <i class="fas fa-filter mr-1"></i> Filter Results
                    </button>
                    <a href="sales_reports.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">
                        Reset Filters
                    </a>
                    <button type="button" onclick="window.print();" class="ml-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">
                        <i class="fas fa-print mr-1"></i> Print Report
                    </button>
                    <a href="export_sales_report.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&fuel_type_id=<?= $fuel_type_id ?>&payment_method=<?= urlencode($payment_method) ?>&sale_type=<?= urlencode($sale_type) ?>" class="ml-2 bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg">
                        <i class="fas fa-file-export mr-1"></i> Export
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Sales -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-blue-500 to-blue-600">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">Total Sales</p>
                        <h2 class="text-3xl font-bold mt-1"><?= number_format($summary['total_sales']) ?></h2>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-shopping-cart text-2xl text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Quantity -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-green-500 to-green-600">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">Total Quantity</p>
                        <h2 class="text-3xl font-bold mt-1"><?= number_format($summary['total_quantity'], 2) ?> L</h2>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-gas-pump text-2xl text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Total Revenue -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-purple-500 to-purple-600">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">Total Revenue</p>
                        <h2 class="text-3xl font-bold mt-1"><?= $currency_symbol ?> <?= number_format($summary['total_revenue'], 2) ?></h2>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-money-bill-wave text-2xl text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Average Sale -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-gradient-to-r from-yellow-800 to-yellow-900">
                <div class="flex items-center justify-between">
                    <div class="text-white">
                        <p class="text-sm font-medium uppercase tracking-wider">Average Sale</p>
                        <h2 class="text-3xl font-bold mt-1"><?= $currency_symbol ?> <?= number_format($summary['avg_sale_value'], 2) ?></h2>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                        <i class="fas fa-chart-line text-2xl text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sales Breakdown Charts -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Fuel Type Breakdown -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Sales by Fuel Type</h3>
            </div>
            <div class="p-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity (L)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($fuel_breakdown)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($fuel_breakdown as $item): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($item['fuel_name'] ?? 'Unknown') ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($item['total_quantity'], 2) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= $currency_symbol ?> <?= number_format($item['total_revenue'], 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Payment Method Breakdown -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Sales by Payment Method</h3>
            </div>
            <div class="p-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Count</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($payment_breakdown)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($payment_breakdown as $item): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= ucfirst(str_replace('_', ' ', $item['payment_method'] ?? 'Unknown')) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($item['total_sales']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= $currency_symbol ?> <?= number_format($item['total_amount'], 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Sale Type Breakdown -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Sales by Type</h3>
            </div>
            <div class="p-6">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Count</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($sale_type_breakdown)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-sm text-center text-gray-500">No data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($sale_type_breakdown as $item): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= ucfirst($item['item_type'] ?? 'Unknown') ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($item['total_items']) ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                    <?= $currency_symbol ?> <?= number_format($item['total_revenue'], 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Sales Data Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Sales Transactions</h3>
            <span class="text-sm text-gray-600">
                Showing <?= count($sales_data) ?> transactions from <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity (L)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Staff</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($sales_data)): ?>
                    <tr>
                        <td colspan="9" class="px-6 py-4 text-sm text-center text-gray-500">No sales data found for the selected criteria</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($sales_data as $sale): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="../../modules/pos/view_sale.php?id=<?= $sale['sale_id'] ?>" class="text-blue-600 hover:text-blue-900 font-medium">
                                    <?= htmlspecialchars($sale['invoice_number'] ?? 'N/A') ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y g:i A', strtotime($sale['sale_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?= !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : '<span class="text-gray-400">Walk-in Customer</span>' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?= htmlspecialchars($sale['fuel_name'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?= number_format($sale['total_quantity'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?= $currency_symbol ?> <?= number_format($sale['total_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php
                                    $payment_status_color = 'gray';
                                    switch ($sale['payment_status']) {
                                        case 'paid':
                                            $payment_status_color = 'green';
                                            break;
                                        case 'pending':
                                            $payment_status_color = 'yellow';
                                            break;
                                        case 'partial':
                                            $payment_status_color = 'orange';
                                            break;
                                        case 'cancelled':
                                            $payment_status_color = 'red';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?= $payment_status_color ?>-100 text-<?= $payment_status_color ?>-800">
                                        <?= ucfirst($sale['payment_status'] ?? 'Unknown') ?>
                                    </span>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?= ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'Unknown')) ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($sale['staff_name'] ?? 'N/A') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="../../modules/pos/view_sale.php?id=<?= $sale['sale_id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (has_permission('manage_pos') && $sale['payment_status'] !== 'cancelled'): ?>
                                <a href="../../modules/pos/print_receipt.php?id=<?= $sale['sale_id'] ?>" class="ml-3 text-green-600 hover:text-green-900">
                                    <i class="fas fa-print"></i> Receipt
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    Total: <span class="font-semibold"><?= count($sales_data) ?></span> transactions
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print styles - only applied when printing -->
<style type="text/css" media="print">
    /* Hide elements not needed in print */
    header, footer, nav, .no-print {
        display: none !important;
    }
    
    /* Adjust page margins and font sizes */
    body {
        font-size: 12pt;
        margin: 0;
        padding: 0;
    }
    
    /* Ensure tables break across pages properly */
    table {
        break-inside: auto;
    }
    
    tr {
        break-inside: avoid;
    }
    
    /* Add page title for print */
    @page {
        margin: 2cm;
    }
    
    /* Print specific header */
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
    }
    
    .print-header h1 {
        font-size: 18pt;
        margin: 0;
    }
    
    .print-header p {
        font-size: 12pt;
        margin: 5px 0;
    }
</style>

<!-- Header visible only when printing -->
<div class="print-header" style="display: none;">
    <h1><?= htmlspecialchars(get_setting('company_name', 'Fuel Manager') ?? '') ?></h1>
    <p>Sales Report</p>
    <p>Period: <?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?></p>
    <p>Generated on: <?= date('M d, Y g:i A') ?></p>
</div>

<?php include_once '../../includes/footer.php'; ?>