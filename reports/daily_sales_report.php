<?php
/**
 * Daily Sales & Lost Profit Report
 * 
 * A simplified report to track daily fuel sales and identify profit loss due to price fluctuations.
 */
ob_start();
// Set page title
$page_title = "Daily Sales & Lost Profit Report";

// Include necessary files
require_once '../includes/header.php';
require_once '../includes/db.php';

// Define currency symbol function to ensure consistency
function getCurrencySymbol() {
    return 'Rs.';
}

// Check if user has permission
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>You do not have permission to access this page.</p>
          </div>';
    include '../includes/footer.php';
    exit;
}

// Set default values
$period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'daily';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$fuel_type_id = isset($_GET['fuel_type_id']) ? $_GET['fuel_type_id'] : null;
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;
$pump_id = isset($_GET['pump_id']) ? $_GET['pump_id'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $period_type = $_POST['period_type'] ?? 'daily';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $fuel_type_id = !empty($_POST['fuel_type_id']) ? $_POST['fuel_type_id'] : null;
    $staff_id = !empty($_POST['staff_id']) ? $_POST['staff_id'] : null;
    $pump_id = !empty($_POST['pump_id']) ? $_POST['pump_id'] : null;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'period_type' => $period_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'fuel_type_id' => $fuel_type_id,
        'staff_id' => $staff_id,
        'pump_id' => $pump_id
    ]);
    
    header("Location: daily_sales_report.php?$query");
    exit;
}

function getSystemSettings($conn) {
    $settings = [];
    $sql = "SELECT setting_name, setting_value FROM system_settings";
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    }
    
    return $settings;
}

// Get settings
$system_settings = getSystemSettings($conn);

// Try to redefine CURRENCY_SYMBOL if it already exists
if (defined('CURRENCY_SYMBOL')) {
    // We can't redefine a constant without using runkit, so we'll use a global variable as fallback
    $GLOBALS['CURRENCY_SYMBOL'] = 'Rs.';
} else {
    // Define it with our preferred value
    define('CURRENCY_SYMBOL', 'Rs.');
    $GLOBALS['CURRENCY_SYMBOL'] = 'Rs.';
}

// Fetch fuel types for the dropdown
function getFuelTypes($conn) {
    $sql = "SELECT 
                fuel_type_id,
                fuel_name
            FROM 
                fuel_types
            ORDER BY 
                fuel_name";
    
    $result = $conn->query($sql);
    
    $fuelTypes = [];
    while ($row = $result->fetch_assoc()) {
        $fuelTypes[] = $row;
    }
    
    return $fuelTypes;
}

// Fetch staff for dropdown
function getAllStaff($conn) {
    $sql = "SELECT 
                staff_id,
                CONCAT(first_name, ' ', last_name) AS staff_name,
                staff_code
            FROM 
                staff
            WHERE 
                status = 'active'
            ORDER BY 
                last_name, first_name";
    
    $result = $conn->query($sql);
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    return $staff;
}

// Fetch pumps for dropdown
function getAllPumps($conn) {
    $sql = "SELECT 
                p.pump_id,
                p.pump_name,
                ft.fuel_name
            FROM 
                pumps p
                JOIN tanks t ON p.tank_id = t.tank_id
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
            WHERE 
                p.status = 'active'
            ORDER BY 
                p.pump_name";
    
    $result = $conn->query($sql);
    
    $pumps = [];
    while ($row = $result->fetch_assoc()) {
        $pumps[] = $row;
    }
    
    return $pumps;
}

// Get current and previous prices for each fuel type
function getFuelPrices($conn) {
    $sql = "SELECT 
                ft.fuel_type_id,
                ft.fuel_name,
                current_prices.selling_price AS current_price,
                previous_prices.selling_price AS previous_price,
                current_prices.effective_date
            FROM 
                fuel_types ft
            LEFT JOIN (
                SELECT 
                    fp.fuel_type_id, 
                    fp.selling_price, 
                    fp.effective_date,
                    ROW_NUMBER() OVER (PARTITION BY fp.fuel_type_id ORDER BY fp.effective_date DESC) as rn
                FROM 
                    fuel_prices fp
                WHERE 
                    fp.status = 'active'
            ) current_prices ON ft.fuel_type_id = current_prices.fuel_type_id AND current_prices.rn = 1
            LEFT JOIN (
                SELECT 
                    fp.fuel_type_id, 
                    fp.selling_price, 
                    fp.effective_date,
                    ROW_NUMBER() OVER (PARTITION BY fp.fuel_type_id ORDER BY fp.effective_date DESC) as rn
                FROM 
                    fuel_prices fp
                WHERE 
                    fp.status = 'active'
            ) previous_prices ON ft.fuel_type_id = previous_prices.fuel_type_id AND previous_prices.rn = 2";
    
    // If your MySQL version doesn't support window functions (ROW_NUMBER), here's an alternative approach:
    // Uncomment this code and comment out the SQL query above if needed
    /*
    $sql = "SELECT 
                ft.fuel_type_id,
                ft.fuel_name,
                (SELECT fp1.selling_price 
                 FROM fuel_prices fp1 
                 WHERE fp1.fuel_type_id = ft.fuel_type_id 
                 AND fp1.status = 'active'
                 ORDER BY fp1.effective_date DESC LIMIT 1) AS current_price,
                (SELECT fp2.selling_price 
                 FROM fuel_prices fp2 
                 WHERE fp2.fuel_type_id = ft.fuel_type_id 
                 AND fp2.status = 'active'
                 ORDER BY fp2.effective_date DESC LIMIT 1, 1) AS previous_price,
                (SELECT fp3.effective_date 
                 FROM fuel_prices fp3 
                 WHERE fp3.fuel_type_id = ft.fuel_type_id 
                 AND fp3.status = 'active'
                 ORDER BY fp3.effective_date DESC LIMIT 1) AS effective_date
            FROM 
                fuel_types ft";
    */
    
    $result = $conn->query($sql);
    
    if (!$result) {
        // Log error and return empty array
        error_log("SQL Error in getFuelPrices: " . $conn->error);
        return [];
    }
    
    $prices = [];
    while ($row = $result->fetch_assoc()) {
        // If no previous price is found, use current price
        if (!$row['previous_price']) {
            $row['previous_price'] = $row['current_price'];
        }
        $prices[$row['fuel_type_id']] = $row;
    }
    
    return $prices;
}

// Get sales data grouped by the selected period
function getSalesData($conn, $period_type, $start_date, $end_date, $fuel_type_id = null, $staff_id = null, $pump_id = null) {
    // Set the grouping field based on period type
    $group_format = '';
    $date_format = '';
    
    switch ($period_type) {
        case 'weekly':
            $group_format = "YEARWEEK(DATE(mr.reading_date), 3)"; // ISO week format
            $date_format = "STR_TO_DATE(CONCAT(YEARWEEK(mr.reading_date, 3), ' Monday'), '%X%V %W')";
            break;
        case 'monthly':
            $group_format = "DATE_FORMAT(mr.reading_date, '%Y-%m')";
            $date_format = "DATE_FORMAT(mr.reading_date, '%Y-%m-01')";
            break;
        case 'daily':
        default:
            $group_format = "DATE(mr.reading_date)";
            $date_format = "DATE(mr.reading_date)";
            break;
    }
    
    // Build the query
    $sql = "SELECT 
                $group_format AS period,
                $date_format AS period_date,
                ft.fuel_type_id,
                ft.fuel_name,
                SUM(mr.volume_dispensed) AS total_volume,
                (SELECT MAX(fp1.selling_price) 
                 FROM fuel_prices fp1 
                 WHERE fp1.fuel_type_id = ft.fuel_type_id 
                 AND fp1.effective_date <= MIN(mr.reading_date)
                 GROUP BY fp1.fuel_type_id
                 ORDER BY fp1.effective_date DESC LIMIT 1) AS current_price,
                (SELECT MAX(fp2.selling_price) 
                 FROM fuel_prices fp2 
                 WHERE fp2.fuel_type_id = ft.fuel_type_id 
                 AND fp2.effective_date < (
                     SELECT MAX(fp3.effective_date) 
                     FROM fuel_prices fp3 
                     WHERE fp3.fuel_type_id = ft.fuel_type_id 
                     AND fp3.effective_date <= MIN(mr.reading_date)
                 )
                 GROUP BY fp2.fuel_type_id
                 ORDER BY fp2.effective_date DESC LIMIT 1) AS previous_price
            FROM 
                meter_readings mr
                JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
                JOIN pumps p ON pn.pump_id = p.pump_id
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
                LEFT JOIN staff_assignments sa ON p.pump_id = sa.pump_id 
                    AND DATE(mr.reading_date) = sa.assignment_date
            WHERE 
                DATE(mr.reading_date) BETWEEN ? AND ?";
    
    $params = [$start_date, $end_date];
    $types = "ss";
    
    if ($fuel_type_id) {
        $sql .= " AND ft.fuel_type_id = ?";
        $params[] = $fuel_type_id;
        $types .= "i";
    }
    
    if ($staff_id) {
        $sql .= " AND sa.staff_id = ?";
        $params[] = $staff_id;
        $types .= "i";
    }
    
    if ($pump_id) {
        $sql .= " AND p.pump_id = ?";
        $params[] = $pump_id;
        $types .= "i";
    }
    
    $sql .= " GROUP BY 
                period, 
                ft.fuel_type_id,
                ft.fuel_name
            ORDER BY 
                period_date ASC, 
                ft.fuel_name ASC";
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return [];
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // If no previous price found, use current price
            if (!$row['previous_price']) {
                $row['previous_price'] = $row['current_price'];
            }
            
            // Calculate total revenue and lost profit
            $row['total_revenue'] = $row['total_volume'] * $row['current_price'];
            
            if ($row['current_price'] < $row['previous_price']) {
                $row['lost_profit'] = $row['total_volume'] * ($row['previous_price'] - $row['current_price']);
            } else {
                $row['lost_profit'] = 0;
            }
            
            // Group by period
            if (!isset($data[$row['period']])) {
                $data[$row['period']] = [
                    'period' => $row['period'],
                    'period_date' => $row['period_date'],
                    'fuels' => [],
                    'totals' => [
                        'total_volume' => 0,
                        'total_revenue' => 0,
                        'lost_profit' => 0
                    ]
                ];
            }
            
            $data[$row['period']]['fuels'][] = $row;
            $data[$row['period']]['totals']['total_volume'] += $row['total_volume'];
            $data[$row['period']]['totals']['total_revenue'] += $row['total_revenue'];
            $data[$row['period']]['totals']['lost_profit'] += $row['lost_profit'];
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error in getSalesData: " . $e->getMessage());
        return [];
    }
}

// Calculate comparison with previous period
function getComparisonWithPreviousPeriod($current_data) {
    $current_totals = [
        'total_volume' => 0,
        'total_revenue' => 0,
        'lost_profit' => 0
    ];
    
    // Calculate current period totals
    foreach ($current_data as $period) {
        $current_totals['total_volume'] += $period['totals']['total_volume'];
        $current_totals['total_revenue'] += $period['totals']['total_revenue'];
        $current_totals['lost_profit'] += $period['totals']['lost_profit'];
    }
    
    return $current_totals;
}

// Get data for the report
$fuel_types = getFuelTypes($conn);
$staff_list = getAllStaff($conn);
$pumps_list = getAllPumps($conn);
$fuel_prices = getFuelPrices($conn);

// Get report data
$report_data = getSalesData($conn, $period_type, $start_date, $end_date, $fuel_type_id, $staff_id, $pump_id);
$summary_totals = getComparisonWithPreviousPeriod($report_data);

// Format period labels based on period type
function formatPeriodLabel($period_date, $period_type) {
    switch ($period_type) {
        case 'weekly':
            $date = new DateTime($period_date);
            $week = $date->format('W');
            $year = $date->format('Y');
            return "Week $week, $year";
        case 'monthly':
            return date('F Y', strtotime($period_date));
        case 'daily':
        default:
            return date('M d, Y', strtotime($period_date));
    }
}

?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Daily Sales & Lost Profit Report</h2>
    
    <!-- Filter Form -->
    <form method="POST" action="" class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <!-- Period Type -->
            <div>
                <label for="period_type" class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                <select id="period_type" name="period_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="daily" <?= $period_type === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $period_type === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $period_type === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
            
            <!-- Date Range -->
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
            </div>
            
            <!-- Fuel Type Filter -->
            <div>
                <label for="fuel_type_id" class="block text-sm font-medium text-gray-700 mb-1">Fuel Type</label>
                <select id="fuel_type_id" name="fuel_type_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Fuel Types</option>
                    <?php foreach ($fuel_types as $fuel): ?>
                    <option value="<?= $fuel['fuel_type_id'] ?>" <?= $fuel_type_id == $fuel['fuel_type_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($fuel['fuel_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Staff Filter (Optional) -->
            <div>
                <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-1">Staff (Optional)</label>
                <select id="staff_id" name="staff_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Staff</option>
                    <?php foreach ($staff_list as $staff): ?>
                    <option value="<?= $staff['staff_id'] ?>" <?= $staff_id == $staff['staff_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($staff['staff_name'] . ' (' . $staff['staff_code'] . ')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Pump Filter (Optional) -->
            <div>
                <label for="pump_id" class="block text-sm font-medium text-gray-700 mb-1">Pump (Optional)</label>
                <select id="pump_id" name="pump_id" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="">All Pumps</option>
                    <?php foreach ($pumps_list as $pump): ?>
                    <option value="<?= $pump['pump_id'] ?>" <?= $pump_id == $pump['pump_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pump['pump_name'] . ' (' . $pump['fuel_name'] . ')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </div>
    </form>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Total Liters Sold -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
            <div class="text-sm font-medium text-blue-800 mb-1">Total Liters Sold</div>
            <div class="text-2xl font-bold text-blue-900"><?= number_format($summary_totals['total_volume'], 2) ?> L</div>
        </div>
        
        <!-- Total Revenue -->
        <div class="bg-green-50 rounded-lg p-4 border border-green-100">
            <div class="text-sm font-medium text-green-800 mb-1">Total Revenue</div>
            <div class="text-2xl font-bold text-green-900"><?= getCurrencySymbol() . ' ' . number_format($summary_totals['total_revenue'], 2) ?></div>
        </div>
        
        <!-- Lost Profit -->
        <div class="bg-red-50 rounded-lg p-4 border border-red-100">
            <div class="text-sm font-medium text-red-800 mb-1">Lost Profit (Due to Price Decrease)</div>
            <div class="text-2xl font-bold text-red-900"><?= getCurrencySymbol() . ' ' . number_format($summary_totals['lost_profit'], 2) ?></div>
        </div>
    </div>
    
    <!-- Sales Summary Table -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Sales Summary</h3>
    
    <?php if (empty($report_data)): ?>
    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800">
        <p class="font-medium">No data found for the selected criteria.</p>
        <p class="text-sm mt-1">Try adjusting your filters or date range.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Period</th>
                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Liters Sold</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Previous Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Revenue</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Lost Profit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($report_data as $period): ?>
                    <?php foreach ($period['fuels'] as $index => $fuel): ?>
                        <tr class="hover:bg-gray-50">
                            <?php if ($index === 0): ?>
                            <td class="py-3 px-4 text-sm text-gray-800 align-top" rowspan="<?= count($period['fuels']) ?>">
                                <?= formatPeriodLabel($period['period_date'], $period_type) ?>
                            </td>
                            <?php endif; ?>
                            <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($fuel['fuel_name']) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($fuel['total_volume'], 2) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= getCurrencySymbol() . ' ' . number_format($fuel['current_price'], 2) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= getCurrencySymbol() . ' ' . number_format($fuel['previous_price'], 2) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= getCurrencySymbol() . ' ' . number_format($fuel['total_revenue'], 2) ?></td>
                            <td class="py-3 px-4 text-sm <?= $fuel['lost_profit'] > 0 ? 'text-red-600 font-medium' : 'text-gray-800' ?> text-right">
                                <?= $fuel['lost_profit'] > 0 ? getCurrencySymbol() . ' ' . number_format($fuel['lost_profit'], 2) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Period Total Row -->
                    <tr class="bg-gray-50 font-medium">
                        <td class="py-2 px-4 text-sm text-gray-800" colspan="2">Total for <?= formatPeriodLabel($period['period_date'], $period_type) ?></td>
                        <td class="py-2 px-4 text-sm text-gray-800 text-right"><?= number_format($period['totals']['total_volume'], 2) ?></td>
                        <td class="py-2 px-4 text-sm text-gray-800 text-right" colspan="2"></td>
                        <td class="py-2 px-4 text-sm text-gray-800 text-right"><?= getCurrencySymbol() . ' ' . number_format($period['totals']['total_revenue'], 2) ?></td>
                        <td class="py-2 px-4 text-sm <?= $period['totals']['lost_profit'] > 0 ? 'text-red-600' : 'text-gray-800' ?> text-right">
                            <?= $period['totals']['lost_profit'] > 0 ? getCurrencySymbol() . ' ' . number_format($period['totals']['lost_profit'], 2) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Grand Total Row -->
                <tr class="bg-gray-100 font-bold">
                    <td class="py-3 px-4 text-sm text-gray-800" colspan="2">Grand Total</td>
                    <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($summary_totals['total_volume'], 2) ?></td>
                    <td class="py-3 px-4 text-sm text-gray-800 text-right" colspan="2"></td>
                    <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= getCurrencySymbol() . ' ' . number_format($summary_totals['total_revenue'], 2) ?></td>
                    <td class="py-3 px-4 text-sm <?= $summary_totals['lost_profit'] > 0 ? 'text-red-600' : 'text-gray-800' ?> text-right">
                        <?= $summary_totals['lost_profit'] > 0 ? getCurrencySymbol() . ' ' . number_format($summary_totals['lost_profit'], 2) : '-' ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Export Options -->
    <div class="flex justify-end mt-4 space-x-2">
        <button onclick="printReport()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-print mr-2"></i> Print
        </button>
        <button onclick="exportPDF()" class="inline-flex items-center px-3 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
            <i class="fas fa-file-pdf mr-2"></i> PDF
        </button>
        <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-green-300 rounded-md shadow-sm text-sm font-medium text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fas fa-file-excel mr-2"></i> Excel
        </button>
    </div>
</div>

<script>
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF (placeholder)
    function exportPDF() {
        alert('PDF export functionality will be implemented here');
        // Implementation would require a PDF library
    }
    
    // Export to Excel (placeholder)
    function exportExcel() {
        alert('Excel export functionality will be implemented here');
        // Implementation would require a spreadsheet library
    }
</script>

<?php
// Include footer
require_once '../includes/footer.php';

ob_end_flush();
?>