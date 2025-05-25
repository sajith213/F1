<?php
/**
 * Tank Management Dashboard
 * 
 * Main overview page for managing fuel tanks
 */

// Set page title
$page_title = "Tank Management";
$breadcrumbs = '<a href="../../index.php">Home</a> / Tank Management';

// Include header
include_once '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Get all tanks with their associated fuel type
$tanks_query = "SELECT t.*, f.fuel_name 
               FROM tanks t
               JOIN fuel_types f ON t.fuel_type_id = f.fuel_type_id
               ORDER BY t.tank_name";
$result = $conn->query($tanks_query);

// Calculate overall statistics
$stats_query = "SELECT 
                COUNT(*) as total_tanks,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_tanks,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_tanks,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_tanks,
                SUM(capacity) as total_capacity,
                SUM(current_volume) as total_volume,
                AVG(current_volume / capacity * 100) as avg_fill_percentage
                FROM tanks";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get recent tank operations (last 5)
$operations_query = "SELECT ti.*, t.tank_name, u.full_name as recorded_by_name
                    FROM tank_inventory ti
                    JOIN tanks t ON ti.tank_id = t.tank_id
                    JOIN users u ON ti.recorded_by = u.user_id
                    ORDER BY ti.operation_date DESC
                    LIMIT 5";
$operations_result = $conn->query($operations_query);
?>

<!-- Dashboard Overview Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Total Tanks -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-blue-100 p-3 mr-4">
                <i class="fas fa-oil-can text-blue-800"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">Total Tanks</div>
                <div class="text-2xl font-bold"><?= $stats['total_tanks'] ?? 0 ?></div>
            </div>
        </div>
    </div>
    
    <!-- Total Capacity -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-green-100 p-3 mr-4">
                <i class="fas fa-fill text-green-800"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">Total Capacity</div>
                <div class="text-2xl font-bold"><?= number_format($stats['total_capacity'] ?? 0, 2) ?> L</div>
            </div>
        </div>
    </div>
    
    <!-- Current Volume -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-yellow-100 p-3 mr-4">
                <i class="fas fa-tachometer-alt text-yellow-800"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">Current Volume</div>
                <div class="text-2xl font-bold"><?= number_format($stats['total_volume'] ?? 0, 2) ?> L</div>
            </div>
        </div>
    </div>
    
    <!-- Fill Percentage -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center">
            <div class="rounded-full bg-purple-100 p-3 mr-4">
                <i class="fas fa-percentage text-purple-800"></i>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">Avg. Fill Level</div>
                <div class="text-2xl font-bold"><?= number_format($stats['avg_fill_percentage'] ?? 0, 1) ?>%</div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Tank Status Overview -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-800">
                    Tank Status Overview
                </h3>
                <a href="add_tank.php" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i> Add New Tank
                </a>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tank Name
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fuel Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Capacity
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Current Volume
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fill Level
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($tank = $result->fetch_assoc()): ?>
                                <?php 
                                    // Calculate fill percentage
                                    $fill_percentage = ($tank['capacity'] > 0) ? ($tank['current_volume'] / $tank['capacity'] * 100) : 0;
                                    
                                    // Determine status color
                                    $status_color = 'gray';
                                    if ($tank['status'] == 'active') {
                                        $status_color = 'green';
                                    } else if ($tank['status'] == 'maintenance') {
                                        $status_color = 'yellow';
                                    } else if ($tank['status'] == 'inactive') {
                                        $status_color = 'red';
                                    }
                                    
                                    // Determine fill level color
                                    $fill_color = 'green';
                                    if ($fill_percentage < 20) {
                                        $fill_color = 'red';
                                    } else if ($fill_percentage < 50) {
                                        $fill_color = 'yellow';
                                    }
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($tank['tank_name']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= htmlspecialchars($tank['fuel_name']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= number_format($tank['capacity'], 2) ?> L
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= number_format($tank['current_volume'], 2) ?> L
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-<?= $fill_color ?>-500 h-2.5 rounded-full" style="width: <?= min(100, round($fill_percentage)) ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-900 mt-1"><?= number_format($fill_percentage, 1) ?>%</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?= $status_color ?>-100 text-<?= $status_color ?>-800">
                                            <?= ucfirst($tank['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="tank_details.php?id=<?= $tank['tank_id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="update_tank.php?id=<?= $tank['tank_id'] ?>" class="text-amber-600 hover:text-amber-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No tanks found. <a href="add_tank.php" class="text-blue-600 hover:underline">Add your first tank</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Right Sidebar -->
    <div class="lg:col-span-1">
        <!-- Recent Tank Operations -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-800">
                    Recent Tank Operations
                </h3>
            </div>
            <div class="p-4">
                <div class="flow-root">
                    <ul role="list" class="-mb-8">
                        <?php if ($operations_result && $operations_result->num_rows > 0): ?>
                            <?php while ($operation = $operations_result->fetch_assoc()): ?>
                                <?php 
                                    // Set icon and color based on operation type
                                    $op_icon = 'fas fa-sync';
                                    $op_color = 'blue';
                                    
                                    switch ($operation['operation_type']) {
                                        case 'delivery':
                                            $op_icon = 'fas fa-truck';
                                            $op_color = 'green';
                                            break;
                                        case 'sales':
                                            $op_icon = 'fas fa-shopping-cart';
                                            $op_color = 'blue';
                                            break;
                                        case 'adjustment':
                                            $op_icon = 'fas fa-sliders-h';
                                            $op_color = 'yellow';
                                            break;
                                        case 'leak':
                                            $op_icon = 'fas fa-tint';
                                            $op_color = 'red';
                                            break;
                                        case 'transfer':
                                            $op_icon = 'fas fa-exchange-alt';
                                            $op_color = 'purple';
                                            break;
                                        case 'initial':
                                            $op_icon = 'fas fa-flag-checkered';
                                            $op_color = 'gray';
                                            break;
                                             // Add the new test operation type
   case 'test_liter':
    $op_icon = 'fas fa-flask';
    $op_color = 'indigo';
    break;
                                    }
                                    
                                    // Format date
                                    $date = new DateTime($operation['operation_date']);
                                    $formatted_date = $date->format('M j, Y g:i A');
                                    
                                    // Format volume change (positive or negative)
                                    $volume_change = $operation['change_amount'];
                                    $change_class = $volume_change >= 0 ? 'text-green-600' : 'text-red-600';
                                    $change_sign = $volume_change >= 0 ? '+' : '';
                                ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($operations_result->num_rows > 1): ?>
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white bg-<?= $op_color ?>-100">
                                                    <i class="<?= $op_icon ?> text-<?= $op_color ?>-600"></i>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 flex justify-between space-x-4">
                                                <div>
                                                    <p class="text-sm text-gray-900">
                                                        <span class="font-medium"><?= ucfirst($operation['operation_type']) ?></span> 
                                                        for <span class="font-medium"><?= htmlspecialchars($operation['tank_name']) ?></span>
                                                    </p>
                                                    <p class="mt-1 text-sm text-gray-500">
                                                        <span class="<?= $change_class ?> font-medium"><?= $change_sign . number_format($volume_change, 2) ?> L</span>
                                                        (<?= number_format($operation['previous_volume'], 2) ?> L → <?= number_format($operation['new_volume'], 2) ?> L)
                                                    </p>
                                                </div>
                                                <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                                    <time datetime="<?= $operation['operation_date'] ?>"><?= $formatted_date ?></time>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="px-4 py-5">
                                <div class="text-sm text-gray-500">
                                    No recent tank operations found.
                                </div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="mt-6">
                    <a href="view_operations.php" class="w-full flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        View All Operations
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tank Status Summary -->
        <div class="bg-white rounded-lg shadow">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-800">
                    Tank Status Summary
                </h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="border border-gray-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-green-600">
                            <?= $stats['active_tanks'] ?? 0 ?>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">Active Tanks</div>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4 text-center">
                        <div class="text-3xl font-bold text-red-600">
                            <?= $stats['inactive_tanks'] ?? 0 ?>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">Inactive Tanks</div>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4 text-center col-span-2">
                        <div class="text-3xl font-bold text-yellow-600">
                            <?= $stats['maintenance_tanks'] ?? 0 ?>
                        </div>
                        <div class="text-sm text-gray-500 mt-1">Tanks In Maintenance</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>