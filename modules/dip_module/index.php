<?php
/**
 * Tank Dip Measurements
 * 
 * Manage daily dip measurements for fuel tanks
 */

// Set page title
$page_title = "Tank Dip Measurements";
$breadcrumbs = '<a href="../../index.php">Home</a> / <a href="../tank_management/index.php">Tank Management</a> / Tank Dip Measurements';

// Include header
include_once '../../includes/header.php';

// Include database connection
require_once '../../includes/db.php';

// Include tank functions
require_once '../tank_management/functions.php';

// Initialize variables
$success_message = '';
$error_message = '';
$filter_tank_id = isset($_GET['tank_id']) ? intval($_GET['tank_id']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get all tanks for dropdown
$tanks_query = "SELECT tank_id, tank_name FROM tanks ORDER BY tank_name";
$tanks_result = $conn->query($tanks_query);
$tanks = [];
if ($tanks_result && $tanks_result->num_rows > 0) {
    while ($row = $tanks_result->fetch_assoc()) {
        $tanks[] = $row;
    }
}

// Process form submission for adding/editing dip measurements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        $dip_id = isset($_POST['dip_id']) ? intval($_POST['dip_id']) : 0;
        $tank_id = intval($_POST['tank_id'] ?? 0);
        $measurement_date = $_POST['measurement_date'] ?? date('Y-m-d');
        $dip_value = floatval($_POST['dip_value'] ?? 0);
        $calculated_volume = floatval($_POST['calculated_volume'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate input
        $errors = [];
        if ($tank_id <= 0) {
            $errors[] = "Please select a valid tank";
        }
        
        if (empty($measurement_date)) {
            $errors[] = "Measurement date is required";
        }
        
        if ($dip_value <= 0) {
            $errors[] = "Dip value must be greater than zero";
        }
        
        if (empty($errors)) {
            // Get current user ID from session
            $recorded_by = $_SESSION['user_id'] ?? 1; // Fallback to admin user if session not set
            
            if ($action === 'add') {
                // Check if a measurement already exists for this tank and date
                $check_stmt = $conn->prepare("SELECT dip_id FROM tank_dip_measurements WHERE tank_id = ? AND measurement_date = ?");
                $check_stmt->bind_param("is", $tank_id, $measurement_date);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error_message = "A dip measurement already exists for this tank on the selected date";
                } else {
                    // Insert new dip measurement
                    $stmt = $conn->prepare("INSERT INTO tank_dip_measurements 
                                          (tank_id, measurement_date, dip_value, calculated_volume, notes, recorded_by, created_at) 
                                          VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isddsi", $tank_id, $measurement_date, $dip_value, $calculated_volume, $notes, $recorded_by);
                    
                    if ($stmt->execute()) {
                        $success_message = "Dip measurement added successfully";
                    } else {
                        $error_message = "Error adding dip measurement: " . $stmt->error;
                    }
                }
            } else if ($action === 'edit' && $dip_id > 0) {
                // Update existing dip measurement
                $stmt = $conn->prepare("UPDATE tank_dip_measurements 
                                       SET tank_id = ?, measurement_date = ?, dip_value = ?, 
                                       calculated_volume = ?, notes = ?, updated_at = NOW() 
                                       WHERE dip_id = ?");
                $stmt->bind_param("isddsi", $tank_id, $measurement_date, $dip_value, $calculated_volume, $notes, $dip_id);
                
                if ($stmt->execute()) {
                    $success_message = "Dip measurement updated successfully";
                } else {
                    $error_message = "Error updating dip measurement: " . $stmt->error;
                }
            }
        } else {
            $error_message = "Please correct the following errors:<br>" . implode("<br>", $errors);
        }
    } else if ($action === 'delete' && isset($_POST['dip_id'])) {
        $dip_id = intval($_POST['dip_id']);
        
        // Delete dip measurement
        $stmt = $conn->prepare("DELETE FROM tank_dip_measurements WHERE dip_id = ?");
        $stmt->bind_param("i", $dip_id);
        
        if ($stmt->execute()) {
            $success_message = "Dip measurement deleted successfully";
        } else {
            $error_message = "Error deleting dip measurement: " . $stmt->error;
        }
    }
}

// Set up pagination
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 20;
$offset = ($current_page - 1) * $records_per_page;

// Build query conditions
$conditions = [];
$params = [];
$param_types = '';

if ($filter_tank_id > 0) {
    $conditions[] = 'tdm.tank_id = ?';
    $params[] = $filter_tank_id;
    $param_types .= 'i';
}

if (!empty($filter_date_from)) {
    $conditions[] = 'tdm.measurement_date >= ?';
    $params[] = $filter_date_from;
    $param_types .= 's';
}

if (!empty($filter_date_to)) {
    $conditions[] = 'tdm.measurement_date <= ?';
    $params[] = $filter_date_to;
    $param_types .= 's';
}

// Create WHERE clause
$where_clause = '';
if (!empty($conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $conditions);
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) AS total 
               FROM tank_dip_measurements tdm 
               $where_clause";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get dip measurements with pagination
$query = "SELECT tdm.*, t.tank_name, u.full_name as recorded_by_name
         FROM tank_dip_measurements tdm
         JOIN tanks t ON tdm.tank_id = t.tank_id
         LEFT JOIN users u ON tdm.recorded_by = u.user_id
         $where_clause
         ORDER BY tdm.measurement_date DESC, t.tank_name
         LIMIT $offset, $records_per_page";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$measurements_result = $stmt->get_result();
?>

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

    <!-- Main Content -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <!-- Header with Actions -->
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-ruler-vertical mr-2 text-blue-600"></i>
                Tank Dip Measurements
            </h2>
            <button type="button" onclick="openAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-plus mr-2"></i> Add New Measurement
            </button>
        </div>
        
        <!-- Filter Form -->
        <div class="p-6 bg-gray-50 border-b border-gray-200">
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Tank Filter -->
                    <div>
                        <label for="tank_id" class="block text-sm font-medium text-gray-700">Tank</label>
                        <select name="tank_id" id="tank_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">All Tanks</option>
                            <?php foreach ($tanks as $tank): ?>
                                <option value="<?= $tank['tank_id'] ?>" <?= $filter_tank_id == $tank['tank_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tank['tank_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Date Range Filter -->
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700">From Date</label>
                        <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($filter_date_from) ?>" 
                              class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700">To Date</label>
                        <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($filter_date_to) ?>" 
                              class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 w-full">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Dip Measurements Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Measurement Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tank
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Dip Value (cm)
                        </th>
                        <!-- <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Calculated Volume (L)
                        </th> -->
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Recorded By
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Notes
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($measurements_result && $measurements_result->num_rows > 0): ?>
                        <?php while ($measurement = $measurements_result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('Y-m-d', strtotime($measurement['measurement_date'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($measurement['tank_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($measurement['dip_value'], 2) ?>
                                </td>
                                <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= number_format($measurement['calculated_volume'], 2) ?>
                                </td> -->
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($measurement['recorded_by_name'] ?? 'Unknown') ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= htmlspecialchars($measurement['notes'] ?? '') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button type="button" onclick="openEditModal(<?= json_encode($measurement) ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button type="button" onclick="confirmDelete(<?= $measurement['dip_id'] ?>)" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                No dip measurements found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> entries
                    </div>
                    <div class="flex space-x-1">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&tank_id=<?= $filter_tank_id ?>&date_from=<?= $filter_date_from ?>&date_to=<?= $filter_date_to ?>" 
                               class="<?= $i == $current_page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?> px-4 py-2 text-sm font-medium rounded-md">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="dipModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full" style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3 border-b">
            <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Add Dip Measurement</h3>
            <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="dipForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="mt-4">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="dip_id" id="dip_id" value="">
            
            <div class="space-y-4">
                <!-- Tank Selection -->
                <div>
                    <label for="modal_tank_id" class="block text-sm font-medium text-gray-700">Tank</label>
                    <select name="tank_id" id="modal_tank_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                        <option value="">Select Tank</option>
                        <?php foreach ($tanks as $tank): ?>
                            <option value="<?= $tank['tank_id'] ?>"><?= htmlspecialchars($tank['tank_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Measurement Date -->
                <div>
                    <label for="measurement_date" class="block text-sm font-medium text-gray-700">Measurement Date</label>
                    <input type="date" name="measurement_date" id="measurement_date" value="<?= date('Y-m-d') ?>" 
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                </div>
                
                <!-- Dip Value -->
                <div>
                    <label for="dip_value" class="block text-sm font-medium text-gray-700">Dip Value (cm)</label>
                    <input type="number" name="dip_value" id="dip_value" step="0.01" min="0" 
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                </div>
                
                <!-- Calculated Volume -->
                <div style="display: none;">
                    <label for="calculated_volume" class="block text-sm font-medium text-gray-700">Calculated Volume (L)</label>
                    <input type="number" name="calculated_volume" id="calculated_volume" step="0.01" min="0" 
                           class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required>
                </div>
                
                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" id="notes" rows="3" 
                              class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                </div>
            </div>
            
            <div class="mt-5 flex justify-end">
                <button type="button" onclick="closeModal()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium mr-2 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700">
                    <span id="submitButtonText">Save</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full" style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Confirm Deletion</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to delete this dip measurement? This action cannot be undone.
                </p>
            </div>
            <form id="deleteForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="dip_id" id="delete_dip_id" value="">
                <div class="flex justify-center mt-4 gap-4">
                    <button type="button" onclick="closeDeleteModal()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200">
                        Cancel
                    </button>
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-red-700">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Modal functions
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Dip Measurement';
        document.getElementById('formAction').value = 'add';
        document.getElementById('dip_id').value = '';
        document.getElementById('modal_tank_id').value = '';
        document.getElementById('measurement_date').value = '<?= date('Y-m-d') ?>';
        document.getElementById('dip_value').value = '';
        document.getElementById('calculated_volume').value = '';
        document.getElementById('notes').value = '';
        document.getElementById('submitButtonText').textContent = 'Save';
        document.getElementById('dipModal').classList.remove('hidden');
    }
    
    function openEditModal(measurement) {
        document.getElementById('modalTitle').textContent = 'Edit Dip Measurement';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('dip_id').value = measurement.dip_id;
        document.getElementById('modal_tank_id').value = measurement.tank_id;
        document.getElementById('measurement_date').value = measurement.measurement_date.substring(0, 10); // Format date to YYYY-MM-DD
        document.getElementById('dip_value').value = measurement.dip_value;
        document.getElementById('calculated_volume').value = measurement.calculated_volume;
        document.getElementById('notes').value = measurement.notes || '';
        document.getElementById('submitButtonText').textContent = 'Update';
        document.getElementById('dipModal').classList.remove('hidden');
    }
    
    function closeModal() {
        document.getElementById('dipModal').classList.add('hidden');
    }
    
    function confirmDelete(dipId) {
        document.getElementById('delete_dip_id').value = dipId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
    
    // Add event listener for dip value to calculate volume (example calculation)
    document.getElementById('dip_value').addEventListener('input', function() {
        const dipValue = parseFloat(this.value) || 0;
        const tankId = document.getElementById('modal_tank_id').value;
        
        // This is a simplified calculation - in a real application, you would need 
        // tank-specific calibration data to convert dip measurements to volume
        if (tankId && dipValue > 0) {
            // Example calculation - replace with actual tank calibration formula
            const calculatedVolume = dipValue * 100; // Simple example: 1cm = 100L
            document.getElementById('calculated_volume').value = calculatedVolume.toFixed(2);
        }
    });
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>
