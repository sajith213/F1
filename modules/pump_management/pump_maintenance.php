<?php
/**
 * Pump Maintenance
 * 
 * This page allows users to update the maintenance status and record maintenance activities for pumps.
 */

// Set page title
$page_title = "Pump Maintenance";
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:underline">Home</a> / <a href="index.php" class="text-blue-600 hover:underline">Pump Management</a> / Pump Maintenance';

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

// Initialize variables
$errors = [];
$success_message = '';

// Get all pumps
$pumps = getPumps();

// Handle form submission for updating maintenance status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $pump_id = isset($_POST['pump_id']) ? (int)$_POST['pump_id'] : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';
    $maintenance_notes = isset($_POST['maintenance_notes']) ? trim($_POST['maintenance_notes']) : '';
    
    if ($pump_id <= 0) {
        $errors[] = "Invalid pump ID";
    }
    
    if (!in_array($new_status, ['active', 'inactive', 'maintenance'])) {
        $errors[] = "Invalid status";
    }
    
    if (empty($errors)) {
        $pump = getPumpById($pump_id);
        
        if ($pump) {
            $pump_data = [
                'pump_name' => $pump['pump_name'],
                'tank_id' => $pump['tank_id'],
                'status' => $new_status,
                'model' => $pump['model'],
                'installation_date' => $pump['installation_date'],
                'last_maintenance_date' => ($new_status === 'maintenance' || $pump['status'] === 'maintenance') ? date('Y-m-d') : $pump['last_maintenance_date'],
                'notes' => $pump['notes'] . "\n\n" . date('Y-m-d H:i:s') . " - Status changed to " . ucfirst($new_status) . ".\n" . $maintenance_notes
            ];
            
            if (updatePump($pump_id, $pump_data)) {
                $success_message = "Pump status updated successfully!";
                
                // Refresh pumps list to reflect the changes
                $pumps = getPumps();
            } else {
                $errors[] = "Failed to update pump status";
            }
        } else {
            $errors[] = "Pump not found";
        }
    }
}

// Handle form submission for recording maintenance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_maintenance') {
    $pump_id = isset($_POST['pump_id']) ? (int)$_POST['pump_id'] : 0;
    $maintenance_date = isset($_POST['maintenance_date']) ? $_POST['maintenance_date'] : '';
    $maintenance_description = isset($_POST['maintenance_description']) ? trim($_POST['maintenance_description']) : '';
    
    if ($pump_id <= 0) {
        $errors[] = "Invalid pump ID";
    }
    
    if (empty($maintenance_date)) {
        $errors[] = "Maintenance date is required";
    }
    
    if (empty($maintenance_description)) {
        $errors[] = "Maintenance description is required";
    }
    
    if (empty($errors)) {
        $pump = getPumpById($pump_id);
        
        if ($pump) {
            $pump_data = [
                'pump_name' => $pump['pump_name'],
                'tank_id' => $pump['tank_id'],
                'status' => 'active', // Set back to active after maintenance
                'model' => $pump['model'],
                'installation_date' => $pump['installation_date'],
                'last_maintenance_date' => $maintenance_date,
                'notes' => $pump['notes'] . "\n\n" . date('Y-m-d H:i:s') . " - Maintenance recorded on " . $maintenance_date . ".\n" . $maintenance_description
            ];
            
            if (updatePump($pump_id, $pump_data)) {
                $success_message = "Maintenance record added successfully!";
                
                // Refresh pumps list to reflect the changes
                $pumps = getPumps();
            } else {
                $errors[] = "Failed to record maintenance";
            }
        } else {
            $errors[] = "Pump not found";
        }
    }
}
?>

<!-- Main content -->
<div class="container mx-auto px-4 py-6">
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <ul class="mt-2 list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo $success_message; ?></p>
        </div>
    <?php endif; ?>

    <!-- Maintenance Overview Card -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Pumps in Maintenance -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                    <i class="fas fa-wrench text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pumps in Maintenance</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo countPumpsByStatus('maintenance'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Active Pumps -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Active Pumps</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo countPumpsByStatus('active'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Inactive Pumps -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                    <i class="fas fa-times-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Inactive Pumps</p>
                    <p class="text-2xl font-semibold text-gray-800"><?php echo countPumpsByStatus('inactive'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pump Maintenance List Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-wrench mr-2"></i> Pump Maintenance Status
            </h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pump</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tank</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Maintenance</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($pumps)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No pumps found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pumps as $pump): ?>
                            <tr class="<?php echo $pump['status'] === 'maintenance' ? 'bg-yellow-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full 
                                            <?php echo $pump['status'] === 'maintenance' ? 'bg-yellow-100' : 'bg-gray-100'; ?>">
                                            <i class="fas fa-gas-pump 
                                                <?php echo $pump['status'] === 'maintenance' ? 'text-yellow-600' : 'text-gray-500'; ?>"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($pump['pump_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($pump['model'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    // Get tank name
                                    $tank_name = 'N/A';
                                    $query = "SELECT t.tank_name, ft.fuel_name 
                                            FROM tanks t 
                                            JOIN fuel_types ft ON t.fuel_type_id = ft.fuel_type_id
                                            WHERE t.tank_id = {$pump['tank_id']}";
                                    $result = $conn->query($query);
                                    
                                    if ($result && $result->num_rows > 0) {
                                        $tank = $result->fetch_assoc();
                                        $tank_name = htmlspecialchars($tank['tank_name']) . ' (' . htmlspecialchars($tank['fuel_name']) . ')';
                                    }
                                    
                                    echo $tank_name;
                                    ?>
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
                                    <?php 
                                    if ($pump['last_maintenance_date']) {
                                        $date = date('M d, Y', strtotime($pump['last_maintenance_date']));
                                        $days = round((time() - strtotime($pump['last_maintenance_date'])) / (60 * 60 * 24));
                                        $maintenance_age_class = $days > 90 ? 'text-red-600 font-medium' : 'text-gray-500';
                                        
                                        echo $date . ' <span class="' . $maintenance_age_class . '">(' . $days . ' days ago)</span>';
                                    } else {
                                        echo '<span class="text-red-600 font-medium">Never</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex space-x-2">
                                        <button type="button" onclick="openStatusModal(<?php echo $pump['pump_id']; ?>, '<?php echo htmlspecialchars($pump['pump_name']); ?>', '<?php echo $pump['status']; ?>')" class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-sync-alt"></i> Update Status
                                        </button>
                                        <button type="button" onclick="openMaintenanceModal(<?php echo $pump['pump_id']; ?>, '<?php echo htmlspecialchars($pump['pump_name']); ?>')" class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-tools"></i> Record Maintenance
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Maintenance Guidelines Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-clipboard-list mr-2"></i> Maintenance Guidelines
            </h2>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-md font-medium text-gray-800 mb-2">Regular Maintenance Tasks</h3>
                    <ul class="list-disc list-inside space-y-2 text-gray-600">
                        <li>Check nozzles for leaks or damage</li>
                        <li>Inspect hoses for wear and tear</li>
                        <li>Clean fuel filters</li>
                        <li>Test pump calibration</li>
                        <li>Check for proper grounding</li>
                        <li>Inspect electrical connections</li>
                        <li>Test emergency shut-off functionality</li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-md font-medium text-gray-800 mb-2">Maintenance Schedule</h3>
                    <ul class="list-disc list-inside space-y-2 text-gray-600">
                        <li><span class="font-medium">Daily:</span> Visual inspection</li>
                        <li><span class="font-medium">Weekly:</span> Filter cleaning</li>
                        <li><span class="font-medium">Monthly:</span> Calibration check</li>
                        <li><span class="font-medium">Quarterly:</span> Comprehensive maintenance</li>
                        <li><span class="font-medium">Annually:</span> Full service and certification</li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-6 bg-yellow-50 p-4 rounded-md border-l-4 border-yellow-500">
                <p class="text-yellow-800">
                    <span class="font-medium">Important:</span> Pumps that have not been serviced for over 90 days should be prioritized for maintenance to ensure optimal performance and safety.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 bg-blue-50 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-sync-alt mr-2"></i> Update Pump Status
            </h2>
            <button type="button" onclick="closeStatusModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="" id="statusForm" class="p-6">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="pump_id" id="status_pump_id">
            
            <div class="mb-4">
                <p class="text-md font-medium text-gray-700 mb-2">Pump: <span id="status_pump_name" class="font-normal"></span></p>
                <p class="text-md font-medium text-gray-700 mb-4">Current Status: 
                    <span id="status_current_status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"></span>
                </p>
            </div>
            
            <div class="mb-4">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                <select id="status" name="status" 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="maintenance_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="maintenance_notes" name="maintenance_notes" rows="3" 
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                          placeholder="Enter reason for status change and any additional notes"></textarea>
            </div>
            
            <div class="flex justify-end">
                <button type="button" onclick="closeStatusModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Update Status
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Record Maintenance Modal -->
<div id="maintenanceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
        <div class="p-4 bg-blue-50 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-tools mr-2"></i> Record Maintenance
            </h2>
            <button type="button" onclick="closeMaintenanceModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="" id="maintenanceForm" class="p-6">
            <input type="hidden" name="action" value="record_maintenance">
            <input type="hidden" name="pump_id" id="maintenance_pump_id">
            
            <div class="mb-4">
                <p class="text-md font-medium text-gray-700 mb-4">Pump: <span id="maintenance_pump_name" class="font-normal"></span></p>
            </div>
            
            <div class="mb-4">
                <label for="maintenance_date" class="block text-sm font-medium text-gray-700 mb-1">Maintenance Date</label>
                <input type="date" id="maintenance_date" name="maintenance_date" 
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                       value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="mb-4">
                <label for="maintenance_description" class="block text-sm font-medium text-gray-700 mb-1">Maintenance Description</label>
                <textarea id="maintenance_description" name="maintenance_description" rows="4" 
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                          placeholder="Describe the maintenance activities performed" required></textarea>
            </div>
            
            <div class="flex justify-end">
                <button type="button" onclick="closeMaintenanceModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Record Maintenance
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Status Modal Functions
    function openStatusModal(pumpId, pumpName, currentStatus) {
        document.getElementById('status_pump_id').value = pumpId;
        document.getElementById('status_pump_name').textContent = pumpName;
        
        const statusElement = document.getElementById('status_current_status');
        statusElement.textContent = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);
        
        // Set appropriate class for the status badge
        statusElement.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium';
        
        switch (currentStatus) {
            case 'active':
                statusElement.classList.add('bg-green-100', 'text-green-800');
                break;
            case 'inactive':
                statusElement.classList.add('bg-red-100', 'text-red-800');
                break;
            case 'maintenance':
                statusElement.classList.add('bg-yellow-100', 'text-yellow-800');
                break;
            default:
                statusElement.classList.add('bg-gray-100', 'text-gray-800');
        }
        
        // Set the dropdown to the current status
        document.getElementById('status').value = currentStatus;
        
        // Show the modal
        document.getElementById('statusModal').classList.remove('hidden');
    }
    
    function closeStatusModal() {
        document.getElementById('statusModal').classList.add('hidden');
    }
    
    // Maintenance Modal Functions
    function openMaintenanceModal(pumpId, pumpName) {
        document.getElementById('maintenance_pump_id').value = pumpId;
        document.getElementById('maintenance_pump_name').textContent = pumpName;
        
        // Show the modal
        document.getElementById('maintenanceModal').classList.remove('hidden');
    }
    
    function closeMaintenanceModal() {
        document.getElementById('maintenanceModal').classList.add('hidden');
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const statusModal = document.getElementById('statusModal');
        const maintenanceModal = document.getElementById('maintenanceModal');
        
        if (event.target === statusModal) {
            closeStatusModal();
        }
        
        if (event.target === maintenanceModal) {
            closeMaintenanceModal();
        }
    }
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>