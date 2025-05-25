<?php
/**
 * Financial Reports
 * 
 * This file generates various financial reports for the petrol pump management system.
 */

ob_start();

// Set page title
$page_title = "Financial Reports";

// Include necessary files
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user has permission
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>You do not have permission to access this page.</p>
          </div>';
    include '../includes/footer.php';
    exit;
}

// Get report type from URL parameter
$report_type = isset($_GET['type']) ? $_GET['type'] : 'revenue';

// Get date range from URL parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get grouping option
$group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'daily';

// Handle form submission for filtering
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'revenue';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $group_by = $_POST['group_by'] ?? 'daily';
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'type' => $report_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'group_by' => $group_by
    ]);
    
    header("Location: financial_reports.php?$query");
    exit;
}

// Function to get daily revenue data
function getRevenueData($conn, $start_date, $end_date, $group_by) {
    $date_format = '%Y-%m-%d';
    $group_field = 'DATE(s.sale_date)';
    
    if ($group_by === 'monthly') {
        $date_format = '%Y-%m';
        $group_field = "DATE_FORMAT(s.sale_date, '%Y-%m')";
    } elseif ($group_by === 'yearly') {
        $date_format = '%Y';
        $group_field = "YEAR(s.sale_date)";
    } elseif ($group_by === 'weekly') {
        $date_format = '%x-%v'; // ISO year and week
        $group_field = "DATE_FORMAT(s.sale_date, '%x-%v')";
    }
    
    $sql = "SELECT 
                $group_field as period,
                DATE_FORMAT(MIN(s.sale_date), '$date_format') as date_period,
                COUNT(s.sale_id) as total_transactions,
                SUM(s.total_amount) as gross_amount,
                SUM(s.discount_amount) as discount_amount,
                SUM(s.tax_amount) as tax_amount,
                SUM(s.net_amount) as net_amount,
                SUM(CASE WHEN s.sale_type = 'fuel' THEN s.net_amount ELSE 0 END) as fuel_sales,
                SUM(CASE WHEN s.sale_type = 'product' THEN s.net_amount ELSE 0 END) as product_sales,
                SUM(CASE WHEN s.sale_type = 'mixed' THEN s.net_amount ELSE 0 END) as mixed_sales,
                SUM(CASE WHEN s.payment_method = 'cash' THEN s.net_amount ELSE 0 END) as cash_sales,
                SUM(CASE WHEN s.payment_method = 'credit_card' THEN s.net_amount ELSE 0 END) as card_sales,
                SUM(CASE WHEN s.payment_method = 'mobile_payment' THEN s.net_amount ELSE 0 END) as mobile_sales,
                SUM(CASE WHEN s.payment_method = 'credit' THEN s.net_amount ELSE 0 END) as credit_sales,
                SUM(CASE WHEN s.payment_method = 'other' THEN s.net_amount ELSE 0 END) as other_sales
            FROM 
                sales s
            WHERE 
                DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY 
                $group_field
            ORDER BY 
                period ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get profit margin data
function getProfitMarginData($conn, $start_date, $end_date, $group_by) {
    $date_format = '%Y-%m-%d';
    $group_field = 'DATE(s.sale_date)';
    
    if ($group_by === 'monthly') {
        $date_format = '%Y-%m';
        $group_field = "DATE_FORMAT(s.sale_date, '%Y-%m')";
    } elseif ($group_by === 'yearly') {
        $date_format = '%Y';
        $group_field = "YEAR(s.sale_date)";
    } elseif ($group_by === 'weekly') {
        $date_format = '%x-%v'; // ISO year and week
        $group_field = "DATE_FORMAT(s.sale_date, '%x-%v')";
    }
    
    $sql = "SELECT 
                $group_field as period,
                DATE_FORMAT(MIN(s.sale_date), '$date_format') as date_period,
                SUM(si.quantity * fp.selling_price) as sales_revenue,
                SUM(si.quantity * fp.purchase_price) as cost_price,
                SUM(si.quantity * (fp.selling_price - fp.purchase_price)) as profit_margin,
                (SUM(si.quantity * (fp.selling_price - fp.purchase_price)) / SUM(si.quantity * fp.selling_price)) * 100 as profit_percentage,
                ft.fuel_type_id,
                ft.fuel_name
            FROM 
                sales s
                JOIN sale_items si ON s.sale_id = si.sale_id
                JOIN pump_nozzles pn ON si.nozzle_id = pn.nozzle_id
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
                JOIN fuel_prices fp ON fp.fuel_type_id = ft.fuel_type_id
                    AND fp.effective_date <= DATE(s.sale_date)
                    AND (fp.status = 'active' OR fp.status = 'expired')
            WHERE 
                si.item_type = 'fuel'
                AND DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY 
                $group_field, ft.fuel_type_id
            ORDER BY 
                period ASC, ft.fuel_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get payments summary
function getPaymentsSummary($conn, $start_date, $end_date) {
    $sql = "SELECT 
                s.payment_method,
                COUNT(s.sale_id) as transaction_count,
                SUM(s.net_amount) as total_amount,
                AVG(s.net_amount) as average_amount,
                MIN(s.net_amount) as min_amount,
                MAX(s.net_amount) as max_amount
            FROM 
                sales s
            WHERE 
                DATE(s.sale_date) BETWEEN ? AND ?
            GROUP BY 
                s.payment_method
            ORDER BY 
                total_amount DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get cash reconciliation data
function getCashReconciliationData($conn, $start_date, $end_date, $group_by) {
    $date_format = '%Y-%m-%d';
    $group_field = 'dcr.record_date';
    
    if ($group_by === 'monthly') {
        $date_format = '%Y-%m';
        $group_field = "DATE_FORMAT(dcr.record_date, '%Y-%m')";
    } elseif ($group_by === 'yearly') {
        $date_format = '%Y';
        $group_field = "YEAR(dcr.record_date)";
    } elseif ($group_by === 'weekly') {
        $date_format = '%x-%v'; // ISO year and week
        $group_field = "DATE_FORMAT(dcr.record_date, '%x-%v')";
    }
    
    $sql = "SELECT 
                $group_field as period,
                DATE_FORMAT(MIN(dcr.record_date), '$date_format') as date_period,
                SUM(dcr.expected_amount) as total_expected,
                SUM(dcr.collected_amount) as total_collected,
                SUM(dcr.difference) as total_difference,
                COUNT(CASE WHEN dcr.difference_type = 'excess' THEN 1 END) as excess_count,
                COUNT(CASE WHEN dcr.difference_type = 'shortage' THEN 1 END) as shortage_count,
                COUNT(CASE WHEN dcr.difference_type = 'balanced' THEN 1 END) as balanced_count,
                SUM(CASE WHEN dcr.difference_type = 'excess' THEN dcr.difference ELSE 0 END) as total_excess,
                SUM(CASE WHEN dcr.difference_type = 'shortage' THEN ABS(dcr.difference) ELSE 0 END) as total_shortage
            FROM 
                daily_cash_records dcr
            WHERE 
                dcr.record_date BETWEEN ? AND ?
            GROUP BY 
                $group_field
            ORDER BY 
                period ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Fetch data based on report type
$report_data = [];
$report_title = "";

switch ($report_type) {
    case 'profit':
        $report_data = getProfitMarginData($conn, $start_date, $end_date, $group_by);
        $report_title = "Profit Margin Analysis";
        break;
        
    case 'payments':
        $report_data = getPaymentsSummary($conn, $start_date, $end_date);
        $report_title = "Payment Methods Summary";
        break;
        
    case 'reconciliation':
        $report_data = getCashReconciliationData($conn, $start_date, $end_date, $group_by);
        $report_title = "Cash Reconciliation Summary";
        break;
        
    case 'revenue':
    default:
        $report_data = getRevenueData($conn, $start_date, $end_date, $group_by);
        $report_title = "Revenue Analysis";
        break;
}

// Calculate totals for summaries
$summary_totals = [];

if ($report_type === 'revenue') {
    $summary_totals = [
        'total_transactions' => array_sum(array_column($report_data, 'total_transactions')),
        'gross_amount' => array_sum(array_column($report_data, 'gross_amount')),
        'discount_amount' => array_sum(array_column($report_data, 'discount_amount')),
        'tax_amount' => array_sum(array_column($report_data, 'tax_amount')),
        'net_amount' => array_sum(array_column($report_data, 'net_amount')),
        'fuel_sales' => array_sum(array_column($report_data, 'fuel_sales')),
        'product_sales' => array_sum(array_column($report_data, 'product_sales')),
        'mixed_sales' => array_sum(array_column($report_data, 'mixed_sales')),
        'cash_sales' => array_sum(array_column($report_data, 'cash_sales')),
        'card_sales' => array_sum(array_column($report_data, 'card_sales')),
        'mobile_sales' => array_sum(array_column($report_data, 'mobile_sales')),
        'credit_sales' => array_sum(array_column($report_data, 'credit_sales')),
        'other_sales' => array_sum(array_column($report_data, 'other_sales'))
    ];
} elseif ($report_type === 'profit') {
    // Group by fuel type for profit margin summary
    $fuel_profits = [];
    foreach ($report_data as $row) {
        $fuel_id = $row['fuel_type_id'];
        if (!isset($fuel_profits[$fuel_id])) {
            $fuel_profits[$fuel_id] = [
                'fuel_name' => $row['fuel_name'],
                'sales_revenue' => 0,
                'cost_price' => 0,
                'profit_margin' => 0
            ];
        }
        $fuel_profits[$fuel_id]['sales_revenue'] += $row['sales_revenue'];
        $fuel_profits[$fuel_id]['cost_price'] += $row['cost_price'];
        $fuel_profits[$fuel_id]['profit_margin'] += $row['profit_margin'];
    }
    
    $summary_totals = [
        'sales_revenue' => array_sum(array_column($report_data, 'sales_revenue')),
        'cost_price' => array_sum(array_column($report_data, 'cost_price')),
        'profit_margin' => array_sum(array_column($report_data, 'profit_margin')),
        'profit_percentage' => $summary_totals['sales_revenue'] > 0 ? 
                               ($summary_totals['profit_margin'] / $summary_totals['sales_revenue']) * 100 : 0,
        'fuel_profits' => $fuel_profits
    ];
} elseif ($report_type === 'reconciliation') {
    $summary_totals = [
        'total_expected' => array_sum(array_column($report_data, 'total_expected')),
        'total_collected' => array_sum(array_column($report_data, 'total_collected')),
        'total_difference' => array_sum(array_column($report_data, 'total_difference')),
        'excess_count' => array_sum(array_column($report_data, 'excess_count')),
        'shortage_count' => array_sum(array_column($report_data, 'shortage_count')),
        'balanced_count' => array_sum(array_column($report_data, 'balanced_count')),
        'total_excess' => array_sum(array_column($report_data, 'total_excess')),
        'total_shortage' => array_sum(array_column($report_data, 'total_shortage'))
    ];
}

?>

<!-- Filter form -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="POST" action="" class="w-full">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select id="report_type" name="report_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="revenue" <?= $report_type === 'revenue' ? 'selected' : '' ?>>Revenue Analysis</option>
                    <option value="profit" <?= $report_type === 'profit' ? 'selected' : '' ?>>Profit Margin</option>
                    <option value="payments" <?= $report_type === 'payments' ? 'selected' : '' ?>>Payment Methods</option>
                    <option value="reconciliation" <?= $report_type === 'reconciliation' ? 'selected' : '' ?>>Cash Reconciliation</option>
                </select>
            </div>
            
            <div>
                <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                <select id="group_by" name="group_by" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" <?= $report_type === 'payments' ? 'disabled' : '' ?>>
                    <option value="daily" <?= $group_by === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $group_by === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $group_by === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    <option value="yearly" <?= $group_by === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                </select>
            </div>
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php if ($report_type === 'revenue'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Transactions</div>
            <div class="text-2xl font-bold text-gray-800"><?= number_format($summary_totals['total_transactions']) ?></div>
            <div class="mt-2 text-xs text-gray-500">For the selected period</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Gross Revenue</div>
            <div class="text-2xl font-bold text-blue-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['gross_amount'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Before discounts and taxes</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Net Revenue</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['net_amount'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Final sales amount</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Avg. Transaction</div>
            <div class="text-2xl font-bold text-indigo-600">
                <?= $summary_totals['total_transactions'] > 0 ? 
                    CURRENCY_SYMBOL . number_format($summary_totals['net_amount'] / $summary_totals['total_transactions'], 2) : 
                    CURRENCY_SYMBOL . '0.00' ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Per transaction</div>
        </div>
        
    <?php elseif ($report_type === 'profit'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Sales Revenue</div>
            <div class="text-2xl font-bold text-blue-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['sales_revenue'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Fuel sales only</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Cost Price</div>
            <div class="text-2xl font-bold text-red-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['cost_price'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Cost of fuel sold</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Profit Margin</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['profit_margin'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Total profit on fuel</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Avg. Profit %</div>
            <div class="text-2xl font-bold text-indigo-600">
                <?= $summary_totals['sales_revenue'] > 0 ? 
                    number_format(($summary_totals['profit_margin'] / $summary_totals['sales_revenue']) * 100, 2) . '%' : 
                    '0.00%' ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Profit percentage</div>
        </div>
        
    <?php elseif ($report_type === 'reconciliation'): ?>
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Expected Collection</div>
            <div class="text-2xl font-bold text-blue-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_expected'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Based on meter readings</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Actual Collection</div>
            <div class="text-2xl font-bold text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_collected'], 2) ?></div>
            <div class="mt-2 text-xs text-gray-500">Cash collected</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-sm font-medium text-gray-500 mb-1">Total Difference</div>
            <div class="text-2xl font-bold <?= $summary_totals['total_difference'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                <?= ($summary_totals['total_difference'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . number_format($summary_totals['total_difference'], 2) ?>
            </div>
            <div class="mt-2 text-xs text-gray-500">Excess or shortage</div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <div class="text-xs font-medium text-gray-500 mb-1">Balanced</div>
                    <div class="text-lg font-bold text-blue-600"><?= number_format($summary_totals['balanced_count']) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 mb-1">Excess</div>
                    <div class="text-lg font-bold text-green-600"><?= number_format($summary_totals['excess_count']) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 mb-1">Shortage</div>
                    <div class="text-lg font-bold text-red-600"><?= number_format($summary_totals['shortage_count']) ?></div>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-500">Settlement counts</div>
        </div>
    <?php endif; ?>
</div>

<!-- Report header with export buttons -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0"><?= $report_title ?></h2>
        
        <div class="flex space-x-2">
            <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-print mr-2"></i> Print
            </button>
            <button onclick="exportPDF()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-pdf mr-2"></i> PDF
            </button>
            <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-excel mr-2"></i> Excel
            </button>
        </div>
    </div>
</div>

<!-- Report content - changes based on report type -->
<div class="bg-white rounded-lg shadow-md overflow-hidden" id="report-content">
    <?php if (empty($report_data)): ?>
        <div class="p-4 text-center text-gray-500">
            <i class="fas fa-info-circle text-blue-500 text-2xl mb-2"></i>
            <p>No data found for the selected criteria.</p>
        </div>
    <?php elseif ($report_type === 'revenue'): ?>
        <!-- Revenue Analysis Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Discounts</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tax</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Sales</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Product Sales</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?php
                                    if ($group_by === 'daily') {
                                        echo date('M d, Y', strtotime($row['date_period']));
                                    } elseif ($group_by === 'weekly') {
                                        $parts = explode('-', $row['date_period']);
                                        echo "Week {$parts[1]}, {$parts[0]}";
                                    } elseif ($group_by === 'monthly') {
                                        echo date('M Y', strtotime($row['date_period'] . '-01'));
                                    } else {
                                        echo $row['date_period'];
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['total_transactions']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['gross_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['discount_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['tax_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= CURRENCY_SYMBOL . number_format($row['net_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= CURRENCY_SYMBOL . number_format($row['fuel_sales'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                <?= CURRENCY_SYMBOL . number_format($row['product_sales'] + $row['mixed_sales'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($summary_totals['total_transactions']) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['gross_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['discount_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['tax_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['net_amount'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['fuel_sales'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600">
                            <?= CURRENCY_SYMBOL . number_format($summary_totals['product_sales'] + $summary_totals['mixed_sales'], 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Payment Method Breakdown Chart -->
        <div class="border-t border-gray-200 mt-6 pt-6 px-6 pb-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Method Breakdown</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <?php
                $payment_methods = [
                    'cash_sales' => ['name' => 'Cash', 'color' => 'green'],
                    'card_sales' => ['name' => 'Credit Card', 'color' => 'blue'],
                    'mobile_sales' => ['name' => 'Mobile Payment', 'color' => 'indigo'],
                    'credit_sales' => ['name' => 'Credit', 'color' => 'yellow'],
                    'other_sales' => ['name' => 'Other', 'color' => 'gray']
                ];
                
                foreach ($payment_methods as $key => $method) {
                    $percentage = $summary_totals['net_amount'] > 0 ? 
                                 ($summary_totals[$key] / $summary_totals['net_amount']) * 100 : 0;
                    ?>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-gray-500"><?= $method['name'] ?></div>
                            <div class="text-sm font-bold text-<?= $method['color'] ?>-600">
                                <?= number_format($percentage, 1) ?>%
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-<?= $method['color'] ?>-600 h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                        <div class="mt-2 text-right text-sm font-medium text-gray-900">
                            <?= CURRENCY_SYMBOL . number_format($summary_totals[$key], 2) ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
        
    <?php elseif ($report_type === 'profit'): ?>
        <!-- Profit Margin Analysis Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sales Revenue</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Price</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit Margin</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Profit %</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?php
                                    if ($group_by === 'daily') {
                                        echo date('M d, Y', strtotime($row['date_period']));
                                    } elseif ($group_by === 'weekly') {
                                        $parts = explode('-', $row['date_period']);
                                        echo "Week {$parts[1]}, {$parts[0]}";
                                    } elseif ($group_by === 'monthly') {
                                        echo date('M Y', strtotime($row['date_period'] . '-01'));
                                    } else {
                                        echo $row['date_period'];
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($row['fuel_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['sales_revenue'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['cost_price'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-green-600">
                                <?= CURRENCY_SYMBOL . number_format($row['profit_margin'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                                <?= number_format($row['profit_percentage'], 2) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" colspan="2" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['sales_revenue'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['cost_price'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-green-600"><?= CURRENCY_SYMBOL . number_format($summary_totals['profit_margin'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-blue-600">
                            <?= $summary_totals['sales_revenue'] > 0 ? 
                                number_format(($summary_totals['profit_margin'] / $summary_totals['sales_revenue']) * 100, 2) . '%' : 
                                '0.00%' ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Fuel Type Profit Breakdown Chart -->
        <div class="border-t border-gray-200 mt-6 pt-6 px-6 pb-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Profit Margin by Fuel Type</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($summary_totals['fuel_profits'] as $fuel): 
                    $profit_percentage = $fuel['sales_revenue'] > 0 ? 
                                        ($fuel['profit_margin'] / $fuel['sales_revenue']) * 100 : 0;
                ?>
                    <div class="bg-white rounded-lg p-4 border border-gray-200">
                        <div class="flex justify-between items-center mb-4">
                            <div class="text-lg font-medium text-gray-900"><?= htmlspecialchars($fuel['fuel_name']) ?></div>
                            <div class="text-lg font-bold text-green-600">
                                <?= CURRENCY_SYMBOL . number_format($fuel['profit_margin'], 2) ?>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div class="text-gray-500 mb-1">Sales Revenue</div>
                                <div class="font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($fuel['sales_revenue'], 2) ?></div>
                            </div>
                            <div>
                                <div class="text-gray-500 mb-1">Cost Price</div>
                                <div class="font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($fuel['cost_price'], 2) ?></div>
                            </div>
                            <div>
                                <div class="text-gray-500 mb-1">Profit Margin</div>
                                <div class="font-medium text-green-600"><?= CURRENCY_SYMBOL . number_format($fuel['profit_margin'], 2) ?></div>
                            </div>
                            <div>
                                <div class="text-gray-500 mb-1">Profit Percentage</div>
                                <div class="font-medium text-blue-600"><?= number_format($profit_percentage, 2) ?>%</div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="text-xs font-medium text-gray-500 mb-1">Profit Margin</div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-green-600 h-2.5 rounded-full" style="width: <?= min($profit_percentage * 2, 100) ?>%"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    <?php elseif ($report_type === 'payments'): ?>
        <!-- Payment Methods Summary Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Average Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Min Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Max Amount</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Distribution</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $total_amount = array_sum(array_column($report_data, 'total_amount'));
                    $total_transactions = array_sum(array_column($report_data, 'transaction_count'));
                    
                    $payment_method_names = [
                        'cash' => 'Cash',
                        'credit_card' => 'Credit Card',
                        'debit_card' => 'Debit Card',
                        'mobile_payment' => 'Mobile Payment',
                        'credit' => 'Credit (Account)',
                        'other' => 'Other'
                    ];
                    
                    foreach ($report_data as $row): 
                        $percentage = $total_amount > 0 ? ($row['total_amount'] / $total_amount) * 100 : 0;
                        $transaction_percentage = $total_transactions > 0 ? ($row['transaction_count'] / $total_transactions) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?= htmlspecialchars($payment_method_names[$row['payment_method']] ?? ucfirst($row['payment_method'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= number_format($row['transaction_count']) ?>
                                <span class="text-xs text-gray-400">(<?= number_format($transaction_percentage, 1) ?>%)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-gray-900">
                                <?= CURRENCY_SYMBOL . number_format($row['total_amount'], 2) ?>
                                <span class="text-xs text-gray-400">(<?= number_format($percentage, 1) ?>%)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['average_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['min_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['max_amount'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= number_format($total_transactions) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($total_amount, 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">
                            <?= $total_transactions > 0 ? CURRENCY_SYMBOL . number_format($total_amount / $total_transactions, 2) : CURRENCY_SYMBOL . '0.00' ?>
                        </td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">-</td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900">-</td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-900">100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Payment Methods Visualization -->
        <div class="border-t border-gray-200 mt-6 pt-6 px-6 pb-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Method Distribution</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Transaction Count Chart -->
                <div class="bg-white rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-medium text-gray-500 mb-4">Transaction Count Distribution</h4>
                    
                    <?php foreach ($report_data as $row): 
                        $transaction_percentage = $total_transactions > 0 ? ($row['transaction_count'] / $total_transactions) * 100 : 0;
                    ?>
                        <div class="mb-3">
                            <div class="flex justify-between items-center mb-1">
                                <div class="text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($payment_method_names[$row['payment_method']] ?? ucfirst($row['payment_method'])) ?>
                                </div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?= number_format($row['transaction_count']) ?> 
                                    <span class="text-xs text-gray-500">(<?= number_format($transaction_percentage, 1) ?>%)</span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full" style="width: <?= $transaction_percentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Amount Distribution Chart -->
                <div class="bg-white rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-medium text-gray-500 mb-4">Amount Distribution</h4>
                    
                    <?php foreach ($report_data as $row): 
                        $amount_percentage = $total_amount > 0 ? ($row['total_amount'] / $total_amount) * 100 : 0;
                    ?>
                        <div class="mb-3">
                            <div class="flex justify-between items-center mb-1">
                                <div class="text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($payment_method_names[$row['payment_method']] ?? ucfirst($row['payment_method'])) ?>
                                </div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?= CURRENCY_SYMBOL . number_format($row['total_amount'], 2) ?> 
                                    <span class="text-xs text-gray-500">(<?= number_format($amount_percentage, 1) ?>%)</span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: <?= $amount_percentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
    <?php elseif ($report_type === 'reconciliation'): ?>
        <!-- Cash Reconciliation Summary Report -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Collected Amount</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Difference</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Excess</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Shortage</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Balanced</th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Accuracy</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($report_data as $row): 
                        $total_records = $row['excess_count'] + $row['shortage_count'] + $row['balanced_count'];
                        $accuracy = $total_records > 0 ? ($row['balanced_count'] / $total_records) * 100 : 0;
                    ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?php
                                    if ($group_by === 'daily') {
                                        echo date('M d, Y', strtotime($row['date_period']));
                                    } elseif ($group_by === 'weekly') {
                                        $parts = explode('-', $row['date_period']);
                                        echo "Week {$parts[1]}, {$parts[0]}";
                                    } elseif ($group_by === 'monthly') {
                                        echo date('M Y', strtotime($row['date_period'] . '-01'));
                                    } else {
                                        echo $row['date_period'];
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['total_expected'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                <?= CURRENCY_SYMBOL . number_format($row['total_collected'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium <?= $row['total_difference'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= ($row['total_difference'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . number_format($row['total_difference'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <?= number_format($row['excess_count']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    <?= number_format($row['shortage_count']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= number_format($row['balanced_count']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="<?= $accuracy >= 90 ? 'bg-green-600' : ($accuracy >= 75 ? 'bg-yellow-500' : 'bg-red-600') ?> h-2.5 rounded-full" style="width: <?= $accuracy ?>%"></div>
                                </div>
                                <div class="text-xs text-center mt-1"><?= number_format($accuracy, 2) ?>%</div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <th scope="row" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_expected'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium text-gray-900"><?= CURRENCY_SYMBOL . number_format($summary_totals['total_collected'], 2) ?></td>
                        <td class="px-6 py-3 text-right text-xs font-medium <?= $summary_totals['total_difference'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= ($summary_totals['total_difference'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . number_format($summary_totals['total_difference'], 2) ?>
                        </td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-green-600"><?= number_format($summary_totals['excess_count']) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-red-600"><?= number_format($summary_totals['shortage_count']) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-blue-600"><?= number_format($summary_totals['balanced_count']) ?></td>
                        <td class="px-6 py-3 text-center text-xs font-medium text-gray-900">
                            <?php
                            $total_all_records = $summary_totals['excess_count'] + $summary_totals['shortage_count'] + $summary_totals['balanced_count'];
                            $overall_accuracy = $total_all_records > 0 ? ($summary_totals['balanced_count'] / $total_all_records) * 100 : 0;
                            echo number_format($overall_accuracy, 2) . '%';
                            ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Cash Settlement Analysis -->
        <div class="border-t border-gray-200 mt-6 pt-6 px-6 pb-4">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Cash Settlement Analysis</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Settlement Status Chart -->
                <div class="bg-white rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-medium text-gray-500 mb-4">Settlement Status Distribution</h4>
                    
                    <?php
                    $total_settlements = $summary_totals['excess_count'] + $summary_totals['shortage_count'] + $summary_totals['balanced_count'];
                    
                    $statuses = [
                        ['name' => 'Balanced', 'count' => $summary_totals['balanced_count'], 'color' => 'blue'],
                        ['name' => 'Excess', 'count' => $summary_totals['excess_count'], 'color' => 'green'],
                        ['name' => 'Shortage', 'count' => $summary_totals['shortage_count'], 'color' => 'red']
                    ];
                    
                    foreach ($statuses as $status): 
                        $percentage = $total_settlements > 0 ? ($status['count'] / $total_settlements) * 100 : 0;
                    ?>
                        <div class="mb-3">
                            <div class="flex justify-between items-center mb-1">
                                <div class="text-sm font-medium text-gray-700">
                                    <?= htmlspecialchars($status['name']) ?>
                                </div>
                                <div class="text-sm font-medium text-gray-900">
                                    <?= number_format($status['count']) ?> 
                                    <span class="text-xs text-gray-500">(<?= number_format($percentage, 1) ?>%)</span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-<?= $status['color'] ?>-600 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Amount Distribution Chart -->
                <div class="bg-white rounded-lg p-4 border border-gray-200">
                    <h4 class="text-sm font-medium text-gray-500 mb-4">Variance Analysis</h4>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Excess Amount -->
                        <div class="bg-green-50 rounded-lg p-4">
                            <div class="text-sm text-green-700 mb-1">Total Excess</div>
                            <div class="text-xl font-bold text-green-700">
                                <?= CURRENCY_SYMBOL . number_format($summary_totals['total_excess'], 2) ?>
                            </div>
                            <div class="text-xs text-green-600 mt-1">
                                <?= number_format($summary_totals['excess_count']) ?> occurrences
                            </div>
                            <div class="text-xs text-green-600">
                                Avg: 
                                <?= $summary_totals['excess_count'] > 0 ? 
                                    CURRENCY_SYMBOL . number_format($summary_totals['total_excess'] / $summary_totals['excess_count'], 2) : 
                                    CURRENCY_SYMBOL . '0.00' ?>
                            </div>
                        </div>
                        
                        <!-- Shortage Amount -->
                        <div class="bg-red-50 rounded-lg p-4">
                            <div class="text-sm text-red-700 mb-1">Total Shortage</div>
                            <div class="text-xl font-bold text-red-700">
                                <?= CURRENCY_SYMBOL . number_format($summary_totals['total_shortage'], 2) ?>
                            </div>
                            <div class="text-xs text-red-600 mt-1">
                                <?= number_format($summary_totals['shortage_count']) ?> occurrences
                            </div>
                            <div class="text-xs text-red-600">
                                Avg: 
                                <?= $summary_totals['shortage_count'] > 0 ? 
                                    CURRENCY_SYMBOL . number_format($summary_totals['total_shortage'] / $summary_totals['shortage_count'], 2) : 
                                    CURRENCY_SYMBOL . '0.00' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="text-sm font-medium text-gray-700 mb-2">Net Difference</div>
                        <div class="text-2xl font-bold <?= $summary_totals['total_difference'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= ($summary_totals['total_difference'] >= 0 ? '+' : '') . CURRENCY_SYMBOL . number_format($summary_totals['total_difference'], 2) ?>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <?= number_format(($summary_totals['total_difference'] / $summary_totals['total_expected']) * 100, 4) ?>% of expected amount
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for export functionality -->
<script>
    // Toggle group_by select based on report type
    document.getElementById('report_type').addEventListener('change', function() {
        const groupBySelect = document.getElementById('group_by');
        if (this.value === 'payments') {
            groupBySelect.disabled = true;
        } else {
            groupBySelect.disabled = false;
        }
    });
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF
    function exportPDF() {
        alert('PDF export functionality will be implemented here');
        // Implementation would require a PDF library like TCPDF or FPDF
    }
    
    // Export to Excel
    function exportExcel() {
        alert('Excel export functionality will be implemented here');
        // Implementation would require PhpSpreadsheet or similar library
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';

ob_end_flush();
?>