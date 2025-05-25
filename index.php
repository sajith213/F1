<?php
/**
 * Dashboard / Index Page
 * Main entry point for the Petrol Pump Management System
 */

// Set page title
$page_title = "Dashboard";
$breadcrumbs = "Home > Dashboard";

// Include header
include 'includes/header.php';

// Include database connection
require_once 'includes/db.php';

// Get current date
$current_date = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');

// Dashboard summary data
$total_fuel_stock = 0;
$total_sales_today = 0;
$total_sales_month = 0;
$staff_on_duty = 0;
$low_stock_tanks = 0;

// Initialize arrays for chart data
$sales_by_fuel_type = [];
$sales_trend_data = [];
$tank_levels = [];

// Only fetch data if database connection is successful
if (isset($conn) && $conn) {
    try {
        // Get total fuel stock in liters
        $query = "SELECT SUM(current_volume) AS total_stock FROM tanks WHERE status = 'active'";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $total_fuel_stock = $row['total_stock'] ?? 0;
        }
        
        // Get today's sales
        $query = "SELECT SUM(net_amount) AS today_sales FROM sales WHERE DATE(sale_date) = '$current_date'";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $total_sales_today = $row['today_sales'] ?? 0;
        }
        
        // Get month's sales
        $query = "SELECT SUM(net_amount) AS month_sales FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$current_month'";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $total_sales_month = $row['month_sales'] ?? 0;
        }
        
        // Get staff on duty today
        $query = "SELECT COUNT(DISTINCT staff_id) AS staff_count FROM staff_assignments WHERE assignment_date = '$current_date' AND status = 'assigned'";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $staff_on_duty = $row['staff_count'] ?? 0;
        }
        
        // Get tanks with low stock
        $query = "SELECT COUNT(*) AS low_stock_count FROM tanks WHERE current_volume <= low_level_threshold AND status = 'active'";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $low_stock_tanks = $row['low_stock_count'] ?? 0;
        }
        
        // Get sales by fuel type for pie chart
        $query = "SELECT ft.fuel_name, SUM(si.quantity) AS total_quantity 
                FROM sale_items si 
                JOIN pump_nozzles pn ON si.nozzle_id = pn.nozzle_id 
                JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id 
                WHERE si.item_type = 'fuel' 
                GROUP BY ft.fuel_type_id";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sales_by_fuel_type[] = [
                    'name' => $row['fuel_name'],
                    'value' => $row['total_quantity']
                ];
            }
        }
        
        // Get last 7 days sales trend
        $query = "SELECT DATE(sale_date) AS sale_day, SUM(net_amount) AS daily_sales 
                FROM sales 
                WHERE sale_date >= DATE_SUB('$current_date', INTERVAL 6 DAY) 
                GROUP BY DATE(sale_date) 
                ORDER BY sale_day";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sales_trend_data[] = [
                    'date' => date('d M', strtotime($row['sale_day'])),
                    'sales' => $row['daily_sales']
                ];
            }
        }
        
        // Get tank levels for gauge chart
        $query = "SELECT t.tank_name, t.current_volume, t.capacity, ft.fuel_name 
                FROM tanks t 
                JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id 
                WHERE t.status = 'active'";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $percentage = ($row['current_volume'] / $row['capacity']) * 100;
                $tank_levels[] = [
                    'name' => $row['tank_name'],
                    'fuel' => $row['fuel_name'],
                    'percentage' => round($percentage, 1),
                    'current' => $row['current_volume'],
                    'capacity' => $row['capacity']
                ];
            }
        }
        
    } catch (Exception $e) {
        // Log error but continue with defaults
        error_log("Dashboard data fetch error: " . $e->getMessage());
    }
}

// Get recent activities (last 5)
$recent_activities = [];
if (isset($conn) && $conn) {
    try {
        $query = "SELECT 'fuel_delivery' AS activity_type, 
                    CONCAT('Fuel delivery received from supplier #', po.supplier_id) AS description, 
                    fd.delivery_date AS activity_date, 
                    u.full_name AS actor 
                FROM fuel_deliveries fd 
                JOIN purchase_orders po ON fd.po_id = po.po_id 
                JOIN users u ON fd.received_by = u.user_id 
                UNION 
                SELECT 'price_change' AS activity_type, 
                    CONCAT('Price updated for ', ft.fuel_name) AS description, 
                    fp.effective_date AS activity_date, 
                    u.full_name AS actor 
                FROM fuel_prices fp 
                JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id 
                JOIN users u ON fp.set_by = u.user_id 
                UNION 
                SELECT 'cash_settlement' AS activity_type, 
                    CONCAT('Cash settled for pump #', dcr.pump_id) AS description, 
                    dcr.record_date AS activity_date, 
                    u.full_name AS actor 
                FROM daily_cash_records dcr 
                JOIN users u ON dcr.verified_by = u.user_id 
                WHERE dcr.status = 'verified' 
                ORDER BY activity_date DESC LIMIT 5";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_activities[] = $row;
            }
        }
    } catch (Exception $e) {
        // Log error but continue
        error_log("Recent activities fetch error: " . $e->getMessage());
    }
}

// Convert data arrays to JSON for JavaScript use
$sales_by_fuel_type_json = json_encode($sales_by_fuel_type);
$sales_trend_data_json = json_encode($sales_trend_data);
$tank_levels_json = json_encode($tank_levels);

// Currency symbol from settings
$currency_symbol = '$';
$query = "SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol'";
$result = isset($conn) && $conn ? $conn->query($query) : null;
if ($result && $row = $result->fetch_assoc()) {
    $currency_symbol = $row['setting_value'];
}

// Function to format number to 2 decimal places with commas
function formatNumber($number) {
    return number_format((float)$number, 2, '.', ',');
}
?>

<!-- Dashboard Content -->
<!-- Welcome message and date are already in the header -->
    
<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- Total Fuel Stock -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Total Fuel Stock</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= formatNumber($total_fuel_stock) ?> L</h3>
            </div>
            <div class="p-3 bg-blue-100 rounded-full w-12 h-12 flex items-center justify-center">
                <i class="fas fa-oil-can text-blue-500"></i>
            </div>
        </div>
        <div class="mt-4">
            <a href="modules/tank_management/index.php" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                View details <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Today's Sales -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Today's Sales</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $currency_symbol ?> <?= formatNumber($total_sales_today) ?></h3>
            </div>
            <div class="p-3 bg-green-100 rounded-full w-12 h-12 flex items-center justify-center">
                <i class="fas fa-cash-register text-green-500"></i>
            </div>
        </div>
        <div class="mt-4">
            <a href="reports/sales_reports.php" class="text-sm text-green-600 hover:text-green-800 flex items-center">
                View details <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Staff on Duty -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-purple-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Staff on Duty</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $staff_on_duty ?> Staff</h3>
            </div>
            <div class="p-3 bg-purple-100 rounded-full w-12 h-12 flex items-center justify-center">
                <i class="fas fa-users text-purple-500"></i>
            </div>
        </div>
        <div class="mt-4">
            <a href="modules/staff_management/index.php" class="text-sm text-purple-600 hover:text-purple-800 flex items-center">
                View details <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Low Stock Tanks</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $low_stock_tanks ?> Tanks</h3>
            </div>
            <div class="p-3 bg-red-100 rounded-full w-12 h-12 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
            </div>
        </div>
        <div class="mt-4">
            <a href="modules/tank_management/index.php" class="text-sm text-red-600 hover:text-red-800 flex items-center">
                View details <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
</div>

<!-- Charts and Data Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Sales Trend Chart -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Sales Trend (Last 7 Days)</h3>
        <div class="h-64">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>
    
    <!-- Sales by Fuel Type -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Sales by Fuel Type</h3>
        <div class="h-64">
            <canvas id="fuelTypePieChart"></canvas>
        </div>
    </div>
</div>

<!-- Tank Levels Section -->
<div class="bg-white rounded-lg shadow p-5 mb-8">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Tank Levels</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php foreach ($tank_levels as $tank): ?>
        <div class="border rounded-lg p-4">
            <div class="flex justify-between items-center mb-2">
                <h4 class="font-bold"><?= htmlspecialchars($tank['name']) ?></h4>
                <span class="text-sm text-gray-500"><?= htmlspecialchars($tank['fuel']) ?></span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                <?php 
                $color_class = 'bg-green-500';
                if ($tank['percentage'] < 30) {
                    $color_class = 'bg-red-500';
                } elseif ($tank['percentage'] < 60) {
                    $color_class = 'bg-yellow-500';
                }
                ?>
                <div class="<?= $color_class ?> h-4 rounded-full" style="width: <?= $tank['percentage'] ?>%"></div>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span class="font-medium"><?= formatNumber($tank['current']) ?> L</span>
                <span class="text-gray-500"><?= $tank['percentage'] ?>%</span>
                <span class="font-medium"><?= formatNumber($tank['capacity']) ?> L</span>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($tank_levels)): ?>
        <div class="col-span-full text-center py-4 text-gray-500">
            <i class="fas fa-info-circle mr-2"></i> No tank data available
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Two-column layout for Quick Access and Recent Activities -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Quick Access Links -->
    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Access</h3>
        <div class="grid grid-cols-2 gap-3">
            <a href="modules/pump_management/meter_reading.php" class="flex flex-col items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                <div class="p-3 bg-blue-100 rounded-full mb-2">
                    <i class="fas fa-tachometer-alt text-blue-500"></i>
                </div>
                <span class="text-sm font-medium text-blue-800">Meter Reading</span>
            </a>
            <a href="modules/cash_settlement/daily_settlement.php" class="flex flex-col items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                <div class="p-3 bg-green-100 rounded-full mb-2">
                    <i class="fas fa-money-bill-wave text-green-500"></i>
                </div>
                <span class="text-sm font-medium text-green-800">Cash Settlement</span>
            </a>
            <a href="modules/attendance/record_attendance.php" class="flex flex-col items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                <div class="p-3 bg-purple-100 rounded-full mb-2">
                    <i class="fas fa-user-clock text-purple-500"></i>
                </div>
                <span class="text-sm font-medium text-purple-800">Attendance</span>
            </a>
            <a href="modules/pos/index.php" class="flex flex-col items-center p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                <div class="p-3 bg-yellow-100 rounded-full mb-2">
                    <i class="fas fa-shopping-cart text-yellow-500"></i>
                </div>
                <span class="text-sm font-medium text-yellow-800">POS</span>
            </a>
            <a href="modules/fuel_ordering/create_order.php" class="flex flex-col items-center p-3 bg-red-50 rounded-lg hover:bg-red-100 transition">
                <div class="p-3 bg-red-100 rounded-full mb-2">
                    <i class="fas fa-truck text-red-500"></i>
                </div>
                <span class="text-sm font-medium text-red-800">Order Fuel</span>
            </a>
            <a href="reports/financial_reports.php" class="flex flex-col items-center p-3 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                <div class="p-3 bg-indigo-100 rounded-full mb-2">
                    <i class="fas fa-chart-bar text-indigo-500"></i>
                </div>
                <span class="text-sm font-medium text-indigo-800">Reports</span>
            </a>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-5">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Activities</h3>
        <div class="space-y-4">
            <?php if (empty($recent_activities)): ?>
            <div class="text-center py-4 text-gray-500">
                <i class="fas fa-info-circle mr-2"></i> No recent activities found
            </div>
            <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                <div class="flex items-start">
                    <?php 
                    $icon_class = 'fas fa-info-circle text-blue-500';
                    $bg_class = 'bg-blue-100';
                    
                    if ($activity['activity_type'] == 'fuel_delivery') {
                        $icon_class = 'fas fa-truck text-green-500';
                        $bg_class = 'bg-green-100';
                    } elseif ($activity['activity_type'] == 'price_change') {
                        $icon_class = 'fas fa-tags text-purple-500';
                        $bg_class = 'bg-purple-100';
                    } elseif ($activity['activity_type'] == 'cash_settlement') {
                        $icon_class = 'fas fa-cash-register text-yellow-500';
                        $bg_class = 'bg-yellow-100';
                    }
                    ?>
                    <div class="flex-shrink-0 mr-4">
                        <div class="p-2 <?= $bg_class ?> rounded-full">
                            <i class="<?= $icon_class ?>"></i>
                        </div>
                    </div>
                    <div class="flex-grow">
                        <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($activity['description']) ?></p>
                        <div class="flex items-center text-xs text-gray-500 mt-1">
                            <span class="mr-2"><?= date('d M Y, h:i A', strtotime($activity['activity_date'])) ?></span>
                            <span>by <?= htmlspecialchars($activity['actor']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>

<!-- Dashboard charts initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if there's data, otherwise use empty placeholders
    const salesTrendData = <?= !empty($sales_trend_data_json) ? $sales_trend_data_json : '[]' ?>;
    const fuelTypeSalesData = <?= !empty($sales_by_fuel_type_json) ? $sales_by_fuel_type_json : '[]' ?>;
    
    // Sales trend chart (line chart)
    const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
    
    // Default data if empty
    const labels = salesTrendData.length > 0 
        ? salesTrendData.map(item => item.date) 
        : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    
    const data = salesTrendData.length > 0 
        ? salesTrendData.map(item => item.sales) 
        : [0, 0, 0, 0, 0, 0, 0];
    
    const salesTrendChart = new Chart(salesTrendCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Daily Sales',
                data: data,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?= $currency_symbol ?> ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '<?= $currency_symbol ?> ' + context.parsed.y.toLocaleString();
                            return label;
                        }
                    }
                }
            }
        }
    });
    
    // Fuel type sales pie chart
    const fuelTypePieCtx = document.getElementById('fuelTypePieChart').getContext('2d');
    
    // Default data for empty chart
    const pieLabels = fuelTypeSalesData.length > 0 
        ? fuelTypeSalesData.map(item => item.name) 
        : ['No Data'];
    
    const pieData = fuelTypeSalesData.length > 0 
        ? fuelTypeSalesData.map(item => item.value) 
        : [1];
    
    const pieColors = fuelTypeSalesData.length > 0 
        ? [
            'rgba(59, 130, 246, 0.7)',
            'rgba(16, 185, 129, 0.7)',
            'rgba(245, 158, 11, 0.7)',
            'rgba(139, 92, 246, 0.7)',
            'rgba(239, 68, 68, 0.7)',
            'rgba(14, 165, 233, 0.7)',
          ]
        : ['rgba(209, 213, 219, 0.6)'];
    
    const pieBorders = fuelTypeSalesData.length > 0
        ? [
            'rgba(59, 130, 246, 1)',
            'rgba(16, 185, 129, 1)',
            'rgba(245, 158, 11, 1)',
            'rgba(139, 92, 246, 1)',
            'rgba(239, 68, 68, 1)',
            'rgba(14, 165, 233, 1)',
          ]
        : ['rgba(209, 213, 219, 1)'];
    
    const fuelTypePieChart = new Chart(fuelTypePieCtx, {
        type: 'pie',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieData,
                backgroundColor: pieColors,
                borderColor: pieBorders,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (fuelTypeSalesData.length > 0) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((context.raw / total) * 100);
                                label += context.formattedValue + ' L (' + percentage + '%)';
                            } else {
                                label += 'No data';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>