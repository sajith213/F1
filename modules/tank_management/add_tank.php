<?php
/**
 * Add New Tank
 * 
 * Form for adding new fuel tanks to the system
 */

// Set page title
$page_title = "Add New Tank";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="index.php">Tank Management</a> / Add New Tank';

// Include header
include_once '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Initialize variables for form
$tank_name = '';
$fuel_type_id = '';
$capacity = '';
$current_volume = '0.00';
$low_level_threshold = '';
$status = 'active';
$location = '';
$notes = '';
$success_message = '';
$error_message = '';

// Get fuel types for dropdown
$fuel_types_query = "SELECT fuel_type_id, fuel_name FROM fuel_types ORDER BY fuel_name";
$fuel_types_result = $conn->query($fuel_types_query);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $tank_name = trim($_POST['tank_name'] ?? '');
    $fuel_type_id = intval($_POST['fuel_type_id'] ?? 0);
    $capacity = floatval($_POST['capacity'] ?? 0);
    $current_volume = floatval($_POST['current_volume'] ?? 0);
    $low_level_threshold = floatval($_POST['low_level_threshold'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate required fields
    $errors = [];
    
    if (empty($tank_name)) {
        $errors[] = "Tank name is required";
    }
    
    if ($fuel_type_id <= 0) {
        $errors[] = "Please select a valid fuel type";
    }
    
    if ($capacity <= 0) {
        $errors[] = "Capacity must be greater than zero";
    }
    
    if ($current_volume < 0) {
        $errors[] = "Current volume cannot be negative";
    }
    
    if ($current_volume > $capacity) {
        $errors[] = "Current volume cannot exceed tank capacity";
    }
    
    if ($low_level_threshold < 0 || $low_level_threshold > $capacity) {
        $errors[] = "Low level threshold must be between 0 and tank capacity";
    }
    
    // If no errors, insert the tank
    if (empty($errors)) {
        // Prepare and execute SQL
        $stmt = $conn->prepare("INSERT INTO tanks (tank_name, fuel_type_id, capacity, current_volume, low_level_threshold, status, location, notes, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        
        $stmt->bind_param("sidddsss", $tank_name, $fuel_type_id, $capacity, $current_volume, $low_level_threshold, $status, $location, $notes);
        
        if ($stmt->execute()) {
            $new_tank_id = $conn->insert_id;
            $success_message = "Tank added successfully!";
            
            // If current volume is greater than 0, add an initial inventory record
            if ($current_volume > 0) {
                $operation_type = 'initial';
                $previous_volume = 0;
                $change_amount = $current_volume;
                $new_volume = $current_volume;
                $operation_date = date('Y-m-d H:i:s');
                $recorded_by = $_SESSION['user_id'];
                $notes_inventory = "Initial tank setup with volume of $current_volume liters";
                
                $inventory_stmt = $conn->prepare("INSERT INTO tank_inventory (tank_id, operation_type, previous_volume, change_amount, new_volume, operation_date, recorded_by, notes) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                $inventory_stmt->bind_param("isdddsss", $new_tank_id, $operation_type, $previous_volume, $change_amount, $new_volume, $operation_date, $recorded_by, $notes_inventory);
                $inventory_stmt->execute();
                $inventory_stmt->close();
            }
            
            // Clear form values for a new entry
            $tank_name = '';
            $fuel_type_id = '';
            $capacity = '';
            $current_volume = '0.00';
            $low_level_threshold = '';
            $status = 'active';
            $location = '';
            $notes = '';
        } else {
            $error_message = "Error adding tank: " . $stmt->error;
        }
        
        $stmt->close();
    } else {
        $error_message = "Please correct the following errors:<br>" . implode("<br>", $errors);
    }
}
?>

<div class="bg-white rounded-lg shadow p-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center pb-4 mb-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">Add New Tank</h2>
        <a href="index.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors duration-150 ease-in-out">
            <i class="fas fa-arrow-left mr-2"></i> Back to Tanks
        </a>
    </div>
    
    <!-- Notification Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded">
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
        <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded">
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
    
    <!-- Add Tank Form -->
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Tank Name -->
            <div>
                <label for="tank_name" class="block text-sm font-medium text-gray-700">Tank Name <span class="text-red-600">*</span></label>
                <input type="text" name="tank_name" id="tank_name" value="<?= htmlspecialchars($tank_name) ?>" required
                      class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <p class="mt-1 text-xs text-gray-500">Enter a unique name for the tank (e.g., "Tank 1" or "Diesel Tank North")</p>
            </div>
            
            <!-- Fuel Type -->
            <div>
                <label for="fuel_type_id" class="block text-sm font-medium text-gray-700">Fuel Type <span class="text-red-600">*</span></label>
                <select name="fuel_type_id" id="fuel_type_id" required
                       class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">Select Fuel Type</option>
                    <?php if ($fuel_types_result && $fuel_types_result->num_rows > 0): ?>
                        <?php while ($fuel_type = $fuel_types_result->fetch_assoc()): ?>
                            <option value="<?= $fuel_type['fuel_type_id'] ?>" <?= $fuel_type_id == $fuel_type['fuel_type_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fuel_type['fuel_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <!-- Capacity -->
            <div>
                <label for="capacity" class="block text-sm font-medium text-gray-700">Capacity (Liters) <span class="text-red-600">*</span></label>
                <input type="number" name="capacity" id="capacity" value="<?= htmlspecialchars($capacity) ?>" step="0.01" min="0" required
                      class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <p class="mt-1 text-xs text-gray-500">Maximum volume the tank can hold</p>
            </div>
            
            <!-- Current Volume -->
            <div>
                <label for="current_volume" class="block text-sm font-medium text-gray-700">Current Volume (Liters)</label>
                <input type="number" name="current_volume" id="current_volume" value="<?= htmlspecialchars($current_volume) ?>" step="0.01" min="0"
                      class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <p class="mt-1 text-xs text-gray-500">Current amount of fuel in the tank (0 for empty tank)</p>
            </div>
            
            <!-- Low Level Threshold -->
            <div>
                <label for="low_level_threshold" class="block text-sm font-medium text-gray-700">Low Level Threshold (Liters)</label>
                <input type="number" name="low_level_threshold" id="low_level_threshold" value="<?= htmlspecialchars($low_level_threshold) ?>" step="0.01" min="0"
                      class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <p class="mt-1 text-xs text-gray-500">Volume level at which to trigger low level alerts</p>
            </div>
            
            <!-- Status -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                <select name="status" id="status" 
                       class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="maintenance" <?= $status == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                </select>
            </div>
            
            <!-- Location -->
            <div>
                <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                <input type="text" name="location" id="location" value="<?= htmlspecialchars($location) ?>"
                      class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                <p class="mt-1 text-xs text-gray-500">Physical location of the tank (e.g., "North Side")</p>
            </div>
            
            <!-- Notes -->
            <div class="md:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea name="notes" id="notes" rows="3"
                         class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"><?= htmlspecialchars($notes) ?></textarea>
            </div>
        </div>
        
        <div class="mt-8 border-t border-gray-200 pt-5">
            <div class="flex justify-end">
                <a href="index.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Tank
                </button>
            </div>
        </div>
    </form>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>