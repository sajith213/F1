<?php
/**
 * Update Pump
 * 
 * This page allows users to update an existing pump.
 */

// Set page title
$page_title = "Update Pump";
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:underline">Home</a> / <a href="index.php" class="text-blue-600 hover:underline">Pump Management</a> / Update Pump';

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

// Check if pump ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>Invalid pump ID. <a href="index.php" class="text-red-900 underline">Return to pump management</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

$pump_id = (int)$_GET['id'];
$pump = getPumpById($pump_id);

// Check if pump exists
if (!$pump) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p class="font-bold">Error</p>
            <p>Pump not found. <a href="index.php" class="text-red-900 underline">Return to pump management</a>.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Get list of tanks for the dropdown
$tanks = getTanks();

// Get list of fuel types for nozzle dropdowns
$fuel_types = getFuelTypes();

// Get existing nozzles for this pump
$existing_nozzles = getNozzlesByPumpId($pump_id);

// Initialize variables
$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate pump name
    if (empty($_POST['pump_name'])) {
        $errors['pump_name'] = 'Pump name is required';
    }
    
    // Validate tank selection
    if (empty($_POST['tank_id']) || !is_numeric($_POST['tank_id'])) {
        $errors['tank_id'] = 'Please select a valid tank';
    }
    
    // If no errors, proceed with updating the pump
    if (empty($errors)) {
        $pump_data = [
            'pump_name' => trim($_POST['pump_name']),
            'tank_id' => (int)$_POST['tank_id'],
            'status' => $_POST['status'],
            'model' => trim($_POST['model']),
            'installation_date' => !empty($_POST['installation_date']) ? $_POST['installation_date'] : null,
            'last_maintenance_date' => !empty($_POST['last_maintenance_date']) ? $_POST['last_maintenance_date'] : null,
            'notes' => trim($_POST['notes'])
        ];
        
        $update_success = updatePump($pump_id, $pump_data);
        
        // Handle existing nozzles updates
        if (isset($_POST['existing_nozzle_id']) && is_array($_POST['existing_nozzle_id'])) {
            foreach ($_POST['existing_nozzle_id'] as $key => $nozzle_id) {
                if (isset($_POST['existing_nozzle_status'][$key]) && $_POST['existing_nozzle_status'][$key] === 'delete') {
                    // Delete this nozzle
                    deleteNozzle($nozzle_id);
                } elseif (isset($_POST['existing_nozzle_fuel_type'][$key])) {
                    // Update this nozzle
                    $nozzle_data = [
                        'pump_id' => $pump_id,
                        'nozzle_number' => (int)$_POST['existing_nozzle_number'][$key],
                        'fuel_type_id' => (int)$_POST['existing_nozzle_fuel_type'][$key],
                        'status' => $_POST['existing_nozzle_status'][$key]
                    ];
                    
                    updateNozzle($nozzle_id, $nozzle_data);
                }
            }
        }
        
        // Handle new nozzles
        if (isset($_POST['new_nozzle_number']) && is_array($_POST['new_nozzle_number'])) {
            foreach ($_POST['new_nozzle_number'] as $key => $nozzle_number) {
                if (!empty($nozzle_number) && isset($_POST['new_nozzle_fuel_type'][$key])) {
                    $nozzle_data = [
                        'pump_id' => $pump_id,
                        'nozzle_number' => (int)$nozzle_number,
                        'fuel_type_id' => (int)$_POST['new_nozzle_fuel_type'][$key],
                        'status' => 'active'
                    ];
                    
                    addNozzle($nozzle_data);
                }
            }
        }
        
        if ($update_success) {
            $success_message = "Pump updated successfully!";
            
            // Refresh pump data
            $pump = getPumpById($pump_id);
            $existing_nozzles = getNozzlesByPumpId($pump_id);
        } else {
            $errors['general'] = "Failed to update pump. Please try again.";
        }
    }
}
?>

<!-- Main content -->
<div class="container mx-auto px-4 py-6">
    <?php if (!empty($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?php echo $errors['general']; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?php echo $success_message; ?></p>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-4 bg-blue-50 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-800">
                <i class="fas fa-gas-pump mr-2"></i> Update Pump - <?php echo htmlspecialchars($pump['pump_name']); ?>
            </h2>
        </div>
        
        <form method="POST" action="" id="pumpForm" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Pump Name -->
                <div>
                    <label for="pump_name" class="block text-sm font-medium text-gray-700 mb-1">Pump Name <span class="text-red-600">*</span></label>
                    <input type="text" id="pump_name" name="pump_name" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo htmlspecialchars($pump['pump_name']); ?>" required>
                    <?php if (!empty($errors['pump_name'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['pump_name']; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Tank Selection -->
                <div>
                    <label for="tank_id" class="block text-sm font-medium text-gray-700 mb-1">Tank <span class="text-red-600">*</span></label>
                    <select id="tank_id" name="tank_id" 
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                            required>
                        <option value="">-- Select Tank --</option>
                        <?php foreach ($tanks as $tank): ?>
                            <option value="<?php echo $tank['tank_id']; ?>" 
                                    <?php echo ($pump['tank_id'] == $tank['tank_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tank['tank_name']) . ' (' . htmlspecialchars($tank['fuel_name']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['tank_id'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['tank_id']; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Model -->
                <div>
                    <label for="model" class="block text-sm font-medium text-gray-700 mb-1">Model</label>
                    <input type="text" id="model" name="model" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo htmlspecialchars($pump['model'] ?? ''); ?>">
                </div>
                
                <!-- Installation Date -->
                <div>
                    <label for="installation_date" class="block text-sm font-medium text-gray-700 mb-1">Installation Date</label>
                    <input type="date" id="installation_date" name="installation_date" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo $pump['installation_date'] ?? ''; ?>">
                </div>
                
                <!-- Last Maintenance Date -->
                <div>
                    <label for="last_maintenance_date" class="block text-sm font-medium text-gray-700 mb-1">Last Maintenance Date</label>
                    <input type="date" id="last_maintenance_date" name="last_maintenance_date" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo $pump['last_maintenance_date'] ?? ''; ?>">
                </div>
                
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" 
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="active" <?php echo ($pump['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($pump['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="maintenance" <?php echo ($pump['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="mt-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3" 
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?php echo htmlspecialchars($pump['notes'] ?? ''); ?></textarea>
            </div>
            
            <!-- Existing Nozzles Section -->
            <div class="mt-8">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-medium text-gray-800">Existing Nozzles</h3>
                </div>
                
                <?php if (empty($existing_nozzles)): ?>
                    <div class="bg-gray-50 p-4 rounded-md text-gray-500">
                        <p>No nozzles have been added to this pump yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="existingNozzlesTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle Number</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($existing_nozzles as $index => $nozzle): ?>
                                    <tr id="existing-nozzle-row-<?php echo $nozzle['nozzle_id']; ?>">
                                        <input type="hidden" name="existing_nozzle_id[]" value="<?php echo $nozzle['nozzle_id']; ?>">
                                        <td class="px-6 py-4">
                                            <input type="number" name="existing_nozzle_number[]" min="1" max="10" 
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                                   value="<?php echo $nozzle['nozzle_number']; ?>" required>
                                        </td>
                                        <td class="px-6 py-4">
                                            <select name="existing_nozzle_fuel_type[]" 
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                                                    required>
                                                <option value="">-- Select Fuel Type --</option>
                                                <?php foreach ($fuel_types as $fuel_type): ?>
                                                    <option value="<?php echo $fuel_type['fuel_type_id']; ?>" 
                                                            <?php echo ($nozzle['fuel_type_id'] == $fuel_type['fuel_type_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($fuel_type['fuel_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4">
                                            <select name="existing_nozzle_status[]" 
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                                                <option value="active" <?php echo ($nozzle['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($nozzle['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="maintenance" <?php echo ($nozzle['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                                <option value="delete">Delete This Nozzle</option>
                                            </select>
                                        </td>
                                        <td class="px-6 py-4">
                                            <button type="button" onclick="confirmDeleteNozzle(<?php echo $nozzle['nozzle_id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- New Nozzles Section -->
            <div class="mt-8">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-medium text-gray-800">Add New Nozzles</h3>
                    <button type="button" id="addNozzle" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:border-blue-700 focus:shadow-outline-blue active:bg-blue-700 transition ease-in-out duration-150">
                        <i class="fas fa-plus mr-1"></i> Add Nozzle
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="newNozzlesTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle Number</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="newNozzlesBody">
                            <tr id="noNewNozzlesRow">
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">No new nozzles added yet. Click "Add Nozzle" to add one.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="mt-8 flex justify-end">
                <a href="index.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <i class="fas fa-save mr-2"></i> Update Pump
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const addNozzleButton = document.getElementById('addNozzle');
        const newNozzlesBody = document.getElementById('newNozzlesBody');
        const noNewNozzlesRow = document.getElementById('noNewNozzlesRow');
        let nozzleCount = 0;
        
        // Function to add a new nozzle row
        function addNozzleRow() {
            // Hide the "no nozzles" message row
            if (noNewNozzlesRow) {
                noNewNozzlesRow.style.display = 'none';
            }
            
            const newRow = document.createElement('tr');
            newRow.id = `new-nozzle-row-${nozzleCount}`;
            
            newRow.innerHTML = `
                <td class="px-6 py-4">
                    <input type="number" name="new_nozzle_number[]" min="1" max="10" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           required>
                </td>
                <td class="px-6 py-4">
                    <select name="new_nozzle_fuel_type[]" 
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                            required>
                        <option value="">-- Select Fuel Type --</option>
                        <?php foreach ($fuel_types as $fuel_type): ?>
                            <option value="<?php echo $fuel_type['fuel_type_id']; ?>">
                                <?php echo htmlspecialchars($fuel_type['fuel_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="px-6 py-4">
                    <button type="button" onclick="removeNozzleRow(${nozzleCount})" 
                            class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                </td>
            `;
            
            newNozzlesBody.appendChild(newRow);
            nozzleCount++;
        }
        
        // Add event listener to the "Add Nozzle" button
        addNozzleButton.addEventListener('click', addNozzleRow);
        
        // Function to remove a new nozzle row
        window.removeNozzleRow = function(rowIndex) {
            const row = document.getElementById(`new-nozzle-row-${rowIndex}`);
            if (row) {
                newNozzlesBody.removeChild(row);
                
                // If no nozzles left, show the "no nozzles" message
                if (newNozzlesBody.children.length === 1 && newNozzlesBody.children[0].id === 'noNewNozzlesRow') {
                    noNewNozzlesRow.style.display = '';
                }
            }
        }
        
        // Function to confirm deletion of an existing nozzle
        window.confirmDeleteNozzle = function(nozzleId) {
            const selectElement = document.querySelector(`#existing-nozzle-row-${nozzleId} select[name="existing_nozzle_status[]"]`);
            if (selectElement) {
                selectElement.value = 'delete';
                alert('This nozzle will be deleted when you save the form. To undo, change the status back to Active.');
            }
        }
    });
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>