<?php
/**
 * Add New Pump
 * 
 * This page allows users to add a new pump to the system.
 */

// Set page title
$page_title = "Add New Pump";
$breadcrumbs = '<a href="../../index.php" class="text-blue-600 hover:underline">Home</a> / <a href="index.php" class="text-blue-600 hover:underline">Pump Management</a> / Add New Pump';

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

// Get list of tanks for the dropdown
$tanks = getTanks();

// Get list of fuel types for nozzle dropdowns
$fuel_types = getFuelTypes();

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
    
    // If no errors, proceed with adding the pump
    if (empty($errors)) {
        $pump_data = [
            'pump_name' => trim($_POST['pump_name']),
            'tank_id' => (int)$_POST['tank_id'],
            'status' => $_POST['status'],
            'model' => trim($_POST['model']),
            'installation_date' => !empty($_POST['installation_date']) ? $_POST['installation_date'] : null,
            'notes' => trim($_POST['notes'])
        ];
        
        $new_pump_id = addPump($pump_data);
        
        if ($new_pump_id) {
            // Now add nozzles if any were specified
            if (isset($_POST['nozzle_number']) && is_array($_POST['nozzle_number'])) {
                foreach ($_POST['nozzle_number'] as $key => $nozzle_number) {
                    if (!empty($nozzle_number) && isset($_POST['fuel_type_id'][$key])) {
                        $nozzle_data = [
                            'pump_id' => $new_pump_id,
                            'nozzle_number' => (int)$nozzle_number,
                            'fuel_type_id' => (int)$_POST['fuel_type_id'][$key],
                            'status' => 'active'
                        ];
                        
                        addNozzle($nozzle_data);
                    }
                }
            }
            
            $success_message = "Pump added successfully!";
            
            // Redirect to pump details page after 2 seconds
            echo '<meta http-equiv="refresh" content="2;url=pump_details.php?id=' . $new_pump_id . '">';
        } else {
            $errors['general'] = "Failed to add pump. Please try again.";
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
                <i class="fas fa-gas-pump mr-2"></i> Pump Details
            </h2>
        </div>
        
        <form method="POST" action="" id="pumpForm" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Pump Name -->
                <div>
                    <label for="pump_name" class="block text-sm font-medium text-gray-700 mb-1">Pump Name <span class="text-red-600">*</span></label>
                    <input type="text" id="pump_name" name="pump_name" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo isset($_POST['pump_name']) ? htmlspecialchars($_POST['pump_name']) : ''; ?>" required>
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
                                    <?php echo (isset($_POST['tank_id']) && $_POST['tank_id'] == $tank['tank_id']) ? 'selected' : ''; ?>>
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
                           value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>">
                </div>
                
                <!-- Installation Date -->
                <div>
                    <label for="installation_date" class="block text-sm font-medium text-gray-700 mb-1">Installation Date</label>
                    <input type="date" id="installation_date" name="installation_date" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           value="<?php echo isset($_POST['installation_date']) ? $_POST['installation_date'] : ''; ?>">
                </div>
                
                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" 
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="maintenance" <?php echo (isset($_POST['status']) && $_POST['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="mt-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="3" 
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
            </div>
            
            <!-- Nozzles Section -->
            <div class="mt-8">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-medium text-gray-800">Nozzles</h3>
                    <button type="button" id="addNozzle" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-500 focus:outline-none focus:border-blue-700 focus:shadow-outline-blue active:bg-blue-700 transition ease-in-out duration-150">
                        <i class="fas fa-plus mr-1"></i> Add Nozzle
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="nozzlesTable">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nozzle Number</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuel Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="nozzlesBody">
                            <tr id="noNozzlesRow">
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">No nozzles added yet. Click "Add Nozzle" to add one.</td>
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
                    <i class="fas fa-save mr-2"></i> Save Pump
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const addNozzleButton = document.getElementById('addNozzle');
        const nozzlesBody = document.getElementById('nozzlesBody');
        const noNozzlesRow = document.getElementById('noNozzlesRow');
        let nozzleCount = 0;
        
        // Function to add a new nozzle row
        function addNozzleRow() {
            // Hide the "no nozzles" message row
            if (noNozzlesRow) {
                noNozzlesRow.style.display = 'none';
            }
            
            const newRow = document.createElement('tr');
            newRow.id = `nozzle-row-${nozzleCount}`;
            
            newRow.innerHTML = `
                <td class="px-6 py-4">
                    <input type="number" name="nozzle_number[]" min="1" max="10" 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50"
                           required>
                </td>
                <td class="px-6 py-4">
                    <select name="fuel_type_id[]" 
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
            
            nozzlesBody.appendChild(newRow);
            nozzleCount++;
        }
        
        // Add event listener to the "Add Nozzle" button
        addNozzleButton.addEventListener('click', addNozzleRow);
        
        // Function to remove a nozzle row
        window.removeNozzleRow = function(rowIndex) {
            const row = document.getElementById(`nozzle-row-${rowIndex}`);
            if (row) {
                nozzlesBody.removeChild(row);
                
                // If no nozzles left, show the "no nozzles" message
                if (nozzlesBody.children.length === 1 && nozzlesBody.children[0].id === 'noNozzlesRow') {
                    noNozzlesRow.style.display = '';
                }
            }
        }
        
        // Add one nozzle row by default
        addNozzleRow();
    });
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>