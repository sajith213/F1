<?php
/**
 * Enhanced Daily Sales & Lost Profit Report
 * 
 * A comprehensive report to track daily fuel sales, revenue, profit margins,
 * and identify profit loss due to price fluctuations.
 */

// Set page title
$page_title = "Daily Sales & Profit Analysis Report";

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

// Set default values
$period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'daily';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$fuel_type_id = isset($_GET['fuel_type_id']) ? $_GET['fuel_type_id'] : null;
$staff_id = isset($_GET['staff_id']) ? $_GET['staff_id'] : null;
$pump_id = isset($_GET['pump_id']) ? $_GET['pump_id'] : null;
$show_price_changes = isset($_GET['show_price_changes']) ? $_GET['show_price_changes'] === '1' : false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $period_type = $_POST['period_type'] ?? 'daily';
    $start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_POST['end_date'] ?? date('Y-m-d');
    $fuel_type_id = !empty($_POST['fuel_type_id']) ? $_POST['fuel_type_id'] : null;
    $staff_id = !empty($_POST['staff_id']) ? $_POST['staff_id'] : null;
    $pump_id = !empty($_POST['pump_id']) ? $_POST['pump_id'] : null;
    $show_price_changes = isset($_POST['show_price_changes']) ? true : false;
    
    // Redirect to maintain bookmarkable URLs
    $query = http_build_query([
        'period_type' => $period_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'fuel_type_id' => $fuel_type_id,
        'staff_id' => $staff_id,
        'pump_id' => $pump_id,
        'show_price_changes' => $show_price_changes ? '1' : '0'
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

// Define currency symbol constant, with fallback to '$' if not found
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', $system_settings['currency_symbol'] ?? 'Rs.');
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
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $fuelTypes[] = $row;
        }
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
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $staff[] = $row;
        }
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
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pumps[] = $row;
        }
    }
    
    return $pumps;
}

// Get price changes during the period
function getPriceChanges($conn, $start_date, $end_date, $fuel_type_id = null) {
    $sql = "SELECT 
                fp.price_id,
                fp.fuel_type_id,
                ft.fuel_name,
                fp.effective_date,
                fp.purchase_price,
                fp.selling_price,
                fp.profit_margin,
                fp.profit_percentage,
                u.full_name as set_by_name
            FROM 
                fuel_prices fp
                JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
                LEFT JOIN users u ON fp.set_by = u.user_id
            WHERE 
                fp.effective_date BETWEEN ? AND ?";
    
    $params = [$start_date, $end_date];
    $types = "ss";
    
    if ($fuel_type_id) {
        $sql .= " AND fp.fuel_type_id = ?";
        $params[] = $fuel_type_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY fp.effective_date DESC, ft.fuel_name ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed in getPriceChanges: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $price_changes = [];
    while ($row = $result->fetch_assoc()) {
        $price_changes[] = $row;
    }
    
    return $price_changes;
}

// Get current and previous prices for each fuel type
function getFuelPrices($conn, $start_date, $end_date) {
    // Get all active fuel types
    $sql = "SELECT fuel_type_id, fuel_name FROM fuel_types";
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("Error fetching fuel types: " . $conn->error);
        return [];
    }
    
    $fuel_types = [];
    while ($row = $result->fetch_assoc()) {
        $fuel_types[$row['fuel_type_id']] = $row;
    }
    
    // For each fuel type, get the price at the start and end of the period
    $prices = [];
    foreach ($fuel_types as $fuel_type_id => $fuel_type) {
        // Get price at the start date (or the closest price before that date)
        $start_price_sql = "SELECT 
                                selling_price, 
                                effective_date,
                                purchase_price,
                                profit_margin,
                                profit_percentage
                            FROM 
                                fuel_prices 
                            WHERE 
                                fuel_type_id = ? 
                                AND effective_date <= ?
                            ORDER BY 
                                effective_date DESC 
                            LIMIT 1";
        
        $stmt = $conn->prepare($start_price_sql);
        if (!$stmt) {
            error_log("Prepare failed for start price: " . $conn->error);
            continue;
        }
        
        $stmt->bind_param("is", $fuel_type_id, $start_date);
        $stmt->execute();
        $start_price_result = $stmt->get_result();
        $start_price_data = $start_price_result->fetch_assoc();
        
        // Get price at the end date (or the closest price before that date)
        $end_price_sql = "SELECT 
                            selling_price, 
                            effective_date,
                            purchase_price,
                            profit_margin,
                            profit_percentage
                          FROM 
                            fuel_prices 
                          WHERE 
                            fuel_type_id = ? 
                            AND effective_date <= ?
                          ORDER BY 
                            effective_date DESC 
                          LIMIT 1";
        
        $stmt = $conn->prepare($end_price_sql);
        if (!$stmt) {
            error_log("Prepare failed for end price: " . $conn->error);
            continue;
        }
        
        $stmt->bind_param("is", $fuel_type_id, $end_date);
        $stmt->execute();
        $end_price_result = $stmt->get_result();
        $end_price_data = $end_price_result->fetch_assoc();
        
        // Get the most recent price before the start date
        $previous_price_sql = "SELECT 
                                selling_price, 
                                effective_date
                               FROM 
                                fuel_prices 
                               WHERE 
                                fuel_type_id = ? 
                                AND effective_date < ?
                               ORDER BY 
                                effective_date DESC 
                               LIMIT 1";
        
        $stmt = $conn->prepare($previous_price_sql);
        if (!$stmt) {
            error_log("Prepare failed for previous price: " . $conn->error);
            continue;
        }
        
        if ($start_price_data) {
            $stmt->bind_param("is", $fuel_type_id, $start_price_data['effective_date']);
            $stmt->execute();
            $previous_price_result = $stmt->get_result();
            $previous_price_data = $previous_price_result->fetch_assoc();
        } else {
            $previous_price_data = null;
        }
        
        // Add to prices array
        $prices[$fuel_type_id] = [
            'fuel_type_id' => $fuel_type_id,
            'fuel_name' => $fuel_type['fuel_name'],
            'start_price' => $start_price_data ? $start_price_data['selling_price'] : null,
            'start_price_date' => $start_price_data ? $start_price_data['effective_date'] : null,
            'start_purchase_price' => $start_price_data ? $start_price_data['purchase_price'] : null,
            'start_profit_margin' => $start_price_data ? $start_price_data['profit_margin'] : null,
            'start_profit_percentage' => $start_price_data ? $start_price_data['profit_percentage'] : null,
            'end_price' => $end_price_data ? $end_price_data['selling_price'] : null,
            'end_price_date' => $end_price_data ? $end_price_data['effective_date'] : null,
            'end_purchase_price' => $end_price_data ? $end_price_data['purchase_price'] : null,
            'end_profit_margin' => $end_price_data ? $end_price_data['profit_margin'] : null,
            'end_profit_percentage' => $end_price_data ? $end_price_data['profit_percentage'] : null,
            'previous_price' => $previous_price_data ? $previous_price_data['selling_price'] : ($start_price_data ? $start_price_data['selling_price'] : null),
            'previous_price_date' => $previous_price_data ? $previous_price_data['effective_date'] : null
        ];
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
                pn.pump_id,
                p.pump_name,
                (
                    SELECT fp.selling_price
                    FROM fuel_prices fp
                    WHERE fp.fuel_type_id = ft.fuel_type_id
                    AND fp.effective_date <= DATE(mr.reading_date)
                    ORDER BY fp.effective_date DESC
                    LIMIT 1
                ) AS selling_price,
                (
                    SELECT fp.purchase_price
                    FROM fuel_prices fp
                    WHERE fp.fuel_type_id = ft.fuel_type_id
                    AND fp.effective_date <= DATE(mr.reading_date)
                    ORDER BY fp.effective_date DESC
                    LIMIT 1
                ) AS purchase_price,
                (
                    SELECT fp.effective_date
                    FROM fuel_prices fp
                    WHERE fp.fuel_type_id = ft.fuel_type_id
                    AND fp.effective_date <= DATE(mr.reading_date)
                    ORDER BY fp.effective_date DESC
                    LIMIT 1
                ) AS price_effective_date,
                (
                    SELECT fp2.selling_price
                    FROM fuel_prices fp2
                    WHERE fp2.fuel_type_id = ft.fuel_type_id
                    AND fp2.effective_date < (
                        SELECT MAX(fp3.effective_date)
                        FROM fuel_prices fp3
                        WHERE fp3.fuel_type_id = ft.fuel_type_id
                        AND fp3.effective_date <= DATE(mr.reading_date)
                    )
                    ORDER BY fp2.effective_date DESC
                    LIMIT 1
                ) AS previous_price
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
                $row['previous_price'] = $row['selling_price'];
            }
            
            // Calculate total revenue, profit and lost profit
            $row['total_revenue'] = $row['total_volume'] * $row['selling_price'];
            $row['profit_margin'] = $row['selling_price'] - $row['purchase_price'];
            $row['total_profit'] = $row['total_volume'] * $row['profit_margin'];
            
            if ($row['selling_price'] < $row['previous_price']) {
                $row['lost_profit'] = $row['total_volume'] * ($row['previous_price'] - $row['selling_price']);
            } else {
                $row['lost_profit'] = 0;
            }
            
            // Calculate profit percentage
            $row['profit_percentage'] = $row['purchase_price'] > 0 ? 
                                        (($row['selling_price'] - $row['purchase_price']) / $row['purchase_price']) * 100 : 0;
            
            // Group by period
            if (!isset($data[$row['period']])) {
                $data[$row['period']] = [
                    'period' => $row['period'],
                    'period_date' => $row['period_date'],
                    'fuels' => [],
                    'totals' => [
                        'total_volume' => 0,
                        'total_revenue' => 0,
                        'total_profit' => 0,
                        'lost_profit' => 0
                    ]
                ];
            }
            
            $data[$row['period']]['fuels'][] = $row;
            $data[$row['period']]['totals']['total_volume'] += $row['total_volume'];
            $data[$row['period']]['totals']['total_revenue'] += $row['total_revenue'];
            $data[$row['period']]['totals']['total_profit'] += $row['total_profit'];
            $data[$row['period']]['totals']['lost_profit'] += $row['lost_profit'];
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error in getSalesData: " . $e->getMessage());
        return [];
    }
}

// Calculate grand totals across all periods
function calculateGrandTotals($sales_data) {
    $grand_totals = [
        'total_volume' => 0,
        'total_revenue' => 0,
        'total_profit' => 0,
        'lost_profit' => 0
    ];
    
    // Sum up totals from each period
    foreach ($sales_data as $period) {
        $grand_totals['total_volume'] += $period['totals']['total_volume'];
        $grand_totals['total_revenue'] += $period['totals']['total_revenue'];
        $grand_totals['total_profit'] += $period['totals']['total_profit'];
        $grand_totals['lost_profit'] += $period['totals']['lost_profit'];
    }
    
    return $grand_totals;
}

// Get fuel-wise totals across all periods
function calculateFuelTotals($sales_data) {
    $fuel_totals = [];
    
    foreach ($sales_data as $period) {
        foreach ($period['fuels'] as $fuel) {
            $fuel_type_id = $fuel['fuel_type_id'];
            
            if (!isset($fuel_totals[$fuel_type_id])) {
                $fuel_totals[$fuel_type_id] = [
                    'fuel_type_id' => $fuel_type_id,
                    'fuel_name' => $fuel['fuel_name'],
                    'total_volume' => 0,
                    'total_revenue' => 0,
                    'total_profit' => 0,
                    'lost_profit' => 0,
                    'current_price' => $fuel['selling_price'],
                    'purchase_price' => $fuel['purchase_price'],
                    'profit_margin' => $fuel['profit_margin']
                ];
            }
            
            $fuel_totals[$fuel_type_id]['total_volume'] += $fuel['total_volume'];
            $fuel_totals[$fuel_type_id]['total_revenue'] += $fuel['total_revenue'];
            $fuel_totals[$fuel_type_id]['total_profit'] += $fuel['total_profit'];
            $fuel_totals[$fuel_type_id]['lost_profit'] += $fuel['lost_profit'];
        }
    }
    
    return $fuel_totals;
}

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

// Get data for the report
$fuel_types = getFuelTypes($conn);
$staff_list = getAllStaff($conn);
$pumps_list = getAllPumps($conn);
$fuel_prices = getFuelPrices($conn, $start_date, $end_date);
$price_changes = $show_price_changes ? getPriceChanges($conn, $start_date, $end_date, $fuel_type_id) : [];

// Get report data
$report_data = getSalesData($conn, $period_type, $start_date, $end_date, $fuel_type_id, $staff_id, $pump_id);
$grand_totals = calculateGrandTotals($report_data);
$fuel_totals = calculateFuelTotals($report_data);

// For chart data
$chart_labels = [];
$chart_datasets = [];
$fuel_colors = [
    '#4BC0C0', // teal
    '#FF6384', // pink
    '#36A2EB', // blue
    '#FFCE56', // yellow
    '#9966FF', // purple
    '#FF9F40', // orange
    '#1ABC9C', // turquoise
    '#F39C12'  // amber
];

if (!empty($report_data)) {
    // Prepare data for chart
    $volume_data = [];
    $revenue_data = [];
    $profit_data = [];
    
    // Get fuel types for chart
    $chart_fuel_types = [];
    foreach ($report_data as $period) {
        foreach ($period['fuels'] as $fuel) {
            if (!in_array($fuel['fuel_type_id'], $chart_fuel_types)) {
                $chart_fuel_types[] = $fuel['fuel_type_id'];
            }
        }
    }
    
    // Initialize datasets
    foreach ($chart_fuel_types as $index => $fuel_type_id) {
        $fuel_name = '';
        foreach ($report_data as $period) {
            foreach ($period['fuels'] as $fuel) {
                if ($fuel['fuel_type_id'] == $fuel_type_id) {
                    $fuel_name = $fuel['fuel_name'];
                    break 2;
                }
            }
        }
        
        $color = $fuel_colors[$index % count($fuel_colors)];
        
        $volume_data[$fuel_type_id] = [
            'label' => $fuel_name,
            'data' => [],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'borderWidth' => 1
        ];
        
        $revenue_data[$fuel_type_id] = [
            'label' => $fuel_name,
            'data' => [],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'borderWidth' => 1
        ];
        
        $profit_data[$fuel_type_id] = [
            'label' => $fuel_name,
            'data' => [],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'borderWidth' => 1
        ];
    }
    
    // Fill in chart data
    foreach ($report_data as $period) {
        $chart_labels[] = formatPeriodLabel($period['period_date'], $period_type);
        
        // Initialize with zero for each fuel type
        foreach ($chart_fuel_types as $fuel_type_id) {
            $volume_data[$fuel_type_id]['data'][] = 0;
            $revenue_data[$fuel_type_id]['data'][] = 0;
            $profit_data[$fuel_type_id]['data'][] = 0;
        }
        
        $period_index = count($chart_labels) - 1;
        
        foreach ($period['fuels'] as $fuel) {
            $fuel_type_id = $fuel['fuel_type_id'];
            
            $volume_data[$fuel_type_id]['data'][$period_index] = round($fuel['total_volume'], 2);
            $revenue_data[$fuel_type_id]['data'][$period_index] = round($fuel['total_revenue'], 2);
            $profit_data[$fuel_type_id]['data'][$period_index] = round($fuel['total_profit'], 2);
        }
    }
    
    // Convert to arrays for Chart.js
    foreach ($volume_data as $dataset) {
        $chart_datasets['volume'][] = $dataset;
    }
    
    foreach ($revenue_data as $dataset) {
        $chart_datasets['revenue'][] = $dataset;
    }
    
    foreach ($profit_data as $dataset) {
        $chart_datasets['profit'][] = $dataset;
    }
}

// Convert to JSON for use in JavaScript
$chart_labels_json = json_encode($chart_labels);
$chart_datasets_json = json_encode($chart_datasets);

?>

<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Daily Sales & Profit Analysis Report</h2>
    
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

            <!-- Show Price Changes -->
            <div class="flex items-center mt-6">
                <input type="checkbox" id="show_price_changes" name="show_price_changes" value="1" 
                       <?= $show_price_changes ? 'checked' : '' ?> 
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="show_price_changes" class="ml-2 block text-sm text-gray-700">
                    Show Price Changes During Period
                </label>
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
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Liters Sold -->
        <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
            <div class="text-sm font-medium text-blue-800 mb-1">Total Liters Sold</div>
            <div class="text-2xl font-bold text-blue-900"><?= number_format($grand_totals['total_volume'], 2) ?> L</div>
        </div>
        
        <!-- Total Revenue -->
        <div class="bg-green-50 rounded-lg p-4 border border-green-100">
            <div class="text-sm font-medium text-green-800 mb-1">Total Revenue</div>
            <div class="text-2xl font-bold text-green-900"><?= CURRENCY_SYMBOL . ' ' . number_format($grand_totals['total_revenue'], 2) ?></div>
        </div>
        
        <!-- Total Profit -->
        <div class="bg-purple-50 rounded-lg p-4 border border-purple-100">
            <div class="text-sm font-medium text-purple-800 mb-1">Total Profit</div>
            <div class="text-2xl font-bold text-purple-900"><?= CURRENCY_SYMBOL . ' ' . number_format($grand_totals['total_profit'], 2) ?></div>
        </div>
        
        <!-- Lost Profit -->
        <div class="bg-red-50 rounded-lg p-4 border border-red-100">
            <div class="text-sm font-medium text-red-800 mb-1">Lost Profit (Due to Price Decrease)</div>
            <div class="text-2xl font-bold text-red-900"><?= CURRENCY_SYMBOL . ' ' . number_format($grand_totals['lost_profit'], 2) ?></div>
        </div>
    </div>
    
    <!-- Price Changes Table (if selected) -->
    <?php if ($show_price_changes && !empty($price_changes)): ?>
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Price Changes During Period</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Effective Date</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Purchase Price</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Selling Price</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Profit Margin</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Profit %</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Set By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($price_changes as $price): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm text-gray-800"><?= date('M d, Y', strtotime($price['effective_date'])) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($price['fuel_name']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($price['purchase_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($price['selling_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($price['profit_margin'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($price['profit_percentage'], 2) ?>%</td>
                        <td class="py-3 px-4 text-sm text-gray-800"><?= htmlspecialchars($price['set_by_name'] ?? 'Unknown') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Fuel Prices At Start & End of Period -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Fuel Prices During Report Period</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                        <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b" colspan="3">At Start (<?= date('M d, Y', strtotime($start_date)) ?>)</th>
                        <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b" colspan="3">At End (<?= date('M d, Y', strtotime($end_date)) ?>)</th>
                        <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b" colspan="2">Change</th>
                    </tr>
                    <tr>
                        <th class="py-2 px-4 text-left text-xs font-medium text-gray-500 border-b"></th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">Purchase</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">Selling</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">Profit %</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">Purchase</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">Selling</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">Profit %</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">Price</th>
                        <th class="py-2 px-4 text-right text-xs font-medium text-gray-500 border-b">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($fuel_prices as $fuel_price): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm font-medium text-gray-800"><?= htmlspecialchars($fuel_price['fuel_name']) ?></td>
                        
                        <!-- Start of period prices -->
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= $fuel_price['start_purchase_price'] ? CURRENCY_SYMBOL . ' ' . number_format($fuel_price['start_purchase_price'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= $fuel_price['start_price'] ? CURRENCY_SYMBOL . ' ' . number_format($fuel_price['start_price'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= $fuel_price['start_profit_percentage'] ? number_format($fuel_price['start_profit_percentage'], 2) . '%' : '-' ?>
                        </td>
                        
                        <!-- End of period prices -->
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= $fuel_price['end_purchase_price'] ? CURRENCY_SYMBOL . ' ' . number_format($fuel_price['end_purchase_price'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= $fuel_price['end_price'] ? CURRENCY_SYMBOL . ' ' . number_format($fuel_price['end_price'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right">
                            <?= $fuel_price['end_profit_percentage'] ? number_format($fuel_price['end_profit_percentage'], 2) . '%' : '-' ?>
                        </td>
                        
                        <!-- Price change -->
                        <?php 
                        $price_change = 0;
                        $price_change_pct = 0;
                        $color_class = '';
                        
                        if ($fuel_price['start_price'] && $fuel_price['end_price']) {
                            $price_change = $fuel_price['end_price'] - $fuel_price['start_price'];
                            $price_change_pct = $fuel_price['start_price'] ? ($price_change / $fuel_price['start_price']) * 100 : 0;
                            
                            if ($price_change > 0) {
                                $color_class = 'text-green-600';
                            } elseif ($price_change < 0) {
                                $color_class = 'text-red-600';
                            }
                        }
                        ?>
                        
                        <td class="py-3 px-4 text-sm <?= $color_class ?> text-right">
                            <?php if ($price_change != 0): ?>
                                <?= ($price_change > 0 ? '+' : '') . CURRENCY_SYMBOL . ' ' . number_format($price_change, 2) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm <?= $color_class ?> text-right">
                            <?php if ($price_change_pct != 0): ?>
                                <?= ($price_change_pct > 0 ? '+' : '') . number_format($price_change_pct, 2) ?>%
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Charts -->
    <?php if (!empty($report_data)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Volume Chart -->
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Volume by Period (Liters)</h3>
            <canvas id="volumeChart" height="250"></canvas>
        </div>
        
        <!-- Revenue Chart -->
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Revenue by Period (<?= CURRENCY_SYMBOL ?>)</h3>
            <canvas id="revenueChart" height="250"></canvas>
        </div>
        
        <!-- Profit Chart -->
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Profit by Period (<?= CURRENCY_SYMBOL ?>)</h3>
            <canvas id="profitChart" height="250"></canvas>
        </div>
        
        <!-- Fuel Distribution Pie Chart -->
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-3">Fuel Distribution</h3>
            <canvas id="distributionChart" height="250"></canvas>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Fuel Summary Table -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Fuel-wise Summary</h3>
        <?php if (empty($fuel_totals)): ?>
        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 text-yellow-800">
            <p class="font-medium">No data found for the selected criteria.</p>
            <p class="text-sm mt-1">Try adjusting your filters or date range.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Fuel Type</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Liters Sold</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Current Price</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Purchase Price</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Profit Margin</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Total Revenue</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Total Profit</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Lost Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($fuel_totals as $fuel): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm font-medium text-gray-800"><?= htmlspecialchars($fuel['fuel_name']) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($fuel['total_volume'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['current_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['purchase_price'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['profit_margin'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['total_revenue'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['total_profit'], 2) ?></td>
                        <td class="py-3 px-4 text-sm <?= $fuel['lost_profit'] > 0 ? 'text-red-600 font-medium' : 'text-gray-800' ?> text-right">
                            <?= $fuel['lost_profit'] > 0 ? CURRENCY_SYMBOL . ' ' . number_format($fuel['lost_profit'], 2) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-100">
                    <tr class="font-medium">
                        <td class="py-3 px-4 text-sm text-gray-800">TOTAL</td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($grand_totals['total_volume'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($grand_totals['total_revenue'], 2) ?></td>
                        <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($grand_totals['total_profit'], 2) ?></td>
                        <td class="py-3 px-4 text-sm <?= $grand_totals['lost_profit'] > 0 ? 'text-red-600 font-medium' : 'text-gray-800' ?> text-right">
                            <?= $grand_totals['lost_profit'] > 0 ? CURRENCY_SYMBOL . ' ' . number_format($grand_totals['lost_profit'], 2) : '-' ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Period-wise Sales Summary Table -->
    <h3 class="text-lg font-semibold text-gray-800 mb-3">Period-wise Sales Summary</h3>
    
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
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Selling Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Purchase Price</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Profit Margin</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Price Effective Date</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Total Revenue</th>
                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Total Profit</th>
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
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['selling_price'], 2) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['purchase_price'], 2) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['profit_margin'], 2) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= date('M d, Y', strtotime($fuel['price_effective_date'])) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['total_revenue'], 2) ?></td>
                            <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($fuel['total_profit'], 2) ?></td>
                            <td class="py-3 px-4 text-sm <?= $fuel['lost_profit'] > 0 ? 'text-red-600 font-medium' : 'text-gray-800' ?> text-right">
                                <?= $fuel['lost_profit'] > 0 ? CURRENCY_SYMBOL . ' ' . number_format($fuel['lost_profit'], 2) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Period Total Row -->
                    <tr class="bg-gray-50 font-medium">
                        <td class="py-2 px-4 text-sm text-gray-800" colspan="2">Total for <?= formatPeriodLabel($period['period_date'], $period_type) ?></td>
                        <td class="py-2 px-4 text-sm text-gray-800 text-right"><?= number_format($period['totals']['total_volume'], 2) ?></td>
                        <td class="py-2 px-4 text-sm text-gray-800 text-right" colspan="4"></td>
                        <td class="py-2 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($period['totals']['total_revenue'], 2) ?></td>
                        <td class="py-2 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($period['totals']['total_profit'], 2) ?></td>
                        <td class="py-2 px-4 text-sm <?= $period['totals']['lost_profit'] > 0 ? 'text-red-600 font-medium' : 'text-gray-800' ?> text-right">
                            <?= $period['totals']['lost_profit'] > 0 ? CURRENCY_SYMBOL . ' ' . number_format($period['totals']['lost_profit'], 2) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Grand Total Row -->
                <tr class="bg-gray-100 font-bold">
                    <td class="py-3 px-4 text-sm text-gray-800" colspan="2">Grand Total</td>
                    <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= number_format($grand_totals['total_volume'], 2) ?></td>
                    <td class="py-3 px-4 text-sm text-gray-800 text-right" colspan="4"></td>
                    <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($grand_totals['total_revenue'], 2) ?></td>
                    <td class="py-3 px-4 text-sm text-gray-800 text-right"><?= CURRENCY_SYMBOL . ' ' . number_format($grand_totals['total_profit'], 2) ?></td>
                    <td class="py-3 px-4 text-sm <?= $grand_totals['lost_profit'] > 0 ? 'text-red-600 font-medium' : 'text-gray-800' ?> text-right">
                        <?= $grand_totals['lost_profit'] > 0 ? CURRENCY_SYMBOL . ' ' . number_format($grand_totals['lost_profit'], 2) : '-' ?>
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
            <i class="fas fa-file-pdf mr-2"></i> Export to PDF
        </button>
        <button onclick="exportExcel()" class="inline-flex items-center px-3 py-2 border border-green-300 rounded-md shadow-sm text-sm font-medium text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fas fa-file-excel mr-2"></i> Export to Excel
        </button>
    </div>
</div>

<!-- Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

<!-- Initialize Charts -->
<script>
    // Define chart colors
    const chartColors = [
        '#4BC0C0', // teal
        '#FF6384', // pink
        '#36A2EB', // blue
        '#FFCE56', // yellow
        '#9966FF', // purple
        '#FF9F40', // orange
        '#1ABC9C', // turquoise
        '#F39C12'  // amber
    ];
    
    // Chart data
    const chartLabels = <?= $chart_labels_json ?> || [];
    const chartDatasets = <?= $chart_datasets_json ?> || { volume: [], revenue: [], profit: [] };
    
    // Create a distribution dataset for the pie chart
    const distributionData = [];
    const distributionLabels = [];
    const distributionColors = [];
    
    <?php foreach ($fuel_totals as $index => $fuel): ?>
    distributionData.push(<?= $fuel['total_volume'] ?>);
    distributionLabels.push("<?= $fuel['fuel_name'] ?>");
    distributionColors.push(chartColors[<?= $index % count($fuel_colors) ?>]);
    <?php endforeach; ?>
    
    document.addEventListener('DOMContentLoaded', function() {
        // Only initialize charts if we have data
        if (chartLabels.length > 0) {
            // Volume Chart
            const volumeCtx = document.getElementById('volumeChart').getContext('2d');
            const volumeChart = new Chart(volumeCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: chartDatasets.volume
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw.toLocaleString() + ' L';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Liters'
                            }
                        }
                    }
                }
            });
            
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: chartDatasets.revenue
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + '<?= CURRENCY_SYMBOL ?> ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '<?= CURRENCY_SYMBOL ?>'
                            }
                        }
                    }
                }
            });
            
            // Profit Chart
            const profitCtx = document.getElementById('profitChart').getContext('2d');
            const profitChart = new Chart(profitCtx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: chartDatasets.profit
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + '<?= CURRENCY_SYMBOL ?> ' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '<?= CURRENCY_SYMBOL ?>'
                            }
                        }
                    }
                }
            });
            
            // Fuel Distribution Pie Chart
            if (distributionData.length > 0) {
                const distributionCtx = document.getElementById('distributionChart').getContext('2d');
                const distributionChart = new Chart(distributionCtx, {
                    type: 'pie',
                    data: {
                        labels: distributionLabels,
                        datasets: [{
                            data: distributionData,
                            backgroundColor: distributionColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                        const percent = Math.round((context.raw / total) * 100);
                                        return context.label + ': ' + context.raw.toLocaleString() + ' L (' + percent + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });
    
    // Print report
    function printReport() {
        window.print();
    }
    
    // Export to PDF
    function exportPDF() {
        // Create a form to submit data to a PDF generator script
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'export_pdf.php';
        form.target = '_blank';
        
        // Add report parameters
        const params = {
            report_type: 'daily_sales',
            start_date: '<?= htmlspecialchars($start_date) ?>',
            end_date: '<?= htmlspecialchars($end_date) ?>',
            period_type: '<?= htmlspecialchars($period_type) ?>',
            fuel_type_id: '<?= htmlspecialchars($fuel_type_id ?? '') ?>',
            staff_id: '<?= htmlspecialchars($staff_id ?? '') ?>',
            pump_id: '<?= htmlspecialchars($pump_id ?? '') ?>',
            show_price_changes: '<?= $show_price_changes ? '1' : '0' ?>'
        };
        
        // Create input elements for each parameter
        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }
        }
        
        // Add form to document, submit it, and remove it
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    // Export to Excel
    function exportExcel() {
        // Create a form to submit data to an Excel generator script
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'export_excel.php';
        form.target = '_blank';
        
        // Add report parameters
        const params = {
            report_type: 'daily_sales',
            start_date: '<?= htmlspecialchars($start_date) ?>',
            end_date: '<?= htmlspecialchars($end_date) ?>',
            period_type: '<?= htmlspecialchars($period_type) ?>',
            fuel_type_id: '<?= htmlspecialchars($fuel_type_id ?? '') ?>',
            staff_id: '<?= htmlspecialchars($staff_id ?? '') ?>',
            pump_id: '<?= htmlspecialchars($pump_id ?? '') ?>',
            show_price_changes: '<?= $show_price_changes ? '1' : '0' ?>'
        };
        
        // Create input elements for each parameter
        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }
        }
        
        // Add form to document, submit it, and remove it
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
</script>

<!-- Print Style -->
<style>
    @media print {
        body {
            font-size: 12pt;
        }
        
        .bg-white {
            background-color: white !important;
            padding: 1em !important;
            margin-bottom: 1em !important;
        }
        
        .hidden-print {
            display: none !important;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    }
</style>

<?php
// Include footer
require_once '../includes/footer.php';
?>