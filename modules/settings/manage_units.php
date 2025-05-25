<?php
/**
 * Simple Units Management
 * 
 * This page allows administrators to manage simple measurement units
 */

$page_title = "Measurement Units";
$breadcrumbs = '<a href="../../index.php">Dashboard</a> / <a href="../settings/index.php">Settings</a> / <span class="text-gray-700">Measurement Units</span>';
include_once '../../includes/header.php';
require_once '../../includes/db.php';

// Check permission (only admin and manager can access)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p>You do not have permission to access this page.</p>
          </div>';
    include_once '../../includes/footer.php';
    exit;
}

// Process form submission for adding/editing unit
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_unit']) || isset($_POST['edit_unit'])) {
        // Extract form data
        $unit_id = $_POST['unit_id'] ?? null;
        $unit_name = trim($_POST['unit_name']);
        $unit_symbol = trim($_POST['unit_symbol']);
        $status = $_POST['status'];
        
        // Validate data
        if (empty($unit_name) || empty($unit_symbol)) {
            $error_message = "Please fill all required fields";
        } else {
            try {
                if (isset($_POST['add_unit'])) {
                    // Add new unit
                    $stmt = $conn->prepare("
                        INSERT INTO units (unit_name, unit_symbol, status) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("sss", $unit_name, $unit_symbol, $status);
                    $stmt->execute();
                    $success_message = "Unit added successfully";
                } else {
                    // Update existing unit
                    $stmt = $conn->prepare("
                        UPDATE units SET unit_name = ?, unit_symbol = ?, status = ?
                        WHERE unit_id = ?
                    ");
                    $stmt->bind_param("sssi", $unit_name, $unit_symbol, $status, $unit_id);
                    $stmt->execute();
                    $success_message = "Unit updated successfully";
                }
                $stmt->close();
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    } 
    // Process unit deletion
    elseif (isset($_POST['delete_unit'])) {
        $unit_id = $_POST['unit_id'] ?? 0;
        
        try {
            // Check if unit is in use
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as product_count
                FROM products
                WHERE unit = ?
            ");
            $checkStmt->bind_param("i", $unit_id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
            
            if ($row['product_count'] > 0) {
                $error_message = "Cannot delete this unit because it is being used by products";
            } else {
                // Delete the unit
                $stmt = $conn->prepare("DELETE FROM units WHERE unit_id = ?");
                $stmt->bind_param("i", $unit_id);
                $stmt->execute();
                $stmt->close();
                $success_message = "Unit deleted successfully";
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get all units
$units = [];
$stmt = $conn->prepare("
    SELECT * FROM units ORDER BY unit_name
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $units[] = $row;
}
$stmt->close();
?>

<!-- Main content -->
<div class="container mx-auto pb-6">
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($success_message) ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?= htmlspecialchars($error_message) ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Add New Unit Button -->
    <div class="mb-6">
        <button type="button" id="add-unit-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus mr-2"></i> Add New Unit
        </button>
    </div>
    
    <!-- Units List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Measurement Units</h2>
            <p class="text-sm text-gray-600">Manage all measurement units used across the system</p>
        </div>
        
        <!-- Units table -->
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($units)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-sm text-center text-gray-500">No units found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($units as $unit): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?= htmlspecialchars($unit['unit_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium">
                                    <?= htmlspecialchars($unit['unit_symbol']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $statusClass = $unit['status'] === 'active' 
                                        ? 'bg-green-100 text-green-800' 
                                        : 'bg-red-100 text-red-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusClass ?>">
                                        <?= ucfirst(htmlspecialchars($unit['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button type="button" class="edit-unit text-blue-600 hover:text-blue-900 mr-3" 
                                            data-unit='<?= json_encode($unit) ?>'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button type="button" class="delete-unit text-red-600 hover:text-red-900"
                                            data-id="<?= $unit['unit_id'] ?>" 
                                            data-name="<?= htmlspecialchars($unit['unit_name']) ?>">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Unit Modal -->
<div id="unit-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800" id="modal-title">Add New Unit</h3>
        </div>
        
        <form id="unit-form" method="POST">
            <input type="hidden" id="unit_id" name="unit_id">
            
            <div class="p-6 space-y-4">
                <div>
                    <label for="unit_name" class="block text-sm font-medium text-gray-700">Unit Name <span class="text-red-500">*</span></label>
                    <input type="text" id="unit_name" name="unit_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    <p class="text-xs text-gray-500 mt-1">Example: Liter, Piece, Box, Tank, etc.</p>
                </div>
                
                <div>
                    <label for="unit_symbol" class="block text-sm font-medium text-gray-700">Unit Symbol <span class="text-red-500">*</span></label>
                    <input type="text" id="unit_symbol" name="unit_symbol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50" required>
                    <p class="text-xs text-gray-500 mt-1">Short symbol like 'L', 'Pcs', 'Box', etc.</p>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" id="cancel-btn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="submit" id="submit-btn" name="add_unit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Unit
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Confirm Deletion</h3>
        </div>
        
        <div class="p-6">
            <p class="text-gray-700">Are you sure you want to delete the unit "<span id="delete-unit-name"></span>"?</p>
            <p class="text-sm text-red-600 mt-2">This action cannot be undone.</p>
        </div>
        
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
            <button type="button" id="delete-cancel-btn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Cancel
            </button>
            <form method="POST">
                <input type="hidden" id="delete_unit_id" name="unit_id">
                <button type="submit" name="delete_unit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Delete Unit
                </button>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for modal functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Unit Modal Functionality
    const unitModal = document.getElementById('unit-modal');
    const unitForm = document.getElementById('unit-form');
    const modalTitle = document.getElementById('modal-title');
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const addUnitBtn = document.getElementById('add-unit-btn');
    
    // Add new unit button
    addUnitBtn.addEventListener('click', function() {
        resetForm();
        modalTitle.textContent = 'Add New Unit';
        submitBtn.textContent = 'Add Unit';
        submitBtn.name = 'add_unit';
        unitModal.classList.remove('hidden');
    });
    
    // Cancel button
    cancelBtn.addEventListener('click', function() {
        unitModal.classList.add('hidden');
    });
    
    // Edit unit buttons
    document.querySelectorAll('.edit-unit').forEach(button => {
        button.addEventListener('click', function() {
            const unitData = JSON.parse(this.getAttribute('data-unit'));
            
            // Fill the form
            document.getElementById('unit_id').value = unitData.unit_id;
            document.getElementById('unit_name').value = unitData.unit_name;
            document.getElementById('unit_symbol').value = unitData.unit_symbol;
            document.getElementById('status').value = unitData.status;
            
            // Update modal
            modalTitle.textContent = 'Edit Unit';
            submitBtn.textContent = 'Update Unit';
            submitBtn.name = 'edit_unit';
            unitModal.classList.remove('hidden');
        });
    });
    
    // Delete unit buttons
    document.querySelectorAll('.delete-unit').forEach(button => {
        button.addEventListener('click', function() {
            const unitId = this.getAttribute('data-id');
            const unitName = this.getAttribute('data-name');
            
            document.getElementById('delete_unit_id').value = unitId;
            document.getElementById('delete-unit-name').textContent = unitName;
            
            document.getElementById('delete-modal').classList.remove('hidden');
        });
    });
    
    // Delete cancel button
    document.getElementById('delete-cancel-btn').addEventListener('click', function() {
        document.getElementById('delete-modal').classList.add('hidden');
    });
    
    // Reset form
    function resetForm() {
        unitForm.reset();
        document.getElementById('unit_id').value = '';
    }
    
    // Close modal when clicking outside
    unitModal.addEventListener('click', function(e) {
        if (e.target === unitModal) {
            unitModal.classList.add('hidden');
        }
    });
    
    document.getElementById('delete-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>