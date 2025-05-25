<?php
/**
 * Pump Management Dashboard
 * 
 * Main dashboard for the pump management module
 */

// Set page title and breadcrumbs
$page_title = "Pump Management";
$breadcrumbs = "<a href='../../index.php'>Home</a> / Pump Management";

// Include necessary files
require_once '../../includes/header.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Get all pumps
$pumps = getAllPumps($conn);

// Count stats
$active_pumps = 0;
$inactive_pumps = 0;
$maintenance_pumps = 0;
$total_nozzles = 0;

foreach ($pumps as $pump) {
    if ($pump['status'] === 'active') {
        $active_pumps++;
    } elseif ($pump['status'] === 'inactive') {
        $inactive_pumps++;
    } elseif ($pump['status'] === 'maintenance') {
        $maintenance_pumps++;
    }
    
    // Get nozzles for this pump to count total
    $nozzles = getNozzlesByPumpId($pump['pump_id']);
    $total_nozzles += count($nozzles);
}

// Get recent meter readings
$recent_readings = getRecentMeterReadings($conn, 5);

// Handle flash messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!-- Notification Messages -->
<?php if (!empty($success_message)): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
    <p><?= htmlspecialchars($success_message) ?></p>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
    <p><?= htmlspecialchars($error_message) ?></p>
</div>
<?php endif; ?>

<!-- Action Buttons -->
<div class="flex flex-wrap mb-6 gap-4">
    <a href="add_pump.php" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i> Add New Pump
    </a>
    <a href="meter_readings.php" class="flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
        <i class="fas fa-tachometer-alt mr-2"></i> Record Meter Reading
    </a>
    <a href="view_pumps.php" class="flex items-center px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition">
        <i class="fas fa-list mr-2"></i> View All Pumps
    </a>
    
    <!-- Button to verify pending meter readings - only for admin/manager -->
    <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'manager'])): ?>
    <a href="verify_meter_readings.php" class="flex items-center px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition ml-auto">
        <i class="fas fa-check-circle mr-2"></i> Verify Meter Readings
        
        <?php 
        // Count pending readings
        $pending_count = count(getPendingMeterReadings($conn));
        if ($pending_count > 0):
        ?>
        <span class="ml-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
            <?= $pending_count ?>
        </span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Active Pumps -->
    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
        <div class="flex items-center">
            <div class="rounded-full bg-green-100 p-3 mr-4">
                <i class="fas fa-charging-station text-green-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Active Pumps</p>
                <p class="text-2xl font-bold"><?= $active_pumps ?></p>
            </div>
        </div>
    </div>
    
    <!-- Inactive Pumps -->
    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
        <div class="flex items-center">
            <div class="rounded-full bg-red-100 p-3 mr-4">
                <i class="fas fa-ban text-red-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Inactive Pumps</p>
                <p class="text-2xl font-bold"><?= $inactive_pumps ?></p>
            </div>
        </div>
    </div>
    
    <!-- Maintenance Pumps -->
    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
        <div class="flex items-center">
            <div class="rounded-full bg-yellow-100 p-3 mr-4">
                <i class="fas fa-tools text-yellow-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">In Maintenance</p>
                <p class="text-2xl font-bold"><?= $maintenance_pumps ?></p>
            </div>
        </div>
    </div>
    
    <!-- Total Nozzles -->
    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
        <div class="flex items-center">
            <div class="rounded-full bg-blue-100 p-3 mr-4">
                <i class="fas fa-gas-pump text-blue-500 text-xl"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Total Nozzles</p>
                <p class="text-2xl font-bold"><?= $total_nozzles ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Pump Overview -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-semibold text-gray-700">Pump Overview</h2>
    </div>
    
    <div class="p-6">
        <?php if (empty($pumps)): ?>
            <p class="text-gray-500 text-center py-4">No pumps found. Add a new pump to get started.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzles</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Installation Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Maintenance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <!-- Find this section in index.php where the pump table rows are generated -->

<?php foreach ($pumps as $pump): ?>
    <tr class="hover:bg-gray-50">
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
                <?php echo htmlspecialchars($pump['tank_name']); ?>
            </div>
            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pump['fuel_name']); ?></div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <?php 
                $nozzles = getNozzlesByPumpId($pump['pump_id']);
                if (!empty($nozzles)): 
            ?>
                <div class="flex flex-wrap gap-1">
                    <?php foreach ($nozzles as $nozzle): ?>
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
            <a href="javascript:void(0);" onclick="deletePump(<?php echo $pump['pump_id']; ?>, '<?php echo htmlspecialchars($pump['pump_name']); ?>')" class="text-red-600 hover:text-red-900" title="Delete">
                <i class="fas fa-trash-alt"></i>
            </a>
        </td>
    </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-center">
                <a href="view_pumps.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All Pumps <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Meter Readings -->
<div class="bg-white rounded-lg shadow">
    <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-700">Recent Meter Readings</h2>
        
        <?php if (in_array($_SESSION['role'] ?? '', ['admin', 'manager'])): ?>
            <?php 
            // Count pending readings
            $pending_count = count(getPendingMeterReadings($conn));
            if ($pending_count > 0):
            ?>
            <a href="verify_meter_readings.php" class="text-yellow-600 hover:text-yellow-700 flex items-center">
                <span class="text-sm font-medium"><?= $pending_count ?> readings need verification</span>
                <i class="fas fa-exclamation-circle ml-2"></i>
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div class="p-6">
        <?php if (empty($recent_readings)): ?>
            <p class="text-gray-500 text-center py-4">No meter readings recorded yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Opening Reading</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Closing Reading</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Volume Dispensed</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_readings as $reading): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    Nozzle #<?= $reading['nozzle_number'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($reading['pump_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($reading['fuel_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($reading['reading_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    <?= number_format($reading['opening_reading'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    <?= number_format($reading['closing_reading'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    <?= number_format($reading['volume_dispensed'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php 
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'verified' => 'bg-green-100 text-green-800',
                                            'disputed' => 'bg-red-100 text-red-800'
                                        ];
                                        $color = $status_colors[$reading['verification_status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $color ?>">
                                        <?= ucfirst($reading['verification_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-center">
                <a href="meter_readings.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All Meter Readings <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Delete Confirmation -->
<script>
function deletePump(pumpId, pumpName) {
    if (confirm("Are you sure you want to delete pump \"" + pumpName + "\"? This action cannot be undone.")) {
        // Create a form dynamically to submit the delete request
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_pump.php';
        
        var pumpIdInput = document.createElement('input');
        pumpIdInput.type = 'hidden';
        pumpIdInput.name = 'pump_id';
        pumpIdInput.value = pumpId;
        
        form.appendChild(pumpIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Include the footer
require_once '../../includes/footer.php';
?>