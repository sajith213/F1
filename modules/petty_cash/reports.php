<?php
/**
 * Petty Cash Management - Reports
 * 
 * Generate reports and analytics for petty cash
 */

// Set page title
$page_title = "Petty Cash Reports";
$breadcrumbs = "Home > Finance > Petty Cash > Reports";

// Include header
include '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';
// Include auth functions
require_once '../../includes/auth.php';

// Check permission
if (!has_permission('manage_petty_cash')) {
    // Redirect to dashboard or show error
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">';
    echo '<p>You do not have permission to access this module.</p>';
    echo '</div>';
    include '../../includes/footer.php';
    exit;
}

// Initialize variables
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'day';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Initialize arrays
$summary_data = [];
$category_data = [];
$daily_data = [];

// Only fetch data if database connection is successful
if (isset($conn) && $conn) {
    try {
        // 1. Fetch summary totals
        $summary_query = "SELECT 
                            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
                            COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
                            COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count,
                            COUNT(*) as transaction_count,
                            MIN(transaction_date) as first_date,
                            MAX(transaction_date) as last_date
                         FROM petty_cash
                         WHERE transaction_date BETWEEN ? AND ?
                         AND status = 'approved'";
        
        $stmt = $conn->prepare($summary_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $summary_data = [
                'total_income' => $row['total_income'] ?: 0,
                'total_expense' => $row['total_expense'] ?: 0,
                'income_count' => $row['income_count'] ?: 0,
                'expense_count' => $row['expense_count'] ?: 0,
                'transaction_count' => $row['transaction_count'] ?: 0,
                'first_date' => $row['first_date'],
                'last_date' => $row['last_date'],
                'net_balance' => ($row['total_income'] ?: 0) - ($row['total_expense'] ?: 0)
            ];
        }
        
        $stmt->close();
        
        // 2. Fetch data by category
        $category_query = "SELECT pc.type, pcc.category_name, 
                             SUM(pc.amount) as total_amount,
                             COUNT(*) as transaction_count
                           FROM petty_cash pc
                           JOIN petty_cash_categories pcc ON pc.category_id = pcc.category_id
                           WHERE pc.transaction_date BETWEEN ? AND ?
                           AND pc.status = 'approved'
                           GROUP BY pc.type, pcc.category_name
                           ORDER BY pc.type, total_amount DESC";
        
        $stmt = $conn->prepare($category_query);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $income_categories = [];
        $expense_categories = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['type'] === 'income') {
                    $income_categories[] = $row;
                } else {
                    $expense_categories[] = $row;
                }
            }
        }
        
        $category_data = [
            'income' => $income_categories,
            'expense' => $expense_categories
        ];
        
        $stmt->close();
        
        // 3. Fetch data for time-series chart (by day, week, or month)
        if ($group_by === 'day') {
            $date_format = '%Y-%m-%d';
            $date_label = 'Day';
        } elseif ($group_by === 'week') {
            $date_format = '%x-W%v'; // Year-Week format
            $date_label = 'Week';
        } elseif ($group_by === 'month') {
            $date_format = '%Y-%m';
            $date_label = 'Month';
        }
        
        $time_query = "SELECT 
                         DATE_FORMAT(transaction_date, ?) as date_group,
                         SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                         SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
                         SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as net
                       FROM petty_cash 
                       WHERE transaction_date BETWEEN ? AND ?
                       AND status = 'approved'
                       GROUP BY date_group
                       ORDER BY MIN(transaction_date)";
        
        $stmt = $conn->prepare($time_query);
        $stmt->bind_param("sss", $date_format, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $daily_data[] = $row;
            }
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        // Log error
        error_log("Petty cash report error: " . $e->getMessage());
    }
}

// Convert data to JSON for chart use
$chart_labels = [];
$income_data = [];
$expense_data = [];
$net_data = [];

foreach ($daily_data as $data) {
    $chart_labels[] = $data['date_group'];
    $income_data[] = $data['income'];
    $expense_data[] = $data['expense'];
    $net_data[] = $data['net'];
}

$time_series_data = [
    'labels' => $chart_labels,
    'income' => $income_data,
    'expense' => $expense_data,
    'net' => $net_data
];

$time_series_json = json_encode($time_series_data);

// Category data for pie charts
$income_categories_labels = array_map(function($item) { return $item['category_name']; }, $category_data['income']);
$income_categories_values = array_map(function($item) { return $item['total_amount']; }, $category_data['income']);
$expense_categories_labels = array_map(function($item) { return $item['category_name']; }, $category_data['expense']);
$expense_categories_values = array_map(function($item) { return $item['total_amount']; }, $category_data['expense']);

$income_pie_data = [
    'labels' => $income_categories_labels,
    'values' => $income_categories_values
];

$expense_pie_data = [
    'labels' => $expense_categories_labels,
    'values' => $expense_categories_values
];

$income_pie_json = json_encode($income_pie_data);
$expense_pie_json = json_encode($expense_pie_data);
?>

<!-- Page Content -->
<div class="mb-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4">
        <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0">Petty Cash Reports</h2>
        <div>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
                <i class="fas fa-arrow-left mr-2"></i> Back to Transactions
            </a>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <div>
                <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                <select id="group_by" name="group_by" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="day" <?= $group_by === 'day' ? 'selected' : '' ?>>Day</option>
                    <option value="week" <?= $group_by === 'week' ? 'selected' : '' ?>>Week</option>
                    <option value="month" <?= $group_by === 'month' ? 'selected' : '' ?>>Month</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded w-full">
                    <i class="fas fa-filter mr-2"></i> Generate Report
                </button>
            </div>
        </form>
    </div>
    
    <!-- Report Tabs -->
    <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200">
            <ul class="flex flex-wrap -mb-px" id="reportTabs" role="tablist">
                <li class="mr-2" role="presentation">
                    <button class="inline-block py-4 px-4 text-blue-600 border-b-2 border-blue-600 rounded-t-lg font-medium" 
                            id="summary-tab" data-tabs-target="summary-tab-content" type="button" role="tab" 
                            aria-controls="summary" aria-selected="true">
                        Summary
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button class="inline-block py-4 px-4 text-gray-500 hover:text-gray-600 hover:border-gray-300 border-b-2 border-transparent rounded-t-lg font-medium" 
                            id="trends-tab" data-tabs-target="trends-tab-content" type="button" role="tab" 
                            aria-controls="trends" aria-selected="false">
                        Trends
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button class="inline-block py-4 px-4 text-gray-500 hover:text-gray-600 hover:border-gray-300 border-b-2 border-transparent rounded-t-lg font-medium" 
                            id="categories-tab" data-tabs-target="categories-tab-content" type="button" role="tab" 
                            aria-controls="categories" aria-selected="false">
                        Categories
                    </button>
                </li>
            </ul>
        </div>
        
        <div id="reportTabContent">
            <!-- Summary Tab -->
            <div class="p-6 rounded-lg" id="summary-tab-content" role="tabpanel" aria-labelledby="summary-tab">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Overview</h3>
                    <p class="text-gray-600 mb-2">
                        This report shows the petty cash activity between 
                        <span class="font-medium"><?= date('F j, Y', strtotime($start_date)) ?></span> and 
                        <span class="font-medium"><?= date('F j, Y', strtotime($end_date)) ?></span>.
                    </p>
                </div>
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <!-- Total Income -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-full mr-4">
                                <i class="fas fa-money-bill-wave text-green-500"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Income</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= format_currency($summary_data['total_income']) ?></h3>
                                <p class="text-xs text-gray-500"><?= $summary_data['income_count'] ?> transactions</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Expense -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-red-100 rounded-full mr-4">
                                <i class="fas fa-hand-holding-usd text-red-500"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Expenses</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= format_currency($summary_data['total_expense']) ?></h3>
                                <p class="text-xs text-gray-500"><?= $summary_data['expense_count'] ?> transactions</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Net Balance -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-full mr-4">
                                <i class="fas fa-balance-scale text-blue-500"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Net Balance</p>
                                <h3 class="text-2xl font-bold <?= $summary_data['net_balance'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= format_currency($summary_data['net_balance']) ?>
                                </h3>
                                <p class="text-xs text-gray-500">
                                    <?= $summary_data['net_balance'] >= 0 ? 'Surplus' : 'Deficit' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Transactions -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-full mr-4">
                                <i class="fas fa-exchange-alt text-purple-500"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Transactions</p>
                                <h3 class="text-2xl font-bold text-gray-800"><?= $summary_data['transaction_count'] ?></h3>
                                <p class="text-xs text-gray-500">
                                    <?= $summary_data['first_date'] ? date('M j', strtotime($summary_data['first_date'])) . ' - ' . date('M j', strtotime($summary_data['last_date'])) : 'No transactions' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mini Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Income vs Expense Chart -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <h4 class="text-base font-semibold text-gray-800 mb-4">Income vs Expense</h4>
                        <div class="h-64">
                            <canvas id="incomeExpenseChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Top Categories Chart -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <h4 class="text-base font-semibold text-gray-800 mb-4">Top Expense Categories</h4>
                        <?php if (empty($category_data['expense'])): ?>
                            <div class="flex items-center justify-center h-64 text-gray-500">
                                <p>No expense data available</p>
                            </div>
                        <?php else: ?>
                            <div class="h-64">
                                <canvas id="topExpensesChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Trends Tab -->
            <div class="hidden p-6 rounded-lg" id="trends-tab-content" role="tabpanel" aria-labelledby="trends-tab">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Income and Expense Trends</h3>
                    <p class="text-gray-600">
                        This chart shows the income, expenses, and net balance trend over time, grouped by <?= $group_by ?>.
                    </p>
                </div>
                
                <!-- Trends Chart -->
                <div class="bg-white border rounded-lg shadow-sm p-4 mb-6">
                    <div class="h-80">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
                
                <!-- Trends Data Table -->
                <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <?= $group_by === 'day' ? 'Date' : ($group_by === 'week' ? 'Week' : 'Month') ?>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Income
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Expense
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Net
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($daily_data)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No data available for the selected period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daily_data as $data): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($data['date_group']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                            <?= format_currency($data['income']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                            <?= format_currency($data['expense']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm <?= $data['net'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= format_currency($data['net']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div class="hidden p-6 rounded-lg" id="categories-tab-content" role="tabpanel" aria-labelledby="categories-tab">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Category Breakdown</h3>
                    <p class="text-gray-600">
                        This report shows the distribution of income and expenses by category.
                    </p>
                </div>
                
                <!-- Category Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Income Categories Chart -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <h4 class="text-base font-semibold text-gray-800 mb-4">Income by Category</h4>
                        <?php if (empty($category_data['income'])): ?>
                            <div class="flex items-center justify-center h-64 text-gray-500">
                                <p>No income data available</p>
                            </div>
                        <?php else: ?>
                            <div class="h-64">
                                <canvas id="incomeCategoriesChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Expense Categories Chart -->
                    <div class="bg-white border rounded-lg shadow-sm p-4">
                        <h4 class="text-base font-semibold text-gray-800 mb-4">Expenses by Category</h4>
                        <?php if (empty($category_data['expense'])): ?>
                            <div class="flex items-center justify-center h-64 text-gray-500">
                                <p>No expense data available</p>
                            </div>
                        <?php else: ?>
                            <div class="h-64">
                                <canvas id="expenseCategoriesChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Category Tables -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Income Categories Table -->
                    <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b text-base font-semibold text-gray-800">
                            Income Categories
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Count
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($category_data['income'])): ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No income data available
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($category_data['income'] as $category): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($category['category_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-green-600">
                                                <?= format_currency($category['total_amount']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?= $category['transaction_count'] ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Expense Categories Table -->
                    <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b text-base font-semibold text-gray-800">
                            Expense Categories
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Category
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Count
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($category_data['expense'])): ?>
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No expense data available
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($category_data['expense'] as $category): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($category['category_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-red-600">
                                                <?= format_currency($category['total_amount']) ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?= $category['transaction_count'] ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Charts initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Common Chart Options
    Chart.defaults.font.family = "'Helvetica', 'Arial', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6B7280';
    
    // Income vs Expense Chart (Doughnut)
    const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
    new Chart(incomeExpenseCtx, {
        type: 'doughnut',
        data: {
            labels: ['Income', 'Expense'],
            datasets: [{
                data: [<?= $summary_data['total_income'] ?>, <?= $summary_data['total_expense'] ?>],
                backgroundColor: ['rgba(16, 185, 129, 0.7)', 'rgba(239, 68, 68, 0.7)'],
                borderColor: ['rgba(16, 185, 129, 1)', 'rgba(239, 68, 68, 1)'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${context.label}: <?= get_setting('currency_symbol', '$') ?>${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Top Expenses Chart (Bar)
    <?php if (!empty($category_data['expense'])): ?>
    const topExpensesCtx = document.getElementById('topExpensesChart').getContext('2d');
    
    // Limit to top 5 categories
    const expenseCategories = <?= json_encode(array_slice($expense_categories_labels, 0, 5)) ?>;
    const expenseValues = <?= json_encode(array_slice($expense_categories_values, 0, 5)) ?>;
    
    new Chart(topExpensesCtx, {
        type: 'bar',
        data: {
            labels: expenseCategories,
            datasets: [{
                label: 'Expense',
                data: expenseValues,
                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                borderColor: 'rgba(239, 68, 68, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `<?= get_setting('currency_symbol', '$') ?>${context.raw.toLocaleString()}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?= get_setting('currency_symbol', '$') ?>' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Trends Chart (Line)
    const trendsData = <?= $time_series_json ?>;
    if (trendsData.labels.length > 0) {
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.labels,
                datasets: [
                    {
                        label: 'Income',
                        data: trendsData.income,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.3
                    },
                    {
                        label: 'Expense',
                        data: trendsData.expense,
                        backgroundColor: 'rgba(239, 68, 68, 0.2)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.3
                    },
                    {
                        label: 'Net',
                        data: trendsData.net,
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: <?= get_setting('currency_symbol', '$') ?>${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '<?= get_setting('currency_symbol', '$') ?>' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Income Categories Chart (Pie)
    const incomePieData = <?= $income_pie_json ?>;
    if (incomePieData.labels.length > 0) {
        const incomeCategoriesCtx = document.getElementById('incomeCategoriesChart').getContext('2d');
        new Chart(incomeCategoriesCtx, {
            type: 'pie',
            data: {
                labels: incomePieData.labels,
                datasets: [{
                    data: incomePieData.values,
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(6, 182, 212, 0.7)',
                        'rgba(124, 58, 237, 0.7)',
                        'rgba(52, 211, 153, 0.7)',
                        'rgba(37, 99, 235, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: <?= get_setting('currency_symbol', '$') ?>${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Expense Categories Chart (Pie)
    const expensePieData = <?= $expense_pie_json ?>;
    if (expensePieData.labels.length > 0) {
        const expenseCategoriesCtx = document.getElementById('expenseCategoriesChart').getContext('2d');
        new Chart(expenseCategoriesCtx, {
            type: 'pie',
            data: {
                labels: expensePieData.labels,
                datasets: [{
                    data: expensePieData.values,
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(249, 115, 22, 0.7)',
                        'rgba(217, 70, 239, 0.7)',
                        'rgba(236, 72, 153, 0.7)',
                        'rgba(220, 38, 38, 0.7)',
                        'rgba(251, 146, 60, 0.7)',
                        'rgba(124, 45, 18, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: <?= get_setting('currency_symbol', '$') ?>${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Tab functionality
    const tabs = document.querySelectorAll('[data-tabs-target]');
    const tabContents = document.querySelectorAll('[role="tabpanel"]');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = document.getElementById(tab.dataset.tabsTarget);
            
            tabContents.forEach(tabContent => {
                tabContent.classList.add('hidden');
            });
            
            tabs.forEach(t => {
                t.classList.remove('text-blue-600', 'border-blue-600');
                t.classList.add('text-gray-500', 'border-transparent');
                t.setAttribute('aria-selected', false);
            });
            
            tab.classList.remove('text-gray-500', 'border-transparent');
            tab.classList.add('text-blue-600', 'border-blue-600');
            tab.setAttribute('aria-selected', true);
            
            target.classList.remove('hidden');
        });
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>