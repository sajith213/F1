<?php
/**
 * View Tank Operations
 * 
 * Page to display all operations for a specific tank or all tanks
 * Enhanced with fuel pricing information and transaction values
 */

// Include tank management functions
require_once 'functions.php';

// Include database connection
require_once '../../includes/db.php';

// Initialize variables
$tank_id = isset($_GET['tank_id']) ? intval($_GET['tank_id']) : 0;
$tank = null;
$tank_name = "All Tanks";
$show_filter = true;
$tanks_list = [];

// Get list of all tanks for filter dropdown
$tanks_query = "SELECT tank_id, tank_name FROM tanks ORDER BY tank_name";
$tanks_result = $conn->query($tanks_query);

if ($tanks_result && $tanks_result->num_rows > 0) {
    while ($row = $tanks_result->fetch_assoc()) {
        $tanks_list[] = $row;
    }
}

// If tank_id is provided, get tank name
if ($tank_id > 0) {
    $tank = getTankById($conn, $tank_id);
    if ($tank) {
        $tank_name = $tank['tank_name'];
    } else {
        // If invalid tank ID, reset to show all tanks
        $tank_id = 0;
    }
}

// Get current fuel prices
$current_prices_query = "SELECT ft.fuel_name, fp.selling_price, fp.effective_date 
                         FROM fuel_prices fp 
                         JOIN fuel_types ft ON fp.fuel_type_id = ft.fuel_type_id 
                         WHERE fp.status = 'active' 
                         ORDER BY ft.fuel_name";
$current_prices_result = $conn->query($current_prices_query);
$current_prices = [];

if ($current_prices_result && $current_prices_result->num_rows > 0) {
    while ($row = $current_prices_result->fetch_assoc()) {
        $current_prices[] = $row;
    }
}

// Set up pagination
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 20;
$offset = ($current_page - 1) * $records_per_page;

// Get filter parameters
$operation_type = isset($_GET['operation_type']) ? $_GET['operation_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$include_price = isset($_GET['include_price']) ? (bool)$_GET['include_price'] : true;

// Build query conditions
$conditions = [];
$params = [];
$param_types = '';

if ($tank_id > 0) {
    $conditions[] = 'ti.tank_id = ?';
    $params[] = $tank_id;
    $param_types .= 'i';
}

if (!empty($operation_type)) {
    $conditions[] = 'ti.operation_type = ?';
    $params[] = $operation_type;
    $param_types .= 's';
}

if (!empty($date_from)) {
    $conditions[] = 'DATE(ti.operation_date) >= ?';
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $conditions[] = 'DATE(ti.operation_date) <= ?';
    $params[] = $date_to;
    $param_types .= 's';
}

// Create WHERE clause
$where_clause = '';
if (!empty($conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $conditions);
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) AS total 
               FROM tank_inventory ti 
               $where_clause";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get operations with pagination and pricing information - updated to only include historical prices
$query = "SELECT ti.*, t.tank_name, t.fuel_type_id, ft.fuel_name, u.full_name as recorded_by_name,
          COALESCE(
            (SELECT fp.selling_price 
             FROM fuel_prices fp 
             WHERE fp.fuel_type_id = t.fuel_type_id 
               AND fp.effective_date <= ti.operation_date 
             ORDER BY fp.effective_date DESC LIMIT 1),
            0
          ) as historical_price,
          COALESCE(
            (SELECT fp.purchase_price 
             FROM fuel_prices fp 
             WHERE fp.fuel_type_id = t.fuel_type_id 
               AND fp.effective_date <= ti.operation_date 
             ORDER BY fp.effective_date DESC LIMIT 1),
            0
          ) as historical_purchase_price
         FROM tank_inventory ti
         JOIN tanks t ON ti.tank_id = t.tank_id
         JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
         JOIN users u ON ti.recorded_by = u.user_id
         $where_clause
         ORDER BY ti.operation_date DESC
         LIMIT $offset, $records_per_page";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$operations_result = $stmt->get_result();

// Calculate summary statistics
$total_value_change = 0;
$total_volume_change = 0;
$operations_data = [];

if ($operations_result && $operations_result->num_rows > 0) {
    while ($op = $operations_result->fetch_assoc()) {
        // Calculate transaction values
        $volume_change = $op['change_amount'];
        $historical_value = $volume_change * $op['historical_price'];
        
        // Add to totals for summary
        $total_volume_change += $volume_change;
        $total_value_change += $historical_value;
        
        // Store operation data for display
        $op['historical_value'] = $historical_value;
        $operations_data[] = $op;
    }
}

// Set page title
$page_title = "Tank Operations: " . htmlspecialchars($tank_name);
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Tank Management</a> / Tank Operations';

// Include header
include_once '../../includes/header.php';

// Helper function to format currency
function formatCurrency($amount) {
    return is_numeric($amount) ? number_format($amount, 2) : "0.00";
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center pb-4 mb-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">
            Tank Operations: <?= htmlspecialchars($tank_name) ?>
        </h2>
        <div class="flex space-x-2">
            <?php if ($tank_id > 0): ?>
                <a href="tank_details.php?id=<?= $tank_id ?>" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors duration-150 ease-in-out">
                    <i class="fas fa-eye mr-2"></i> View Tank
                </a>
            <?php endif; ?>
            <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors duration-150 ease-in-out">
                <i class="fas fa-arrow-left mr-2"></i> Back to Tanks
            </a>
        </div>
    </div>
    
    <!-- Current Fuel Prices Section -->
    <div class="mb-6 bg-green-50 p-4 rounded-lg border border-green-200">
        <h3 class="text-lg font-semibold text-green-800 mb-2">Current Fuel Prices</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php if (!empty($current_prices)): ?>
                <?php foreach($current_prices as $price): ?>
                    <div class="bg-white p-3 rounded shadow-sm">
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($price['fuel_name']) ?></p>
                        <p class="text-xl font-bold text-green-600"><?= formatCurrency($price['selling_price']) ?></p>
                        <p class="text-xs text-gray-400">Since <?= date('M j, Y', strtotime($price['effective_date'])) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white p-3 rounded shadow-sm col-span-4">
                    <p class="text-sm text-gray-500 text-center">No current fuel prices available</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Summary Section -->
    <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
        <h3 class="text-lg font-semibold text-blue-800 mb-2">Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-3 rounded shadow-sm">
                <p class="text-sm text-gray-500">Total Operations</p>
                <p class="text-xl font-bold"><?= $total_records ?></p>
            </div>
            <div class="bg-white p-3 rounded shadow-sm">
                <p class="text-sm text-gray-500">Total Volume Change</p>
                <p class="text-xl font-bold <?= $total_volume_change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= ($total_volume_change >= 0 ? '+' : '') . formatVolume($total_volume_change) ?>
                </p>
            </div>
            <div class="bg-white p-3 rounded shadow-sm">
                <p class="text-sm text-gray-500">Total Value Change</p>
                <p class="text-xl font-bold <?= $total_value_change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= ($total_value_change >= 0 ? '+' : '') . formatCurrency($total_value_change) ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Filter Form -->
    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET" class="space-y-4">
            <!-- Hidden field for tank_id if viewing a specific tank -->
            <?php if ($tank_id > 0): ?>
                <input type="hidden" name="tank_id" value="<?= $tank_id ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Tank Filter (only show if not viewing a specific tank) -->
                <?php if ($tank_id == 0): ?>
                    <div>
                        <label for="tank_id" class="block text-sm font-medium text-gray-700">Tank</label>
                        <select name="tank_id" id="tank_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">All Tanks</option>
                            <?php foreach ($tanks_list as $t): ?>
                                <option value="<?= $t['tank_id'] ?>" <?= $tank_id == $t['tank_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['tank_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <!-- Operation Type Filter -->
                <div>
                    <label for="operation_type" class="block text-sm font-medium text-gray-700">Operation Type</label>
                    <select name="operation_type" id="operation_type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value="">All Types</option>
                        <option value="delivery" <?= $operation_type == 'delivery' ? 'selected' : '' ?>>Delivery</option>
                        <option value="sales" <?= $operation_type == 'sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="adjustment" <?= $operation_type == 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                        <option value="leak" <?= $operation_type == 'leak' ? 'selected' : '' ?>>Leak</option>
                        <option value="transfer" <?= $operation_type == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                        <option value="initial" <?= $operation_type == 'initial' ? 'selected' : '' ?>>Initial</option>
                        <option value="test_liter" <?= $operation_type == 'test_liter' ? 'selected' : '' ?>>Test Liter</option>
                    </select>
                </div>
                
                <!-- Date Range Filter -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                          class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                          class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
            </div>
            
            <!-- Price Display Option -->
            <div class="flex items-center">
                <input type="checkbox" name="include_price" id="include_price" value="1" <?= $include_price ? 'checked' : '' ?> 
                      class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="include_price" class="ml-2 block text-sm text-gray-700">
                    Show pricing information
                </label>
            </div>
            
            <div class="flex justify-end">
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . ($tank_id > 0 ? '?tank_id=' . $tank_id : '')) ?>" class="bg-gray-100 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 mr-3">
                    Reset Filters
                </a>
                <button type="submit" class="bg-blue-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Operations Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date & Time
                    </th>
                    <?php if ($tank_id == 0): ?>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tank
                        </th>
                    <?php endif; ?>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Fuel Type
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Operation Type
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Previous Volume
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Change
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        New Volume
                    </th>
                    <?php if ($include_price): ?>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Price (at operation date)
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Transaction Value
                    </th>
                    <?php endif; ?>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Recorded By
                    </th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Notes
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($operations_data)): ?>
                    <?php foreach ($operations_data as $op): ?>
                        <?php 
                            // Format date
                            $date = new DateTime($op['operation_date']);
                            $formatted_date = $date->format('M j, Y g:i A');
                            
                            // Format volume change (positive or negative)
                            $volume_change = $op['change_amount'];
                            $change_class = $volume_change >= 0 ? 'text-green-600' : 'text-red-600';
                            $change_sign = $volume_change >= 0 ? '+' : '';
                            
                            // Format transaction values
                            $historical_value = $op['historical_value'];
                            $historical_value_class = $historical_value >= 0 ? 'text-green-600' : 'text-red-600';
                            $historical_value_sign = $historical_value >= 0 ? '+' : '';
                            
                            // Set icon and color based on operation type
                            $op_icon = 'fas fa-sync';
                            $op_color = 'blue';
                            
                            switch ($op['operation_type']) {
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
           case 'test_liter':
        $op_icon = 'fas fa-flask';
        $op_color = 'indigo';
        break;
    default:
        $op_icon = 'fas fa-sync';
        $op_color = 'blue';
                            }
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $formatted_date ?>
                            </td>
                            <?php if ($tank_id == 0): ?>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="tank_details.php?id=<?= $op['tank_id'] ?>" class="text-blue-600 hover:underline text-sm font-medium">
                                        <?= htmlspecialchars($op['tank_name']) ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($op['fuel_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-<?= $op_color ?>-100 text-<?= $op_color ?>-800">
                                    <i class="<?= $op_icon ?> mr-1"></i> <?= ucfirst($op['operation_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= formatVolume($op['previous_volume']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= $change_class ?>">
                                <?= $change_sign . formatVolume($volume_change) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= formatVolume($op['new_volume']) ?>
                            </td>
                            <?php if ($include_price): ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= formatCurrency($op['historical_price']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= $historical_value_class ?>">
                                <?= $historical_value_sign . formatCurrency(abs($historical_value)) ?>
                            </td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($op['recorded_by_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                <?= htmlspecialchars($op['notes']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $tank_id == 0 ? ($include_price ? 10 : 8) : ($include_price ? 9 : 7) ?>" class="px-6 py-4 text-center text-sm text-gray-500">
                            No operations found matching the current filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-6">
            <nav class="flex items-center justify-between border-t border-gray-200 px-4 sm:px-0">
                <div class="flex w-0 flex-1">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $current_page - 1]))) ?>" 
                           class="inline-flex items-center border-t-2 border-transparent pt-4 pr-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                            <i class="fas fa-arrow-left mr-3"></i>
                            Previous
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center border-t-2 border-transparent pt-4 pr-1 text-sm font-medium text-gray-300">
                            <i class="fas fa-arrow-left mr-3"></i>
                            Previous
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="hidden md:flex">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <span class="inline-flex items-center border-t-2 border-blue-500 px-4 pt-4 text-sm font-medium text-blue-600">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>" 
                               class="inline-flex items-center border-t-2 border-transparent px-4 pt-4 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                
                <div class="flex w-0 flex-1 justify-end">
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['page' => $current_page + 1]))) ?>"
                           class="inline-flex items-center border-t-2 border-transparent pt-4 pl-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700">
                            Next
                            <i class="fas fa-arrow-right ml-3"></i>
                        </a>
                    <?php else: ?>
                        <span class="inline-flex items-center border-t-2 border-transparent pt-4 pl-1 text-sm font-medium text-gray-300">
                            Next
                            <i class="fas fa-arrow-right ml-3"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    <?php endif; ?>
    
    <!-- Export Options -->
    <div class="mt-6 border-t border-gray-200 pt-4">
        <h3 class="text-lg font-medium text-gray-900 mb-2">Export Options</h3>
        <div class="flex space-x-3">
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-csv mr-2"></i> Export to CSV
            </a>
            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(array_merge($_GET, ['export' => 'pdf']))) ?>" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-file-pdf mr-2"></i> Export to PDF
            </a>
        </div>
    </div>
</div>

<!-- JavaScript for Additional Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle for showing price details
    const priceCheckbox = document.getElementById('include_price');
    if (priceCheckbox) {
        priceCheckbox.addEventListener('change', function() {
            document.querySelector('form').submit();
        });
    }
    
    // Export handlers could be added here if needed
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>