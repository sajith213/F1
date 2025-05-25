<?php
/**
 * View All Pumps
 * 
 * This page displays a comprehensive list of all pumps with filtering and sorting options.
 */

// Set page title
$page_title = "View All Pumps";
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:underline">Home</a> / <a href="index.php" class="text-blue-600 hover:underline">Pump Management</a> / View All Pumps';

// Include header
include_once '../../includes/header.php';

// Include database connection
include_once '../../includes/db.php';

// Include the pump management functions
include_once 'functions.php';

// Check if user is authorized to access this module
if (!hasPermission('pump_management', $_SESSION['role'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Access Denied</p>
            <p>You do not have permission to access this module.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Initialize filter and sorting variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$tank_filter = isset($_GET['tank_id']) ? (int)$_GET['tank_id'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'pump_name';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'asc';

// Build the query with filters and sorting
$query = "SELECT p.*, t.tank_name, ft.fuel_name
          FROM pumps p
          LEFT JOIN tanks t ON p.tank_id = t.tank_id
          LEFT JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
          WHERE 1=1";

if (!empty($status_filter)) {
    $query .= " AND p.status = '{$status_filter}'";
}

if ($tank_filter > 0) {
    $query .= " AND p.tank_id = {$tank_filter}";
}

// Validate sort parameters
$allowed_sort_fields = ['pump_name', 'status', 'tank_name', 'installation_date', 'last_maintenance_date'];
$allowed_sort_orders = ['asc', 'desc'];

if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'pump_name';
}

if (!in_array($sort_order, $allowed_sort_orders)) {
    $sort_order = 'asc';
}

$query .= " ORDER BY {$sort_by} {$sort_order}";

// Execute the query
$result = $conn->query($query);
$pumps = [];

if ($result) {
    $pumps = $result->fetch_all(MYSQLI_ASSOC);
}

// Get list of tanks for the filter dropdown
$tanks = getTanks();

// Get pump nozzles with fuel types
$nozzles = getPumpNozzles();

// Group nozzles by pump_id
$pumpNozzles = [];
foreach ($nozzles as $nozzle) {
    if (!isset($pumpNozzles[$nozzle['pump_id']])) {
        $pumpNozzles[$nozzle['pump_id']] = [];
    }
    $pumpNozzles[$nozzle['pump_id']][] = $nozzle;
}

// Function to generate sort URL
function getSortUrl($field, $current_sort, $current_order) {
    $url = '?';
    
    // Add existing filter parameters
    if (isset($_GET['status'])) {
        $url .= 'status=' . urlencode($_GET['status']) . '&';
    }
    
    if (isset($_GET['tank_id'])) {
        $url .= 'tank_id=' . urlencode($_GET['tank_id']) . '&';
    }
    
    // Set sort parameters
    $url .= 'sort=' . $field;
    
    // Toggle order if already sorting by this field
    if ($current_sort === $field) {
        $url .= '&order=' . ($current_order === 'asc' ? 'desc' : 'asc');
    } else {
        $url .= '&order=asc'; // Default to ascending for new sort field
    }
    
    return $url;
}

// Function to get sort indicator
function getSortIndicator($field, $current_sort, $current_order) {
    if ($current_sort === $field) {
        return $current_order === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    }
    return ' <i class="fas fa-sort text-gray-300"></i>';
}
?>

<!-- Main content -->
<div class="container mx-auto px-4 py-6">
    <!-- Filters Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-filter mr-2"></i> Filter Pumps
            </h2>
        </div>
        
        <div class="p-6">
            <form method="GET" action="" class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4">
                <!-- Status Filter -->
                <div class="w-full md:w-1/3">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" 
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                
                <!-- Tank Filter -->
                <div class="w-full md:w-1/3">
                    <label for="tank_id" class="block text-sm font-medium text-gray-700 mb-1">Tank</label>
                    <select id="tank_id" name="tank_id" 
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="0">All Tanks</option>
                        <?php foreach ($tanks as $tank): ?>
                            <option value="<?php echo $tank['tank_id']; ?>" 
                                    <?php echo $tank_filter === $tank['tank_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tank['tank_name']) . ' (' . htmlspecialchars($tank['fuel_name']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filter Button -->
                <div class="w-full md:w-auto self-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white w-full md:w-auto font-bold py-2 px-4 rounded inline-flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i> Apply Filters
                    </button>
                </div>
                
                <!-- Clear Filters -->
                <?php if (!empty($status_filter) || $tank_filter > 0): ?>
                    <div class="w-full md:w-auto self-end">
                        <a href="view_pumps.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 w-full md:w-auto font-bold py-2 px-4 rounded inline-flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Pumps List Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-blue-50 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-gas-pump mr-2"></i> All Pumps
                <?php if (!empty($status_filter) || $tank_filter > 0): ?>
                    <span class="text-sm text-gray-600 ml-2">(Filtered)</span>
                <?php endif; ?>
            </h2>
            
            <a href="add_pump.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-plus mr-2"></i> Add New Pump
            </a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('pump_name', $sort_by, $sort_order); ?>" class="hover:text-gray-900">
                                Pump Name<?php echo getSortIndicator('pump_name', $sort_by, $sort_order); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('tank_name', $sort_by, $sort_order); ?>" class="hover:text-gray-900">
                                Tank<?php echo getSortIndicator('tank_name', $sort_by, $sort_order); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Nozzles
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('status', $sort_by, $sort_order); ?>" class="hover:text-gray-900">
                                Status<?php echo getSortIndicator('status', $sort_by, $sort_order); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('installation_date', $sort_by, $sort_order); ?>" class="hover:text-gray-900">
                                Installation Date<?php echo getSortIndicator('installation_date', $sort_by, $sort_order); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('last_maintenance_date', $sort_by, $sort_order); ?>" class="hover:text-gray-900">
                                Last Maintenance<?php echo getSortIndicator('last_maintenance_date', $sort_by, $sort_order); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($pumps)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                <?php if (!empty($status_filter) || $tank_filter > 0): ?>
                                    No pumps found with the selected filters. <a href="view_pumps.php" class="text-blue-600 hover:underline">Clear filters</a>.
                                <?php else: ?>
                                    No pumps found. <a href="add_pump.php" class="text-blue-600 hover:underline">Add a new pump</a>.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pumps as $pump): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-gray-100 rounded-full">
                                            <i class="fas fa-gas-pump text-gray-500"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($pump['pump_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pump['model'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($pump['tank_name'] ?? 'N/A'); ?>
                                    </div>
                                    <?php if (!empty($pump['fuel_name'])): ?>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pump['fuel_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (isset($pumpNozzles[$pump['pump_id']])): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($pumpNozzles[$pump['pump_id']] as $nozzle): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php 
                                                    switch ($nozzle['fuel_type_id']) {
                                                        case 1: echo 'bg-green-100 text-green-800'; break; // Petrol 92
                                                        case 2: echo 'bg-blue-100 text-blue-800'; break;   // Petrol 95
                                                        case 3: echo 'bg-red-100 text-red-800'; break;     // Diesel
                                                        case 4: echo 'bg-purple-100 text-purple-800'; break; // Super Diesel
                                                        default: echo 'bg-gray-100 text-gray-800';
                                                    }
                                                    ?>">
                                                    <?php 
                                                    echo 'N' . $nozzle['nozzle_number'] . ': ' . 
                                                         htmlspecialchars($nozzle['fuel_name']);
                                                    ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500">No nozzles</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php 
                                        switch ($pump['status']) {
                                            case 'active': echo 'bg-green-100 text-green-800'; break;
                                            case 'inactive': echo 'bg-red-100 text-red-800'; break;
                                            case 'maintenance': echo 'bg-yellow-100 text-yellow-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($pump['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $pump['installation_date'] ? date('M d, Y', strtotime($pump['installation_date'])) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    if ($pump['last_maintenance_date']) {
                                        $date = date('M d, Y', strtotime($pump['last_maintenance_date']));
                                        $days = round((time() - strtotime($pump['last_maintenance_date'])) / (60 * 60 * 24));
                                        echo $date . ' (' . $days . ' days ago)';
                                    } else {
                                        echo 'Not recorded';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="pump_details.php?id=<?php echo $pump['pump_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="update_pump.php?id=<?php echo $pump['pump_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" onclick="confirmDelete(<?php echo $pump['pump_id']; ?>, '<?php echo htmlspecialchars($pump['pump_name']); ?>')" class="text-red-600 hover:text-red-900" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Function to confirm pump deletion
    function confirmDelete(pumpId, pumpName) {
        if (confirm(`Are you sure you want to delete pump "${pumpName}"? This action cannot be undone.`)) {
            window.location.href = 'delete_pump.php?id=' + pumpId;
        }
    }
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>