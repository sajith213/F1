<?php
/**
 * Tank Details
 * 
 * View detailed information about a specific tank
 */

// Include tank management functions
require_once 'functions.php';

// Include database connection
require_once '../../includes/db.php';

// Check for tank ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to tanks list if no ID provided
    header('Location: index.php');
    exit;
}

$tank_id = intval($_GET['id']);

// Get tank information
$tank = getTankById($conn, $tank_id);

// If tank not found, redirect to tanks list
if (!$tank) {
    header('Location: index.php');
    exit;
}

// Get connected pumps
$pumps = getConnectedPumps($conn, $tank_id);

// Get tank operations (recent 10)
$operations = getTankInventoryOperations($conn, $tank_id, 10);

// Calculate fill percentage
$fill_percentage = calculateFillPercentage($tank['current_volume'], $tank['capacity']);
$fill_color = getFillColorClass($fill_percentage);
$status_color = getStatusColorClass($tank['status']);

// Process tank operations (add fuel, remove fuel)
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation_type'])) {
    $operation_type = $_POST['operation_type'];
    $change_amount = floatval($_POST['change_amount'] ?? 0);
    $operation_notes = trim($_POST['notes'] ?? '');
    
    // Validate input
    $errors = [];
    
    if ($change_amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    }
    
    // For removal operations, convert to negative
    if ($operation_type === 'removal') {
        $operation_type = 'adjustment'; // Database enum value
        $change_amount = -$change_amount;
        
        // Check if removal amount exceeds current volume
        if (abs($change_amount) > $tank['current_volume']) {
            $errors[] = "Removal amount cannot exceed current tank volume";
        }
    } else if ($operation_type === 'addition') {
        $operation_type = 'adjustment'; // Database enum value
        
        // Check if addition would exceed tank capacity
        if (($change_amount + $tank['current_volume']) > $tank['capacity']) {
            $errors[] = "Addition would exceed tank capacity";
        }
    }
    
    if (empty($operation_notes)) {
        $errors[] = "Please provide notes for this operation";
    }
    
    // If no errors, record the operation
    if (empty($errors)) {
        if (recordTankOperation($conn, $tank_id, $operation_type, $tank['current_volume'], $change_amount, $operation_notes)) {
            $success_message = "Tank operation recorded successfully";
            
            // Refresh tank data
            $tank = getTankById($conn, $tank_id);
            $operations = getTankInventoryOperations($conn, $tank_id, 10);
            $fill_percentage = calculateFillPercentage($tank['current_volume'], $tank['capacity']);
            $fill_color = getFillColorClass($fill_percentage);
        } else {
            $error_message = "Failed to record tank operation";
        }
    } else {
        $error_message = "Please correct the following errors:<br>" . implode("<br>", $errors);
    }
}

// Set page title
$page_title = "Tank Details: " . htmlspecialchars($tank['tank_name']);
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Tank Management</a> / Tank Details';

// Include header
include_once '../../includes/header.php';
?>

<!-- Tank Details Content -->
<div class="space-y-6">
    <!-- Notification Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded">
            <div class="flex items-center">
                <div class="py-1">
                    <svg class="w-6 h-6 mr-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p><?= $success_message ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded">
            <div class="flex items-center">
                <div class="py-1">
                    <svg class="w-6 h-6 mr-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <p><?= $error_message ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tank Overview Card -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <!-- Header with Actions -->
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-oil-can mr-2 text-blue-600"></i>
                <?= htmlspecialchars($tank['tank_name']) ?>
                <span class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?= $status_color ?>-100 text-<?= $status_color ?>-800">
                    <?= ucfirst($tank['status']) ?>
                </span>
            </h2>
            <div class="flex space-x-2">
                <a href="update_tank.php?id=<?= $tank_id ?>" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-edit mr-1"></i> Edit Tank
                </a>
                <a href="index.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Tanks
                </a>
            </div>
        </div>
        
        <!-- Tank Information -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Left Column: Tank Details -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Tank Information</h3>
                        <div class="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Fuel Type</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($tank['fuel_name']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Location</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($tank['location'] ?: 'Not specified') ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Capacity</p>
                                <p class="mt-1 text-sm text-gray-900"><?= formatVolume($tank['capacity']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Low Level Threshold</p>
                                <p class="mt-1 text-sm text-gray-900"><?= formatVolume($tank['low_level_threshold']) ?></p>
                            </div>
                            <div class="col-span-2">
                                <p class="text-sm font-medium text-gray-500">Notes</p>
                                <p class="mt-1 text-sm text-gray-900"><?= htmlspecialchars($tank['notes'] ?: 'No notes available') ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connected Pumps -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Connected Pumps</h3>
                        <?php if (!empty($pumps)): ?>
                            <div class="bg-gray-50 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Pump Name
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Model
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Last Maintenance
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($pumps as $pump): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <a href="../pump_management/pump_details.php?id=<?= $pump['pump_id'] ?>" class="text-blue-600 hover:underline">
                                                        <?= htmlspecialchars($pump['pump_name']) ?>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php 
                                                        $pump_status_color = 'gray';
                                                        if ($pump['status'] == 'active') {
                                                            $pump_status_color = 'green';
                                                        } else if ($pump['status'] == 'maintenance') {
                                                            $pump_status_color = 'yellow';
                                                        } else if ($pump['status'] == 'inactive') {
                                                            $pump_status_color = 'red';
                                                        }
                                                    ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?= $pump_status_color ?>-100 text-<?= $pump_status_color ?>-800">
                                                        <?= ucfirst($pump['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($pump['model'] ?: 'Not specified') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $pump['last_maintenance_date'] ? date('M j, Y', strtotime($pump['last_maintenance_date'])) : 'Not recorded' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700">
                                No pumps are connected to this tank. 
                                <a href="../pump_management/add_pump.php" class="text-blue-600 hover:underline">Add a pump</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tank Operations History -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Recent Operations</h3>
                            <a href="view_operations.php?tank_id=<?= $tank_id ?>" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                View All <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <?php if (!empty($operations)): ?>
                            <div class="bg-gray-50 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Date & Time
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Operation
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Volume Change
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Recorded By
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Notes
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($operations as $op): ?>
                                            <?php 
                                                // Format date
                                                $date = new DateTime($op['operation_date']);
                                                $formatted_date = $date->format('M j, Y g:i A');
                                                
                                                // Format volume change (positive or negative)
                                                $volume_change = $op['change_amount'];
                                                $change_class = $volume_change >= 0 ? 'text-green-600' : 'text-red-600';
                                                $change_sign = $volume_change >= 0 ? '+' : '';
                                            ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $formatted_date ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <?= ucfirst($op['operation_type']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= $change_class ?>">
                                                    <?= $change_sign . formatVolume($volume_change) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($op['recorded_by_name']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500 truncate max-w-xs">
                                                    <?= htmlspecialchars($op['notes']) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700">
                                No operations have been recorded for this tank.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column: Status and Actions -->
                <div class="space-y-6">
                    <!-- Current Status -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <h3 class="text-md font-medium text-gray-700">Current Status</h3>
                        </div>
                        <div class="p-4">
                            <div class="flex flex-col items-center">
                                <!-- Tank Visualization -->
                                <div class="w-32 h-48 relative border-2 border-gray-300 rounded-md mb-4">
                                    <div class="absolute bottom-0 left-0 right-0 bg-<?= $fill_color ?>-500 rounded-b transition-all duration-500" 
                                         style="height: <?= $fill_percentage ?>%;">
                                    </div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="text-xl font-bold <?= $fill_percentage > 50 ? 'text-white' : 'text-gray-700' ?>">
                                            <?= number_format($fill_percentage, 1) ?>%
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Volume Information -->
                                <div class="text-center mb-4">
                                    <p class="text-sm font-medium text-gray-500">Current Volume</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= formatVolume($tank['current_volume']) ?></p>
                                    <p class="text-sm text-gray-500">of <?= formatVolume($tank['capacity']) ?> capacity</p>
                                </div>
                                
                                <!-- Low Level Warning -->
                                <?php if (hasTankLowLevel($tank)): ?>
                                    <div class="w-full p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded mb-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium">
                                                    Low fuel level alert! Current volume is below the threshold of <?= formatVolume($tank['low_level_threshold']) ?>.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <!-- <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <h3 class="text-md font-medium text-gray-700">Quick Actions</h3>
                        </div>
                        <div class="p-4">
                            <div class="space-y-4">
                                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $tank_id) ?>" method="POST" class="space-y-3">
                                    <input type="hidden" name="operation_type" value="addition">
                                    
                                    <div>
                                        <label for="add_amount" class="block text-sm font-medium text-gray-700">Add Fuel (Liters)</label>
                                        <div class="mt-1 flex rounded-md shadow-sm">
                                            <input type="number" name="change_amount" id="add_amount" step="0.01" min="0.01" max="<?= $tank['capacity'] - $tank['current_volume'] ?>"
                                                  class="focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                                  placeholder="Amount to add">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="add_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                        <div class="mt-1">
                                            <textarea id="add_notes" name="notes" rows="2"
                                                     class="focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                                     placeholder="Reason for adding fuel"></textarea>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <i class="fas fa-plus mr-2"></i> Add Fuel
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="border-t border-gray-200 pt-4">
                                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $tank_id) ?>" method="POST" class="space-y-3">
                                        <input type="hidden" name="operation_type" value="removal">
                                        
                                        <div>
                                            <label for="remove_amount" class="block text-sm font-medium text-gray-700">Remove Fuel (Liters)</label>
                                            <div class="mt-1 flex rounded-md shadow-sm">
                                                <input type="number" name="change_amount" id="remove_amount" step="0.01" min="0.01" max="<?= $tank['current_volume'] ?>"
                                                      class="focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                                      placeholder="Amount to remove">
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <label for="remove_notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                            <div class="mt-1">
                                                <textarea id="remove_notes" name="notes" rows="2"
                                                         class="focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                                         placeholder="Reason for removing fuel"></textarea>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <i class="fas fa-minus mr-2"></i> Remove Fuel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div> -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>