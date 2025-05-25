<?php
// Include header
include 'includes/header.php';
include 'includes/sidebar.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch tank statistics
$tankQuery = "SELECT 
                COUNT(*) as total_tanks,
                SUM(CASE WHEN current_volume < low_level_threshold THEN 1 ELSE 0 END) as low_tanks,
                SUM(capacity) as total_capacity,
                SUM(current_volume) as total_volume
              FROM tanks";
$tankResult = $conn->query($tankQuery);
$tankStats = $tankResult->fetch_assoc();

// Fetch pump statistics
$pumpQuery = "SELECT 
                COUNT(*) as total_pumps,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_pumps,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_pumps
              FROM pumps";
$pumpResult = $conn->query($pumpQuery);
$pumpStats = $pumpResult->fetch_assoc();

// Fetch sales statistics for today
$today = date('Y-m-d');
$salesQuery = "SELECT 
                COUNT(*) as total_sales,
                SUM(total_amount) as total_amount,
                AVG(total_amount) as avg_amount
              FROM sales
              WHERE DATE(sale_date) = '$today'";
$salesResult = $conn->query($salesQuery);
$salesStats = $salesResult->fetch_assoc();

// Fetch staff on duty today
$staffQuery = "SELECT s.staff_id, s.first_name, s.last_name, p.pump_name, sa.shift
              FROM staff_assignments sa
              JOIN staff s ON sa.staff_id = s.staff_id
              JOIN pumps p ON sa.pump_id = p.pump_id
              WHERE sa.assignment_date = '$today' AND sa.status = 'assigned'
              ORDER BY sa.shift, p.pump_name
              LIMIT 5";
$staffResult = $conn->query($staffQuery);

// Fetch latest fuel prices
$priceQuery = "SELECT ft.fuel_name, fp.selling_price, fp.effective_date
              FROM fuel_prices fp
              JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id
              WHERE fp.status = 'active'
              ORDER BY fp.effective_date DESC";
$priceResult = $conn->query($priceQuery);

// Fetch recent fuel deliveries
$deliveryQuery = "SELECT fd.delivery_id, fd.delivery_date, s.supplier_name, po.po_number,
                  SUM(di.quantity_received) as total_quantity
                FROM fuel_deliveries fd
                JOIN purchase_orders po ON fd.po_id = po.po_id
                JOIN suppliers s ON po.supplier_id = s.supplier_id
                JOIN delivery_items di ON fd.delivery_id = di.delivery_id
                GROUP BY fd.delivery_id
                ORDER BY fd.delivery_date DESC
                LIMIT 5";
$deliveryResult = $conn->query($deliveryQuery);

// Get fuel dispensed by type (last 7 days) for the chart
$chartQuery = "SELECT ft.fuel_name, SUM(mr.volume_dispensed) as volume
              FROM meter_readings mr
              JOIN pump_nozzles pn ON mr.nozzle_id = pn.nozzle_id
              JOIN fuel_types ft ON pn.fuel_type_id = ft.fuel_type_id
              WHERE mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY ft.fuel_type_id";
$chartResult = $conn->query($chartQuery);
$chartData = [];
while ($row = $chartResult->fetch_assoc()) {
    $chartData[] = $row;
}
?>

<div>
    <!-- Welcome Message -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
        <p class="text-gray-600">Here's what's happening today, <?php echo date('F d, Y'); ?></p>
    </div>
    
    <!-- Stats Cards Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Tanks Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                        <i class="fas fa-cubes text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Tanks Status
                            </dt>
                            <dd>
                                <div class="text-lg font-medium text-gray-900">
                                    <?php echo $tankStats['total_tanks']; ?> Tanks
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between items-center text-sm text-gray-600">
                        <span>Volume</span>
                        <span><?php echo number_format($tankStats['total_volume'], 2); ?> / <?php echo number_format($tankStats['total_capacity'], 2); ?> L</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-1">
                        <?php $percentage = ($tankStats['total_volume'] / $tankStats['total_capacity']) * 100; ?>
                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo min(100, $percentage); ?>%"></div>
                    </div>
                </div>
                <?php if ($tankStats['low_tanks'] > 0): ?>
                <div class="mt-3 flex items-center text-sm font-medium text-red-600">
                    <i class="fas fa-exclamation-circle mr-1.5"></i>
                    <?php echo $tankStats['low_tanks']; ?> tanks below threshold
                </div>
                <?php endif; ?>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6">
                <a href="<?php echo APP_URL; ?>/modules/tank_management/" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                    View all tanks <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Pumps Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                        <i class="fas fa-charging-station text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Pumps Status
                            </dt>
                            <dd>
                                <div class="text-lg font-medium text-gray-900">
                                    <?php echo $pumpStats['total_pumps']; ?> Pumps
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span class="flex items-center">
                            <span class="h-2.5 w-2.5 rounded-full bg-green-500 mr-2"></span>
                            Active
                        </span>
                        <span><?php echo $pumpStats['active_pumps']; ?></span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600 mt-1">
                        <span class="flex items-center">
                            <span class="h-2.5 w-2.5 rounded-full bg-yellow-500 mr-2"></span>
                            Maintenance
                        </span>
                        <span><?php echo $pumpStats['maintenance_pumps']; ?></span>
                    </div>
                </div>
                <?php if ($pumpStats['maintenance_pumps'] > 0): ?>
                <div class="mt-3 flex items-center text-sm font-medium text-yellow-600">
                    <i class="fas fa-tools mr-1.5"></i>
                    <?php echo $pumpStats['maintenance_pumps']; ?> pumps in maintenance
                </div>
                <?php endif; ?>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6">
                <a href="<?php echo APP_URL; ?>/modules/pump_management/" class="text-sm font-medium text-green-600 hover:text-green-500">
                    View all pumps <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Today's Sales Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                        <i class="fas fa-cash-register text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Today's Sales
                            </dt>
                            <dd>
                                <div class="text-lg font-medium text-gray-900">
                                    <?php echo formatCurrency($salesStats['total_amount'] ?: 0); ?>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4 space-y-1">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Total Transactions</span>
                        <span><?php echo $salesStats['total_sales'] ?: 0; ?></span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Average Sale</span>
                        <span><?php echo formatCurrency($salesStats['avg_amount'] ?: 0); ?></span>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6">
                <a href="<?php echo APP_URL; ?>/modules/pos/" class="text-sm font-medium text-purple-600 hover:text-purple-500">
                    Go to POS <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Cash Settlement Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-amber-100 rounded-md p-3">
                        <i class="fas fa-money-bill-wave text-amber-600 text-xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Cash Settlement
                            </dt>
                            <dd>
                                <div class="text-lg font-medium text-gray-900">
                                    <?php 
                                    // Get pending settlements count
                                    $settlementQuery = "SELECT COUNT(*) as pending FROM daily_cash_records WHERE record_date = '$today' AND status = 'pending'";
                                    $settlementResult = $conn->query($settlementQuery);
                                    $settlementStats = $settlementResult->fetch_assoc();
                                    
                                    echo $settlementStats['pending']; ?> Pending
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="w-full inline-flex justify-center items-center px-4 py-2 border border-amber-600 shadow-sm text-sm font-medium rounded-md text-amber-600 bg-white hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                        <i class="fas fa-wallet mr-2"></i>
                        Process Settlements
                    </button>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6">
                <a href="<?php echo APP_URL; ?>/modules/cash_settlement/" class="text-sm font-medium text-amber-600 hover:text-amber-500">
                    View all settlements <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Charts & Data Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Fuel Dispensed Chart -->
        <div class="bg-white rounded-lg shadow lg:col-span-2">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Fuel Dispensed (Last 7 Days)</h3>
                <div class="mt-4 h-64">
                    <canvas id="fuelChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Current Fuel Prices -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Current Fuel Prices</h3>
                    <?php if (hasPermission('manager')): ?>
                    <a href="<?php echo APP_URL; ?>/modules/price_management/add_price.php" class="inline-flex items-center text-sm font-medium text-accent-600 hover:text-accent-500">
                        <i class="fas fa-plus mr-1"></i> New
                    </a>
                    <?php endif; ?>
                </div>
                <div class="mt-4 flow-root">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead>
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Fuel Type</th>
                                        <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Price</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($priceResult && $priceResult->num_rows > 0): ?>
                                        <?php while ($price = $priceResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm font-medium text-gray-900"><?php echo $price['fuel_name']; ?></td>
                                                <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-gray-500"><?php echo formatCurrency($price['selling_price']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="2" class="whitespace-nowrap py-3 pl-4 pr-3 text-sm text-gray-500 text-center">No price data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Info Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Staff on Duty -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Staff on Duty Today</h3>
                    <?php if (hasPermission('manager')): ?>
                    <a href="<?php echo APP_URL; ?>/modules/staff_management/assign_staff.php" class="inline-flex items-center text-sm font-medium text-accent-600 hover:text-accent-500">
                        <i class="fas fa-user-plus mr-1"></i> Assign
                    </a>
                    <?php endif; ?>
                </div>
                <div class="mt-4 flow-root">
                    <ul class="divide-y divide-gray-200">
                        <?php if ($staffResult && $staffResult->num_rows > 0): ?>
                            <?php while ($staff = $staffResult->fetch_assoc()): ?>
                                <li class="py-3 flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-700 font-medium">
                                            <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900"><?php echo $staff['first_name'] . ' ' . $staff['last_name']; ?></p>
                                            <p class="text-xs text-gray-500">Pump: <?php echo $staff['pump_name']; ?></p>
                                        </div>
                                    </div>
                                    <div class="text-xs font-medium">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 capitalize">
                                            <?php echo $staff['shift']; ?> shift
                                        </span>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="py-4 text-center text-gray-500">No staff assignments for today</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="mt-4">
                    <a href="<?php echo APP_URL; ?>/modules/staff_management/" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                        View all staff <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Fuel Deliveries -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Recent Fuel Deliveries</h3>
                    <?php if (hasPermission('manager')): ?>
                    <a href="<?php echo APP_URL; ?>/modules/fuel_ordering/create_order.php" class="inline-flex items-center text-sm font-medium text-accent-600 hover:text-accent-500">
                        <i class="fas fa-truck mr-1"></i> New Order
                    </a>
                    <?php endif; ?>
                </div>
                <div class="mt-4 flow-root">
                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead>
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">Date</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Supplier</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">PO #</th>
                                        <th scope="col" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php if ($deliveryResult && $deliveryResult->num_rows > 0): ?>
                                        <?php while ($delivery = $deliveryResult->fetch_assoc()): ?>
                                            <tr>
                                                <td class="whitespace-nowrap py-3 pl-4 pr-3 text-sm font-medium text-gray-900"><?php echo formatDate($delivery['delivery_date']); ?></td>
                                                <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500"><?php echo $delivery['supplier_name']; ?></td>
                                                <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500"><?php echo $delivery['po_number']; ?></td>
                                                <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-gray-500"><?php echo number_format($delivery['total_quantity'], 2); ?> L</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="whitespace-nowrap py-3 pl-4 pr-3 text-sm text-gray-500 text-center">No recent deliveries</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="<?php echo APP_URL; ?>/modules/fuel_ordering/" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                        View all deliveries <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare data for Fuel Chart
    const chartData = <?php echo json_encode($chartData); ?>;
    
    const fuelLabels = chartData.map(item => item.fuel_name);
    const fuelVolumes = chartData.map(item => item.volume);
    
    // Create color array
    const colors = [
        'rgba(54, 162, 235, 0.7)',
        'rgba(255, 99, 132, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(255, 159, 64, 0.7)',
        'rgba(153, 102, 255, 0.7)'
    ];
    
    const ctx = document.getElementById('fuelChart').getContext('2d');
    const fuelChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: fuelLabels,
            datasets: [{
                label: 'Volume (Liters)',
                data: fuelVolumes,
                backgroundColor: colors.slice(0, fuelLabels.length),
                borderColor: colors.slice(0, fuelLabels.length).map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Liters'
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
                            if (context.parsed.y !== null) {
                                label += parseFloat(context.parsed.y).toFixed(2) + ' Liters';
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